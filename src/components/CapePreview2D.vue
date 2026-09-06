<template>
  <div class="cape-preview-2d" ref="wrapperRef" :style="{ width: width + 'px', height: height + 'px' }">
    <div v-if="loading" class="preview-loading">
      <div class="spinner"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { SkinViewer } from 'skinview3d'

const props = defineProps({
  capeUrl: { type: String, default: '' },
  width: { type: Number, default: 260 },
  height: { type: Number, default: 360 }
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
    skin: '/steve.png',
    cape: props.capeUrl || '',
    model: 'default',
    background: 'transparent',
    enableControls: false,
    zoom: 0.7
  })

  // 静态展示，不旋转
  viewer.autoRotate = false

  // 背景透明
  viewer.background = 'transparent'

  // 相机从背后看，突出披风
  viewer.camera.position.set(0, 12, -30)
  viewer.camera.lookAt(0, 12, 0)

  // 角色居中
  viewer.playerObject.position.x = 0

  // 角色正面朝前（默认），相机在背后，所以看到的是披风
  viewer.playerObject.rotation.y = 0

  viewer.zoom = 0.6

  loading.value = false
}

watch(() => props.capeUrl, (newUrl) => {
  if (viewer && newUrl) {
    loading.value = true
    viewer.loadCape(newUrl)
      .then(() => { loading.value = false })
      .catch(() => { loading.value = false })
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
.cape-preview-2d {
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