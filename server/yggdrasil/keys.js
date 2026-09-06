const crypto = require('crypto')
const fs = require('fs')
const path = require('path')

const KEYS_DIR = path.join(__dirname, '..', '..', 'storage', 'yggdrasil')
const PRIVATE_KEY_PATH = path.join(KEYS_DIR, 'private_key.pem')
const PUBLIC_KEY_PATH = path.join(KEYS_DIR, 'public_key.pem')

let privateKey = null
let publicKey = null

// 确保密钥存在，不存在则自动生成 4096 位 RSA 密钥
function ensureKeys() {
  if (privateKey && publicKey) return

  if (!fs.existsSync(KEYS_DIR)) {
    fs.mkdirSync(KEYS_DIR, { recursive: true })
  }

  if (fs.existsSync(PRIVATE_KEY_PATH) && fs.existsSync(PUBLIC_KEY_PATH)) {
    privateKey = fs.readFileSync(PRIVATE_KEY_PATH, 'utf-8')
    publicKey = fs.readFileSync(PUBLIC_KEY_PATH, 'utf-8')
    return
  }

  // 生成 4096 位 RSA 密钥对
  const { publicKey: pub, privateKey: priv } = crypto.generateKeyPairSync('rsa', {
    modulusLength: 4096,
    publicKeyEncoding: { type: 'spki', format: 'pem' },
    privateKeyEncoding: { type: 'pkcs8', format: 'pem' }
  })

  fs.writeFileSync(PRIVATE_KEY_PATH, priv)
  fs.writeFileSync(PUBLIC_KEY_PATH, pub)

  privateKey = priv
  publicKey = pub
  console.log('[Yggdrasil] 已生成新的 RSA 4096 密钥对')
}

function getPrivateKey() {
  ensureKeys()
  return privateKey
}

function getPublicKey() {
  ensureKeys()
  return publicKey
}

// 用私钥对数据签名，返回 base64（RSA-SHA1，与 Mojang/BS 一致）
const sign = (data) => {
  ensureKeys()
  const signer = crypto.createSign('RSA-SHA1')
  signer.update(data, 'utf-8')
  signer.end()
  return signer.sign(privateKey, 'base64')
}

module.exports = { ensureKeys, getPrivateKey, getPublicKey, sign }