<template>
  <div class="upload-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-upload"></i> 上传皮肤 / 披风</h1>
        <p>分享你的 Minecraft 皮肤与披风作品</p>
      </div>

      <div class="upload-layout">
        <!-- Upload Form -->
        <div class="upload-card">
          <form @submit.prevent="handleUpload" class="upload-form">
            <div v-if="error" class="alert alert-error">
              <i class="fas fa-exclamation-circle"></i>
              {{ error }}
            </div>

            <div v-if="success" class="alert alert-success">
              <i class="fas fa-check-circle"></i>
              {{ success }}
            </div>

            <!-- File Upload -->
            <div class="form-group">
              <label>皮肤文件</label>
              <div
                class="drop-zone"
                :class="{ 'has-file': file, 'dragging': isDragging }"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleDrop"
                @click="triggerFileInput"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/png"
                  class="file-input-hidden"
                  @change="handleFileSelect"
                />
                <template v-if="file">
                  <img :src="filePreview" class="file-preview" />
                  <p class="file-name">{{ file.name }}</p>
                  <p class="file-size">{{ formatFileSize(file.size) }}</p>
                </template>
                <template v-else>
                  <i class="fas fa-cloud-upload-alt"></i>
                  <p>点击或拖拽文件到此处</p>
                  <p class="hint">支持 PNG 格式，最大 {{ maxUploadSizeKB }}KB</p>
                </template>
              </div>
            </div>

            <!-- Skin Name -->
            <div class="form-group">
              <label for="skinName">皮肤名称</label>
              <input
                id="skinName"
                v-model="skinName"
                type="text"
                class="input"
                placeholder="给皮肤起个名字"
                required
                maxlength="30"
              />
            </div>

            <!-- Model Type -->
            <div class="form-group">
              <label>模型类型</label>
              <div class="model-selector">
                <label class="model-option" :class="{ active: modelType === 'steve' }">
                  <input type="radio" v-model="modelType" value="steve" />
                  <i class="fas fa-male"></i>
                  <div class="model-info">
                    <span class="model-name">经典模型</span>
                    <span class="model-desc">Steve 4px 手臂</span>
                  </div>
                </label>
                <label class="model-option" :class="{ active: modelType === 'alex' }">
                  <input type="radio" v-model="modelType" value="alex" />
                  <i class="fas fa-female"></i>
                  <div class="model-info">
                    <span class="model-name">纤细模型</span>
                    <span class="model-desc">Alex 3px 手臂</span>
                  </div>
                </label>
                <label class="model-option" :class="{ active: modelType === 'cape' }">
                  <input type="radio" v-model="modelType" value="cape" />
                  <i class="fas fa-flag"></i>
                  <div class="model-info">
                    <span class="model-name">披风</span>
                    <span class="model-desc">Cape 披风材质</span>
                  </div>
                </label>
              </div>
            </div>

            <!-- Privacy -->
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="isPublic" />
                <span class="checkbox-custom">
                  <i class="fas fa-check"></i>
                </span>
                公开皮肤（所有人都能看到）
              </label>
            </div>

            <button
              type="submit"
              class="btn btn-primary btn-lg btn-block"
              :disabled="submitting"
            >
              <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
              <i class="fas fa-upload" v-else></i>
              {{ submitting ? '上传中...' : '上传皮肤' }}
            </button>
          </form>
        </div>

        <!-- Preview Panel -->
        <div class="preview-card">
          <h3><i class="fas fa-cube"></i> 实时预览</h3>
          <div v-if="modelType === 'cape'" class="cape-preview-wrap">
            <div class="cape-flat-preview">
              <h4><i class="fas fa-image"></i> 平面预览</h4>
              <div class="cape-flat-image-wrap">
                <img :src="filePreview" class="cape-flat-image" />
              </div>
              <p class="cape-flat-tip">披风纹理</p>
            </div>
            <div class="cape-3d-preview">
              <h4><i class="fas fa-cube"></i> 3D 预览</h4>
              <SkinViewer3D
                :skinUrl="'/steve.png'"
                :capeUrl="filePreview || ''"
                :model="'default'"
                :autoRotate="true"
                :height="360"
              />
              <p class="cape-3d-tip">披风将显示在角色背后</p>
            </div>
          </div>
          <SkinViewer3D
            v-else
            :skinUrl="filePreview || '/steve.png'"
            :model="modelType === 'alex' ? 'slim' : 'default'"
            :autoRotate="true"
            :height="400"
          />
          <div class="upload-hints">
            <h4><i class="fas fa-info-circle"></i> 上传须知</h4>
            <ul>
              <li>支持 64x32 和 64x64 像素的皮肤文件</li>
              <li>仅支持 PNG 格式</li>
              <li>请确保你拥有皮肤的上传权限</li>
              <li>禁止上传违规内容</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { skinsApi } from '@/api/skins'
import { formatFileSize } from '@/utils/helpers'
import SkinViewer3D from '@/components/SkinViewer3D.vue'
import api from '@/api/index'

const router = useRouter()
const fileInput = ref(null)
const file = ref(null)
const filePreview = ref('')
const skinName = ref('')
const modelType = ref('steve')
const isPublic = ref(true)
const isDragging = ref(false)
const error = ref('')
const success = ref('')
const submitting = ref(false)
const maxUploadSizeKB = ref(51200) // 默认 51200 KB，等待 API 返回后更新
const maxUploadSizeBytes = ref(51200 * 1024)

onMounted(async () => {
  try {
    const res = await api.get('/settings/public')
    const sizeKB = res?.data?.max_upload_size || 51200
    maxUploadSizeKB.value = sizeKB
    maxUploadSizeBytes.value = sizeKB * 1024
  } catch (e) {
    console.error('获取上传设置失败:', e)
  }
})

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(e) {
  const selectedFile = e.target.files[0]
  if (selectedFile) processFile(selectedFile)
}

function handleDrop(e) {
  isDragging.value = false
  const droppedFile = e.dataTransfer.files[0]
  if (droppedFile) processFile(droppedFile)
}

function processFile(f) {
  if (!f.type.includes('png')) {
    error.value = '仅支持 PNG 格式的图片'
    return
  }
  if (f.size > maxUploadSizeBytes.value) {
    error.value = `文件大小不能超过 ${maxUploadSizeKB.value}KB`
    return
  }
  file.value = f
  filePreview.value = URL.createObjectURL(f)
  error.value = ''

  if (!skinName.value) {
    skinName.value = f.name.replace(/\.png$/i, '')
  }
}

async function handleUpload() {
  error.value = ''
  success.value = ''
  submitting.value = true

  try {
    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('name', skinName.value)
    formData.append('type', modelType.value)
    formData.append('public', isPublic.value ? '1' : '0')

    const data = await skinsApi.upload(formData)
    success.value = '上传成功！'
    setTimeout(() => {
      router.push(`/skinlib/${data.tid || data.data?.tid}`)
    }, 1000)
  } catch (e) {
    error.value = e.message || e.msg || '上传失败，请重试'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.upload-page {
  padding-bottom: 60px;
}

.upload-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  align-items: start;
}

.upload-card,
.preview-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 32px;
}

.preview-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.preview-card h3 i {
  color: var(--accent-primary);
}

.drop-zone {
  border: 2px dashed var(--border-color);
  border-radius: var(--border-radius);
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all var(--transition-normal);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.drop-zone:hover,
.drop-zone.dragging {
  border-color: var(--accent-primary);
  background: var(--accent-glow);
}

.drop-zone.has-file {
  border-style: solid;
  border-color: var(--success);
}

.drop-zone i {
  font-size: 40px;
  color: var(--accent-primary);
}

.drop-zone p {
  font-size: 14px;
  color: var(--text-secondary);
}

.drop-zone .hint {
  font-size: 12px;
  color: var(--text-muted);
}

.file-input-hidden {
  display: none;
}

.file-preview {
  max-width: 120px;
  image-rendering: pixelated;
  border-radius: 8px;
  margin-bottom: 8px;
}

.file-name {
  font-weight: 600;
  color: var(--text-primary) !important;
}

.file-size {
  font-size: 12px !important;
  color: var(--text-muted) !important;
}

.model-selector {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
}

.model-option {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 18px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  background: linear-gradient(180deg, #2a2a5a 0%, #1e1e42 100%);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.model-option input {
  display: none;
}

.model-option:hover {
  border-color: rgba(108, 92, 231, 0.4);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.model-option.active {
  border-color: var(--accent-primary);
  background: linear-gradient(135deg, rgba(108, 92, 231, 0.2) 0%, rgba(162, 155, 254, 0.2) 100%);
  box-shadow: 0 4px 15px rgba(108, 92, 231, 0.25);
}

.model-option i {
  font-size: 24px;
  color: var(--accent-primary);
}

.model-info {
  display: flex;
  flex-direction: column;
}

.model-name {
  font-weight: 600;
  font-size: 14px;
}

.model-desc {
  font-size: 12px;
  color: var(--text-muted);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 14px;
  color: var(--text-secondary);
}

.checkbox-label input {
  display: none;
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-color);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}

.checkbox-custom i {
  font-size: 10px;
  color: #fff;
  opacity: 0;
  transition: opacity var(--transition-fast);
}

.checkbox-label input:checked + .checkbox-custom {
  background: var(--accent-primary);
  border-color: var(--accent-primary);
}

.checkbox-label input:checked + .checkbox-custom i {
  opacity: 1;
}

.upload-hints {
  margin-top: 24px;
  padding: 20px;
  background: var(--bg-tertiary);
  border-radius: var(--border-radius-sm);
}

.cape-preview-wrap {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding: 0;
  width: 100%;
}

.cape-flat-preview,
.cape-3d-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.cape-flat-preview h4,
.cape-3d-preview h4 {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-secondary);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.cape-flat-preview h4 i,
.cape-3d-preview h4 i {
  color: var(--accent-primary);
  font-size: 12px;
}

.cape-flat-image-wrap {
  background: repeating-conic-gradient(#c0c0c0 0% 25%, #e8e8e8 0% 50%) 50% / 20px 20px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  width: 100%;
  max-width: 200px;
  min-height: 130px;
}

.cape-flat-image {
  display: block;
  width: 160px;
  height: auto;
  image-rendering: pixelated;
}

.cape-flat-tip,
.cape-3d-tip {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}

.upload-hints h4 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.upload-hints h4 i {
  color: var(--info);
}

.upload-hints ul {
  list-style: none;
  padding: 0;
}

.upload-hints li {
  font-size: 13px;
  color: var(--text-muted);
  padding: 4px 0;
  padding-left: 16px;
  position: relative;
}

.upload-hints li::before {
  content: '>';
  position: absolute;
  left: 0;
  color: var(--accent-primary);
}

@media (max-width: 768px) {
  .upload-layout {
    grid-template-columns: 1fr;
  }
}
</style>