<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface Shift {
  id: number
  name: string
}

interface ScheduleDetail {
  day_of_week: number
  shift_id: number | null
  shift: Shift | null
}

interface ScheduleRow {
  id: number
  name: string
  code: string
  is_active: boolean
  company: Company
  details: ScheduleDetail[]
}

const DAYS = [
  { value: 1, label: 'Senin' },
  { value: 2, label: 'Selasa' },
  { value: 3, label: 'Rabu' },
  { value: 4, label: 'Kamis' },
  { value: 5, label: 'Jumat' },
  { value: 6, label: 'Sabtu' },
  { value: 7, label: 'Minggu' },
]

const schedules = ref<ScheduleRow[]>([])
const companies = ref<Company[]>([])
const shifts = ref<Shift[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: 0,
  name: '',
  code: '',
  is_active: true,
  details: DAYS.map((d) => ({ day_of_week: d.value, shift_id: null as number | null })),
})

function dayLabel(day: number) {
  return DAYS.find((d) => d.value === day)?.label ?? day
}

function scheduleSummary(row: ScheduleRow) {
  return row.details
    .slice()
    .sort((a, b) => a.day_of_week - b.day_of_week)
    .map((d) => (d.shift ? d.shift.name.slice(0, 3) : 'Libur'))
    .join(' · ')
}

async function loadSchedules() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/working-schedules')
    schedules.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar working schedule.'
  } finally {
    loading.value = false
  }
}

async function loadCompanies() {
  try {
    const response = await apiClient.get('/api/companies')
    companies.value = response.data.data.data
  } catch {
    companies.value = []
  }
}

async function loadShifts() {
  try {
    const response = await apiClient.get('/api/shifts')
    shifts.value = response.data.data.data
  } catch {
    shifts.value = []
  }
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.name = ''
  form.code = ''
  form.is_active = true
  form.details = DAYS.map((d) => ({ day_of_week: d.value, shift_id: null }))
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(schedule: ScheduleRow) {
  isEditing.value = true
  formError.value = ''
  form.id = schedule.id
  form.company_id = schedule.company.id
  form.name = schedule.name
  form.code = schedule.code
  form.is_active = schedule.is_active
  form.details = DAYS.map((d) => {
    const existing = schedule.details.find((detail) => detail.day_of_week === d.value)
    return { day_of_week: d.value, shift_id: existing?.shift_id ?? null }
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  const payload = {
    company_id: form.company_id,
    name: form.name,
    code: form.code,
    is_active: form.is_active,
    details: form.details,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/working-schedules/${form.id}`, payload)
    } else {
      await apiClient.post('/api/working-schedules', payload)
    }

    showModal.value = false
    await loadSchedules()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(schedule: ScheduleRow) {
  if (!confirm(`Hapus working schedule "${schedule.name}"?`)) return

  try {
    await apiClient.delete(`/api/working-schedules/${schedule.id}`)
    await loadSchedules()
  } catch {
    alert('Gagal menghapus working schedule.')
  }
}

onMounted(() => {
  loadSchedules()
  loadCompanies()
  loadShifts()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Working Schedule</h1>
        <p class="mt-1 text-sm text-slate-500">Template jadwal kerja mingguan, dijadikan default schedule untuk employee.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0 || shifts.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Working Schedule
      </button>
    </div>

    <p v-if="(companies.length === 0 || shifts.length === 0) && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Pastikan sudah ada Company dan Shift sebelum membuat working schedule.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Pola Mingguan (Sen-Min)</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="schedule in schedules"
            :key="schedule.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ schedule.name }}</td>
            <td class="px-5 py-3.5 text-xs text-slate-500">{{ scheduleSummary(schedule) }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ schedule.company.name }}</td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="schedule.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'"
              >
                {{ schedule.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(schedule)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(schedule)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                >
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8"
      >
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Working Schedule' : 'Tambah Working Schedule' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Template</label>
                <input v-model="form.name" type="text" required placeholder="mis. Jadwal Reguler" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input v-model="form.code" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Pola Mingguan</label>
              <div class="space-y-2 rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <div
                  v-for="(detail, index) in form.details"
                  :key="detail.day_of_week"
                  class="grid grid-cols-3 items-center gap-3"
                >
                  <span class="text-sm font-medium text-slate-700">{{ dayLabel(detail.day_of_week) }}</span>
                  <select
                    v-model="form.details[index]!.shift_id"
                    class="col-span-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm focus:border-primary focus:outline-none"
                  >
                    <option :value="null">Libur</option>
                    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
              </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
              Aktif
            </label>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button
              @click="handleSubmit"
              :disabled="saving"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>