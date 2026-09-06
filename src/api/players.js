import api from './index'

export const playersApi = {
  getList() {
    return api.get('/user/player/list')
  },

  add(name) {
    return api.post('/user/player', { name })
  },

  rename(pid, name) {
    return api.put(`/user/player/${pid}/name`, { name })
  },

  setTexture(pid, tid_skin, tid_cape = null) {
    return api.put(`/user/player/${pid}/textures`, { tid_skin, tid_cape })
  },

  clearTexture(pid) {
    return api.delete(`/user/player/${pid}/textures`)
  },

  delete(pid) {
    return api.delete(`/user/player/${pid}`)
  }
}