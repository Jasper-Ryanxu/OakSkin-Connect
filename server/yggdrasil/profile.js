const crypto = require('crypto')
const db = require('../db')
const { sign } = require('./keys')

// 从角色名获取/分配 UUID（参考 BS generateUuidV3 / uuid4 两种算法）
// 默认使用 v3（离线UUID，稳定），可通过 config 切换
function getUuidFromName(name) {
  const database = db.getDb()
  const existing = database.prepare('SELECT uuid FROM uuid WHERE name = ?').get(name)
  if (existing) return existing.uuid

  let uuid
  let config = {}
  try {
    const fs = require('fs')
    const path = require('path')
    config = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'config.json'), 'utf-8'))
  } catch (e) {}

  const algorithm = (config.yggdrasil && config.yggdrasil.uuid_algorithm) || 'v3'
  if (algorithm === 'v4') {
    uuid = crypto.randomBytes(16).toString('hex')
  } else {
    // v3: OfflinePlayer:name 的 MD5，设置版本位和变体位
    uuid = generateUuidV3(name)
  }

  database.prepare('INSERT INTO uuid (name, uuid) VALUES (?, ?)').run(name, uuid)
  return uuid
}

function generateUuidV3(name) {
  const data = Buffer.from(crypto.createHash('md5').update('OfflinePlayer:' + name).digest())
  data[6] = (data[6] & 0x0f) | 0x30
  data[8] = (data[8] & 0x3f) | 0x80
  return data.toString('hex')
}

// 从玩家记录创建 Profile（返回 null 表示不存在）
function createFromPlayer(player) {
  if (!player) return null

  const database = db.getDb()

  // 皮肤模型
  let model = 'default'
  let skinHash = null
  if (player.tid_skin) {
    const skin = database.prepare('SELECT hash, type FROM textures WHERE tid = ?').get(player.tid_skin)
    if (skin) {
      skinHash = skin.hash
      model = skin.type === 'alex' ? 'slim' : 'default'
    }
  }

  let capeHash = null
  if (player.tid_cape) {
    const cape = database.prepare('SELECT hash FROM textures WHERE tid = ?').get(player.tid_cape)
    if (cape) capeHash = cape.hash
  }

  const uuid = getUuidFromName(player.name)

  return {
    uuid,
    name: player.name,
    model,
    skin: skinHash,
    cape: capeHash,
    player
  }
}

function createFromUuid(uuid) {
  const database = db.getDb()
  const result = database.prepare('SELECT name FROM uuid WHERE uuid = ?').get(uuid)
  if (!result) return null

  const player = database.prepare('SELECT * FROM players WHERE name = ?').get(result.name)
  if (!player) return null

  return createFromPlayer(player)
}

// 序列化 Profile（与 BS serialize 一致）
function serialize(profile, unsigned) {
  if (typeof unsigned !== 'boolean') {
    unsigned = true
  }

  let textures = {
    timestamp: Date.now(),
    profileId: profile.uuid.replace(/-/g, ''),
    profileName: profile.name,
    isPublic: true,
    textures: {}
  }

  if (unsigned === false) {
    textures.signatureRequired = true
  }

  // 站点 URL
  let config = {}
  try {
    const fs = require('fs')
    const path = require('path')
    config = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'config.json'), 'utf-8'))
  } catch (e) {}
  let siteUrl = (config.site && config.site.url) || ''
  siteUrl = siteUrl.replace(/\/$/, '')

  if (profile.skin) {
    const skinObj = {
      url: `${siteUrl}/textures/${profile.skin}.png`
    }
    if (profile.model === 'slim') {
      skinObj.metadata = { model: 'slim' }
    }
    textures.textures.SKIN = skinObj
  }

  if (profile.cape) {
    textures.textures.CAPE = {
      url: `${siteUrl}/textures/${profile.cape}.png`
    }
  }

  const result = {
    id: profile.uuid.replace(/-/g, ''),
    name: profile.name,
    properties: [
      {
        name: 'textures',
        value: Buffer.from(JSON.stringify(textures)).toString('base64')
      },
      {
        name: 'uploadableTextures',
        value: 'skin,cape'
      }
    ]
  }

  if (unsigned === false) {
    // 给每个属性签名
    result.properties.forEach(prop => {
      prop.signature = sign(prop.value)
    })
  }

  return JSON.stringify(result)
}

module.exports = { getUuidFromName, createFromPlayer, createFromUuid, serialize }