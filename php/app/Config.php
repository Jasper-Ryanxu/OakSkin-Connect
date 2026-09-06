<?php
/**
 * 配置管理：从 config/config.php 读取站点配置
 */
declare(strict_types=1);

namespace App;

class Config
{
    private static ?array $data = null;

    public static function path(): string
    {
        return BS_CONFIG . '/config.php';
    }

    public static function exists(): bool
    {
        return file_exists(self::path());
    }

    public static function load(): array
    {
        if (self::$data !== null) return self::$data;
        if (self::exists()) {
            self::$data = require self::path();
        }
        return self::$data ?? [];
    }

    /** 读取键，支持点号嵌套，如 site.url */
    public static function get(string $key, mixed $default = null): mixed
    {
        $data = self::load();
        $segments = explode('.', $key);
        foreach ($segments as $seg) {
            if (is_array($data) && array_key_exists($seg, $data)) {
                $data = $data[$seg];
            } else {
                return $default;
            }
        }
        return $data;
    }

    /** 写入配置替换整个数组 */
    public static function write(array $config): bool
    {
        $dir = dirname(self::path());
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $export = "<?php\n/**\n * OakSkin Connect 站点配置（由安装向导生成）\n */\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents(self::path(), $export) === false) {
            return false;
        }
        self::$data = $config;
        return true;
    }

    public static function siteUrl(): string
    {
        $url = (string) self::get('site.url', '');
        return rtrim($url, '/');
    }

    public static function dbConfig(): array
    {
        return (array) self::get('database', []);
    }
}