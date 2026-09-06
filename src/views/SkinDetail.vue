<template>
  <div class="skin-detail-page">
    <div class="container">
      <div v-if="loading" class="loading-spinner">
        <div class="spinner"></div>
      </div>

      <template v-else-if="skin">
        <div class="detail-layout">
          <!-- 3D Viewer -->
          <div class="detail-viewer">
            <SkinViewer3D
              :skinUrl="isCape ? '/steve.png' : skinUrl"
              :capeUrl="isCape ? skinUrl : ''"
              :model="isCape ? 'default' : (skin.model || 'default')"
              :autoRotate="true"
              :height="500"
            />
            <div class="viewer-actions">
              <template v-if="authStore.isLoggedIn">
                <button v-if="!isLiked" class="btn-primary-sm" @click="addToCloset">
                  <i class="fas fa-box"></i>
                  加入衣柜
                </button>
                <template v-else>
                  <button class="btn-danger-sm" @click="removeFromCloset">
                    <i class="fas fa-box-open"></i>
                    移除衣柜
                  </button>
                  <button class="btn-outline-sm" @click="showRename = true">
                    <i class="fas fa-pen"></i>
                    重命名
                  </button>
                  <button v-if="!isCape" class="btn-outline-sm" @click="setAsAvatar">
                    <i class="fas fa-user-circle"></i>
                    设为头像
                  </button>
                  <button v-else class="btn-outline-sm" @click="openSetCape">
                    <i class="fas fa-flag"></i>
                    设为披风
                  </button>
                </template>
              </template>
              <button class="btn-outline-sm" @click="downloadSkin">
                <i class="fas fa-download"></i>
                下载{{ isCape ? '披风' : '皮肤' }}
              </button>
            </div>
          </div>

          <!-- Skin Info -->
          <div class="detail-info">
            <div class="info-header">
              <h1 class="skin-name">{{ skin.name }}</h1>
              <div class="skin-type-badge" :class="skin.type">
                {{ skin.type === 'alex' ? '纤细模型' : skin.type === 'cape' ? '披风' : '经典模型' }}
              </div>
            </div>

            <div class="info-meta">
              <div class="meta-item">
                <i class="fas fa-user"></i>
                <span>上传者：{{ skin.owner?.nickname || '匿名' }}</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-calendar"></i>
                <span>上传时间：{{ formatDate(skin.upload_at) }}</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-heart"></i>
                <span>{{ skin.likes || 0 }} 人喜欢</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-download"></i>
                <span>{{ skin.downloads || 0 }} 次下载</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-file"></i>
                <span>大小：{{ formatFileSize(skin.size) }}</span>
              </div>
            </div>

            <div class="info-actions">
              <button class="btn btn-secondary" @click="toggleLike" :class="{ liked: isLiked }">
                <i class="fas fa-heart" :class="{ 'fas': isLiked, 'far': !isLiked }"></i>
                {{ isLiked ? '已喜欢' : '喜欢' }}
              </button>
              <button v-if="canManage" class="btn btn-danger" @click="deleteSkin">
                <i class="fas fa-trash"></i>
                删除
              </button>
            </div>

            <!-- 2D Preview -->
            <div class="preview-section">
              <h3><i class="fas fa-image"></i> 平面预览</h3>
              <div v-if="isCape" class="cape-plan">
                <CapePreview2D :capeUrl="skinUrl" :width="260" :height="360" />
                <p class="cape-plan-label">披风预览</p>
              </div>
              <SkinPreview2D v-else :skinUrl="skinUrl" :width="260" :height="360" :model="skin.model" />
            </div>
          </div>
        </div>
      </template>

      <div v-else class="empty-state">
        <i class="fas fa-exclamation-circle"></i>
        <h3>皮肤不存在</h3>
        <p>该皮肤可能已被删除或不存在</p>
        <router-link to="/skinlib" class="btn btn-primary" style="margin-top: 16px;">
          <i class="fas fa-arrow-left"></i> 返回皮肤库
        </router-link>
      </div>
    </div>

    <!-- Rename Modal -->
    <Teleport to="body">
      <div v-if="showRename" class="modal-overlay" @click.self="showRename = false">
        <div class="modal-dialog">
          <h3><i class="fas fa-pen"></i> 重命名</h3>
          <input
            v-model="renameValue"
            type="text"
            class="input"
            placeholder="输入新名称"
            maxlength="30"
            @keyup.enter="renameClosetItem"
          />
          <div class="modal-actions">
            <button class="btn btn-secondary" @click="showRename = false">取消</button>
            <button class="btn btn-primary" @click="renameClosetItem" :disabled="!renameValue.trim()">
              <i class="fas fa-check"></i> 确定
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Avatar Toast -->
    <Teleport to="body">
      <div v-if="avatarMsg" class="toast">{{ avatarMsg }}</div>
    </Teleport>

    <!-- Set Cape Modal -->
    <Teleport to="body">
      <div v-if="showSetCape" class="modal-overlay" @click.self="showSetCape = false">
        <div class="modal-dialog">
          <h3><i class="fas fa-flag"></i> 设为披风</h3>
          <p class="modal-tip">选择要佩戴此披风的角色：</p>
          <div v-if="players.length === 0" class="modal-empty">
            请先在角色管理中添加角色
          </div>
          <label
            v-for="p in players"
            :key="p.pid"
            class="player-option"
            :class="{ active: selectedPid === p.pid }"
            @click="selectedPid = p.pid"
          >
            <input type="radio" :value="p.pid" v-model="selectedPid" />
            <i class="fas fa-user"></i>
            <span class="player-option-name">{{ p.name }}</span>
            <span v-if="p.cape" class="player-option-has"><i class="fas fa-check"></i> 已佩戴</span>
          </label>
          <div class="modal-actions">
            <button class="btn btn-secondary" @click="showSetCape = false">取消</button>
            <button class="btn btn-primary" @click="setAsCape" :disabled="!selectedPid">
              <i class="fas fa-check"></i> 确定
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { skinsApi } from '@/api/skins'
import { closetApi } from '@/api/closet'
import api from '@/api/index'
import { getSkinUrl, formatDate, formatFileSize } from '@/utils/helpers'
import SkinViewer3D from '@/components/SkinViewer3D.vue'
import SkinPreview2D from '@/components/SkinPreview2D.vue'
import CapePreview2D from '@/components/CapePreview2D.vue'
import { playersApi } from '@/api/players'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const skin = ref(null)
const loading = ref(true)
const isLiked = ref(false)
const closetIds = ref([])
const showRename = ref(false)
const renameValue = ref('')
const avatarMsg = ref('')
const showSetCape = ref(false)
const selectedPid = ref(null)
const players = ref([])

const isCape = computed(() => skin.value?.type === 'cape')

const skinUrl = computed(() => {
  if (!skin.value) return ''
  return getSkinUrl(skin.value.hash)
})

const canManage = computed(() => {
  if (!skin.value || !authStore.isLoggedIn) return false
  return authStore.isAdmin || skin.value.uploader === authStore.user?.uid
})

onMounted(async () => {
  const tid = route.params.tid
  try {
    skin.value = await skinsApi.getDetail(tid)
    if (skin.value?.name) {
      document.title = `${skin.value.name} - 材质详情 | OakSkin-Connect`
    }
    if (authStore.isLoggedIn) {
      await checkCloset()
    }
  } catch (e) {
    skin.value = null
  } finally {
    loading.value = false
  }
})

async function checkCloset() {
  try {
    const data = await closetApi.getAllIds()
    closetIds.value = data || []
    isLiked.value = closetIds.value.includes(parseInt(route.params.tid))
  } catch (e) {
    // ignore
  }
}

async function toggleLike() {
  if (!authStore.isLoggedIn) {
    router.push('/auth/login')
    return
  }
  try {
    if (isLiked.value) {
      await closetApi.remove(route.params.tid)
      isLiked.value = false
      if (skin.value) skin.value.likes = Math.max(0, (skin.value.likes || 1) - 1)
    } else {
      await closetApi.add(route.params.tid)
      isLiked.value = true
      if (skin.value) skin.value.likes = (skin.value.likes || 0) + 1
    }
  } catch (e) {
    console.error('Failed to toggle like:', e)
  }
}

async function addToCloset() {
  if (!authStore.isLoggedIn) {
    router.push('/auth/login')
    return
  }
  try {
    await closetApi.add(route.params.tid)
    isLiked.value = true
  } catch (e) {
    console.error('Failed to add to closet:', e)
  }
}

async function downloadSkin() {
  if (!skin.value) return
  try {
    // skinsApi.download 经响应拦截器已直接返回 Blob 本体
    const blob = await skinsApi.download(skin.value.tid)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${skin.value.name}.png`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    // 下载计数由后端 +1，重新拉详情刷新显示
    try {
      skin.value = await skinsApi.getDetail(skin.value.tid)
    } catch (e) { /* ignore */ }
  } catch (e) {
    console.error('下载失败:', e)
    // 降级：直接打开图片
    const link = document.createElement('a')
    link.href = skinUrl.value
    link.download = `${skin.value.name}.png`
    link.target = '_blank'
    link.click()
  }
}

async function deleteSkin() {
  if (!confirm('确定要删除这个皮肤吗？')) return
  try {
    await skinsApi.delete(route.params.tid)
    router.push('/skinlib')
  } catch (e) {
    console.error('Failed to delete skin:', e)
  }
}

async function removeFromCloset() {
  try {
    await closetApi.remove(route.params.tid)
    isLiked.value = false
    if (skin.value) skin.value.likes = Math.max(0, (skin.value.likes || 1) - 1)
  } catch (e) {
    console.error('Failed to remove from closet:', e)
  }
}

async function renameClosetItem() {
  const name = renameValue.value.trim()
  if (!name) return
  try {
    await closetApi.rename(route.params.tid, name)
    showRename.value = false
    renameValue.value = ''
    if (skin.value) skin.value.name = name
  } catch (e) {
    console.error('Failed to rename:', e)
  }
}

async function setAsAvatar() {
  try {
    await api.post('/user/avatar', { tid: parseInt(route.params.tid) })
    avatarMsg.value = '头像已更新'
    setTimeout(() => { avatarMsg.value = '' }, 2000)
  } catch (e) {
    console.error('Failed to set avatar:', e)
  }
}

async function openSetCape() {
  selectedPid.value = null
  try {
    const data = await playersApi.getList()
    players.value = data.data || data || []
  } catch (e) {
    players.value = []
  }
  showSetCape.value = true
}

async function setAsCape() {
  if (!selectedPid.value) return
  try {
    await playersApi.setTexture(selectedPid.value, null, parseInt(route.params.tid))
    showSetCape.value = false
    avatarMsg.value = '披风已佩戴'
    setTimeout(() => { avatarMsg.value = '' }, 2000)
  } catch (e) {
    console.error('Failed to set cape:', e)
    avatarMsg.value = '设置失败'
    setTimeout(() => { avatarMsg.value = '' }, 2000)
  }
}
</script>

<style scoped>
.skin-detail-page {
  padding: 30px 0 60px;
}

.detail-layout {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 32px;
  align-items: start;
}

.detail-viewer {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.viewer-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.viewer-actions button {
  width: 100%;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  line-height: 1.4;
}

.btn-primary-sm {
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  color: #fff;
  box-shadow: 0 2px 8px rgba(108, 92, 231, 0.3);
}
.btn-primary-sm:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(108, 92, 231, 0.4);
}

.btn-danger-sm {
  background: linear-gradient(135deg, #e74c3c, #ff6b6b);
  color: #fff;
  box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}
.btn-danger-sm:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(231, 76, 60, 0.4);
}

.btn-outline-sm {
  background: transparent;
  color: var(--text-primary);
  border: 1px solid var(--border-color) !important;
}
.btn-outline-sm:hover {
  background: var(--bg-hover);
  border-color: var(--accent-primary) !important;
  color: var(--accent-primary);
}

.detail-info {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.info-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.skin-name {
  font-size: 24px;
  font-weight: 800;
  word-break: break-word;
}

.skin-type-badge {
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.skin-type-badge.steve,
.skin-type-badge.default {
  background: rgba(0, 184, 148, 0.15);
  color: var(--success);
}

.skin-type-badge.alex,
.skin-type-badge.slim {
  background: rgba(253, 203, 110, 0.15);
  color: var(--warning);
}

.info-meta {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--text-secondary);
}

.meta-item i {
  width: 18px;
  color: var(--accent-primary);
}

.info-actions {
  display: flex;
  gap: 12px;
}

.info-actions .liked {
  background: rgba(225, 112, 85, 0.15);
  color: var(--danger);
  border-color: var(--danger);
}

.preview-section {
  margin-top: 8px;
}

.preview-section h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.preview-section h3 i {
  color: var(--accent-primary);
}

.cape-plan {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.cape-plan-label {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}

@media (max-width: 900px) {
  .detail-layout {
    grid-template-columns: 1fr;
  }
}
</style>

<style>
/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}
.modal-dialog {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 28px;
  width: 360px;
  max-width: 90vw;
}
.modal-dialog h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.modal-dialog h3 i {
  color: var(--accent-primary);
}
.modal-dialog .input {
  margin-bottom: 16px;
}
.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}
.modal-tip {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 14px;
}
.modal-empty {
  font-size: 13px;
  color: var(--text-muted);
  padding: 14px 0;
  text-align: center;
  background: var(--bg-tertiary);
  border-radius: 6px;
  margin-bottom: 16px;
}
.player-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  margin-bottom: 8px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}
.player-option:hover {
  border-color: var(--accent-primary);
}
.player-option.active {
  border-color: var(--accent-primary);
  background: rgba(108, 92, 231, 0.1);
}
.player-option input {
  display: none;
}
.player-option i {
  color: var(--accent-primary);
}
.player-option-name {
  font-size: 14px;
  font-weight: 600;
  flex: 1;
}
.player-option-has {
  font-size: 12px;
  color: var(--success);
  display: flex;
  align-items: center;
  gap: 4px;
}
/* Toast */
.toast {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--success);
  color: #fff;
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  z-index: 2000;
  box-shadow: 0 4px 20px rgba(0, 184, 148, 0.4);
}
</style>