<template>
  <canvas ref="canvasRef" class="skin-avatar" :style="{ width: size + 'px', height: size + 'px' }"></canvas>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue'

const props = defineProps({
  skinUrl: { type: String, default: '' },
  size: { type: Number, default: 64 }
})

const canvasRef = ref(null)

function render() {
  const canvas = canvasRef.value
  if (!canvas || !props.skinUrl) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const img = new Image()
  img.crossOrigin = 'anonymous'

  img.onload = () => {
    const s = props.size
    canvas.width = s
    canvas.height = s
    ctx.clearRect(0, 0, s, s)
    ctx.imageSmoothingEnabled = false

    // 裁取头部 (8,8,8,8) 并缩放
    ctx.drawImage(img, 8, 8, 8, 8, 0, 0, s, s)

    // 如果有覆盖层 (64x64 格式)，叠加头部覆盖层
    if (img.height === 64) {
      ctx.drawImage(img, 40, 8, 8, 8, 0, 0, s, s)
    }
  }

  img.onerror = () => {
    const s = props.size
    canvas.width = s
    canvas.height = s
    ctx.fillStyle = '#1a1a3e'
    ctx.fillRect(0, 0, s, s)
    ctx.fillStyle = '#6c5ce7'
    ctx.font = `${s * 0.5}px sans-serif`
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('?', s / 2, s / 2)
  }

  img.src = props.skinUrl
}

watch(() => props.skinUrl, () => nextTick(render))
onMounted(() => nextTick(render))
</script>

<style scoped>
.skin-avatar {
  border-radius: 0;
  image-rendering: pixelated;
  display: block;
}
</style>