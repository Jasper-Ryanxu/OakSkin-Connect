<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <div class="auth-header">
          <div class="auth-icon">
            <i class="fas fa-cube"></i>
          </div>
          <h1>欢迎回来</h1>
          <p>登录到 OakSkin Connect</p>
        </div>

        <form @submit.prevent="handleLogin" class="auth-form">
          <div v-if="error" class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ error }}
          </div>

          <div class="form-group">
            <label for="email">
              <i class="fas fa-envelope"></i>
              邮箱地址
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              class="input"
              placeholder="请输入你的邮箱地址"
              required
              autocomplete="email"
            />
          </div>

          <div class="form-group">
            <label for="password">
              <i class="fas fa-lock"></i>
              密码
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              class="input"
              placeholder="请输入你的密码"
              required
              autocomplete="current-password"
            />
          </div>

          <!-- 滑块验证码 -->
          <div class="form-group">
            <label>
              <i class="fas fa-shield-alt"></i>
              安全验证
            </label>
            <SliderCaptcha @verified="onCaptchaVerified" />
          </div>

          <button
            type="submit"
            class="btn btn-primary btn-lg btn-block"
            :disabled="submitting"
          >
            <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
            <i class="fas fa-sign-in-alt" v-else></i>
            {{ submitting ? '登录中...' : '登录' }}
          </button>
        </form>

        <div class="oauth-divider">
          <span>或使用第三方账号</span>
        </div>

        <button class="btn btn-oauth btn-block" @click="handleOakSkinLogin" :disabled="oakLoading">
          <i class="fas fa-cube" v-if="!oakLoading"></i>
          <i class="fas fa-spinner fa-spin" v-else></i>
          {{ oakLoading ? '正在跳转...' : '使用 OakSkin 登录' }}
        </button>

        <div class="auth-footer">
          <p>
            还没有账号？
            <router-link to="/auth/register">立即注册</router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SliderCaptcha from '@/components/SliderCaptcha.vue'
import { oauthApi } from '@/api/oauth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)
const oakLoading = ref(false)
const captchaToken = ref('')

function onCaptchaVerified(token) {
  captchaToken.value = token
}

onMounted(async () => {
  // 处理 OakSkin 登录回调：如果 URL 携带 oakskin_token，直接写入并跳转
  const oakToken = route.query.oakskin_token
  if (oakToken) {
    authStore.setToken(oakToken)
    await authStore.fetchUser()
    router.replace(route.query.redirect || '/dashboard')
    return
  }
  const err = route.query.error
  if (err) {
    const detail = route.query.detail
    const query = route.query.query
    const extra = detail || query
    error.value = `第三方登录失败（${err}${extra ? ' · ' + extra : ''}），请重试`
  }
})

async function handleOakSkinLogin() {
  oakLoading.value = true
  error.value = ''
  try {
    const data = await oauthApi.getOakSkinLoginUrl()
    window.location.href = data.url
  } catch (e) {
    error.value = '无法获取登录地址，请稍后重试'
    oakLoading.value = false
  }
}

async function handleLogin() {
  error.value = ''

  if (!captchaToken.value) {
    error.value = '请完成滑块验证'
    return
  }

  submitting.value = true

  try {
    await authStore.login(email.value, password.value, captchaToken.value)
    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (e) {
    error.value = e.message || e.msg || '登录失败，请检查邮箱和密码'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.auth-page {
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background:
    radial-gradient(ellipse at 50% 0%, rgba(108, 92, 231, 0.1) 0%, transparent 60%);
}

.auth-container {
  width: 100%;
  max-width: 420px;
}

.auth-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-lg);
  padding: 40px;
  box-shadow: var(--shadow-lg);
}

.auth-header {
  text-align: center;
  margin-bottom: 32px;
}

.auth-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 16px;
  background: var(--accent-gradient);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: #fff;
  box-shadow: 0 0 30px rgba(108, 92, 231, 0.3);
}

.auth-header h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 4px;
}

.auth-header p {
  color: var(--text-secondary);
  font-size: 14px;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-group label i {
  font-size: 12px;
  color: var(--accent-primary);
}

.auth-footer {
  text-align: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border-color);
}

.auth-footer p {
  font-size: 14px;
  color: var(--text-secondary);
}

.auth-footer a {
  color: var(--accent-secondary);
  font-weight: 600;
}

.oauth-divider {
  display: flex;
  align-items: center;
  margin: 22px 0 0;
  gap: 12px;
  color: var(--text-muted);
  font-size: 12px;
}

.oauth-divider::before,
.oauth-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border-color);
}

.btn-oauth {
  margin-top: 16px;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  color: #fff;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(108, 92, 231, 0.25);
}
</style>