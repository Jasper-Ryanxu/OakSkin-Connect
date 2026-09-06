import api from './index'

export const settingsApi = {
  getPublic() {
    return api.get('/settings/public')
  }
}