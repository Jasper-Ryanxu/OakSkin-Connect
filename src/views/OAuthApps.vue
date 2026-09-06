<template>
  <div class="oauth-apps-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-key"></i> OAuth 应用</h1>
        <p>创建第三方应用，供外部通过 OAuth2 登录并读取你的站点用户基础信息</p>
      </div>

      <div class="panel-card">
        <h3 class="panel-title"><i class="fas fa-plus-circle"></i> 新建应用</h3>
        <div class="panel-body">
          <div v-if="oauthMsg" class="alert" :class="oauthMsgType === 'error' ? 'alert-error' : 'alert-success'">
            <i class="fas" :class="oauthMsgType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'"></i>
            {{ oauthMsg }}
          </div>

          <div class="oauth-form-grid">
            <div class="form-group">
              <label for="oauthName">应用名称</label>
              <input id="oauthName" v-model="oauthForm.name" type="text" class="input" placeholder="例如：我的小站" />
            </div>
            <div class="form-group">
              <label for="oauthRedirect">回调地址（redirect_uri）</label>
              <input id="oauthRedirect" v-model="oauthForm.redirect_uri" type="text" class="input" placeholder="https://example.com/callback" />
            </div>
            <div class="form-group">
              <label for="oauthIcon">图标链接（可选）</label>
              <input id="oauthIcon" v-model="oauthForm.icon" type="text" class="input" placeholder="https://example.com/icon.png" @input="onIconInput" @change="iconPreviewError = false" />
              <div class="icon-preview" :class="['icon-preview-wrap', { 'icon-preview-empty': !iconPreviewUrl }]">
                <img v-if="iconPreviewUrl" :src="iconPreviewUrl" alt="图标预览" @load="iconPreviewError = false" @error="onIconError" />
                <i v-else class="fas fa-image"></i>
              </div>
              <span v-if="iconPreviewUrl && iconPreviewError" class="form-hint error">图标加载失败，请检查链接</span>
            </div>
          </div>

          <div class="settings-group">
            <h4>授权权限（可多选）</h4>
            <div class="scope-options">
              <label v-for="opt in scopeOptions" :key="opt.value" class="scope-check">
                <input type="checkbox" :value="opt.value" v-model="oauthForm.scopes" />
                <span class="scope-check-custom"><i class="fas fa-check"></i></span>
                <span class="scope-check-text">{{ opt.label }}</span>
              </label>
            </div>
          </div>

          <div class="panel-actions">
            <button class="btn btn-primary" :disabled="oauthCreating" @click="createOAuth">
              <i class="fas fa-spinner fa-spin" v-if="oauthCreating"></i>
              <i class="fas fa-plus" v-else></i>
              {{ oauthCreating ? '创建中...' : '创建应用' }}
            </button>
          </div>
        </div>
      </div>

      <div class="panel-card">
        <h3 class="panel-title"><i class="fas fa-list"></i> 应用列表</h3>
        <div class="panel-body">
          <div v-if="oauthList.length === 0" class="empty-state">
            <i class="fas fa-plug"></i>
            <h3>暂无应用</h3>
            <p>创建一个应用以开始接入 OAuth2</p>
          </div>
          <div v-else v-for="app in oauthList" :key="app.client_id" class="oauth-app-item">
            <div class="oauth-app-icon">
              <img v-if="app.icon" :src="app.icon" alt="" @error="e => { e.target.style.display = 'none' }" />
              <i v-else class="fas fa-plug"></i>
            </div>
            <div class="oauth-app-info">
              <div class="oauth-app-name">
                {{ app.name }}
                <span v-for="s in (String(app.scopes).split(/[\\s,]+/).filter(Boolean))" :key="s" class="scope-tag">{{ s }}</span>
              </div>
              <div class="oauth-app-meta">
                <code class="oauth-code">client_id: {{ app.client_id }}</code>
                <code class="oauth-code">secret: {{ app.secret }}</code>
                <div class="oauth-redirect">{{ app.redirect_uri }}</div>
              </div>
            </div>
            <button class="btn btn-sm btn-ghost btn-danger" title="删除应用" @click="deleteOAuth(app)">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { oauthApi } from '@/api/oauth'

const oauthList = ref([])
const oauthMsg = ref('')
const oauthMsgType = ref('success')
const oauthCreating = ref(false)
const iconPreviewUrl = ref('')
const iconPreviewError = ref(false)
const scopeOptions = [
  { value: 'email', label: '读取邮箱' },
  { value: 'nickname', label: '读取昵称' },
  { value: 'avatar', label: '读取头像' },
  { value: 'score', label: '读取积分' }
]
const oauthForm = ref({
  name: '',
  redirect_uri: '',
  icon: '',
  scopes: []
})

onMounted(loadOAuth)

async function loadOAuth() {
  try {
    const data = await oauthApi.listClients()
    oauthList.value = data.data || []
  } catch (e) {
    oauthList.value = []
  }
}

async function onIconInput() {
  iconPreviewUrl.value = oauthForm.value.icon
  iconPreviewError.value = false
}

function onIconError() {
  // 仅当输入框不再聚焦时才判定失败，避免输入过程中的误报
  iconPreviewError.value = true
}

async function createOAuth() {
  oauthMsg.value = ''
  oauthMsgType.value = 'success'
  if (!oauthForm.value.name.trim() || !oauthForm.value.redirect_uri.trim()) {
    oauthMsg.value = '请填写应用名称和回调地址'
    oauthMsgType.value = 'error'
    return
  }
  if (oauthForm.value.scopes.length === 0) oauthForm.value.scopes = ['email']
  oauthCreating.value = true
  try {
    const data = await oauthApi.createClient({
      name: oauthForm.value.name,
      redirect_uri: oauthForm.value.redirect_uri,
      icon: oauthForm.value.icon,
      scopes: oauthForm.value.scopes.join(' ')
    })
    oauthMsg.value = data.message || '应用创建成功'
    const created = data.data || {}
    oauthMsg.value += `，client_id: ${created.client_id}`
    oauthForm.value = { name: '', redirect_uri: '', icon: '', scopes: [] }
    iconPreviewUrl.value = ''
    iconPreviewError.value = false
    await loadOAuth()
  } catch (e) {
    oauthMsg.value = e.message || e.msg || '创建失败'
    oauthMsgType.value = 'error'
  } finally {
    oauthCreating.value = false
    setTimeout(() => { oauthMsg.value = '' }, 6000)
  }
}

async function deleteOAuth(app) {
  if (!confirm(`确定删除应用「${app.name}」吗？该操作不可恢复。`)) return
  try {
    await oauthApi.deleteClient(app.client_id)
    await loadOAuth()
  } catch (e) {
    alert(e.message || e.msg || '删除失败')
  }
}
</script>

<style scoped>
.oauth-apps-page {
  padding: 30px 0 60px;
}

.page-header {
  text-align: center;
  margin-bottom: 24px;
}

.page-header h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 6px;
}

.page-header h1 i {
  color: var(--accent-primary);
  margin-right: 6px;
}

.page-header p {
  color: var(--text-muted);
  font-size: 14px;
}

.panel-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  overflow: hidden;
  margin-bottom: 20px;
}

.panel-title {
  font-size: 15px;
  font-weight: 700;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.panel-title i {
  color: var(--accent-primary);
}

.panel-body {
  padding: 20px;
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.alert {
  margin-bottom: 16px;
}

.oauth-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.oauth-form-grid .form-group:nth-child(3) {
  grid-column: 1 / -1;
}

.icon-preview {
  margin-top: 10px;
  width: 64px;
  height: 64px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.icon-preview-empty {
  background: var(--bg-tertiary);
  color: var(--text-muted);
  font-size: 20px;
}

.form-hint.error {
  color: var(--danger);
}

.settings-group h4 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 10px;
}

.scope-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 4px;
}

.scope-check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
  color: var(--text-secondary);
}

.scope-check input {
  display: none;
}

.scope-check-custom {
  width: 18px;
  height: 18px;
  border: 2px solid var(--border-color);
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}

.scope-check-custom i {
  font-size: 10px;
  color: #fff;
  opacity: 0;
}

.scope-check input:checked + .scope-check-custom {
  background: var(--accent-primary);
  border-color: var(--accent-primary);
}

.scope-check input:checked + .scope-check-custom i {
  opacity: 1;
}

.oauth-app-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 0;
  border-top: 1px solid var(--border-color);
}

.oauth-app-item:first-of-type {
  border-top: none;
}

.oauth-app-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  overflow: hidden;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.oauth-app-icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.oauth-app-info {
  flex: 1;
  min-width: 0;
}

.oauth-app-name {
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.scope-tag {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  background: rgba(108, 92, 231, 0.15);
  color: var(--accent-secondary);
}

.oauth-app-meta {
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.oauth-code {
  font-size: 11px;
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 2px 6px;
  color: var(--text-secondary);
}

.oauth-redirect {
  font-size: 11px;
  color: var(--text-muted);
  word-break: break-all;
}

.btn-danger {
  color: var(--danger) !important;
}

@media (max-width: 640px) {
  .oauth-form-grid {
    grid-template-columns: 1fr;
  }
  .oauth-app-item {
    flex-wrap: wrap;
  }
}
</style>