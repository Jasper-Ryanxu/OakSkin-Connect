<?php
/**
 * Yggdrasil 外置登录控制器
 * 对应原 Node server/routes/yggdrasil.js 的全部端点。
 * 端点统一在 index.php 中按 /api/yggdrasil 前缀注册。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;
use App\Yggdrasil\Keys;
use App\Yggdrasil\Profile;
use App\Yggdrasil\Token;

class YggdrasilController
{
    // ===================== 辅助查询 =====================

    private static function getUserByEmail(string $email): ?array
    {
        return Database::get('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
    }

    private static function getUserByNameOrEmail(string $identification): ?array
    {
        if (strpos($identification, '@') !== false) {
            return self::getUserByEmail($identification);
        }
        $player = Database::get('SELECT * FROM players WHERE name = ?', [$identification]);
        if (!$player) return null;
        return Database::get('SELECT * FROM users WHERE uid = ?', [$player['uid']]);
    }

    private static function checkPassword(?array $user, string $password): bool
    {
        if (!$user) return false;
        return verify_password($password, $user['password']);
    }

    /** 生成用户身份 id（参照 BS uuid5(NAMESPACE_DNS, email)） */
    private static function userUuid(string $email): string
    {
        $namespace = hex2bin('6ba7b8109dad11d180b400c04fd430c8');
        $data = sha1($namespace . $email, true);
        $bytes = unpack('C*', $data);
        $bytes[7] = ($bytes[7] & 0x0f) | 0x50; // 版本位 -> 5
        $bytes[9] = ($bytes[9] & 0x3f) | 0x80; // 变体位
        $bin = '';
        foreach ($bytes as $b) $bin .= chr($b);
        return bin2hex($bin);
    }

    /** 某用户所有可用角色 */
    private static function availableProfiles(array $user): array
    {
        $players = Database::all('SELECT name FROM players WHERE uid = ?', [$user['uid']]);
        return array_map(function ($p) {
            return ['id' => Profile::getUuidFromName($p['name']), 'name' => $p['name']];
        }, $players);
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP'] as $k) {
            if (!empty($_SERVER[$k])) {
                $first = explode(',', $_SERVER[$k])[0];
                return trim($first);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    // ===================== 输出辅助 =====================

    /** Yggdrasil 标准 JSON 响应 */
    private static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** 204 无内容 */
    private static function noContent(): never
    {
        http_response_code(204);
        echo '';
        exit;
    }

    /** Yggdrasil 标准错误 */
    private static function error(int $status, string $errorName, string $msg): never
    {
        self::json(['error' => $errorName, 'errorMessage' => $msg], $status);
    }

    private static function forbidden(string $msg): never
    {
        self::error(403, 'ForbiddenOperationException', $msg);
    }

    private static function illegal(string $msg): never
    {
        self::error(400, 'IllegalArgumentException', $msg);
    }

    // ===================== 元数据 =====================

    /** GET /api/yggdrasil */
    public function meta(): never
    {
        Keys::ensure();
        $siteUrl = Config::siteUrl();
        $domains = [];
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if ($host) $domains[] = $host;

        $skinDomains = Config::get('yggdrasil.skin_domains', '');
        if ($skinDomains) {
            foreach (explode(',', (string) $skinDomains) as $d) {
                $d = trim($d);
                if ($d !== '' && !in_array($d, $domains, true)) $domains[] = $d;
            }
        }

        self::json([
            'meta' => [
                'serverName' => (string) Config::get('site.name', 'OakSkin Connect'),
                'implementationName' => 'Yggdrasil API for OakSkin Connect',
                'implementationVersion' => '1.0.0',
                'links' => [
                    'homepage' => $siteUrl,
                    'register' => $siteUrl . '/register',
                ],
                'feature.non_email_login' => true,
            ],
            'skinDomains' => $domains,
            'signaturePublickey' => Keys::getPublicKey(),
        ]);
    }

    // ===================== authserver =====================

    /** POST /api/yggdrasil/authserver/authenticate */
    public function authenticate(): never
    {
        $body = json_body();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';
        $requestUser = $body['requestUser'] ?? false;
        $clientToken = $body['clientToken'] ?? bin2hex(random_bytes(16));

        if ($username === '' || $password === '') {
            self::illegal('用户名和密码不能为空');
        }

        $user = self::getUserByNameOrEmail($username);
        if (!self::checkPassword($user, $password)) {
            self::forbidden('邮箱/用户名与密码不匹配');
        }

        $uuid = self::userUuid($user['email']);
        $profiles = self::availableProfiles($user);

        $profileId = '';
        if (count($profiles) === 1) {
            $profileId = $profiles[0]['id'];
        }

        $accessToken = Token::issue($user['email'], $clientToken, $profileId);

        $resp = [
            'accessToken' => $accessToken,
            'clientToken' => $clientToken,
            'availableProfiles' => $profiles,
        ];

        if ($requestUser) {
            $resp['user'] = ['id' => $uuid, 'properties' => []];
        }

        if (count($profiles) === 1) {
            $resp['selectedProfile'] = $profiles[0];
        }

        self::json($resp);
    }

    /** POST /api/yggdrasil/authserver/refresh */
    public function refresh(): never
    {
        $body = json_body();
        $accessToken = $body['accessToken'] ?? '';
        $clientToken = $body['clientToken'] ?? '';
        $requestUser = $body['requestUser'] ?? false;
        $selectedProfile = $body['selectedProfile'] ?? null;

        $token = Token::find($accessToken);
        if (!$token) self::forbidden('访问令牌无效');
        if ($clientToken && $token['client_token'] !== $clientToken) self::forbidden('客户端令牌不匹配');

        $user = self::getUserByEmail($token['owner']);
        if (!$user) self::forbidden('用户不存在');

        $profiles = self::availableProfiles($user);
        $uuid = self::userUuid($user['email']);

        $profileId = $token['profile_id'];
        if (!empty($selectedProfile['id'])) {
            $belongs = false;
            foreach ($profiles as $p) {
                if ($p['id'] === $selectedProfile['id']) { $belongs = true; break; }
            }
            if (!$belongs) self::forbidden('所选角色不属于该用户');
            $profileId = $selectedProfile['id'];
        }

        $resp = [
            'accessToken' => $accessToken,
            'clientToken' => $token['client_token'],
            'availableProfiles' => $profiles,
        ];

        if ($requestUser) {
            $resp['user'] = ['id' => $uuid, 'properties' => []];
        }

        $selected = null;
        foreach ($profiles as $p) {
            $target = !empty($selectedProfile['id']) ? $selectedProfile['id'] : $profileId;
            if ($p['id'] === $target) { $selected = $p; break; }
        }
        if ($selected) {
            $resp['selectedProfile'] = $selected;
            $profileId = $selected['id'];
        }

        Token::revoke($accessToken);
        $resp['accessToken'] = Token::issue($user['email'], $token['client_token'], $profileId);

        self::json($resp);
    }

    /** POST /api/yggdrasil/authserver/validate */
    public function validate(): never
    {
        $body = json_body();
        $accessToken = $body['accessToken'] ?? '';
        $clientToken = $body['clientToken'] ?? '';

        $token = Token::find($accessToken);
        if (!$token) self::forbidden('访问令牌无效');
        if ($clientToken && $token['client_token'] !== $clientToken) self::forbidden('客户端令牌不匹配');

        self::noContent();
    }

    /** POST /api/yggdrasil/authserver/signout */
    public function signout(): never
    {
        $body = json_body();
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        $user = self::getUserByNameOrEmail($username);
        if (!self::checkPassword($user, $password)) self::forbidden('邮箱/用户名与密码不匹配');

        Token::revokeAll($user['email']);
        self::noContent();
    }

    /** POST /api/yggdrasil/authserver/invalidate */
    public function invalidate(): never
    {
        $body = json_body();
        $accessToken = $body['accessToken'] ?? '';
        if ($accessToken) Token::revoke($accessToken);
        self::noContent();
    }

    // ===================== sessionserver =====================

    /** POST /api/yggdrasil/sessionserver/session/minecraft/join */
    public function join(): never
    {
        $body = json_body();
        $accessToken = $body['accessToken'] ?? '';
        $selectedProfile = $body['selectedProfile'] ?? '';
        $serverId = $body['serverId'] ?? '';

        $token = Token::find($accessToken);
        if (!$token) self::forbidden('访问令牌无效');

        $user = self::getUserByEmail($token['owner']);
        if (!$user) self::forbidden('用户不存在');

        $profiles = self::availableProfiles($user);
        $belongs = false;
        foreach ($profiles as $p) {
            if ($p['id'] === $selectedProfile) { $belongs = true; break; }
        }
        if (!$belongs) self::forbidden('所选角色不属于该用户');

        Database::run(
            'INSERT INTO ygg_sessions (server_id, profile_id, ip, created_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE profile_id = VALUES(profile_id), ip = VALUES(ip), created_at = VALUES(created_at)',
            [$serverId, $selectedProfile, self::clientIp(), time()]
        );

        self::noContent();
    }

    /** GET /api/yggdrasil/sessionserver/session/minecraft/hasJoined */
    public function hasJoined(): never
    {
        $username = $_GET['username'] ?? '';
        $serverId = $_GET['serverId'] ?? '';
        $ip = $_GET['ip'] ?? '';

        $session = Database::get('SELECT * FROM ygg_sessions WHERE server_id = ?', [$serverId]);
        if (!$session) self::noContent();

        $profile = Profile::createFromUuid($session['profile_id']);
        if (!$profile || $profile['name'] !== $username) self::noContent();

        if ($ip !== '' && $session['ip'] !== '' && $ip !== $session['ip']) self::noContent();

        self::json(json_decode(Profile::serialize($profile, false), true));
    }

    /** GET /api/yggdrasil/sessionserver/session/minecraft/profile/:uuid */
    public function profile(string $uuid): never
    {
        $profile = Profile::createFromUuid($uuid);
        if (!$profile) self::noContent();

        $unsigned = true;
        if (isset($_GET['unsigned'])) {
            if ($_GET['unsigned'] === 'false') $unsigned = false;
            elseif ($_GET['unsigned'] === 'true') $unsigned = true;
        }

        self::json(json_decode(Profile::serialize($profile, $unsigned), true));
    }

    // ===================== api/profiles =====================

    /** POST /api/yggdrasil/api/profiles/minecraft */
    public function profilesByNames(): never
    {
        $names = json_body();
        if (!is_array($names)) $names = [];

        $result = [];
        foreach ($names as $name) {
            $player = Database::get('SELECT name FROM players WHERE name = ?', [(string) $name]);
            if ($player) {
                $result[] = ['id' => Profile::getUuidFromName($player['name']), 'name' => $player['name']];
            }
        }
        self::json($result);
    }

    /** GET /api/yggdrasil/api/users/profiles/minecraft/:username */
    public function userProfileByName(string $username): never
    {
        $player = Database::get('SELECT name FROM players WHERE name = ?', [$username]);
        if (!$player) self::noContent();
        self::json(['id' => Profile::getUuidFromName($player['name']), 'name' => $player['name']]);
    }

    // ===================== 材质上传/重置 =====================

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /** PUT /api/yggdrasil/api/user/profile/:uuid/:type */
    public function uploadTexture(string $uuid, string $type): never
    {
        $accessToken = self::bearerToken();
        $token = $accessToken ? Token::find($accessToken) : null;
        if (!$token) self::forbidden('访问令牌无效');

        $user = self::getUserByEmail($token['owner']);
        if (!$user) self::forbidden('用户不存在');

        $profile = Profile::createFromUuid($uuid);
        if (!$profile) self::forbidden('角色不存在');

        $player = Database::get('SELECT * FROM players WHERE pid = ? AND uid = ?', [$profile['player']['pid'], $user['uid']]);
        if (!$player) self::forbidden('无权操作该角色');

        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            self::illegal('缺少文件');
        }

        $buffer = file_get_contents($_FILES['file']['tmp_name']);
        $hash = hash('sha256', $buffer);

        if (!is_dir(BS_TEXTURES)) {
            @mkdir(BS_TEXTURES, 0777, true);
        }
        $filePath = BS_TEXTURES . '/' . $hash . '.png';
        if (!is_file($filePath)) {
            file_put_contents($filePath, $buffer);
        }

        $texture = Database::get('SELECT tid FROM textures WHERE hash = ? AND uploader = ?', [$hash, $user['uid']]);
        if (!$texture) {
            $model = ($type === 'skin')
                ? (($_POST['model'] ?? 'steve') === 'slim' ? 'alex' : 'steve')
                : 'cape';
            $result = Database::run(
                'INSERT INTO textures (name, type, hash, size, uploader, public) VALUES (?, ?, ?, ?, ?, 0)',
                [$type . '-' . substr($hash, 0, 8), $model, $hash, (int) $_FILES['file']['size'], $user['uid']]
            );
            $tid = (int) $result['lastInsertRowid'];
        } else {
            $tid = (int) $texture['tid'];
        }

        $column = $type === 'skin' ? 'tid_skin' : 'tid_cape';
        Database::run("UPDATE players SET $column = ?, last_modified = NOW() WHERE pid = ?", [$tid, $player['pid']]);

        self::noContent();
    }

    /** DELETE /api/yggdrasil/api/user/profile/:uuid/:type */
    public function resetTexture(string $uuid, string $type): never
    {
        $accessToken = self::bearerToken();
        $token = $accessToken ? Token::find($accessToken) : null;
        if (!$token) self::forbidden('访问令牌无效');

        $user = self::getUserByEmail($token['owner']);
        if (!$user) self::forbidden('用户不存在');

        $profile = Profile::createFromUuid($uuid);
        if (!$profile) self::forbidden('角色不存在');

        $player = Database::get('SELECT * FROM players WHERE pid = ? AND uid = ?', [$profile['player']['pid'], $user['uid']]);
        if (!$player) self::forbidden('无权操作该角色');

        $column = $type === 'skin' ? 'tid_skin' : 'tid_cape';
        Database::run("UPDATE players SET $column = NULL, last_modified = NOW() WHERE pid = ?", [$player['pid']]);

        self::noContent();
    }
}