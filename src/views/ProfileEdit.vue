<template>
  <div class="profile-edit-page">
    <div class="container">
      <div class="page-header">
        <h1><i class="fas fa-edit"></i> 编辑资料</h1>
        <p>修改你的个人资料信息</p>
      </div>

      <div class="edit-card">
        <form @submit.prevent="handleSave" class="edit-form">
          <div v-if="error" class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ error }}
          </div>
          <div v-if="success" class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ success }}
          </div>

          <div class="form-group">
            <label for="nickname">昵称</label>
            <input
              id="nickname"
              v-model="form.nickname"
              type="text"
              class="input"
              placeholder="输入昵称"
              required
            />
          </div>

          <div class="form-group">
            <label for="email">邮箱地址</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              class="input"
              placeholder="输入邮箱地址"
              required
            />
            <p class="form-help">邮箱地址不可修改，如需更改请联系管理员</p>
          </div>

          <div class="form-group">
            <label for="current_password">当前密码</label>
            <input
              id="current_password"
              v-model="form.current_password"
              type="password"
              class="input"
              placeholder="输入当前密码以确认修改"
            />
          </div>

          <div class="form-group">
            <label for="new_password">新密码（可选）</label>
            <input
              id="new_password"
              v-model="form.new_password"
              type="password"
              class="input"
              placeholder="留空则不修改密码"
              minlength="8"
            />
          </div>

          <div class="form-group">
            <label for="new_password_confirm">确认新密码</label>
            <input
              id="new_password_confirm"
              v-model="form.new_password_confirm"
              type="password"
              class="input"
              placeholder="再次输入新密码"
            />
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              <i class="fas fa-spinner fa-spin" v-if="submitting"></i>
              <i class="fas fa-save" v-else></i>
              {{ submitting ? '保存中...' : '保存修改' }}
            </button>
            <router-link to="/user" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> 返回
            </router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/auth'

const authStore = useAuthStore()
const error = ref('')
const success = ref('')
const submitting = ref(false)

const form = ref({
  nickname: '',
  email: '',
  current_password: '',
  new_password: '',
  new_password_confirm: ''
})

onMounted(() => {
  if (authStore.user) {
    form.value.nickname = authStore.user.nickname || ''
    form.value.email = authStore.user.email || ''
  }
})

async function handleSave() {
  error.value = ''
  success.value = ''

  if (form.value.new_password) {
    if (form.value.new_password.length < 8) {
      error.value = '新密码长度至少为8位'
      return
    }
    if (form.value.new_password !== form.value.new_password_confirm) {
      error.value = '两次输入的新密码不一致'
      return
    }
  }

  submitting.value = true
  try {
    await authApi.updateProfile({
      nickname: form.value.nickname,
      password: form.value.new_password || undefined,
      current_password: form.value.current_password || undefined
    })
    success.value = '资料已更新'
    await authStore.fetchUser()
  } catch (e) {
    error.value = e.message || e.msg || '保存失败'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.profile-edit-page {
  padding-bottom: 60px;
}

.edit-card {
  max-width: 600px;
  margin: 0 auto;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  padding: 32px;
}

.edit-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-actions {
  display: flex;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}
</style>