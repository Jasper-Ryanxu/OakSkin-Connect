const express = require('express')
const router = express.Router()
const db = require('../db')
const { authenticate, requireAdmin } = require('../middleware/auth')

// 获取站点统计
router.get('/stats', authenticate, requireAdmin, (req, res) => {
  const database = db.getDb()

  const userCount = database.prepare('SELECT COUNT(*) as cnt FROM users').get()
  const skinCount = database.prepare('SELECT COUNT(*) as cnt FROM textures').get()
  const playerCount = database.prepare('SELECT COUNT(*) as cnt FROM players').get()
  const todayUpload = database.prepare(
    "SELECT COUNT(*) as cnt FROM textures WHERE date(upload_at) = date('now')"
  ).get()

  res.json({
    code: 0,
    data: {
      users: userCount.cnt,
      skins: skinCount.cnt,
      players: playerCount.cnt,
      today_uploads: todayUpload.cnt
    }
  })
})

// 获取用户列表
router.get('/users', authenticate, requireAdmin, (req, res) => {
  const database = db.getDb()
  const keyword = req.query.keyword || ''
  const page = parseInt(req.query.page) || 1
  const perPage = parseInt(req.query.per_page) || 20
  const offset = (page - 1) * perPage

  let where = ''
  let params = []
  if (keyword) {
    where = 'WHERE email LIKE ? OR nickname LIKE ?'
    params.push(`%${keyword}%`, `%${keyword}%`)
  }

  const countResult = database.prepare(`SELECT COUNT(*) as cnt FROM users ${where}`).get(...params)
  const users = database.prepare(
    `SELECT uid, email, nickname, permission, score, avatar, verified, register_at FROM users ${where} ORDER BY uid DESC LIMIT ? OFFSET ?`
  ).all(...params, perPage, offset)

  res.json({
    code: 0,
    data: users,
    total: countResult.cnt,
    current_page: page,
    per_page: perPage
  })
})

// 修改用户权限
router.put('/users/:uid/permission', authenticate, requireAdmin, (req, res) => {
  const database = db.getDb()
  const targetUid = parseInt(req.params.uid)
  const { permission } = req.body
  const currentUser = req.user

  // 检查目标用户是否存在
  const target = database.prepare('SELECT uid, permission, nickname FROM users WHERE uid = ?').get(targetUid)
  if (!target) {
    return res.status(404).json({ code: 404, message: '用户不存在' })
  }

  // 权限规则：
  // 1. 只有站长(permission=2)可以设置管理员(permission=1)
  // 2. 管理员(permission=1)不能修改站长(permission=2)的权限
  // 3. 管理员(permission=1)不能提升他人为管理员
  // 4. 不能修改自己的权限
  // 5. 不能修改站长的权限（即使是管理员）

  if (targetUid === currentUser.uid) {
    return res.status(403).json({ code: 403, message: '不能修改自己的权限' })
  }

  if (target.permission >= 2 && currentUser.permission < 2) {
    return res.status(403).json({ code: 403, message: '无权修改站长的权限' })
  }

  if (permission === 1 && currentUser.permission < 2) {
    return res.status(403).json({ code: 403, message: '只有站长可以设置管理员' })
  }

  if (permission < -1 || permission > 2) {
    return res.status(422).json({ code: 422, message: '无效的权限值' })
  }

  database.prepare('UPDATE users SET permission = ? WHERE uid = ?').run(permission, targetUid)

  const roleNames = { 2: '超级管理员', 1: '管理员', 0: '用户', '-1': '封禁' }
  res.json({
    code: 0,
    message: `已将 ${target.nickname} 的权限设置为 ${roleNames[permission] || permission}`
  })
})

module.exports = router