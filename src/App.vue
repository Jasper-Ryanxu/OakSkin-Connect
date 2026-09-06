<template>
  <div id="oakskin-app" :class="{ 'dark-theme': isDarkMode }">
    <NavBar />
    <main class="main-content">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
    <Footer />
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import NavBar from '@/components/NavBar.vue'
import Footer from '@/components/Footer.vue'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const isDarkMode = computed(() => authStore.isDarkMode)

onMounted(() => {
  // 加载公开站点设置（站点名、SEO、友情链接等）
  settingsStore.load()
})
</script>

<style scoped>
#oakskin-app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--bg-primary);
  color: var(--text-primary);
  transition: background 0.3s, color 0.3s;
}

.main-content {
  flex: 1;
  padding-top: 60px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>