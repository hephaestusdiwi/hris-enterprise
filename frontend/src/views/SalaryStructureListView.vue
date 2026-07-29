<script setup lang="ts">
import { ref, onMounted, reactive, computed } from 'vue'
import { Plus, Trash2, X, Copy, Eye, GripVertical } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company { id: number; name: string }

interface SalaryComponent {
  id: number
  name: string
  code: string
  calculation_method: 'fixed' | 'percentage'
  amount: string | null
  percentage_value: string | null
  percentage_base: 'basic_salary' | 'gross_salary' | null
}

interface DetailRow {
  salary_component_id: number | null
  override_amount: number | null
  override_percentage_value: number | null
  override_percentage_base: 'basic_salary' | 'gross_salary'
  display_order: number
}

interface StructureDetail {
  id: number
  display_order: number
  override_amount: string | null
  override_percentage_value: string | null
  override_percentage_base: string | null
  salary_component: SalaryComponent
}

interface StructureRow {
  id: number
  company_id: number
  code: string
  name: string
  description: string | null
  effective_date: string
  is_active: boolean
  details_count: number
  company: Company
  details?: StructureDetail[]
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatCurrency(value: string | null) {
  if (value === null) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}

const structures = ref<StructureRow[]>([])
const companies = ref<Company[]>([])
const salaryComponents = ref<SalaryComponent[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadStructures() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/salary-structures')
    structures.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar salary structure.'
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  const [companyRes, componentRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/salary-components'),
  ])
  companies.value = companyRes.data.data.data
  salaryComponents.value = componentRes.data.data.data
}

const filteredComponents = computed(() => salaryComponents.value.filter((c) => (c as any).company_id === form.company_id))

// ---------- CREATE / NEW VERSION MODAL ----------
const showModal = ref(false)
const modalTitle = ref('Tambah Salary Structure')
const saving = ref(false)
const formError = ref('')

const form = reactive({
  company_id: 0,
  code: '',
  name: '',
  description: '',
  effective_date: '',
  is_active: true,
})

const rows = ref<DetailRow[]>([])

function resetForm() {
  form.company_id = companies.value[0]?.id ?? 0
  form.code = ''
  form.name = ''
  form.description = ''
  form.effective_date = new Date().toISOString().slice(0, 10)
  form.is_active = true
  rows.value = []
}

function openCreateModal() {
  modalTitle.value = 'Tambah Salary Structure'
  formError.value = ''
  resetForm()
  addRow()
  showModal.value = true
}

function openNewVersion(structure: StructureRow) {
  modalTitle.value = `Versi Baru — ${structure.name}`
  formError.value = ''
  form.company_id = structure.company_id
  form.code = structure.code
  form.name = structure.name
  form.description = structure.description ?? ''
  form.effective_date = ''
  form.is_active = true

  rows.value = (structure.details ?? []).map((d) => ({
    salary_component_id: d.salary_component.id,
    override_amount: d.override_amount !== null ? Number(d.override_amount) : null,
    override_percentage_value: d.override_percentage_value !== null ? Number(d.override_percentage_value) : null,
    override_percentage_base: (d.override_percentage_base as any) ?? 'basic_salary',
    display_order: d.display_order,
  }))

  showModal.value = true
}

async function openNewVersionFromRow(row: StructureRow) {
  // pastikan details-nya udah di-load (list index belum nge-include details)
  const response = await apiClient.get(`/api/salary-structures/${row.id}`)
  openNewVersion(response.data.data)
}

function closeModal() {
  showModal.value = false
}

function addRow() {
  rows.value.push({
    salary_component_id: null,
    override_amount: null,
    override_percentage_value: null,
    override_percentage_base: 'basic_salary',
    display_order: rows.value.length + 1,
  })
}

function removeRow(index: number) {
  rows.value.splice(index, 1)
}

function componentFor(row: DetailRow): SalaryComponent | null {
  return salaryComponents.value.find((c) => c.id === row.salary_component_id) ?? null
}

async function handleSubmit() {
  formError.value = ''

  if (rows.value.length === 0 || rows.value.some((r) => !r.salary_component_id)) {
    formError.value = 'Minimal 1 komponen harus dipilih untuk setiap baris.'
    return
  }

  saving.value = true

  const payload = {
    company_id: form.company_id,
    code: form.code,
    name: form.name,
    description: form.description || null,
    effective_date: form.effective_date,
    is_active: form.is_active,
    details: rows.value.map((r, index) => {
      const component = componentFor(r)
      const isPercentage = component?.calculation_method === 'percentage'

      return {
        salary_component_id: r.salary_component_id,
        override_amount: !isPercentage ? r.override_amount : null,
        override_percentage_value: isPercentage ? r.override_percentage_value : null,
        override_percentage_base: isPercentage ? r.override_percentage_base : null,
        display_order: r.display_order ?? index + 1,
      }
    }),
  }

  try {
    await apiClient.post('/api/salary-structures', payload)
    showModal.value = false
    await loadStructures()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: StructureRow) {
  if (!confirm(`Hapus salary structure "${row.name}" (efektif ${formatDate(row.effective_date)})?`)) return

  try {
    await apiClient.delete(`/api/salary-structures/${row.id}`)
    await loadStructures()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus salary structure.')
  }
}

// ---------- DETAIL VIEW MODAL ----------
const showDetailModal = ref(false)
const detailTarget = ref<StructureRow | null>(null)
const detailLoading = ref(false)

async function openDetail(row: StructureRow) {
  detailTarget.value = null
  showDetailModal.value = true
  detailLoading.value = true
  try {
    const response = await apiClient.get(`/api/salary-structures/${row.id}`)
    detailTarget.value = response.data.data
  } finally {
    detailLoading.value = false
  }
}

function closeDetailModal() {
  showDetailModal.value = false
  detailTarget.value = null
}

onMounted(() => {
  loadStructures()
  loadReferenceData()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Salary Structure</h1>
        <p class="mt-1 text-sm text-slate-500">Paket komponen gaji, ter-versi berdasarkan Effective Date.</p>
      </div>
      <button
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Structure
      </button>
    </div>

    <p v-if="companies.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada company. Tambahkan company terlebih dahulu.
    </p>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else-if="structures.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada salary structure.
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Effective Date</th>
            <th class="px-5 py-3 font-medium text-slate-500">Komponen</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in structures" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ row.name }}</p>
              <p class="text-xs text-slate-400">{{ row.code }} · {{ row.company.name }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.effective_date) }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.details_count }} komponen</td>
            <td class="px-5 py-3.5">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="row.is_active ? 'bg-primary-soft text-primary-dark' : 'bg-slate-50 text-slate-400'">
                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button @click="openDetail(row)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Lihat Detail">
                  <Eye class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button @click="openNewVersionFromRow(row)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Buat Versi Baru">
                  <Copy class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button @click="handleDelete(row)" class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-500" title="Hapus">
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- CREATE / NEW VERSION MODAL -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="flex max-h-full w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ modalTitle }}</h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Effective Date</label>
                <input v-model="form.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input v-model="form.code" type="text" required placeholder="STAFF_PKG" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
                <p class="mt-1 text-xs text-slate-400">Sama seperti versi sebelumnya = versi baru dari struktur yang sama.</p>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
                <input v-model="form.name" type="text" required placeholder="Staff Package" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
              <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div>
              <div class="mb-3 flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Komponen Gaji</h3>
                <button type="button" @click="addRow" class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-dark">
                  <Plus class="h-3.5 w-3.5" :stroke-width="2" />
                  Tambah Baris
                </button>
              </div>

              <div class="space-y-2">
                <div v-for="(row, index) in rows" :key="index" class="rounded-xl border border-slate-200 p-3">
                  <div class="flex items-start gap-2">
                    <GripVertical class="mt-2.5 h-4 w-4 shrink-0 text-slate-300" :stroke-width="1.75" />

                    <div class="flex-1 space-y-2">
                      <select v-model.number="row.salary_component_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                        <option :value="null" disabled>Pilih komponen</option>
                        <option v-for="c in filteredComponents" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
                      </select>

                      <div v-if="componentFor(row)" class="grid grid-cols-2 gap-2">
                        <template v-if="componentFor(row)!.calculation_method === 'fixed'">
                          <div class="col-span-2">
                            <label class="mb-0.5 block text-xs text-slate-500">
                              Override Nominal (kosongkan = pakai default {{ formatCurrency(componentFor(row)!.amount) }})
                            </label>
                            <input v-model.number="row.override_amount" type="number" min="0" placeholder="Kosongkan = default" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none" />
                          </div>
                        </template>
                        <template v-else>
                          <div>
                            <label class="mb-0.5 block text-xs text-slate-500">
                              Override % (default {{ componentFor(row)!.percentage_value }}%)
                            </label>
                            <input v-model.number="row.override_percentage_value" type="number" min="0" max="100" step="0.01" placeholder="Kosongkan = default" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none" />
                          </div>
                          <div>
                            <label class="mb-0.5 block text-xs text-slate-500">Basis %</label>
                            <select v-model="row.override_percentage_base" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-primary focus:outline-none">
                              <option value="basic_salary">Gaji Pokok</option>
                              <option value="gross_salary">Gaji Kotor</option>
                            </select>
                          </div>
                        </template>
                      </div>
                    </div>

                    <button type="button" @click="removeRow(index)" class="mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500">
                      <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
              <p class="text-sm font-medium text-slate-700">Aktif</p>
              <input v-model="form.is_active" type="checkbox" class="peer sr-only" />
              <div class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-primary peer-checked:after:translate-x-5"></div>
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

    <!-- DETAIL VIEW MODAL -->
    <Teleport to="body">
      <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ detailTarget?.name ?? 'Memuat...' }}</h2>
            <button @click="closeDetailModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="detailLoading || !detailTarget" class="text-sm text-slate-400">Memuat...</div>
            <div v-else class="space-y-3">
              <p class="text-xs text-slate-400">
                {{ detailTarget.code }} · Efektif {{ formatDate(detailTarget.effective_date) }} · {{ detailTarget.company.name }}
              </p>

              <div v-for="detail in detailTarget.details" :key="detail.id" class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-2.5 text-sm">
                <span class="text-slate-700">{{ detail.salary_component.name }}</span>
                <span class="font-medium text-slate-600">
                  <template v-if="detail.salary_component.calculation_method === 'fixed'">
                    {{ formatCurrency(detail.override_amount ?? detail.salary_component.amount) }}
                    <span v-if="detail.override_amount" class="text-xs text-amber-600">(override)</span>
                  </template>
                  <template v-else>
                    {{ detail.override_percentage_value ?? detail.salary_component.percentage_value }}%
                    <span v-if="detail.override_percentage_value" class="text-xs text-amber-600">(override)</span>
                  </template>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>