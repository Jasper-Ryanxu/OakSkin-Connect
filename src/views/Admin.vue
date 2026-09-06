<template>
  <div class="admin-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-shield-alt"></i> 管理面板</h1>
        <p>站点管理与维护</p>
      </div>

      <!-- Admin Tabs -->
      <div class="admin-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="tab-btn"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          <i :class="tab.icon"></i>
          {{ tab.label }}
        </button>
      </div>

      <!-- Overview Tab -->
      <div v-if="activeTab === 'overview'" class="admin-section">
        <div class="stats-grid">
          <div class="stat-card" v-for="stat in overviewStats" :key="stat.label">
            <div class="stat-icon" :style="{ background: stat.color }">
              <i :class="stat.icon"></i>
            </div>
            <div class="stat-data">
              <span class="stat-value">{{ stat.value }}</span>
              <span class="stat-label">{{ stat.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Users Tab -->
      <div v-if="activeTab === 'users'" class="admin-section">
        <div class="section-header">
          <h2>用户管理</h2>
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input
              v-model="userSearch"
              type="text"
              class="input"
              placeholder="搜索用户..."
              @input="onSearch"
            />
          </div>
        </div>

        <div class="user-table">
          <div class="table-header">
            <span class="col-uid">UID</span>
            <span class="col-user">用户</span>
            <span class="col-email">邮箱</span>
            <span class="col-role">权限</span>
            <span class="col-score">积分</span>
            <span class="col-date">注册时间</span>
            <span class="col-action">操作</span>
          </div>

          <div v-if="userList.length === 0" class="table-empty">暂无用户数据</div>

          <div
            v-for="user in userList"
            :key="user.uid"
            class="table-row"
            :class="{ 'row-self': user.uid === authStore.user?.uid }"
          >
            <span class="col-uid">#{{ user.uid }}</span>
            <span class="col-user">
              <span class="user-avatar-sm">
                <SkinAvatar v-if="user.uid" :skinUrl="getAvatarUrl(user.uid)" :size="28" />
                <i v-else class="fas fa-user"></i>
              </span>
              <span class="user-nickname">
                {{ user.nickname }}
                <span v-if="user.uid === authStore.user?.uid" class="self-tag">我</span>
              </span>
            </span>
            <span class="col-email">{{ user.email }}</span>
            <span class="col-role">
              <span class="role-badge-sm" :class="`role-${user.permission}`">
                <i v-if="user.permission >= 2" class="fas fa-crown"></i>
                {{ roleName(user.permission) }}
              </span>
            </span>
            <span class="col-score">{{ user.score }}</span>
            <span class="col-date">{{ formatDate(user.register_at) }}</span>
            <span class="col-action">
              <div class="action-group" v-if="user.uid !== authStore.user?.uid">
                <button
                  v-if="user.permission !== 2 || authStore.user?.permission >= 2"
                  class="btn btn-sm btn-ghost"
                  @click="showPermissionModal(user)"
                  title="修改权限"
                >
                  <i class="fas fa-shield-alt"></i>
                </button>
              </div>
              <span v-else class="self-action">-</span>
            </span>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="userTotal > perPage" class="pagination">
          <button :disabled="userPage <= 1" @click="userPage--; loadUsers()">
            <i class="fas fa-chevron-left"></i>
          </button>
          <span class="page-info">{{ userPage }} / {{ totalPages }}</span>
          <button :disabled="userPage >= totalPages" @click="userPage++; loadUsers()">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Settings Tab -->
      <div v-if="activeTab === 'settings'" class="admin-section">
        <div class="section-header">
          <h2>站点设置</h2>
        </div>

        <div class="settings-card">
          <div class="settings-group">
            <h3><i class="fas fa-upload"></i> 上传限制</h3>
            <div class="form-group">
              <label for="uploadSize">材质最大上传大小（KB）</label>
              <div class="input-group">
                <input
                  id="uploadSize"
                  v-model.number="settings.upload_max_size"
                  type="number"
                  class="input"
                  min="1"
                  max="512000"
                />
                <span class="input-suffix">KB</span>
              </div>
              <span class="form-hint">允许上传的最大文件大小，范围 1-512000 KB，默认 51200 KB（50 MB）</span>
            </div>
          </div>

          <div class="settings-group">
            <h3><i class="fas fa-bullhorn"></i> 站点公告</h3>
            <div class="form-group">
              <label for="announcement">公告内容</label>
              <textarea
                id="announcement"
                v-model="settings.announcement"
                class="input"
                rows="4"
                placeholder="在此输入公告内容，留空则不显示公告"
              ></textarea>
              <span class="form-hint">公告将显示在用户仪表盘顶部，支持纯文本</span>
            </div>
          </div>

          <div class="settings-group">
            <h3><i class="fas fa-info-circle"></i> 站点信息与SEO</h3>
            <div class="form-group">
              <label for="siteName">站点名称</label>
              <input id="siteName" v-model.trim="settings.site_name" type="text" class="input" placeholder="OakSkin Connect" />
              <span class="form-hint">修改后将同步到导航栏、页脚版权与浏览器标题</span>
            </div>
            <div class="form-group">
              <label for="seoKeywords">SEO 关键词</label>
              <input id="seoKeywords" v-model.trim="settings.seo_keywords" type="text" class="input" placeholder="多个关键词用英文逗号分隔" />
            </div>
            <div class="form-group">
              <label for="seoDescription">SEO 介绍</label>
              <textarea
                id="seoDescription"
                v-model.trim="settings.seo_description"
                class="input"
                rows="3"
                placeholder="用于搜索引擎展示的站点介绍"
              ></textarea>
            </div>
          </div>

          <div class="settings-group">
            <h3><i class="fas fa-link"></i> 友情链接</h3>
            <p class="form-hint">将展示在站底页脚。留空则隐藏友情链接区域。</p>
            <div v-for="(fl, i) in settings.friend_links" :key="i" class="friend-link-row">
              <div class="form-group">
                <label :for="`flName${i}`">名称</label>
                <input :id="`flName${i}`" v-model.trim="fl.name" type="text" class="input" placeholder="朋友站点" />
              </div>
              <div class="form-group">
                <label :for="`flUrl${i}`">链接地址</label>
                <input :id="`flUrl${i}`" v-model.trim="fl.url" type="url" class="input" placeholder="https://example.com" />
              </div>
              <button
                type="button"
                class="btn btn-sm btn-ghost btn-danger fl-remove"
                title="删除此项"
                @click="removeFriendLink(i)"
              >
                <i class="fas fa-times"></i>
              </button>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" @click="addFriendLink">
              <i class="fas fa-plus"></i> 添加链接
            </button>
          </div>

          <div class="settings-group">
            <h3><i class="fas fa-code-branch"></i> OakSkin 第三方登录</h3>
            <p class="form-hint">在皮肤站（skin.oak-ms.top）创建 OAuth 应用后，将对应信息填入此处。</p>
            <div class="oauth-settings-grid">
              <div class="form-group">
                <label for="oakClientId">应用 ID</label>
                <input id="oakClientId" v-model.trim="settings.oakskin.client_id" type="text" class="input" placeholder="应用 ID" />
              </div>
              <div class="form-group">
                <label for="oakSecret">应用密钥</label>
                <input id="oakSecret" v-model.trim="settings.oakskin.client_secret" type="password" class="input" placeholder="请输入应用密钥" autocomplete="off" />
              </div>
              <div class="form-group oauth-redirect-full">
                <label for="oakRedirect">回调地址</label>
                <input id="oakRedirect" v-model.trim="settings.oakskin.redirect_uri" type="text" class="input" placeholder="https://mcskin.oak-ms.top/callback/oakskin" />
              </div>
            </div>
          </div>

          <div class="settings-actions">
            <button
              class="btn btn-primary"
              :disabled="settingsSaving"
              @click="saveSettings"
            >
              <i class="fas fa-spinner fa-spin" v-if="settingsSaving"></i>
              <i class="fas fa-save" v-else></i>
              {{ settingsSaving ? '保存中...' : '保存设置' }}
            </button>
            <span v-if="settingsMsg" class="settings-msg" :class="settingsMsgType">{{ settingsMsg }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Permission Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal">
          <div class="modal-header">
            <h3><i class="fas fa-shield-alt"></i> 修改权限</h3>
            <button class="modal-close" @click="showModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="modal-user-info">
              <span class="modal-user-avatar">
                <SkinAvatar v-if="modalUser?.uid" :skinUrl="getAvatarUrl(modalUser.uid)" :size="40" />
                <i v-else class="fas fa-user"></i>
              </span>
              <div>
                <p class="modal-user-name">{{ modalUser?.nickname }}</p>
                <p class="modal-user-email">{{ modalUser?.email }}</p>
              </div>
            </div>

            <div class="permission-options">
              <label
                v-for="opt in permissionOptions"
                :key="opt.value"
                class="perm-radio"
                :class="{
                  selected: selectedPermission === opt.value,
                  disabled: opt.disabled
                }"
              >
                <input
                  type="radio"
                  :value="opt.value"
                  v-model="selectedPermission"
                  :disabled="opt.disabled"
                />
                <span class="perm-radio-dot"></span>
                <span class="perm-radio-label">
                  <span class="perm-name">{{ opt.label }}</span>
                  <span class="perm-desc">{{ opt.desc }}</span>
                </span>
              </label>
            </div>

            <div v-if="permissionError" class="alert alert-error">
              <i class="fas fa-exclamation-circle"></i>
              {{ permissionError }}
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showModal = false">取消</button>
            <button
              class="btn btn-primary"
              :disabled="selectedPermission === modalUser?.permission"
              @click="confirmPermission"
            >
              <i class="fas fa-check"></i> 确认修改
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import { adminApi } from '@/api/admin'
import { debounce, getAvatarUrl } from '@/utils/helpers'
import SkinAvatar from '@/components/SkinAvatar.vue'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()

const activeTab = ref('overview')

const tabs = [
  { id: 'overview', label: '概览', icon: 'fas fa-chart-pie' },
  { id: 'users', label: '用户管理', icon: 'fas fa-users' },
  { id: 'settings', label: '站点设置', icon: 'fas fa-cog' }
]

// Stats
const overviewStats = ref([
  { label: '用户总数', value: '--', icon: 'fas fa-users', color: 'rgba(108, 92, 231, 0.2)' },
  { label: '皮肤总数', value: '--', icon: 'fas fa-palette', color: 'rgba(0, 184, 148, 0.2)' },
  { label: '角色总数', value: '--', icon: 'fas fa-user-tag', color: 'rgba(253, 203, 110, 0.2)' },
  { label: '今日上传', value: '--', icon: 'fas fa-upload', color: 'rgba(116, 185, 255, 0.2)' }
])

// Users
const userList = ref([])
const userSearch = ref('')
const userTotal = ref(0)
const userPage = ref(1)
const perPage = 15

// Modal
const showModal = ref(false)
const modalUser = ref(null)
const selectedPermission = ref(0)
const permissionError = ref('')

// Settings
const settings = ref({
  upload_max_size: 51200,
  announcement: '',
  site_name: 'OakSkin Connect',
  seo_keywords: '',
  seo_description: '',
  friend_links: [{ name: '', url: '' }],
  oakskin: {
    client_id: '',
    client_secret: '',
    redirect_uri: 'https://mcskin.oak-ms.top/callback/oakskin'
  }
})
const settingsSaving = ref(false)
const settingsMsg = ref('')
const settingsMsgType = ref('success')

const totalPages = computed(() => Math.max(1, Math.ceil(userTotal.value / perPage)))

const permissionOptions = computed(() => {
  const currentPerm = authStore.user?.permission || 0
  return [
    { value: 2, label: '超级管理员', desc: '站点最高权限，拥有所有管理功能', disabled: currentPerm < 2 },
    { value: 1, label: '管理员', desc: '可管理用户和皮肤', disabled: currentPerm < 2 },
    { value: 0, label: '用户', desc: '普通注册用户', disabled: false },
    { value: -1, label: '封禁', desc: '禁止登录和使用', disabled: false }
  ]
})

function roleName(permission) {
  const names = { 2: '超级管理员', 1: '管理员', 0: '用户', '-1': '封禁' }
  return names[permission] || '未知'
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr + 'Z')
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

onMounted(async () => {
  await Promise.all([loadStats(), loadUsers(), loadSettings()])
})

// Load stats
async function loadStats() {
  try {
    const data = await adminApi.getStats()
    const s = data.data || data
    overviewStats.value = [
      { label: '用户总数', value: s.users, icon: 'fas fa-users', color: 'rgba(108, 92, 231, 0.2)' },
      { label: '皮肤总数', value: s.skins, icon: 'fas fa-palette', color: 'rgba(0, 184, 148, 0.2)' },
      { label: '角色总数', value: s.players, icon: 'fas fa-user-tag', color: 'rgba(253, 203, 110, 0.2)' },
      { label: '今日上传', value: s.today_uploads, icon: 'fas fa-upload', color: 'rgba(116, 185, 255, 0.2)' }
    ]
  } catch (e) {
    console.error('Failed to load stats:', e)
  }
}

// Load users
async function loadUsers() {
  try {
    const data = await adminApi.getUsers({ keyword: userSearch.value, page: userPage.value, per_page: perPage })
    userList.value = data.data || []
    userTotal.value = data.total || 0
  } catch (e) {
    console.error('Failed to load users:', e)
  }
}

const onSearch = debounce(() => {
  userPage.value = 1
  loadUsers()
}, 400)

// Permission modal
function showPermissionModal(user) {
  selectedPermission.value = user.permission
  permissionError.value = ''
  modalUser.value = user
  showModal.value = true
}

async function confirmPermission() {
  if (!modalUser.value) return
  if (selectedPermission.value === modalUser.value.permission) return

  permissionError.value = ''
  try {
    const data = await adminApi.updatePermission(modalUser.value.uid, selectedPermission.value)
    showModal.value = false
    await loadUsers()
  } catch (e) {
    permissionError.value = e.message || e.msg || '修改权限失败'
  }
}

// Settings
function addFriendLink() {
  settings.value.friend_links.push({ name: '', url: '' })
}

function removeFriendLink(i) {
  settings.value.friend_links.splice(i, 1)
  if (!settings.value.friend_links.length) settings.value.friend_links.push({ name: '', url: '' })
}

async function loadSettings() {
  try {
    const data = await adminApi.getSettings()
    const s = data.data || data
    settings.value = {
      upload_max_size: s.upload_max_size ? s.upload_max_size : 51200,
      announcement: s.announcement || '',
      site_name: s.site_name || 'OakSkin Connect',
      seo_keywords: s.seo_keywords || '',
      seo_description: s.seo_description || '',
      friend_links: Array.isArray(s.friend_links) && s.friend_links.length ? s.friend_links : [{ name: '', url: '' }],
      oakskin: {
        client_id: s.oakskin?.client_id || '',
        client_secret: s.oakskin?.client_secret || '',
        redirect_uri: s.oakskin?.redirect_uri || 'https://mcskin.oak-ms.top/callback/oakskin'
      }
    }
  } catch (e) {
    console.error('Failed to load settings:', e)
  }
}

async function saveSettings() {
  settingsSaving.value = true
  settingsMsg.value = ''
  try {
    const data = await adminApi.updateSettings({
      upload_max_size: settings.value.upload_max_size,
      announcement: settings.value.announcement,
      site_name: settings.value.site_name,
      seo_keywords: settings.value.seo_keywords,
      seo_description: settings.value.seo_description,
      friend_links: settings.value.friend_links,
      oakskin: settings.value.oakskin
    })
    settingsMsg.value = data.message || '设置已保存'
    settingsMsgType.value = 'success'
    settingsStore.load(true) // 刷新全局站点设置（站点名/SEO/友情链接）
  } catch (e) {
    settingsMsg.value = e.message || e.msg || '保存失败'
    settingsMsgType.value = 'error'
  } finally {
    settingsSaving.value = false
    setTimeout(() => { settingsMsg.value = '' }, 3000)
  }
}
</script>

<style scoped>
.admin-page {
  padding-bottom: 60px;
}

.admin-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 24px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 4px;
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 24px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease;
  font-family: var(--font-family);
}

.tab-btn:hover {
  color: var(--text-primary);
  background: rgba(108, 92, 231, 0.08);
}

.tab-btn.active {
  color: #fff;
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
  box-shadow: 0 4px 15px rgba(108, 92, 231, 0.35);
}

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--text-primary);
}

.stat-data {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 22px;
  font-weight: 800;
}

.stat-label {
  font-size: 12px;
  color: var(--text-muted);
}

/* Section header */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.section-header h2 {
  font-size: 18px;
  font-weight: 700;
}

.search-box {
  position: relative;
  width: 260px;
}

.search-box i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 14px;
}

.search-box .input {
  padding-left: 34px;
}

/* User Table */
.user-table {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  overflow: hidden;
}

.table-header {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  background: var(--bg-tertiary);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.table-row {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-top: 1px solid var(--border-color);
  font-size: 13px;
  transition: background 0.15s ease;
}

.table-row:hover {
  background: rgba(108, 92, 231, 0.04);
}

.table-row.row-self {
  background: rgba(108, 92, 231, 0.06);
}

.table-empty {
  padding: 40px;
  text-align: center;
  color: var(--text-muted);
}

/* Columns */
.col-uid { width: 60px; font-weight: 600; color: var(--text-muted); font-size: 12px; }
.col-user { flex: 1; display: flex; align-items: center; gap: 10px; }
.col-email { flex: 1.5; color: var(--text-secondary); font-size: 12px; }
.col-role { width: 120px; }
.col-score { width: 60px; text-align: center; }
.col-date { width: 120px; color: var(--text-muted); font-size: 12px; }
.col-action { width: 80px; text-align: center; }

/* User avatar */
.user-avatar-sm {
  width: 28px;
  height: 28px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--text-muted);
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}

.user-nickname {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}

.self-tag {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 3px;
  background: rgba(108, 92, 231, 0.15);
  color: var(--accent-secondary);
  font-weight: 700;
}

/* Role badges */
.role-badge-sm {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
}

.role-2 {
  background: rgba(225, 112, 85, 0.15);
  color: var(--danger);
}

.role-2 i {
  font-size: 10px;
}

.role-1 {
  background: rgba(108, 92, 231, 0.15);
  color: var(--accent-secondary);
}

.role-0 {
  background: rgba(0, 184, 148, 0.15);
  color: var(--success);
}

.role-neg1 {
  background: rgba(108, 92, 231, 0.08);
  color: var(--text-muted);
}

/* Action group */
.action-group {
  display: flex;
  gap: 4px;
  justify-content: center;
}

.self-action {
  color: var(--text-muted);
  font-size: 12px;
}

/* Pagination */
.page-info {
  font-size: 13px;
  color: var(--text-muted);
  min-width: 60px;
  text-align: center;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.modal {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  width: 100%;
  max-width: 440px;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-close {
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
}

.oauth-settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-top: 4px;
}

.friend-link-row {
  display: grid;
  grid-template-columns: 1fr 2fr auto;
  gap: 12px;
  align-items: end;
  margin-bottom: 12px;
}

.friend-link-row .form-group {
  margin-bottom: 0;
}

.fl-remove {
  height: 38px;
  width: 38px;
  flex-shrink: 0;
}

.oauth-settings-grid .oauth-redirect-full {
  grid-column: 1 / -1;
}

@media (max-width: 640px) {
  .oauth-settings-grid {
    grid-template-columns: 1fr;
  }
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--border-color);
}

/* Modal user info */
.modal-user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--border-color);
}

.modal-user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  overflow: hidden;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 16px;
  flex-shrink: 0;
}

.modal-user-name {
  font-weight: 700;
  font-size: 15px;
  margin-bottom: 2px;
}

.modal-user-email {
  font-size: 12px;
  color: var(--text-muted);
}

/* Permission options */
.permission-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.perm-radio {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.perm-radio:hover:not(.disabled) {
  border-color: rgba(108, 92, 231, 0.3);
  background: rgba(108, 92, 231, 0.04);
}

.perm-radio.selected {
  border-color: var(--accent-primary);
  background: rgba(108, 92, 231, 0.08);
}

.perm-radio.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.perm-radio input {
  display: none;
}

.perm-radio-dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid var(--border-color);
  flex-shrink: 0;
  transition: all 0.15s ease;
  position: relative;
}

.perm-radio.selected .perm-radio-dot {
  border-color: var(--accent-primary);
  background: var(--accent-primary);
}

.perm-radio.selected .perm-radio-dot::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #fff;
}

.perm-radio-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.perm-name {
  font-size: 14px;
  font-weight: 700;
}

.perm-desc {
  font-size: 11px;
  color: var(--text-muted);
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .admin-tabs {
    flex-direction: column;
  }
  .col-email, .col-date {
    display: none;
  }
  .search-box {
    width: 180px;
  }
}

/* Settings */
.settings-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 24px;
}

.settings-group {
  margin-bottom: 28px;
}

.settings-group:last-child {
  margin-bottom: 16px;
}

.settings-group h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.settings-group h3 i {
  color: var(--accent-primary);
  font-size: 14px;
}

.settings-group .form-group {
  margin-bottom: 0;
}

.settings-group .input-group {
  display: flex;
  align-items: center;
  gap: 8px;
  max-width: 200px;
}

.settings-group .input-group .input {
  flex: 1;
}

.input-suffix {
  font-size: 14px;
  color: var(--text-muted);
  font-weight: 600;
}

.settings-group textarea.input {
  max-width: 500px;
  min-height: 80px;
  resize: vertical;
}

.settings-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}

.settings-msg {
  font-size: 13px;
  font-weight: 600;
}

.settings-msg.success {
  color: var(--success);
}

.settings-msg.error {
  color: var(--danger);
}
</style>