import axios from 'axios'

export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  withXSRFToken: true,
})

let csrfPrimed = false

export async function ensureCsrfCookie() {
  if (csrfPrimed) return
  await apiClient.get('/sanctum/csrf-cookie')
  csrfPrimed = true
}

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      csrfPrimed = false
    }
    return Promise.reject(error)
  }
)
