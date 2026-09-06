<?php
/**
 * 日志系统 - 仿 Blessing Skin 风格
 * 写入 storage/logs/app.log，支持请求耗时记录与详细上下文。
 */
declare(strict_types=1);

namespace App;

class Log
{
    private static ?string $logFile = null;
    private static ?float $requestStart = null;

    /** 获取日志文件路径（自动创建目录） */
    private static function file(): string
    {
        if (self::$logFile) return self::$logFile;
        $dir = defined('BS_STORAGE') ? BS_STORAGE . '/logs' : __DIR__ . '/../storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        self::$logFile = $dir . '/app.log';
        return self::$logFile;
    }

    /**
     * 写入一条日志记录
     * 仅记录 ERROR 和 WARN 级别，INFO/DEBUG 不输出
     * @param string $level   日志级别
     * @param string $message 日志正文
     * @param array  $context 附加上下文
     */
    public static function write(string $level, string $message, array $context = []): void
    {
        // 只输出 ERROR 和 WARN 级别
        $levelUpper = strtoupper($level);
        if (!in_array($levelUpper, ['ERROR', 'WARN'], true)) return;

        try {
            $file = self::file();
            $time = date('Y-m-d H:i:s');
            $levelPadded = str_pad($levelUpper, 5, ' ', STR_PAD_RIGHT);
            $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $line = "[{$time}] [{$levelPadded}] {$message}{$ctx}" . PHP_EOL;
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 日志写入失败不阻塞业务
        }
    }

    public static function info(string $msg, array $ctx = []): void  { self::write('INFO', $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void  { self::write('WARN', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('ERROR', $msg, $ctx); }
    public static function debug(string $msg, array $ctx = []): void { self::write('DEBUG', $msg, $ctx); }

    /**
     * 记录一次请求到达（含客户端 IP 与请求 ID）
     * 在入口文件最早处调用
     */
    public static function request(): void
    {
        self::$requestStart = microtime(true);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ctx = ['ip' => $ip, 'ua' => mb_substr($agent, 0, 80)];
        self::info("REQ {$method} {$uri}", $ctx);
    }

    /**
     * 记录请求结束（在响应输出前调用）
     */
    public static function response(): void
    {
        if (self::$requestStart === null) return;
        $elapsed = round((microtime(true) - self::$requestStart) * 1000, 1);
        $code = http_response_code();
        self::info("RES {$code} ({$elapsed}ms)");
        self::$requestStart = null;
    }

    /**
     * 获取日志文件大小（字节）
     */
    public static function size(): int
    {
        $file = self::file();
        return is_file($file) ? filesize($file) : 0;
    }

    /**
     * 获取最近 N 行日志
     */
    public static function tail(int $lines = 50): array
    {
        $file = self::file();
        if (!is_file($file)) return [];
        $data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$data) return [];
        return array_slice($data, -$lines);
    }
}