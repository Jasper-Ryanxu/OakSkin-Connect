<template>
  <div class="oauth-auth-page">
    <div class="oauth-auth-container">
      <!-- 授权中 / 信息加载 -->
      <div v-if="loading" class="oauth-auth-card center">
        <div class="spinner"></div>
        <p>正在加载应用信息...</p>
      </div>

      <!-- 授权成功提示 -->
      <div v-else-if="success" class="oauth-auth-card center">
        <div class="success-icon">
          <i class="fas fa-check"></i>
        </div>
        <h2>授权成功</h2>
        <p>
          你已授权 <strong>{{ app?.name }}</strong> 获取你的基础信息，<br />
          {{ countdown }} 秒后自动跳转回应用。
        </p>
      </div>

      <!-- 授权确认 -->
      <div v-else-if="app" class="oauth-auth-card">
        <div class="app-header">
          <div class="app-icon" v-if="app.icon">
            <img :src="app.icon" alt="" @error="onIconError" />
          </div>
          <div class="app-icon fallback" v-else>
            <i class="fas fa-plug"></i>
          </div>
          <div class="app-meta">
            <h2>{{ app.name }}</h2>
            <p>请求访问你的 OakSkin Connect 账号</p>
          </div>
        </div>

        <div v-if="error" class="alert alert-error">{{ error }}</div>

        <div class="scope-box">
          <h3><i class="fas fa-shield-alt"></i> 该应用将获得以下权限</h3>
          <ul>
            <li v-if="scopeList.includes('email')"><i class="fas fa-envelope"></i> 读取你的邮箱地址</li>
            <li v-if="scopeList.includes('nickname')"><i class="fas fa-user"></i> 读取你的昵称</li>
            <li v-if="scopeList.includes('avatar')"><i class="fas fa-id-card"></i> 读取你的头像</li>
            <li v-if="scopeList.includes('score')"><i class="fas fa-coins"></i> 读取你的积分</li>
            <li v-if="scopeList.length === 0"><i class="fas fa-info-circle"></i> 基础身份信息</li>
          </ul>
        </div>

        <button class="btn btn-primary btn-lg btn-block" :disabled="authorizing" @click="confirmAuthorize">
          <i class="fas fa-spinner fa-spin" v-if="authorizing"></i>
          <i class="fas fa-check-circle" v-else></i>
          {{ authorizing ? '授权中...' : '授权并登录' }}
        </button>
        <p class="cancel-hint">
          仅在你信任该应用时才点击授权，<a :href="cancelUrl" @click.prevent="decline">取消</a>将退回应用。
        </p>
      </div>

      <!-- 应用不存在 -->
      <div v-else class="oauth-auth-card center">
        <i class="fas fa-exclamation-triangle" style="font-size:40px;color:var(--warning);"></i>
        <h2>授权请求无效</h2>
        <p>应用不存在或参数有误。</p>
        <a href="/" class="btn btn-secondary btn-sm">返回首页</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { oauthApi } from '@/api/oauth'

const route = useRoute()
const authStore = useAuthStore()

const loading = ref(true)
const authorizing = ref(false)
const success = ref(false)
const error = ref('')
const app = ref(null)
const countdown = ref(3)
let timer = null

const scopeList = computed(() => {
  if (!app.value?.scopes) return []
  return String(app.value.scopes).split(/[\s,]+/).filter(Boolean)
})

const redirectUri = computed(() => route.query.redirect_uri || '')
const stateVal = computed(() => route.query.state || '')
const cancelUrl = computed(() => {
  const sep = redirectUri.value.includes('?') ? '&' : '?'
  return redirectUri.value + sep + 'error=access_denied&state=' + encodeURIComponent(stateVal.value)
})

function onIconError(e) {
  e.target.closest('.app-icon').classList.add('fallback')
}

onMounted(async () => {
  const clientId = route.query.client_id
  if (!clientId) {
    loading.value = false
    app.value = null
    return
  }
  // 未登录：跳登录并在回来后回到授权页
  if (!authStore.isLoggedIn) {
    window.location.href = '/auth/login?redirect=' + encodeURIComponent(route.fullPath)
    return
  }
  try {
    const data = await oauthApi.getClientInfo(clientId)
    app.value = data.data || data
  } catch (e) {
    app.value = null
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

async function confirmAuthorize() {
  authorizing.value = true
  error.value = ''
  try {
    const data = await oauthApi.authorize({
      client_id: route.query.client_id,
      redirect_uri: redirectUri.value,
      state: stateVal.value
    })
    // 显示授权成功页，3 秒后跳转回应用
    success.value = true
    startCountdown(() => {
      window.location.href = data.redirect_url
    })
  } catch (e) {
    error.value = e.message || e.msg || '授权失败，请重试'
    authorizing.value = false
  }
}

function decline() {
  window.location.href = cancelUrl.value
}

function startCountdown(callback) {
  countdown.value = 3
  timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(timer)
      callback()
    }
  }, 1000)
}
</script>

<style scoped>
.oauth-auth-page {
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: radial-gradient(ellipse at 50% 0%, rgba(108, 92, 231, 0.1) 0%, transparent 60%);
}

.oauth-auth-container {
  width: 100%;
  max-width: 440px;
}

.oauth-auth-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-lg);
  padding: 36px;
  box-shadow: var(--shadow-lg);
}

.oauth-auth-card.center {
  text-align: center;
}

.app-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.app-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  overflow: hidden;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.app-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.app-icon.fallback i {
  color: #fff;
  font-size: 24px;
}

.app-meta h2 {
  font-size: 20px;
  font-weight: 800;
  margin-bottom: 2px;
}

.app-meta p {
  font-size: 13px;
  color: var(--text-muted);
}

.scope-box {
  background: var(--bg-tertiary);
  border-radius: var(--border-radius);
  padding: 18px 20px;
  margin-bottom: 24px;
}

.scope-box h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.scope-box h3 i {
  color: var(--accent-primary);
}

.scope-box ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.scope-box li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-secondary);
  padding: 4px 0;
}

.scope-box li i {
  color: var(--success);
  font-size: 12px;
  width: 16px;
}

.cancel-hint {
  margin-top: 14px;
  font-size: 12px;
  color: var(--text-muted);
  text-align: center;
}

.cancel-hint a {
  color: var(--text-secondary);
  text-decoration: underline;
}

.success-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 16px;
  border-radius: 50%;
  background: linear-gradient(135deg, #00b894, #55efc4);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  color: #fff;
  box-shadow: 0 0 30px rgba(0, 184, 148, 0.4);
}

.oauth-auth-card.center h2 {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 8px;
}

.oauth-auth-card.center p {
  font-size: 13px;
  color: var(--text-muted);
  line-height: 1.7;
}
</style>