<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowLeft, Users, UserRound } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface HierarchyPerson {
  id: number
  name: string
  position: string | null
  photo_url: string | null
}

interface EmployeeDetail {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
  photo_url: string | null
  company: { id: number; name: string } | null
  branch: { id: number; name: string } | null
  department: { id: number; name: string } | null
  position: { id: number; name: string } | null
  job_level: { id: number; name: string } | null
  employment_status: { id: number; name: string } | null
  employment_type: { id: number; name: string } | null
}

const route = useRoute()
const router = useRouter()

const employee = ref<EmployeeDetail | null>(null)
const manager = ref<HierarchyPerson | null>(null)
const directReports = ref<HierarchyPerson[]>([])

const loading = ref(true)
const errorMessage = ref('')

const employeeId = computed(() => Number(route.params.id))

const fullName = computed(() => {
  if (!employee.value) return ''
  return [employee.value.first_name, employee.value.last_name].filter(Boolean).join(' ')
})

const initials = computed(() =>
  fullName.value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join(''),
)

async function loadEmployee(id: number) {
  loading.value = true
  errorMessage.value = ''
  employee.value = null
  manager.value = null
  directReports.value = []

  try {
    const [detailRes, hierarchyRes] = await Promise.all([
      apiClient.get(`/api/employees/${id}`),
      apiClient.get(`/api/employees/${id}/hierarchy`),
    ])

    employee.value = detailRes.data.data
    manager.value = hierarchyRes.data.data.manager
    directReports.value = hierarchyRes.data.data.direct_reports
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 403) {
      errorMessage.value = 'Kamu tidak punya akses untuk melihat data employee ini.'
    } else if (status === 404) {
      errorMessage.value = 'Employee tidak ditemukan.'
    } else {
      errorMessage.value = 'Gagal memuat data employee.'
    }
  } finally {
    loading.value = false
  }
}

function goToEmployee(id: number) {
  router.push({ name: 'employee-detail', params: { id } })
}

onMounted(() => loadEmployee(employeeId.value))

// Route param bisa berubah (klik Manager/Direct Report) tanpa component
// di-unmount ulang oleh Vue Router — perlu di-watch supaya data ke-refresh.
watch(employeeId, (id) => {
  if (!Number.isNaN(id)) loadEmployee(id)
})
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 p-6">
    <button
      type="button"
      class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700"
      @click="router.push({ name: 'employees' })"
    >
      <ArrowLeft class="h-4 w-4" :stroke-width="2" />
      Kembali ke Employee List
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <template v-else-if="employee">
      <!-- Header -->
      <div class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <img
          v-if="employee.photo_url"
          :src="employee.photo_url"
          class="h-16 w-16 rounded-full object-cover"
          alt=""
        />
        <div v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-soft text-lg font-semibold text-primary-dark">
          {{ initials }}
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-lg font-semibold text-slate-900">{{ fullName }}</p>
          <p class="mt-0.5 truncate text-sm text-slate-500">
            {{ employee.position?.name ?? 'Belum ada posisi' }}
            <span v-if="employee.department"> &middot; {{ employee.department.name }}</span>
          </p>
          <p class="mt-1 text-xs text-slate-400">{{ employee.employee_number }}</p>
        </div>

        <span
          v-if="employee.employment_status"
          class="shrink-0 rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500"
        >
          {{ employee.employment_status.name }}
        </span>
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <!-- Manager -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <h2 class="text-sm font-semibold text-slate-700">Manager</h2>

          <button
            v-if="manager"
            type="button"
            class="mt-3 flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left transition hover:border-primary/30 hover:bg-primary-soft/40"
            @click="goToEmployee(manager.id)"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
              <img
                v-if="manager.photo_url"
                :src="manager.photo_url"
                :alt="manager.name"
                class="h-full w-full object-cover"
              />
              <span v-else>
                {{ manager.name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('') }}
              </span>
            </div>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">{{ manager.name }}</p>
              <p v-if="manager.position" class="truncate text-xs text-slate-400">{{ manager.position }}</p>
            </div>
          </button>

          <div v-else class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
            Employee ini tidak memiliki manager (posisi teratas struktur organisasi).
          </div>
        </div>

        <!-- Direct Reports -->
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
          <h2 class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
            Direct Reports
            <span v-if="directReports.length" class="rounded-full bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-400">
              {{ directReports.length }}
            </span>
          </h2>

          <div v-if="directReports.length" class="mt-3 space-y-2">
            <button
              v-for="report in directReports"
              :key="report.id"
              type="button"
              class="flex w-full items-center gap-3 rounded-xl border border-slate-100 p-3 text-left transition hover:border-primary/30 hover:bg-primary-soft/40"
              @click="goToEmployee(report.id)"
            >
              <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-xs font-semibold text-primary-dark">
                <img
                  v-if="report.photo_url"
                  :src="report.photo_url"
                  :alt="report.name"
                  class="h-full w-full object-cover"
                />
                <span v-else>
                  {{ report.name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('') }}
                </span>
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-800">{{ report.name }}</p>
                <p v-if="report.position" class="truncate text-xs text-slate-400">{{ report.position }}</p>
              </div>
            </button>
          </div>

          <div v-else class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-400">
            <Users class="mx-auto mb-1.5 h-5 w-5 text-slate-300" :stroke-width="1.5" />
            Belum ada direct report.
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
