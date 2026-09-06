<?php
/**
 * 角色控制器：角色列表/添加/重命名/设皮肤/清除/删除
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Response;
use App\Auth;

class PlayerController
{
    /** GET /api/user/player/list */
    public function list(): never
    {
        $user = Auth::require();
        $players = Database::all(
            'SELECT p.*,
                    t.name as skin_name, t.hash as skin_hash, t.type as skin_type,
                    c.name as cape_name, c.hash as cape_hash, c.type as cape_type
             FROM players p
             LEFT JOIN textures t ON p.tid_skin = t.tid
             LEFT JOIN textures c ON p.tid_cape = c.tid
             WHERE p.uid = ? AND p.name <> \'\'
             ORDER BY p.last_modified DESC',
            [$user['uid']]
        );

        $data = array_map(function ($p) {
            $hasSkin = (int) $p['tid_skin'] > 0;
            $hasCape = (int) $p['tid_cape'] > 0;
            return [
                'pid' => (int) $p['pid'],
                'uid' => (int) $p['uid'],
                'name' => $p['name'],
                'tid_skin' => $hasSkin ? (int) $p['tid_skin'] : null,
                'tid_cape' => $hasCape ? (int) $p['tid_cape'] : null,
                'last_modified' => $p['last_modified'],
                'model' => ($p['skin_type'] === 'alex') ? 'slim' : 'default',
                'skin' => $hasSkin ? [
                    'tid' => (int) $p['tid_skin'],
                    'name' => $p['skin_name'],
                    'hash' => $p['skin_hash'],
                    'type' => $p['skin_type'],
                ] : null,
                'cape' => $hasCape ? [
                    'tid' => (int) $p['tid_cape'],
                    'name' => $p['cape_name'],
                    'hash' => $p['cape_hash'],
                    'type' => $p['cape_type'],
                ] : null,
            ];
        }, $players);

        Response::data(['data' => $data]);
    }

    /** POST /api/user/player 添加角色 */
    public function add(): never
    {
        $user = Auth::require();
        $name = trim(json_body()['name'] ?? '');
        if ($name === '') {
            Response::error('角色名不能为空', 422, 422);
        }
        if (preg_match('/[^a-zA-Z0-9_]/', $name)) {
            Response::error('角色名只能包含字母、数字和下划线', 422, 422);
        }

        $existing = Database::get('SELECT pid FROM players WHERE name = ?', [$name]);
        if ($existing) {
            Response::error('该角色名已被占用', 422, 422);
        }

        $count = Database::get('SELECT COUNT(*) as cnt FROM players WHERE uid = ?', [$user['uid']]);
        if ((int) $count['cnt'] >= 10) {
            Response::error('最多只能添加10个角色', 422, 422);
        }

        $result = Database::run('INSERT INTO players (uid, name) VALUES (?, ?)', [$user['uid'], $name]);
        Response::data(['code' => 0, 'message' => '添加成功', 'pid' => $result['lastInsertRowid']]);
    }

    /** PUT /api/user/player/:pid/name */
    public function rename(int $pid): never
    {
        $user = Auth::require();
        $name = json_body()['name'] ?? '';
        self::own($pid, $user);
        Database::run('UPDATE players SET name = ?, last_modified = NOW() WHERE pid = ?', [$name, $pid]);
        Response::data(['code' => 0, 'message' => '更新成功']);
    }

    /** PUT /api/user/player/:pid/textures */
    public function setTextures(int $pid): never
    {
        $user = Auth::require();
        $body = json_body();
        $tidSkin = $body['tid_skin'] ?? null;
        $tidCape = $body['tid_cape'] ?? null;
        self::own($pid, $user);

        if ($tidSkin) {
            $skin = Database::get('SELECT tid FROM textures WHERE tid = ?', [$tidSkin]);
            if (!$skin) {
                Response::notFound('皮肤不存在');
            }
        }

        Database::run(
            'UPDATE players SET tid_skin = ?, tid_cape = ?, last_modified = NOW() WHERE pid = ?',
            [$tidSkin ? (int) $tidSkin : null, $tidCape ? (int) $tidCape : null, $pid]
        );
        Response::data(['code' => 0, 'message' => '皮肤已更新']);
    }

    /** DELETE /api/user/player/:pid/textures */
    public function clearTextures(int $pid): never
    {
        $user = Auth::require();
        self::own($pid, $user);
        Database::run("UPDATE players SET tid_skin = NULL, tid_cape = NULL, last_modified = NOW() WHERE pid = ?", [$pid]);
        Response::data(['code' => 0, 'message' => '皮肤已清除']);
    }

    /** DELETE /api/user/player/:pid */
    public function delete(int $pid): never
    {
        $user = Auth::require();
        self::own($pid, $user);
        Database::run('DELETE FROM players WHERE pid = ?', [$pid]);
        Response::data(['code' => 0, 'message' => '删除成功']);
    }

    /** 校验角色归属 */
    private static function own(int $pid, array $user): void
    {
        $player = Database::get('SELECT * FROM players WHERE pid = ? AND uid = ?', [$pid, $user['uid']]);
        if (!$player) {
            Response::notFound('角色不存在');
        }
    }
}