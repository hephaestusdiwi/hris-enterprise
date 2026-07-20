<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X, Building2, ShieldCheck, MapPin, Camera, Smartphone } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

interface Branch {
  id: number
  name: string
  company_id: number
}

interface AttendanceSettingRow {
  id: number
  company_id: number
  branch_id: number | null
  require_photo: boolean
  require_location: boolean
  office_latitude: number | null
  office_longitude: number | null
  location_radius_meters: number
  allow_mobile_checkin: boolean
  company: Company
  branch: Branch | null
}

const settings = ref<AttendanceSettingRow[]>([])
const companies = ref<Company[]>([])
const branches = ref<Branch[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  company_id: 0,
  branch_id: null as number | null,
  require_photo: false,
  require_location: false,
  office_latitude: null as number | null,
  office_longitude: null as number | null,
  location_radius_meters: 100,
  allow_mobile_checkin: true,
})

const filteredBranches = computed(() => branches.value.filter((b) => b.company_id === form.company_id))

const radiusRingSize = computed(() => {
  const size = Math.min(Math.max((form.location_radius_meters || 0) / 10, 24), 72)
  return `${size}px`
})

async function loadSettings() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/attendance-settings')
    settings.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar attendance setting.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, branchRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/branches'),
  ])
  companies.value = companyRes.data.data.data
  branches.value = branchRes.data.data.data
}

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.branch_id = null
  form.require_photo = false
  form.require_location = false
  form.office_latitude = null
  form.office_longitude = null
  form.location_radius_meters = 100
  form.allow_mobile_checkin = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(row: AttendanceSettingRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.company_id
  form.branch_id = row.branch_id
  form.require_photo = row.require_photo
  form.require_location = row.require_location
  form.office_latitude = row.office_latitude
  form.office_longitude = row.office_longitude
  form.location_radius_meters = row.location_radius_meters
  form.allow_mobile_checkin = row.allow_mobile_checkin
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function validateForm(): string {
  if (form.require_location) {
    if (form.office_latitude === null || form.office_longitude === null) {
      return 'Latitude dan longitude wajib diisi karena verifikasi lokasi aktif.'
    }
    if (form.office_latitude < -90 || form.office_latitude > 90) {
      return 'Latitude harus di antara -90 dan 90.'
    }
    if (form.office_longitude < -180 || form.office_longitude > 180) {
      return 'Longitude harus di antara -180 dan 180.'
    }
  }
  if (!form.location_radius_meters || form.location_radius_meters < 1) {
    return 'Radius absensi minimal 1 meter.'
  }
  return ''
}

async function handleSubmit() {
  formError.value = validateForm()
  if (formError.value) return

  saving.value = true

  const payload = {
    company_id: form.company_id,
    branch_id: form.branch_id,
    require_photo: form.require_photo,
    require_location: form.require_location,
    office_latitude: form.require_location ? form.office_latitude : null,
    office_longitude: form.require_location ? form.office_longitude : null,
    location_radius_meters: form.location_radius_meters,
    allow_mobile_checkin: form.allow_mobile_checkin,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/attendance-settings/${form.id}`, payload)
    } else {
      await apiClient.post('/api/attendance-settings', payload)
    }

    showModal.value = false
    await loadSettings()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: AttendanceSettingRow) {
  const label = row.branch?.name ?? `${row.company.name} (Default)`
  if (!confirm(`Hapus attendance setting "${label}"?`)) return

  try {
    await apiClient.delete(`/api/attendance-settings/${row.id}`)
    await loadSettings()
  } catch {
    alert('Gagal menghapus attendance setting.')
  }
}

onMounted(() => {
  loadSettings()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Attendance Setting</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola aturan verifikasi dan lokasi absensi per company/branch.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Setting
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu sebelum membuat attendance setting.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">Branch</th>
            <th class="px-5 py-3 font-medium text-slate-500">Verifikasi</th>
            <th class="px-5 py-3 font-medium text-slate-500">Radius</th>
            <th class="px-5 py-3 font-medium text-slate-500">Mobile Check-in</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in settings"
            :key="row.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ row.company.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">
              <span v-if="row.branch">{{ row.branch.name }}</span>
              <span v-else class="rounded-full bg-slate-100 px-2 py-0.5 text-xs italic text-slate-500">Default Company</span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-1.5">
                <span
                  class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                  :class="row.require_photo ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
                >
                  <Camera class="h-3 w-3" :stroke-width="2" />
                  Foto
                </span>
                <span
                  class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                  :class="row.require_location ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
                >
                  <MapPin class="h-3 w-3" :stroke-width="2" />
                  Lokasi
                </span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-slate-500">
              <span v-if="row.require_location">{{ row.location_radius_meters }} m</span>
              <span v-else>-</span>
            </td>
            <td class="px-5 py-3.5">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="row.allow_mobile_checkin ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'"
              >
                {{ row.allow_mobile_checkin ? 'Aktif' : 'Nonaktif' }}
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
        <div class="flex max-h-full w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Attendance Setting' : 'Tambah Attendance Setting' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-7 overflow-y-auto px-6 py-5">
            <!-- Company & Branch -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Company & Branch</h3>
              </div>

              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                  <select
                    v-model.number="form.company_id"
                    required
                    @change="form.branch_id = null"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Branch</label>
                  <select v-model="form.branch_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option :value="null">Default Company</option>
                    <option v-for="b in filteredBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                  <p class="mt-1 text-xs text-slate-400">
                    Dikosongkan (Default Company) berarti setting berlaku untuk seluruh branch yang belum punya setting sendiri. Cuma boleh 1 default per company.
                  </p>
                </div>
              </div>
            </div>

            <!-- Verifikasi Kehadiran -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <ShieldCheck class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Verifikasi Kehadiran</h3>
              </div>

              <div class="space-y-3">
                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <Camera class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
                    <div>
                      <p class="text-sm font-medium text-slate-700">Wajib Foto</p>
                      <p class="text-xs text-slate-400">Karyawan harus ambil foto saat check-in/check-out</p>
                    </div>
                  </div>
                  <input v-model="form.require_photo" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <MapPin class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
                    <div>
                      <p class="text-sm font-medium text-slate-700">Wajib Lokasi</p>
                      <p class="text-xs text-slate-400">Check-in/check-out cuma bisa dalam radius kantor</p>
                    </div>
                  </div>
                  <input v-model="form.require_location" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <Smartphone class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
                    <div>
                      <p class="text-sm font-medium text-slate-700">Izinkan Check-in via Mobile</p>
                      <p class="text-xs text-slate-400">Karyawan bisa absen lewat aplikasi mobile</p>
                    </div>
                  </div>
                  <input v-model="form.allow_mobile_checkin" type="checkbox" class="peer sr-only" />
                  <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
                </label>
              </div>
            </div>

            <!-- Lokasi & Radius -->
            <div>
              <div class="mb-3 flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                  <MapPin class="h-4 w-4" :stroke-width="1.75" />
                </div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lokasi & Radius</h3>
              </div>

              <div class="space-y-3">
                <Transition
                  enter-active-class="transition-all duration-150 ease-out"
                  enter-from-class="opacity-0 -translate-y-1"
                  enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition-all duration-100 ease-in"
                  leave-from-class="opacity-100"
                  leave-to-class="opacity-0"
                >
                  <div v-if="form.require_location" class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Latitude</label>
                      <input v-model.number="form.office_latitude" type="number" step="0.000001" min="-90" max="90" placeholder="-6.200000" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                    </div>
                    <div>
                      <label class="mb-1 block text-sm font-medium text-slate-700">Longitude</label>
                      <input v-model.number="form.office_longitude" type="number" step="0.000001" min="-180" max="180" placeholder="106.816666" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                    </div>
                  </div>
                  <p v-else class="text-xs text-slate-400">Aktifkan "Wajib Lokasi" buat isi koordinat kantor.</p>
                </Transition>

                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Radius Absensi</label>
                  <div class="relative">
                    <input v-model.number="form.location_radius_meters" type="number" min="1" required class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-11 text-sm focus:border-primary focus:outline-none" />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400">meter</span>
                  </div>
                </div>

                <div class="flex items-center gap-4 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                  <div class="relative flex h-20 w-20 shrink-0 items-center justify-center">
                    <div
                      class="absolute rounded-full border-2 border-dashed border-primary/40"
                      :style="{ width: radiusRingSize, height: radiusRingSize }"
                    ></div>
                    <div class="absolute h-2.5 w-2.5 rounded-full bg-primary"></div>
                  </div>
                  <p class="text-xs text-slate-500">
                    Karyawan cuma bisa check-in dalam radius
                    <span class="font-medium text-slate-700">{{ form.location_radius_meters || 0 }} meter</span>
                    dari titik kantor.
                  </p>
                </div>
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