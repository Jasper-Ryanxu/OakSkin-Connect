const express = require('express')
const router = express.Router()
const path = require('path')
const fs = require('fs')
const multer = require('multer')
const db = require('../db')
const { authenticate } = require('../middleware/auth')

// 读取配置
const config = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'config.json'), 'utf-8'))

// 登录
router.post('/login', (req, res) => {
  const { email, password } = req.body
  if (!email || !password) {
    return res.status(422).json({ code: 422, message: '请填写邮箱和密码' })
  }

  const database = db.getDb()
  const user = database.prepare('SELECT * FROM users WHERE email = ?').get(email)

  if (!user) {
    return res.status(401).json({ code: 401, message: '邮箱或密码错误' })
  }

  if (!db.verifyPassword(password, user.password)) {
    return res.status(401).json({ code: 401, message: '邮箱或密码错误' })
  }

  // 生成令牌
  const token = db.generateToken()
  const expiresAt = new Date(Date.now() + (config.auth.token_lifetime || 86400) * 1000).toISOString()

  database.prepare('INSERT INTO tokens (uid, token, expires_at) VALUES (?, ?, ?)')
    .run(user.uid, token, expiresAt)

  res.json({
    token: token,
    user: {
      uid: user.uid,
      email: user.email,
      nickname: user.nickname,
      permission: user.permission,
      score: user.score,
      avatar: user.avatar,
      verified: user.verified
    }
  })
})

// 注册
router.post('/register', (req, res) => {
  const { email, password, nickname } = req.body

  if (!email || !password || !nickname) {
    return res.status(422).json({ code: 422, message: '请填写所有必填字段' })
  }

  if (!config.auth.allow_register) {
    return res.status(403).json({ code: 403, message: '注册已关闭' })
  }

  if (password.length < (config.auth.password_min_length || 8)) {
    return res.status(422).json({ code: 422, message: `密码长度至少为${config.auth.password_min_length || 8}位` })
  }

  const database = db.getDb()
  const existing = database.prepare('SELECT uid FROM users WHERE email = ?').get(email)
  if (existing) {
    return res.status(422).json({ code: 422, message: '该邮箱已被注册' })
  }

  const hashed = db.hashPassword(password)
  const result = database.prepare(`
    INSERT INTO users (email, password, nickname, permission, verified)
    VALUES (?, ?, ?, 0, 1)
  `).run(email, hashed, nickname)

  res.json({
    code: 0,
    message: '注册成功',
    user: {
      uid: result.lastInsertRowid,
      email,
      nickname,
      permission: 0,
      verified: 1
    }
  })
})

// 退出登录
router.post('/logout', authenticate, (req, res) => {
  const authHeader = req.headers.authorization
  const token = authHeader.substring(7)
  const database = db.getDb()
  database.prepare('DELETE FROM tokens WHERE token = ?').run(token)
  res.json({ code: 0, message: '已退出登录' })
})

module.exports = router