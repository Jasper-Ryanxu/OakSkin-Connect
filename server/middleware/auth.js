const db = require('../db')

function authenticate(req, res, next) {
  const authHeader = req.headers.authorization
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ code: 401, message: '未登录' })
  }

  const token = authHeader.substring(7)
  const database = db.getDb()
  const tokenRecord = database.prepare(`
    SELECT t.uid, t.expires_at, u.* FROM tokens t
    JOIN users u ON t.uid = u.uid
    WHERE t.token = ?
  `).get(token)

  if (!tokenRecord) {
    return res.status(401).json({ code: 401, message: '无效的令牌' })
  }

  if (new Date(tokenRecord.expires_at) < new Date()) {
    database.prepare('DELETE FROM tokens WHERE token = ?').run(token)
    return res.status(401).json({ code: 401, message: '令牌已过期' })
  }

  req.user = {
    uid: tokenRecord.uid,
    email: tokenRecord.email,
    nickname: tokenRecord.nickname,
    permission: tokenRecord.permission,
    score: tokenRecord.score,
    avatar: tokenRecord.avatar,
    verified: tokenRecord.verified
  }

  next()
}

function optionalAuth(req, res, next) {
  const authHeader = req.headers.authorization
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    req.user = null
    return next()
  }

  const token = authHeader.substring(7)
  const database = db.getDb()
  const tokenRecord = database.prepare(`
    SELECT t.uid, t.expires_at, u.* FROM tokens t
    JOIN users u ON t.uid = u.uid
    WHERE t.token = ?
  `).get(token)

  if (tokenRecord && new Date(tokenRecord.expires_at) >= new Date()) {
    req.user = {
      uid: tokenRecord.uid,
      email: tokenRecord.email,
      nickname: tokenRecord.nickname,
      permission: tokenRecord.permission,
      score: tokenRecord.score,
      avatar: tokenRecord.avatar,
      verified: tokenRecord.verified
    }
  } else {
    req.user = null
  }

  next()
}

function requireAdmin(req, res, next) {
  if (!req.user || req.user.permission < 1) {
    return res.status(403).json({ code: 403, message: '权限不足' })
  }
  next()
}

module.exports = { authenticate, optionalAuth, requireAdmin }