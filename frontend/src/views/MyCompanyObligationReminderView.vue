<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Bell, BellRing, ScrollText, CheckCheck } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

type ObligationStatus = 'active' | 'completed' | 'cancelled'

interface Company { id: number; name: string }
interface Employee { id: number; first_name: string; last_name: string | null }
interface MyObligationRow {
  id: number
  company: Company
  type: string
  title: string
  pic: Employee | null
  due_date: string
  remaining_days: number
  status: ObligationStatus
}
interface ReminderNotification {
  id: string
  read_at: string | null
  created_at: string
  data: {
    company_obligation_id: number
    title: string
    type_label: string
    due_date: string
    remaining_days: number
    milestone: number
  }
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatDateTime(value: string) {
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function employeeName(e: Employee | null) {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
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

// ---------- Reminders ----------
const reminders = ref<ReminderNotification[]>([])
const unreadCount = ref(0)
const remindersLoading = ref(true)
const remindersError = ref('')

async function loadReminders() {
  remindersLoading.value = true
  remindersError.value = ''
  try {
    const response = await apiClient.get('/api/my-company-obligation-reminders')
    reminders.value = response.data.data.data
    unreadCount.value = response.data.meta.unread_count
  } catch {
    remindersError.value = 'Gagal memuat reminder.'
  } finally {
    remindersLoading.value = false
  }
}

async function markAsRead(reminder: ReminderNotification) {
  if (reminder.read_at) return
  try {
    await apiClient.post(`/api/company-obligation-reminders/${reminder.id}/read`)
    reminder.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch {
    // biarkan reminder tetap unread kalau gagal, user bisa coba lagi
  }
}

// ---------- Obligation saya (PIC / recipient) ----------
const myObligations = ref<MyObligationRow[]>([])
const obligationsLoading = ref(true)
const obligationsError = ref('')

async function loadMyObligations() {
  obligationsLoading.value = true
  obligationsError.value = ''
  try {
    const response = await apiClient.get('/api/my-company-obligations')
    myObligations.value = response.data.data
  } catch {
    obligationsError.value = 'Gagal memuat Company Obligations kamu.'
  } finally {
    obligationsLoading.value = false
  }
}

onMounted(() => {
  loadReminders()
  loadMyObligations()
})
</script>

<template>
  <div class="space-y-8">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reminder Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Reminder Company Obligations yang ditujukan untuk kamu (sebagai PIC, recipient, atau role terkait).</p>
    </div>

    <section class="space-y-3">
      <div class="flex items-center gap-2">
        <BellRing class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
        <h2 class="text-sm font-semibold text-slate-700">Notifikasi</h2>
        <span v-if="unreadCount > 0" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ unreadCount }} belum dibaca</span>
      </div>

      <div v-if="remindersLoading" class="text-sm text-slate-400">Memuat reminder...</div>
      <div v-else-if="remindersError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ remindersError }}</div>
      <div v-else-if="reminders.length === 0" class="flex items-center gap-2 rounded-xl border border-slate-100 bg-white p-4 text-sm text-slate-400">
        <Bell class="h-4 w-4 shrink-0" :stroke-width="1.75" /> Belum ada reminder buat kamu.
      </div>
      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div
          v-for="reminder in reminders"
          :key="reminder.id"
          @click="markAsRead(reminder)"
          class="flex cursor-pointer items-start justify-between gap-3 border-b border-slate-50 px-5 py-3.5 last:border-0 hover:bg-slate-50/50"
          :class="{ 'bg-primary-soft/40': !reminder.read_at }"
        >
          <div class="min-w-0">
            <p class="font-medium text-slate-800">
              <span v-if="!reminder.read_at" class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary align-middle"></span>
              {{ reminder.data.title }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ reminder.data.type_label }} · Jatuh tempo {{ formatDate(reminder.data.due_date) }}
              <span class="rounded-full px-1.5 py-0.5 font-medium" :class="remainingBadgeClass(reminder.data.remaining_days)">{{ remainingLabel(reminder.data.remaining_days) }}</span>
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-xs text-slate-400">{{ formatDateTime(reminder.created_at) }}</p>
            <p v-if="reminder.read_at" class="mt-1 flex items-center justify-end gap-1 text-xs text-emerald-500"><CheckCheck class="h-3 w-3" :stroke-width="1.75" /> Dibaca</p>
          </div>
        </div>
      </div>
    </section>

    <section class="space-y-3">
      <div class="flex items-center gap-2">
        <ScrollText class="h-4 w-4 text-slate-400" :stroke-width="1.75" />
        <h2 class="text-sm font-semibold text-slate-700">Obligation Saya</h2>
      </div>

      <div v-if="obligationsLoading" class="text-sm text-slate-400">Memuat data...</div>
      <div v-else-if="obligationsError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ obligationsError }}</div>
      <div v-else-if="myObligations.length === 0" class="rounded-xl border border-slate-100 bg-white p-4 text-sm text-slate-400">
        Kamu tidak jadi PIC atau recipient obligation manapun saat ini.
      </div>
      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 font-medium text-slate-500">Obligation</th>
              <th class="px-5 py-3 font-medium text-slate-500">Company</th>
              <th class="px-5 py-3 font-medium text-slate-500">PIC</th>
              <th class="px-5 py-3 font-medium text-slate-500">Jatuh Tempo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in myObligations" :key="row.id" class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
              <td class="px-5 py-3.5 font-medium text-slate-800">{{ row.title }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ row.company.name }}</td>
              <td class="px-5 py-3.5 text-slate-500">{{ employeeName(row.pic) }}</td>
              <td class="px-5 py-3.5">
                <p class="text-slate-600">{{ formatDate(row.due_date) }}</p>
                <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="remainingBadgeClass(row.remaining_days)">
                  {{ remainingLabel(row.remaining_days) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>