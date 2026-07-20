<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X, Building2, Clock, Coffee, Timer } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface ShiftRow {
  id: number
  name: string
  code: string
  start_time: string
  end_time: string
  is_overnight: boolean
  break_start_time: string | null
  break_end_time: string | null
  late_tolerance_minutes: number
  check_in_before_minutes: number
  check_out_after_minutes: number
  is_active: boolean
  company: Company
}

const shifts = ref<ShiftRow[]>([])
const companies = ref<Company[]>([])
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
  start_time: '',
  end_time: '',
  is_overnight: false,
  break_start_time: '',
  break_end_time: '',
  late_tolerance_minutes: 0,
  check_in_before_minutes: 0,
  check_out_after_minutes: 0,
  is_active: true,
})

function timeShort(value: string | null) {
  return value ? value.slice(0, 5) : '-'
}

// --- Timeline / duration helpers (buat preview di modal) ---
function toMinutes(t: string) {
  if (!t) return 0
  const [h, m] = t.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

function formatDuration(mins: number) {
  if (mins <= 0) return '0j'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m ? `${h}j ${m}m` : `${h}j`
}

const startMinutes = computed(() => toMinutes(form.start_time))
const endMinutes = computed(() => {
  let end = toMinutes(form.end_time)
  if (form.is_overnight && end <= startMinutes.value) end += 24 * 60
  return end
})
const totalDuration = computed(() => {
  if (!form.start_time || !form.end_time) return 0
  return Math.max(endMinutes.value - startMinutes.value, 0)
})
const breakDuration = computed(() => {
  if (!form.break_start_time || !form.break_end_time) return 0
  let bStart = toMinutes(form.break_start_time)
  let bEnd = toMinutes(form.break_end_time)
  if (bEnd <= bStart) bEnd += 24 * 60
  return Math.max(bEnd - bStart, 0)
})
const effectiveDuration = computed(() => Math.max(totalDuration.value - breakDuration.value, 0))

// Posisi & lebar bar dalam persen, diskalakan ke 24 jam (1440 menit)
const workBarStyle = computed(() => ({
  left: `${(startMinutes.value / 1440) * 100}%`,
  width: `${(totalDuration.value / 1440) * 100}%`,
}))
const breakBarStyle = computed(() => {
  if (!form.break_start_time || !form.break_end_time) return null
  const bStart = toMinutes(form.break_start_time)
  return {
    left: `${((bStart - startMinutes.value) / 1440) * 100}%`,
    width: `${(breakDuration.value / 1440) * 100}%`,
  }
})

async function loadShifts() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/shifts')
    shifts.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar shift.'
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

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.name = ''
  form.code = ''
  form.start_time = ''
  form.end_time = ''
  form.is_overnight = false
  form.break_start_time = ''
  form.break_end_time = ''
  form.late_tolerance_minutes = 0
  form.check_in_before_minutes = 0
  form.check_out_after_minutes = 0
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(shift: ShiftRow) {
  isEditing.value = true
  formError.value = ''
  form.id = shift.id
  form.company_id = shift.company.id
  form.name = shift.name
  form.code = shift.code
  form.start_time = timeShort(shift.start_time)
  form.end_time = timeShort(shift.end_time)
  form.is_overnight = shift.is_overnight
  form.break_start_time = shift.break_start_time ? timeShort(shift.break_start_time) : ''
  form.break_end_time = shift.break_end_time ? timeShort(shift.break_end_time) : ''
  form.late_tolerance_minutes = shift.late_tolerance_minutes
  form.check_in_before_minutes = shift.check_in_before_minutes
  form.check_out_after_minutes = shift.check_out_after_minutes
  form.is_active = shift.is_active
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
    start_time: form.start_time,
    end_time: form.end_time,
    is_overnight: form.is_overnight,
    break_start_time: form.break_start_time || null,
    break_end_time: form.break_end_time || null,
    late_tolerance_minutes: form.late_tolerance_minutes,
    check_in_before_minutes: form.check_in_before_minutes,
    check_out_after_minutes: form.check_out_after_minutes,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/shifts/${form.id}`, payload)
    } else {
      await apiClient.post('/api/shifts', payload)
    }

    showModal.value = false
    await loadShifts()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(shift: ShiftRow) {
  if (!confirm(`Hapus shift "${shift.name}"?`)) return

  try {
    await apiClient.delete(`/api/shifts/${shift.id}`)
    await loadShifts()
  } catch {
    alert('Gagal menghapus shift.')
  }
}

onMounted(() => {
  loadShifts()
  loadCompanies()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Shift</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola jam kerja, istirahat, dan toleransi absensi.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Shift
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat shift.
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
            <th class="px-5 py-3 font-medium text-slate-500">Jam Kerja</th>
            <th class="px-5 py-3 font-medium text-slate-500">Istirahat</th>
            <th class="px-5 py-3 font-medium text-slate-500">Toleransi Telat</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="shift in shifts"
            :key="shift.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 font-medium text-slate-800">
              {{ shift.name }}
              <span v-if="shift.is_overnight" class="ml-1.5 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-600">
                Lintas Hari
              </span>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ timeShort(shift.start_time) }} - {{ timeShort(shift.end_time) }}</td>
            <td class="px-5 py-3.5 text-slate-500">
              <span v-if="shift.break_start_time">{{ timeShort(shift.break_start_time) }} - {{ timeShort(shift.break_end_time) }}</span>
              <span v-else>-</span>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ shift.late_tolerance_minutes }} menit</td>
            <td class="px-5 py-3.5 text-slate-500">{{ shift.company.name }}</td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(shift)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(shift)"
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
        <div class="flex max-h-full w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Shift' : 'Tambah Shift' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-7 overflow-y-auto px-6 py-5">
            <!-- Informasi Dasar -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Informasi Dasar</h3>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                  <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Shift</label>
                    <input v-model="form.name" type="text" required placeholder="mis. Shift Pagi" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                    <input v-model="form.code" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                </div>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-slate-700">Status Aktif</p>
                    <p class="text-xs text-slate-400">Shift nonaktif tidak bisa dipilih saat penjadwalan</p>
                  </div>
                  <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>
              </div>
            </div>

            <!-- Jadwal Kerja -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Clock class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Jadwal Kerja</h3>
              </div>

              <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Jam Masuk</label>
                    <input v-model="form.start_time" type="time" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Jam Pulang</label>
                    <input v-model="form.end_time" type="time" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                </div>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-slate-700">Shift Lintas Hari</p>
                    <p class="text-xs text-slate-400">Jam pulang berada di hari berikutnya</p>
                  </div>
                  <input v-model="form.is_overnight" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <!-- Timeline preview -->
                <div v-if="form.start_time && form.end_time" class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                  <div class="relative h-2 w-full overflow-hidden rounded-full bg-slate-200">
                    <div class="absolute top-0 h-full rounded-full bg-primary" :style="workBarStyle"></div>
                    <div v-if="breakBarStyle" class="absolute top-0 h-full rounded-full bg-amber-400" :style="breakBarStyle"></div>
                  </div>
                  <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                    <span>00.00</span>
                    <span class="font-medium text-slate-700">
                      Efektif {{ formatDuration(effectiveDuration) }}
                      <span v-if="breakDuration" class="text-slate-400">(istirahat {{ formatDuration(breakDuration) }})</span>
                    </span>
                    <span>24.00</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Istirahat -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Coffee class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Istirahat</h3>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Mulai Istirahat</label>
                  <input v-model="form.break_start_time" type="time" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Selesai Istirahat</label>
                  <input v-model="form.break_end_time" type="time" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
              </div>
            </div>

            <!-- Toleransi & Aturan Absensi -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Timer class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Toleransi & Aturan Absensi</h3>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Toleransi Telat</label>
                  <div class="relative">
                    <input v-model.number="form.late_tolerance_minutes" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-11 text-sm focus:border-primary focus:outline-none" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">menit</span>
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Check-in Sebelum</label>
                  <div class="relative">
                    <input v-model.number="form.check_in_before_minutes" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-11 text-sm focus:border-primary focus:outline-none" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">menit</span>
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Check-out Setelah</label>
                  <div class="relative">
                    <input v-model.number="form.check_out_after_minutes" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-11 text-sm focus:border-primary focus:outline-none" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">menit</span>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
            <p v-if="totalDuration" class="text-xs text-slate-400">
              Total durasi <span class="font-medium text-slate-600">{{ formatDuration(totalDuration) }}</span>
            </p>
            <p v-else></p>
            <button
              @click="handleSubmit"
              :disabled="saving"
              class="rounded-xl bg-primary px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>