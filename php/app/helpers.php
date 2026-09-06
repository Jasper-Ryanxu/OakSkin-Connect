<?php
/**
 * 全局辅助函数
 */
declare(strict_types=1);

use App\Database;
use App\Config;

/** 生成与 Node 端一致的随机令牌 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** 密码哈希：PBKDF2-SHA512, 10000 迭代, salt:hash(hex) */
function hash_password(string $password): string
{
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2('sha512', $password, $salt, 10000, 64, false);
    return $salt . ':' . $hash;
}

/** 校验密码 */
function verify_password(string $password, string $stored): bool
{
    $parts = explode(':', $stored, 2);
    if (count($parts) !== 2) return false;
    [$salt, $hash] = $parts;
    $computed = hash_pbkdf2('sha512', $password, $salt, 10000, 64, false);
    return hash_equals($hash, $computed);
}

/** 读取请求 JSON 体（PUT/PATCH 用 php://input） */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** 读取当前用户（鉴权中设置），可为 null */
function current_user(): ?array
{
    return App\Auth::user();
}

function text(): array
{
    return json_body();
}