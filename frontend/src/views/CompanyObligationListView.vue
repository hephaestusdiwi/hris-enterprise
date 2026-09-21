<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Pencil, Trash2, X, Filter, ScrollText, Bell, UserPlus, History } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

type ObligationStatus = 'active' | 'completed' | 'cancelled'
type RecipientType = 'user' | 'pic' | 'role'

interface Company { id: number; name: string }
interface Employee { id: number; first_name: string; last_name: string | null }
interface UserOption { id: number; name: string; email: string }
interface RecipientRow {
  id: number
  recipient_type: RecipientType
  role: string | null
  user: UserOption | null
}
interface ObligationRow {
  id: number
  company: Company
  type: string
  title: string
  description: string | null
  pic: Employee | null
  due_date: string
  remaining_days: number
  amount: string | null
  status: ObligationStatus
  recipients?: RecipientRow[]
}
interface ReminderRow {
  id: string
  read_at: string | null
  created_at: string
  data: { milestone: number; recipient_type: RecipientType; recipient_role: string | null }
  notifiable: UserOption
}

const authStore = useAuthStore()
const canCreate = computed(() => authStore.permissions.includes('create company obligations'))
const canEdit = computed(() => authStore.permissions.includes('edit company obligations'))
const canDelete = computed(() => authStore.permissions.includes('delete company obligations'))

function employeeName(e: Employee | null) {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}
function formatCurrency(value: string | number | null) {
  if (value === null) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value))
}
function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatDateTime(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusLabels: Record<ObligationStatus, string> = {
  active: 'Aktif', completed: 'Selesai', cancelled: 'Dibatalkan',
}
const statusBadgeClass: Record<ObligationStatus, string> = {
  active: 'bg-primary-soft text-primary-dark',
  completed: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-slate-100 text-slate-500',
}
function remainingBadgeClass(days: number) {
  if (days < 0) return 'bg-red-50 text-red-600'
  if (days <= 7) return 'bg-amber-50 text-amber-600'
  if (days <= 30) return 'bg-sky-50 text-sky-600'
  return 'bg-slate-100 text-slate-500'
}
function remainingLabel(days: number) {
  if (days < 0) return `Telat ${Math.abs(days)} hari`
  if (days === 0) return 'Jatuh tempo hari ini'
  return `H-${days}`
}

// ---------- Master data ----------
const types = ref<Record<string, string>>({})
const companies = ref<Company[]>([])
const employees = ref<Employee[]>([])
const users = ref<UserOption[]>([])
const roles = ref<string[]>([])
const recipientDirectoryAvailable = ref(true)

async function loadMasterData() {
  const [companyRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
  ])
  companies.value = companyRes.data.data.data
  employees.value = employeeRes.data.data.data

  // /api/users & /api/roles digerbangi permission 'view users' (beda dari
  // 'view company obligations') -- dipisah try/catch sendiri supaya kalau
  // suatu saat ada role yang punya akses module ini tapi bukan 'view
  // users', halaman TETAP jalan (cuma opsi recipient User/Role yang
  // disembunyikan), bukan ikut gagal total.
  try {
    const [userRes, roleRes] = await Promise.all([
      apiClient.get('/api/users'),
      apiClient.get('/api/roles'),
    ])
    users.value = userRes.data.data.data
    roles.value = roleRes.data.data
  } catch {
    recipientDirectoryAvailable.value = false
  }
}

// ---------- List ----------
const obligations = ref<ObligationRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const filters = reactive({ company_id: '', type: '', status: '' })

async function loadList() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/company-obligations', {
      params: {
        company_id: filters.company_id || undefined,
        type: filters.type || undefined,
        status: filters.status || undefined,
      },
    })
    obligations.value = response.data.data.data
    types.value = response.data.meta.types
  } catch {
    errorMessage.value = 'Gagal memuat daftar Company Obligations.'
  } finally {
    loading.value = false
  }
}

// ---------- Create / Edit modal ----------
const showFormModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  id: 0,
  company_id: 0,
  type: '',
  title: '',
  description: '',
  pic_employee_id: null as number | null,
  due_date: '',
  amount: '' as string | number,
  status: 'active' as ObligationStatus,
})

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.type = Object.keys(types.value)[0] ?? ''
  form.title = ''
  form.description = ''
  form.pic_employee_id = null
  form.due_date = ''
  form.amount = ''
  form.status = 'active'
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showFormModal.value = true
}

function openEditModal(row: ObligationRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.company.id
  form.type = row.type
  form.title = row.title
  form.description = row.description ?? ''
  form.pic_employee_id = row.pic?.id ?? null
  form.due_date = row.due_date
  form.amount = row.amount ?? ''
  form.status = row.status
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''
  try {
    if (isEditing.value) {
      await apiClient.put(`/api/company-obligations/${form.id}`, {
        company_id: form.company_id,
        title: form.title,
        description: form.description || null,
        pic_employee_id: form.pic_employee_id,
        due_date: form.due_date,
        amount: form.amount === '' ? null : form.amount,
        status: form.status,
      })
    } else {
      await apiClient.post('/api/company-obligations', {
        company_id: form.company_id,
        type: form.type,
        title: form.title,
        description: form.description || null,
        pic_employee_id: form.pic_employee_id,
        due_date: form.due_date,
        amount: form.amount === '' ? null : form.amount,
      })
    }
    showFormModal.value = false
    await loadList()
    if (isEditing.value && drawerTarget.value?.id === form.id) await openDrawer({ id: form.id } as ObligationRow)
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: ObligationRow) {
  if (!confirm(`Hapus Company Obligation "${row.title}"?`)) return
  try {
    await apiClient.delete(`/api/company-obligations/${row.id}`)
    if (drawerTarget.value?.id === row.id) closeDrawer()
    await loadList()
  } catch {
    alert('Gagal menghapus Company Obligation.')
  }
}

// ---------- Detail drawer ----------
const showDrawer = ref(false)
const drawerTarget = ref<ObligationRow | null>(null)
const drawerTab = ref<'info' | 'recipients' | 'reminders'>('info')
const reminders = ref<ReminderRow[]>([])
const remindersLoading = ref(false)
const remindersLoaded = ref(false)

async function openDrawer(row: ObligationRow) {
  drawerTab.value = 'info'
  remindersLoaded.value = false
  reminders.value = []
  try {
    const response = await apiClient.get(`/api/company-obligations/${row.id}`)
    drawerTarget.value = response.data.data
    showDrawer.value = true
  } catch {
    errorMessage.value = 'Gagal memuat detail Company Obligation.'
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}

async function loadReminderHistory() {
  if (!drawerTarget.value) return
  remindersLoading.value = true
  try {
    const response = await apiClient.get(`/api/company-obligations/${drawerTarget.value.id}/reminders`)
    reminders.value = response.data.data
    remindersLoaded.value = true
  } catch {
    reminders.value = []
  } finally {
    remindersLoading.value = false
  }
}

// ---------- Recipients ----------
const newRecipientType = ref<RecipientType>('pic')
const newRecipientUserId = ref<number | null>(null)
const newRecipientRole = ref('')
const recipientSaving = ref(false)
const recipientError = ref('')

async function addRecipient() {
  if (!drawerTarget.value) return
  recipientSaving.value = true
  recipientError.value = ''
  try {
    const payload: Record<string, unknown> = { recipient_type: newRecipientType.value }
    if (newRecipientType.value === 'user') payload.user_id = newRecipientUserId.value
    if (newRecipientType.value === 'role') payload.role = newRecipientRole.value

    await apiClient.post(`/api/company-obligations/${drawerTarget.value.id}/recipients`, payload)
    newRecipientUserId.value = null
    newRecipientRole.value = ''
    await openDrawer(drawerTarget.value)
  } catch (err: any) {
    recipientError.value = err.response?.data?.message || 'Gagal menambahkan recipient.'
  } finally {
    recipientSaving.value = false
  }
}

async function removeRecipient(recipient: RecipientRow) {
  if (!drawerTarget.value) return
  if (!confirm('Hapus recipient reminder ini?')) return
  try {
    await apiClient.delete(`/api/company-obligations/${drawerTarget.value.id}/recipients/${recipient.id}`)
    await openDrawer(drawerTarget.value)
  } catch {
    alert('Gagal menghapus recipient.')
  }
}

function recipientLabel(r: RecipientRow) {
  if (r.recipient_type === 'pic') return 'PIC Obligation (mengikuti PIC saat ini)'
  if (r.recipient_type === 'user') return r.user ? `${r.user.name} (${r.user.email})` : 'User'
  return `Role: ${r.role}`
}

const drawerTabs: Array<[typeof drawerTab.value, string]> = [
  ['info', 'Info'],
  ['recipients', 'Recipients'],
  ['reminders', 'Reminder'],
]

onMounted(async () => {
  await loadMasterData()
  await loadList()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Company Obligations</h1>
        <p class="mt-1 text-sm text-slate-500">Sewa Ruko, MOU Legal, dan Jatuh Tempo Piutang dalam satu tempat.</p>
      </div>
      <button
        v-if="canCreate"
        @click="openCreateModal"
        :disabled="companies.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Obligation
      </button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Filter class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
      <select v-model="filters.company_id" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua company</option>
        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.type" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua tipe</option>
        <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
      </select>
      <select v-model="filters.status" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua status</option>
        <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Obligation</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">PIC</th>
            <th class="px-5 py-3 font-medium text-slate-500">Jatuh Tempo</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Nominal</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="obligations.length === 0"><td colspan="7" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada data.</td></tr>
          <tr v-for="row in obligations" :key="row.id" @click="openDrawer(row)" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ row.title }}</p>
              <p class="text-xs text-slate-400">{{ types[row.type] ?? row.type }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.company.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ employeeName(row.pic) }}</td>
            <td class="px-5 py-3.5">
              <p class="text-slate-600">{{ formatDate(row.due_date) }}</p>
              <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="remainingBadgeClass(row.remaining_days)">
                {{ remainingLabel(row.remaining_days) }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ formatCurrency(row.amount) }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[row.status]">{{ statusLabels[row.status] }}</span></td>
            <td class="px-5 py-3.5" @click.stop>
              <div class="flex items-center justify-end gap-1">
                <button v-if="canEdit" @click="openEditModal(row)" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600">
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button v-if="canDelete" @click="handleDelete(row)" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500">
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-6">
        <div class="max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Edit Obligation' : 'Tambah Obligation' }}</h2>
            <button @click="closeFormModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
              <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Kebutuhan</label>
              <select v-model="form.type" :disabled="isEditing" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none disabled:bg-slate-50 disabled:text-slate-400">
                <option v-for="(label, value) in types" :key="value" :value="value">{{ label }}</option>
              </select>
              <p v-if="isEditing" class="mt-1 text-xs text-slate-400">Tipe tidak bisa diubah setelah dibuat.</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Judul</label>
              <input v-model="form.title" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
              <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PIC</label>
              <select v-model="form.pic_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">- Tanpa PIC -</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Jatuh Tempo</label>
                <input v-model="form.due_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nominal (opsional)</label>
                <input v-model="form.amount" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div v-if="isEditing">
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="form.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
              </select>
              <p class="mt-1 text-xs text-slate-400">Reminder otomatis berhenti begitu status bukan Aktif.</p>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <button type="submit" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Detail drawer -->
    <Teleport to="body">
      <div v-if="showDrawer && drawerTarget" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <ScrollText class="h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <h2 class="truncate text-lg font-semibold text-slate-900">{{ drawerTarget.title }}</h2>
              </div>
              <p class="mt-0.5 text-sm text-slate-500">{{ types[drawerTarget.type] ?? drawerTarget.type }} · {{ drawerTarget.company.name }}</p>
            </div>
            <button @click="closeDrawer" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="flex gap-1 border-b border-slate-100 px-6 pt-3">
            <button
              v-for="tab in drawerTabs"
              :key="tab[0]"
              @click="drawerTab = tab[0]; if (tab[0] === 'reminders' && !remindersLoaded) loadReminderHistory()"
              class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors"
              :class="drawerTab === tab[0] ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400 hover:text-slate-600'"
            >
              {{ tab[1] }}
            </button>
          </div>

          <!-- Info tab -->
          <div v-if="drawerTab === 'info'" class="space-y-4 px-6 py-5">
            <div class="flex items-center gap-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass[drawerTarget.status]">{{ statusLabels[drawerTarget.status] }}</span>
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="remainingBadgeClass(drawerTarget.remaining_days)">{{ remainingLabel(drawerTarget.remaining_days) }}</span>
            </div>

            <div class="rounded-xl bg-primary-soft p-4">
              <p class="text-xs text-primary-dark">Jatuh Tempo</p>
              <p class="text-xl font-semibold text-primary-dark">{{ formatDate(drawerTarget.due_date) }}</p>
              <p v-if="drawerTarget.amount" class="mt-1 text-xs text-slate-500">Nominal: {{ formatCurrency(drawerTarget.amount) }}</p>
            </div>

            <div>
              <p class="mb-1 text-xs font-medium text-slate-500">PIC</p>
              <p class="text-sm text-slate-700">{{ employeeName(drawerTarget.pic) }}</p>
            </div>

            <div v-if="drawerTarget.description">
              <p class="mb-1 text-xs font-medium text-slate-500">Deskripsi</p>
              <p class="text-sm text-slate-700">{{ drawerTarget.description }}</p>
            </div>

            <div class="flex gap-2 pt-2">
              <button v-if="canEdit" @click="openEditModal(drawerTarget)" class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                <Pencil class="h-4 w-4" :stroke-width="1.75" /> Edit
              </button>
              <button v-if="canDelete" @click="handleDelete(drawerTarget)" class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-200 py-2.5 text-sm font-medium text-red-500 hover:bg-red-50">
                <Trash2 class="h-4 w-4" :stroke-width="1.75" /> Hapus
              </button>
            </div>
          </div>

          <!-- Recipients tab -->
          <div v-else-if="drawerTab === 'recipients'" class="space-y-4 px-6 py-5">
            <div v-if="!drawerTarget.recipients || drawerTarget.recipients.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              Belum ada recipient reminder.
            </div>
            <div v-else class="space-y-2">
              <div v-for="r in drawerTarget.recipients" :key="r.id" class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2.5 text-sm">
                <span class="text-slate-700">{{ recipientLabel(r) }}</span>
                <button v-if="canEdit" @click="removeRecipient(r)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500">
                  <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                </button>
              </div>
            </div>

            <div v-if="canEdit" class="space-y-2 rounded-xl border border-slate-200 p-3">
              <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500"><UserPlus class="h-3.5 w-3.5" :stroke-width="1.75" /> Tambah Recipient</p>
              <select v-model="newRecipientType" class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="pic">PIC Obligation</option>
                <option v-if="recipientDirectoryAvailable" value="user">User Tertentu</option>
                <option v-if="recipientDirectoryAvailable" value="role">Role</option>
              </select>
              <p v-if="!recipientDirectoryAvailable" class="text-xs text-amber-600">
                Daftar user/role tidak bisa dimuat (butuh akses 'view users'). Recipient tipe PIC tetap bisa ditambahkan.
              </p>
              <select v-if="newRecipientType === 'user'" v-model.number="newRecipientUserId" class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">- Pilih user -</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
              </select>
              <select v-if="newRecipientType === 'role'" v-model="newRecipientRole" class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">- Pilih role -</option>
                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
              </select>
              <p v-if="recipientError" class="text-xs text-red-600">{{ recipientError }}</p>
              <button
                @click="addRecipient"
                :disabled="recipientSaving || (newRecipientType === 'user' && !newRecipientUserId) || (newRecipientType === 'role' && !newRecipientRole)"
                class="w-full rounded-lg bg-primary py-2 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50"
              >
                {{ recipientSaving ? 'Menyimpan...' : 'Tambah' }}
              </button>
            </div>
          </div>

          <!-- Reminders tab -->
          <div v-else class="space-y-3 px-6 py-5">
            <div v-if="remindersLoading" class="text-sm text-slate-400">Memuat riwayat reminder...</div>
            <div v-else-if="reminders.length === 0" class="flex items-center gap-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              <Bell class="h-4 w-4 shrink-0" :stroke-width="1.75" /> Belum ada reminder yang terkirim.
            </div>
            <div v-else class="space-y-2">
              <div v-for="rem in reminders" :key="rem.id" class="rounded-xl border border-slate-100 px-3 py-2.5 text-sm">
                <div class="flex items-center justify-between">
                  <span class="font-medium text-slate-700">{{ rem.notifiable.name }}</span>
                  <span class="flex items-center gap-1 text-xs text-slate-400"><History class="h-3 w-3" :stroke-width="1.75" /> {{ formatDateTime(rem.created_at) }}</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">
                  Milestone H-{{ rem.data.milestone }} · via {{ rem.data.recipient_type }}<span v-if="rem.data.recipient_role"> ({{ rem.data.recipient_role }})</span>
                  · {{ rem.read_at ? 'Sudah dibaca' : 'Belum dibaca' }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>