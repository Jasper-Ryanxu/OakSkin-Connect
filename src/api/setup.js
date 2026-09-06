import api from './index'

export const setupApi = {
  getStatus() {
    return api.get('/setup/status')
  },

  install(data) {
    return api.post('/setup/install', data)
  }
}