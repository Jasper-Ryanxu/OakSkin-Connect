import api from './index'

export const oauthApi = {
  // 提供方：应用信息 / 授权
  getClientInfo(clientId) {
    return api.get('/oauth/client-info', { params: { client_id: clientId } })
  },
  authorize(data) {
    return api.post('/oauth/authorize', data)
  },

  // 客户端：OakSkin 第三方登录
  getOakSkinLoginUrl() {
    return api.get('/auth/oakskin/url')
  },

  // 提供方：管理员管理 OAuth 应用
  listClients() {
    return api.get('/admin/oauth/clients')
  },
  createClient(data) {
    return api.post('/admin/oauth/clients', data)
  },
  deleteClient(clientId) {
    return api.delete(`/admin/oauth/clients/${clientId}`)
  }
}