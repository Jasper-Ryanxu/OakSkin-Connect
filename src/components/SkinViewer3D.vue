<template>
  <div class="skin-viewer-3d" ref="wrapperRef" :style="{ height: typeof height === 'number' ? height + 'px' : height }">
    <div class="viewer-toolbar">
      <button
        class="toolbar-btn"
        @click="toggleRotation"
        :title="isRotating ? '停止旋转' : '开始旋转'"
      >
        <i :class="isRotating ? 'fas fa-pause' : 'fas fa-play'"></i>
      </button>
      <button
        class="toolbar-btn"
        @click="toggleWalk"
        :title="isWalking ? '切换到跑动' : '切换到行走'"
      >
        <i :class="isWalking ? 'fas fa-walking' : 'fas fa-running'"></i>
      </button>
      <button class="toolbar-btn" @click="togglePause" :title="isPaused ? '恢复' : '暂停'">
        <i :class="isPaused ? 'fas fa-play' : 'fas fa-pause'"></i>
      </button>
      <button class="toolbar-btn" @click="resetViewer" title="重置">
        <i class="fas fa-stop"></i>
      </button>
    </div>
    <div class="bg-picker">
      <span
        class="bg-dot"
        :class="{ active: currentBg === 'white' }"
        style="background:#fff"
        title="白色背景"
        @click="setBackground('white')"
      ></span>
      <span
        class="bg-dot"
        :class="{ active: currentBg === 'gray' }"
        style="background:#6c757d"
        title="灰色背景"
        @click="setBackground('gray')"
      ></span>
      <span
        class="bg-dot"
        :class="{ active: currentBg === 'black' }"
        style="background:#000"
        title="黑色背景"
        @click="setBackground('black')"
      ></span>
    </div>
    <div class="viewer-info">
      <span class="info-hint">
        <i class="fas fa-mouse-pointer"></i> 拖拽旋转 | 滚轮缩放
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import {
  SkinViewer,
  WalkingAnimation,
  RunningAnimation
} from 'skinview3d'

const props = defineProps({
  skinUrl: { type: String, default: '' },
  capeUrl: { type: String, default: '' },
  model: { type: String, default: 'default' },
  autoRotate: { type: Boolean, default: true },
  background: { type: String, default: '' },
  height: { type: [Number, String], default: 400 }
})

const wrapperRef = ref(null)
const isRotating = ref(true)
const isWalking = ref(true)
const isPaused = ref(false)
const currentBg = ref('gray')

let viewer = null
let currentAnim = null

// 参考 BS：默认缩小到 70%（initPositionZ = 70）
const BS_ZOOM = 0.7
const BS_GRAY = '#6c757d'
const DEFAULT_SKIN = '/steve.png'

function initViewer() {
  if (!wrapperRef.value) return

  const canvas = document.createElement('canvas')
  canvas.style.width = '100%'
  canvas.style.height = '100%'
  wrapperRef.value.appendChild(canvas)

  // 与 BS 一致：画布铺满容器
  const width = wrapperRef.value.clientWidth || 300
  const height = typeof props.height === 'number' ? props.height : 400

  viewer = new SkinViewer({
    canvas,
    width,
    height,
    skin: props.skinUrl || DEFAULT_SKIN,
    cape: props.capeUrl || '',
    model: props.model === 'slim' ? 'slim' : 'default',
    zoom: BS_ZOOM,
    background: props.background || BS_GRAY,
    enableControls: true
  })

  // 与 BS 一致：持续旋转（对应 BS 添加 ROTATION 动画）
  viewer.autoRotate = props.autoRotate
  viewer.autoRotateSpeed = 0.4
  isRotating.value = props.autoRotate
  currentBg.value = props.background ? 'custom' : 'gray'

  // 与 BS 一致：默认播放行走动画
  currentAnim = new WalkingAnimation()
  viewer.animation = currentAnim
  isWalking.value = true
}

function toggleRotation() {
  isRotating.value = !isRotating.value
  if (viewer) viewer.autoRotate = isRotating.value
}

function toggleWalk() {
  if (!viewer) return
  isWalking.value = !isWalking.value
  currentAnim = isWalking.value ? new WalkingAnimation() : new RunningAnimation()
  viewer.animation = currentAnim
}

function togglePause() {
  isPaused.value = !isPaused.value
  if (viewer) viewer.renderPaused = isPaused.value
}

function refreshAnim() {
  // 暂停或暂停时保留当前动画类型
  if (isWalking.value) currentAnim = new WalkingAnimation()
  else currentAnim = new RunningAnimation()
  if (viewer) viewer.animation = currentAnim
}

function resetViewer() {
  // 与 BS reset 一致：清除动画并暂停
  if (viewer) {
    viewer.animation = null
    viewer.autoRotate = false
    isRotating.value = false
    viewer.resetCameraPose()
    viewer.playerObject.resetJoints()
    isPaused.value = true
    viewer.renderPaused = true
  }
}

function setBackground(name) {
  if (!viewer) return
  const map = { white: '#ffffff', gray: BS_GRAY, black: '#000000' }
  viewer.background = map[name] || BS_GRAY
  currentBg.value = name
}

function onResize() {
  if (!viewer || !wrapperRef.value) return
  const rect = wrapperRef.value.getBoundingClientRect()
  if (rect.width > 0 && rect.height > 0) {
    viewer.setSize(rect.width, rect.height)
  }
}

watch(() => props.skinUrl, (newUrl) => {
  if (viewer) {
    viewer.loadSkin(newUrl || DEFAULT_SKIN, { model: props.model === 'slim' ? 'slim' : 'default' })
  }
})

watch(() => props.model, (newModel) => {
  if (viewer && props.skinUrl) {
    viewer.loadSkin(props.skinUrl, { model: newModel === 'slim' ? 'slim' : 'default' })
  }
})

watch(() => props.capeUrl, (newUrl) => {
  if (!viewer) return
  if (newUrl) viewer.loadCape(newUrl)
  else viewer.resetCape()
})

watch(() => props.background, (newBg) => {
  if (viewer && newBg) {
    viewer.background = newBg
    currentBg.value = 'custom'
  }
})

onMounted(() => {
  nextTick(() => {
    initViewer()
    setTimeout(onResize, 60)
    window.addEventListener('resize', onResize)
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  if (viewer) {
    viewer.animation = null
    viewer.dispose()
    viewer = null
  }
})
</script>

<style scoped>
.skin-viewer-3d {
  position: relative;
  width: 100%;
  border-radius: var(--border-radius);
  overflow: hidden;
  background: #6c757d;
  border: 1px solid var(--border-color);
}

.skin-viewer-3d :deep(canvas) {
  display: block;
}

.viewer-toolbar {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  z-index: 10;
}

.toolbar-btn {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, rgba(42, 42, 90, 0.8) 0%, rgba(30, 30, 66, 0.8) 100%);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  color: #e8e8f0;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 13px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.toolbar-btn:hover {
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  border-color: transparent;
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
}

.toolbar-btn:active {
  transform: translateY(0px) scale(0.95);
}

.bg-picker {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 10;
  display: flex;
  gap: 6px;
  padding: 6px;
  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  backdrop-filter: blur(8px);
}

.bg-dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid rgba(255, 255, 255, 0.2);
  transition: transform var(--transition-fast);
}

.bg-dot:hover {
  transform: scale(1.1);
}

.bg-dot.active {
  border-color: var(--accent-primary);
  box-shadow: 0 0 0 2px var(--accent-glow);
}

.viewer-info {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10;
}

.info-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
  background: rgba(0, 0, 0, 0.3);
  padding: 4px 12px;
  border-radius: 8px;
  backdrop-filter: blur(10px);
}
</style>