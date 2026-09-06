import api from './index'

export const authApi = {
  login(email, password, captcha_token) {
    return api.post('/auth/login', { email, password, captcha_token })
  },

  register(nickname, email, password, verify_code, captcha_token) {
    return api.post('/auth/register', { nickname, email, password, verify_code, captcha_token })
  },

  logout() {
    return api.post('/auth/logout')
  },

  sendVerifyCode(email) {
    return api.post('/auth/send-verify-code', { email })
  },

  getUser() {
    return api.get('/user')
  },

  updateProfile(data) {
    return api.post('/user/profile', data)
  }
}