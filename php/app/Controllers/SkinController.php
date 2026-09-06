<?php
/**
 * 皮肤库控制器：列表/详情/上传/修改/删除/下载
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;
use App\Auth;

class SkinController
{
    /** GET /api/skinlib/list */
    public function list(): never
    {
        Auth::optional();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;
        $sort = $_GET['sort'] ?? 'upload_at';
        $order = strtoupper($_GET['order'] ?? 'desc');
        $type = $_GET['type'] ?? '';
        $keyword = $_GET['keyword'] ?? '';
        $uploader = $_GET['uploader'] ?? '';

        $where = 'WHERE t.public = 1';
        $params = [];
        if ($type) { $where .= ' AND t.type = ?'; $params[] = $type; }
        if ($keyword) { $where .= ' AND t.name LIKE ?'; $params[] = "%{$keyword}%"; }
        if ($uploader) { $where .= ' AND t.uploader = ?'; $params[] = (int) $uploader; }

        $allowedSorts = ['upload_at', 'likes', 'name'];
        $sortField = in_array($sort, $allowedSorts, true) ? $sort : 'upload_at';
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $count = Database::get("SELECT COUNT(*) as total FROM textures t {$where}", $params);
        $items = Database::all(
            "SELECT t.*, u.nickname as owner_nickname
             FROM textures t
             LEFT JOIN users u ON t.uploader = u.uid
             {$where}
             ORDER BY t.{$sortField} {$order}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(fn($item) => self::fmt($item), $items);

        Response::data([
            'code' => 0,
            'data' => $data,
            'total' => (int) $count['total'],
            'total_count' => (int) $count['total'],
            'current_page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /** GET /api/skinlib/stats 公开站点统计（首页用） */
    public function stats(): never
    {
        $skins = Database::get('SELECT COUNT(*) as cnt FROM textures WHERE public = 1');
        $users = Database::get('SELECT COUNT(*) as cnt FROM users');
        $today = Database::get("SELECT COUNT(*) as cnt FROM textures WHERE public = 1 AND DATE(upload_at) = CURDATE()");
        $downloads = Database::get('SELECT COALESCE(SUM(downloads), 0) as cnt FROM textures WHERE public = 1');

        Response::data([
            'code' => 0,
            'data' => [
                'skins' => (int) $skins['cnt'],
                'users' => (int) $users['cnt'],
                'today_uploads' => (int) $today['cnt'],
                'downloads' => (int) $downloads['cnt'],
            ],
        ]);
    }

    /** GET /api/skinlib/show/:tid */
    public function show(int $tid): never
    {
        Auth::optional();
        $item = Database::get(
            'SELECT t.*, u.nickname as owner_nickname
             FROM textures t
             LEFT JOIN users u ON t.uploader = u.uid
             WHERE t.tid = ?',
            [$tid]
        );
        if (!$item) {
            Response::notFound('皮肤不存在');
        }
        Response::data(self::fmt($item));
    }

    /** POST /api/texture 上传 */
    public function upload(): never
    {
        $user = Auth::require();

        if (empty($_FILES['file'])) {
            Response::error('请选择文件', 422, 422);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('文件上传失败', 422, 422);
        }

        // A) PNG 校验（不依赖 fileinfo 扩展，通过文件头签名判断）
        $handle = fopen($file['tmp_name'], 'rb');
        $head = $handle ? fread($handle, 8) : '';
        if ($handle) fclose($handle);
        // PNG 固定签名：\x89PNG\r\n\x1a\n
        if ($head !== "\x89PNG\r\n\x1a\n") {
            @unlink($file['tmp_name']);
            Response::error('仅支持 PNG 格式', 422, 422);
        }

        // B) 尺寸限制
        $maxKb = (int) Config::get('upload.max_size', 51200);
        if ($file['size'] > $maxKb * 1024) {
            @unlink($file['tmp_name']);
            Response::error("文件大小不能超过 " . round($maxKb / 1024, 1) . " MB", 422, 422);
        }

        // C) 生成 hash 并存储
        $binary = file_get_contents($file['tmp_name']);
        $hash = hash('md5', $binary);

        if (!is_dir(BS_TEXTURES)) @mkdir(BS_TEXTURES, 0777, true);
        if (!is_writable(BS_TEXTURES)) {
            \App\Log::error('材质目录不可写', ['dir' => BS_TEXTURES]);
            Response::error('材质目录不可写，请检查 ' . BS_TEXTURES . ' 权限', 500, 500);
        }

        $target = BS_TEXTURES . '/' . $hash . '.png';
        if (!is_file($target)) {
            $moved = @move_uploaded_file($file['tmp_name'], $target);
            if (!$moved && !is_file($target)) {
                \App\Log::error('保存材质文件失败', ['from' => $file['tmp_name'], 'to' => $target, 'err' => error_get_last()['message'] ?? ''] );
                Response::error('保存皮肤文件失败，请检查目录权限', 500, 500);
            }
        }

        $name = $_POST['name'] ?? preg_replace('/\.png$/i', '', $file['name']);
        $type = $_POST['type'] ?? 'steve';
        // 仅允许 steve / alex(皮肤) 或 cape(披风)
        if (!in_array($type, ['steve', 'alex', 'cape'], true)) {
            $type = 'steve';
        }
        $isPublic = ($_POST['public'] ?? '1') === '0' ? 0 : 1;

        $result = Database::run(
            'INSERT INTO textures (name, type, hash, size, uploader, public) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $type, $hash, (int) $file['size'], $user['uid'], $isPublic]
        );
        $tid = (int) $result['lastInsertRowid'];

        // 上传成功后自动加入衣柜（同一用户同一材质只存一次）
        $alreadyInCloset = Database::get('SELECT id FROM user_closet WHERE uid = ? AND tid = ?', [$user['uid'], $tid]);
        if (!$alreadyInCloset) {
            Database::run('INSERT INTO user_closet (uid, tid, item_name) VALUES (?, ?, ?)', [$user['uid'], $tid, $name]);
        }

        // 公开材质 -> 更新站点地图
        if ($isPublic) {
            \App\Sitemap::generate();
        }

        Response::data([
            'code' => 0,
            'message' => '上传成功',
            'tid' => $tid,
            'data' => ['tid' => $tid],
        ]);
    }

    /** PUT /api/texture/:tid/type */
    public function updateType(int $tid): never
    {
        $type = json_body()['type'] ?? '';
        self::guard($tid);
        Database::run('UPDATE textures SET type = ? WHERE tid = ?', [$type, $tid]);
        Response::data(['code' => 0, 'message' => '更新成功']);
    }

    /** PUT /api/texture/:tid/name */
    public function updateName(int $tid): never
    {
        $name = json_body()['name'] ?? '';
        self::guard($tid);
        Database::run('UPDATE textures SET name = ? WHERE tid = ?', [$name, $tid]);
        Response::data(['code' => 0, 'message' => '更新成功']);
    }

    /** PUT /api/texture/:tid/privacy */
    public function updatePrivacy(int $tid): never
    {
        $public = (bool) (json_body()['public'] ?? false);
        self::guard($tid);
        Database::run('UPDATE textures SET public = ? WHERE tid = ?', [$public ? 1 : 0, $tid]);
        // 公开状态变化影响站点地图
        \App\Sitemap::generate();
        Response::data(['code' => 0, 'message' => '更新成功']);
    }

    /** DELETE /api/texture/:tid */
    public function delete(int $tid): never
    {
        self::guard($tid);
        $item = Database::get('SELECT * FROM textures WHERE tid = ?', [$tid]);
        $target = BS_TEXTURES . '/' . $item['hash'] . '.png';
        if (is_file($target)) @unlink($target);
        Database::run('DELETE FROM textures WHERE tid = ?', [$tid]);
        // 删除后同步站点地图
        \App\Sitemap::generate();
        Response::data(['code' => 0, 'message' => '删除成功']);
    }

    /** GET /api/texture/download/:tid */
    public function download(int $tid): never
    {
        $item = Database::get('SELECT * FROM textures WHERE tid = ?', [$tid]);
        if (!$item) {
            Response::notFound('皮肤不存在');
        }
        Database::run('UPDATE textures SET downloads = downloads + 1 WHERE tid = ?', [$tid]);
        $target = BS_TEXTURES . '/' . $item['hash'] . '.png';
        if (!is_file($target)) {
            Response::notFound('文件不存在');
        }
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $item['name'] . '.png"');
        readfile($target);
        exit;
    }

    /** 格式化为接口返回结构 */
    private static function fmt(array $item): array
    {
        return [
            'tid' => (int) $item['tid'],
            'name' => $item['name'],
            'type' => $item['type'],
            'hash' => $item['hash'],
            'size' => (int) $item['size'],
            'uploader' => (int) $item['uploader'],
            'public' => (bool) $item['public'],
            'likes' => (int) $item['likes'],
            'downloads' => (int) ($item['downloads'] ?? 0),
            'upload_at' => $item['upload_at'],
            'model' => ($item['type'] === 'alex') ? 'slim' : 'default',
            'owner' => ['nickname' => $item['owner_nickname'] ?? null],
        ];
    }

    /** 校验是否为本人皮肤或管理员 */
    private static function guard(int $tid): void
    {
        $user = Auth::require();
        $item = Database::get('SELECT * FROM textures WHERE tid = ?', [$tid]);
        if (!$item) {
            Response::notFound('皮肤不存在');
        }
        if ((int) $item['uploader'] !== $user['uid'] && $user['permission'] < 1) {
            Response::forbidden('权限不足');
        }
    }
}