const express = require('express')
const router = express.Router()
const db = require('../db')
const { authenticate } = require('../middleware/auth')

// 获取衣柜列表
router.get('/list', authenticate, (req, res) => {
  const database = db.getDb()
  const items = database.prepare(`
    SELECT t.*, uc.item_name, uc.created_at
    FROM user_closet uc
    JOIN textures t ON uc.tid = t.tid
    WHERE uc.uid = ?
    ORDER BY uc.created_at DESC
  `).all(req.user.uid)

  const data = items.map(item => ({
    tid: item.tid,
    name: item.name,
    type: item.type,
    hash: item.hash,
    size: item.size,
    uploader: item.uploader,
    public: !!item.public,
    likes: item.likes,
    upload_at: item.upload_at,
    pivot: {
      item_name: item.item_name,
      created_at: item.created_at
    }
  }))

  res.json({ data })
})

// 获取所有衣柜 ID
router.get('/ids', authenticate, (req, res) => {
  const database = db.getDb()
  const items = database.prepare('SELECT tid FROM user_closet WHERE uid = ?').all(req.user.uid)
  res.json(items.map(i => i.tid))
})

// 添加到衣柜
router.post('/', authenticate, (req, res) => {
  const { tid, item_name } = req.body
  const database = db.getDb()

  const skin = database.prepare('SELECT tid FROM textures WHERE tid = ?').get(tid)
  if (!skin) return res.status(404).json({ code: 404, message: '皮肤不存在' })

  const existing = database.prepare('SELECT id FROM user_closet WHERE uid = ? AND tid = ?').get(req.user.uid, tid)
  if (existing) {
    return res.status(422).json({ code: 422, message: '该皮肤已在衣柜中' })
  }

  database.prepare('INSERT INTO user_closet (uid, tid, item_name) VALUES (?, ?, ?)')
    .run(req.user.uid, tid, item_name || '')

  // 增加喜欢数
  database.prepare('UPDATE textures SET likes = likes + 1 WHERE tid = ?').run(tid)

  res.json({ code: 0, message: '已添加到衣柜' })
})

// 重命名衣柜项目
router.put('/:tid', authenticate, (req, res) => {
  const database = db.getDb()
  database.prepare('UPDATE user_closet SET item_name = ? WHERE uid = ? AND tid = ?')
    .run(req.body.item_name, req.user.uid, req.params.tid)
  res.json({ code: 0, message: '更新成功' })
})

// 从衣柜移除
router.delete('/:tid', authenticate, (req, res) => {
  const database = db.getDb()
  database.prepare('DELETE FROM user_closet WHERE uid = ? AND tid = ?')
    .run(req.user.uid, req.params.tid)
  // 减少喜欢数
  database.prepare('UPDATE textures SET likes = MAX(0, likes - 1) WHERE tid = ?').run(req.params.tid)
  res.json({ code: 0, message: '已移除' })
})

module.exports = router