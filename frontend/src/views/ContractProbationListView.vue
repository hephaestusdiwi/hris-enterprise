<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Settings,
  X,
  Search,
  LayoutGrid,
  FileText,
  GraduationCap,
  AlertTriangle,
  Mail,
  Users,
  CheckCircle2,
  Loader2,
  ChevronRight,
  CalendarClock,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

interface ContractProbationItem {
  type: 'contract' | 'probation'
  end_date: string
  remaining_days: number
  employee: { id: number; employee_number: string; name: string; photo_url: string | null; position: string | null }
  employment_type: string | null
  employment_status: string | null
  manager: { id: number; name: string } | null
}

interface Setting {
  contract_reminder_days: number
  probation_reminder_days: number
  email_reminder_enabled: boolean
  manager_reminder_enabled: boolean
}

const authStore = useAuthStore()
const canManageSettings = authStore.permissions.includes('edit contract probation settings')

const items = ref<ContractProbationItem[]>([])
const loading = ref(true)
const errorMessage = ref('')

const typeFilter = ref<'' | 'contract' | 'probation'>('')
const search = ref('')

const filterOptions = [
  { v: '' as const, label: 'Semua', icon: LayoutGrid },
  { v: 'contract' as const, label: 'Contract', icon: FileText },
  { v: 'probation' as const, label: 'Probation', icon: GraduationCap },
]

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employees/contract-probation', {
      params: {
        type: typeFilter.value || undefined,
        search: search.value || undefined,
      },
    })
    items.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat data Contract & Probation.'
  } finally {
    loading.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 300)
})
watch(typeFilter, load)

onMounted(load)

function remainingLabel(days: number) {
  if (days === 0) return 'Hari ini'
  return `${days} hari`
}

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase()
}

function urgencyClass(days: number) {
  if (days <= 7) return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100'
  if (days <= 14) return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100'
  return 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-100'
}

function urgencyDot(days: number) {
  if (days <= 7) return 'bg-red-500'
  if (days <= 14) return 'bg-amber-500'
  return 'bg-slate-400'
}

// ---- Summary stats ----
const stats = computed(() => {
  const contract = items.value.filter((i) => i.type === 'contract').length
  const probation = items.value.filter((i) => i.type === 'probation').length
  const urgent = items.value.filter((i) => i.remaining_days <= 7).length
  return { contract, probation, urgent }
})

// ---- Settings modal ----
const showSettings = ref(false)
const settingForm = ref<Setting>({
  contract_reminder_days: 30,
  probation_reminder_days: 30,
  email_reminder_enabled: true,
  manager_reminder_enabled: true,
})
const settingSaving = ref(false)
const settingSaved = ref(false)
const settingError = ref('')

async function openSettings() {
  showSettings.value = true
  settingSaved.value = false
  settingError.value = ''
  try {
    const response = await apiClient.get('/api/contract-probation-settings')
    settingForm.value = response.data.data
  } catch {
    settingError.value = 'Gagal memuat setting.'
  }
}

async function saveSettings() {
  settingSaving.value = true
  settingError.value = ''
  try {
    await apiClient.put('/api/contract-probation-settings', settingForm.value)
    settingSaving.value = false
    settingSaved.value = true
    await load() // threshold mungkin berubah, refresh daftar
    setTimeout(() => {
      showSettings.value = false
      settingSaved.value = false
    }, 800)
  } catch {
    settingError.value = 'Gagal menyimpan setting.'
    settingSaving.value = false
  }
}

// ---- Extend Contract action (lewat Employee Movement, bukan jalur update baru) ----
const extendTarget = ref<ContractProbationItem | null>(null)
const extendForm = ref({ new_end_date: '', effective_date: '', reason: '' })
const extendSaving = ref(false)
const extendError = ref('')

function openExtend(item: ContractProbationItem) {
  extendTarget.value = item
  extendForm.value = { new_end_date: '', effective_date: new Date().toISOString().slice(0, 10), reason: '' }
  extendError.value = ''
}

async function submitExtend() {
  if (!extendTarget.value) return
  extendSaving.value = true
  extendError.value = ''
  try {
    await apiClient.post(`/api/employees/${extendTarget.value.employee.id}/movements`, {
      movement_type: 'contract_change',
      effective_date: extendForm.value.effective_date,
      contract_end_date: extendForm.value.new_end_date,
      reason: extendForm.value.reason || 'Extend Contract',
    })
    extendTarget.value = null
    await load()
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    extendError.value = message ?? 'Gagal mengajukan perpanjangan. Pastikan Approval Flow sudah dikonfigurasi.'
  } finally {
    extendSaving.value = false
  }
}
</script>

<template>
  <Transition name="page" appear>
    <div class="mx-auto max-w-6xl space-y-5 p-6">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">Contract & Probation</h1>
          <p class="mt-0.5 text-sm text-slate-500">
            Employee yang contract atau probation-nya mendekati akhir.
          </p>
        </div>
        <button
          v-if="canManageSettings"
          type="button"
          @click="openSettings"
          class="flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50 active:scale-95"
        >
          <Settings class="h-3.5 w-3.5" :stroke-width="2" />
          Setting Reminder
        </button>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Contract Berakhir</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
              <FileText class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.contract }}</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Probation Berakhir</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
              <GraduationCap class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.probation }}</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Urgent (≤ 7 hari)</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-50 text-red-600">
              <AlertTriangle class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.urgent }}</p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="flex rounded-xl border border-slate-200 p-0.5">
          <button
            v-for="opt in filterOptions"
            :key="opt.v"
            type="button"
            @click="typeFilter = opt.v"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-all"
            :class="typeFilter === opt.v ? 'bg-primary-soft text-primary-dark shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          >
            <component :is="opt.icon" class="h-3.5 w-3.5" />
            {{ opt.label }}
          </button>
        </div>

        <div class="relative min-w-[220px] flex-1">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-300" />
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama atau employee number..."
            class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
          />
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-2">
        <div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
      </div>

      <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

      <div
        v-else-if="items.length === 0"
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-14 text-center"
      >
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
          <CalendarClock class="h-5 w-5 text-slate-300" />
        </div>
        <p class="text-sm font-medium text-slate-600">Tidak ada data</p>
        <p class="mt-1 max-w-sm text-xs text-slate-400">
          Tidak ada employee yang masuk threshold Contract/Probation saat ini. Coba sesuaikan filter atau setting reminder di atas.
        </p>
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50/60 text-xs uppercase tracking-wider text-slate-400">
              <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-3 py-3 font-medium">Type</th>
                <th class="px-3 py-3 font-medium">End Date</th>
                <th class="px-3 py-3 font-medium">Remaining</th>
                <th class="px-3 py-3 font-medium">Manager</th>
                <th class="px-3 py-3 font-medium">Status</th>
                <th class="px-3 py-3 font-medium">Action</th>
              </tr>
            </thead>
            <TransitionGroup tag="tbody" name="row-fade" class="divide-y divide-slate-50">
              <tr
                v-for="(item, i) in items"
                :key="`${item.type}-${item.employee.id}-${i}`"
                :style="{ transitionDelay: `${Math.min(i, 8) * 30}ms` }"
                class="transition-colors hover:bg-slate-50/50"
              >
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-xs font-semibold text-primary-dark ring-2 ring-white shadow-sm">
                      <img
                        v-if="item.employee.photo_url"
                        :src="item.employee.photo_url"
                        :alt="item.employee.name"
                        class="h-full w-full object-cover"
                      />
                      <span v-else>{{ initials(item.employee.name) }}</span>
                    </div>
                    <div>
                      <RouterLink
                        :to="{ name: 'employee-detail', params: { id: item.employee.id } }"
                        class="font-medium text-slate-800 hover:text-primary hover:underline"
                      >
                        {{ item.employee.name }}
                      </RouterLink>
                      <p class="text-xs text-slate-400">{{ item.employee.employee_number }}<span v-if="item.employee.position"> · {{ item.employee.position }}</span></p>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3.5">
                  <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="item.type === 'contract' ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700'"
                  >
                    {{ item.type === 'contract' ? 'Contract' : 'Probation' }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-slate-600">{{ item.end_date }}</td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="urgencyClass(item.remaining_days)">
                    <span class="h-1.5 w-1.5 rounded-full" :class="urgencyDot(item.remaining_days)"></span>
                    {{ remainingLabel(item.remaining_days) }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-slate-500">{{ item.manager?.name ?? '-' }}</td>
                <td class="px-3 py-3.5 text-slate-500">{{ item.employment_status ?? '-' }}</td>
                <td class="px-3 py-3.5">
                  <button
                    v-if="item.type === 'contract'"
                    type="button"
                    @click="openExtend(item)"
                    class="group inline-flex items-center gap-1 rounded-lg bg-primary-soft px-2.5 py-1.5 text-xs font-medium text-primary-dark transition-colors hover:bg-primary-soft/70"
                  >
                    Extend
                    <ChevronRight class="h-3 w-3 transition-transform group-hover:translate-x-0.5" />
                  </button>
                  <span v-else class="text-xs text-slate-300">—</span>
                </td>
              </tr>
            </TransitionGroup>
          </table>
        </div>
      </div>

      <!-- Modal: Setting Reminder -->
      <Transition name="overlay">
        <div
          v-if="showSettings"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="showSettings = false"
        >
          <Transition name="panel" appear>
            <div class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-100">
              <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-soft text-primary-dark">
                    <Settings class="h-5 w-5" />
                  </span>
                  <div>
                    <h2 class="text-sm font-semibold text-slate-800">Setting Reminder</h2>
                    <p class="text-xs text-slate-400">Contract & Probation notification</p>
                  </div>
                </div>
                <button
                  type="button"
                  @click="showSettings = false"
                  class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>

              <div class="max-h-[65vh] space-y-6 overflow-y-auto px-6 py-5">
                <!-- Contract threshold -->
                <div>
                  <div class="mb-2 flex items-center gap-2">
                    <FileText class="h-4 w-4 text-amber-500" />
                    <label class="text-xs font-semibold text-slate-700">Reminder Contract</label>
                  </div>
                  <p class="mb-3 text-xs text-slate-400">Kirim reminder berapa hari sebelum contract berakhir.</p>
                  <div class="flex items-center gap-3">
                    <input
                      v-model.number="settingForm.contract_reminder_days"
                      type="range"
                      min="1"
                      max="90"
                      class="h-1.5 flex-1 cursor-pointer accent-amber-500"
                    />
                    <span class="w-16 shrink-0 rounded-lg bg-amber-50 px-2 py-1.5 text-center text-xs font-semibold text-amber-700">
                      {{ settingForm.contract_reminder_days }}h
                    </span>
                  </div>
                </div>

                <!-- Probation threshold -->
                <div>
                  <div class="mb-2 flex items-center gap-2">
                    <GraduationCap class="h-4 w-4 text-sky-500" />
                    <label class="text-xs font-semibold text-slate-700">Reminder Probation</label>
                  </div>
                  <p class="mb-3 text-xs text-slate-400">Kirim reminder berapa hari sebelum probation berakhir.</p>
                  <div class="flex items-center gap-3">
                    <input
                      v-model.number="settingForm.probation_reminder_days"
                      type="range"
                      min="1"
                      max="90"
                      class="h-1.5 flex-1 cursor-pointer accent-sky-500"
                    />
                    <span class="w-16 shrink-0 rounded-lg bg-sky-50 px-2 py-1.5 text-center text-xs font-semibold text-sky-700">
                      {{ settingForm.probation_reminder_days }}h
                    </span>
                  </div>
                </div>

                <div class="h-px bg-slate-100"></div>

                <!-- Notification channels -->
                <div>
                  <p class="mb-3 text-xs font-semibold text-slate-700">Notification Channel</p>
                  <div class="space-y-1">
                    <div class="flex items-center justify-between rounded-xl px-3 py-2.5 transition-colors hover:bg-slate-50">
                      <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                          <Mail class="h-4 w-4" />
                        </span>
                        <div>
                          <p class="text-xs font-medium text-slate-700">Email Reminder</p>
                          <p class="text-[11px] text-slate-400">Kirim notifikasi ke email employee</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        role="switch"
                        :aria-checked="settingForm.email_reminder_enabled"
                        @click="settingForm.email_reminder_enabled = !settingForm.email_reminder_enabled"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200"
                        :class="settingForm.email_reminder_enabled ? 'bg-primary' : 'bg-slate-200'"
                      >
                        <span
                          class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"
                          :class="settingForm.email_reminder_enabled ? 'translate-x-6' : 'translate-x-1'"
                        ></span>
                      </button>
                    </div>

                    <div class="flex items-center justify-between rounded-xl px-3 py-2.5 transition-colors hover:bg-slate-50">
                      <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                          <Users class="h-4 w-4" />
                        </span>
                        <div>
                          <p class="text-xs font-medium text-slate-700">Manager / Approval Line</p>
                          <p class="text-[11px] text-slate-400">Kirim notifikasi ke manager terkait</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        role="switch"
                        :aria-checked="settingForm.manager_reminder_enabled"
                        @click="settingForm.manager_reminder_enabled = !settingForm.manager_reminder_enabled"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200"
                        :class="settingForm.manager_reminder_enabled ? 'bg-primary' : 'bg-slate-200'"
                      >
                        <span
                          class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"
                          :class="settingForm.manager_reminder_enabled ? 'translate-x-6' : 'translate-x-1'"
                        ></span>
                      </button>
                    </div>
                  </div>
                </div>

                <p v-if="settingError" class="flex items-center gap-1.5 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-600">
                  <AlertTriangle class="h-3.5 w-3.5 shrink-0" /> {{ settingError }}
                </p>
              </div>

              <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button
                  type="button"
                  @click="showSettings = false"
                  class="rounded-xl px-4 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="button"
                  :disabled="settingSaving"
                  @click="saveSettings"
                  class="flex min-w-[110px] items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-medium text-white transition-all disabled:opacity-70"
                  :class="settingSaved ? 'bg-emerald-500' : 'bg-primary hover:bg-primary-dark'"
                >
                  <Loader2 v-if="settingSaving" class="h-3.5 w-3.5 animate-spin" />
                  <CheckCircle2 v-else-if="settingSaved" class="h-3.5 w-3.5" />
                  {{ settingSaving ? 'Menyimpan...' : settingSaved ? 'Tersimpan' : 'Simpan' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>

      <!-- Modal: Extend Contract (submit via Employee Movement, bukan update Employee langsung) -->
      <Transition name="overlay">
        <div
          v-if="extendTarget"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="extendTarget = null"
        >
          <Transition name="panel" appear>
            <div v-if="extendTarget" class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-100">
              <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                    <FileText class="h-5 w-5" />
                  </span>
                  <div>
                    <h2 class="text-sm font-semibold text-slate-800">Extend Contract</h2>
                    <p class="text-xs text-slate-400">{{ extendTarget.employee.name }}</p>
                  </div>
                </div>
                <button type="button" @click="extendTarget = null" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
                  <X class="h-4 w-4" />
                </button>
              </div>

              <div class="space-y-4 px-6 py-5">
                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5 text-xs">
                  <span class="text-slate-400">Current End Date</span>
                  <span class="font-medium text-slate-700">{{ extendTarget.end_date }}</span>
                </div>

                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">New End Date</label>
                  <input
                    v-model="extendForm.new_end_date"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">Effective Date</label>
                  <input
                    v-model="extendForm.effective_date"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium text-slate-600">Reason</label>
                  <textarea
                    v-model="extendForm.reason"
                    rows="2"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
                    placeholder="Contract extension"
                  ></textarea>
                </div>

                <p class="text-xs text-slate-400">
                  Perubahan ini akan diajukan lewat Employee Movement dan mengikuti Approval Flow yang berlaku — bukan langsung mengubah data employee.
                </p>
                <p v-if="extendError" class="flex items-center gap-1.5 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-600">
                  <AlertTriangle class="h-3.5 w-3.5 shrink-0" /> {{ extendError }}
                </p>
              </div>

              <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button type="button" @click="extendTarget = null" class="rounded-xl px-4 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50">
                  Batal
                </button>
                <button
                  type="button"
                  :disabled="extendSaving || !extendForm.new_end_date"
                  @click="submitExtend"
                  class="flex min-w-[100px] items-center justify-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
                >
                  <Loader2 v-if="extendSaving" class="h-3.5 w-3.5 animate-spin" />
                  {{ extendSaving ? 'Mengajukan...' : 'Submit' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
.page-enter-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.2s ease;
}
.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

.panel-enter-active {
  transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.panel-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.panel-enter-from,
.panel-leave-to {
  opacity: 0;
  transform: translateY(16px) scale(0.96);
}

.row-fade-enter-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.row-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.row-fade-move {
  transition: transform 0.3s ease;
}
</style>