<?php
/**
 * Yggdrasil 令牌存储
 *
 * 原 Node 版用内存 Map，PHP 是无状态运行，因此改用数据库表 ygg_tokens 持久化。
 */
declare(strict_types=1);

namespace App\Yggdrasil;

use App\Database;

class Token
{
    public static function generateAccessToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** 签发令牌，返回 accessToken */
    public static function issue(string $owner, string $clientToken = '', string $profileId = ''): string
    {
        $accessToken = self::generateAccessToken();
        Database::run(
            'INSERT INTO ygg_tokens (access_token, owner, client_token, profile_id, created_at) VALUES (?, ?, ?, ?, ?)',
            [$accessToken, $owner, $clientToken, $profileId, time()]
        );
        return $accessToken;
    }

    /** 查找令牌，返回记录或 null */
    public static function find(string $accessToken): ?array
    {
        return Database::get('SELECT * FROM ygg_tokens WHERE access_token = ?', [$accessToken]);
    }

    /** 是否存在且有效 */
    public static function isValid(string $accessToken): bool
    {
        return self::find($accessToken) !== null;
    }

    /** 吊销单个令牌 */
    public static function revoke(string $accessToken): void
    {
        Database::run('DELETE FROM ygg_tokens WHERE access_token = ?', [$accessToken]);
    }

    /** 吊销某用户所有令牌 */
    public static function revokeAll(string $owner): void
    {
        Database::run('DELETE FROM ygg_tokens WHERE owner = ?', [$owner]);
    }

    /** 获取某用户所有令牌 */
    public static function allOf(string $owner): array
    {
        return Database::all('SELECT * FROM ygg_tokens WHERE owner = ?', [$owner]);
    }

    /** 确保表存在（迁移时创建） */
    public static function migrate(): void
    {
        Database::pdo()->exec("CREATE TABLE IF NOT EXISTS ygg_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            access_token VARCHAR(128) NOT NULL UNIQUE,
            owner VARCHAR(190) NOT NULL,
            client_token VARCHAR(64) DEFAULT '',
            profile_id VARCHAR(64) DEFAULT '',
            created_at INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}