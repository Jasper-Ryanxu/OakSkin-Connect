const express = require('express')
const router = express.Router()
const path = require('path')
const fs = require('fs')
const db = require('../db')
const { authenticate } = require('../middleware/auth')

// 获取当前用户信息
router.get('/', authenticate, (req, res) => {
  res.json(req.user)
})

// 更新个人资料
router.post('/profile', authenticate, (req, res) => {
  const { nickname, password, current_password } = req.body
  const database = db.getDb()

  if (nickname) {
    database.prepare('UPDATE users SET nickname = ? WHERE uid = ?').run(nickname, req.user.uid)
  }

  if (password && current_password) {
    const user = database.prepare('SELECT * FROM users WHERE uid = ?').get(req.user.uid)
    if (!db.verifyPassword(current_password, user.password)) {
      return res.status(422).json({ code: 422, message: '当前密码错误' })
    }
    const hashed = db.hashPassword(password)
    database.prepare('UPDATE users SET password = ? WHERE uid = ?').run(hashed, req.user.uid)
  }

  res.json({ code: 0, message: '资料已更新' })
})

// 设置头像
router.post('/avatar', authenticate, (req, res) => {
  const { tid } = req.body
  if (!tid) return res.status(422).json({ code: 422, message: '缺少参数' })

  const database = db.getDb()
  const skin = database.prepare('SELECT tid FROM textures WHERE tid = ?').get(tid)
  if (!skin) return res.status(404).json({ code: 404, message: '皮肤不存在' })

  database.prepare('UPDATE users SET avatar = ? WHERE uid = ?').run(tid, req.user.uid)
  res.json({ code: 0, message: '头像已更新' })
})

// 获取头像（返回皮肤的头部位 PNG）
router.get('/:uid/avatar', (req, res) => {
  const database = db.getDb()
  const user = database.prepare('SELECT avatar FROM users WHERE uid = ?').get(req.params.uid)
  if (!user || !user.avatar) {
    return res.redirect('/example.png')
  }

  const skin = database.prepare('SELECT hash FROM textures WHERE tid = ?').get(user.avatar)
  if (!skin) {
    return res.redirect('/example.png')
  }

  res.redirect(`/textures/${skin.hash}.png`)
})

module.exports = router