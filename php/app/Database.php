<?php
/**
 * 数据库层：PDO MySQL
 * 提供 get/all/run + 表结构初始化（迁移），SQL 风格与 Node 端保持接近方便移植。
 */
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    /** 通过 config 中的 database 配置连接 */
    public static function init(array $dbConfig): void
    {
        self::$config = $dbConfig;

        $driver = $dbConfig['driver'] ?? 'mysql';
        $host = $dbConfig['mysql']['host'] ?? '127.0.0.1';
        $port = $dbConfig['mysql']['port'] ?? 3306;
        $name = $dbConfig['mysql']['database'] ?? '';
        $user = $dbConfig['mysql']['username'] ?? '';
        $pass = $dbConfig['mysql']['password'] ?? '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException('数据库连接失败: ' . $e->getMessage());
        }

        self::migrate();
    }

    public static function pdo(): ?PDO
    {
        return self::$pdo;
    }

    /** 迁移：建表 */
    public static function migrate(): void
    {
        $sqls = [
            "CREATE TABLE IF NOT EXISTS users (
                uid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL UNIQUE,
                password VARCHAR(500) NOT NULL,
                nickname VARCHAR(100) NOT NULL,
                avatar INT DEFAULT 0,
                score INT DEFAULT 0,
                permission INT DEFAULT 0,
                ip VARCHAR(64) DEFAULT '',
                verified INT DEFAULT 1,
                is_dark_mode INT DEFAULT 1,
                last_sign_at VARCHAR(64) DEFAULT '',
                register_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS textures (
                tid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'steve',
                hash VARCHAR(64) NOT NULL UNIQUE,
                size INT DEFAULT 0,
                uploader INT UNSIGNED NOT NULL,
                public TINYINT DEFAULT 1,
                likes INT DEFAULT 0,
                downloads INT DEFAULT 0,
                upload_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_textures_uploader FOREIGN KEY (uploader) REFERENCES users(uid) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS players (
                pid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid INT UNSIGNED NOT NULL,
                name VARCHAR(64) NOT NULL,
                tid_skin INT UNSIGNED DEFAULT NULL,
                tid_cape INT UNSIGNED DEFAULT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_players_uid FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
                CONSTRAINT fk_players_skin FOREIGN KEY (tid_skin) REFERENCES textures(tid) ON DELETE SET NULL,
                CONSTRAINT fk_players_cape FOREIGN KEY (tid_cape) REFERENCES textures(tid) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS user_closet (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid INT UNSIGNED NOT NULL,
                tid INT UNSIGNED NOT NULL,
                item_name VARCHAR(255) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_closet (uid, tid),
                CONSTRAINT fk_closet_uid FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
                CONSTRAINT fk_closet_tid FOREIGN KEY (tid) REFERENCES textures(tid) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS reports (
                rid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tid INT UNSIGNED NOT NULL,
                reporter_uid INT UNSIGNED NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                status INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reports_tid FOREIGN KEY (tid) REFERENCES textures(tid) ON DELETE CASCADE,
                CONSTRAINT fk_reports_uid FOREIGN KEY (reporter_uid) REFERENCES users(uid) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS uuid (
                name VARCHAR(64) PRIMARY KEY,
                uuid VARCHAR(64) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid INT UNSIGNED NOT NULL,
                token VARCHAR(128) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                CONSTRAINT fk_tokens_uid FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS email_verify_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                code VARCHAR(10) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                used TINYINT DEFAULT 0,
                INDEX idx_email_code (email, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS ygg_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                access_token VARCHAR(128) NOT NULL UNIQUE,
                owner VARCHAR(190) NOT NULL,
                client_token VARCHAR(64) DEFAULT '',
                profile_id VARCHAR(64) DEFAULT '',
                created_at INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS ygg_sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                server_id VARCHAR(128) NOT NULL UNIQUE,
                profile_id VARCHAR(64) DEFAULT '',
                ip VARCHAR(64) DEFAULT '',
                created_at INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS oauth_clients (
                client_id VARCHAR(64) PRIMARY KEY,
                secret VARCHAR(128) NOT NULL,
                name VARCHAR(120) NOT NULL,
                redirect_uri VARCHAR(500) NOT NULL,
                scopes VARCHAR(500) DEFAULT '',
                icon VARCHAR(500) DEFAULT '',
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS oauth_codes (
                code VARCHAR(128) PRIMARY KEY,
                client_id VARCHAR(64) NOT NULL,
                uid INT UNSIGNED NOT NULL,
                redirect_uri VARCHAR(500) NOT NULL,
                scope VARCHAR(500) DEFAULT '',
                expires_at DATETIME NOT NULL,
                used TINYINT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS oauth_tokens (
                access_token VARCHAR(128) PRIMARY KEY,
                client_id VARCHAR(64) NOT NULL,
                uid INT UNSIGNED NOT NULL,
                scope VARCHAR(500) DEFAULT '',
                expires_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($sqls as $sql) {
            self::$pdo->exec($sql);
        }
    }

    // ---- Statement 风格的便捷方法 ----

    /** 取单行，未找到返回 null（占位符参数按序传入） */
    public static function get(string $sql, array $params = []): ?array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(self::bind($params));
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** 取多行 */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(self::bind($params));
        return $stmt->fetchAll();
    }

    /** 执行写入，返回 lastInsertId + rowCount */
    public static function run(string $sql, array $params = []): array
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute(self::bind($params));
        return [
            'lastInsertRowid' => (int) self::$pdo->lastInsertId(),
            'changes' => $stmt->rowCount(),
        ];
    }

    /** 将 ?[] 首项为关联数组的情况转成按位置绑定的值；此处大部分参数是位置数组 */
    private static function bind(array $params): array
    {
        return array_values($params);
    }

    public static function quote(mixed $value): string
    {
        return self::$pdo->quote((string) $value);
    }
}