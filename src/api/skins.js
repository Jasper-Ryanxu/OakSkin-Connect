import api from './index'

export const skinsApi = {
  getList(params = {}) {
    return api.get('/skinlib/list', { params })
  },

  getDetail(tid) {
    return api.get(`/skinlib/show/${tid}`)
  },

  getStats() {
    return api.get('/skinlib/stats')
  },

  upload(formData) {
    return api.post('/texture', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  updateType(tid, type) {
    return api.put(`/texture/${tid}/type`, { type })
  },

  updateName(tid, name) {
    return api.put(`/texture/${tid}/name`, { name })
  },

  updatePrivacy(tid, privacy) {
    return api.put(`/texture/${tid}/privacy`, { public: privacy })
  },

  delete(tid) {
    return api.delete(`/texture/${tid}`)
  },

  like(tid) {
    return api.post(`/texture/${tid}/like`)
  },

  unlike(tid) {
    return api.delete(`/texture/${tid}/like`)
  },

  report(tid, reason) {
    return api.post('/skinlib/report', { tid, reason })
  },

  download(tid) {
    return api.get(`/texture/download/${tid}`, { responseType: 'blob' })
  }
}