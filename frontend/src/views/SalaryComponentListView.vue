<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company {
  id: number
  name: string
}

type Category = 'basic_salary' | 'allowance' | 'deduction' | 'statutory'
type CalcMethod = 'fixed' | 'percentage'
type PercentageBase = 'basic_salary' | 'gross_salary'

interface SalaryComponentRow {
  id: number
  company_id: number
  name: string
  code: string
  category: Category
  is_addition: boolean
  calculation_method: CalcMethod
  amount: string | null
  percentage_value: string | null
  percentage_base: PercentageBase | null
  is_taxable: boolean
  include_in_bpjs_base: boolean
  is_active: boolean
  company: Company
}

const categoryLabels: Record<Category, string> = {
  basic_salary: 'Basic Salary',
  allowance: 'Allowance',
  deduction: 'Deduction',
  statutory: 'Statutory',
}

const categoryBadgeClass: Record<Category, string> = {
  basic_salary: 'bg-primary-soft text-primary-dark',
  allowance: 'bg-blue-50 text-blue-600',
  deduction: 'bg-red-50 text-red-600',
  statutory: 'bg-violet-50 text-violet-600',
}

const percentageBaseLabels: Record<PercentageBase, string> = {
  basic_salary: 'Gaji Pokok',
  gross_salary: 'Gaji Kotor',
}

function formatCurrency(value: string | null) {
  if (value === null) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const components = ref<SalaryComponentRow[]>([])
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
  category: 'allowance' as Category,
  is_addition: true,
  calculation_method: 'fixed' as CalcMethod,
  amount: null as number | null,
  percentage_value: null as number | null,
  percentage_base: 'basic_salary' as PercentageBase,
  is_taxable: false,
  include_in_bpjs_base: false,
  is_active: true,
})

const isFixed = computed(() => form.calculation_method === 'fixed')

async function loadComponents() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/salary-components')
    components.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar salary component.'
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
  form.category = 'allowance'
  form.is_addition = true
  form.calculation_method = 'fixed'
  form.amount = null
  form.percentage_value = null
  form.percentage_base = 'basic_salary'
  form.is_taxable = false
  form.include_in_bpjs_base = false
  form.is_active = true
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showModal.value = true
}

function openEditModal(row: SalaryComponentRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.company_id
  form.name = row.name
  form.code = row.code
  form.category = row.category
  form.is_addition = row.is_addition
  form.calculation_method = row.calculation_method
  form.amount = row.amount !== null ? Number(row.amount) : null
  form.percentage_value = row.percentage_value !== null ? Number(row.percentage_value) : null
  form.percentage_base = row.percentage_base ?? 'basic_salary'
  form.is_taxable = row.is_taxable
  form.include_in_bpjs_base = row.include_in_bpjs_base
  form.is_active = row.is_active
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function onCategoryChange() {
  // Default masuk akal per kategori — HR tetap bisa override manual
  if (form.category === 'deduction' || form.category === 'statutory') {
    form.is_addition = form.category === 'statutory' ? form.is_addition : false
  } else {
    form.is_addition = true
  }
}

async function handleSubmit() {
  formError.value = ''
  saving.value = true

  const payload = {
    company_id: form.company_id,
    name: form.name,
    code: form.code,
    category: form.category,
    is_addition: form.is_addition,
    calculation_method: form.calculation_method,
    amount: isFixed.value ? form.amount : null,
    percentage_value: isFixed.value ? null : form.percentage_value,
    percentage_base: isFixed.value ? null : form.percentage_base,
    is_taxable: form.is_taxable,
    include_in_bpjs_base: form.include_in_bpjs_base,
    is_active: form.is_active,
  }

  try {
    if (isEditing.value) {
      await apiClient.put(`/api/salary-components/${form.id}`, payload)
    } else {
      await apiClient.post('/api/salary-components', payload)
    }

    showModal.value = false
    await loadComponents()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: SalaryComponentRow) {
  if (!confirm(`Hapus salary component "${row.name}"?`)) return

  try {
    await apiClient.delete(`/api/salary-components/${row.id}`)
    await loadComponents()
  } catch {
    alert('Gagal menghapus salary component.')
  }
}

onMounted(() => {
  loadComponents()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Salary Component</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola elemen gaji (Gaji Pokok, Tunjangan, Potongan, Statutory).</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Component
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="components.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada salary component.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Kategori</th>
            <th class="px-5 py-3 font-medium text-slate-500">Nilai</th>
            <th class="px-5 py-3 font-medium text-slate-500">Sifat</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in components" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ row.name }}</p>
              <p class="text-xs text-slate-400">{{ row.code }} · {{ row.company.name }}</p>
            </td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="categoryBadgeClass[row.category]">
                {{ categoryLabels[row.category] }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-slate-500">
              <span v-if="row.calculation_method === 'fixed'">{{ formatCurrency(row.amount) }}</span>
              <span v-else>{{ row.percentage_value }}% dari {{ percentageBaseLabels[row.percentage_base!] }}</span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap gap-1">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="row.is_addition ? 'bg-primary-soft text-primary-dark' : 'bg-red-50 text-red-600'">
                  {{ row.is_addition ? 'Penambah' : 'Pengurang' }}
                </span>
                <span v-if="row.is_taxable" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">Kena Pajak</span>
                <span v-if="row.include_in_bpjs_base" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Basis BPJS</span>
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
                <button @click="openEditModal(row)" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button @click="handleDelete(row)" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500">
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit Salary Component' : 'Tambah Salary Component' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
                <input v-model="form.name" type="text" required placeholder="Tunjangan Transport" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input v-model="form.code" type="text" required placeholder="TRANSPORT" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kategori</label>
                <select v-model="form.category" @change="onCategoryChange" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option value="basic_salary">Basic Salary</option>
                  <option value="allowance">Allowance</option>
                  <option value="deduction">Deduction</option>
                  <option value="statutory">Statutory</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sifat</label>
                <select v-model="form.is_addition" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="true">Penambah (menambah gaji)</option>
                  <option :value="false">Pengurang (memotong gaji)</option>
                </select>
                <p v-if="form.category === 'statutory'" class="mt-1 text-xs text-slate-400">
                  Statutory bisa jadi penambah (kontribusi perusahaan) atau pengurang (potongan karyawan) — pilih manual.
                </p>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Metode Kalkulasi</label>
              <div class="flex gap-2">
                <button type="button" @click="form.calculation_method = 'fixed'" class="flex-1 rounded-xl border py-2 text-sm font-medium" :class="isFixed ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-500'">
                  Fixed Amount
                </button>
                <button type="button" @click="form.calculation_method = 'percentage'" class="flex-1 rounded-xl border py-2 text-sm font-medium" :class="!isFixed ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-500'">
                  Percentage
                </button>
              </div>
            </div>

            <div v-if="isFixed">
              <label class="mb-1 block text-sm font-medium text-slate-700">Nominal (Rp)</label>
              <input v-model.number="form.amount" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div v-else class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Persentase (%)</label>
                <input v-model.number="form.percentage_value" type="number" min="0" max="100" step="0.01" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Basis Persentase</label>
                <select v-model="form.percentage_base" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option value="basic_salary">Gaji Pokok</option>
                  <option value="gross_salary">Gaji Kotor</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
              <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-3 py-3">
                <p class="text-xs font-medium text-slate-700">Kena Pajak</p>
                <input v-model="form.is_taxable" type="checkbox" class="peer sr-only" />
                <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
              </label>
              <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-3 py-3">
                <p class="text-xs font-medium text-slate-700">Basis BPJS</p>
                <input v-model="form.include_in_bpjs_base" type="checkbox" class="peer sr-only" />
                <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
              </label>
              <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-3 py-3">
                <p class="text-xs font-medium text-slate-700">Aktif</p>
                <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
                <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
              </label>
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