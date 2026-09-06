import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('oakskin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  response => response.data,
  error => {
    if (error.response) {
      const { status, data, config } = error.response
      if (status === 401) {
        localStorage.removeItem('oakskin_token')
        localStorage.removeItem('oakskin_user')
        window.location.href = '/auth/login'
      }
      // 某些虚拟主机仅支持 GET/POST：DELETE 落到 405 时用 POST + X-HTTP-Method-Override 重试一次
      if (status === 405 && config && config.method === 'delete' && !config._retried) {
        config._retried = true
        config.method = 'post'
        config.headers = { ...config.headers, 'X-HTTP-Method-Override': 'DELETE' }
        if (config.data === undefined) config.data = {}
        return api.request(config)
      }
      return Promise.reject(data)
    }
    return Promise.reject(error)
  }
)

export default api