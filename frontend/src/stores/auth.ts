import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import apiClient from '@/lib/axios'

interface User {
  id: number
  name: string
  email: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => !!user.value)

  async function fetchCsrfCookie() {
    await apiClient.get('/sanctum/csrf-cookie')
  }

  async function login(email: string, password: string) {
    await fetchCsrfCookie()
    await apiClient.post('/api/login', { email, password })
    await fetchUser()
  }

  async function logout() {
    await apiClient.post('/api/logout')
    user.value = null
  }

  async function fetchUser() {
    try {
      const response = await apiClient.get('/api/user')
      user.value = response.data.data
    } catch {
      user.value = null
    }
  }

  return { user, isAuthenticated, login, logout, fetchUser }
})