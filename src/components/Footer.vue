<template>
  <footer class="footer">
    <div class="container footer-inner">
      <div class="footer-left">
        <div class="footer-brand">
          <i class="fas fa-cube"></i>
          <span>{{ settingsStore.siteName }}</span>
        </div>
        <p class="footer-desc">
          一个现代化的 Minecraft 皮肤管理平台，为玩家提供优质的皮肤服务。
        </p>
      </div>
      <div class="footer-links">
        <div class="footer-section">
          <h4>导航</h4>
          <router-link to="/skinlib">皮肤库</router-link>
          <router-link to="/skinlib/upload">上传皮肤</router-link>
        </div>
        <div class="footer-section" v-if="settingsStore.friendLinks.length">
          <h4>友情链接</h4>
          <a v-for="(fl, i) in settingsStore.friendLinks" :key="i" :href="fl.url" target="_blank" rel="noopener nofollow">
            {{ fl.name }}
          </a>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; {{ year }} {{ settingsStore.siteName }}. All rights reserved.</p>
        <p class="footer-powered">由 <strong>OakSkin-Connect</strong> 强力驱动。</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'

const year = new Date().getFullYear()
const settingsStore = useSettingsStore()
</script>

<style scoped>
.footer {
  background: var(--bg-secondary);
  border-top: 1px solid var(--border-color);
  padding: 40px 0 20px;
  margin-top: 60px;
}

.footer-inner {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}

.footer-left {
  max-width: 300px;
}

.footer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 12px;
}

.footer-brand i {
  color: var(--accent-primary);
  font-size: 20px;
}

.footer-desc {
  color: var(--text-muted);
  font-size: 13px;
  line-height: 1.6;
}

.footer-links {
  display: flex;
  gap: 40px;
}

.footer-section h4 {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  margin-bottom: 12px;
}

.footer-section a {
  display: block;
  color: var(--text-muted);
  font-size: 13px;
  padding: 4px 0;
  transition: color var(--transition-fast);
  text-decoration: none;
}

.footer-section a:hover {
  color: var(--accent-primary);
}

.footer-bottom {
  width: 100%;
  text-align: center;
  padding-top: 20px;
  margin-top: 20px;
  border-top: 1px solid var(--border-color);
}

.footer-bottom p {
  font-size: 12px;
  color: var(--text-muted);
}

.footer-bottom p + p {
  margin-top: 4px;
}

.footer-powered strong {
  color: var(--accent-primary);
}

@media (max-width: 768px) {
  .footer-inner {
    flex-direction: column;
    gap: 30px;
  }
  .footer-links {
    gap: 30px;
  }
}
</style>