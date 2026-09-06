import api from './index'

export const adminApi = {
  getStats() {
    return api.get('/admin/stats')
  },

  getUsers(params = {}) {
    return api.get('/admin/users', { params })
  },

  updatePermission(uid, permission) {
    return api.put(`/admin/users/${uid}/permission`, { permission })
  },

  getSettings() {
    return api.get('/admin/settings')
  },

  updateSettings(data) {
    return api.put('/admin/settings', data)
  }
}