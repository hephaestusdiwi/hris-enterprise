<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

type GenderRestriction = 'male' | 'female' | null

interface LeaveTypeRow {
  id: number
  company_id: number
  name: string
  code: string
  description: string | null
  color: string | null
  is_paid: boolean
  max_days_per_year: number | null
  min_service_months: number
  requires_attachment: boolean
  gender_restriction: GenderRestriction
  carry_over_allowed: boolean
  carry_over_max_days: number | null
  carry_over_expiry_month: number | null
  requires_approval: boolean
  allow_half_day: boolean
  allow_hourly: boolean
  requires_balance: boolean
  is_active: boolean
  company: Company
}

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const leaveTypes = ref<LeaveTypeRow[]>([])
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
  description: '',
  color: '#3B82F6',
  is_paid: true,
  max_days_per_year: null as number | null,
  min_service_months: 0,
  requires_attachment: false,
  gender_restriction: '' as '' | GenderRestriction,
  carry_over_allowed: false,
  carry_over_max_days: null as number | null,
  carry_over_expiry_month: null as number | null,
  requires_approval: true,
  allow_half_day: false,
  allow_hourly: false,
  requires_balance: true,
  is_active: true,
})

async function loadLeaveTypes() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/leave-types')
    leaveTypes.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar leave type.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const response = await apiClient.get('/api/companies')
  companies.value = response.data.data.data
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.name = ''
  form.code = ''
  form.description = ''
  form.color = '#3B82F6'
  form.is_paid = true
  form.max_days_per_year = null
  form.min_service_months = 0
  form.requires_attachment = false
  form.gender_restriction = ''
  form.carry_over_allowed = false
  form.carry_over_max_days = null
  form.carry_over_expiry_month = null
  form.requires_approval = true
  form.allow_half_day = false
  form.allow_hourly = false
  form.requires_balance = true
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(row: LeaveTypeRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.company_id
  form.name = row.name
  form.code = row.code
  form.description = row.description ?? ''
  form.color = row.color ?? '#3B82F6'
  form.is_paid = row.is_paid
  form.max_days_per_year = row.max_days_per_year
  form.min_service_months = row.min_service_months
  form.requires_attachment = row.requires_attachment
  form.gender_restriction = row.gender_restriction ?? ''
  form.carry_over_allowed = row.carry_over_allowed
  form.carry_over_max_days = row.carry_over_max_days
  form.carry_over_expiry_month = row.carry_over_expiry_month
  form.requires_approval = row.requires_approval
  form.allow_half_day = row.allow_half_day
  form.allow_hourly = row.allow_hourly
  form.requires_balance = row.requires_balance
  form.is_active = row.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const payload = {
    company_id: form.company_id,
    name: form.name,
    code: form.code,
    description: form.description || null,
    color: form.color || null,
    is_paid: form.is_paid,
    max_days_per_year: form.max_days_per_year,
    min_service_months: form.min_service_months,
    requires_attachment: form.requires_attachment,
    gender_restriction: form.gender_restriction || null,
    carry_over_allowed: form.carry_over_allowed,
    carry_over_max_days: form.carry_over_allowed ? form.carry_over_max_days : null,
    carry_over_expiry_month: form.carry_over_allowed ? form.carry_over_expiry_month : null,
    requires_approval: form.requires_approval,
    allow_half_day: form.allow_half_day,
    allow_hourly: form.allow_hourly,
    requires_balance: form.requires_balance,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/leave-types/${form.id}`, payload)
    } else {
      await apiClient.post('/api/leave-types', payload)
    }

    showModal.value = false
    await loadLeaveTypes()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: LeaveTypeRow) {
  if (!confirm(`Hapus leave type "${row.name}"?`)) return

  try {
    await apiClient.delete(`/api/leave-types/${row.id}`)
    await loadLeaveTypes()
  } catch {
    alert('Gagal menghapus leave type.')
  }
}

onMounted(() => {
  loadLeaveTypes()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Leave Type</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola jenis cuti dan konfigurasinya (Annual, Sick, Maternity, dll).</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Leave Type
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>
    <div v-else-if="leaveTypes.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada leave type.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Kuota/Tahun</th>
            <th class="px-5 py-3 font-medium text-slate-500">Sifat</th>
            <th class="px-5 py-3 font-medium text-slate-500">Syarat</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in leaveTypes"
            :key="row.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2.5">
                <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: row.color ?? '#94A3B8' }"></span>
                <div>
                  <p class="font-medium text-slate-800">{{ row.name }}</p>
                  <p class="text-xs text-slate-400">{{ row.code }} · {{ row.company.name }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-3.5 text-slate-500">
              {{ row.max_days_per_year !== null ? `${row.max_days_per_year} hari` : 'Tidak dibatasi' }}
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap gap-1">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="row.is_paid ? 'bg-primary-soft text-primary-dark' : 'bg-slate-100 text-slate-500'">
                  {{ row.is_paid ? 'Paid' : 'Unpaid' }}
                </span>
                <span v-if="row.gender_restriction" class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-600">
                  {{ row.gender_restriction === 'female' ? 'Wanita' : 'Pria' }}
                </span>
                <span v-if="!row.requires_balance" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                  No Balance
                </span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-xs text-slate-500">
              <div class="space-y-0.5">
                <p v-if="row.min_service_months > 0">Min. {{ row.min_service_months }} bln kerja</p>
                <p v-if="row.requires_attachment">Wajib lampiran</p>
                <p v-if="row.carry_over_allowed">
                  Carry over {{ row.carry_over_max_days ?? '-' }} hari
                  <span v-if="row.carry_over_expiry_month">(exp. {{ monthNames[row.carry_over_expiry_month - 1] }})</span>
                </p>
              </div>
            </td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="row.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
              >
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(row)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(row)"
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
              {{ isEditing ? 'Edit Leave Type' : 'Tambah Leave Type' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
            <!-- Dasar -->
            <div>
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Dasar</h3>
              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                  <select
                    v-model.number="form.company_id"
                    required
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
                    <input v-model="form.name" type="text" required placeholder="Annual Leave" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                    <input v-model="form.code" type="text" required placeholder="ANNUAL" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                </div>

                <div class="grid grid-cols-[1fr_auto] gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
                    <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Warna</label>
                    <input v-model="form.color" type="color" class="h-[42px] w-14 rounded-xl border border-slate-200 p-1" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Kelayakan -->
            <div>
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Kelayakan &amp; Kuota</h3>
              <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kuota per Tahun (hari)</label>
                    <input v-model.number="form.max_days_per_year" type="number" min="0" placeholder="Kosongkan = tidak dibatasi" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Min. Masa Kerja (bulan)</label>
                    <input v-model.number="form.min_service_months" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Batasan Gender</label>
                  <select v-model="form.gender_restriction" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="">Semua Gender</option>
                    <option value="male">Khusus Laki-laki</option>
                    <option value="female">Khusus Perempuan</option>
                  </select>
                </div>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Wajib Lampiran (mis. surat dokter)</p>
                  <input v-model="form.requires_attachment" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>
              </div>
            </div>

            <!-- Carry Over -->
            <div>
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Carry Over</h3>
              <div class="space-y-3">
                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Izinkan Carry Over</p>
                  <input v-model="form.carry_over_allowed" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <div v-if="form.carry_over_allowed" class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Maks. Hari Dibawa</label>
                    <input v-model.number="form.carry_over_max_days" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kedaluwarsa (Bulan)</label>
                    <select v-model.number="form.carry_over_expiry_month" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                      <option :value="null">-</option>
                      <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Opsi Pengajuan -->
            <div>
              <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Opsi Pengajuan</h3>
              <div class="grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Berbayar (Paid)</p>
                  <input v-model="form.is_paid" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Butuh Approval</p>
                  <input v-model="form.requires_approval" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Potong Saldo Cuti</p>
                  <input v-model="form.requires_balance" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Izinkan Setengah Hari</p>
                  <input v-model="form.allow_half_day" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Izinkan Per Jam</p>
                  <input v-model="form.allow_hourly" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <p class="text-sm font-medium text-slate-700">Aktif</p>
                  <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>
              </div>
            </div>

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