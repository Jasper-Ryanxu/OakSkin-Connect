<?php
/**
 * 自制滑块验证码
 * 生成加密挑战，前端通过拖拽滑块验证
 * 采用"滑动到末端"模式，后端生成随机阈值（80-95%），用户需拖动到阈值以上
 */
declare(strict_types=1);

namespace App;

class Captcha
{
    /** 验证码有效期（秒） */
    private const EXPIRE = 300;
    /** 最小阈值 */
    private const THRESHOLD_MIN = 80;
    /** 最大阈值 */
    private const THRESHOLD_MAX = 95;
    /** 密钥来源 */
    private const SECRET_SALT = 'OakSkin_Captcha_S3cr3t!';

    /**
     * 生成验证码挑战
     * @return array{token: string, hint: string}
     */
    public static function generate(): array
    {
        $threshold = random_int(self::THRESHOLD_MIN, self::THRESHOLD_MAX);
        $expires = time() + self::EXPIRE;
        $secret = self::secret();

        $plain = $threshold . '|' . $expires;
        $encrypted = openssl_encrypt($plain, 'aes-128-ecb', $secret);
        if ($encrypted === false) {
            Log::error('Captcha: openssl_encrypt 失败，使用 HMAC 回退');
            $encrypted = $plain . '|' . hash_hmac('sha256', $plain, $secret);
        }

        return [
            'token' => bin2hex($encrypted),
        ];
    }

    /**
     * 验证用户拖拽位置
     * @param string $token generate() 返回的 token
     * @param int $userPosition 用户拖拽到的位置百分比 (0-100)
     * @return bool
     */
    public static function verify(string $token, int $userPosition): bool
    {
        $secret = self::secret();
        $encrypted = @hex2bin($token);
        if ($encrypted === false) return false;

        // 先尝试 OpenSSL 解密
        $decrypted = openssl_decrypt($encrypted, 'aes-128-ecb', $secret);
        if ($decrypted === false) {
            // 回退：HMAC 校验（兼容 openssl 不可用的情况）
            $parts = explode('|', $encrypted);
            if (count($parts) !== 3) return false;
            $threshold = (int) $parts[0];
            $expires = (int) $parts[1];
            $mac = $parts[2];
            $plain = $parts[0] . '|' . $parts[1];
            if (hash_hmac('sha256', $plain, $secret) !== $mac) return false;
            if (time() > $expires) return false;
            return $userPosition >= $threshold;
        }

        $parts = explode('|', $decrypted);
        if (count($parts) !== 2) return false;

        $threshold = (int) $parts[0];
        $expires = (int) $parts[1];

        // 过期检查
        if (time() > $expires) return false;

        // 用户需拖到阈值以上
        return $userPosition >= $threshold;
    }

    private static function secret(): string
    {
        $siteKey = Config::get('site.key', '');
        $base = $siteKey ?: (BS_ROOT . DIRECTORY_SEPARATOR);
        return hash('sha256', $base . self::SECRET_SALT, true);
    }
}