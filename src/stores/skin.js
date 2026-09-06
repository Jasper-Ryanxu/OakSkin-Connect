import { defineStore } from 'pinia'
import { ref } from 'vue'
import { skinsApi } from '@/api/skins'

export const useSkinStore = defineStore('skin', () => {
  const skins = ref([])
  const currentSkin = ref(null)
  const totalCount = ref(0)
  const loading = ref(false)
  const currentPage = ref(1)
  const pageSize = ref(20)
  const filter = ref({
    type: '',
    sort: 'upload_at',
    order: 'desc',
    keyword: ''
  })

  async function fetchSkins(params = {}) {
    loading.value = true
    try {
      const query = {
        page: currentPage.value,
        ...filter.value,
        ...params
      }
      const data = await skinsApi.getList(query)
      skins.value = data.data || data.items || []
      totalCount.value = data.total || data.total_count || 0
    } catch (e) {
      console.error('Failed to fetch skins:', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchSkinDetail(tid) {
    loading.value = true
    try {
      const data = await skinsApi.getDetail(tid)
      currentSkin.value = data
      return data
    } catch (e) {
      console.error('Failed to fetch skin detail:', e)
      throw e
    } finally {
      loading.value = false
    }
  }

  function setFilter(newFilter) {
    filter.value = { ...filter.value, ...newFilter }
    currentPage.value = 1
  }

  function setPage(page) {
    currentPage.value = page
  }

  return {
    skins,
    currentSkin,
    totalCount,
    loading,
    currentPage,
    pageSize,
    filter,
    fetchSkins,
    fetchSkinDetail,
    setFilter,
    setPage
  }
})