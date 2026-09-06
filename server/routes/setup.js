const express = require('express')
const router = express.Router()
const path = require('path')
const fs = require('fs')

const lockPath = path.join(__dirname, '..', '..', 'install.lock')

// 检查是否已安装（install.lock）
router.get('/status', (req, res) => {
  const locked = fs.existsSync(lockPath)
  res.json({ installed: locked })
})

// 执行安装
router.post('/install', async (req, res) => {
  if (fs.existsSync(lockPath)) {
    return res.status(400).json({ code: 400, message: '系统已安装，请删除 install.lock 后重试' })
  }

  const {
    site_name, site_url,
    db_driver, db_path,
    admin_email, admin_password, admin_nickname
  } = req.body

  if (!site_name || !admin_email || !admin_password) {
    return res.status(422).json({ code: 422, message: '请填写必要信息' })
  }

  if (admin_password.length < 6) {
    return res.status(422).json({ code: 422, message: '密码长度不能少于6位' })
  }

  const config = {
    site: {
      name: site_name,
      url: site_url || 'http://localhost:8080',
      max_upload_size: 10240
    },
    database: {
      driver: db_driver || 'sqlite',
      sqlite: {
        path: db_path || 'storage/oakskin.db'
      }
    },
    auth: {
      token_expire: 604800,
      token_lifetime: 604800,
      allow_register: true,
      password_min_length: 6,
      verify_email: false
    },
    admin: {
      email: admin_email,
      password: admin_password,
      nickname: admin_nickname || '站长'
    }
  }

  try {
    // 写入 config.json
    const configPath = path.join(__dirname, '..', '..', 'config.json')
    const dir = path.dirname(configPath)
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true })
    fs.writeFileSync(configPath, JSON.stringify(config, null, 2), 'utf-8')

    // 动态加载业务路由和初始化数据库，无需重启服务器
    const loadRoutes = req.app.get('loadBusinessRoutes')
    if (loadRoutes) {
      await loadRoutes(config)
    }

    // 数据库初始化成功后再创建 install.lock
    fs.writeFileSync(lockPath, new Date().toISOString(), 'utf-8')

    res.json({ code: 0, message: '安装成功' })
  } catch (e) {
    // 安装失败时清理 config.json，允许重试
    try {
      if (fs.existsSync(configPath)) fs.unlinkSync(configPath)
    } catch (_) {}
    res.status(500).json({ code: 500, message: '安装失败: ' + e.message })
  }
})

module.exports = router