<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { X, ShieldCheck, ShieldAlert } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Employee { id: number; first_name: string; last_name: string | null }

interface TaxProfile {
  id: number
  employee_id: number
  tax_id_number: string | null
  has_tax_id: boolean
  tax_method: string | null
}

interface PtkpStatusRow {
  id: number
  ptkp_status: string
  tax_year: number
}

const ptkpLabels: Record<string, string> = {
  tk0: 'TK/0', tk1: 'TK/1', tk2: 'TK/2', tk3: 'TK/3', k0: 'K/0', k1: 'K/1', k2: 'K/2', k3: 'K/3',
}

function employeeName(e: { first_name: string; last_name: string | null }) {
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

const employees = ref<Employee[]>([])
const profiles = ref<TaxProfile[]>([])
const loading = ref(true)

const profileByEmployeeId = computed(() => {
  const map = new Map<number, TaxProfile>()
  profiles.value.forEach((p) => map.set(p.employee_id, p))
  return map
})

async function loadAll() {
  loading.value = true
  const [empRes, profRes] = await Promise.all([
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
    apiClient.get('/api/pph21/employee-tax-profiles'),
  ])
  employees.value = empRes.data.data.data
  profiles.value = profRes.data.data
  loading.value = false
}

// ---------- EDIT MODAL ----------
const showModal = ref(false)
const targetEmployee = ref<Employee | null>(null)
const saving = ref(false)
const formError = ref('')
const ptkpHistory = ref<PtkpStatusRow[]>([])

const form = reactive({
  tax_id_number: '',
  has_tax_id: true,
  tax_method: '' as string,
})

const ptkpForm = reactive({ ptkp_status: 'tk0', tax_year: new Date().getFullYear() + 1 })
const savingPtkp = ref(false)
const ptkpError = ref('')

async function openEdit(employee: Employee) {
  targetEmployee.value = employee
  formError.value = ''
  const existing = profileByEmployeeId.value.get(employee.id)
  form.tax_id_number = existing?.tax_id_number ?? ''
  form.has_tax_id = existing?.has_tax_id ?? true
  form.tax_method = existing?.tax_method ?? ''
  ptkpForm.ptkp_status = 'tk0'
  ptkpForm.tax_year = new Date().getFullYear() + 1
  showModal.value = true

  const response = await apiClient.get(`/api/pph21/employee-tax-profiles/${employee.id}`)
  ptkpHistory.value = response.data.data.ptkp_history
}

async function submitForm() {
  if (!targetEmployee.value) return
  saving.value = true
  formError.value = ''
  try {
    await apiClient.put(`/api/pph21/employee-tax-profiles/${targetEmployee.value.id}`, {
      ...form,
      tax_method: form.tax_method || null,
    })
    await loadAll()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan tax profile.'
  } finally {
    saving.value = false
  }
}

async function submitPtkpAdjustment() {
  if (!targetEmployee.value) return
  savingPtkp.value = true
  ptkpError.value = ''
  try {
    await apiClient.post(`/api/pph21/employee-tax-profiles/${targetEmployee.value.id}/ptkp-status`, ptkpForm)
    const response = await apiClient.get(`/api/pph21/employee-tax-profiles/${targetEmployee.value.id}`)
    ptkpHistory.value = response.data.data.ptkp_history
  } catch (err: any) {
    ptkpError.value = err.response?.data?.message || 'Gagal menyimpan status PTKP.'
  } finally {
    savingPtkp.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Tax Profile Karyawan</h1>
      <p class="mt-1 text-sm text-slate-500">NPWP, metode pajak, dan status PTKP per karyawan. Tarif & formula diatur di Pengaturan PPh 21.</p>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Employee</th>
            <th class="px-5 py-3 font-medium text-slate-500">NPWP/NIK</th>
            <th class="px-5 py-3 font-medium text-slate-500">Metode Pajak</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in employees" :key="emp.id" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50" @click="openEdit(emp)">
            <td class="px-5 py-3.5 font-medium text-slate-800">{{ employeeName(emp) }}</td>
            <td class="px-5 py-3.5">
              <span v-if="profileByEmployeeId.get(emp.id)?.tax_id_number" class="flex items-center gap-1.5 text-slate-600">
                <ShieldCheck class="h-3.5 w-3.5 text-emerald-500" :stroke-width="1.75" /> {{ profileByEmployeeId.get(emp.id)?.tax_id_number }}
              </span>
              <span v-else class="flex items-center gap-1.5 text-slate-400">
                <ShieldAlert class="h-3.5 w-3.5" :stroke-width="1.75" /> Belum diisi
              </span>
            </td>
            <td class="px-5 py-3.5 text-slate-600">{{ profileByEmployeeId.get(emp.id)?.tax_method ?? 'Default' }}</td>
            <td class="px-5 py-3.5 text-right text-xs font-medium text-primary-dark">Edit</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal && targetEmployee" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Pajak — {{ employeeName(targetEmployee) }}</h2>
            <button @click="showModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <form @submit.prevent="submitForm" class="space-y-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">NPWP / NIK (16 digit)</label>
                <input v-model="form.tax_id_number" maxlength="20" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.has_tax_id" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                Punya NPWP/NIK terdaftar (kalau tidak, kena surcharge)
              </label>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Metode Pajak</label>
                <select v-model="form.tax_method" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option value="">Default (ikut pengaturan company)</option>
                  <option value="gross">Gross</option>
                  <option value="gross_up">Gross-Up</option>
                  <option value="netto">Netto</option>
                </select>
              </div>
              <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
              <button type="submit" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
                {{ saving ? 'Menyimpan...' : 'Simpan Profile' }}
              </button>
            </form>

            <div class="border-t border-slate-100 pt-4">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Status PTKP per Tahun Pajak</p>
              <div class="mb-3 space-y-1.5">
                <div v-for="row in ptkpHistory" :key="row.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                  <span class="text-slate-500">Tahun Pajak {{ row.tax_year }}</span>
                  <span class="font-medium text-slate-700">{{ ptkpLabels[row.ptkp_status] }}</span>
                </div>
                <p v-if="ptkpHistory.length === 0" class="text-xs text-slate-400">Belum ada status PTKP tercatat.</p>
              </div>

              <p class="mb-2 text-xs text-slate-400">Tambah/ubah status berlaku MULAI tahun pajak yang dipilih — tidak mengubah histori tahun sebelumnya (mirror PTKP Status Adjustment Talenta).</p>
              <div class="flex items-end gap-2">
                <div class="flex-1">
                  <label class="mb-1 block text-xs font-medium text-slate-700">Status Baru</label>
                  <select v-model="ptkpForm.ptkp_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                    <option v-for="(label, key) in ptkpLabels" :key="key" :value="key">{{ label }}</option>
                  </select>
                </div>
                <div class="w-28">
                  <label class="mb-1 block text-xs font-medium text-slate-700">Tahun Pajak</label>
                  <input v-model.number="ptkpForm.tax_year" type="number" min="2020" max="2100" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                </div>
                <button @click="submitPtkpAdjustment" :disabled="savingPtkp" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
                  {{ savingPtkp ? '...' : 'Tambah' }}
                </button>
              </div>
              <p v-if="ptkpError" class="mt-2 text-sm text-red-600">{{ ptkpError }}</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>