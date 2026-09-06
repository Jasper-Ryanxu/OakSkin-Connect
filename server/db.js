const initSqlJs = require('sql.js')
const path = require('path')
const fs = require('fs')
const crypto = require('crypto')

let db = null
let dbPath = ''

// ===== sql.js 兼容 better-sqlite3 API 的包装 =====
class Statement {
  constructor(sql) {
    this.sql = sql
  }

  get(...params) {
    const stmt = db.prepare(this.sql)
    if (params.length > 0) stmt.bind(params)
    let result = undefined
    if (stmt.step()) result = stmt.getAsObject()
    stmt.free()
    return result
  }

  all(...params) {
    const stmt = db.prepare(this.sql)
    if (params.length > 0) stmt.bind(params)
    const results = []
    while (stmt.step()) results.push(stmt.getAsObject())
    stmt.free()
    return results
  }

  run(...params) {
    if (params.length > 0) {
      db.run(this.sql, params)
    } else {
      db.run(this.sql)
    }
    const r = db.exec("SELECT last_insert_rowid() AS id, changes() AS chg")
    saveDb()
    return {
      lastInsertRowid: r[0]?.values[0]?.[0] || 0,
      changes: r[0]?.values[0]?.[1] || 0
    }
  }
}

function prepare(sql) {
  return new Statement(sql)
}

function saveDb() {
  if (!db) return
  const data = db.export()
  fs.writeFileSync(dbPath, Buffer.from(data))
}

function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString('hex')
  const hash = crypto.pbkdf2Sync(password, salt, 10000, 64, 'sha512').toString('hex')
  return `${salt}:${hash}`
}

function verifyPassword(password, stored) {
  const [salt, hash] = stored.split(':')
  const verify = crypto.pbkdf2Sync(password, salt, 10000, 64, 'sha512').toString('hex')
  return hash === verify
}

function generateToken() {
  return crypto.randomBytes(32).toString('hex')
}

async function initialize(config) {
  const dbConfig = config.database
  SQL = await initSqlJs()

  if (dbConfig.driver === 'sqlite') {
    dbPath = path.join(__dirname, '..', dbConfig.sqlite.path)
    const dir = path.dirname(dbPath)
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true })

    if (fs.existsSync(dbPath)) {
      const buffer = fs.readFileSync(dbPath)
      db = new SQL.Database(buffer)
    } else {
      db = new SQL.Database()
    }
  }

  db.run("PRAGMA foreign_keys = ON")
  createTables()
  return db
}

function createTables() {
  db.run(`
    CREATE TABLE IF NOT EXISTS users (
      uid INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      password TEXT NOT NULL,
      nickname TEXT NOT NULL,
      avatar INTEGER DEFAULT 0,
      score INTEGER DEFAULT 0,
      permission INTEGER DEFAULT 0,
      ip TEXT DEFAULT '',
      verified INTEGER DEFAULT 1,
      is_dark_mode INTEGER DEFAULT 1,
      last_sign_at TEXT DEFAULT '',
      register_at TEXT DEFAULT (datetime('now'))
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS textures (
      tid INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      type TEXT NOT NULL DEFAULT 'steve',
      hash TEXT NOT NULL UNIQUE,
      size INTEGER DEFAULT 0,
      uploader INTEGER NOT NULL,
      public INTEGER DEFAULT 1,
      likes INTEGER DEFAULT 0,
      upload_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (uploader) REFERENCES users(uid) ON DELETE CASCADE
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS players (
      pid INTEGER PRIMARY KEY AUTOINCREMENT,
      uid INTEGER NOT NULL,
      name TEXT NOT NULL,
      tid_skin INTEGER DEFAULT NULL,
      tid_cape INTEGER DEFAULT NULL,
      last_modified TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
      FOREIGN KEY (tid_skin) REFERENCES textures(tid) ON DELETE SET NULL,
      FOREIGN KEY (tid_cape) REFERENCES textures(tid) ON DELETE SET NULL
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS user_closet (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uid INTEGER NOT NULL,
      tid INTEGER NOT NULL,
      item_name TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE,
      FOREIGN KEY (tid) REFERENCES textures(tid) ON DELETE CASCADE,
      UNIQUE(uid, tid)
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS reports (
      rid INTEGER PRIMARY KEY AUTOINCREMENT,
      tid INTEGER NOT NULL,
      reporter_uid INTEGER NOT NULL,
      reason TEXT NOT NULL,
      status INTEGER DEFAULT 0,
      created_at TEXT DEFAULT (datetime('now')),
      FOREIGN KEY (tid) REFERENCES textures(tid) ON DELETE CASCADE,
      FOREIGN KEY (reporter_uid) REFERENCES users(uid) ON DELETE CASCADE
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS uuid (
      name TEXT PRIMARY KEY,
      uuid TEXT NOT NULL
    )
  `)
  db.run(`
    CREATE TABLE IF NOT EXISTS tokens (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uid INTEGER NOT NULL,
      token TEXT NOT NULL UNIQUE,
      created_at TEXT DEFAULT (datetime('now')),
      expires_at TEXT NOT NULL,
      FOREIGN KEY (uid) REFERENCES users(uid) ON DELETE CASCADE
    )
  `)
  saveDb()
}

function createAdmin(adminConfig) {
  const existing = prepare('SELECT uid FROM users WHERE permission = 2').get()
  if (existing) return

  const hashed = hashPassword(adminConfig.password)
  prepare(`
    INSERT INTO users (email, password, nickname, permission, verified)
    VALUES (?, ?, ?, 2, 1)
  `).run(adminConfig.email, hashed, adminConfig.nickname)

  console.log(`[OakSkin] 管理员账号已创建: ${adminConfig.email}`)
}

function getDb() {
  return { prepare }
}

module.exports = {
  initialize,
  getDb,
  hashPassword,
  verifyPassword,
  generateToken,
  createAdmin
}