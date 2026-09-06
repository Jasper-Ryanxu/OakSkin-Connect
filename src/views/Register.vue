<template>
  <div class="auth-page">
    <div class="auth-container">
      <div class="auth-card">
        <div class="auth-header">
          <div class="auth-icon">
            <i class="fas fa-user-plus"></i>
          </div>
          <h1>创建账号</h1>
          <p>加入 OakSkin Connect 社区</p>
        </div>

        <form @submit.prevent="handleRegister" class="auth-form">
          <div v-if="error" class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ error }}
          </div>

          <div v-if="success" class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ success }}
          </div>

          <div class="form-group">
            <label for="nickname">
              <i class="fas fa-user"></i>
              昵称
            </label>
            <input
              id="nickname"
              v-model="nickname"
              type="text"
              class="input"
              placeholder="选择一个昵称"
              required
              minlength="3"
              maxlength="20"
            />
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
              @input="resetVerifyCode"
            />
          </div>

          <!-- 邮箱验证码 -->
          <div class="form-group">
            <label for="verifyCode">
              <i class="fas fa-shield-alt"></i>
              邮箱验证码
            </label>
            <div class="input-group-row">
              <input
                id="verifyCode"
                v-model="verifyCode"
                type="text"
                class="input"
                placeholder="输入验证码"
                maxlength="6"
                required
              />
              <button
                type="button"
                class="btn btn-secondary btn-code"
                :disabled="codeSending || codeCooldown > 0 || !email"
                @click="sendCode"
              >
                <i class="fas fa-spinner fa-spin" v-if="codeSending"></i>
                {{ codeCooldown > 0 ? `${codeCooldown}s` : '发送验证码' }}
              </button>
            </div>
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
              placeholder="设置密码（至少8位）"
              required
              minlength="8"
              autocomplete="new-password"
            />
          </div>

          <div class="form-group">
            <label for="password-confirm">
              <i class="fas fa-check-circle"></i>
              确认密码
            </label>
            <input
              id="password-confirm"
              v-model="passwordConfirm"
              type="password"
              class="input"
              placeholder="再次输入密码"
              required
              autocomplete="new-password"
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
            <i class="fas fa-user-plus" v-else></i>
            {{ submitting ? '注册中...' : '创建账号' }}
          </button>
        </form>

        <div class="auth-footer">
          <p>
            已有账号？
            <router-link to="/auth/login">立即登录</router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/auth'
import SliderCaptcha from '@/components/SliderCaptcha.vue'

const router = useRouter()
const authStore = useAuthStore()

const nickname = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const verifyCode = ref('')
const error = ref('')
const success = ref('')
const submitting = ref(false)
const codeSending = ref(false)
const codeCooldown = ref(0)

let codeTimer = null

function resetVerifyCode() {
  verifyCode.value = ''
}

async function sendCode() {
  if (!email.value) return
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    error.value = '邮箱格式不正确'
    return
  }
  error.value = ''
  codeSending.value = true
  try {
    await authApi.sendVerifyCode(email.value)
    // 开始倒计时
    codeCooldown.value = 60
    codeTimer = setInterval(() => {
      codeCooldown.value--
      if (codeCooldown.value <= 0) {
        clearInterval(codeTimer)
        codeTimer = null
      }
    }, 1000)
  } catch (e) {
    error.value = e.message || e.msg || '发送失败'
  } finally {
    codeSending.value = false
  }
}

onMounted(() => {
  // 不需要自动加载验证码
})

onUnmounted(() => {
  if (codeTimer) clearInterval(codeTimer)
})

const captchaToken = ref('')

function onCaptchaVerified(token) {
  captchaToken.value = token
}

async function handleRegister() {
  error.value = ''
  success.value = ''

  if (password.value !== passwordConfirm.value) {
    error.value = '两次输入的密码不一致'
    return
  }

  if (password.value.length < 8) {
    error.value = '密码长度至少为8位'
    return
  }

  if (!verifyCode.value) {
    error.value = '请填写邮箱验证码'
    return
  }

  if (!captchaToken.value) {
    error.value = '请完成滑块验证'
    return
  }

  submitting.value = true

  try {
    await authStore.register(nickname.value, email.value, password.value, verifyCode.value, captchaToken.value)
    success.value = '注册成功！正在跳转...'
    setTimeout(() => {
      router.push('/auth/login')
    }, 1500)
  } catch (e) {
    error.value = e.message || e.msg || '注册失败，请检查信息是否正确'
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

.input-group-row {
  display: flex;
  gap: 8px;
}

.input-group-row .input {
  flex: 1;
}

.btn-code {
  white-space: nowrap;
  padding: 12px 16px;
  font-size: 13px;
  min-width: 120px;
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
</style>