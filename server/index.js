const express = require('express')
const cors = require('cors')
const path = require('path')
const fs = require('fs')
const db = require('./db')

const app = express()
const PORT = process.env.PORT || 8080

// 中间件
app.use(cors())
app.use(express.json())
app.use(express.urlencoded({ extended: true }))

// 检查安装锁
const lockPath = path.join(__dirname, '..', 'install.lock')
const configPath = path.join(__dirname, '..', 'config.json')
let config = null
let configExists = false
let isInstalled = false

if (fs.existsSync(lockPath)) {
  isInstalled = true
}

try {
  if (fs.existsSync(configPath)) {
    config = JSON.parse(fs.readFileSync(configPath, 'utf-8'))
    configExists = true
  }
} catch (e) {
  console.error('[OakSkin] 配置文件读取失败:', e.message)
}

// 静态文件 - 皮肤纹理
const texturesDir = path.join(__dirname, '..', 'storage', 'textures')
if (!fs.existsSync(texturesDir)) {
  fs.mkdirSync(texturesDir, { recursive: true })
}
app.use('/textures', express.static(texturesDir))

// 公共设置接口（无需登录）— 动态读取 config.json
app.get('/api/settings/public', (req, res) => {
  try {
    const cfg = JSON.parse(fs.readFileSync(configPath, 'utf-8'))
    res.json({
      code: 0,
      data: {
        max_upload_size: cfg?.site?.max_upload_size || 10240
      }
    })
  } catch {
    res.json({
      code: 0,
      data: {
        max_upload_size: 10240
      }
    })
  }
})

// 将 loadBusinessRoutes 存入 app 供 setup 路由使用
app.set('loadBusinessRoutes', loadBusinessRoutes)

// Setup 路由（无需配置即可访问）
app.use('/api/setup', require('./routes/setup'))

// 已安装时加载业务路由
const distDir = path.join(__dirname, '..', 'dist')

async function loadBusinessRoutes(config) {
  if (!config) return
  try {
    await db.initialize(config)
    db.createAdmin(config.admin)
    // 动态加载业务路由
    app.use('/api/auth', require('./routes/auth'))
    app.use('/api/skinlib', require('./routes/skins'))
    app.use('/api/texture', require('./routes/skins'))
    app.use('/api/user/player', require('./routes/players'))
    app.use('/api/user/closet', require('./routes/closet'))
    app.use('/api/user', require('./routes/user'))
    app.use('/api/admin', require('./routes/admin'))
    // Yggdrasil 外置登录 API
    app.use('/api/yggdrasil', require('./routes/yggdrasil'))
    console.log(`[OakSkin] 数据库: ${config.database.driver}`)
    console.log(`[OakSkin] 管理员账号: ${config.admin.email}`)
  } catch (e) {
    console.error('[OakSkin] 加载业务路由失败:', e.message)
  }
}

if (isInstalled && configExists && config) {
  loadBusinessRoutes(config)
}

// 静态文件 - 前端构建产物
app.use(express.static(distDir))

// SPA 回退 - 仅对非 API 路由返回 index.html，附带 404 状态
app.get(/^\/(?!api\/).*/, (req, res) => {
  res.status(404).sendFile(path.join(distDir, 'index.html'))
})

// 错误处理
app.use((err, req, res, next) => {
  console.error('Server Error:', err)
  res.status(500).json({ code: 500, message: '服务器内部错误' })
})

// 启动服务器
app.listen(PORT, () => {
  console.log(`[OakSkin Connect] 服务器运行在 http://localhost:${PORT}`)
  if (!isInstalled || !configExists) {
    console.log('[OakSkin] 未检测到 install.lock，请访问 /setup 完成安装')
  }
})

// 导出 loadBusinessRoutes 供 setup.js 安装后调用
module.exports = { loadBusinessRoutes }