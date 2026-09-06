<template>
  <nav class="navbar">
    <div class="container navbar-inner">
      <div class="navbar-left">
        <router-link to="/" class="navbar-brand">
          <i class="fas fa-cube"></i>
          <span class="brand-text">{{ settingsStore.siteName }}</span>
        </router-link>
        <div class="navbar-links">
          <router-link to="/skinlib" class="nav-link">
            <i class="fas fa-palette"></i>
            皮肤库
          </router-link>
          <router-link to="/config" class="nav-link">
            <i class="fas fa-wrench"></i>
            模组配置
          </router-link>
          <router-link v-if="authStore.isLoggedIn" to="/skinlib/upload" class="nav-link">
            <i class="fas fa-upload"></i>
            上传
          </router-link>
          <router-link v-if="authStore.isLoggedIn" to="/oauth/apps" class="nav-link">
            <i class="fas fa-key"></i>
            OAuth应用
          </router-link>
        </div>
      </div>

      <div class="navbar-right">
        <template v-if="authStore.isLoggedIn">
          <div class="navbar-user" @click="showUserMenu = !showUserMenu">
            <div class="user-avatar">
              <SkinAvatar v-if="authStore.user?.uid" :skinUrl="getAvatarUrl(authStore.user.uid)" :size="32" />
              <i v-else class="fas fa-user"></i>
            </div>
            <span class="user-name">{{ authStore.nickname }}</span>
            <i class="fas fa-chevron-down" :class="{ rotated: showUserMenu }"></i>
          </div>

          <Transition name="dropdown">
            <div v-if="showUserMenu" class="user-menu" @click="showUserMenu = false">
              <router-link to="/dashboard" class="menu-item">
                <i class="fas fa-chart-pie"></i>
                仪表盘
              </router-link>
              <router-link to="/user" class="menu-item">
                <i class="fas fa-id-card"></i>
                个人中心
              </router-link>
              <router-link to="/user/player" class="menu-item">
                <i class="fas fa-users"></i>
                角色管理
              </router-link>
              <router-link to="/user/closet" class="menu-item">
                <i class="fas fa-box"></i>
                衣柜
              </router-link>
              <router-link v-if="authStore.isAdmin" to="/admin" class="menu-item">
                <i class="fas fa-shield-alt"></i>
                管理面板
              </router-link>
              <div class="menu-divider"></div>
              <button class="menu-item" @click="handleLogout">
                <i class="fas fa-sign-out-alt"></i>
                退出登录
              </button>
            </div>
          </Transition>
        </template>

        <template v-else>
          <router-link to="/auth/login" class="nav-link">
            <i class="fas fa-sign-in-alt"></i>
            登录
          </router-link>
          <router-link to="/auth/register" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i>
            注册
          </router-link>
        </template>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import SkinAvatar from '@/components/SkinAvatar.vue'
import { getAvatarUrl } from '@/utils/helpers'

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const showUserMenu = ref(false)

async function handleLogout() {
  await authStore.logout()
  router.push('/')
}
</script>

<style scoped>
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: var(--navbar-height);
  background: var(--bg-navbar);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-color);
  z-index: 1000;
}

.navbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 100%;
}

.navbar-left {
  display: flex;
  align-items: center;
  gap: 32px;
}

.navbar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 20px;
  font-weight: 800;
  color: var(--text-primary);
  text-decoration: none;
}

.navbar-brand i {
  font-size: 22px;
  color: var(--accent-primary);
}

.brand-text {
  background: var(--accent-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.navbar-links {
  display: flex;
  align-items: center;
  gap: 4px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 500;
  border-radius: var(--border-radius-sm);
  transition: all var(--transition-fast);
  text-decoration: none;
}

.nav-link:hover,
.nav-link.router-link-active {
  color: var(--text-primary);
  background: var(--bg-tertiary);
}

.navbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
}

.navbar-user {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: var(--border-radius-sm);
  cursor: pointer;
  transition: background var(--transition-fast);
}

.navbar-user:hover {
  background: var(--bg-tertiary);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  overflow: hidden;
  background: var(--accent-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 14px;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}

.fa-chevron-down {
  font-size: 10px;
  color: var(--text-muted);
  transition: transform var(--transition-fast);
}

.fa-chevron-down.rotated {
  transform: rotate(180deg);
}

.user-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 200px;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  z-index: 1001;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  color: var(--text-primary);
  font-size: 14px;
  border: none;
  background: none;
  cursor: pointer;
  text-decoration: none;
  font-family: var(--font-family);
  font-weight: 500;
  transition: all 0.15s ease;
}

.menu-item i {
  width: 18px;
  color: var(--text-muted);
}

.menu-item:hover {
  background: rgba(108, 92, 231, 0.1);
  color: var(--accent-secondary);
}

.menu-item:hover i {
  color: var(--accent-secondary);
}

.menu-divider {
  height: 1px;
  background: var(--border-color);
  margin: 4px 0;
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>