<?php
/**
 * OakSkin Connect - 入口文件（前端 SPA + API 由 .htaccess 路由到此）
 *
 * .htaccess 规则：
 *   /api/*        -> 到此文件继续处理 API
 *   其他请求      -> 直接命中的静态文件（asset / textures）或回退到 index.html
 *
 * 本文件同时承担 SPA 回退：解析路径，若非 /api 或真实文件则交给前端。
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Router;
use App\Database;
use App\Config;
use App\Response;
use App\Log;

// 记录请求（BS 风格：记录 IP、UA、请求耗时）
Log::request();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path ?? '/', '/');
if ($path === '' || $path === false) $path = '/';

// 已安装（有 install.lock）才连接 DB；安装向导内自行初始化
if (file_exists(BS_INSTALL_LOCK)) {
    try {
        Database::init(Config::dbConfig());
    } catch (\Throwable $e) {
        \App\Log::error('数据库初始化失败: ' . $e->getMessage());
        // 不中断，setup 路由可诊断
    }
}

// ---------- SPA 静态与回退（非 API） ----------
$isApi = strpos($path, '/api/') === 0 || $path === '/api' || $path === '/callback/oakskin';
if (!$isApi) {
    $clean = $path === '/' ? 'index.html' : ltrim($path, '/');
    // 若 /xxx 且 xxx 是真实文件（asset 或 textures），由静态规则命中；
    // 此处统一把所有非 api 路径交给前端 index.html（存在文件时仍可被下面覆盖）。
    $file = BS_PUBLIC . ($path === '/' ? '/index.html' : $path);
    // 站点地图：文件不存在时即时生成
    if ($path === '/sitemap.xml' && !is_file($file)) {
        \App\Sitemap::generate();
    }
    // 真正的文件（css/js/png 等）
    if (is_file($file)) {
        Log::response();
        serve_file($file);
    }
    // SPA 回退
    $index = BS_PUBLIC . '/index.html';
    if (is_file($index)) {
        Log::response();
        serve_file($index);
    }
    http_response_code(404);
    Log::response();
    echo 'Not Found';
    exit;
}

// ---------- API 路由 ----------
load_api_routes();
// Yggdrasil 需在任意方法下工作；路由表已按方法登记
Router::dispatch();
// 如果 dispatch 未 exit（未知路由），记录响应
Log::response();

/**
 * 加载全部 API 路由定义
 */
function load_api_routes(): void
{
    // setup 路由（无需 DB/配置）
    require BS_APP . '/routes/setup.php';
    require BS_APP . '/routes/captcha.php';
    require BS_APP . '/routes/settings.php';
    require BS_APP . '/routes/oauth.php';

    // 已安装才注册业务路由（避免未安装报错）
    $installed = file_exists(BS_INSTALL_LOCK);
    if (!$installed) {
        return;
    }
    require BS_APP . '/routes/auth.php';
    require BS_APP . '/routes/skins.php';
    require BS_APP . '/routes/players.php';
    require BS_APP . '/routes/closet.php';
    require BS_APP . '/routes/user.php';
    require BS_APP . '/routes/admin.php';
    require BS_APP . '/routes/yggdrasil.php';
}

function serve_file(string $file): never
{
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $types = [
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript',
        'css' => 'text/css',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'json' => 'application/json',
        'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
    ];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($file);
    exit;
}