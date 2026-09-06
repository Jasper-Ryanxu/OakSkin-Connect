const express = require('express')
const multer = require('multer')
const crypto = require('crypto')
const path = require('path')
const fs = require('fs')
const router = express.Router()
const db = require('../db')
const profileApp = require('../yggdrasil/profile')
const tokenStore = require('../yggdrasil/token')
const { ensureKeys, getPublicKey, sign } = require('../yggdrasil/keys')

// ===================== 配置 =====================
const configPath = path.join(__dirname, '..', '..', 'config.json')
const texturesDir = path.join(__dirname, '..', '..', 'storage', 'textures')

function loadConfig() {
  return JSON.parse(fs.readFileSync(configPath, 'utf-8'))
}

function siteUrl() {
  const cfg = loadConfig()
  let url = (cfg.site && cfg.site.url) || ''
  return url.replace(/\/$/, '')
}

function siteHost() {
  try {
    return new URL(siteUrl()).host
  } catch (e) {
    return ''
  }
}

// ===================== 服务器会话缓存（join -> hasJoined） =====================
const serverSessions = new Map() // serverId -> { profileId, ip }

// ===================== 用户查询辅助 =====================
function getUserByEmail(email) {
  const database = db.getDb()
  return database.prepare('SELECT * FROM users WHERE email = ?').get(email)
}

function getUserByNameOrEmail(identification) {
  const database = db.getDb()
  if (String(identification).includes('@')) {
    return getUserByEmail(String(identification).toLowerCase())
  }
  // 角色名 -> 玩家 -> 用户
  const player = database.prepare('SELECT * FROM players WHERE name = ?').get(identification)
  if (!player) return null
  return database.prepare('SELECT * FROM users WHERE uid = ?').get(player.uid)
}

function checkPassword(user, password) {
  if (!user || !db.verifyPassword(password, user.password)) return false
  return true
}

// 生成用户的 UUID（参照 BS 用 uuid5(NAMESPACE_DNS, email)）
function userUuid(email) {
  const NAMESPACE_DNS = Buffer.from('6ba7b810-9dad-11d1-80b4-00c04fd430c8'.replace(/-/g, ''), 'hex')
  const hash = crypto.createHash('sha1')
  hash.update(NAMESPACE_DNS)
  hash.update(Buffer.from(email, 'utf-8'))
  const digest = hash.digest()
  digest[6] = (digest[6] & 0x0f) | 0x50
  digest[8] = (digest[8] & 0x3f) | 0x80
  return digest.toString('hex')
}

function availableProfiles(user) {
  const database = db.getDb()
  const players = database.prepare('SELECT * FROM players WHERE uid = ?').all(user.uid)
  return players.map(p => ({
    id: profileApp.getUuidFromName(p.name),
    name: p.name
  }))
}

// ===================== 错误响应（Yggdrasil 标准） =====================
function forbidden(msg) {
  return res401(403, 'ForbiddenOperationException', msg)
}
function illegal(msg) {
  return res401(400, 'IllegalArgumentException', msg)
}
function res401(status, error, msg) {
  const e = new Error(msg)
  e.status = status
  e.errorName = error
  return e
}

// 统一错误处理
function wrap(handler) {
  return (req, res) => {
    try {
      handler(req, res)
    } catch (e) {
      const status = e.status || 400
      const name = e.errorName || 'IllegalArgumentException'
      res.status(status).json({ error: name, errorMessage: e.message })
    }
  }
}

// ===================== 元数据 (GET /api/yggdrasil) =====================
router.get('/', wrap((req, res) => {
  ensureKeys()
  const cfg = loadConfig()
  const domains = new Set()
  if (siteHost()) domains.add(siteHost())
  // 额外皮肤域名配置
  if (cfg.yggdrasil && cfg.yggdrasil.skin_domains) {
    cfg.yggdrasil.skin_domains.split(',').map(s => s.trim()).filter(Boolean)
      .forEach(d => domains.add(d))
  }
  res.json({
    meta: {
      serverName: (cfg.site && cfg.site.name) || 'OakSkin Connect',
      implementationName: 'Yggdrasil API for OakSkin Connect',
      implementationVersion: '1.0.0',
      links: {
        homepage: siteUrl(),
        register: siteUrl() + '/register'
      },
      'feature.non_email_login': true
    },
    skinDomains: Array.from(domains),
    signaturePublickey: getPublicKey()
  })
}))

// ===================== authserver/authenticate =====================
router.post('/authserver/authenticate', wrap((req, res) => {
  const username = req.body.username
  const password = req.body.password
  const requestUser = req.body.requestUser
  const clientToken = req.body.clientToken || generateClientToken()

  if (!username || !password) {
    throw illegal('用户名和密码不能为空')
  }

  const user = getUserByNameOrEmail(username)
  if (!checkPassword(user, password)) {
    throw forbidden('邮箱/用户名与密码不匹配')
  }

  const uuid = userUuid(user.email)
  const profiles = availableProfiles(user)

  // 只有一个角色时自动选择
  let profileId = ''
  if (profiles.length === 1) {
    profileId = profiles[0].id
  }

  const accessToken = tokenStore.issue({
    owner: user.email,
    clientToken,
    profileId
  })

  const resp = {
    accessToken,
    clientToken,
    availableProfiles: profiles
  }

  if (requestUser) {
    resp.user = { id: uuid, properties: [] }
  }

  if (profiles.length === 1) {
    resp.selectedProfile = profiles[0]
  }

  res.json(resp)
}))

// ===================== authserver/refresh =====================
router.post('/authserver/refresh', wrap((req, res) => {
  const accessToken = req.body.accessToken
  const clientToken = req.body.clientToken
  const requestUser = req.body.requestUser
  const selectedProfile = req.body.selectedProfile

  const token = tokenStore.find(accessToken)
  if (!token) {
    throw forbidden('访问令牌无效')
  }
  if (clientToken && token.clientToken !== clientToken) {
    throw forbidden('客户端令牌不匹配')
  }

  const user = getUserByEmail(token.owner)
  if (!user) {
    throw forbidden('用户不存在')
  }

  const profiles = availableProfiles(user)
  const uuid = userUuid(user.email)

  let profileId = token.profileId
  if (selectedProfile && selectedProfile.id) {
    // 校验所选角色属于该用户
    const belongs = profiles.some(p => p.id === selectedProfile.id)
    if (!belongs) {
      throw forbidden('所选角色不属于该用户')
    }
    profileId = selectedProfile.id
  }

  const resp = {
    accessToken: accessToken,
    clientToken: token.clientToken,
    availableProfiles: profiles
  }

  if (requestUser) {
    resp.user = { id: uuid, properties: [] }
  }

  const selected = profiles.find(p => p.id === (selectedProfile && selectedProfile.id) || p.id === profileId)
  if (selected) {
    resp.selectedProfile = selected
    profileId = selected.id
  }

  // 吊销旧令牌，签发新令牌
  tokenStore.revoke(accessToken)
  const newToken = tokenStore.issue({ owner: user.email, clientToken: token.clientToken, profileId })
  resp.accessToken = newToken

  res.json(resp)
}))

// ===================== authserver/validate =====================
router.post('/authserver/validate', wrap((req, res) => {
  const accessToken = req.body.accessToken
  const clientToken = req.body.clientToken

  const token = tokenStore.find(accessToken)
  if (!token) {
    throw forbidden('访问令牌无效')
  }
  if (clientToken && token.clientToken !== clientToken) {
    throw forbidden('客户端令牌不匹配')
  }
  res.status(204).end()
}))

// ===================== authserver/signout =====================
router.post('/authserver/signout', wrap((req, res) => {
  const username = req.body.username
  const password = req.body.password
  const user = getUserByNameOrEmail(username)
  if (!checkPassword(user, password)) {
    throw forbidden('邮箱/用户名与密码不匹配')
  }
  tokenStore.revokeAll(user.email)
  res.status(204).end()
}))

// ===================== authserver/invalidate =====================
router.post('/authserver/invalidate', wrap((req, res) => {
  const accessToken = req.body.accessToken
  if (accessToken) tokenStore.revoke(accessToken)
  res.status(204).end()
}))

// ===================== sessionserver/join =====================
router.post('/sessionserver/session/minecraft/join', wrap((req, res) => {
  const accessToken = req.body.accessToken
  const selectedProfile = req.body.selectedProfile
  const serverId = req.body.serverId

  const token = tokenStore.find(accessToken)
  if (!token) {
    throw forbidden('访问令牌无效')
  }

  const user = getUserByEmail(token.owner)
  if (!user) {
    throw forbidden('用户不存在')
  }

  // 校验所选角色属于 token owner
  const profiles = availableProfiles(user)
  if (!profiles.some(p => p.id === selectedProfile)) {
    throw forbidden('所选角色不属于该用户')
  }

  // 记录加入会话（IP 可选，authlib-injector 供校验）
  serverSessions.set(serverId, {
    profileId: selectedProfile,
    ip: req.ip
  })

  res.status(204).end()
}))

// ===================== sessionserver/hasJoined =====================
router.get('/sessionserver/session/minecraft/hasJoined', wrap((req, res) => {
  const username = req.query.username
  const serverId = req.query.serverId
  const ip = req.query.ip

  const session = serverSessions.get(serverId)
  if (!session) {
    return res.status(204).end()
  }

  const profile = profileApp.createFromUuid(session.profileId)
  if (!profile || profile.name !== username) {
    return res.status(204).end()
  }

  // IP 不匹配则拒绝
  if (ip && session.ip && ip !== session.ip) {
    return res.status(204).end()
  }

  res.set('Content-Type', 'application/json')
  res.send(profileApp.serialize(profile, false))
}))

// ===================== sessionserver/profile/{uuid} =====================
router.get('/sessionserver/session/minecraft/profile/:uuid', wrap((req, res) => {
  const uuid = req.params.uuid
  const profile = profileApp.createFromUuid(uuid)
  if (!profile) {
    return res.status(204).end()
  }

  // ?unsigned=false 返回带签名版本，默认不签名
  let unsigned = true
  if (req.query.unsigned === 'false') unsigned = false
  if (req.query.unsigned === 'true') unsigned = true

  res.set('Content-Type', 'application/json')
  res.send(profileApp.serialize(profile, unsigned))
}))

// ===================== api/profiles/minecraft (批量查询) =====================
router.post('/api/profiles/minecraft', wrap((req, res) => {
  const names = Array.isArray(req.body) ? req.body : []
  const result = []
  for (const name of names) {
    const database = db.getDb()
    const player = database.prepare('SELECT * FROM players WHERE name = ?').get(name)
    if (player) {
      result.push({
        id: profileApp.getUuidFromName(player.name),
        name: player.name
      })
    }
  }
  res.json(result)
}))

// ===================== api/users/profiles/minecraft/{username} =====================
router.get('/api/users/profiles/minecraft/:username', wrap((req, res) => {
  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE name = ?').get(req.params.username)
  if (!player) {
    return res.status(204).end()
  }
  res.json({
    id: profileApp.getUuidFromName(player.name),
    name: player.name
  })
}))

// ===================== 上传/重置材质（Bearer 鉴权） =====================
const upload = multer({ storage: multer.memoryStorage() })

function bearerToken(req) {
  const h = req.headers.authorization || ''
  if (!h.startsWith('Bearer ')) return null
  return h.slice(7)
}

// PUT /api/user/profile/:uuid/:type
router.put('/api/user/profile/:uuid/:type', upload.single('file'), wrap((req, res) => {
  const accessToken = bearerToken(req)
  const token = tokenStore.find(accessToken)
  if (!token) {
    throw forbidden('访问令牌无效')
  }

  const user = getUserByEmail(token.owner)
  if (!user) {
    throw forbidden('用户不存在')
  }

  const profile = profileApp.createFromUuid(req.params.uuid)
  if (!profile) {
    throw forbidden('角色不存在')
  }

  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?')
    .get(profile.player.pid, user.uid)
  if (!player) {
    throw forbidden('无权操作该角色')
  }

  const type = req.params.type // skin | cape
  if (!req.file) {
    throw illegal('缺少文件')
  }

  const hash = crypto.createHash('sha256').update(req.file.buffer).digest('hex')
  if (!fs.existsSync(texturesDir)) fs.mkdirSync(texturesDir, { recursive: true })
  const filePath = path.join(texturesDir, hash + '.png')
  if (!fs.existsSync(filePath)) {
    fs.writeFileSync(filePath, req.file.buffer)
  }

  // 查找或创建材质记录
  let texture = database.prepare('SELECT tid FROM textures WHERE hash = ? AND uploader = ?').get(hash, user.uid)
  if (!texture) {
    const model = type === 'skin' ? (req.body.model === 'slim' ? 'alex' : 'steve') : 'cape'
    const r = database.prepare(
      'INSERT INTO textures (name, type, hash, size, uploader, public) VALUES (?, ?, ?, ?, ?, 0)'
    ).run(type + '-' + hash.slice(0, 8), model, hash, req.file.size, user.uid)
    texture = { tid: r.lastInsertRowid }
  }

  database.prepare(`UPDATE players SET tid_${type} = ?, last_modified = datetime('now') WHERE pid = ?`)
    .run(texture.tid, player.pid)

  res.status(204).end()
}))

// DELETE /api/user/profile/:uuid/:type
router.delete('/api/user/profile/:uuid/:type', wrap((req, res) => {
  const accessToken = bearerToken(req)
  const token = tokenStore.find(accessToken)
  if (!token) {
    throw forbidden('访问令牌无效')
  }

  const user = getUserByEmail(token.owner)
  if (!user) {
    throw forbidden('用户不存在')
  }

  const profile = profileApp.createFromUuid(req.params.uuid)
  if (!profile) {
    throw forbidden('角色不存在')
  }

  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?')
    .get(profile.player.pid, user.uid)
  if (!player) {
    throw forbidden('无权操作该角色')
  }

  const type = req.params.type
  database.prepare(`UPDATE players SET tid_${type} = NULL, last_modified = datetime('now') WHERE pid = ?`)
    .run(player.pid)

  res.status(204).end()
}))

function generateClientToken() {
  return crypto.randomBytes(16).toString('hex')
}

module.exports = router