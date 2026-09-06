<template>
  <div class="dashboard-page">
    <div class="container">
      <!-- Announcement Banner -->
      <div v-if="announcement" class="announcement-banner">
        <div class="announcement-icon">
          <i class="fas fa-bullhorn"></i>
        </div>
        <div class="announcement-content markdown-body" v-html="renderedAnnouncement"></div>
      </div>

      <!-- User Info Card -->
      <div class="user-card">
        <div class="user-card-left">
          <div class="user-avatar">
            <SkinAvatar v-if="authStore.user?.uid" :skinUrl="getAvatarUrl(authStore.user.uid)" :size="56" />
            <i v-else class="fas fa-user"></i>
          </div>
          <div class="user-info">
            <h1 class="user-nickname">{{ authStore.nickname || '用户' }}</h1>
            <p class="user-email">{{ authStore.user?.email || '' }}</p>
            <span class="user-role" :class="roleClass">{{ roleName }}</span>
          </div>
        </div>
        <div class="user-card-right">
          <router-link to="/user/profile/edit" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit"></i> 编辑资料
          </router-link>
        </div>
      </div>

      <!-- Yggdrasil 外置登录说明 -->
      <div class="yggdrasil-card">
        <div class="yggdrasil-head">
          <div class="yggdrasil-icon"><i class="fas fa-server"></i></div>
          <div class="yggdrasil-title">
            <h3>外置登录（Yggdrasil）</h3>
            <p>在启动器中配置本站认证服务器，即可使用本站账号外置登录。</p>
          </div>
        </div>
        <div class="yggdrasil-api-row">
          <span class="yggdrasil-label">本站的 Yggdrasil API 认证服务器地址：</span>
          <code class="yggdrasil-code">{{ yggdrasilApi }}</code>
        </div>
        <div class="yggdrasil-actions">
          <button class="btn btn-primary btn-sm" @click="copyYggdrasil">
            <i class="fas" :class="yggCopied ? 'fa-check' : 'fa-copy'"></i>
            {{ yggCopied ? '已复制' : '复制 API 地址' }}
          </button>
          <div class="drag-tip">
            <span class="drag-desc">或将下方按钮拖动至启动器（如 HMCL、BakaXL）的任意界面，快速添加认证服务器</span>
            <div class="drag-btn" draggable="true" @dragstart="onYggDragStart" @click="copyYggdrasil">
              <i class="fas fa-grip-vertical"></i> 拖我至启动器
            </div>
          </div>
          <a class="ygg-tutorial" href="https://docs.skin.oak-ms.top/beginner/yggdrasil.html" target="_blank" rel="noopener">
            <i class="fas fa-book-open"></i> 查看教程
          </a>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="stats-row">
        <div class="stat-item">
          <span class="stat-value">{{ stats.skins }}</span>
          <span class="stat-label">上传皮肤</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-value">{{ stats.players }}</span>
          <span class="stat-label">游戏角色</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-value">{{ stats.closet }}</span>
          <span class="stat-label">衣柜收藏</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-value">{{ authStore.score }}</span>
          <span class="stat-label">积分</span>
        </div>
      </div>

      <!-- Main Content: Two Column Layout -->
      <div class="dashboard-layout">
        <!-- Left Panel: Quick Links -->
        <div class="dashboard-sidebar">
          <div class="panel-card">
            <h3 class="panel-title"><i class="fas fa-bolt"></i> 快捷操作</h3>
            <div class="quick-links">
              <router-link to="/skinlib/upload" class="quick-link">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>上传皮肤</span>
              </router-link>
              <router-link to="/user/player" class="quick-link">
                <i class="fas fa-users"></i>
                <span>角色管理</span>
              </router-link>
              <router-link to="/user/closet" class="quick-link">
                <i class="fas fa-box"></i>
                <span>我的衣柜</span>
              </router-link>
              <router-link to="/skinlib" class="quick-link">
                <i class="fas fa-search"></i>
                <span>浏览皮肤库</span>
              </router-link>
              <router-link to="/config" class="quick-link">
                <i class="fas fa-wrench"></i>
                <span>模组配置</span>
              </router-link>
            </div>
          </div>

          <!-- Sponsor Card -->
          <div class="panel-card sponsor-card">
            <h3 class="panel-title"><i class="fas fa-heart"></i> 支持我们</h3>
            <div class="sponsor-body">
              <p class="sponsor-desc">觉得不错？赞助一杯咖啡，助力本站持续运行！</p>
              <a href="https://afdian.com/a/oak-mc" target="_blank" rel="noopener" class="sponsor-link">
                <img
                  width="200"
                  src="https://pic1.afdiancdn.com/static/img/welcome/button-sponsorme.png"
                  alt="支持我们"
                />
              </a>
            </div>
          </div>
        </div>

        <!-- Right Panel: List -->
        <div class="dashboard-main">
          <div class="panel-card">
            <div class="panel-header">
              <h3 class="panel-title"><i class="fas fa-clock"></i> 最近上传</h3>
              <router-link to="/skinlib/upload" class="btn btn-primary btn-sm">
                <i class="fas fa-upload"></i> 上传新皮肤
              </router-link>
            </div>

            <div v-if="loadingRecent" class="loading-spinner">
              <div class="spinner"></div>
            </div>

            <div v-else-if="recentSkins.length === 0" class="empty-state">
              <i class="fas fa-palette"></i>
              <h3>还没有上传皮肤</h3>
              <p>点击上方按钮上传你的第一个皮肤吧！</p>
            </div>

            <div v-else class="skin-list">
              <div class="list-header">
                <span class="list-col-preview"></span>
                <span class="list-col-name">名称</span>
                <span class="list-col-type">类型</span>
                <span class="list-col-date">上传时间</span>
                <span class="list-col-likes">喜欢</span>
              </div>
              <div
                v-for="skin in recentSkins"
                :key="skin.tid"
                class="list-row"
                @click="$router.push(`/skinlib/${skin.tid}`)"
              >
                <span class="list-col-preview">
                  <span class="upload-head">
                    <CapePreview2D
                      v-if="skin.type === 'cape'"
                      :capeUrl="getSkinUrl(skin.hash)"
                      :width="32"
                      :height="32"
                    />
                    <SkinAvatar
                      v-else
                      :skinUrl="getSkinUrl(skin.hash)"
                      :size="32"
                      class="list-thumb-avatar"
                    />
                  </span>
                </span>
                <span class="list-col-name">
                  <span class="list-skin-name">{{ skin.name }}</span>
                </span>
                <span class="list-col-type">
                  <span class="type-badge" :class="skin.type">
                    {{ skin.type === 'alex' ? '纤细' : '经典' }}
                  </span>
                </span>
                <span class="list-col-date">{{ formatDate(skin.upload_at) }}</span>
                <span class="list-col-likes">
                  <i class="fas fa-heart"></i> {{ skin.likes || 0 }}
                </span>
              </div>
            </div>
          </div>

          <!-- Players List -->
          <div class="panel-card">
            <div class="panel-header">
              <h3 class="panel-title"><i class="fas fa-users"></i> 我的角色</h3>
              <router-link to="/user/player" class="btn btn-ghost btn-sm">
                <i class="fas fa-arrow-right"></i> 管理角色
              </router-link>
            </div>

            <div v-if="players.length === 0" class="empty-state">
              <i class="fas fa-user-plus"></i>
              <h3>还没有角色</h3>
              <p>去角色管理页面添加你的 Minecraft 角色</p>
            </div>

            <div v-else class="player-list">
              <div class="list-header">
                <span class="list-col-preview"></span>
                <span class="list-col-name">角色名</span>
                <span class="list-col-name">当前皮肤</span>
                <span class="list-col-date">最后更新</span>
              </div>
              <div
                v-for="player in players"
                :key="player.pid"
                class="list-row"
                @click="$router.push('/user/player')"
              >
                <span class="list-col-preview">
                  <span class="player-head">
                    <SkinAvatar
                      v-if="player.skin?.hash"
                      :skinUrl="getSkinUrl(player.skin.hash)"
                      :size="40"
                      class="list-thumb"
                    />
                    <i v-else class="fas fa-user"></i>
                  </span>
                </span>
                <span class="list-col-name">
                  <span class="list-skin-name">{{ player.name }}</span>
                </span>
                <span class="list-col-name">{{ player.skin?.name || '未设置' }}</span>
                <span class="list-col-date">{{ formatDate(player.last_modified) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import { skinsApi } from '@/api/skins'
import { playersApi } from '@/api/players'
import { closetApi } from '@/api/closet'
import { adminApi } from '@/api/admin'
import { getSkinUrl, getAvatarUrl, formatDate } from '@/utils/helpers'
import { renderMarkdown } from '@/utils/markdown'
import SkinAvatar from '@/components/SkinAvatar.vue'
import CapePreview2D from '@/components/CapePreview2D.vue'

const renderedAnnouncement = computed(() => renderMarkdown(announcement.value))

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const loadingRecent = ref(false)
const recentSkins = ref([])
const players = ref([])
const yggCopied = ref(false)

/** Yggdrasil API 地址 = 站点地址 + /api/yggdrasil */
const yggdrasilApi = computed(() => {
  const base = settingsStore.siteUrl || window.location.origin
  return `${base.replace(/\/$/, '')}/api/yggdrasil`
})

function copyYggdrasil() {
  navigator.clipboard?.writeText(yggdrasilApi.value).then(() => {
    yggCopied.value = true
    setTimeout(() => { yggCopied.value = false }, 2000)
  }).catch(() => {
    const ta = document.createElement('textarea')
    ta.value = yggdrasilApi.value
    document.body.appendChild(ta)
    ta.select()
    try { document.execCommand('copy'); yggCopied.value = true; setTimeout(() => { yggCopied.value = false }, 2000) } catch (e) {}
    document.body.removeChild(ta)
  })
}

function onYggDragStart(e) {
  const url = yggdrasilApi.value
  e.dataTransfer.setData('text/uri-list', url)
  e.dataTransfer.setData('text/plain', url)
  e.dataTransfer.effectAllowed = 'copy'
}

const stats = ref({
  skins: 0,
  players: 0,
  closet: 0
})

const announcement = ref('')

const roleName = computed(() => {
  const p = authStore.user?.permission
  if (p >= 2) return '超级管理员'
  if (p === 1) return '管理员'
  return '用户'
})

const roleClass = computed(() => {
  const p = authStore.user?.permission
  if (p >= 2) return 'role-admin'
  if (p === 1) return 'role-mod'
  return 'role-user'
})

onMounted(async () => {
  settingsStore.load() // 确保站点名/站点地址已加载
  try {
    const [skinData, playerData, closetData, settingsData] = await Promise.all([
      skinsApi.getList({ uploader: authStore.user?.uid, per_page: 10, sort: 'upload_at', order: 'desc' }),
      playersApi.getList().catch(() => ({ data: [] })),
      closetApi.getList().catch(() => []),
      adminApi.getSettings().catch(() => null),
    ])
    recentSkins.value = skinData.data || skinData.items || []
    players.value = (playerData.data || playerData || []).slice(0, 5)
    stats.value.skins = skinData.total || skinData.total_count || recentSkins.value.length
    stats.value.players = (playerData.data || playerData || []).length
    stats.value.closet = (closetData.data || closetData || []).length
    if (settingsData?.data?.announcement) {
      announcement.value = settingsData.data.announcement
    }
  } catch (e) {
    console.error('Failed to load dashboard data:', e)
  }
})
</script>

<style scoped>
.dashboard-page {
  padding: 30px 0 60px;
}

/* Announcement Banner */
.announcement-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 20px;
  background: linear-gradient(135deg, rgba(108, 92, 231, 0.1), rgba(162, 155, 254, 0.1));
  border: 1px solid rgba(108, 92, 231, 0.2);
  border-radius: var(--border-radius);
  margin-bottom: 20px;
}

.announcement-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  flex-shrink: 0;
}

.announcement-content {
  flex: 1;
  font-size: 14px;
  color: var(--text-primary);
  line-height: 1.6;
}

/* Yggdrasil 外置登录说明 */
.yggdrasil-card {
  background: linear-gradient(135deg, rgba(0, 184, 148, 0.08), rgba(108, 92, 231, 0.08));
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 20px 24px;
  margin-bottom: 20px;
}

.yggdrasil-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 14px;
}

.yggdrasil-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: linear-gradient(135deg, #00b894, #6c5ce7);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.yggdrasil-title h3 {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 2px;
}

.yggdrasil-title p {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}

.yggdrasil-api-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 14px;
}

.yggdrasil-label {
  font-size: 13px;
  color: var(--text-secondary);
}

.yggdrasil-code {
  font-family: var(--font-mono, monospace);
  font-size: 13px;
  color: var(--accent-primary);
  word-break: break-all;
  user-select: all;
}

.yggdrasil-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.drag-tip {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.drag-desc {
  font-size: 12px;
  color: var(--text-muted);
}

.drag-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1px dashed var(--accent-primary);
  border-radius: 8px;
  color: var(--accent-primary);
  background: rgba(108, 92, 231, 0.08);
  font-size: 13px;
  font-weight: 600;
  cursor: grab;
  user-select: none;
  transition: all 0.15s ease;
  align-self: flex-start;
}

.drag-btn:hover {
  background: rgba(108, 92, 231, 0.16);
  transform: scale(1.02);
}

.ygg-tutorial {
  font-size: 13px;
  color: var(--accent-secondary);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.ygg-tutorial:hover {
  text-decoration: underline;
}

/* User Card */
.user-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 28px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 20px;
}

.user-card-left {
  display: flex;
  align-items: center;
  gap: 18px;
}

.user-avatar {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--accent-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
  flex-shrink: 0;
}

.user-nickname {
  font-size: 20px;
  font-weight: 800;
  margin-bottom: 1px;
}

.user-email {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.user-role {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.role-user { background: rgba(0, 184, 148, 0.15); color: var(--success); }
.role-mod { background: rgba(108, 92, 231, 0.15); color: var(--accent-secondary); }
.role-admin { background: rgba(225, 112, 85, 0.15); color: var(--danger); }

/* Stats Row */
.stats-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  padding: 20px 28px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 24px;
}

.stat-item {
  flex: 1;
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 26px;
  font-weight: 800;
  background: var(--accent-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-label {
  display: block;
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
  margin-top: 2px;
}

.stat-divider {
  width: 1px;
  height: 40px;
  background: var(--border-color);
}

/* Dashboard Layout */
.dashboard-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 24px;
  align-items: start;
}

/* Panel Card */
.panel-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  overflow: hidden;
  margin-bottom: 20px;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
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

.panel-header .panel-title {
  padding: 0;
  border-bottom: none;
}

/* Quick Links */
.quick-links {
  padding: 8px;
}

.quick-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 6px;
  color: var(--text-primary);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.15s ease;
}

.quick-link:hover {
  background: rgba(108, 92, 231, 0.1);
  color: var(--accent-secondary);
}

.quick-link i {
  width: 20px;
  text-align: center;
  color: var(--text-muted);
  font-size: 16px;
}

.quick-link:hover i {
  color: var(--accent-secondary);
}

/* Sponsor Card */
.sponsor-card .panel-title i {
  color: var(--danger);
}

.sponsor-body {
  padding: 16px 20px 20px;
  text-align: center;
}

.sponsor-desc {
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 12px;
  line-height: 1.6;
}

.sponsor-link {
  display: inline-block;
  text-decoration: none;
}

.sponsor-link img {
  max-width: 100%;
  height: auto;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.2));
  transition: transform 0.2s ease;
}

.sponsor-link:hover img {
  transform: scale(1.03);
}

/* Preview */
.preview-wrap {
  line-height: 0;
}

/* Skin List */
.skin-list, .player-list {
  overflow-x: auto;
}

.list-header {
  display: flex;
  align-items: center;
  padding: 10px 20px;
  background: var(--bg-tertiary);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.list-row {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  border-top: 1px solid var(--border-color);
  font-size: 13px;
  cursor: pointer;
  transition: background 0.15s ease;
}

.list-row:hover {
  background: rgba(108, 92, 231, 0.05);
}

.list-col-preview {
  width: 40px;
  flex-shrink: 0;
}

.list-thumb {
  width: 32px;
  height: 32px;
  object-fit: contain;
  image-rendering: pixelated;
  border-radius: 4px;
  background: var(--bg-tertiary);
  border: 1px solid var(--border-color);
}

.player-head {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  background: var(--bg-tertiary);
  border-radius: 4px;
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  font-size: 14px;
}

.upload-head {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: var(--bg-tertiary);
  border-radius: 4px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.list-thumb-avatar {
  display: block;
  width: 100%;
  height: 100%;
}

.list-col-name {
  flex: 1;
  padding: 0 10px;
}

.list-skin-name {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}

.list-col-type {
  width: 60px;
  flex-shrink: 0;
}

.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.type-badge.steve, .type-badge.default {
  background: rgba(0, 184, 148, 0.15);
  color: var(--success);
}

.type-badge.alex, .type-badge.slim {
  background: rgba(253, 203, 110, 0.15);
  color: var(--warning);
}

.list-col-date {
  width: 150px;
  flex-shrink: 0;
  color: var(--text-muted);
  font-size: 12px;
}

.list-col-likes {
  width: 60px;
  flex-shrink: 0;
  text-align: right;
  color: var(--text-muted);
}

.list-col-likes i {
  color: var(--danger);
  font-size: 11px;
}

/* Responsive */
@media (max-width: 900px) {
  .dashboard-layout {
    grid-template-columns: 1fr;
  }
  .list-col-date {
    display: none;
  }
}

@media (max-width: 768px) {
  .stats-row {
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px;
  }
  .stat-item {
    min-width: 45%;
  }
  .stat-divider {
    display: none;
  }
  .user-card {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }
  .user-card-left {
    flex-direction: column;
  }
  .list-col-type {
    display: none;
  }
}
</style>