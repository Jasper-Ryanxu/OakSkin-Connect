import { defineStore } from 'pinia'
import { ref } from 'vue'
import { settingsApi } from '@/api/settings'

export const useSettingsStore = defineStore('settings', () => {
  const siteName = ref('OakSkin Connect')
  const siteUrl = ref('')
  const seoKeywords = ref('')
  const seoDescription = ref('')
  const friendLinks = ref([])
  const announcement = ref('')
  const maxUploadSize = ref(51200)

  let loaded = false
  let loading = null

  async function load(force = false) {
    if (loaded && !force) return
    if (loading) return loading
    loading = (async () => {
      try {
        const d = await settingsApi.getPublic()
        const s = d.data || {}
        siteName.value = s.site_name || 'OakSkin Connect'
        siteUrl.value = s.site_url || ''
        seoKeywords.value = s.seo_keywords || ''
        seoDescription.value = s.seo_description || ''
        friendLinks.value = Array.isArray(s.friend_links) ? s.friend_links : []
        announcement.value = s.announcement || ''
        maxUploadSize.value = s.max_upload_size || 51200
        applyMeta()
      } catch (e) {
        // ignore, use defaults
      } finally {
        loaded = true
        loading = null
      }
    })()
    return loading
  }

  /** 应用 SEO 元信息（keywords / description） */
  function applyMeta() {
    setMeta('keywords', seoKeywords.value)
    setMeta('description', seoDescription.value)
  }

  function setMeta(name, content) {
    if (!content) return
    let el = document.querySelector(`meta[name="${name}"]`)
    if (!el) {
      el = document.createElement('meta')
      el.setAttribute('name', name)
      document.head.appendChild(el)
    }
    el.setAttribute('content', content)
  }

  return {
    siteName,
    siteUrl,
    seoKeywords,
    seoDescription,
    friendLinks,
    announcement,
    maxUploadSize,
    load,
    applyMeta
  }
})