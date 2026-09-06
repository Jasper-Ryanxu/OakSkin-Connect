const express = require('express')
const router = express.Router()
const db = require('../db')
const { authenticate } = require('../middleware/auth')

// 获取角色列表
router.get('/list', authenticate, (req, res) => {
  const database = db.getDb()
  const players = database.prepare(`
    SELECT p.*, t.name as skin_name, t.hash as skin_hash, t.type as skin_type
    FROM players p
    LEFT JOIN textures t ON p.tid_skin = t.tid
    WHERE p.uid = ?
    ORDER BY p.last_modified DESC
  `).all(req.user.uid)

  const data = players.map(p => ({
    pid: p.pid,
    uid: p.uid,
    name: p.name,
    tid_skin: p.tid_skin,
    tid_cape: p.tid_cape,
    last_modified: p.last_modified,
    model: p.skin_type === 'alex' ? 'slim' : 'default',
    skin: p.tid_skin ? {
      tid: p.tid_skin,
      name: p.skin_name,
      hash: p.skin_hash,
      type: p.skin_type
    } : null
  }))

  res.json({ data })
})

// 添加角色
router.post('/', authenticate, (req, res) => {
  const { name } = req.body
  if (!name) {
    return res.status(422).json({ code: 422, message: '请输入角色名' })
  }

  const database = db.getDb()
  const existing = database.prepare('SELECT pid FROM players WHERE name = ?').get(name)
  if (existing) {
    return res.status(422).json({ code: 422, message: '该角色名已被占用' })
  }

  const count = database.prepare('SELECT COUNT(*) as cnt FROM players WHERE uid = ?').get(req.user.uid)
  if (count.cnt >= 10) {
    return res.status(422).json({ code: 422, message: '最多只能添加10个角色' })
  }

  const result = database.prepare(`
    INSERT INTO players (uid, name) VALUES (?, ?)
  `).run(req.user.uid, name)

  res.json({
    code: 0,
    message: '添加成功',
    pid: result.lastInsertRowid
  })
})

// 重命名角色
router.put('/:pid/name', authenticate, (req, res) => {
  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?').get(req.params.pid, req.user.uid)
  if (!player) return res.status(404).json({ code: 404, message: '角色不存在' })

  database.prepare('UPDATE players SET name = ?, last_modified = datetime(\'now\') WHERE pid = ?')
    .run(req.body.name, req.params.pid)
  res.json({ code: 0, message: '更新成功' })
})

// 设置角色皮肤
router.put('/:pid/textures', authenticate, (req, res) => {
  const { tid_skin, tid_cape } = req.body
  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?').get(req.params.pid, req.user.uid)
  if (!player) return res.status(404).json({ code: 404, message: '角色不存在' })

  if (tid_skin) {
    const skin = database.prepare('SELECT tid FROM textures WHERE tid = ?').get(tid_skin)
    if (!skin) return res.status(404).json({ code: 404, message: '皮肤不存在' })
  }

  database.prepare(`
    UPDATE players SET tid_skin = ?, tid_cape = ?, last_modified = datetime('now') WHERE pid = ?
  `).run(tid_skin || null, tid_cape || null, req.params.pid)

  res.json({ code: 0, message: '皮肤已更新' })
})

// 清除角色皮肤
router.delete('/:pid/textures', authenticate, (req, res) => {
  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?').get(req.params.pid, req.user.uid)
  if (!player) return res.status(404).json({ code: 404, message: '角色不存在' })

  database.prepare('UPDATE players SET tid_skin = NULL, tid_cape = NULL, last_modified = datetime(\'now\') WHERE pid = ?')
    .run(req.params.pid)
  res.json({ code: 0, message: '皮肤已清除' })
})

// 删除角色
router.delete('/:pid', authenticate, (req, res) => {
  const database = db.getDb()
  const player = database.prepare('SELECT * FROM players WHERE pid = ? AND uid = ?').get(req.params.pid, req.user.uid)
  if (!player) return res.status(404).json({ code: 404, message: '角色不存在' })

  database.prepare('DELETE FROM players WHERE pid = ?').run(req.params.pid)
  res.json({ code: 0, message: '删除成功' })
})

module.exports = router