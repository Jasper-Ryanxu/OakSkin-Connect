<?php
/**
 * 衣柜控制器
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Response;
use App\Auth;

class ClosetController
{
    /** GET /api/user/closet/list */
    public function list(): never
    {
        $user = Auth::require();
        $items = Database::all(
            'SELECT t.*, uc.item_name, uc.created_at
             FROM user_closet uc
             JOIN textures t ON uc.tid = t.tid
             WHERE uc.uid = ?
             ORDER BY uc.created_at DESC',
            [$user['uid']]
        );

        $data = array_map(function ($item) {
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
                'pivot' => [
                    'item_name' => $item['item_name'],
                    'created_at' => $item['created_at'],
                ],
            ];
        }, $items);

        Response::data(['data' => $data]);
    }

    /** GET /api/user/closet/ids */
    public function ids(): never
    {
        $user = Auth::require();
        $items = Database::all('SELECT tid FROM user_closet WHERE uid = ?', [$user['uid']]);
        $ids = array_map(fn($i) => (int) $i['tid'], $items);
        Response::data($ids);
    }

    /** POST /api/user/closet */
    public function add(): never
    {
        $user = Auth::require();
        $body = json_body();
        $tid = (int) ($body['tid'] ?? 0);
        $itemName = $body['item_name'] ?? '';

        $skin = Database::get('SELECT tid FROM textures WHERE tid = ?', [$tid]);
        if (!$skin) {
            Response::notFound('皮肤不存在');
        }

        $existing = Database::get('SELECT id FROM user_closet WHERE uid = ? AND tid = ?', [$user['uid'], $tid]);
        if ($existing) {
            Response::error('该皮肤已在衣柜中', 422, 422);
        }

        Database::run('INSERT INTO user_closet (uid, tid, item_name) VALUES (?, ?, ?)', [$user['uid'], $tid, $itemName]);
        Database::run('UPDATE textures SET likes = likes + 1 WHERE tid = ?', [$tid]);
        Response::data(['code' => 0, 'message' => '已添加到衣柜']);
    }

    /** PUT /api/user/closet/:tid */
    public function rename(int $tid): never
    {
        $user = Auth::require();
        $itemName = json_body()['item_name'] ?? '';
        Database::run('UPDATE user_closet SET item_name = ? WHERE uid = ? AND tid = ?', [$itemName, $user['uid'], $tid]);
        Response::data(['code' => 0, 'message' => '更新成功']);
    }

    /** DELETE /api/user/closet/:tid */
    public function remove(int $tid): never
    {
        $user = Auth::require();
        Database::run('DELETE FROM user_closet WHERE uid = ? AND tid = ?', [$user['uid'], $tid]);
        Database::run('UPDATE textures SET likes = GREATEST(0, likes - 1) WHERE tid = ?', [$tid]);
        Response::data(['code' => 0, 'message' => '已移除']);
    }
}