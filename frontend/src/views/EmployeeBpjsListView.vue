<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { X, ShieldCheck, ShieldAlert } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee { id: number; first_name: string; last_name: string | null; company_id: number }

interface Participation {
  id: number
  employee_id: number
  bpjs_health_number: string | null
  bpjs_health_family_count: number
  bpjs_health_start_date: string | null
  bpjs_health_cost_bearer: string
  bpjs_employment_number: string | null
  bpjs_registration_npp_number: string | null
  bpjs_employment_start_date: string | null
  jht_cost_bearer: string
}

interface Registration { id: number; company_id: number; npp_number: string; label: string | null }

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const employees = ref<Employee[]>([])
const participations = ref<Participation[]>([])
const registrations = ref<Registration[]>([])
const loading = ref(true)

const participationByEmployeeId = computed(() => {
  const map = new Map<number, Participation>()
  participations.value.forEach((p) => map.set(p.employee_id, p))
  return map
})

async function loadAll() {
  loading.value = true
  const [empRes, partRes, regRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
    apiClient.get('/api/bpjs/employee-participations'),
    apiClient.get('/api/bpjs/company-registrations'),
  ])
  employees.value = empRes.data.data.data
  participations.value = partRes.data.data
  registrations.value = regRes.data.data
  loading.value = false
}

// ---------- EDIT FORM ----------
const showModal = ref(false)
const targetEmployee = ref<Employee | null>(null)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  bpjs_health_number: '',
  bpjs_health_family_count: 0,
  bpjs_health_start_date: '',
  bpjs_health_cost_bearer: 'default',
  bpjs_employment_number: '',
  bpjs_registration_npp_number: '',
  bpjs_employment_start_date: '',
  jht_cost_bearer: 'default',
})

function availableRegistrations(employee: Employee) {
  return registrations.value.filter((r) => r.company_id === employee.company_id)
}

function openEdit(employee: Employee) {
  targetEmployee.value = employee
  formError.value = ''
  const existing = participationByEmployeeId.value.get(employee.id)
  form.bpjs_health_number = existing?.bpjs_health_number ?? ''
  form.bpjs_health_family_count = existing?.bpjs_health_family_count ?? 0
  form.bpjs_health_start_date = existing?.bpjs_health_start_date?.slice(0, 10) ?? ''
  form.bpjs_health_cost_bearer = existing?.bpjs_health_cost_bearer ?? 'default'
  form.bpjs_employment_number = existing?.bpjs_employment_number ?? ''
  form.bpjs_registration_npp_number = existing?.bpjs_registration_npp_number ?? ''
  form.bpjs_employment_start_date = existing?.bpjs_employment_start_date?.slice(0, 10) ?? ''
  form.jht_cost_bearer = existing?.jht_cost_bearer ?? 'default'
  showModal.value = true
}

async function submitForm() {
  if (!targetEmployee.value) return
  saving.value = true
  formError.value = ''
  try {
    await apiClient.put(`/api/bpjs/employee-participations/${targetEmployee.value.id}`, form)
    showModal.value = false
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan kepesertaan BPJS.'
  } finally {
    saving.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Kepesertaan BPJS Karyawan</h1>
      <p class="mt-1 text-sm text-slate-500">Nomor kepesertaan & pengaturan cost-bearer per karyawan. Tarif & formula diatur di Pengaturan BPJS.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">BPJS Kesehatan</th>
            <th class="px-5 py-3 font-medium text-slate-500">BPJS Ketenagakerjaan</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in employees" :key="emp.id" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50" @click="openEdit(emp)">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(emp) }}</td>
            <td class="px-5 py-3.5">
              <span v-if="participationByEmployeeId.get(emp.id)?.bpjs_health_number" class="flex items-center gap-1.5 text-slate-600">
                <ShieldCheck class="h-3.5 w-3.5 text-emerald-500" :stroke-width="1.75" /> {{ participationByEmployeeId.get(emp.id)?.bpjs_health_number }}
              </span>
              <span v-else class="flex items-center gap-1.5 text-slate-400">
                <ShieldAlert class="h-3.5 w-3.5" :stroke-width="1.75" /> Belum terdaftar
              </span>
            </td>
            <td class="px-5 py-3.5">
              <span v-if="participationByEmployeeId.get(emp.id)?.bpjs_employment_number" class="flex items-center gap-1.5 text-slate-600">
                <ShieldCheck class="h-3.5 w-3.5 text-emerald-500" :stroke-width="1.75" /> {{ participationByEmployeeId.get(emp.id)?.bpjs_employment_number }}
              </span>
              <span v-else class="flex items-center gap-1.5 text-slate-400">
                <ShieldAlert class="h-3.5 w-3.5" :stroke-width="1.75" /> Belum terdaftar
              </span>
            </td>
            <td class="px-5 py-3.5 text-right text-xs font-medium text-primary-dark">Edit</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal && targetEmployee" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">BPJS — {{ employeeName(targetEmployee) }}</h2>
            <button @click="showModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="submitForm" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">BPJS Kesehatan</p>
              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nomor BPJS Kesehatan</label>
                  <input v-model="form.bpjs_health_number" maxlength="20" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Jumlah Tanggungan</label>
                    <input v-model.number="form.bpjs_health_family_count" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                    <input v-model="form.bpjs_health_start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Cost Bearer</label>
                  <select v-model="form.bpjs_health_cost_bearer" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="default">Default (ikut pengaturan company)</option>
                    <option value="company_borne">Ditanggung Company</option>
                    <option value="employee_borne">Ditanggung Karyawan</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">BPJS Ketenagakerjaan (JHT / JKK / JKM)</p>
              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Nomor KPJ</label>
                  <input v-model="form.bpjs_employment_number" maxlength="20" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">NPP (menentukan kelas risiko JKK)</label>
                  <select v-model="form.bpjs_registration_npp_number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="">- Pilih NPP -</option>
                    <option v-for="r in availableRegistrations(targetEmployee)" :key="r.id" :value="r.npp_number">{{ r.npp_number }}{{ r.label ? ` — ${r.label}` : '' }}</option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                  <input v-model="form.bpjs_employment_start_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-slate-700">JHT Cost Bearer</label>
                  <select v-model="form.jht_cost_bearer" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option value="default">Default (ikut pengaturan company)</option>
                    <option value="company_borne">Ditanggung Company</option>
                    <option value="employee_borne">Ditanggung Karyawan</option>
                    <option value="not_participating">Tidak Diikutkan</option>
                  </select>
                </div>
                <p class="text-xs text-slate-400">JKK & JKM otomatis 100% ditanggung company begitu KPJ diisi — tidak ada opsi cost bearer utk keduanya (sesuai regulasi).</p>
              </div>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
          </form>

          <div class="border-t border-slate-100 px-6 py-4">
            <button @click="submitForm" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>