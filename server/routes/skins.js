const express = require('express')
const router = express.Router()
const path = require('path')
const fs = require('fs')
const multer = require('multer')
const crypto = require('crypto')
const db = require('../db')
const { authenticate, optionalAuth } = require('../middleware/auth')

// 读取配置
const configPath = path.join(__dirname, '..', '..', 'config.json')
const config = JSON.parse(fs.readFileSync(configPath, 'utf-8'))

// 配置文件上传
const texturesDir = path.join(__dirname, '..', '..', 'storage', 'textures')
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, texturesDir),
  filename: (req, file, cb) => {
    const hash = crypto.randomBytes(16).toString('hex')
    cb(null, hash + '.png')
  }
})

// 读取当前最大上传大小（KB）- 动态读取
function getMaxUploadSizeKB() {
  try {
    const cfg = JSON.parse(fs.readFileSync(configPath, 'utf-8'))
    return cfg.site?.max_upload_size || 10240
  } catch {
    return 10240
  }
}

const upload = multer({
  storage,
  limits: { fileSize: 500 * 1024 * 1024 }, // multer 上限放松，由自定义中间件控制
  fileFilter: (req, file, cb) => {
    if (file.mimetype === 'image/png') {
      cb(null, true)
    } else {
      cb(new Error('仅支持 PNG 格式'))
    }
  }
})

// 获取皮肤列表
router.get('/list', optionalAuth, (req, res) => {
  const database = db.getDb()
  const page = parseInt(req.query.page) || 1
  const perPage = parseInt(req.query.per_page) || 20
  const offset = (page - 1) * perPage
  const sort = req.query.sort || 'upload_at'
  const order = (req.query.order || 'desc').toUpperCase()
  const type = req.query.type || ''
  const keyword = req.query.keyword || ''
  const uploader = req.query.uploader || ''

  let where = 'WHERE t.public = 1'
  let params = []

  if (type) {
    where += ' AND t.type = ?'
    params.push(type)
  }

  if (keyword) {
    where += ' AND t.name LIKE ?'
    params.push(`%${keyword}%`)
  }

  if (uploader) {
    where += ' AND t.uploader = ?'
    params.push(parseInt(uploader))
  }

  const allowedSorts = ['upload_at', 'likes', 'name']
  const sortField = allowedSorts.includes(sort) ? sort : 'upload_at'

  const countResult = database.prepare(`
    SELECT COUNT(*) as total FROM textures t ${where}
  `).get(...params)

  const items = database.prepare(`
    SELECT t.*, u.nickname as owner_nickname
    FROM textures t
    LEFT JOIN users u ON t.uploader = u.uid
    ${where}
    ORDER BY t.${sortField} ${order}
    LIMIT ? OFFSET ?
  `).all(...params, perPage, offset)

  // 格式化数据
  const data = items.map(item => ({
    tid: item.tid,
    name: item.name,
    type: item.type,
    hash: item.hash,
    size: item.size,
    uploader: item.uploader,
    public: !!item.public,
    likes: item.likes,
    downloads: item.downloads || 0,
    upload_at: item.upload_at,
    model: item.type === 'alex' ? 'slim' : 'default',
    owner: { nickname: item.owner_nickname }
  }))

  res.json({
    code: 0,
    data: data,
    total: countResult.total,
    total_count: countResult.total,
    current_page: page,
    per_page: perPage
  })
})

// 获取皮肤详情
router.get('/show/:tid', optionalAuth, (req, res) => {
  const database = db.getDb()
  const item = database.prepare(`
    SELECT t.*, u.nickname as owner_nickname
    FROM textures t
    LEFT JOIN users u ON t.uploader = u.uid
    WHERE t.tid = ?
  `).get(req.params.tid)

  if (!item) {
    return res.status(404).json({ code: 404, message: '皮肤不存在' })
  }

  res.json({
    tid: item.tid,
    name: item.name,
    type: item.type,
    hash: item.hash,
    size: item.size,
    uploader: item.uploader,
    public: !!item.public,
    likes: item.likes,
    upload_at: item.upload_at,
    model: item.type === 'alex' ? 'slim' : 'default',
    downloads: item.downloads || 0,
    owner: { nickname: item.owner_nickname }
  })
})

// 上传皮肤
router.post('/', authenticate, (req, res, next) => {
  // 动态检查文件大小
  const maxSizeKB = getMaxUploadSizeKB()
  const maxSizeBytes = maxSizeKB * 1024
  const contentLength = parseInt(req.headers['content-length']) || 0
  if (contentLength > maxSizeBytes * 2) { // 粗略预检（multipart 有额外开销）
    return res.status(422).json({ code: 422, message: `文件大小不能超过 ${Math.round(maxSizeKB / 1024)}MB` })
  }
  next()
}, upload.single('file'), (req, res) => {
  if (!req.file) {
    return res.status(422).json({ code: 422, message: '请选择文件' })
  }

  // 动态检查实际文件大小
  const maxSizeKB = getMaxUploadSizeKB()
  const maxSizeBytes = maxSizeKB * 1024
  if (req.file.size > maxSizeBytes) {
    // 删除已上传的文件
    fs.unlink(req.file.path, () => {})
    return res.status(422).json({ code: 422, message: `文件大小不能超过 ${Math.round(maxSizeKB / 1024)}MB` })
  }

  const { name, type, public: isPublic } = req.body
  const database = db.getDb()

  // 去掉扩展名作为文件名
  const skinName = name || req.file.originalname.replace(/\.png$/i, '')
  const hash = path.basename(req.file.filename, '.png')

  const result = database.prepare(`
    INSERT INTO textures (name, type, hash, size, uploader, public)
    VALUES (?, ?, ?, ?, ?, ?)
  `).run(
    skinName,
    type || 'steve',
    hash,
    req.file.size,
    req.user.uid,
    isPublic === '0' ? 0 : 1
  )

  res.json({
    code: 0,
    message: '上传成功',
    tid: result.lastInsertRowid,
    data: { tid: result.lastInsertRowid }
  })
})

// 更新皮肤类型
router.put('/:tid/type', authenticate, (req, res) => {
  const database = db.getDb()
  const item = database.prepare('SELECT * FROM textures WHERE tid = ?').get(req.params.tid)
  if (!item) return res.status(404).json({ code: 404, message: '皮肤不存在' })
  if (item.uploader !== req.user.uid && req.user.permission < 1) {
    return res.status(403).json({ code: 403, message: '权限不足' })
  }

  database.prepare('UPDATE textures SET type = ? WHERE tid = ?').run(req.body.type, req.params.tid)
  res.json({ code: 0, message: '更新成功' })
})

// 更新皮肤名称
router.put('/:tid/name', authenticate, (req, res) => {
  const database = db.getDb()
  const item = database.prepare('SELECT * FROM textures WHERE tid = ?').get(req.params.tid)
  if (!item) return res.status(404).json({ code: 404, message: '皮肤不存在' })
  if (item.uploader !== req.user.uid && req.user.permission < 1) {
    return res.status(403).json({ code: 403, message: '权限不足' })
  }

  database.prepare('UPDATE textures SET name = ? WHERE tid = ?').run(req.body.name, req.params.tid)
  res.json({ code: 0, message: '更新成功' })
})

// 更新皮肤隐私
router.put('/:tid/privacy', authenticate, (req, res) => {
  const database = db.getDb()
  const item = database.prepare('SELECT * FROM textures WHERE tid = ?').get(req.params.tid)
  if (!item) return res.status(404).json({ code: 404, message: '皮肤不存在' })
  if (item.uploader !== req.user.uid && req.user.permission < 1) {
    return res.status(403).json({ code: 403, message: '权限不足' })
  }

  database.prepare('UPDATE textures SET public = ? WHERE tid = ?').run(req.body.public ? 1 : 0, req.params.tid)
  res.json({ code: 0, message: '更新成功' })
})

// 删除皮肤
router.delete('/:tid', authenticate, (req, res) => {
  const database = db.getDb()
  const item = database.prepare('SELECT * FROM textures WHERE tid = ?').get(req.params.tid)
  if (!item) return res.status(404).json({ code: 404, message: '皮肤不存在' })
  if (item.uploader !== req.user.uid && req.user.permission < 1) {
    return res.status(403).json({ code: 403, message: '权限不足' })
  }

  // 删除文件
  const filePath = path.join(texturesDir, item.hash + '.png')
  if (fs.existsSync(filePath)) {
    fs.unlinkSync(filePath)
  }

  database.prepare('DELETE FROM textures WHERE tid = ?').run(req.params.tid)
  res.json({ code: 0, message: '删除成功' })
})

// 下载皮肤（递增下载计数）
router.get('/download/:tid', (req, res) => {
  const database = db.getDb()
  const item = database.prepare('SELECT * FROM textures WHERE tid = ?').get(req.params.tid)
  if (!item) {
    return res.status(404).json({ code: 404, message: '皮肤不存在' })
  }

  // 递增下载计数
  database.prepare('UPDATE textures SET downloads = downloads + 1 WHERE tid = ?').run(req.params.tid)

  const filePath = path.join(texturesDir, item.hash + '.png')
  if (!fs.existsSync(filePath)) {
    return res.status(404).json({ code: 404, message: '文件不存在' })
  }

  res.download(filePath, `${item.name || 'skin'}.png`)
})

module.exports = router