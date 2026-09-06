<template>
  <div class="home-page">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="hero-bg">
        <div class="hero-particles"></div>
        <div class="hero-glow-1"></div>
        <div class="hero-glow-2"></div>
      </div>
      <div class="container hero-content">
        <div class="hero-text fade-in">
          <div class="hero-badge">
            <i class="fas fa-cube"></i>
            <span>OakSkin Connect v1.0</span>
          </div>
          <h1 class="hero-title">
            绽放你的<br/><span class="highlight">个性皮肤</span>
          </h1>
          <p class="hero-desc">
            上传、管理、展示你的 Minecraft 皮肤，<br/>
            让每一个角色都与众不同。
          </p>
          <div class="hero-actions">
            <router-link to="/skinlib" class="btn btn-primary btn-lg">
              <i class="fas fa-palette"></i>
              浏览皮肤库
            </router-link>
            <router-link v-if="!authStore.isLoggedIn" to="/auth/register" class="btn btn-secondary btn-lg">
              <i class="fas fa-user-plus"></i>
              立即注册
            </router-link>
          </div>
          <div class="hero-trust">
            <div class="trust-item">
              <i class="fas fa-check-circle"></i>
              <span>免费使用</span>
            </div>
            <div class="trust-item">
              <i class="fas fa-check-circle"></i>
              <span>3D 预览</span>
            </div>
            <div class="trust-item">
              <i class="fas fa-check-circle"></i>
              <span>社区分享</span>
            </div>
          </div>
        </div>
        <div class="hero-preview fade-in">
          <div class="preview-frame">
            <div class="preview-glow"></div>
            <SkinViewer3D
              :skinUrl="currentSkinUrl"
              :autoRotate="true"
              :height="520"
              background="#0a0a1a"
            />
          </div>
          <div class="preview-badges">
            <div class="badge-item">
              <i class="fas fa-rotate"></i>
              <span>拖拽旋转</span>
            </div>
            <div class="badge-item">
              <i class="fas fa-mouse"></i>
              <span>滚轮缩放</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="container">
        <div class="section-header">
          <div class="section-tag">
            <i class="fas fa-star"></i>
            <span>核心功能</span>
          </div>
          <h2>为 Minecraft 玩家量身打造</h2>
          <p>一站式皮肤管理工具，让创作和分享更加简单</p>
        </div>
        <div class="features-grid">
          <div class="feature-card" v-for="feature in features" :key="feature.title">
            <div class="feature-icon">
              <i :class="feature.icon"></i>
            </div>
            <h3>{{ feature.title }}</h3>
            <p>{{ feature.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Recent Skins Section -->
    <section class="recent-section">
      <div class="container">
        <div class="section-header">
          <div class="section-tag">
            <i class="fas fa-clock"></i>
            <span>最新皮肤</span>
          </div>
          <h2>社区最新作品</h2>
          <p>发现玩家们最新上传的精彩皮肤</p>
        </div>

        <div v-if="loading" class="loading-spinner">
          <div class="spinner"></div>
        </div>

        <div v-else-if="recentSkins.length === 0" class="empty-state">
          <i class="fas fa-palette"></i>
          <h3>暂无皮肤</h3>
          <p>还没有上传任何皮肤，快来成为第一个吧！</p>
        </div>

        <div v-else class="skin-grid">
          <SkinCard v-for="skin in recentSkins" :key="skin.tid" :skin="skin" />
        </div>

        <div class="section-footer">
          <router-link to="/skinlib" class="btn btn-secondary btn-lg">
            <i class="fas fa-arrow-right"></i>
            查看全部皮肤
          </router-link>
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
      <div class="container">
        <div class="stats-grid">
          <div class="stat-item" v-for="stat in stats" :key="stat.label">
            <div class="stat-value">{{ stat.value }}</div>
            <div class="stat-label">{{ stat.label }}</div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { skinsApi } from '@/api/skins'
import SkinViewer3D from '@/components/SkinViewer3D.vue'
import SkinCard from '@/components/SkinCard.vue'

const authStore = useAuthStore()
const loading = ref(false)
const recentSkins = ref([])
const currentSkinUrl = ref('/example.png')

const features = [
  {
    icon: 'fas fa-cloud-upload-alt',
    title: '皮肤上传',
    desc: '支持 Steve 和 Alex 两种模型，轻松上传你的个性皮肤。'
  },
  {
    icon: 'fas fa-cube',
    title: '3D 预览',
    desc: '使用 Three.js 实现的高质量 3D 皮肤渲染，支持旋转、缩放和动画。'
  },
  {
    icon: 'fas fa-users',
    title: '角色管理',
    desc: '管理多个游戏角色，为每个角色分配不同的皮肤和披风。'
  },
  {
    icon: 'fas fa-box',
    title: '个人衣柜',
    desc: '收藏你喜欢的皮肤，随时为你的角色更换装扮。'
  },
  {
    icon: 'fas fa-shield-alt',
    title: '安全可靠',
    desc: '完善的权限管理，保护你的创作成果。'
  },
  {
    icon: 'fas fa-paint-brush',
    title: '丰富资源',
    desc: '来自社区的丰富皮肤资源，总有适合你的那一款。'
  }
]

const stats = ref([
  { value: '0', label: '皮肤总数' },
  { value: '0', label: '注册用户' },
  { value: '0', label: '今日上传' },
  { value: '0', label: '下载总数' }
])

onMounted(async () => {
  loading.value = true
  try {
    const [skinData, statData] = await Promise.all([
      skinsApi.getList({ page: 1, per_page: 8, sort: 'upload_at', order: 'desc' }),
      skinsApi.getStats().catch(() => null)
    ])
    recentSkins.value = skinData.data || skinData.items || []
    if (recentSkins.value.length > 0) {
      currentSkinUrl.value = `/textures/${recentSkins.value[0].hash}`
    }
    if (statData?.data) {
      stats.value[0].value = String(statData.data.skins ?? 0)
      stats.value[1].value = String(statData.data.users ?? 0)
      stats.value[2].value = String(statData.data.today_uploads ?? 0)
      stats.value[3].value = String(statData.data.downloads ?? 0)
    }
  } catch (e) {
    console.error('Failed to load recent skins:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
/* ===== Hero Section ===== */
.hero-section {
  position: relative;
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 15% 50%, rgba(108, 92, 231, 0.12) 0%, transparent 50%),
    radial-gradient(ellipse at 85% 50%, rgba(162, 155, 254, 0.08) 0%, transparent 50%);
  z-index: 0;
}

.hero-particles {
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(1px 1px at 10% 20%, rgba(108, 92, 231, 0.3) 0%, transparent 100%),
    radial-gradient(1px 1px at 30% 60%, rgba(162, 155, 254, 0.3) 0%, transparent 100%),
    radial-gradient(1px 1px at 50% 10%, rgba(108, 92, 231, 0.3) 0%, transparent 100%),
    radial-gradient(1px 1px at 70% 80%, rgba(162, 155, 254, 0.3) 0%, transparent 100%),
    radial-gradient(1px 1px at 90% 40%, rgba(108, 92, 231, 0.3) 0%, transparent 100%);
  background-size: 200px 200px;
}

.hero-glow-1 {
  position: absolute;
  top: -20%;
  right: 10%;
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(108, 92, 231, 0.08) 0%, transparent 70%);
  border-radius: 50%;
  filter: blur(60px);
}

.hero-glow-2 {
  position: absolute;
  bottom: -10%;
  left: 5%;
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(162, 155, 254, 0.06) 0%, transparent 70%);
  border-radius: 50%;
  filter: blur(50px);
}

.hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
  padding: 80px 0;
  width: 100%;
}

.hero-text {
  flex: 0 0 auto;
  width: 480px;
  max-width: 45%;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background: rgba(108, 92, 231, 0.1);
  border: 1px solid rgba(108, 92, 231, 0.2);
  border-radius: 6px;
  font-size: 12px;
  color: var(--accent-secondary);
  font-weight: 600;
  margin-bottom: 24px;
  letter-spacing: 0.5px;
}

.hero-badge i {
  font-size: 11px;
}

.hero-title {
  font-size: 52px;
  font-weight: 800;
  line-height: 1.15;
  margin-bottom: 20px;
  letter-spacing: -1px;
}

.hero-title .highlight {
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 50%, #6c5ce7 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  background-size: 200% auto;
}

.hero-desc {
  font-size: 16px;
  color: var(--text-secondary);
  line-height: 1.8;
  margin-bottom: 32px;
}

.hero-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 32px;
}

.hero-trust {
  display: flex;
  gap: 24px;
}

.trust-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 500;
}

.trust-item i {
  color: #00b894;
  font-size: 14px;
}

/* ===== Hero Preview ===== */
.hero-preview {
  flex: 0 0 auto;
  width: 500px;
  max-width: 50%;
}

.preview-frame {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  background: #0a0a1a;
  border: 1px solid rgba(108, 92, 231, 0.15);
  box-shadow:
    0 20px 60px rgba(0, 0, 0, 0.4),
    0 0 80px rgba(108, 92, 231, 0.05);
}

.preview-glow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, rgba(108, 92, 231, 0.1) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
  z-index: 0;
}

.preview-badges {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 16px;
}

.badge-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(108, 92, 231, 0.08);
  border: 1px solid rgba(108, 92, 231, 0.12);
  border-radius: 6px;
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 500;
}

.badge-item i {
  color: var(--accent-secondary);
  font-size: 11px;
}

/* ===== Features Section ===== */
.features-section {
  padding: 80px 0;
  background: var(--bg-secondary);
}

.section-header {
  text-align: center;
  margin-bottom: 50px;
}

.section-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 14px;
  background: rgba(108, 92, 231, 0.1);
  border: 1px solid rgba(108, 92, 231, 0.15);
  border-radius: 6px;
  font-size: 12px;
  color: var(--accent-secondary);
  font-weight: 600;
  margin-bottom: 16px;
  letter-spacing: 0.5px;
}

.section-tag i {
  font-size: 11px;
}

.section-header h2 {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 8px;
}

.section-header p {
  color: var(--text-secondary);
  font-size: 16px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.feature-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 32px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.feature-card:hover {
  transform: translateY(-6px);
  border-color: rgba(108, 92, 231, 0.3);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
}

.feature-icon {
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(108, 92, 231, 0.15) 0%, rgba(162, 155, 254, 0.15) 100%);
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 22px;
  color: var(--accent-secondary);
}

.feature-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.feature-card p {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.6;
}

/* ===== Recent Section ===== */
.recent-section {
  padding: 80px 0;
}

.section-footer {
  text-align: center;
  margin-top: 40px;
}

/* ===== Stats Section ===== */
.stats-section {
  padding: 60px 0;
  background: var(--bg-secondary);
  border-top: 1px solid var(--border-color);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  text-align: center;
}

.stat-value {
  font-size: 42px;
  font-weight: 800;
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: var(--text-muted);
  font-weight: 500;
}

/* ===== Responsive ===== */
@media (max-width: 1100px) {
  .hero-content {
    flex-direction: column;
    text-align: center;
    gap: 48px;
  }
  .hero-text {
    width: 100%;
    max-width: 600px;
  }
  .hero-preview {
    width: 100%;
    max-width: 480px;
  }
  .hero-actions {
    justify-content: center;
  }
  .hero-title {
    justify-content: center;
  }
  .hero-trust {
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .hero-section {
    min-height: auto;
    padding: 60px 0;
  }
  .hero-title {
    font-size: 36px;
  }
  .hero-desc br {
    display: none;
  }
  .hero-actions {
    flex-direction: column;
    align-items: center;
  }
  .hero-trust {
    flex-wrap: wrap;
    gap: 16px;
  }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .recent-section {
    padding: 50px 0;
  }
}
</style>