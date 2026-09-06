import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('oakskin_user') || 'null'))
  const token = ref(localStorage.getItem('oakskin_token') || '')
  const isDarkMode = ref(true)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => {
    if (!user.value) return false
    return user.value.permission >= 1
  })
  const nickname = computed(() => user.value?.nickname || '')
  const avatar = computed(() => user.value?.avatar || 0)
  const score = computed(() => user.value?.score || 0)

  async function login(email, password, captcha_token) {
    const data = await authApi.login(email, password, captcha_token)
    token.value = data.access_token || data.token
    user.value = data.user || data
    localStorage.setItem('oakskin_token', token.value)
    localStorage.setItem('oakskin_user', JSON.stringify(user.value))
    return data
  }

  function setToken(t) {
    token.value = t
    localStorage.setItem('oakskin_token', t)
  }

  async function register(nickname, email, password, verify_code, captcha_token) {
    const data = await authApi.register(nickname, email, password, verify_code, captcha_token)
    return data
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch (e) {
      // ignore
    }
    token.value = ''
    user.value = null
    localStorage.removeItem('oakskin_token')
    localStorage.removeItem('oakskin_user')
  }

  async function fetchUser() {
    try {
      const data = await authApi.getUser()
      user.value = data
      localStorage.setItem('oakskin_user', JSON.stringify(data))
    } catch (e) {
      // ignore
    }
  }

  function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value
    document.documentElement.setAttribute('data-theme', isDarkMode.value ? 'dark' : 'light')
  }

  return {
    user,
    token,
    isDarkMode,
    isLoggedIn,
    isAdmin,
    nickname,
    avatar,
    score,
    login,
    register,
    logout,
    setToken,
    fetchUser,
    toggleDarkMode
  }
})