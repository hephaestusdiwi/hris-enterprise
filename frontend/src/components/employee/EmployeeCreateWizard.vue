<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { X, ArrowLeft, ArrowRight, Check, Copy, UserRound, Building2, KeyRound, CheckCircle2 } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Ref {
  id: number
  name: string
}

interface ManagerOption {
  id: number
  employee_number: string
  first_name: string
  last_name: string | null
}

interface AvailableUser {
  id: number
  name: string
  email: string
}

const emit = defineEmits<{
  close: []
  created: []
}>()

const STEPS = [
  { number: 1, label: 'Personal Data' },
  { number: 2, label: 'Employment Data' },
  { number: 3, label: 'Account' },
] as const

const currentStep = ref(1)
const isSuccess = ref(false)
const saving = ref(false)
const formError = ref('')
const loadingRefs = ref(true)

const companies = ref<Ref[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])
const workingSchedules = ref<Ref[]>([])
const employmentStatuses = ref<Ref[]>([])
const managerOptions = ref<ManagerOption[]>([])
const availableUsers = ref<AvailableUser[]>([])

const inviteLink = ref<string | null>(null)
const linkCopied = ref(false)

const form = reactive({
  // Step 1 — Personal
  first_name: '',
  last_name: '',
  gender: 'male',
  birth_place: '',
  birth_date: '',
  marital_status: '',
  phone: '',
  personal_email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  national_id_number: '',
  tax_number: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_holder_name: '',

  // Step 2 — Employment
  employee_number: '',
  company_id: 0,
  branch_id: null as number | null,
  department_id: null as number | null,
  position_id: null as number | null,
  job_level_id: null as number | null,
  working_schedule_id: null as number | null,
  employment_status_id: null as number | null,
  manager_employee_id: null as number | null,
  join_date: new Date().toISOString().slice(0, 10),
  resign_date: '',

  // Step 3 — Account
  account_mode: 'new' as 'existing' | 'new',
  user_id: null as number | null,
  new_user_email: '',
})

function fullName(row: { first_name: string; last_name: string | null }) {
  return [row.first_name, row.last_name].filter(Boolean).join(' ')
}

const step1Valid = computed(() => {
  return form.first_name.trim() !== '' && form.gender !== ''
})

const step2Valid = computed(() => {
  return form.employee_number.trim() !== '' && form.company_id > 0 && form.join_date !== ''
})

const step3Valid = computed(() => {
  if (form.account_mode === 'existing') return form.user_id !== null
  return /^\S+@\S+\.\S+$/.test(form.new_user_email)
})

const canGoNext = computed(() => {
  if (currentStep.value === 1) return step1Valid.value
  if (currentStep.value === 2) return step2Valid.value
  return false
})

function goNext() {
  if (currentStep.value < 3 && canGoNext.value) {
    currentStep.value++
  }
}

function goBack() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

async function loadReferenceData() {
  loadingRefs.value = true
  try {
    const [companyRes, branchRes, departmentRes, positionRes, jobLevelRes, workingScheduleRes, statusRes, employeeRes, nextNumberRes, availableUsersRes] =
      await Promise.all([
        apiClient.get('/api/companies'),
        apiClient.get('/api/branches'),
        apiClient.get('/api/departments'),
        apiClient.get('/api/positions'),
        apiClient.get('/api/job-levels'),
        apiClient.get('/api/working-schedules'),
        apiClient.get('/api/employment-statuses'),
        apiClient.get('/api/employees'),
        apiClient.get('/api/employees/next-number'),
        apiClient.get('/api/employees/available-users'),
      ])

    companies.value = companyRes.data.data.data
    branches.value = branchRes.data.data.data
    departments.value = departmentRes.data.data.data
    positions.value = positionRes.data.data.data
    jobLevels.value = jobLevelRes.data.data.data
    workingSchedules.value = workingScheduleRes.data.data.data
    employmentStatuses.value = statusRes.data.data.data
    managerOptions.value = employeeRes.data.data.data
    availableUsers.value = availableUsersRes.data.data

    form.employee_number = nextNumberRes.data.data.employee_number
    form.company_id = companies.value[0]?.id ?? 0
  } catch {
    formError.value = 'Gagal memuat data referensi. Coba tutup dan buka lagi.'
  } finally {
    loadingRefs.value = false
  }
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  const payload: Record<string, unknown> = {
    employee_number: form.employee_number,
    company_id: form.company_id,
    branch_id: form.branch_id,
    department_id: form.department_id,
    position_id: form.position_id,
    job_level_id: form.job_level_id,
    working_schedule_id: form.working_schedule_id,
    employment_status_id: form.employment_status_id,
    manager_employee_id: form.manager_employee_id,
    join_date: form.join_date,
    resign_date: form.resign_date || null,
    first_name: form.first_name,
    last_name: form.last_name || null,
    gender: form.gender,
    birth_place: form.birth_place || null,
    birth_date: form.birth_date || null,
    marital_status: form.marital_status || null,
    phone: form.phone || null,
    personal_email: form.personal_email || null,
    address: form.address || null,
    emergency_contact_name: form.emergency_contact_name || null,
    emergency_contact_phone: form.emergency_contact_phone || null,
    national_id_number: form.national_id_number || null,
    tax_number: form.tax_number || null,
    bank_name: form.bank_name || null,
    bank_account_number: form.bank_account_number || null,
    bank_account_holder_name: form.bank_account_holder_name || null,
  }

  if (form.account_mode === 'existing') {
    payload.user_id = form.user_id
  } else {
    payload.new_user = { email: form.new_user_email }
  }

  try {
    const response = await apiClient.post('/api/employees', payload)
    inviteLink.value = response.data.data.invite_link ?? null
    isSuccess.value = true
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
    // Kalau error karena field spesifik (mis. employee_number/email dobel), balikin ke step yang relevan
    const errors = err.response?.data?.errors
    if (errors) {
      const errorFields = Object.keys(errors)
      if (errorFields.some((f) => ['employee_number', 'company_id', 'join_date'].includes(f))) {
        currentStep.value = 2
      } else if (errorFields.some((f) => ['user_id', 'new_user.email'].includes(f))) {
        currentStep.value = 3
      } else {
        currentStep.value = 1
      }
    }
  } finally {
    saving.value = false
  }
}

async function copyInviteLink() {
  if (!inviteLink.value) return
  await navigator.clipboard.writeText(inviteLink.value)
  linkCopied.value = true
  setTimeout(() => (linkCopied.value = false), 2000)
}

function finish() {
  emit('created')
}

onMounted(loadReferenceData)
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4 py-8">
      <div class="flex max-h-full w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ isSuccess ? 'Employee Berhasil Dibuat' : 'Tambah Employee' }}
          </h2>
          <button @click="emit('close')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Step indicator -->
        <div v-if="!isSuccess" class="flex items-center justify-center gap-2 border-b border-slate-100 px-6 py-4">
          <template v-for="(step, index) in STEPS" :key="step.number">
            <div class="flex items-center gap-2">
              <div
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                :class="
                  currentStep > step.number
                    ? 'bg-primary text-white'
                    : currentStep === step.number
                      ? 'bg-primary text-white'
                      : 'bg-slate-100 text-slate-400'
                "
              >
                <Check v-if="currentStep > step.number" class="h-3.5 w-3.5" :stroke-width="3" />
                <span v-else>{{ step.number }}</span>
              </div>
              <span class="text-xs font-medium" :class="currentStep >= step.number ? 'text-slate-700' : 'text-slate-400'">
                {{ step.label }}
              </span>
            </div>
            <div v-if="index < STEPS.length - 1" class="h-px w-8 shrink-0" :class="currentStep > step.number ? 'bg-primary' : 'bg-slate-200'" />
          </template>
        </div>

        <!-- Loading state -->
        <div v-if="loadingRefs" class="flex-1 px-6 py-10 text-center text-sm text-slate-400">
          Memuat data...
        </div>

        <!-- Success state -->
        <div v-else-if="isSuccess" class="flex-1 space-y-5 px-6 py-6">
          <div class="flex flex-col items-center gap-2 text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
              <CheckCircle2 class="h-6 w-6" :stroke-width="1.75" />
            </div>
            <p class="text-sm text-slate-500">
              {{ fullName(form) }} ({{ form.employee_number }}) sudah tersimpan.
            </p>
          </div>

          <div v-if="inviteLink" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-medium text-amber-800">Invite link untuk aktivasi akun</p>
            <p class="mt-1 text-xs text-amber-700">
              Link ini cuma muncul sekali di sini. Copy sekarang dan kirim manual ke karyawan (chat/email) — dia akan set password sendiri lewat link ini.
            </p>
            <div class="mt-3 flex items-center gap-2">
              <input
                type="text"
                readonly
                :value="inviteLink"
                class="flex-1 truncate rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-slate-600"
              />
              <button
                @click="copyInviteLink"
                class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors"
                :class="linkCopied ? 'bg-emerald-500 text-white' : 'bg-amber-600 text-white hover:bg-amber-700'"
              >
                <Check v-if="linkCopied" class="h-3.5 w-3.5" :stroke-width="2" />
                <Copy v-else class="h-3.5 w-3.5" :stroke-width="1.75" />
                {{ linkCopied ? 'Tersalin' : 'Copy Link' }}
              </button>
            </div>
          </div>

          <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
            Foto profil belum diisi. Tambahkan lewat menu Actions &rarr; "Upload Foto Profil" setelah ini.
          </p>
        </div>

        <!-- Form steps -->
        <form v-else @submit.prevent="goNext" class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <!-- STEP 1: Personal -->
          <div v-if="currentStep === 1" class="space-y-5">
            <div class="mb-1 flex items-center gap-2">
              <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                <UserRound class="h-4 w-4" :stroke-width="1.75" />
              </div>
              <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Basic Information</h3>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Depan *</label>
                <input v-model="form.first_name" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Belakang</label>
                <input v-model="form.last_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Jenis Kelamin *</label>
                <select v-model="form.gender" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option value="male">Laki-laki</option>
                  <option value="female">Perempuan</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status Pernikahan</label>
                <select v-model="form.marital_status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option value="">-</option>
                  <option value="single">Belum Menikah</option>
                  <option value="married">Menikah</option>
                  <option value="divorced">Cerai</option>
                  <option value="widowed">Janda/Duda</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tempat Lahir</label>
                <input v-model="form.birth_place" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Lahir</label>
                <input v-model="form.birth_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Telepon</label>
                <input v-model="form.phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email Pribadi</label>
                <input v-model="form.personal_email" type="email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div class="col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Alamat</label>
                <textarea v-model="form.address" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Kontak Darurat</label>
                <input v-model="form.emergency_contact_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Telepon Darurat</label>
                <input v-model="form.emergency_contact_phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">NIK</label>
                <input v-model="form.national_id_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">NPWP</label>
                <input v-model="form.tax_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Bank</label>
                <input v-model="form.bank_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">No. Rekening</label>
                <input v-model="form.bank_account_number" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div class="col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Nama Pemilik Rekening</label>
                <input v-model="form.bank_account_holder_name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
          </div>

          <!-- STEP 2: Employment -->
          <div v-if="currentStep === 2" class="space-y-5">
            <div class="mb-1 flex items-center gap-2">
              <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                <Building2 class="h-4 w-4" :stroke-width="1.75" />
              </div>
              <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Employment Data</h3>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">No. Karyawan *</label>
                <input v-model="form.employee_number" type="text" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Bergabung *</label>
                <input v-model="form.join_date" type="date" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Company *</label>
                <select v-model.number="form.company_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Branch</label>
                <select v-model="form.branch_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                <select v-model="form.department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                <select v-model="form.position_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Job Level</label>
                <select v-model="form.job_level_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="jl in jobLevels" :key="jl.id" :value="jl.id">{{ jl.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Working Schedule</label>
                <select v-model="form.working_schedule_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="ws in workingSchedules" :key="ws.id" :value="ws.id">{{ ws.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Employment Status</label>
                <select v-model="form.employment_status_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="s in employmentStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Manager</label>
                <select v-model="form.manager_employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                  <option :value="null">-</option>
                  <option v-for="m in managerOptions" :key="m.id" :value="m.id">{{ fullName(m) }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tanggal Resign</label>
                <input v-model="form.resign_date" type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
              </div>
            </div>
            <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
              Approval Line otomatis mengikuti hierarki Job Level / Department / Branch / Company yang berlaku — tidak perlu diatur manual di sini.
            </p>
          </div>

          <!-- STEP 3: Account -->
          <div v-if="currentStep === 3" class="space-y-5">
            <div class="mb-1 flex items-center gap-2">
              <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-soft text-primary-dark">
                <KeyRound class="h-4 w-4" :stroke-width="1.75" />
              </div>
              <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Account</h3>
            </div>

            <div class="flex gap-2">
              <button
                type="button"
                @click="form.account_mode = 'new'"
                class="flex-1 rounded-xl border px-4 py-3 text-left text-sm transition-colors"
                :class="form.account_mode === 'new' ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <p class="font-medium">Buat akun baru</p>
                <p class="mt-0.5 text-xs opacity-80">Karyawan akan dapat invite link untuk aktivasi</p>
              </button>
              <button
                type="button"
                @click="form.account_mode = 'existing'"
                class="flex-1 rounded-xl border px-4 py-3 text-left text-sm transition-colors"
                :class="form.account_mode === 'existing' ? 'border-primary bg-primary-soft text-primary-dark' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <p class="font-medium">Pakai akun existing</p>
                <p class="mt-0.5 text-xs opacity-80">Link ke User account yang sudah ada</p>
              </button>
            </div>

            <div v-if="form.account_mode === 'new'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Email Karyawan *</label>
              <input
                v-model="form.new_user_email"
                type="email"
                required
                placeholder="nama@perusahaan.com"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
              <p class="mt-1 text-xs text-slate-400">Invite link akan muncul setelah Employee berhasil dibuat.</p>
            </div>

            <div v-else>
              <label class="mb-1 block text-sm font-medium text-slate-700">Pilih User Account *</label>
              <select v-model="form.user_id" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option :value="null">-</option>
                <option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
              </select>
              <p v-if="availableUsers.length === 0" class="mt-1 text-xs text-amber-600">
                Tidak ada User account yang tersedia (semua sudah terhubung ke Employee lain).
              </p>
            </div>
          </div>

          <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        </form>

        <!-- Footer -->
        <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
          <template v-if="isSuccess">
            <div />
            <button @click="finish" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark">
              Selesai
            </button>
          </template>
          <template v-else-if="!loadingRefs">
            <button
              v-if="currentStep > 1"
              type="button"
              @click="goBack"
              class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            >
              <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
              Kembali
            </button>
            <div v-else />

            <button
              v-if="currentStep < 3"
              type="button"
              @click="goNext"
              :disabled="!canGoNext"
              class="flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              Lanjut
              <ArrowRight class="h-4 w-4" :stroke-width="1.75" />
            </button>
            <button
              v-else
              type="button"
              @click="handleSubmit"
              :disabled="!step3Valid || saving"
              class="rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Buat Employee' }}
            </button>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>
