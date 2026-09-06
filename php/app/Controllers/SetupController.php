<?php
/**
 * Setup 控制器：安装状态检查与安装
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;

class SetupController
{
    /** GET /api/setup/status */
    public function status(): never
    {
        $config = Config::load();
        $mailConfigured = isset($config['mail']);
        Response::data([
            'installed' => file_exists(BS_INSTALL_LOCK),
            'mail_configured' => $mailConfigured,
        ]);
    }

    /** POST /api/setup/install */
    public function install(): never
    {
        if (file_exists(BS_INSTALL_LOCK)) {
            Response::error('系统已安装，请删除 install.lock 后重试', 400, 400);
        }

        $body = json_body();
        $siteName = $body['site_name'] ?? '';
        $siteUrl = $body['site_url'] ?? 'http://localhost';
        $adminEmail = $body['admin_email'] ?? '';
        $adminPassword = $body['admin_password'] ?? '';
        $adminNickname = $body['admin_nickname'] ?? '站长';

        // 数据库（MySQL）
        $db = $body['database'] ?? $body['db'] ?? [];
        $dbHost = $db['host'] ?? $body['db_host'] ?? '127.0.0.1';
        $dbPort = (int) ($db['port'] ?? $body['db_port'] ?? 3306);
        $dbName = $db['database'] ?? $body['db_name'] ?? '';
        $dbUser = $db['username'] ?? $body['db_user'] ?? '';
        $dbPass = $db['password'] ?? $body['db_password'] ?? '';

        // SMTP 配置
        $mailHost = $body['mail_host'] ?? '';
        $mailPort = $body['mail_port'] ?? '465';
        $mailUser = $body['mail_user'] ?? '';
        $mailPass = $body['mail_password'] ?? '';
        $mailEnc = $body['mail_encryption'] ?? 'ssl';
        $mailFrom = $body['mail_from'] ?? $mailUser;

        // 移除 68API 滑块验证配置（已替换为自制验证码）

        if (!$siteName || !$adminEmail || !$adminPassword) {
            Response::error('请填写必要信息', 422, 422);
        }
        if (strlen($adminPassword) < 6) {
            Response::error('密码长度不能少于6位', 422, 422);
        }
        if (!$dbName || !$dbUser) {
            Response::error('请填写数据库信息', 422, 422);
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 422, 422);
        }

        $config = [
            'site' => [
                'name' => $siteName,
                'url' => $siteUrl,
                'max_upload_size' => 10240,
            ],
            'database' => [
                'driver' => 'mysql',
                'mysql' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'database' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass,
                ],
            ],
            'auth' => [
                'token_expire' => 604800,
                'token_lifetime' => 604800,
                'allow_register' => true,
                'password_min_length' => 6,
                'verify_email' => false,
            ],
            'yggdrasil' => [
                'uuid_algorithm' => 'v3',
                'skin_domains' => '',
            ],
            'admin' => [
                'email' => $adminEmail,
                'password' => $adminPassword,
                'nickname' => $adminNickname,
            ],
            'mail' => [
                'host' => $mailHost,
                'port' => $mailPort,
                'username' => $mailUser,
                'password' => $mailPass,
                'encryption' => $mailEnc,
                'from_address' => $mailFrom,
                'from_name' => $siteName,
                'use_mail_fallback' => '1',
            ],
        ];

        // 测试数据库连接并初始化表结构
        try {
            Database::init($config['database']);
        } catch (\Throwable $e) {
            Response::error('安装失败: ' . $e->getMessage(), 500, 500);
        }

        // 写入配置（先于建表，ensureKeys 等依赖）
        if (!Config::write($config)) {
            Response::error('安装失败: 无法写入配置文件', 500, 500);
        }

        // 创建站长（超级管理员 permission=2）
        try {
            self::createAdmin($config['admin']);
        } catch (\Throwable $e) {
            Response::error('安装失败: ' . $e->getMessage(), 500, 500);
        }

        // 材质目录
        if (!is_dir(BS_TEXTURES)) @mkdir(BS_TEXTURES, 0777, true);

        // 写安装锁
        file_put_contents(BS_INSTALL_LOCK, date('c'));

        Response::data(['code' => 0, 'message' => '安装成功']);
    }

    private static function createAdmin(array $admin): void
    {
        $existing = Database::get('SELECT uid FROM users WHERE permission = 2 LIMIT 1');
        if ($existing) return;

        Database::run(
            'INSERT INTO users (email, password, nickname, permission, verified) VALUES (?, ?, ?, 2, 1)',
            [$admin['email'], hash_password($admin['password']), $admin['nickname']]
        );
    }
}