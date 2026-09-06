<template>
  <div class="setup-page">
    <div class="setup-container">
      <div class="setup-header">
        <div class="setup-logo">
          <i class="fas fa-cube"></i>
        </div>
        <h1>OakSkin Connect 安装向导</h1>
        <p>配置你的皮肤站基本设置</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="setup-card">
        <div class="loading-spinner">
          <div class="spinner"></div>
          <p>检查安装状态...</p>
        </div>
      </div>

      <!-- Already Installed -->
      <div v-else-if="installed" class="setup-card">
        <div class="setup-success">
          <div class="success-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <h2>系统已安装</h2>
          <p>OakSkin Connect 已经配置完成，你可以直接使用。</p>
          <div class="setup-actions">
            <router-link to="/" class="btn btn-primary">
              <i class="fas fa-home"></i> 返回首页
            </router-link>
            <router-link to="/auth/login" class="btn btn-secondary">
              <i class="fas fa-sign-in-alt"></i> 前往登录
            </router-link>
          </div>
          <div class="setup-delete-hint">
            <i class="fas fa-info-circle"></i>
            如需重新安装，请删除服务器上的 <code>install.lock</code> 和 <code>config.json</code> 文件并重启服务器。
          </div>
        </div>
      </div>

      <!-- Install Form -->
      <div v-else class="setup-card">
        <div class="setup-steps">
          <div class="step" :class="{ active: step >= 1 }">
            <span class="step-num">1</span>
            <span class="step-text">站点设置</span>
          </div>
          <div class="step-line"></div>
          <div class="step" :class="{ active: step >= 2 }">
            <span class="step-num">2</span>
            <span class="step-text">数据库</span>
          </div>
          <div class="step-line"></div>
          <div class="step" :class="{ active: step >= 3 }">
            <span class="step-num">3</span>
            <span class="step-text">站长账号</span>
          </div>
          <div class="step-line"></div>
          <div class="step" :class="{ active: step >= 4 }">
            <span class="step-num">4</span>
            <span class="step-text">邮件&验证</span>
          </div>
          <div class="step-line"></div>
          <div class="step" :class="{ active: step >= 5 }">
            <span class="step-num">5</span>
            <span class="step-text">完成安装</span>
          </div>
        </div>

        <!-- Step 1: Site Settings -->
        <div v-if="step === 1" class="setup-form">
          <h2><i class="fas fa-globe"></i> 站点设置</h2>
          <p class="form-desc">配置你的皮肤站基本信息</p>

          <div class="form-group">
            <label for="siteName">站点名称 *</label>
            <input
              id="siteName"
              v-model="form.site_name"
              type="text"
              class="input"
              placeholder="例如：OakSkin Connect"
            />
          </div>

          <div class="form-group">
            <label for="siteUrl">站点地址</label>
            <input
              id="siteUrl"
              v-model="form.site_url"
              type="text"
              class="input"
              placeholder="http://localhost:8080"
            />
            <span class="form-hint">留空默认为 http://localhost:8080</span>
          </div>

          <div class="setup-nav">
            <button class="btn btn-primary btn-lg" @click="step = 2">
              下一步 <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Step 2: Database -->
        <div v-if="step === 2" class="setup-form">
          <h2><i class="fas fa-database"></i> 数据库配置</h2>
          <p class="form-desc">配置 MySQL 数据库连接信息</p>

          <div class="form-group">
            <label for="dbHost">数据库地址 *</label>
            <input
              id="dbHost"
              v-model="form.db_host"
              type="text"
              class="input"
              placeholder="127.0.0.1"
            />
            <span class="form-hint">通常为 localhost 或 127.0.0.1</span>
          </div>

          <div class="form-group">
            <label for="dbPort">数据库端口</label>
            <input
              id="dbPort"
              v-model="form.db_port"
              type="text"
              class="input"
              placeholder="3306"
            />
            <span class="form-hint">默认 3306</span>
          </div>

          <div class="form-group">
            <label for="dbName">数据库名 *</label>
            <input
              id="dbName"
              v-model="form.db_name"
              type="text"
              class="input"
              placeholder="oakskin"
            />
            <span class="form-hint">请先在主机面板创建一个数据库并填入其名称（表会自动创建）</span>
          </div>

          <div class="form-group">
            <label for="dbUser">数据库用户名 *</label>
            <input
              id="dbUser"
              v-model="form.db_user"
              type="text"
              class="input"
              placeholder="oakskin"
            />
          </div>

          <div class="form-group">
            <label for="dbPassword">数据库密码 *</label>
            <input
              id="dbPassword"
              v-model="form.db_password"
              type="password"
              class="input"
              placeholder="数据库密码"
            />
          </div>

          <div class="setup-nav">
            <button class="btn btn-secondary btn-lg" @click="step = 1">
              <i class="fas fa-arrow-left"></i> 上一步
            </button>
            <button class="btn btn-primary btn-lg" @click="step = 3">
              下一步 <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Step 3: Admin Account -->
        <div v-if="step === 3" class="setup-form">
          <h2><i class="fas fa-shield-alt"></i> 站长账号</h2>
          <p class="form-desc">创建站长（超级管理员），拥有站点最高权限</p>

          <div class="form-group">
            <label for="adminEmail">站长邮箱 *</label>
            <input
              id="adminEmail"
              v-model="form.admin_email"
              type="email"
              class="input"
              placeholder="admin@example.com"
            />
          </div>

          <div class="form-group">
            <label for="adminPassword">站长密码 *</label>
            <input
              id="adminPassword"
              v-model="form.admin_password"
              type="password"
              class="input"
              placeholder="设置站长密码"
            />
          </div>

          <div class="form-group">
            <label for="adminNickname">站长昵称</label>
            <input
              id="adminNickname"
              v-model="form.admin_nickname"
              type="text"
              class="input"
              placeholder="站长"
            />
          </div>
          <div class="form-group">
            <div class="role-badge">
              <i class="fas fa-crown"></i>
              超级管理员
            </div>
            <span class="form-hint">此账号拥有站点全部管理权限，包括用户管理、皮肤管理、站点设置等</span>
          </div>

          <div class="setup-nav">
            <button class="btn btn-secondary btn-lg" @click="step = 2">
              <i class="fas fa-arrow-left"></i> 上一步
            </button>
            <button class="btn btn-primary btn-lg" @click="step = 4">
              下一步 <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>

        <!-- Step 4: SMTP & Captcha -->
        <div v-if="step === 4" class="setup-form">
          <h2><i class="fas fa-envelope"></i> 邮件服务 & 安全验证</h2>
          <p class="form-desc">配置 SMTP 邮件服务（用于发送验证码和欢迎邮件）和滑块验证码</p>

          <div class="form-section">
            <h3><i class="fas fa-mail-bulk"></i> SMTP 邮件配置</h3>
            <p class="form-hint">推荐使用 QQ 邮箱 SMTP（收件人多为 QQ 邮箱，送达率最高）。<br>
            需先在邮箱设置中开启 SMTP 服务并生成<strong>授权码</strong>（不是登录密码）。</p>

            <div class="email-provider-tips">
              <div class="provider-tip">
                <strong>QQ 邮箱</strong> smtp.qq.com:465 (SSL) — 用 QQ 号@qq.com 和授权码
              </div>
              <div class="provider-tip">
                <strong>163 邮箱</strong> smtp.163.com:465 (SSL) — 用完整邮箱地址和授权码
              </div>
              <div class="provider-tip">
                <strong>Gmail</strong> smtp.gmail.com:587 (TLS) — 需开启"允许不够安全的应用"
              </div>
            </div>

            <div class="form-group">
              <label for="mailHost">SMTP 服务器地址</label>
              <input
                id="mailHost"
                v-model="form.mail_host"
                type="text"
                class="input"
                placeholder="smtp.qq.com"
              />
            </div>

            <div class="form-group">
              <label for="mailPort">SMTP 端口</label>
              <input
                id="mailPort"
                v-model="form.mail_port"
                type="text"
                class="input"
                placeholder="465"
              />
              <span class="form-hint">SSL 为 465，TLS 为 587</span>
            </div>

            <div class="form-group">
              <label for="mailUser">SMTP 用户名</label>
              <input
                id="mailUser"
                v-model="form.mail_user"
                type="text"
                class="input"
                placeholder="your@qq.com"
              />
              <span class="form-hint">完整邮箱地址，如 3460852069@qq.com</span>
            </div>

            <div class="form-group">
              <label for="mailPassword">SMTP 授权码</label>
              <input
                id="mailPassword"
                v-model="form.mail_password"
                type="password"
                class="input"
                placeholder="邮箱授权码（不是登录密码）"
              />
              <span class="form-hint">去邮箱设置 → 账户 → 生成授权码</span>
            </div>

            <div class="form-group">
              <label for="mailEncryption">加密方式</label>
              <select id="mailEncryption" v-model="form.mail_encryption" class="input">
                <option value="ssl">SSL（推荐）</option>
                <option value="tls">TLS</option>
              </select>
            </div>

            <div class="form-group">
              <label for="mailFrom">发件人地址</label>
              <input
                id="mailFrom"
                v-model="form.mail_from"
                type="text"
                class="input"
                placeholder="oak_mailer@163.com"
              />
              <span class="form-hint">发件邮箱地址，必须和 SMTP 用户名同一域名。<br>留空则使用 SMTP 用户名。不要填中文名！</span>
            </div>
          </div>

          <div class="form-section" style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border-color);">
            <h3><i class="fas fa-shield-alt"></i> 安全验证</h3>
            <p class="form-hint">本站使用自制滑块验证码，无需额外配置即可生效。</p>
          </div>

          <div class="setup-nav">
            <button class="btn btn-secondary btn-lg" @click="step = 3">
              <i class="fas fa-arrow-left"></i> 上一步
            </button>
            <button class="btn btn-primary btn-lg" @click="doInstall" :disabled="installing">
              <i class="fas fa-spinner fa-spin" v-if="installing"></i>
              {{ installing ? '安装中...' : '完成安装' }}
            </button>
          </div>
        </div>

        <!-- Step 5: Complete -->
        <div v-if="step === 5" class="setup-form">
          <div class="setup-success">
            <div class="success-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <h2>安装完成！</h2>
            <p>OakSkin Connect 已成功配置。</p>

            <div class="setup-summary">
              <div class="summary-item">
                <span class="summary-label">站点名称</span>
                <span class="summary-value">{{ form.site_name }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">数据库</span>
                <span class="summary-value">MySQL / {{ form.db_name }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">站长邮箱</span>
                <span class="summary-value">{{ form.admin_email }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">站长昵称</span>
                <span class="summary-value">{{ form.admin_nickname }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">SMTP</span>
                <span class="summary-value">{{ form.mail_host || '未配置' }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">角色权限</span>
                <span class="summary-value role-tag">超级管理员</span>
              </div>
            </div>

            <div class="setup-actions">
              <router-link to="/auth/login" class="btn btn-primary btn-lg">
                <i class="fas fa-sign-in-alt"></i> 前往登录
              </router-link>
            </div>
          </div>
        </div>

        <div v-if="error" class="setup-error">
          <i class="fas fa-exclamation-circle"></i>
          {{ error }}
        </div>
      </div>

      <div class="setup-footer">
        <p>OakSkin Connect v1.0 &mdash; 开源 Minecraft 皮肤站</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api'

const loading = ref(true)
const installed = ref(false)
const installing = ref(false)
const step = ref(1)
const error = ref('')

const form = ref({
  site_name: 'OakSkin Connect',
  site_url: 'http://localhost:8080',
  db_host: '127.0.0.1',
  db_port: '3306',
  db_name: '',
  db_user: '',
  db_password: '',
  admin_email: '',
  admin_password: '',
  admin_nickname: '站长',
  mail_host: '',
  mail_port: '465',
  mail_user: '',
  mail_password: '',
  mail_encryption: 'ssl',
  mail_from: '',
})

onMounted(async () => {
  try {
    const data = await api.get('/setup/status')
    installed.value = data.installed
  } catch (e) {
    installed.value = false
  } finally {
    loading.value = false
  }
})

async function doInstall() {
  error.value = ''

  if (!form.value.db_name) {
    error.value = '请输入数据库名'
    return
  }
  if (!form.value.db_user) {
    error.value = '请输入数据库用户名'
    return
  }
  if (form.value.db_password === '') {
    error.value = '请输入数据库密码'
    return
  }
  if (!form.value.admin_email) {
    error.value = '请输入管理员邮箱'
    return
  }
  if (!form.value.admin_password) {
    error.value = '请输入管理员密码'
    return
  }
  if (form.value.admin_password.length < 6) {
    error.value = '管理员密码长度不能少于6位'
    return
  }

  installing.value = true
  try {
    await api.post('/setup/install', form.value)
    step.value = 5
  } catch (e) {
    error.value = e.message || e.msg || '安装失败，请重试'
  } finally {
    installing.value = false
  }
}
</script>

<style scoped>
.setup-page {
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background:
    radial-gradient(ellipse at 50% 0%, rgba(108, 92, 231, 0.08) 0%, transparent 60%);
}

.setup-container {
  width: 100%;
  max-width: 560px;
}

.setup-header {
  text-align: center;
  margin-bottom: 32px;
}

.setup-logo {
  width: 64px;
  height: 64px;
  margin: 0 auto 16px;
  background: var(--accent-gradient);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  color: #fff;
  box-shadow: 0 0 40px rgba(108, 92, 231, 0.3);
}

.setup-header h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 6px;
}

.setup-header p {
  font-size: 14px;
  color: var(--text-muted);
}

.setup-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 40px;
  box-shadow: var(--shadow-lg);
}

/* Steps */
.setup-steps {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 36px;
  gap: 0;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.step-num {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  background: var(--bg-tertiary);
  color: var(--text-muted);
  border: 2px solid var(--border-color);
  transition: all 0.3s ease;
}

.step.active .step-num {
  background: var(--accent-gradient);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 4px 15px rgba(108, 92, 231, 0.35);
}

.step-text {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
}

.step.active .step-text {
  color: var(--accent-secondary);
}

.step-line {
  width: 40px;
  height: 2px;
  background: var(--border-color);
  margin: 0 8px;
  margin-bottom: 22px;
}

/* Form */
.setup-form h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.setup-form h2 i {
  color: var(--accent-primary);
}

.form-desc {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 24px;
}

.form-section h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--accent-secondary);
}

.form-section h3 i {
  font-size: 14px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.form-hint {
  display: block;
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 4px;
  line-height: 1.5;
}

.email-provider-tips {
  margin: 12px 0 20px;
  padding: 12px 16px;
  background: rgba(108, 92, 231, 0.06);
  border-radius: 8px;
  border: 1px solid rgba(108, 92, 231, 0.12);
}
.provider-tip {
  font-size: 12px;
  color: var(--text-secondary);
  padding: 4px 0;
  line-height: 1.6;
}
.provider-tip strong {
  color: var(--accent-primary);
  font-weight: 600;
}

.setup-nav {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 28px;
}

select.input {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236a6a8a'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 20px;
  padding-right: 40px;
}

/* Success */
.setup-success {
  text-align: center;
}

.success-icon {
  font-size: 56px;
  color: var(--success);
  margin-bottom: 16px;
}

.setup-success h2 {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 8px;
  justify-content: center;
}

.setup-success p {
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: 24px;
}

.setup-summary {
  text-align: left;
  background: var(--bg-tertiary);
  border-radius: 6px;
  padding: 16px 20px;
  margin-bottom: 20px;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
}

.summary-item:last-child {
  border-bottom: none;
}

.summary-label {
  color: var(--text-muted);
}

.summary-value {
  font-weight: 600;
}

.setup-warning {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: rgba(253, 203, 110, 0.1);
  border: 1px solid rgba(253, 203, 110, 0.2);
  border-radius: 6px;
  font-size: 13px;
  color: var(--warning);
  text-align: left;
  margin-bottom: 24px;
}

.setup-warning i {
  font-size: 16px;
  flex-shrink: 0;
}

.setup-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.setup-delete-hint {
  margin-top: 20px;
  padding: 12px 16px;
  background: var(--bg-tertiary);
  border-radius: 6px;
  font-size: 12px;
  color: var(--text-muted);
  text-align: left;
  display: flex;
  align-items: center;
  gap: 8px;
}

.setup-delete-hint code {
  background: rgba(108, 92, 231, 0.15);
  color: var(--accent-secondary);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: linear-gradient(135deg, rgba(253, 203, 110, 0.15) 0%, rgba(225, 112, 85, 0.15) 100%);
  border: 1px solid rgba(253, 203, 110, 0.3);
  border-radius: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--warning);
}

.role-badge i {
  font-size: 14px;
  color: var(--warning);
}

.role-tag {
  color: var(--warning) !important;
  font-weight: 700;
}

.setup-error {
  margin-top: 20px;
  padding: 12px 16px;
  background: rgba(225, 112, 85, 0.1);
  border: 1px solid rgba(225, 112, 85, 0.2);
  border-radius: 6px;
  color: var(--danger);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.setup-footer {
  text-align: center;
  margin-top: 24px;
  font-size: 12px;
  color: var(--text-muted);
}

@media (max-width: 600px) {
  .setup-card {
    padding: 24px;
  }
  .step-line {
    width: 20px;
  }
}
</style>