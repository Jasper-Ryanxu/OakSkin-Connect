<template>
  <div class="player-manage-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-users"></i> 角色管理</h1>
        <p>管理你的游戏角色（最多10个）</p>
      </div>

      <!-- Player Table -->
      <div class="manage-card">
        <div class="card-header">
          <h2>我的角色 ({{ players.length }})</h2>
          <button class="btn btn-primary btn-sm" @click="showAddModal = true">
            <i class="fas fa-plus"></i> 添加角色
          </button>
        </div>

        <div v-if="loading" class="loading-spinner">
          <div class="spinner"></div>
        </div>

        <div v-else-if="players.length === 0" class="empty-state">
          <i class="fas fa-user-plus"></i>
          <h3>还没有角色</h3>
          <p>添加一个 Minecraft 角色来绑定皮肤</p>
        </div>

        <div v-else class="player-table">
          <div class="table-header">
            <span class="col-name">角色名</span>
            <span class="col-skin">当前皮肤</span>
            <span class="col-date">最后更新</span>
            <span class="col-actions">操作</span>
          </div>
          <div
            v-for="player in players"
            :key="player.pid"
            class="table-row"
          >
            <span class="col-name">
              <span class="player-head">
                <SkinAvatar
                  v-if="player.skin?.hash"
                  :skinUrl="getSkinUrl(player.skin.hash)"
                  :size="30"
                />
                <i v-else class="fas fa-user head-placeholder"></i>
              </span>
              {{ player.name }}
            </span>
            <span class="col-skin">
              <span v-if="player.skin?.name" class="skin-tag">{{ player.skin.name }}</span>
              <span v-if="player.cape?.name" class="cape-tag"><i class="fas fa-flag"></i> {{ player.cape.name }}</span>
              <span v-if="!player.skin?.name && !player.cape?.name" class="text-muted">未设置</span>
            </span>
            <span class="col-date">{{ formatDate(player.last_modified) }}</span>
            <span class="col-actions">
              <button class="btn btn-sm btn-ghost" @click="editPlayer(player)" title="更换皮肤">
                <i class="fas fa-paint-brush"></i>
              </button>
              <button class="btn btn-sm btn-ghost" @click="renamePlayer(player)" title="重命名">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-danger-ghost" @click="deletePlayer(player)" title="删除">
                <i class="fas fa-trash"></i>
              </button>
            </span>
          </div>
        </div>
      </div>

      <!-- Skin Selector -->
      <div v-if="selectedPlayer" class="manage-card skin-selector-panel">
        <div class="card-header">
          <h2><i class="fas fa-paint-brush"></i> 为 {{ selectedPlayer.name }} 选择{{ selectionMode === 'cape' ? '披风' : '皮肤' }}</h2>
          <button class="btn btn-ghost btn-sm" @click="selectedPlayer = null">
            <i class="fas fa-times"></i> 取消
          </button>
        </div>

        <div class="selector-tabs">
          <button
            class="selector-tab"
            :class="{ active: selectionMode === 'skin' }"
            @click="switchMode('skin')"
          >
            <i class="fas fa-shirt"></i> 皮肤
          </button>
          <button
            class="selector-tab"
            :class="{ active: selectionMode === 'cape' }"
            @click="switchMode('cape')"
          >
            <i class="fas fa-flag"></i> 披风
          </button>
        </div>

        <div class="search-box">
          <i class="fas fa-search"></i>
          <input
            v-model="searchQuery"
            type="text"
            class="input"
            :placeholder="`搜索${selectionMode === 'cape' ? '披风' : '皮肤'}...`"
            @input="searchSkins"
          />
        </div>

        <div v-if="skinLoading" class="loading-spinner">
          <div class="spinner"></div>
        </div>

        <div v-else-if="availableSkins.length === 0" class="empty-state">
          <i class="fas fa-palette"></i>
          <h3>暂无可用皮肤</h3>
        </div>

        <div v-else class="skin-selector-grid">
          <div
            v-for="skin in availableSkins"
            :key="skin.tid"
            class="skin-option"
            :class="{ selected: selectedSkinTid === skin.tid }"
            @click="selectedSkinTid = skin.tid"
          >
            <img
              v-if="skin.type === 'cape'"
              :src="getSkinUrl(skin.hash)"
              :alt="skin.name"
              class="skin-thumb cape-thumb"
            />
            <SkinPreview2D
              v-else
              :skinUrl="getSkinUrl(skin.hash)"
              :model="skin.model || 'default'"
              :width="120"
              :height="140"
              class="skin-thumb"
            />
            <span class="skin-label">{{ skin.name }}</span>
          </div>
        </div>

        <button
          class="btn btn-primary btn-block"
          :disabled="!selectedSkinTid"
          @click="applySkin"
        >
          <i class="fas fa-check"></i> 应用{{ selectionMode === 'cape' ? '披风' : '皮肤' }}
        </button>
      </div>
    </div>

    <!-- Add Player Modal -->
    <Teleport to="body">
      <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
        <div class="modal">
          <div class="modal-header">
            <h3><i class="fas fa-plus"></i> 添加角色</h3>
            <button class="modal-close" @click="showAddModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="playerName">游戏角色名</label>
              <input
                id="playerName"
                v-model="newPlayerName"
                type="text"
                class="input"
                placeholder="输入 Minecraft 角色名"
                @keyup.enter="addPlayer"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showAddModal = false">取消</button>
            <button class="btn btn-primary" :disabled="!newPlayerName" @click="addPlayer">
              <i class="fas fa-plus"></i> 添加
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Rename Modal -->
    <Teleport to="body">
      <div v-if="showRenameModal" class="modal-overlay" @click.self="showRenameModal = false">
        <div class="modal">
          <div class="modal-header">
            <h3><i class="fas fa-edit"></i> 重命名角色</h3>
            <button class="modal-close" @click="showRenameModal = false">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="renameInput">新角色名</label>
              <input
                id="renameInput"
                v-model="renameName"
                type="text"
                class="input"
                placeholder="输入新角色名"
                @keyup.enter="confirmRename"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showRenameModal = false">取消</button>
            <button class="btn btn-primary" :disabled="!renameName" @click="confirmRename">
              <i class="fas fa-check"></i> 确认
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { playersApi } from '@/api/players'
import { skinsApi } from '@/api/skins'
import SkinPreview2D from '@/components/SkinPreview2D.vue'
import SkinAvatar from '@/components/SkinAvatar.vue'
import { getSkinUrl, formatDate, debounce } from '@/utils/helpers'

const authStore = useAuthStore()
const players = ref([])
const loading = ref(true)
const showAddModal = ref(false)
const newPlayerName = ref('')
const selectedPlayer = ref(null)
const availableSkins = ref([])
const skinLoading = ref(false)
const selectedSkinTid = ref(null)
const searchQuery = ref('')
const selectionMode = ref('skin')
const showRenameModal = ref(false)
const renamePlayerData = ref(null)
const renameName = ref('')

onMounted(async () => {
  await fetchPlayers()
})

async function fetchPlayers() {
  loading.value = true
  try {
    const data = await playersApi.getList()
    players.value = data.data || data || []
  } catch (e) {
    console.error('Failed to fetch players:', e)
  } finally {
    loading.value = false
  }
}

async function addPlayer() {
  if (!newPlayerName.value) return
  try {
    await playersApi.add(newPlayerName.value)
    newPlayerName.value = ''
    showAddModal.value = false
    await fetchPlayers()
  } catch (e) {
    console.error('Failed to add player:', e)
  }
}

function editPlayer(player) {
  selectedPlayer.value = player
  selectionMode.value = 'skin'
  selectedSkinTid.value = null
  searchQuery.value = ''
  loadSkins()
}

function switchMode(mode) {
  selectionMode.value = mode
  selectedSkinTid.value = null
  searchQuery.value = ''
  loadSkins()
}

function renamePlayer(player) {
  renamePlayerData.value = player
  renameName.value = player.name
  showRenameModal.value = true
}

async function confirmRename() {
  if (!renameName.value || !renamePlayerData.value) return
  try {
    await playersApi.rename(renamePlayerData.value.pid, renameName.value)
    showRenameModal.value = false
    renamePlayerData.value = null
    await fetchPlayers()
  } catch (e) {
    console.error('Failed to rename player:', e)
  }
}

async function loadSkins() {
  skinLoading.value = true
  try {
    const params = { per_page: 50, sort: 'upload_at', order: 'desc' }
    if (selectionMode.value === 'cape') params.type = 'cape'
    const data = await skinsApi.getList(params)
    availableSkins.value = data.data || data.items || []
  } catch (e) {
    console.error('Failed to load skins:', e)
  } finally {
    skinLoading.value = false
  }
}

const searchSkins = debounce(async () => {
  if (!searchQuery.value) {
    await loadSkins()
    return
  }
  skinLoading.value = true
  try {
    const params = { keyword: searchQuery.value, per_page: 50 }
    if (selectionMode.value === 'cape') params.type = 'cape'
    const data = await skinsApi.getList(params)
    availableSkins.value = data.data || data.items || []
  } catch (e) {
    console.error('Failed to search skins:', e)
  } finally {
    skinLoading.value = false
  }
}, 400)

async function applySkin() {
  if (!selectedPlayer.value || !selectedSkinTid.value) return
  try {
    const tid = parseInt(selectedSkinTid.value)
    let tidSkin
    let tidCape
    if (selectionMode.value === 'cape') {
      tidCape = tid
      tidSkin = selectedPlayer.value.skin?.tid || null
    } else {
      tidSkin = tid
      tidCape = selectedPlayer.value.cape?.tid || null
    }
    await playersApi.setTexture(selectedPlayer.value.pid, tidSkin, tidCape)
    selectedPlayer.value = null
    selectedSkinTid.value = null
    await fetchPlayers()
  } catch (e) {
    console.error('Failed to apply skin:', e)
  }
}

async function deletePlayer(player) {
  if (!confirm(`确定要删除角色 "${player.name}" 吗？`)) return
  try {
    await playersApi.delete(player.pid)
    await fetchPlayers()
  } catch (e) {
    console.error('Failed to delete player:', e)
  }
}
</script>

<style scoped>
.player-manage-page {
  padding-bottom: 60px;
}

.manage-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 24px;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border-color);
}

.card-header h2 {
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Player Table */
.player-table {
  overflow-x: auto;
}

.table-header {
  display: flex;
  align-items: center;
  padding: 12px 24px;
  background: var(--bg-tertiary);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.table-row {
  display: flex;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid var(--border-color);
  font-size: 14px;
  transition: background var(--transition-fast);
}

.table-row:hover {
  background: var(--bg-tertiary);
}

.col-name {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
}

.player-head {
  width: 34px;
  height: 34px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--border-color);
}

.head-placeholder {
  font-size: 14px;
  color: var(--text-muted);
}

.col-skin {
  flex: 1;
  color: var(--text-secondary);
  font-size: 13px;
}

.text-muted {
  color: var(--text-muted);
}

.skin-tag,
.cape-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  margin-right: 6px;
  margin-bottom: 2px;
}

.skin-tag {
  background: rgba(0, 184, 148, 0.15);
  color: var(--success);
}

.cape-tag {
  background: rgba(108, 92, 231, 0.15);
  color: var(--accent-primary);
}

.selector-tabs {
  display: flex;
  gap: 8px;
  padding: 0 24px;
  margin-top: 16px;
}

.selector-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--bg-tertiary);
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-fast);
  font-family: var(--font-family);
}

.selector-tab.active {
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
  color: #fff;
  border-color: transparent;
}

.cape-thumb {
  height: 140px;
  width: 120px;
  object-fit: contain;
  padding: 10px;
}

.col-date {
  width: 160px;
  color: var(--text-muted);
  font-size: 13px;
}

.col-actions {
  width: 140px;
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

/* Skin Selector */
.skin-selector-panel {
  padding: 0;
}

.search-box {
  position: relative;
  margin: 16px 24px;
}

.search-box i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
}

.search-box .input {
  padding-left: 38px;
}

.skin-selector-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  padding: 0 24px 16px;
  max-height: 360px;
  overflow-y: auto;
}

.skin-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px;
  border: 2px solid var(--border-color);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  background: linear-gradient(180deg, #2a2a5a 0%, #1e1e42 100%);
}

.skin-option:hover {
  border-color: rgba(108, 92, 231, 0.4);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.skin-option.selected {
  border-color: var(--accent-primary);
  background: linear-gradient(135deg, rgba(108, 92, 231, 0.2) 0%, rgba(162, 155, 254, 0.2) 100%);
  box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
}

.skin-thumb {
  width: 120px;
  height: 140px;
  object-fit: contain;
  image-rendering: pixelated;
  border-radius: 4px;
}

.skin-label {
  font-size: 11px;
  color: var(--text-secondary);
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

.btn-block {
  margin: 0 24px 16px;
  width: calc(100% - 48px);
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
  border-radius: var(--border-radius);
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  font-size: 18px;
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
  font-size: 18px;
  padding: 4px;
}

.modal-body {
  padding: 24px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
  .col-date {
    display: none;
  }
  .skin-selector-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>