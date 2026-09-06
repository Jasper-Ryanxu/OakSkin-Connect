<?php
/**
 * 用户控制器：当前用户信息 / 资料 / 头像
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Response;
use App\Auth;

class UserController
{
    /** GET /api/user */
    public function me(): never
    {
        Response::data(Auth::require());
    }

    /** POST /api/user/profile */
    public function updateProfile(): never
    {
        $user = Auth::require();
        $body = json_body();
        $nickname = $body['nickname'] ?? '';
        $password = $body['password'] ?? '';
        $currentPassword = $body['current_password'] ?? '';

        if ($nickname) {
            Database::run('UPDATE users SET nickname = ? WHERE uid = ?', [$nickname, $user['uid']]);
        }

        if ($password && $currentPassword) {
            $row = Database::get('SELECT * FROM users WHERE uid = ?', [$user['uid']]);
            if (!$row || !verify_password($currentPassword, $row['password'])) {
                Response::error('当前密码错误', 422, 422);
            }
            Database::run('UPDATE users SET password = ? WHERE uid = ?', [hash_password($password), $user['uid']]);
        }

        Auth::resetUser();
        Response::data(['code' => 0, 'message' => '资料已更新']);
    }

    /** POST /api/user/avatar 设置头像 */
    public function setAvatar(): never
    {
        $user = Auth::require();
        $body = json_body();
        $tid = (int) ($body['tid'] ?? 0);
        if (!$tid) {
            Response::error('缺少参数', 422, 422);
        }
        $skin = Database::get('SELECT tid FROM textures WHERE tid = ?', [$tid]);
        if (!$skin) {
            Response::notFound('皮肤不存在');
        }
        Database::run('UPDATE users SET avatar = ? WHERE uid = ?', [$tid, $user['uid']]);
        Response::data(['code' => 0, 'message' => '头像已更新']);
    }

    /** GET /api/user/:uid/avatar 获取头像（重定向到皮肤头） */
    public function getAvatar(int $uid): never
    {
        $user = Database::get('SELECT avatar FROM users WHERE uid = ?', [$uid]);
        if (!$user || !$user['avatar']) {
            self::redirectDefault();
        }
        $skin = Database::get('SELECT hash FROM textures WHERE tid = ?', [(int) $user['avatar']]);
        if (!$skin) {
            self::redirectDefault();
        }
        header('Location: /textures/' . $skin['hash'] . '.png');
        exit;
    }

    private static function redirectDefault(): never
    {
        header('Location: /steve.png');
        exit;
    }
}