<template>
  <div class="skin-library-page">
    <div class="page-header">
      <h1><i class="fas fa-palette"></i> 皮肤库</h1>
      <p>发现社区分享的精彩皮肤</p>
    </div>

    <!-- Filters -->
    <div class="container">
      <div class="filters-bar">
        <div class="filter-group">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input
              v-model="searchKeyword"
              type="text"
              class="input"
              placeholder="搜索皮肤名称..."
              @input="debouncedSearch"
            />
          </div>
        </div>
        <div class="filter-group">
          <button
            class="filter-btn"
            :class="{ active: filterType === '' }"
            @click="setFilterType('')"
          >
            全部
          </button>
          <button
            class="filter-btn"
            :class="{ active: filterType === 'steve' }"
            @click="setFilterType('steve')"
          >
            <i class="fas fa-male"></i> 经典
          </button>
          <button
            class="filter-btn"
            :class="{ active: filterType === 'alex' }"
            @click="setFilterType('alex')"
          >
            <i class="fas fa-female"></i> 纤细
          </button>
          <button
            class="filter-btn"
            :class="{ active: filterType === 'cape' }"
            @click="setFilterType('cape')"
          >
            <i class="fas fa-flag"></i> 披风
          </button>
        </div>
        <div class="filter-group">
          <select v-model="sortBy" class="input sort-select" @change="fetchData">
            <option value="upload_at">最新上传</option>
            <option value="likes">最多喜欢</option>
            <option value="name">名称排序</option>
          </select>
        </div>
      </div>

      <!-- Skin Grid -->
      <div v-if="loading" class="loading-spinner">
        <div class="spinner"></div>
      </div>

      <div v-else-if="skins.length === 0" class="empty-state">
        <i class="fas fa-palette"></i>
        <h3>暂无皮肤</h3>
        <p>还没有上传任何皮肤，快来成为第一个吧！</p>
        <router-link to="/skinlib/upload" class="btn btn-primary" style="margin-top: 16px;">
          <i class="fas fa-upload"></i> 上传皮肤
        </router-link>
      </div>

      <div v-else class="skin-grid">
        <SkinCard v-for="skin in skins" :key="skin.tid" :skin="skin" />
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button
          v-for="page in visiblePages"
          :key="page"
          :class="{ active: page === currentPage }"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
        <button :disabled="currentPage >= totalPages" @click="goToPage(currentPage + 1)">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { skinsApi } from '@/api/skins'
import { debounce } from '@/utils/helpers'
import SkinCard from '@/components/SkinCard.vue'

const skins = ref([])
const loading = ref(false)
const currentPage = ref(1)
const totalCount = ref(0)
const pageSize = ref(20)
const filterType = ref('')
const sortBy = ref('upload_at')
const searchKeyword = ref('')

const totalPages = computed(() => Math.ceil(totalCount.value / pageSize.value))

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = currentPage.value
  let start = Math.max(1, current - 2)
  let end = Math.min(total, current + 2)

  if (end - start < 4) {
    if (start === 1) end = Math.min(total, start + 4)
    else start = Math.max(1, end - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  return pages
})

async function fetchData() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
      sort: sortBy.value,
      order: 'desc'
    }
    if (filterType.value) params.type = filterType.value
    if (searchKeyword.value) params.keyword = searchKeyword.value

    const data = await skinsApi.getList(params)
    skins.value = data.data || data.items || []
    totalCount.value = data.total || data.total_count || 0
  } catch (e) {
    console.error('Failed to fetch skins:', e)
  } finally {
    loading.value = false
  }
}

function setFilterType(type) {
  filterType.value = type
  currentPage.value = 1
  fetchData()
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  fetchData()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const debouncedSearch = debounce(() => {
  currentPage.value = 1
  fetchData()
}, 400)

onMounted(fetchData)
</script>

<style scoped>
.skin-library-page {
  padding-bottom: 40px;
}

.filters-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 0;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-box {
  position: relative;
  width: 280px;
}

.search-box i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 14px;
}

.search-box .input {
  padding-left: 38px;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: linear-gradient(180deg, #2a2a5a 0%, #1e1e42 100%);
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  font-family: var(--font-family);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.filter-btn:hover {
  background: linear-gradient(180deg, #32326a 0%, #262652 100%);
  border-color: rgba(108, 92, 231, 0.4);
  color: var(--text-primary);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.filter-btn.active {
  background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 4px 12px rgba(108, 92, 231, 0.35);
  font-weight: 700;
}

.sort-select {
  width: 140px;
  cursor: pointer;
}

@media (max-width: 768px) {
  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-box {
    width: 100%;
  }
  .filter-group {
    flex-wrap: wrap;
  }
}
</style>