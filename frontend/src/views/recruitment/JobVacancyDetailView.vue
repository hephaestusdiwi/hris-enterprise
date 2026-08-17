<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeft } from 'lucide-vue-next'

interface RefOption { id: number; name: string }
interface EmployeeOption { id: number; first_name: string; last_name: string | null }

interface JobVacancyDetail {
  id: number
  title: string
  slug: string
  description: string
  requirements: string | null
  status: string
  visibility: string
  application_method: string
  external_apply_url: string | null
  application_deadline: string | null
  published_at: string | null
  paused_at: string | null
  closed_at: string | null
  filled_at: string | null
  cancelled_at: string | null
  archived_at: string | null
  hiring_requisition: { id: number; reason: string; headcount_requested: number; headcount_filled: number } | null
  position: RefOption | null
  department: RefOption | null
  hiring_manager: EmployeeOption | null
  recruiter: EmployeeOption | null
  employment_type: RefOption | null
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const vacancyId = computed(() => Number(route.params.id))
const vacancy = ref<JobVacancyDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')

function employeeName(e: EmployeeOption | null): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

async function loadVacancy() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/job-vacancies/${vacancyId.value}`)
    vacancy.value = response.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat detail Job Vacancy.'
  } finally {
    loading.value = false
  }
}

function availableActions(status: string): Array<{ action: string; label: string }> {
  switch (status) {
    case 'draft':
      return [{ action: 'publish', label: 'Publish' }, { action: 'cancel', label: 'Cancel' }]
    case 'published':
      return [{ action: 'pause', label: 'Pause' }, { action: 'close', label: 'Close' }, { action: 'cancel', label: 'Cancel' }]
    case 'paused':
      return [{ action: 'publish', label: 'Resume' }, { action: 'close', label: 'Close' }, { action: 'cancel', label: 'Cancel' }]
    case 'closed':
    case 'filled':
    case 'cancelled':
      return [{ action: 'archive', label: 'Archive' }]
    default:
      return []
  }
}

const ACTION_PERMISSION: Record<string, string> = {
  publish: 'publish job vacancies',
  pause: 'publish job vacancies',
  close: 'close job vacancies',
  cancel: 'cancel job vacancies',
  archive: 'archive job vacancies',
}

function canDoAction(action: string): boolean {
  return authStore.permissions.includes(ACTION_PERMISSION[action])
}

async function runAction(action: string) {
  actionError.value = ''
  try {
    await apiClient.post(`/api/job-vacancies/${vacancyId.value}/${action}`)
    await loadVacancy()
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Aksi gagal dijalankan.'
  }
}

onMounted(loadVacancy)
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-1 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'job-vacancies.index' })">
      <ArrowLeft class="h-4 w-4" /> Kembali
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else-if="vacancy" class="space-y-4">
      <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

      <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-lg font-semibold text-slate-800">{{ vacancy.title }}</h1>
            <p class="text-sm text-slate-400">
              {{ vacancy.position?.name || '-' }} · {{ vacancy.department?.name || '-' }} · {{ vacancy.employment_type?.name || '-' }}
            </p>
          </div>
          <div class="flex gap-2">
            <button
              v-for="a in availableActions(vacancy.status)"
              v-show="canDoAction(a.action)"
              :key="a.action"
              class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
              @click="runAction(a.action)"
            >
              {{ a.label }}
            </button>
          </div>
        </div>

        <dl class="mt-6 grid grid-cols-3 gap-4 text-sm">
          <div><dt class="text-slate-400">Status</dt><dd class="font-medium text-slate-700">{{ vacancy.status }}</dd></div>
          <div><dt class="text-slate-400">Visibility</dt><dd class="font-medium text-slate-700">{{ vacancy.visibility }}</dd></div>
          <div><dt class="text-slate-400">Application Method</dt><dd class="font-medium text-slate-700">{{ vacancy.application_method }}</dd></div>
          <div><dt class="text-slate-400">Hiring Manager</dt><dd class="font-medium text-slate-700">{{ employeeName(vacancy.hiring_manager) }}</dd></div>
          <div><dt class="text-slate-400">Recruiter</dt><dd class="font-medium text-slate-700">{{ employeeName(vacancy.recruiter) }}</dd></div>
          <div v-if="vacancy.hiring_requisition">
            <dt class="text-slate-400">Headcount</dt>
            <dd class="font-medium text-slate-700">{{ vacancy.hiring_requisition.headcount_filled }} / {{ vacancy.hiring_requisition.headcount_requested }}</dd>
          </div>
          <div v-if="vacancy.application_deadline"><dt class="text-slate-400">Deadline</dt><dd class="font-medium text-slate-700">{{ vacancy.application_deadline }}</dd></div>
          <div v-if="vacancy.application_method === 'external'"><dt class="text-slate-400">External URL</dt><dd class="font-medium text-slate-700">{{ vacancy.external_apply_url }}</dd></div>
        </dl>

        <div class="mt-6 space-y-2">
          <div>
            <h3 class="text-xs font-medium uppercase text-slate-400">Deskripsi</h3>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.description }}</p>
          </div>
          <div v-if="vacancy.requirements">
            <h3 class="text-xs font-medium uppercase text-slate-400">Requirements</h3>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ vacancy.requirements }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>