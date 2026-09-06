<template>
  <div class="slider-captcha" :class="{ verified: state === 'verified' }">
    <div class="captcha-track">
      <div class="captcha-zone"></div>
      <div class="captcha-bg" :class="{ success: state === 'verified', fail: state === 'fail' }">
        <span class="captcha-hint" v-if="state === 'idle'">
          <i class="fas fa-arrow-right"></i> 拖动滑块验证
        </span>
        <span class="captcha-hint" v-else-if="state === 'loading'">
          <i class="fas fa-spinner fa-spin"></i> 验证中...
        </span>
        <span class="captcha-hint success-text" v-else-if="state === 'verified'">
          <i class="fas fa-check-circle"></i> 验证通过
        </span>
        <span class="captcha-hint fail-text" v-else-if="state === 'fail'">
          <i class="fas fa-times-circle"></i> 验证失败，请重试
        </span>
      </div>
      <div
        class="captcha-slider"
        :class="{ dragging: isDragging }"
        :style="{ left: sliderPos + 'px' }"
        @mousedown.prevent="startDrag"
        @touchstart.prevent="startDrag"
        ref="sliderRef"
      >
        <i :class="iconClass"></i>
      </div>
    </div>
    <div class="captcha-progress" :style="{ width: progressPct + '%' }"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/api/index'

const emit = defineEmits(['verified'])

const state = ref('idle') // idle | loading | verified | fail
const isDragging = ref(false)
const sliderPos = ref(0)
const sliderRef = ref(null)
const trackWidth = ref(0)
const startX = ref(0)
const startSliderPos = ref(0)
const captchaToken = ref('')
const tokenLoading = ref(false)

const iconClass = computed(() => {
  if (state.value === 'verified') return 'fas fa-check'
  if (state.value === 'fail') return 'fas fa-times'
  return 'fas fa-chevron-right'
})

const progressPct = computed(() => {
  if (trackWidth.value === 0) return 0
  const maxTravel = trackWidth.value - 40
  if (maxTravel <= 0) return 0
  return (sliderPos.value / maxTravel) * 100
})

async function fetchToken() {
  if (tokenLoading.value) return
  tokenLoading.value = true
  try {
    const res = await api.get('/captcha/generate')
    if (res?.data?.token) {
      captchaToken.value = res.data.token
    }
  } catch (e) {
    console.error('获取验证码失败:', e)
  } finally {
    tokenLoading.value = false
  }
}

onMounted(() => {
  fetchToken()
  // 组件挂载后获取 track 宽度
  setTimeout(() => {
    if (sliderRef.value?.parentElement) {
      trackWidth.value = sliderRef.value.parentElement.offsetWidth || 300
    }
  }, 100)
})

async function startDrag(e) {
  if (state.value !== 'idle') return
  isDragging.value = true

  const track = sliderRef.value?.parentElement
  trackWidth.value = track?.offsetWidth || 300

  const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX
  startX.value = clientX
  startSliderPos.value = sliderPos.value

  // 如果还没有 token，再次获取
  if (!captchaToken.value) {
    await fetchToken()
  }

  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', endDrag)
  document.addEventListener('touchmove', onDrag, { passive: false })
  document.addEventListener('touchend', endDrag)
}

function onDrag(e) {
  if (!isDragging.value) return
  const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX
  const delta = clientX - startX.value
  let newPos = startSliderPos.value + delta
  if (newPos < 0) newPos = 0
  if (newPos > trackWidth.value - 40) newPos = trackWidth.value - 40
  sliderPos.value = newPos
}

async function endDrag() {
  if (!isDragging.value) return
  isDragging.value = false

  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', endDrag)
  document.removeEventListener('touchmove', onDrag)
  document.removeEventListener('touchend', endDrag)

  if (!captchaToken.value) {
    state.value = 'fail'
    setTimeout(() => reset(), 1000)
    return
  }

  state.value = 'loading'

  // 计算百分比位置（基于滑块可移动范围）
  const pct = Math.round(progressPct.value)

  try {
    const res = await api.post('/captcha/verify', {
      token: captchaToken.value,
      position: pct
    })

    if (res?.code === 0) {
      state.value = 'verified'
      emit('verified', res.data?.token || 'captcha_ok')
    } else {
      state.value = 'fail'
      setTimeout(() => reset(), 1200)
    }
  } catch (e) {
    state.value = 'fail'
    setTimeout(() => reset(), 1200)
  }
}

function reset() {
  state.value = 'idle'
  sliderPos.value = 0
  captchaToken.value = ''
  // 重新获取 token
  fetchToken()
}
</script>

<style scoped>
.slider-captcha {
  width: 100%;
  user-select: none;
  margin-bottom: 16px;
}

.captcha-track {
  position: relative;
  height: 40px;
  background: var(--bg-secondary, #2a2a3e);
  border: 1px solid var(--border-color, #3a3a5e);
  border-radius: 4px;
  overflow: hidden;
}

.captcha-zone {
  position: absolute;
  top: 0;
  right: 0;
  width: 30%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(108, 92, 231, 0.08));
  border-left: 1px dashed rgba(108, 92, 231, 0.2);
  pointer-events: none;
  z-index: 0;
}

.captcha-bg {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.3s ease;
  z-index: 1;
}

.captcha-bg.success {
  background: rgba(0, 184, 148, 0.15);
}

.captcha-bg.fail {
  background: rgba(231, 76, 60, 0.15);
}

.captcha-hint {
  font-size: 13px;
  color: var(--text-muted, #888);
  display: flex;
  align-items: center;
  gap: 6px;
}

.captcha-hint i {
  font-size: 12px;
}

.success-text {
  color: var(--success, #00b894);
  font-weight: 600;
}

.fail-text {
  color: var(--danger, #e74c3c);
  font-weight: 600;
}

.captcha-slider {
  position: absolute;
  top: -1px;
  left: 0;
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #6c5ce7, #a29bfe);
  border: 1px solid transparent;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  z-index: 2;
  transition: box-shadow 0.2s ease;
  color: #fff;
  font-size: 14px;
}

.captcha-slider:hover {
  box-shadow: 0 0 12px rgba(108, 92, 231, 0.5);
}

.captcha-slider.dragging {
  cursor: grabbing;
  box-shadow: 0 0 16px rgba(108, 92, 231, 0.6);
}

.captcha-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 2px;
  background: linear-gradient(90deg, #6c5ce7, #a29bfe);
  transition: width 0.05s linear;
  border-radius: 0 0 0 4px;
  pointer-events: none;
  z-index: 3;
}
</style>