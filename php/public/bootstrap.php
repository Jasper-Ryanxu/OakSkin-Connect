<?php
/**
 * OakSkin Connect - PHP 引导文件
 * 加载常量、配置、自动加载与全局错误处理。
 */
declare(strict_types=1);

define('BS_ROOT', dirname(__DIR__));              // php/
define('BS_PUBLIC', __DIR__);                     // php/public  (web 根目录)
define('BS_APP', dirname(__DIR__) . '/app');
define('BS_CONFIG', dirname(__DIR__) . '/config');
define('BS_STORAGE', dirname(__DIR__) . '/storage');
define('BS_TEXTURES', __DIR__ . '/textures');     // 材质公开目录 (web 可访问)
define('BS_DIST', __DIR__);                        // 前端构建产物与 index.php 同居
define('BS_INSTALL_LOCK', dirname(__DIR__) . '/install.lock');

error_reporting(E_ALL);
// 诊断用：临时开启错误显示，方便定位 500。定位后请改回 '0' 再上线。
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// 简单自动加载：App\Xxx\Yyy -> app/Xxx/Yyy.php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) === 0) {
        $relative = substr($class, strlen($prefix));
        $file = BS_APP . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/../app/helpers.php';

date_default_timezone_set('Asia/Shanghai');

// 全局异常/错误捕获：写入日志，避免出现无提示的 500
set_exception_handler(function (\Throwable $e) {
    \App\Log::error('未捕获异常: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['code' => 500, 'message' => '服务器内部错误: ' . $e->getMessage()]);
});
set_error_handler(function ($severity, $message, $file, $line) {
    \App\Log::error("PHP错误(severity={$severity}): {$message}", ['file' => $file, 'line' => $line]);
    return false;
});