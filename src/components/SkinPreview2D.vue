<template>
  <div class="skin-preview-2d" ref="wrapperRef" :style="{ width: width + 'px', height: height + 'px' }">
    <div v-if="loading" class="preview-loading">
      <div class="spinner"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { SkinViewer } from 'skinview3d'

const props = defineProps({
  skinUrl: { type: String, default: '' },
  model: { type: String, default: 'default' },
  width: { type: Number, default: 200 },
  height: { type: Number, default: 300 }
})

const wrapperRef = ref(null)
const loading = ref(true)

let viewer = null

function initViewer() {
  if (!wrapperRef.value) return

  const canvas = document.createElement('canvas')
  canvas.style.width = '100%'
  canvas.style.height = '100%'
  wrapperRef.value.appendChild(canvas)

  viewer = new SkinViewer({
    canvas,
    width: props.width,
    height: props.height,
    skin: props.skinUrl || '',
    model: props.model === 'slim' ? 'slim' : 'default',
    background: 'transparent',
    enableControls: false,
    zoom: 0.7
  })

  // 关闭自动旋转，静态展示
  viewer.autoRotate = false

  // 背景完全透明
  viewer.background = 'transparent'

  // 相机拉远，确保角色完整显示
  viewer.camera.position.set(0, 10, 34)
  viewer.camera.lookAt(0, 10, 0)

  // 场景水平居中，角色整体向左偏移一点
  viewer.playerObject.position.x = -4

  // 角色向左旋转固定角度（绕 Y 轴），呈向左偏的视角
  viewer.playerObject.rotation.y = Math.PI / 6 // 约 +30°，向左转

  // 拉远镜头后调整角色在画布中的占比
  viewer.zoom = 0.65

  loading.value = false
}

watch(() => props.skinUrl, (newUrl) => {
  if (viewer && newUrl) {
    loading.value = true
    viewer.loadSkin(newUrl, { model: props.model === 'slim' ? 'slim' : 'default' })
      .then(() => { loading.value = false })
      .catch(() => { loading.value = false })
  }
})

watch(() => props.model, (newModel) => {
  if (viewer && props.skinUrl) {
    viewer.loadSkin(props.skinUrl, { model: newModel === 'slim' ? 'slim' : 'default' })
  }
})

onMounted(() => {
  nextTick(() => {
    initViewer()
  })
})

onUnmounted(() => {
  if (viewer) {
    viewer.dispose()
    viewer = null
  }
})
</script>

<style scoped>
.skin-preview-2d {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  background: transparent;
  border: none;
  overflow: hidden;
}

.preview-loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border-color);
  border-top-color: var(--accent-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>