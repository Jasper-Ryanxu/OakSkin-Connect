<?php
/**
 * 管理控制器
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;
use App\Auth;
use App\Log;

class AdminController
{
    /** GET /api/admin/stats */
    public function stats(): never
    {
        Auth::requireAdmin();
        $users = Database::get('SELECT COUNT(*) as cnt FROM users');
        $skins = Database::get('SELECT COUNT(*) as cnt FROM textures');
        $players = Database::get('SELECT COUNT(*) as cnt FROM players');
        $today = Database::get("SELECT COUNT(*) as cnt FROM textures WHERE DATE(upload_at) = CURDATE()");

        Response::data([
            'code' => 0,
            'data' => [
                'users' => (int) $users['cnt'],
                'skins' => (int) $skins['cnt'],
                'players' => (int) $players['cnt'],
                'today_uploads' => (int) $today['cnt'],
            ],
        ]);
    }

    /** GET /api/admin/users */
    public function users(): never
    {
        Auth::requireAdmin();
        $keyword = $_GET['keyword'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($keyword) {
            $where = 'WHERE email LIKE ? OR nickname LIKE ?';
            $params = ["%{$keyword}%", "%{$keyword}%"];
        }

        $count = Database::get("SELECT COUNT(*) as cnt FROM users {$where}", $params);
        $users = Database::all(
            "SELECT uid, email, nickname, permission, score, avatar, verified, register_at
             FROM users {$where} ORDER BY uid DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        Response::data([
            'code' => 0,
            'data' => $users,
            'total' => (int) $count['cnt'],
            'current_page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /** PUT /api/admin/users/:uid/permission */
    public function updatePermission(int $uid): never
    {
        $current = Auth::requireAdmin();
        $permission = (int) (json_body()['permission'] ?? 0);

        $target = Database::get('SELECT uid, permission, nickname FROM users WHERE uid = ?', [$uid]);
        if (!$target) {
            Response::notFound('用户不存在');
        }

        if ($uid === (int) $current['uid']) {
            Response::forbidden('不能修改自己的权限');
        }
        if ((int) $target['permission'] >= 2 && (int) $current['permission'] < 2) {
            Response::forbidden('无权修改站长的权限');
        }
        if ($permission === 1 && (int) $current['permission'] < 2) {
            Response::forbidden('只有站长可以设置管理员');
        }
        if ($permission < -1 || $permission > 2) {
            Response::error('无效的权限值', 422, 422);
        }

        Database::run('UPDATE users SET permission = ? WHERE uid = ?', [$permission, $uid]);

        $roles = [2 => '超级管理员', 1 => '管理员', 0 => '用户', -1 => '封禁'];
        Response::data([
            'code' => 0,
            'message' => "已将 {$target['nickname']} 的权限设置为 " . ($roles[$permission] ?? $permission),
        ]);
    }

    /** GET /api/admin/settings */
    public function settings(): never
    {
        Auth::requireAdmin();
        Response::data([
            'code' => 0,
            'data' => [
                'upload_max_size' => (int) Config::get('upload.max_size', 51200),
                'announcement' => (string) Config::get('announcement.content', ''),
                'site_name' => (string) Config::get('site.name', 'OakSkin Connect'),
                'seo_keywords' => (string) Config::get('seo.keywords', ''),
                'seo_description' => (string) Config::get('seo.description', ''),
                'friend_links' => (array) Config::get('friend_links', []),
                'oakskin' => [
                    'client_id' => (string) Config::get('oauth.oakskin.client_id', ''),
                    'client_secret' => (string) Config::get('oauth.oakskin.client_secret', ''),
                    'redirect_uri' => (string) Config::get('oauth.oakskin.redirect_uri', 'https://mcskin.oak-ms.top/callback/oakskin'),
                ],
            ],
        ]);
    }

    /** PUT /api/admin/settings */
    public function updateSettings(): never
    {
        Auth::requireAdmin();
        $body = json_body();
        $config = Config::load();

        if (isset($body['upload_max_size'])) {
            $size = (int) $body['upload_max_size'];
            if ($size < 1 || $size > 512000) {
                Response::error('上传大小限制必须在 1-512000 KB 之间', 422, 422);
            }
            $config['upload']['max_size'] = $size; // 单位 KB
            Log::info("Admin: 上传大小限制已更新为 {$size} KB");
        }

        if (isset($body['announcement'])) {
            $config['announcement']['content'] = (string) $body['announcement'];
            Log::info('Admin: 公告已更新');
        }

        if (isset($body['site_name'])) {
            $config['site']['name'] = trim((string) $body['site_name']) ?: 'OakSkin Connect';
            Log::info('Admin: 站点名称已更新');
        }

        if (isset($body['seo_keywords'])) {
            $config['seo']['keywords'] = (string) $body['seo_keywords'];
        }

        if (isset($body['seo_description'])) {
            $config['seo']['description'] = (string) $body['seo_description'];
        }

        if (isset($body['friend_links'])) {
            $links = [];
            foreach ((array) $body['friend_links'] as $fl) {
                if (!is_array($fl)) continue;
                $name = trim((string) ($fl['name'] ?? ''));
                $url = trim((string) ($fl['url'] ?? ''));
                if ($name === '' || $url === '') continue;
                $links[] = ['name' => $name, 'url' => $url];
            }
            $config['friend_links'] = $links;
            Log::info('Admin: 友情链接已更新');
        }

        if (isset($body['oakskin']) && is_array($body['oakskin'])) {
            $config['oauth'] = $config['oauth'] ?? [];
            $config['oauth']['oakskin'] = $config['oauth']['oakskin'] ?? [];
            foreach (['client_id', 'client_secret', 'redirect_uri'] as $k) {
                if (array_key_exists($k, $body['oakskin'])) {
                    $config['oauth']['oakskin'][$k] = (string) $body['oakskin'][$k];
                }
            }
            Log::info('Admin: OakSkin 第三方登录配置已更新');
        }

        if (!Config::write($config)) {
            Response::error('保存设置失败', 500, 500);
        }

        Response::data([
            'code' => 0,
            'message' => '设置已保存',
        ]);
    }
}