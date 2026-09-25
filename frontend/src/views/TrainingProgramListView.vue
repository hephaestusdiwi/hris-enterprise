<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Plus, Pencil, Trash2, X, Filter, GraduationCap, Bell, UserPlus, History, Users, CalendarPlus,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

type ProgramStatus = 'draft' | 'scheduled' | 'ongoing' | 'completed' | 'cancelled'
type SessionStatus = 'scheduled' | 'ongoing' | 'completed' | 'cancelled'
type SessionMode = 'online' | 'offline' | 'hybrid'
type ParticipantStatus = 'registered' | 'attended' | 'absent' | 'completed' | 'cancelled'
type RecipientType = 'user' | 'pic' | 'role'
type TrainingType = 'internal' | 'external'

interface Company { id: number; name: string }
interface Category { id: number; name: string }
interface Employee { id: number; first_name: string; last_name: string | null }
interface UserOption { id: number; name: string; email: string }
interface RecipientRow { id: number; recipient_type: RecipientType; role: string | null; user: UserOption | null }
interface ParticipantRow {
  id: number
  status: ParticipantStatus
  score: string | null
  notes: string | null
  employee: Employee
}
interface SessionRow {
  id: number
  name: string
  trainer_name: string | null
  trainer_employee_id: number | null
  location: string | null
  mode: SessionMode
  start_at: string
  end_at: string | null
  quota: number | null
  status: SessionStatus
  notes: string | null
  remaining_days: number
  participant_count: number
  participants?: ParticipantRow[]
}
interface ProgramRow {
  id: number
  company: Company
  category: Category
  title: string
  description: string | null
  type: TrainingType
  organizer: string | null
  pic: Employee | null
  budget: string | null
  status: ProgramStatus
  sessions_count?: number
  sessions?: SessionRow[]
  recipients?: RecipientRow[]
}
interface ReminderRow {
  id: string
  read_at: string | null
  created_at: string
  data: { milestone: number; recipient_type: string; recipient_role: string | null }
  notifiable: UserOption
}

const authStore = useAuthStore()
const canCreate = computed(() => authStore.permissions.includes('create trainings'))
const canEdit = computed(() => authStore.permissions.includes('edit trainings'))
const canDelete = computed(() => authStore.permissions.includes('delete trainings'))

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

const programStatusLabels: Record<ProgramStatus, string> = {
  draft: 'Draft', scheduled: 'Terjadwal', ongoing: 'Berjalan', completed: 'Selesai', cancelled: 'Dibatalkan',
}
const programStatusBadge: Record<ProgramStatus, string> = {
  draft: 'bg-slate-100 text-slate-500',
  scheduled: 'bg-sky-50 text-sky-600',
  ongoing: 'bg-primary-soft text-primary-dark',
  completed: 'bg-emerald-50 text-emerald-600',
  cancelled: 'bg-red-50 text-red-500',
}
const sessionStatusLabels: Record<SessionStatus, string> = {
  scheduled: 'Terjadwal', ongoing: 'Berjalan', completed: 'Selesai', cancelled: 'Dibatalkan',
}
const participantStatusLabels: Record<ParticipantStatus, string> = {
  registered: 'Terdaftar', attended: 'Hadir', absent: 'Tidak Hadir', completed: 'Selesai', cancelled: 'Dibatalkan',
}
const typeLabels: Record<TrainingType, string> = { internal: 'Internal', external: 'Eksternal' }
const modeLabels: Record<SessionMode, string> = { online: 'Online', offline: 'Offline', hybrid: 'Hybrid' }

function remainingBadgeClass(days: number) {
  if (days < 0) return 'bg-red-50 text-red-600'
  if (days <= 7) return 'bg-amber-50 text-amber-600'
  if (days <= 30) return 'bg-sky-50 text-sky-600'
  return 'bg-slate-100 text-slate-500'
}
function remainingLabel(days: number) {
  if (days < 0) return `Telat ${Math.abs(days)} hari`
  if (days === 0) return 'Mulai hari ini'
  return `H-${days}`
}

// ---------- Master data ----------
const companies = ref<Company[]>([])
const categories = ref<Category[]>([])
const employees = ref<Employee[]>([])
const users = ref<UserOption[]>([])
const roles = ref<string[]>([])
const recipientDirectoryAvailable = ref(true)

async function loadMasterData() {
  const [companyRes, categoryRes, employeeRes] = await Promise.all([
    apiClient.get('/api/companies'),
    apiClient.get('/api/training-categories'),
    apiClient.get('/api/employees', { params: { per_page: 200 } }),
  ])
  companies.value = companyRes.data.data.data
  categories.value = categoryRes.data.data.data
  employees.value = employeeRes.data.data.data

  try {
    const [userRes, roleRes] = await Promise.all([apiClient.get('/api/users'), apiClient.get('/api/roles')])
    users.value = userRes.data.data.data
    roles.value = roleRes.data.data
  } catch {
    recipientDirectoryAvailable.value = false
  }
}

// ---------- List ----------
const programs = ref<ProgramRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const filters = reactive({ company_id: '', training_category_id: '', status: '' })

async function loadList() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/training-programs', {
      params: {
        company_id: filters.company_id || undefined,
        training_category_id: filters.training_category_id || undefined,
        status: filters.status || undefined,
      },
    })
    programs.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar Training Program.'
  } finally {
    loading.value = false
  }
}

// ---------- Create / Edit program modal ----------
const showFormModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  id: 0,
  company_id: 0,
  training_category_id: 0,
  title: '',
  description: '',
  type: 'internal' as TrainingType,
  organizer: '',
  pic_employee_id: null as number | null,
  budget: '' as string | number,
  status: 'draft' as ProgramStatus,
})

function resetForm() {
  form.id = 0
  form.company_id = companies.value[0]?.id ?? 0
  form.training_category_id = categories.value[0]?.id ?? 0
  form.title = ''
  form.description = ''
  form.type = 'internal'
  form.organizer = ''
  form.pic_employee_id = null
  form.budget = ''
  form.status = 'draft'
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  resetForm()
  showFormModal.value = true
}

function openEditModal(row: ProgramRow) {
  isEditing.value = true
  formError.value = ''
  form.id = row.id
  form.company_id = row.company.id
  form.training_category_id = row.category.id
  form.title = row.title
  form.description = row.description ?? ''
  form.type = row.type
  form.organizer = row.organizer ?? ''
  form.pic_employee_id = row.pic?.id ?? null
  form.budget = row.budget ?? ''
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
      await apiClient.put(`/api/training-programs/${form.id}`, {
        company_id: form.company_id,
        training_category_id: form.training_category_id,
        title: form.title,
        description: form.description || null,
        organizer: form.organizer || null,
        pic_employee_id: form.pic_employee_id,
        budget: form.budget === '' ? null : form.budget,
        status: form.status,
      })
    } else {
      await apiClient.post('/api/training-programs', {
        company_id: form.company_id,
        training_category_id: form.training_category_id,
        title: form.title,
        description: form.description || null,
        type: form.type,
        organizer: form.organizer || null,
        pic_employee_id: form.pic_employee_id,
        budget: form.budget === '' ? null : form.budget,
      })
    }
    showFormModal.value = false
    await loadList()
    if (isEditing.value && drawerTarget.value?.id === form.id) await openDrawer({ id: form.id } as ProgramRow)
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: ProgramRow) {
  if (!confirm(`Hapus Training Program "${row.title}"?`)) return
  try {
    await apiClient.delete(`/api/training-programs/${row.id}`)
    if (drawerTarget.value?.id === row.id) closeDrawer()
    await loadList()
  } catch {
    alert('Gagal menghapus Training Program.')
  }
}

// ---------- Program detail drawer ----------
const showDrawer = ref(false)
const drawerTarget = ref<ProgramRow | null>(null)
const drawerTab = ref<'info' | 'sessions' | 'recipients'>('info')
const drawerTabs: Array<[typeof drawerTab.value, string]> = [
  ['info', 'Info'],
  ['sessions', 'Sesi'],
  ['recipients', 'Recipients'],
]

async function openDrawer(row: ProgramRow) {
  drawerTab.value = 'info'
  try {
    const response = await apiClient.get(`/api/training-programs/${row.id}`)
    drawerTarget.value = response.data.data
    showDrawer.value = true
  } catch {
    errorMessage.value = 'Gagal memuat detail Training Program.'
  }
}
function closeDrawer() {
  showDrawer.value = false
  drawerTarget.value = null
}
async function refreshDrawer() {
  if (drawerTarget.value) await openDrawer({ id: drawerTarget.value.id } as ProgramRow)
}

// ---------- Session create/edit ----------
const showSessionModal = ref(false)
const isEditingSession = ref(false)
const sessionSaving = ref(false)
const sessionFormError = ref('')
const sessionForm = reactive({
  id: 0,
  name: '',
  trainer_name: '',
  trainer_employee_id: null as number | null,
  location: '',
  mode: 'offline' as SessionMode,
  start_at: '',
  end_at: '',
  quota: '' as string | number,
  status: 'scheduled' as SessionStatus,
  notes: '',
})

function resetSessionForm() {
  sessionForm.id = 0
  sessionForm.name = ''
  sessionForm.trainer_name = ''
  sessionForm.trainer_employee_id = null
  sessionForm.location = ''
  sessionForm.mode = 'offline'
  sessionForm.start_at = ''
  sessionForm.end_at = ''
  sessionForm.quota = ''
  sessionForm.status = 'scheduled'
  sessionForm.notes = ''
}
function openCreateSessionModal() {
  isEditingSession.value = false
  sessionFormError.value = ''
  resetSessionForm()
  showSessionModal.value = true
}
function toDatetimeLocal(value: string) {
  const d = new Date(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
function openEditSessionModal(session: SessionRow) {
  isEditingSession.value = true
  sessionFormError.value = ''
  sessionForm.id = session.id
  sessionForm.name = session.name
  sessionForm.trainer_name = session.trainer_name ?? ''
  sessionForm.trainer_employee_id = session.trainer_employee_id
  sessionForm.location = session.location ?? ''
  sessionForm.mode = session.mode
  sessionForm.start_at = toDatetimeLocal(session.start_at)
  sessionForm.end_at = session.end_at ? toDatetimeLocal(session.end_at) : ''
  sessionForm.quota = session.quota ?? ''
  sessionForm.status = session.status
  sessionForm.notes = session.notes ?? ''
  showSessionModal.value = true
}
function closeSessionModal() {
  showSessionModal.value = false
}
async function handleSessionSubmit() {
  if (!drawerTarget.value) return
  sessionSaving.value = true
  sessionFormError.value = ''
  const payload = {
    name: sessionForm.name,
    trainer_name: sessionForm.trainer_name || null,
    trainer_employee_id: sessionForm.trainer_employee_id,
    location: sessionForm.location || null,
    mode: sessionForm.mode,
    start_at: sessionForm.start_at,
    end_at: sessionForm.end_at || null,
    quota: sessionForm.quota === '' ? null : sessionForm.quota,
    notes: sessionForm.notes || null,
    ...(isEditingSession.value ? { status: sessionForm.status } : {}),
  }
  try {
    if (isEditingSession.value) {
      await apiClient.put(`/api/training-programs/${drawerTarget.value.id}/sessions/${sessionForm.id}`, payload)
    } else {
      await apiClient.post(`/api/training-programs/${drawerTarget.value.id}/sessions`, payload)
    }
    showSessionModal.value = false
    await refreshDrawer()
  } catch (err: any) {
    sessionFormError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    sessionSaving.value = false
  }
}
async function handleDeleteSession(session: SessionRow) {
  if (!drawerTarget.value) return
  if (!confirm(`Hapus sesi "${session.name}"?`)) return
  try {
    await apiClient.delete(`/api/training-programs/${drawerTarget.value.id}/sessions/${session.id}`)
    await refreshDrawer()
  } catch {
    alert('Gagal menghapus sesi.')
  }
}

// ---------- Session detail drawer (participants + reminders) ----------
const showSessionDrawer = ref(false)
const sessionDrawerTarget = ref<SessionRow | null>(null)
const sessionDrawerTab = ref<'participants' | 'reminders'>('participants')
const newParticipantEmployeeId = ref<number | null>(null)
const participantSaving = ref(false)
const participantError = ref('')
const sessionReminders = ref<ReminderRow[]>([])
const sessionRemindersLoading = ref(false)
const sessionRemindersLoaded = ref(false)

async function openSessionDrawer(session: SessionRow) {
  sessionDrawerTarget.value = session
  sessionDrawerTab.value = 'participants'
  sessionReminders.value = []
  sessionRemindersLoaded.value = false
  newParticipantEmployeeId.value = null
  showSessionDrawer.value = true
}
function closeSessionDrawer() {
  showSessionDrawer.value = false
  sessionDrawerTarget.value = null
}
async function refreshSessionDrawer() {
  if (!drawerTarget.value || !sessionDrawerTarget.value) return
  const sessionId = sessionDrawerTarget.value.id
  await refreshDrawer()
  const updated = drawerTarget.value?.sessions?.find((s) => s.id === sessionId)
  if (updated) sessionDrawerTarget.value = updated
}

async function addParticipant() {
  if (!drawerTarget.value || !sessionDrawerTarget.value || !newParticipantEmployeeId.value) return
  participantSaving.value = true
  participantError.value = ''
  try {
    await apiClient.post(
      `/api/training-programs/${drawerTarget.value.id}/sessions/${sessionDrawerTarget.value.id}/participants`,
      { employee_id: newParticipantEmployeeId.value },
    )
    newParticipantEmployeeId.value = null
    await refreshSessionDrawer()
  } catch (err: any) {
    participantError.value = err.response?.data?.message || 'Gagal mendaftarkan peserta.'
  } finally {
    participantSaving.value = false
  }
}
async function updateParticipant(participant: ParticipantRow, changes: Partial<{ status: ParticipantStatus; score: string | number | null }>) {
  if (!drawerTarget.value || !sessionDrawerTarget.value) return
  try {
    await apiClient.put(
      `/api/training-programs/${drawerTarget.value.id}/sessions/${sessionDrawerTarget.value.id}/participants/${participant.id}`,
      changes,
    )
    await refreshSessionDrawer()
  } catch {
    alert('Gagal memperbarui data peserta.')
  }
}
function onParticipantStatusChange(participant: ParticipantRow, event: Event) {
  const value = (event.target as HTMLSelectElement).value as ParticipantStatus
  updateParticipant(participant, { status: value })
}
function onParticipantScoreChange(participant: ParticipantRow, event: Event) {
  const value = (event.target as HTMLInputElement).value
  updateParticipant(participant, { score: value || null })
}
async function removeParticipant(participant: ParticipantRow) {
  if (!drawerTarget.value || !sessionDrawerTarget.value) return
  if (!confirm(`Hapus ${employeeName(participant.employee)} dari sesi ini?`)) return
  try {
    await apiClient.delete(
      `/api/training-programs/${drawerTarget.value.id}/sessions/${sessionDrawerTarget.value.id}/participants/${participant.id}`,
    )
    await refreshSessionDrawer()
  } catch {
    alert('Gagal menghapus peserta.')
  }
}
async function loadSessionReminders() {
  if (!sessionDrawerTarget.value) return
  sessionRemindersLoading.value = true
  try {
    const response = await apiClient.get(`/api/training-sessions/${sessionDrawerTarget.value.id}/reminders`)
    sessionReminders.value = response.data.data
    sessionRemindersLoaded.value = true
  } catch {
    sessionReminders.value = []
  } finally {
    sessionRemindersLoading.value = false
  }
}

// ---------- Recipients (program level) ----------
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
    await apiClient.post(`/api/training-programs/${drawerTarget.value.id}/recipients`, payload)
    newRecipientUserId.value = null
    newRecipientRole.value = ''
    await refreshDrawer()
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
    await apiClient.delete(`/api/training-programs/${drawerTarget.value.id}/recipients/${recipient.id}`)
    await refreshDrawer()
  } catch {
    alert('Gagal menghapus recipient.')
  }
}
function recipientLabel(r: RecipientRow) {
  if (r.recipient_type === 'pic') return 'PIC Program (mengikuti PIC saat ini)'
  if (r.recipient_type === 'user') return r.user ? `${r.user.name} (${r.user.email})` : 'User'
  return `Role: ${r.role}`
}

onMounted(async () => {
  await loadMasterData()
  await loadList()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Training Program</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola pelatihan karyawan -- sesi/batch, peserta, dan reminder.</p>
      </div>
      <button
        v-if="canCreate"
        @click="openCreateModal"
        :disabled="companies.length === 0 || categories.length === 0"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah Training
      </button>
    </div>

    <p v-if="categories.length === 0 && !loading" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-700">
      Belum ada Training Category. Buat kategori dulu sebelum membuat Training Program.
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <Filter class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
      <select v-model="filters.company_id" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua company</option>
        <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.training_category_id" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.status" @change="loadList" class="rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
        <option value="">Semua status</option>
        <option v-for="(label, value) in programStatusLabels" :key="value" :value="value">{{ label }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Training</th>
            <th class="px-5 py-3 font-medium text-slate-500">Company</th>
            <th class="px-5 py-3 font-medium text-slate-500">PIC</th>
            <th class="px-5 py-3 text-center font-medium text-slate-500">Sesi</th>
            <th class="px-5 py-3 font-medium text-slate-500">Status</th>
            <th class="px-5 py-3 text-right font-medium text-slate-500">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="programs.length === 0"><td colspan="6" class="px-5 py-6 text-center text-sm text-slate-400">Tidak ada data.</td></tr>
          <tr v-for="row in programs" :key="row.id" @click="openDrawer(row)" class="cursor-pointer border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
            <td class="px-5 py-3.5">
              <p class="font-medium text-slate-800">{{ row.title }}</p>
              <p class="text-xs text-slate-400">{{ row.category.name }} · {{ typeLabels[row.type] }}</p>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ row.company.name }}</td>
            <td class="px-5 py-3.5 text-slate-500">{{ employeeName(row.pic) }}</td>
            <td class="px-5 py-3.5 text-center text-slate-600">{{ row.sessions_count ?? 0 }}</td>
            <td class="px-5 py-3.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="programStatusBadge[row.status]">{{ programStatusLabels[row.status] }}</span></td>
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

    <!-- Create / Edit program modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-6">
        <div class="max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Edit Training' : 'Tambah Training' }}</h2>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Kategori</label>
              <select v-model.number="form.training_category_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Judul Training</label>
              <input v-model="form.title" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
              <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tipe</label>
                <select v-model="form.type" :disabled="isEditing" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none disabled:bg-slate-50 disabled:text-slate-400">
                  <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Penyelenggara</label>
                <input v-model="form.organizer" type="text" placeholder="Nama vendor (opsional)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PIC</label>
              <select v-model="form.pic_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">- Tanpa PIC -</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Budget (opsional)</label>
              <input v-model="form.budget" type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div v-if="isEditing">
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="form.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, value) in programStatusLabels" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <button type="submit" :disabled="saving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Program detail drawer -->
    <Teleport to="body">
      <div v-if="showDrawer && drawerTarget" class="fixed inset-0 z-50 flex justify-end bg-slate-900/30">
        <div class="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <GraduationCap class="h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <h2 class="truncate text-lg font-semibold text-slate-900">{{ drawerTarget.title }}</h2>
              </div>
              <p class="mt-0.5 text-sm text-slate-500">{{ drawerTarget.category.name }} · {{ drawerTarget.company.name }}</p>
            </div>
            <button @click="closeDrawer" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="flex gap-1 border-b border-slate-100 px-6 pt-3">
            <button
              v-for="tab in drawerTabs"
              :key="tab[0]"
              @click="drawerTab = tab[0]"
              class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors"
              :class="drawerTab === tab[0] ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400 hover:text-slate-600'"
            >
              {{ tab[1] }}
            </button>
          </div>

          <!-- Info tab -->
          <div v-if="drawerTab === 'info'" class="space-y-4 px-6 py-5">
            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="programStatusBadge[drawerTarget.status]">{{ programStatusLabels[drawerTarget.status] }}</span>

            <div>
              <p class="mb-1 text-xs font-medium text-slate-500">PIC</p>
              <p class="text-sm text-slate-700">{{ employeeName(drawerTarget.pic) }}</p>
            </div>
            <div v-if="drawerTarget.organizer">
              <p class="mb-1 text-xs font-medium text-slate-500">Penyelenggara</p>
              <p class="text-sm text-slate-700">{{ drawerTarget.organizer }}</p>
            </div>
            <div v-if="drawerTarget.budget">
              <p class="mb-1 text-xs font-medium text-slate-500">Budget</p>
              <p class="text-sm text-slate-700">{{ formatCurrency(drawerTarget.budget) }}</p>
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

          <!-- Sessions tab -->
          <div v-else-if="drawerTab === 'sessions'" class="space-y-3 px-6 py-5">
            <button v-if="canEdit" @click="openCreateSessionModal" class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-sm font-medium text-slate-500 hover:border-primary hover:text-primary-dark">
              <CalendarPlus class="h-4 w-4" :stroke-width="1.75" /> Tambah Sesi
            </button>

            <div v-if="!drawerTarget.sessions || drawerTarget.sessions.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              Belum ada sesi/batch.
            </div>
            <div v-for="session in drawerTarget.sessions" :key="session.id" class="rounded-xl border border-slate-100 p-3.5">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="font-medium text-slate-800">{{ session.name }}</p>
                  <p class="text-xs text-slate-500">{{ formatDateTime(session.start_at) }} · {{ modeLabels[session.mode] }}<span v-if="session.location"> · {{ session.location }}</span></p>
                  <p v-if="session.trainer_name" class="text-xs text-slate-400">Trainer: {{ session.trainer_name }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="remainingBadgeClass(session.remaining_days)">{{ remainingLabel(session.remaining_days) }}</span>
                </div>
              </div>
              <div class="mt-2 flex items-center justify-between">
                <p class="text-xs text-slate-500">{{ session.participant_count }}<span v-if="session.quota"> / {{ session.quota }}</span> peserta · {{ sessionStatusLabels[session.status] }}</p>
                <div class="flex items-center gap-1">
                  <button @click="openSessionDrawer(session)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Kelola peserta">
                    <Users class="h-3.5 w-3.5" :stroke-width="1.75" />
                  </button>
                  <button v-if="canEdit" @click="openEditSessionModal(session)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" />
                  </button>
                  <button v-if="canDelete" @click="handleDeleteSession(session)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500">
                    <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Recipients tab -->
          <div v-else class="space-y-4 px-6 py-5">
            <div v-if="!drawerTarget.recipients || drawerTarget.recipients.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              Belum ada recipient tambahan. Peserta tiap sesi otomatis dapat reminder.
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
                <option value="pic">PIC Program</option>
                <option v-if="recipientDirectoryAvailable" value="user">User Tertentu</option>
                <option v-if="recipientDirectoryAvailable" value="role">Role</option>
              </select>
              <p v-if="!recipientDirectoryAvailable" class="text-xs text-amber-600">Daftar user/role tidak bisa dimuat (butuh akses 'view users').</p>
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
        </div>
      </div>
    </Teleport>

    <!-- Create / Edit session modal -->
    <Teleport to="body">
      <div v-if="showSessionModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/30 px-4 py-6">
        <div class="max-h-[calc(100vh-3rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">{{ isEditingSession ? 'Edit Sesi' : 'Tambah Sesi' }}</h2>
            <button @click="closeSessionModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" /></button>
          </div>

          <form @submit.prevent="handleSessionSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama Sesi</label>
              <input v-model="sessionForm.name" type="text" required placeholder="mis. Batch 1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mulai</label>
                <input v-model="sessionForm.start_at" type="datetime-local" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Selesai (opsional)</label>
                <input v-model="sessionForm.end_at" type="datetime-local" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mode</label>
                <select v-model="sessionForm.mode" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option v-for="(label, value) in modeLabels" :key="value" :value="value">{{ label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Lokasi</label>
                <input v-model="sessionForm.location" type="text" placeholder="Ruang/link meeting" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Trainer (Internal)</label>
                <select v-model="sessionForm.trainer_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">- Tidak ada -</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Trainer (Nama Bebas)</label>
                <input v-model="sessionForm.trainer_name" type="text" placeholder="mis. konsultan eksternal" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Kuota (opsional)</label>
              <input v-model="sessionForm.quota" type="number" min="1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>

            <div v-if="isEditingSession">
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="sessionForm.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option v-for="(label, value) in sessionStatusLabels" :key="value" :value="value">{{ label }}</option>
              </select>
              <p class="mt-1 text-xs text-slate-400">Reminder otomatis berhenti begitu status bukan Terjadwal.</p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
              <textarea v-model="sessionForm.notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>

            <p v-if="sessionFormError" class="text-sm text-red-600">{{ sessionFormError }}</p>

            <button type="submit" :disabled="sessionSaving" class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50">
              {{ sessionSaving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Session detail drawer (participants + reminders), stacked on top of program drawer -->
    <Teleport to="body">
      <div v-if="showSessionDrawer && sessionDrawerTarget" class="fixed inset-0 z-[60] flex justify-end bg-slate-900/40">
        <div class="h-full w-full max-w-lg overflow-y-auto bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
              <h2 class="truncate text-lg font-semibold text-slate-900">{{ sessionDrawerTarget.name }}</h2>
              <p class="mt-0.5 text-sm text-slate-500">{{ formatDateTime(sessionDrawerTarget.start_at) }}</p>
            </div>
            <button @click="closeSessionDrawer" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-50"><X class="h-5 w-5" :stroke-width="1.75" /></button>
          </div>

          <div class="flex gap-1 border-b border-slate-100 px-6 pt-3">
            <button @click="sessionDrawerTab = 'participants'" class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors" :class="sessionDrawerTab === 'participants' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400 hover:text-slate-600'">Peserta</button>
            <button @click="sessionDrawerTab = 'reminders'; if (!sessionRemindersLoaded) loadSessionReminders()" class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors" :class="sessionDrawerTab === 'reminders' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400 hover:text-slate-600'">Reminder</button>
          </div>

          <!-- Participants tab -->
          <div v-if="sessionDrawerTab === 'participants'" class="space-y-3 px-6 py-5">
            <div v-if="canEdit" class="flex gap-2 rounded-xl border border-slate-200 p-3">
              <select v-model.number="newParticipantEmployeeId" class="flex-1 rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">- Pilih karyawan -</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeName(e) }}</option>
              </select>
              <button @click="addParticipant" :disabled="participantSaving || !newParticipantEmployeeId" class="shrink-0 rounded-lg bg-primary px-3 text-xs font-medium text-white hover:bg-primary-dark disabled:opacity-50">
                Daftarkan
              </button>
            </div>
            <p v-if="participantError" class="text-xs text-red-600">{{ participantError }}</p>

            <div v-if="!sessionDrawerTarget.participants || sessionDrawerTarget.participants.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              Belum ada peserta.
            </div>
            <div v-for="p in sessionDrawerTarget.participants" :key="p.id" class="rounded-xl border border-slate-100 p-3">
              <div class="flex items-center justify-between">
                <p class="font-medium text-slate-800">{{ employeeName(p.employee) }}</p>
                <button v-if="canEdit" @click="removeParticipant(p)" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500">
                  <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                </button>
              </div>
              <div v-if="canEdit" class="mt-2 grid grid-cols-2 gap-2">
                <select
                  :value="p.status"
                  @change="onParticipantStatusChange(p, $event)"
                  class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-primary focus:outline-none"
                >
                  <option v-for="(label, value) in participantStatusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
                <input
                  :value="p.score"
                  @change="onParticipantScoreChange(p, $event)"
                  type="number" min="0" max="100" step="0.01" placeholder="Nilai"
                  class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-primary focus:outline-none"
                />
              </div>
              <p v-else class="mt-1 text-xs text-slate-500">{{ participantStatusLabels[p.status] }}<span v-if="p.score"> · Nilai: {{ p.score }}</span></p>
            </div>
          </div>

          <!-- Reminders tab -->
          <div v-else class="space-y-3 px-6 py-5">
            <div v-if="sessionRemindersLoading" class="text-sm text-slate-400">Memuat riwayat reminder...</div>
            <div v-else-if="sessionReminders.length === 0" class="flex items-center gap-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
              <Bell class="h-4 w-4 shrink-0" :stroke-width="1.75" /> Belum ada reminder yang terkirim.
            </div>
            <div v-else class="space-y-2">
              <div v-for="rem in sessionReminders" :key="rem.id" class="rounded-xl border border-slate-100 px-3 py-2.5 text-sm">
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