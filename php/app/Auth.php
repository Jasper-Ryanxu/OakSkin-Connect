<?php
/**
 * 鉴权辅助：Bearer token 解析与当前用户
 */
declare(strict_types=1);

namespace App;

class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** 获取 Authorization Bearer token，无则 null */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        // 兼容部分主机把 Authorization 放入 REDIRECT_HTTP_AUTHORIZATION
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /** 校验 token 并返回用户（未过期的 token 计入 users 关联信息），无效返回 null */
    public static function attempt(): ?array
    {
        $token = self::bearerToken();
        if (!$token) return null;

        $row = Database::get(
            'SELECT t.uid, t.expires_at, u.* FROM tokens t JOIN users u ON t.uid = u.uid WHERE t.token = ?',
            [$token]
        );
        if (!$row) return null;

        if (strtotime($row['expires_at']) < time()) {
            Database::run('DELETE FROM tokens WHERE token = ?', [$token]);
            return null;
        }

        return self::map($row);
    }

    private static function map(array $u): array
    {
        return [
            'uid' => (int) $u['uid'],
            'email' => $u['email'],
            'nickname' => $u['nickname'],
            'permission' => (int) $u['permission'],
            'score' => (int) ($u['score'] ?? 0),
            'avatar' => (int) ($u['avatar'] ?? 0),
            'verified' => (int) ($u['verified'] ?? 1),
            'is_dark_mode' => (int) ($u['is_dark_mode'] ?? 1),
        ];
    }

    /** 必填鉴权：未登录则 401 */
    public static function require(): array
    {
        $user = self::user();
        if (!$user) {
            \App\Response::unauthorized();
        }
        return $user;
    }

    /** 获取当前用户（惰性加载） */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$user = self::attempt();
            self::$loaded = true;
        }
        return self::$user;
    }

    /** 可选鉴权：不设置当前用户也不报错 */
    public static function optional(): ?array
    {
        return self::user();
    }

    /** 管理员门槛：permission >= 1 */
    public static function requireAdmin(): array
    {
        $user = self::require();
        if ($user['permission'] < 1) {
            \App\Response::forbidden();
        }
        return $user;
    }

    public static function resetUser(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}