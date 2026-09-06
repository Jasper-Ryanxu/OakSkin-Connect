const crypto = require('crypto')
const db = require('../db')

// 内存令牌存储：accessToken -> record
// record: { owner(email), clientToken, profileId, createdAt }
const tokens = new Map()

function generateAccessToken() {
  return crypto.randomBytes(16).toString('hex')
}

// 签发令牌，返回 accessToken
function issue({ owner, clientToken, profileId }) {
  const accessToken = generateAccessToken()
  tokens.set(accessToken, {
    owner,
    clientToken: clientToken || '',
    profileId: profileId || '',
    createdAt: Date.now() / 1000
  })
  return accessToken
}

// 查找令牌（未过期），过期则删除并返回 null
function find(accessToken) {
  const token = tokens.get(accessToken)
  if (!token) return null
  return token
}

// 校验令牌是否有效（存在）
function isValid(accessToken) {
  return tokens.has(accessToken)
}

// 吊销单个令牌
function revoke(accessToken) {
  tokens.delete(accessToken)
}

// 吊销某个用户（owner）的所有令牌
function revokeAll(owner) {
  for (const [key, token] of tokens) {
    if (token.owner === owner) {
      tokens.delete(key)
    }
  }
}

// 获取某个用户的所有令牌
function allOf(owner) {
  const result = []
  for (const [key, token] of tokens) {
    if (token.owner === owner) {
      result.push({ accessToken: key, ...token })
    }
  }
  return result
}

module.exports = { issue, find, isValid, revoke, revokeAll, allOf, generateAccessToken }