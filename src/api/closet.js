import api from './index'

export const closetApi = {
  getList() {
    return api.get('/user/closet/list')
  },

  add(tid, item_name = '') {
    return api.post('/user/closet', { tid, item_name })
  },

  rename(tid, item_name) {
    return api.put(`/user/closet/${tid}`, { item_name })
  },

  remove(tid) {
    return api.delete(`/user/closet/${tid}`)
  },

  getAllIds() {
    return api.get('/user/closet/ids')
  }
}