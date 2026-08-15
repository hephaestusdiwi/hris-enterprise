<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface MovementRow {
  id: number
  movement_type: string
  effective_date: string
  status: string
  before_snapshot: Record<string, unknown>
  after_snapshot: Record<string, unknown>
  reason: string | null
  applied_at: string | null
  employee: { id: number; first_name: string; last_name: string | null }
  requested_by: { id: number; name: string } | null
}

const MOVEMENT_TYPE_LABELS: Record<string, string> = {
  transfer: 'Transfer',
  promotion: 'Promotion',
  demotion: 'Demotion',
  contract_change: 'Contract Change',
  probation_confirmed: 'Change Status',
  resignation: 'Resignation',
  rehire: 'Rehire',
}

const STATUS_STYLE: Record<string, string> = {
  pending_approval: 'bg-amber-50 text-amber-700',
  approved: 'bg-sky-50 text-sky-700',
  applied: 'bg-emerald-50 text-emerald-700',
  rejected: 'bg-red-50 text-red-700',
  cancelled: 'bg-slate-100 text-slate-500',
}

const movements = ref<MovementRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

const filters = ref({
  movement_type: '',
  status: '',
  effective_date_from: '',
  effective_date_to: '',
})

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employee-movements', {
      params: {
        movement_type: filters.value.movement_type || undefined,
        status: filters.value.status || undefined,
        effective_date_from: filters.value.effective_date_from || undefined,
        effective_date_to: filters.value.effective_date_to || undefined,
      },
    })
    movements.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat Employee Movement.'
  } finally {
    loading.value = false
  }
}

watch(filters, load, { deep: true })
onMounted(load)

const detailTarget = ref<MovementRow | null>(null)

const FIELD_LABELS: Record<string, string> = {
  company_id: 'Company',
  branch_id: 'Branch',
  department_id: 'Department',
  position_id: 'Position',
  job_level_id: 'Job Level',
  manager_employee_id: 'Manager',
  employment_type_id: 'Employment Type',
  employment_status_id: 'Employment Status',
  contract_start_date: 'Contract Start Date',
  contract_end_date: 'Contract End Date',
  probation_end_date: 'Probation End Date',
  resign_date: 'Resign Date',
  join_date: 'Join Date',
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-5 p-6">
    <div>
      <h1 class="text-lg font-semibold text-slate-900">Employee Movement</h1>
      <p class="mt-0.5 text-sm text-slate-500">Riwayat perubahan lifecycle employee (transfer, promosi, contract, status, dst).</p>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filters.movement_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <option value="">Semua Type</option>
        <option v-for="(label, value) in MOVEMENT_TYPE_LABELS" :key="value" :value="value">{{ label }}</option>
      </select>
      <select v-model="filters.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <option value="">Semua Status</option>
        <option value="pending_approval">Pending Approval</option>
        <option value="approved">Approved</option>
        <option value="applied">Applied</option>
        <option value="rejected">Rejected</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <input v-model="filters.effective_date_from" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      <span class="self-center text-xs text-slate-400">s/d</span>
      <input v-model="filters.effective_date_to" type="date" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="movements.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada Employee Movement yang tercatat.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-100 bg-slate-50/60 text-xs uppercase tracking-wider text-slate-400">
            <tr>
              <th class="px-5 py-3 font-medium">Employee</th>
              <th class="px-3 py-3 font-medium">Type</th>
              <th class="px-3 py-3 font-medium">Effective Date</th>
              <th class="px-3 py-3 font-medium">Status</th>
              <th class="px-3 py-3 font-medium">Requested By</th>
              <th class="px-3 py-3 font-medium"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="m in movements" :key="m.id" class="hover:bg-slate-50/50">
              <td class="px-5 py-3.5">
                <RouterLink :to="{ name: 'employee-detail', params: { id: m.employee.id } }" class="font-medium text-slate-800 hover:text-primary hover:underline">
                  {{ m.employee.first_name }} {{ m.employee.last_name }}
                </RouterLink>
              </td>
              <td class="px-3 py-3.5 text-slate-600">{{ MOVEMENT_TYPE_LABELS[m.movement_type] ?? m.movement_type }}</td>
              <td class="px-3 py-3.5 text-slate-600">{{ m.effective_date }}</td>
              <td class="px-3 py-3.5">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="STATUS_STYLE[m.status] ?? 'bg-slate-100 text-slate-500'">
                  {{ m.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-3 py-3.5 text-slate-500">{{ m.requested_by?.name ?? '-' }}</td>
              <td class="px-3 py-3.5 text-right">
                <button type="button" @click="detailTarget = m" class="text-xs font-medium text-primary-dark hover:underline">Detail</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Detail before/after -->
    <div v-if="detailTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-800">
            {{ MOVEMENT_TYPE_LABELS[detailTarget.movement_type] }} — {{ detailTarget.employee.first_name }} {{ detailTarget.employee.last_name }}
          </h2>
          <button type="button" @click="detailTarget = null"><X class="h-4 w-4 text-slate-400" /></button>
        </div>

        <div class="mb-4 flex items-center gap-3 text-xs text-slate-500">
          <span>Effective: {{ detailTarget.effective_date }}</span>
          <span class="rounded-full px-2.5 py-0.5 font-medium" :class="STATUS_STYLE[detailTarget.status]">{{ detailTarget.status.replace('_', ' ') }}</span>
        </div>

        <table class="w-full text-left text-xs">
          <thead class="text-slate-400">
            <tr>
              <th class="pb-2 font-medium">Field</th>
              <th class="pb-2 font-medium">Before</th>
              <th class="pb-2 font-medium">After</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(after, field) in detailTarget.after_snapshot" :key="field">
              <td class="py-2 font-medium text-slate-600">{{ FIELD_LABELS[field] ?? field }}</td>
              <td class="py-2 text-slate-400">{{ detailTarget.before_snapshot[field] ?? '-' }}</td>
              <td class="py-2" :class="detailTarget.before_snapshot[field] !== after ? 'font-semibold text-primary-dark' : 'text-slate-500'">
                {{ after ?? '-' }}
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="detailTarget.reason" class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
          <span class="font-medium">Reason:</span> {{ detailTarget.reason }}
        </p>
      </div>
    </div>
  </div>
</template>
