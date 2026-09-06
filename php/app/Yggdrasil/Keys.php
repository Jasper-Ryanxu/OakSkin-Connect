<?php
/**
 * Yggdrasil 密钥管理：RSA 4096 签名密钥（openssl）
 */
declare(strict_types=1);

namespace App\Yggdrasil;

class Keys
{
    private const DIR = BS_STORAGE . '/yggdrasil';
    private static ?string $privateKey = null;
    private static ?string $publicKey = null;

    public static function ensure(): void
    {
        if (self::$privateKey && self::$publicKey) return;

        $privPath = self::DIR . '/private_key.pem';
        $pubPath = self::DIR . '/public_key.pem';

        if (!is_dir(self::DIR)) @mkdir(self::DIR, 0777, true);

        if (is_file($privPath) && is_file($pubPath)) {
            self::$privateKey = file_get_contents($privPath);
            self::$publicKey = file_get_contents($pubPath);
            return;
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$res) {
            throw new \RuntimeException('RSA 密钥生成失败');
        }
        openssl_pkey_export($res, $privPem);
        $pub = openssl_pkey_get_details($res);

        file_put_contents($privPath, $privPem);
        file_put_contents($pubPath, $pub['key']);

        self::$privateKey = $privPem;
        self::$publicKey = $pub['key'];
    }

    public static function getPrivateKey(): string
    {
        self::ensure();
        return self::$privateKey;
    }

    public static function getPublicKey(): string
    {
        self::ensure();
        return self::$publicKey;
    }

    /** 用私钥签名（RSA-SHA1），返回 base64 */
    public static function sign(string $data): string
    {
        self::ensure();
        openssl_sign($data, $signature, self::$privateKey, OPENSSL_ALGO_SHA1);
        return base64_encode($signature);
    }
}