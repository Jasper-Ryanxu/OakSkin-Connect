<?php
/**
 * OAuth2 控制器
 * 1) 提供方：OAuth 应用管理 / 授权码 / 令牌 / 用户信息
 * 2) 客户端：使用 OakSkin 第三方登录（授权码流程）
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Response;
use App\Auth;
use App\Config;

class OAuthController
{
    /** 支持的权限范围 */
    private const SCOPES = ['email', 'nickname', 'avatar', 'score'];

    private const CODE_TTL = 600;       // 授权码 10 分钟
    private const TOKEN_TTL = 2592000;  // 令牌 30 天

    // ---------------- 提供方：OAuth 应用管理（管理员） ----------------

    /** GET /api/admin/oauth/clients */
    public function clients(): never
    {
        $user = Auth::require();
        $sql = 'SELECT * FROM oauth_clients';
        $params = [];
        // 管理员可见全部应用，普通成员仅可见自己的
        if ($user['permission'] < 1) {
            $sql .= ' WHERE created_by = ?';
            $params[] = (int) $user['uid'];
        }
        $sql .= ' ORDER BY created_at DESC';
        $items = Database::all($sql, $params);
        foreach ($items as &$c) {
            $c['secret'] = (string) ($c['secret'] ?? '');
        }
        Response::data(['code' => 0, 'data' => $items]);
    }

    /** POST /api/admin/oauth/clients */
    public function createClient(): never
    {
        $user = Auth::require();
        $body = json_body();
        $name = trim((string) ($body['name'] ?? ''));
        $redirect = trim((string) ($body['redirect_uri'] ?? ''));
        $scopes = trim((string) ($body['scopes'] ?? 'email'));
        $icon = trim((string) ($body['icon'] ?? ''));

        if ($name === '' || $redirect === '') {
            Response::error('应用名称和回调地址必填', 422, 422);
        }
        if (strpos($redirect, 'http://') !== 0 && strpos($redirect, 'https://') !== 0) {
            Response::error('回调地址必须是 http(s):// 开头', 422, 422);
        }

        $scopes = self::sanitizeScopes($scopes);
        $clientId = 'oak_' . generate_token(16);
        $secret = generate_token(32);

        Database::run(
            'INSERT INTO oauth_clients (client_id, secret, name, redirect_uri, scopes, icon, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$clientId, $secret, $name, $redirect, implode(' ', $scopes), $icon, (int) $user['uid']]
        );

        Response::data([
            'code' => 0,
            'message' => '应用创建成功',
            'data' => ['client_id' => $clientId, 'secret' => $secret],
        ]);
    }

    /** DELETE /api/admin/oauth/clients/:clientId */
    public function deleteClient(string $clientId): never
    {
        $user = Auth::require();
        $client = Database::get('SELECT * FROM oauth_clients WHERE client_id = ?', [$clientId]);
        if (!$client) {
            Response::notFound('应用不存在');
        }
        // 仅创建者或管理员可删除
        if ($user['permission'] < 1 && (int) $client['created_by'] !== (int) $user['uid']) {
            Response::forbidden('无权删除该应用');
        }
        Database::run('DELETE FROM oauth_clients WHERE client_id = ?', [$clientId]);
        Database::run('DELETE FROM oauth_codes WHERE client_id = ?', [$clientId]);
        Database::run('DELETE FROM oauth_tokens WHERE client_id = ?', [$clientId]);
        Response::data(['code' => 0, 'message' => '应用已删除']);
    }

    // ---------------- 提供方：授权流程 ----------------

    /** GET /api/oauth/client-info 供授权页加载应用信息 */
    public function clientInfo(): never
    {
        $clientId = $_GET['client_id'] ?? '';
        if (!$clientId) {
            Response::error('缺少 client_id', 422, 422);
        }
        $client = Database::get('SELECT * FROM oauth_clients WHERE client_id = ?', [$clientId]);
        if (!$client) {
            Response::notFound('应用不存在');
        }
        Response::data([
            'code' => 0,
            'data' => [
                'client_id' => $client['client_id'],
                'name' => $client['name'],
                'icon' => $client['icon'],
                'scopes' => $client['scopes'],
            ],
        ]);
    }

    /** POST /api/oauth/authorize 用户确认授权，返回带 code 的重定向地址 */
    public function authorize(): never
    {
        $user = Auth::require();
        $body = json_body();
        $clientId = (string) ($body['client_id'] ?? '');
        $redirectUri = (string) ($body['redirect_uri'] ?? '');
        $state = (string) ($body['state'] ?? '');

        $client = Database::get('SELECT * FROM oauth_clients WHERE client_id = ?', [$clientId]);
        if (!$client) {
            Response::notFound('应用不存在');
        }
        if ($client['redirect_uri'] !== $redirectUri) {
            Response::error('回调地址不匹配', 422, 422);
        }

        $code = generate_token(32);
        Database::run(
            'INSERT INTO oauth_codes (code, client_id, uid, redirect_uri, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$code, $clientId, (int) $user['uid'], $redirectUri, $client['scopes'], date('Y-m-d H:i:s', time() + self::CODE_TTL)]
        );
        \App\Log::error('OakSkin OAuth: 用户已授权, 生成授权码', [
            'client_id' => $clientId,
            'uid' => (int) $user['uid'],
            'redirect_uri' => $redirectUri,
        ]);

        $sep = strpos($redirectUri, '?') === false ? '?' : '&';
        $final = $redirectUri . $sep . 'code=' . urlencode($code) . '&state=' . urlencode($state);

        Response::data(['code' => 0, 'redirect_url' => $final]);
    }

    /** POST /api/oauth/token 用授权码换取访问令牌 */
    public function token(): never
    {
        $body = json_body();
        $grant = (string) ($body['grant_type'] ?? 'authorization_code');
        $code = (string) ($body['code'] ?? '');
        $clientId = (string) ($body['client_id'] ?? '');
        $secret = (string) ($body['client_secret'] ?? '');
        $redirectUri = (string) ($body['redirect_uri'] ?? '');

        $client = Database::get('SELECT * FROM oauth_clients WHERE client_id = ?', [$clientId]);
        if (!$client || !hash_equals((string) $client['secret'], $secret)) {
            self::oauthError('invalid_client', '客户端认证失败');
        }
        if ($client['redirect_uri'] !== $redirectUri) {
            self::oauthError('invalid_request', '回调地址不匹配');
        }

        $rec = Database::get(
            'SELECT * FROM oauth_codes WHERE code = ? AND client_id = ? AND used = 0 AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1',
            [$code, $clientId]
        );
        if (!$rec) {
            self::oauthError('invalid_grant', '授权码无效或已过期');
        }
        Database::run('UPDATE oauth_codes SET used = 1 WHERE code = ?', [$code]);

        $accessToken = generate_token(32);
        Database::run(
            'INSERT INTO oauth_tokens (access_token, client_id, uid, scope, expires_at) VALUES (?, ?, ?, ?, ?)',
            [$accessToken, $clientId, (int) $rec['uid'], $rec['scope'], date('Y-m-d H:i:s', time() + self::TOKEN_TTL)]
        );

        $user = Database::get('SELECT uid, email, nickname, avatar, score FROM users WHERE uid = ?', [(int) $rec['uid']]);
        self::oauthJson([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL,
            'scope' => $rec['scope'],
            'user' => self::mapUser($user),
        ]);
    }

    /** GET /api/oauth/userinfo 携带 Bearer 令牌获取用户信息 */
    public function userinfo(): never
    {
        $token = self::bearer();
        if (!$token) {
            self::oauthError('invalid_token', '缺少访问令牌');
        }
        $rec = Database::get('SELECT * FROM oauth_tokens WHERE access_token = ? AND expires_at > NOW()', [$token]);
        if (!$rec) {
            self::oauthError('invalid_token', '访问令牌无效或已过期');
        }
        $user = Database::get('SELECT uid, email, nickname, avatar, score FROM users WHERE uid = ?', [(int) $rec['uid']]);
        if (!$user) {
            self::oauthError('invalid_token', '用户不存在');
        }
        self::oauthJson(self::mapUser($user));
    }

    // ---------------- 客户端：OakSkin 第三方登录 ----------------

    /** GET /api/auth/oakskin/url 返回跳转 OakSkin 授权页的地址 */
    public function oakSkinLoginUrl(): never
    {
        $base = self::oakBase();
        $clientId = self::oakClientId();
        $redirectUri = self::oakRedirect();
        $state = generate_token(16);
        // 简单地返回 URL；code 为一次性，状态校验在回调端完成
        $url = $base . '/oauth/authorize?response_type=code&client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&state=' . $state;
        // 仅当配置了 scope 才附带，避免皮肤站返回 invalid_scope
        $scope = self::oakScope();
        if ($scope !== '') {
            $url .= '&scope=' . urlencode($scope);
        }
        \App\Log::error('OakSkin login: 生成授权跳转链接', [
            'base' => $base,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'oauth_client_id' => self::oakClientId(),
            'scope' => $scope,
        ]);
        Response::data(['code' => 0, 'url' => $url, 'state' => $state]);
    }

    /** GET /callback/oakskin 处理 OakSkin 授权回调（浏览器访问） */
    public function oakSkinCallback(): never
    {
        $base = self::oakBase();
        $clientId = self::oakClientId();
        $secret = self::oakClientSecret();
        $redirectUri = self::oakRedirect();

        // 记录收到的回调参数，便于排查
        \App\Log::error('OakSkin login: 收到回调 query', [
            'query' => $_SERVER['QUERY_STRING'] ?? '',
            'base' => $base,
            'client_id' => $clientId,
        ]);

        // 兼容不同参数名，作者咨询服务端会把 code 放在其中一种
        $code = '';
        foreach (['code', 'oauth_code', 'access_code', 'oidc_code', 'token'] as $k) {
            if (!empty($_GET[$k])) {
                $code = (string) $_GET[$k];
                \App\Log::error('OakSkin login: 取得凭证', ['param' => $k]);
                break;
            }
        }

        if (!$code) {
            \App\Log::error('OakSkin login: 回调缺少 code', ['query' => $_SERVER['QUERY_STRING'] ?? '']);
            self::redirect('/auth/login?error=authorize_failed&query=' . urlencode((string) $_SERVER['QUERY_STRING']));
        }

        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $secret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];
        // 兼容 /oauth/token 与 /api/oauth/token
        $tokenData = self::httpJson($base . '/oauth/token', $params);
        if (empty($tokenData) || isset($tokenData['error'])) {
            \App\Log::error('OakSkin login: token 交换失败(/oauth/token)', ['res' => $tokenData ?? '', 'client_id' => $clientId]);
            $tokenData = self::httpJson($base . '/api/oauth/token', $params);
            \App\Log::error('OakSkin login: token 交换失败(/api/oauth/token)', ['res' => $tokenData ?? '']);
        }
        if (empty($tokenData) || isset($tokenData['error'])) {
            \App\Log::error('OakSkin login: token 交换最终失败', ['res' => $tokenData ?? '', 'base' => $base]);
            self::redirect('/auth/login?error=oauth_failed&detail=' . urlencode((string) ($tokenData['error'] ?? 'empty')));
        }
        \App\Log::error('OakSkin login: token 交换成功', ['scopes' => $tokenData['scope'] ?? '']);

        $accessToken = $tokenData['access_token'] ?? '';
        // 优先取 token 响应内直接返回的用户，否则探测用户信息接口
        $remoteUser = isset($tokenData['user']) && is_array($tokenData['user']) ? $tokenData['user'] : null;
        if (!$remoteUser && $accessToken) {
            $remoteUser = self::oakUserInfo($base, $accessToken);
        }
        if (empty($remoteUser)) {
            \App\Log::error('OakSkin login: 获取用户信息失败', ['token' => $accessToken]);
            self::redirect('/auth/login?error=oauth_failed&detail=userinfo');
        }
        \App\Log::error('OakSkin login: 获取用户信息成功', ['user' => $remoteUser]);

        $email = filter_var($remoteUser['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            \App\Log::error('OakSkin login: 未返回有效邮箱', ['user' => $remoteUser]);
            self::redirect('/auth/login?error=no_email');
        }

        // 查找或创建本地账号
        $user = Database::get('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user) {
            $nickname = preg_replace('/[^\\p{L}\\p{N}_ -]/u', '', (string) ($remoteUser['nickname'] ?? explode('@', $email)[0]));
            $nickname = mb_substr($nickname, 0, 30) ?: explode('@', $email)[0];
            $result = Database::run(
                'INSERT INTO users (email, password, nickname, permission, verified) VALUES (?, ?, ?, 0, 1)',
                [$email, hash_password(generate_token(32)), $nickname] // 随机不可登录密码
            );
            $uid = (int) $result['lastInsertRowid'];
        } else {
            $uid = (int) $user['uid'];
        }

        // 签发本地 token
        $token = generate_token(32);
        $lifetime = (int) Config::get('auth.token_lifetime', 604800);
        Database::run('INSERT INTO tokens (uid, token, expires_at) VALUES (?, ?, ?)', [$uid, $token, date('Y-m-d H:i:s', time() + $lifetime)]);

        $site = Config::siteUrl() ?: '';
        self::redirect($site . '/auth/login?oakskin_token=' . $token);
    }

    // ---------------- 工具 ----------------

    private static function mapUser(array $u): array
    {
        return [
            'uid' => (int) $u['uid'],
            'email' => $u['email'],
            'nickname' => $u['nickname'],
            'avatar' => (int) ($u['avatar'] ?? 0),
            'score' => (int) ($u['score'] ?? 0),
        ];
    }

    private static function sanitizeScopes(string $scopes): array
    {
        $raw = preg_split('/[\s,]+/', $scopes);
        $out = [];
        foreach ($raw as $s) {
            if (in_array($s, self::SCOPES, true)) $out[] = $s;
        }
        return array_unique($out) ?: ['email'];
    }

    private static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        // 允许 ?access_token=
        return $_GET['access_token'] ?? null;
    }

    private static function oakBase(): string
    {
        return rtrim((string) Config::get('oauth.oakskin.base_url', 'https://skin.oak-ms.top'), '/');
    }

    private static function oakClientId(): string
    {
        return (string) Config::get('oauth.oakskin.client_id', '2');
    }

    private static function oakClientSecret(): string
    {
        return (string) Config::get('oauth.oakskin.client_secret', '');
    }

    private static function oakRedirect(): string
    {
        $c = (string) Config::get('oauth.oakskin.redirect_uri', 'https://mcskin.oak-ms.top/callback/oakskin');
        return $c;
    }

    private static function oakScope(): string
    {
        return (string) Config::get('oauth.oakskin.scope', '');
    }

    private static function oauthError(string $code, string $desc): never
    {
        \App\Log::error('OakSkin OAuth 提供方错误', ['error' => $code, 'desc' => $desc, 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
        self::oauthJson(['error' => $code, 'error_description' => $desc], 400);
    }

    private static function oauthJson(array $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /** POST JSON 请求 */
    private static function httpJson(string $url, array $data): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $res = curl_exec($ch);
            if ($res === false) {
                \App\Log::error('OakSkin login: curl 请求失败', ['url' => $url, 'err' => curl_error($ch)]);
                curl_close($ch);
                return [];
            }
            curl_close($ch);
            return json_decode($res, true) ?: [];
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 15,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res ? (json_decode($res, true) ?: []) : [];
    }

    /** 探测多个用户信息接口，返回带身份标识的用户数组 */
    private static function oakUserInfo(string $base, string $token): ?array
    {
        $candidates = [
            '/api/oauth/userinfo',
            '/oauth/userinfo',
            '/api/user',
            '/api/auth/user',
        ];
        // 只有包含下列任一「身份标识」字段时才认定是用户，避免把 404/401 错误页当用户
        $idKeys = ['email', 'uid', 'id', 'sub', 'nickname', 'name', 'username'];
        foreach ($candidates as $path) {
            [$status, $raw] = self::httpGetRaw($base . $path, $token);
            \App\Log::error('OakSkin login: 尝试获取用户信息', [
                'path' => $path,
                'status' => $status,
                'body' => self::truncate((string) $raw),
            ]);
            $body = json_decode($raw, true);
            if (!is_array($body)) {
                continue;
            }
            foreach ($idKeys as $k) {
                if (isset($body[$k])) {
                    return $body;
                }
            }
        }
        return null;
    }

    /** GET 请求，返回 [HTTP 状态码, 原始响应体] */
    private static function httpGetRaw(string $url, string $token): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $raw = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                return [$status, "curl-error:{$err}"];
            }
            return [$status, (string) $raw];
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n",
            'timeout' => 15,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        return [0, (string) $raw];
    }

    /** 截断用于日志的字符串 */
    private static function truncate(string $s): string
    {
        $s = trim($s);
        return strlen($s) > 500 ? substr($s, 0, 500) . '...' : $s;
    }
}