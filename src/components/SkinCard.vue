<template>
  <router-link :to="`/skinlib/${skin.tid}`" class="skin-card">
    <div class="skin-preview">
      <CapePreview2D
        v-if="skin.type === 'cape'"
        :capeUrl="getSkinUrl(skin.hash)"
        :width="160"
        :height="220"
      />
      <SkinPreview2D v-else :skinUrl="getSkinUrl(skin.hash)" :width="160" :height="220" :model="skin.model || 'default'" />
      <div class="skin-overlay">
        <i class="fas fa-eye"></i>
        <span>查看详情</span>
      </div>
      <div class="skin-type-badge" :class="skin.type">
        {{ skin.type === 'alex' ? '纤细' : skin.type === 'cape' ? '披风' : '经典' }}
      </div>
    </div>
    <div class="skin-info">
      <h3 class="skin-name">{{ skin.name }}</h3>
      <div class="skin-meta">
        <span class="skin-author">
          <i class="fas fa-user"></i>
          {{ skin.owner?.nickname || '匿名' }}
        </span>
        <span class="skin-likes">
          <i class="fas fa-heart"></i>
          {{ skin.likes || 0 }}
        </span>
      </div>
    </div>
  </router-link>
</template>

<script setup>
import { getSkinUrl } from '@/utils/helpers'
import SkinPreview2D from '@/components/SkinPreview2D.vue'
import CapePreview2D from '@/components/CapePreview2D.vue'

const props = defineProps({
  skin: {
    type: Object,
    required: true
  }
})
</script>

<style scoped>
.skin-card {
  display: block;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  overflow: hidden;
  transition: all var(--transition-normal);
  cursor: pointer;
  text-decoration: none;
  color: inherit;
}

.skin-card:hover {
  transform: translateY(-4px);
  border-color: var(--accent-primary);
  box-shadow: 0 8px 30px rgba(108, 92, 231, 0.25);
}

.skin-preview {
  position: relative;
  width: 100%;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.skin-preview :deep(.skin-preview-2d) {
  width: 100%;
  border: none;
  padding: 0;
  background: transparent;
}

.skin-card:hover .skin-overlay {
  opacity: 1;
}

.skin-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  opacity: 0;
  transition: opacity var(--transition-normal);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  z-index: 2;
}

.skin-overlay i {
  font-size: 24px;
}

.skin-type-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.skin-type-badge.steve,
.skin-type-badge.default {
  background: rgba(0, 184, 148, 0.2);
  color: var(--success);
}

.skin-type-badge.alex,
.skin-type-badge.slim {
  background: rgba(253, 203, 110, 0.2);
  color: var(--warning);
}

.skin-type-badge.cape {
  background: rgba(108, 92, 231, 0.2);
  color: var(--accent-primary);
}

.cape-image {
  width: 100%;
  height: 220px;
  object-fit: contain;
  image-rendering: pixelated;
  padding: 20px;
  background: var(--bg-tertiary);
}

.skin-info {
  padding: 14px;
}

.skin-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.skin-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: var(--text-muted);
}

.skin-author,
.skin-likes {
  display: flex;
  align-items: center;
  gap: 4px;
}

.skin-likes i {
  color: var(--danger);
}
</style>