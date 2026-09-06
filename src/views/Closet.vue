<template>
  <div class="closet-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-box"></i> 我的衣柜</h1>
        <p>收藏你喜欢的皮肤</p>
      </div>

      <div v-if="loading" class="loading-spinner">
        <div class="spinner"></div>
      </div>

      <div v-else-if="closetItems.length === 0" class="empty-state">
        <i class="fas fa-box-open"></i>
        <h3>衣柜是空的</h3>
        <p>去皮肤库浏览并收藏你喜欢的皮肤吧！</p>
        <router-link to="/skinlib" class="btn btn-primary" style="margin-top: 16px;">
          <i class="fas fa-palette"></i> 浏览皮肤库
        </router-link>
      </div>

      <div v-else class="closet-grid">
        <div v-for="item in closetItems" :key="item.tid" class="closet-item">
          <router-link :to="`/skinlib/${item.tid}`" class="closet-preview">
            <CapePreview2D
              v-if="item.type === 'cape'"
              :capeUrl="getSkinUrl(item.hash)"
              :width="160"
              :height="160"
            />
            <SkinPreview2D
              v-else
              :skinUrl="getSkinUrl(item.hash)"
              :width="160"
              :height="160"
              :model="item.type === 'alex' ? 'slim' : 'default'"
            />
            <div class="closet-overlay">
              <i class="fas fa-eye"></i>
            </div>
          </router-link>
          <div class="closet-info">
            <p class="closet-name">{{ item.name }}</p>
            <p class="closet-item-name">{{ item.pivot?.item_name || '' }}</p>
            <button class="btn btn-danger btn-sm" @click="removeFromCloset(item.tid)">
              <i class="fas fa-trash"></i> 移除
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { closetApi } from '@/api/closet'
import { getSkinUrl } from '@/utils/helpers'
import SkinPreview2D from '@/components/SkinPreview2D.vue'
import CapePreview2D from '@/components/CapePreview2D.vue'

const loading = ref(true)
const closetItems = ref([])

onMounted(async () => {
  loading.value = true
  try {
    const data = await closetApi.getList()
    closetItems.value = data.data || data || []
  } catch (e) {
    console.error('Failed to load closet:', e)
  } finally {
    loading.value = false
  }
})

async function removeFromCloset(tid) {
  try {
    await closetApi.remove(tid)
    closetItems.value = closetItems.value.filter(item => item.tid !== tid)
  } catch (e) {
    console.error('Failed to remove from closet:', e)
  }
}
</script>

<style scoped>
.closet-page {
  padding-bottom: 60px;
}

.closet-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 20px;
}

.closet-item {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  overflow: hidden;
  transition: all var(--transition-normal);
}

.closet-item:hover {
  transform: translateY(-2px);
  border-color: var(--accent-primary);
  box-shadow: var(--shadow-glow);
}

.closet-preview {
  display: block;
  position: relative;
  aspect-ratio: 1;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.closet-img {
  max-width: 80%;
  max-height: 80%;
  image-rendering: pixelated;
  transition: transform var(--transition-normal);
}

.closet-item:hover .closet-img {
  transform: scale(1.1);
}

.closet-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity var(--transition-normal);
  color: #fff;
  font-size: 24px;
}

.closet-item:hover .closet-overlay {
  opacity: 1;
}

.closet-info {
  padding: 12px;
  text-align: center;
}

.closet-name {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.closet-item-name {
  font-size: 11px;
  color: var(--text-muted);
  margin-bottom: 8px;
}
</style>