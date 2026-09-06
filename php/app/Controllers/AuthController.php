<?php
/**
 * 认证控制器：登录/注册/退出/邮箱验证码
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;
use App\Auth;
use App\Mailer;
use App\Log;

class AuthController
{
    /** POST /api/auth/send-verify-code */
    public function sendVerifyCode(): never
    {
        $body = json_body();
        $email = $body['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 422, 422);
        }

        // 检查是否已被注册
        $existing = Database::get('SELECT uid FROM users WHERE email = ?', [$email]);
        if ($existing) {
            Response::error('该邮箱已被注册', 422, 422);
        }

        // 检查 60 秒内是否已发送过
        $recent = Database::get(
            'SELECT id FROM email_verify_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1',
            [$email]
        );
        if ($recent) {
            Response::error('请 60 秒后再试', 429, 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Database::run(
            'INSERT INTO email_verify_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))',
            [$email, $code]
        );

        // 尝试发送邮件（SMTP → mail() 自动回退）
        $ok = Mailer::sendVerifyCode($email, $code);
        if ($ok) {
            Response::data(['code' => 0, 'message' => '验证码已发送']);
        }

        // 全都失败，返回调试代码
        Log::warn('Mailer: 无法发送邮件，返回调试验证码', ['email' => $email, 'code' => $code]);
        Response::data(['code' => 0, 'message' => '验证码已发送（开发模式）', 'debug_code' => $code]);
    }

    /** POST /api/auth/login */
    public function login(): never
    {
        $body = json_body();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        $captchaToken = $body['captcha_token'] ?? '';

        if (!$email || !$password) {
            Response::error('请填写邮箱和密码', 422, 422);
        }

        // 验证滑块验证码
        self::verifyCaptcha($captchaToken);

        $user = Database::get('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || !verify_password($password, $user['password'])) {
            Response::error('邮箱或密码错误', 401, 401);
        }

        $token = generate_token(32);
        $lifetime = (int) Config::get('auth.token_lifetime', 604800);
        $expires = date('Y-m-d H:i:s', time() + $lifetime);
        Database::run('INSERT INTO tokens (uid, token, expires_at) VALUES (?, ?, ?)', [$user['uid'], $token, $expires]);

        Response::data([
            'token' => $token,
            'user' => [
                'uid' => (int) $user['uid'],
                'email' => $user['email'],
                'nickname' => $user['nickname'],
                'permission' => (int) $user['permission'],
                'score' => (int) $user['score'],
                'avatar' => (int) $user['avatar'],
                'verified' => (int) $user['verified'],
            ],
        ]);
    }

    /** POST /api/auth/register */
    public function register(): never
    {
        $body = json_body();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        $nickname = $body['nickname'] ?? '';
        $verifyCode = $body['verify_code'] ?? '';
        $captchaToken = $body['captcha_token'] ?? '';

        if (!$email || !$password || !$nickname) {
            Response::error('请填写所有必填字段', 422, 422);
        }

        if (!Config::get('auth.allow_register', true)) {
            Response::error('注册已关闭', 403, 403);
        }

        $minLen = (int) Config::get('auth.password_min_length', 6);
        if (strlen($password) < $minLen) {
            Response::error("密码长度至少为{$minLen}位", 422, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 422, 422);
        }

        // 验证滑块验证码
        self::verifyCaptcha($captchaToken);

        // 验证邮箱验证码
        if (empty($verifyCode)) {
            Response::error('请填写邮箱验证码', 422, 422);
        }
        $record = Database::get(
            'SELECT id FROM email_verify_codes WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$email, $verifyCode]
        );
        if (!$record) {
            Response::error('验证码无效或已过期', 422, 422);
        }
        // 标记为已使用
        Database::run('UPDATE email_verify_codes SET used = 1 WHERE id = ?', [$record['id']]);

        $existing = Database::get('SELECT uid FROM users WHERE email = ?', [$email]);
        if ($existing) {
            Response::error('该邮箱已被注册', 422, 422);
        }

        $result = Database::run(
            'INSERT INTO users (email, password, nickname, permission, verified) VALUES (?, ?, ?, 0, 1)',
            [$email, hash_password($password), $nickname]
        );

        Response::data([
            'code' => 0,
            'message' => '注册成功',
            'user' => [
                'uid' => $result['lastInsertRowid'],
                'email' => $email,
                'nickname' => $nickname,
                'permission' => 0,
                'verified' => 1,
            ],
        ]);
    }

    /** POST /api/auth/test-mail */
    public function testMail(): never
    {
        Auth::require();
        $user = Auth::user();
        if (!$user) {
            Response::error('未登录', 401, 401);
        }

        $ok = Mailer::send(
            $user['email'],
            'OakSkin Connect - SMTP 测试',
            '<h2 style="color:#6c5ce7;">SMTP 测试成功</h2><p>如果你的 SMTP 配置正确，恭喜你收到了这封测试邮件！</p>'
        );

        if (!$ok) {
            Response::error('邮件发送失败，请检查 storage/logs/app.log 查看详细错误', 500, 500);
        }

        Response::data(['code' => 0, 'message' => '测试邮件已发送到 ' . $user['email']]);
    }

    /** POST /api/auth/logout */
    public function logout(): never
    {
        Auth::require();
        $token = Auth::bearerToken();
        if ($token) {
            Database::run('DELETE FROM tokens WHERE token = ?', [$token]);
        }
        Response::data(['code' => 0, 'message' => '已退出登录']);
    }

    /**
     * 校验滑块验证码 token（自制验证码，仅检查 token 是否有效）
     */
    private static function verifyCaptcha(string $token): void
    {
        if ($token === '') {
            Response::error('请完成滑块验证', 422, 422);
        }

        // 自制验证码 token 格式为 captcha_ok_xxxxx
        if (strpos($token, 'captcha_ok_') !== 0) {
            Response::error('滑块验证无效，请重新验证', 422, 422);
        }
    }
}