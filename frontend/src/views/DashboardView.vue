<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'

interface Stat {
  label: string
  value: string | number
}

interface DashboardData {
  user: { id: number; name: string; email: string }
  roles: string[]
  permissions: string[]
  stats: Stat[]
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const response = await apiClient.get('/api/dashboard')
    data.value = response.data.data
  } catch {
    error.value = 'Gagal memuat dashboard. Coba refresh halaman.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div v-if="loading" class="text-sm text-slate-500">Memuat dashboard...</div>

  <div v-else-if="error" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
    {{ error }}
  </div>

  <div v-else-if="data" class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Selamat datang, {{ data.user.name }}</h1>
      <p class="mt-1 text-sm text-slate-500">
        Role:
        <span
          v-for="role in data.roles"
          :key="role"
          class="ml-1 inline-block rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white"
        >
          {{ role }}
        </span>
      </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="stat in data.stats"
        :key="stat.label"
        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
      >
        <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ stat.value }}</p>
      </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="text-sm font-medium text-slate-500">Permission Kamu</h2>
      <ul class="mt-2 flex flex-wrap gap-2">
        <li
          v-for="perm in data.permissions"
          :key="perm"
          class="rounded-md bg-slate-100 px-2.5 py-1 text-xs text-slate-700"
        >
          {{ perm }}
        </li>
      </ul>
    </div>
  </div>
</template>