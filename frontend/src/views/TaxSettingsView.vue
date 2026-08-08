<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, X, Trash2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Company { id: number; name: string }

interface PtkpConfigRow {
  id: number
  ptkp_status: string
  effective_date: string
  annual_amount: string
}

interface TerBracketRow {
  id: number
  category: 'a' | 'b' | 'c'
  effective_date: string
  income_from: string
  income_to: string | null
  rate_percentage: string
}

interface TaxBracketRow {
  id: number
  effective_date: string
  income_from: string
  income_to: string | null
  rate_percentage: string
}

const ptkpLabels: Record<string, string> = {
  tk0: 'TK/0', tk1: 'TK/1', tk2: 'TK/2', tk3: 'TK/3', k0: 'K/0', k1: 'K/1', k2: 'K/2', k3: 'K/3',
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatCurrency(value: string | null) {
  if (value === null) return 'Tidak terbatas'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function isFutureOrToday(dateStr: string) {
  const d = new Date(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return d >= today
}

const activeTab = ref<'ptkp' | 'ter' | 'pasal17' | 'default'>('ptkp')
const companies = ref<Company[]>([])
const selectedCompanyId = ref<number | null>(null)

async function loadCompanies() {
  const response = await apiClient.get('/api/companies')
  companies.value = response.data.data.data ?? response.data.data
  selectedCompanyId.value = companies.value[0]?.id ?? null
}

// ---------- PTKP CONFIG ----------
const ptkpConfigs = ref<PtkpConfigRow[]>([])
async function loadPtkpConfigs() {
  const response = await apiClient.get('/api/pph21/ptkp-configs')
  ptkpConfigs.value = response.data.data
}

const showPtkpModal = ref(false)
const ptkpForm = reactive({ ptkp_status: 'tk0', effective_date: '', annual_amount: null as number | null })
const ptkpError = ref('')
const savingPtkp = ref(false)

function openPtkpModal() {
  ptkpForm.ptkp_status = 'tk0'
  ptkpForm.effective_date = new Date().toISOString().slice(0, 10)
  ptkpForm.annual_amount = null
  ptkpError.value = ''
  showPtkpModal.value = true
}

async function submitPtkp() {
  savingPtkp.value = true
  ptkpError.value = ''
  try {
    await apiClient.post('/api/pph21/ptkp-configs', ptkpForm)
    showPtkpModal.value = false
    await loadPtkpConfigs()
  } catch (err: any) {
    ptkpError.value = err.response?.data?.message || 'Gagal menyimpan PTKP config.'
  } finally {
    savingPtkp.value = false
  }
}

// ---------- TER BRACKETS ----------
const terBrackets = ref<TerBracketRow[]>([])
async function loadTerBrackets() {
  const response = await apiClient.get('/api/pph21/ter-rate-brackets')
  terBrackets.value = response.data.data
}

const showTerModal = ref(false)
const terForm = reactive({
  category: 'a' as 'a' | 'b' | 'c',
  effective_date: '',
  income_from: null as number | null,
  income_to: null as number | null,
  rate_percentage: null as number | null,
})
const terError = ref('')
const savingTer = ref(false)

function openTerModal() {
  terForm.category = 'a'
  terForm.effective_date = new Date().toISOString().slice(0, 10)
  terForm.income_from = null
  terForm.income_to = null
  terForm.rate_percentage = null
  terError.value = ''
  showTerModal.value = true
}

async function submitTer() {
  savingTer.value = true
  terError.value = ''
  try {
    await apiClient.post('/api/pph21/ter-rate-brackets', terForm)
    showTerModal.value = false
    await loadTerBrackets()
  } catch (err: any) {
    terError.value = err.response?.data?.message || 'Gagal menyimpan bracket TER.'
  } finally {
    savingTer.value = false
  }
}

async function deleteTerBracket(row: TerBracketRow) {
  if (!confirm('Hapus bracket ini?')) return
  try {
    await apiClient.delete(`/api/pph21/ter-rate-brackets/${row.id}`)
    await loadTerBrackets()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus.')
  }
}

// ---------- PASAL 17 BRACKETS ----------
const taxBrackets = ref<TaxBracketRow[]>([])
async function loadTaxBrackets() {
  const response = await apiClient.get('/api/pph21/tax-bracket-configs')
  taxBrackets.value = response.data.data
}

const showBracketModal = ref(false)
const bracketForm = reactive({
  effective_date: '',
  income_from: null as number | null,
  income_to: null as number | null,
  rate_percentage: null as number | null,
})
const bracketError = ref('')
const savingBracket = ref(false)

function openBracketModal() {
  bracketForm.effective_date = new Date().toISOString().slice(0, 10)
  bracketForm.income_from = null
  bracketForm.income_to = null
  bracketForm.rate_percentage = null
  bracketError.value = ''
  showBracketModal.value = true
}

async function submitBracket() {
  savingBracket.value = true
  bracketError.value = ''
  try {
    await apiClient.post('/api/pph21/tax-bracket-configs', bracketForm)
    showBracketModal.value = false
    await loadTaxBrackets()
  } catch (err: any) {
    bracketError.value = err.response?.data?.message || 'Gagal menyimpan bracket Pasal 17.'
  } finally {
    savingBracket.value = false
  }
}

async function deleteTaxBracket(row: TaxBracketRow) {
  if (!confirm('Hapus bracket ini?')) return
  try {
    await apiClient.delete(`/api/pph21/tax-bracket-configs/${row.id}`)
    await loadTaxBrackets()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus.')
  }
}

// ---------- COMPANY DEFAULT ----------
const defaultForm = reactive({
  default_tax_method: 'gross',
  no_npwp_surcharge_percentage: 20,
  position_cost_percentage: 5,
  position_cost_monthly_cap: 500000,
  position_cost_annual_cap: 6000000,
})
const savingDefault = ref(false)
const defaultSaved = ref(false)

async function loadDefaultSetting() {
  if (!selectedCompanyId.value) return
  const response = await apiClient.get('/api/pph21/company-setting', { params: { company_id: selectedCompanyId.value } })
  const data = response.data.data
  if (data) {
    defaultForm.default_tax_method = data.default_tax_method
    defaultForm.no_npwp_surcharge_percentage = Number(data.no_npwp_surcharge_percentage)
    defaultForm.position_cost_percentage = Number(data.position_cost_percentage)
    defaultForm.position_cost_monthly_cap = Number(data.position_cost_monthly_cap)
    defaultForm.position_cost_annual_cap = Number(data.position_cost_annual_cap)
  }
}

async function saveDefaultSetting() {
  savingDefault.value = true
  defaultSaved.value = false
  try {
    await apiClient.put('/api/pph21/company-setting', { ...defaultForm, company_id: selectedCompanyId.value })
    defaultSaved.value = true
  } finally {
    savingDefault.value = false
  }
}

async function onCompanyChange() {
  await loadDefaultSetting()
}

onMounted(async () => {
  await loadCompanies()
  await Promise.all([loadPtkpConfigs(), loadTerBrackets(), loadTaxBrackets(), loadDefaultSetting()])
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Pengaturan PPh 21</h1>
        <p class="mt-1 text-sm text-slate-500">PTKP, tarif TER, tarif Pasal 17 — dipakai Tax Calculation Engine tiap payroll dijalankan. Isi angka dari lampiran resmi PMK terbaru.</p>
      </div>
      <select v-model.number="selectedCompanyId" @change="onCompanyChange" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>

    <div class="flex gap-1 border-b border-slate-100">
      <button v-for="tab in [{ key: 'ptkp', label: 'PTKP' }, { key: 'ter', label: 'Tarif TER' }, { key: 'pasal17', label: 'Tarif Pasal 17' }, { key: 'default', label: 'Default Company' }]"
        :key="tab.key" @click="activeTab = tab.key as any"
        class="border-b-2 px-4 py-2.5 text-sm font-medium"
        :class="activeTab === tab.key ? 'border-primary text-primary-dark' : 'border-transparent text-slate-500 hover:text-slate-700'">
        {{ tab.label }}
      </button>
    </div>

    <!-- PTKP TAB -->
    <div v-if="activeTab === 'ptkp'" class="space-y-4">
      <p class="text-xs text-slate-400">Global — sama untuk semua company, sesuai regulasi.</p>
      <div class="flex justify-end">
        <button @click="openPtkpModal" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
          <Plus class="h-4 w-4" :stroke-width="2" /> Tambah PTKP
        </button>
      </div>
      <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Status</th>
              <th class="px-5 py-3 font-medium text-slate-500">Berlaku Sejak</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Nominal Setahun</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in ptkpConfigs" :key="row.id" class="border-b border-slate-50 last:border-0">
              <td class="px-5 py-3.5 font-medium text-slate-800">{{ ptkpLabels[row.ptkp_status] }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.effective_date) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(row.annual_amount) }}</td>
            </tr>
            <tr v-if="ptkpConfigs.length === 0">
              <td colspan="3" class="px-5 py-6 text-center text-sm text-slate-400">Belum ada PTKP config.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TER TAB -->
    <div v-if="activeTab === 'ter'" class="space-y-4">
      <p class="text-xs text-slate-400">Global. Isi lengkap semua lapisan per kategori (A/B/C) dari lampiran resmi PMK 168/2023 — jangan andalkan angka dari sumber tidak resmi.</p>
      <div class="flex justify-end">
        <button @click="openTerModal" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
          <Plus class="h-4 w-4" :stroke-width="2" /> Tambah Bracket
        </button>
      </div>
      <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Kategori</th>
              <th class="px-5 py-3 font-medium text-slate-500">Berlaku Sejak</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Dari</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Sampai</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Tarif</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in terBrackets" :key="row.id" class="border-b border-slate-50 last:border-0">
              <td class="px-5 py-3.5 font-medium text-slate-800">Kategori {{ row.category.toUpperCase() }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.effective_date) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(row.income_from) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(row.income_to) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ row.rate_percentage }}%</td>
              <td class="px-5 py-3.5 text-right">
                <button v-if="isFutureOrToday(row.effective_date)" @click="deleteTerBracket(row)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </td>
            </tr>
            <tr v-if="terBrackets.length === 0">
              <td colspan="6" class="px-5 py-6 text-center text-sm text-slate-400">Belum ada bracket TER.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PASAL 17 TAB -->
    <div v-if="activeTab === 'pasal17'" class="space-y-4">
      <p class="text-xs text-slate-400">Global, dipakai rekonsiliasi tahunan (Desember/resign). Sesuai UU HPP: 5% / 15% / 25% / 30% / 35%.</p>
      <div class="flex justify-end">
        <button @click="openBracketModal" class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
          <Plus class="h-4 w-4" :stroke-width="2" /> Tambah Lapisan
        </button>
      </div>
      <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Berlaku Sejak</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Dari</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Sampai</th>
              <th class="px-5 py-3 text-right font-medium text-slate-500">Tarif</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in taxBrackets" :key="row.id" class="border-b border-slate-50 last:border-0">
              <td class="px-5 py-3.5 text-slate-500">{{ formatDate(row.effective_date) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(row.income_from) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ formatCurrency(row.income_to) }}</td>
              <td class="px-5 py-3.5 text-right text-slate-600">{{ row.rate_percentage }}%</td>
              <td class="px-5 py-3.5 text-right">
                <button v-if="isFutureOrToday(row.effective_date)" @click="deleteTaxBracket(row)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </td>
            </tr>
            <tr v-if="taxBrackets.length === 0">
              <td colspan="5" class="px-5 py-6 text-center text-sm text-slate-400">Belum ada bracket Pasal 17.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- DEFAULT TAB -->
    <div v-if="activeTab === 'default'" class="max-w-md space-y-4 rounded-2xl border border-slate-100 bg-white p-6">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Metode Pajak Default</label>
        <select v-model="defaultForm.default_tax_method" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
          <option value="gross">Gross</option>
          <option value="gross_up">Gross-Up</option>
          <option value="netto">Netto</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Surcharge Tanpa NPWP (%)</label>
        <input v-model.number="defaultForm.no_npwp_surcharge_percentage" type="number" step="0.01" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Biaya Jabatan (%)</label>
        <input v-model.number="defaultForm.position_cost_percentage" type="number" step="0.01" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Cap Bulanan</label>
          <input v-model.number="defaultForm.position_cost_monthly_cap" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">Cap Tahunan</label>
          <input v-model.number="defaultForm.position_cost_annual_cap" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
      </div>
      <button @click="saveDefaultSetting" :disabled="savingDefault" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
        {{ savingDefault ? 'Menyimpan...' : 'Simpan' }}
      </button>
      <p v-if="defaultSaved" class="text-center text-xs text-emerald-600">Tersimpan.</p>
    </div>

    <!-- MODAL: PTKP -->
    <Teleport to="body">
      <div v-if="showPtkpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Tambah PTKP</h2>
            <button @click="showPtkpModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>
          <form @submit.prevent="submitPtkp" class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="ptkpForm.ptkp_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, key) in ptkpLabels" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Berlaku Sejak</label>
              <input v-model="ptkpForm.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nominal Setahun</label>
              <input v-model.number="ptkpForm.annual_amount" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <p v-if="ptkpError" class="text-sm text-red-600">{{ ptkpError }}</p>
            <button type="submit" :disabled="savingPtkp" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ savingPtkp ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- MODAL: TER BRACKET -->
    <Teleport to="body">
      <div v-if="showTerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Tambah Bracket TER</h2>
            <button @click="showTerModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>
          <form @submit.prevent="submitTer" class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Kategori</label>
              <select v-model="terForm.category" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="a">Kategori A</option>
                <option value="b">Kategori B</option>
                <option value="c">Kategori C</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Berlaku Sejak</label>
              <input v-model="terForm.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Dari</label>
                <input v-model.number="terForm.income_from" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sampai (kosong = tak terbatas)</label>
                <input v-model.number="terForm.income_to" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tarif %</label>
              <input v-model.number="terForm.rate_percentage" type="number" step="0.01" min="0" max="100" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <p v-if="terError" class="text-sm text-red-600">{{ terError }}</p>
            <button type="submit" :disabled="savingTer" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ savingTer ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- MODAL: PASAL 17 BRACKET -->
    <Teleport to="body">
      <div v-if="showBracketModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Tambah Lapisan Pasal 17</h2>
            <button @click="showBracketModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>
          <form @submit.prevent="submitBracket" class="space-y-4 px-6 py-5">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Berlaku Sejak</label>
              <input v-model="bracketForm.effective_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Dari</label>
                <input v-model.number="bracketForm.income_from" type="number" min="0" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sampai (kosong = tak terbatas)</label>
                <input v-model.number="bracketForm.income_to" type="number" min="0" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tarif %</label>
              <input v-model.number="bracketForm.rate_percentage" type="number" step="0.01" min="0" max="100" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <p v-if="bracketError" class="text-sm text-red-600">{{ bracketError }}</p>
            <button type="submit" :disabled="savingBracket" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50">
              {{ savingBracket ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>