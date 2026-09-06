import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home.vue')
  },
  {
    path: '/auth/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/auth/register',
    name: 'Register',
    component: () => import('@/views/Register.vue'),
    meta: { guest: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { auth: true }
  },
  {
    path: '/skinlib',
    name: 'SkinLibrary',
    component: () => import('@/views/SkinLibrary.vue')
  },
  {
    path: '/skinlib/upload',
    name: 'UploadSkin',
    component: () => import('@/views/Upload.vue'),
    meta: { auth: true }
  },
  {
    path: '/skinlib/:tid',
    name: 'SkinDetail',
    component: () => import('@/views/SkinDetail.vue')
  },
  {
    path: '/user',
    name: 'Profile',
    component: () => import('@/views/Profile.vue'),
    meta: { auth: true }
  },
  {
    path: '/user/profile/edit',
    name: 'ProfileEdit',
    component: () => import('@/views/ProfileEdit.vue'),
    meta: { auth: true }
  },
  {
    path: '/user/player',
    name: 'PlayerManage',
    component: () => import('@/views/PlayerManage.vue'),
    meta: { auth: true }
  },
  {
    path: '/user/closet',
    name: 'Closet',
    component: () => import('@/views/Closet.vue'),
    meta: { auth: true }
  },
  {
    path: '/admin',
    name: 'Admin',
    component: () => import('@/views/Admin.vue'),
    meta: { auth: true, admin: true }
  },
  {
    path: '/oauth/authorize',
    name: 'OAuthAuthorize',
    component: () => import('@/views/OAuthAuthorize.vue')
  },
  {
    path: '/oauth/apps',
    name: 'OAuthApps',
    component: () => import('@/views/OAuthApps.vue'),
    meta: { auth: true }
  },
  {
    path: '/config',
    name: 'ConfigGenerator',
    component: () => import('@/views/ConfigGenerator.vue')
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/Forbidden.vue')
  },
  {
    path: '/setup',
    name: 'Setup',
    component: () => import('@/views/Setup.vue')
  },
  {
    path: '/500',
    name: 'ServerError',
    component: () => import('@/views/ServerError.vue')
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound.vue')
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // 需要登录但未登录
  if (to.meta.auth && !authStore.isLoggedIn) {
    return next({ name: 'Login', query: { redirect: to.fullPath } })
  }

  // 已登录用户访问游客页面（登录/注册）
  if (to.meta.guest && authStore.isLoggedIn) {
    return next({ name: 'Dashboard' })
  }

  // 需要管理员权限（permission >= 1），否则 403
  if (to.meta.admin) {
    if (!authStore.isLoggedIn) {
      return next({ name: 'Login', query: { redirect: to.fullPath } })
    }
    if (!authStore.isAdmin) {
      return next({ name: 'Forbidden' })
    }
  }

  next()
})

// SEO：按路由设置页面标题（站点名取自站点设置）
const pageTitles = {
  'Home': '全新Minecraft皮肤站',
  'SkinLibrary': '皮肤库',
  'SkinDetail': '材质详情',
  'Login': '登录',
  'Register': '注册',
  'Dashboard': '仪表盘',
  'UploadSkin': '上传材质',
  'Profile': '资料',
  'ProfileEdit': '编辑资料',
  'PlayerManage': '角色管理',
  'Closet': '衣柜',
  'Admin': '后台管理',
  'OAuthAuthorize': '授权',
  'OAuthApps': 'OAuth应用',
  'ConfigGenerator': '模组配置',
  'Setup': '安装向导',
  'Forbidden': '权限不足',
  'NotFound': '页面未找到',
  'ServerError': '服务器错误'
}

router.afterEach((to) => {
  const settings = useSettingsStore()
  settings.load().finally(() => {
    const name = settings.siteName || 'OakSkin-Connect'
    if (to.name === 'Home') {
      document.title = name
    } else {
      const title = pageTitles[to.name] || pageTitles.Home
      document.title = `${title} | ${name}`
    }
  })
})

export default router