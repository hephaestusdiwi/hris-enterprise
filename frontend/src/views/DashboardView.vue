<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Users, Briefcase, CalendarClock, TrendingUp, ChevronDown } from 'lucide-vue-next'
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

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 11) return 'Selamat Pagi'
  if (hour < 15) return 'Selamat Siang'
  if (hour < 19) return 'Selamat Sore'
  return 'Selamat Malam'
})

const formattedDate = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' })
)

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
    <!-- Greeting card -->
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)] sm:p-8">
      <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
        <div class="flex-1">
          <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
            {{ greeting }}, <span class="uppercase text-primary-dark">{{ data.user.name }}</span>!
          </h1>
          <p class="mt-1 text-sm text-slate-500">Ini hari {{ formattedDate }}</p>

          <div class="mt-5 flex flex-wrap gap-1.5">
            <span
              v-for="role in data.roles"
              :key="role"
              class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
            >
              {{ role }}
            </span>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
            <p class="mb-2.5 text-xs font-medium text-slate-400">Shortcut</p>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-primary/40 hover:text-primary-dark"
              >
                Live Attendance
              </button>
              <button
                type="button"
                class="flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-primary/40 hover:text-primary-dark"
              >
                More Request
                <ChevronDown class="h-3 w-3" :stroke-width="2" />
              </button>
            </div>
          </div>
        </div>

        <!-- Illustration -->
        <svg viewBox="0 0 180 160" class="hidden h-32 w-40 shrink-0 sm:block">
          <rect x="15" y="128" width="150" height="8" rx="4" class="fill-slate-200" />
          <rect x="30" y="136" width="6" height="18" class="fill-slate-200" />
          <rect x="144" y="136" width="6" height="18" class="fill-slate-200" />

          <rect x="65" y="100" width="50" height="30" rx="3" class="fill-slate-700" />
          <rect x="69" y="103" width="42" height="22" rx="2" class="fill-primary/20" />
          <rect x="58" y="128" width="64" height="5" rx="2.5" class="fill-slate-300" />

          <path d="M52 128 C52 100 68 88 90 88 C112 88 128 100 128 128 Z" class="fill-primary-soft" />
          <circle cx="90" cy="60" r="24" class="fill-primary-dark" />

          <g transform="translate(128,20)">
            <rect width="34" height="26" rx="10" class="fill-emerald-500" />
            <path d="M9 13 L14.5 18.5 L25 7" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M14 26 L8 34 L18 26 Z" class="fill-emerald-500" />
          </g>
        </svg>
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