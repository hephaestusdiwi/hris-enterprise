<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Users, Briefcase, CalendarClock, TrendingUp } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import AttendanceSelfServiceCard from '@/components/AttendanceSelfServiceCard.vue'

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

const statIcons = [Users, Briefcase, CalendarClock, TrendingUp]
const statPalettes = [
  { bg: 'bg-primary-soft', text: 'text-primary-dark' },
  { bg: 'bg-blue-50', text: 'text-blue-600' },
  { bg: 'bg-amber-50', text: 'text-amber-600' },
  { bg: 'bg-violet-50', text: 'text-violet-600' },
]

function statIcon(i: number) {
  return statIcons[i % statIcons.length]!
}
function statPalette(i: number) {
  return statPalettes[i % statPalettes.length]!
}

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
  <div v-if="loading" class="text-sm text-slate-400">Memuat dashboard...</div>

  <div v-else-if="error" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
    {{ error }}
  </div>

  <div v-else-if="data" class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
        Selamat datang, {{ data.user.name }}
      </h1>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <span
          v-for="role in data.roles"
          :key="role"
          class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
        >
          {{ role }}
        </span>
      </div>
    </div>

    <AttendanceSelfServiceCard />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="(stat, i) in data.stats"
        :key="stat.label"
        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_4px_16px_rgba(15,23,42,0.06)]"
      >
        <div class="flex h-10 w-10 items-center justify-center rounded-xl" :class="statPalette(i).bg">
          <component :is="statIcon(i)" class="h-5 w-5" :class="statPalette(i).text" :stroke-width="1.75" />
        </div>
        <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-900">{{ stat.value }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ stat.label }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <h2 class="text-sm font-medium text-slate-500">Permission Kamu</h2>
      <div class="mt-3 flex flex-wrap gap-1.5">
        <span
          v-for="perm in data.permissions"
          :key="perm"
          class="rounded-full border border-slate-100 bg-slate-50 px-2.5 py-1 text-xs text-slate-600"
        >
          {{ perm }}
        </span>
      </div>
    </div>
  </div>
</template>