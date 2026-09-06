<?php
/**
 * Yggdrasil Profile：UUID 分配、textures 构造与签名
 * 参考原 Node profile.js 的算法（离线 UUID v3 / v4）
 */
declare(strict_types=1);

namespace App\Yggdrasil;

use App\Config;
use App\Database;
use App\Yggdrasil\Keys;

class Profile
{
    /** 根据角色名获取/分配 UUID */
    public static function getUuidFromName(string $name): string
    {
        $existing = Database::get('SELECT uuid FROM uuid WHERE name = ?', [$name]);
        if ($existing) return $existing['uuid'];

        $algorithm = Config::get('yggdrasil.uuid_algorithm', 'v3');
        if ($algorithm === 'v4') {
            $uuid = bin2hex(random_bytes(16));
        } else {
            $uuid = self::generateUuidV3($name);
        }

        Database::run('INSERT INTO uuid (name, uuid) VALUES (?, ?)', [$name, $uuid]);
        return $uuid;
    }

    /** 离线 UUID v3：MD5("OfflinePlayer:".$name)，并设版本/变体位 */
    public static function generateUuidV3(string $name): string
    {
        $data = md5('OfflinePlayer:' . $name, true);
        $bytes = unpack('C*', $data);
        // 版本位 (索引 6 -> 第7字节)
        $bytes[7] = ($bytes[7] & 0x0f) | 0x30;
        // 变体位 (索引 8 -> 第9字节)
        $bytes[9] = ($bytes[9] & 0x3f) | 0x80;
        $binary = '';
        foreach ($bytes as $b) $binary .= chr($b);
        return bin2hex($binary);
    }

    /** 从玩家记录创建 Profile，返回 null 说明角色不存在 */
    public static function createFromPlayer(?array $player): ?array
    {
        if (!$player) return null;

        $model = 'default';
        $skinHash = null;
        if (!empty($player['tid_skin'])) {
            $skin = Database::get('SELECT hash, type FROM textures WHERE tid = ?', [$player['tid_skin']]);
            if ($skin) {
                $skinHash = $skin['hash'];
                $model = $skin['type'] === 'alex' ? 'slim' : 'default';
            }
        }

        $capeHash = null;
        if (!empty($player['tid_cape'])) {
            $cape = Database::get('SELECT hash FROM textures WHERE tid = ?', [$player['tid_cape']]);
            if ($cape) $capeHash = $cape['hash'];
        }

        $uuid = self::getUuidFromName($player['name']);

        return [
            'uuid' => $uuid,
            'name' => $player['name'],
            'model' => $model,
            'skin' => $skinHash,
            'cape' => $capeHash,
            'player' => $player,
        ];
    }

    /** 按 UUID 查 Profile */
    public static function createFromUuid(string $uuid): ?array
    {
        $result = Database::get('SELECT name FROM uuid WHERE uuid = ?', [$uuid]);
        if (!$result) return null;
        $player = Database::get('SELECT * FROM players WHERE name = ?', [$result['name']]);
        if (!$player) return null;
        return self::createFromPlayer($player);
    }

    /** 序列化 Profile（与 BS serialize 一致）。$unsigned=false 时为带签名版本 */
    public static function serialize(array $profile, ?bool $unsigned = null): string
    {
        if (!is_bool($unsigned)) {
            $unsigned = true;
        }

        $textures = [
            'timestamp' => (int) (microtime(true) * 1000),
            'profileId' => str_replace('-', '', $profile['uuid']),
            'profileName' => $profile['name'],
            'isPublic' => true,
            'textures' => new \stdClass(),
        ];

        if ($unsigned === false) {
            $textures['signatureRequired'] = true;
        }

        $siteUrl = Config::siteUrl();

        if ($profile['skin']) {
            $skinObj = ['url' => $siteUrl . '/textures/' . $profile['skin'] . '.png'];
            if ($profile['model'] === 'slim') {
                $skinObj['metadata'] = ['model' => 'slim'];
            }
            $textures['textures']->SKIN = $skinObj;
        }

        if ($profile['cape']) {
            $textures['textures']->CAPE = [
                'url' => $siteUrl . '/textures/' . $profile['cape'] . '.png',
            ];
        }

        $result = [
            'id' => str_replace('-', '', $profile['uuid']),
            'name' => $profile['name'],
            'properties' => [
                [
                    'name' => 'textures',
                    'value' => base64_encode(json_encode($textures, JSON_UNESCAPED_UNICODE)),
                ],
                [
                    'name' => 'uploadableTextures',
                    'value' => 'skin,cape',
                ],
            ],
        ];

        if ($unsigned === false) {
            foreach ($result['properties'] as $i => $prop) {
                $result['properties'][$i]['signature'] = Keys::sign($prop['value']);
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}