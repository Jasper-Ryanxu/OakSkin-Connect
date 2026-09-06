<template>
  <div class="profile-page">
    <div class="container">
      <div class="profile-header">
        <div class="profile-avatar">
          <SkinAvatar v-if="authStore.user?.uid" :skinUrl="getAvatarUrl(authStore.user.uid)" :size="80" />
          <i v-else class="fas fa-user"></i>
        </div>
        <div class="profile-info">
          <h1>{{ authStore.nickname || '用户' }}</h1>
          <p class="profile-email">{{ authStore.user?.email || '' }}</p>
          <div class="profile-stats">
            <div class="stat">
              <span class="stat-value">{{ authStore.score }}</span>
              <span class="stat-label">积分</span>
            </div>
            <div class="stat">
              <span class="stat-value">{{ playerCount }}</span>
              <span class="stat-label">角色</span>
            </div>
            <div class="stat">
              <span class="stat-value">{{ closetCount }}</span>
              <span class="stat-label">收藏</span>
            </div>
          </div>
        </div>
        <div class="profile-actions">
          <router-link to="/user/profile/edit" class="btn btn-secondary">
            <i class="fas fa-edit"></i> 编辑资料
          </router-link>
        </div>
      </div>

      <div class="profile-content">
        <div class="profile-section">
          <h2><i class="fas fa-cube"></i> 我的皮肤</h2>
          <div v-if="loading" class="loading-spinner">
            <div class="spinner"></div>
          </div>
          <div v-else-if="mySkins.length === 0" class="empty-state">
            <i class="fas fa-palette"></i>
            <h3>还没有上传皮肤</h3>
            <p>去上传你的第一个皮肤吧！</p>
            <router-link to="/skinlib/upload" class="btn btn-primary" style="margin-top: 16px;">
              <i class="fas fa-upload"></i> 上传皮肤
            </router-link>
          </div>
          <div v-else class="skin-grid">
            <SkinCard v-for="skin in mySkins" :key="skin.tid" :skin="skin" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { skinsApi } from '@/api/skins'
import { playersApi } from '@/api/players'
import { closetApi } from '@/api/closet'
import SkinCard from '@/components/SkinCard.vue'
import SkinAvatar from '@/components/SkinAvatar.vue'
import { getAvatarUrl } from '@/utils/helpers'

const authStore = useAuthStore()
const loading = ref(false)
const mySkins = ref([])
const playerCount = ref(0)
const closetCount = ref(0)

onMounted(async () => {
  loading.value = true
  try {
    const [skinData, playerData, closetData] = await Promise.all([
      skinsApi.getList({ uploader: authStore.user?.uid, per_page: 12 }),
      playersApi.getList().catch(() => ({ data: [] })),
      closetApi.getList().catch(() => [])
    ])
    mySkins.value = skinData.data || skinData.items || []
    playerCount.value = (playerData.data || playerData || []).length
    closetCount.value = (closetData.data || closetData || []).length
  } catch (e) {
    console.error('Failed to load profile data:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.profile-page {
  padding: 30px 0 60px;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 32px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 32px;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--accent-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: #fff;
  flex-shrink: 0;
}

.profile-info {
  flex: 1;
}

.profile-info h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 4px;
}

.profile-email {
  font-size: 14px;
  color: var(--text-muted);
  margin-bottom: 12px;
}

.profile-stats {
  display: flex;
  gap: 24px;
}

.stat {
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: var(--accent-secondary);
}

.stat-label {
  font-size: 12px;
  color: var(--text-muted);
}

.profile-section {
  margin-top: 32px;
}

.profile-section h2 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.profile-section h2 i {
  color: var(--accent-primary);
}

@media (max-width: 768px) {
  .profile-header {
    flex-direction: column;
    text-align: center;
  }
  .profile-stats {
    justify-content: center;
  }
}
</style>