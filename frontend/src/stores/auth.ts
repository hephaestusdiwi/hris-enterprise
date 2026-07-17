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
  const roles = ref<string[]>([])
  const permissions = ref<string[]>([])
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
    roles.value = []
    permissions.value = []
  }

  async function fetchUser() {
    try {
      const response = await apiClient.get('/api/user')
      const data = response.data.data
      user.value = { id: data.id, name: data.name, email: data.email }
      roles.value = data.roles ?? []
      permissions.value = data.permissions ?? []
    } catch {
      user.value = null
      roles.value = []
      permissions.value = []
    }
  }

  return { user, roles, permissions, isAuthenticated, login, logout, fetchUser }
})