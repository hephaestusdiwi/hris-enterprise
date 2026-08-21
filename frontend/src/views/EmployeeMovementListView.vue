<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import type { Component } from 'vue'
import { RouterLink } from 'vue-router'
import {
  X,
  ChevronDown,
  Calendar,
  User,
  Eye,
  RotateCcw,
  ArrowLeftRight,
  ArrowRight,
  TrendingUp,
  TrendingDown,
  FileText,
  UserCheck,
  LogOut,
  Clock,
  CheckCircle,
  CheckCircle2,
  XCircle,
  Ban,
  History,
  Inbox,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface MovementRow {
  id: number
  movement_type: string
  effective_date: string
  status: string
  before_snapshot: Record<string, unknown>
  after_snapshot: Record<string, unknown>
  reason: string | null
  applied_at: string | null
  employee: { id: number; first_name: string; last_name: string | null; photo_url: string | null }
  requested_by: { id: number; name: string } | null
}

const MOVEMENT_TYPE_LABELS: Record<string, string> = {
  transfer: 'Transfer',
  promotion: 'Promotion',
  demotion: 'Demotion',
  contract_change: 'Contract Change',
  probation_confirmed: 'Change Status',
  resignation: 'Resignation',
  rehire: 'Rehire',
}

const MOVEMENT_TYPE_ICON: Record<string, Component> = {
  transfer: ArrowLeftRight,
  promotion: TrendingUp,
  demotion: TrendingDown,
  contract_change: FileText,
  probation_confirmed: UserCheck,
  resignation: LogOut,
  rehire: RotateCcw,
}

const MOVEMENT_TYPE_COLOR: Record<string, string> = {
  transfer: 'bg-violet-50 text-violet-600',
  promotion: 'bg-emerald-50 text-emerald-600',
  demotion: 'bg-orange-50 text-orange-600',
  contract_change: 'bg-amber-50 text-amber-600',
  probation_confirmed: 'bg-sky-50 text-sky-600',
  resignation: 'bg-red-50 text-red-600',
  rehire: 'bg-teal-50 text-teal-600',
}

const STATUS_STYLE: Record<string, string> = {
  pending_approval: 'bg-amber-50 text-amber-700',
  approved: 'bg-sky-50 text-sky-700',
  applied: 'bg-emerald-50 text-emerald-700',
  rejected: 'bg-red-50 text-red-700',
  cancelled: 'bg-slate-100 text-slate-500',
}

const STATUS_DOT: Record<string, string> = {
  pending_approval: 'bg-amber-500',
  approved: 'bg-sky-500',
  applied: 'bg-emerald-500',
  rejected: 'bg-red-500',
  cancelled: 'bg-slate-400',
}

const STATUS_ICON: Record<string, Component> = {
  pending_approval: Clock,
  approved: CheckCircle,
  applied: CheckCircle2,
  rejected: XCircle,
  cancelled: Ban,
}

function typeIcon(type: string) {
  return MOVEMENT_TYPE_ICON[type] ?? FileText
}
function typeColor(type: string) {
  return MOVEMENT_TYPE_COLOR[type] ?? 'bg-slate-100 text-slate-500'
}
function statusIcon(status: string) {
  return STATUS_ICON[status] ?? Clock
}
function statusLabel(status: string) {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}
function initials(first: string, last: string | null) {
  return `${first?.[0] ?? ''}${last?.[0] ?? ''}`.toUpperCase()
}

const movements = ref<MovementRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

const filters = ref({
  movement_type: '',
  status: '',
  effective_date_from: '',
  effective_date_to: '',
})

const hasActiveFilters = computed(() => Object.values(filters.value).some((v) => v !== ''))
function resetFilters() {
  filters.value = { movement_type: '', status: '', effective_date_from: '', effective_date_to: '' }
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employee-movements', {
      params: {
        movement_type: filters.value.movement_type || undefined,
        status: filters.value.status || undefined,
        effective_date_from: filters.value.effective_date_from || undefined,
        effective_date_to: filters.value.effective_date_to || undefined,
      },
    })
    movements.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat riwayat Employee Movement.'
  } finally {
    loading.value = false
  }
}

watch(filters, load, { deep: true })
onMounted(load)

// ---- Summary stats ----
const stats = computed(() => {
  const pending = movements.value.filter((m) => m.status === 'pending_approval').length
  const applied = movements.value.filter((m) => m.status === 'applied').length
  const rejected = movements.value.filter((m) => m.status === 'rejected').length
  return { pending, applied, rejected }
})

// ---- Detail modal ----
const detailTarget = ref<MovementRow | null>(null)

const FIELD_LABELS: Record<string, string> = {
  company_id: 'Company',
  branch_id: 'Branch',
  department_id: 'Department',
  position_id: 'Position',
  job_level_id: 'Job Level',
  manager_employee_id: 'Manager',
  employment_type_id: 'Employment Type',
  employment_status_id: 'Employment Status',
  contract_start_date: 'Contract Start Date',
  contract_end_date: 'Contract End Date',
  probation_end_date: 'Probation End Date',
  resign_date: 'Resign Date',
  join_date: 'Join Date',
}

function isChanged(field: string, after: unknown) {
  return detailTarget.value?.before_snapshot[field] !== after
}
</script>

<template>
  <Transition name="page" appear>
    <div class="mx-auto max-w-5xl space-y-5 p-6">
      <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-soft text-primary-dark">
          <History class="h-5 w-5" />
        </span>
        <div>
          <h1 class="text-lg font-semibold text-slate-900">Employee Movement</h1>
          <p class="mt-0.5 text-sm text-slate-500">Riwayat perubahan lifecycle employee (transfer, promosi, contract, status, dst).</p>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Pending Approval</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
              <Clock class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.pending }}</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Applied</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
              <CheckCircle2 class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.applied }}</p>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition-shadow hover:shadow-[0_2px_8px_rgba(15,23,42,0.06)]">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-400">Rejected</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-50 text-red-600">
              <XCircle class="h-4 w-4" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ stats.rejected }}</p>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-100 bg-white p-3 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="relative">
          <select
            v-model="filters.movement_type"
            class="appearance-none rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
          >
            <option value="">Semua Type</option>
            <option v-for="(label, value) in MOVEMENT_TYPE_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
          <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        </div>

        <div class="relative">
          <select
            v-model="filters.status"
            class="appearance-none rounded-xl border border-slate-200 bg-white py-2 pl-3 pr-8 text-sm text-slate-600 transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary-soft"
          >
            <option value="">Semua Status</option>
            <option value="pending_approval">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="applied">Applied</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
        </div>

        <div class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
          <Calendar class="h-3.5 w-3.5 shrink-0 text-slate-300" />
          <input v-model="filters.effective_date_from" type="date" class="text-sm text-slate-600 outline-none" />
          <span class="text-xs text-slate-300">–</span>
          <input v-model="filters.effective_date_to" type="date" class="text-sm text-slate-600 outline-none" />
        </div>

        <button
          v-if="hasActiveFilters"
          type="button"
          @click="resetFilters"
          class="ml-auto flex items-center gap-1 text-xs font-medium text-slate-400 transition-colors hover:text-slate-600"
        >
          <RotateCcw class="h-3 w-3" /> Reset
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-2">
        <div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
      </div>

      <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

      <div
        v-else-if="movements.length === 0"
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-14 text-center"
      >
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
          <Inbox class="h-5 w-5 text-slate-300" />
        </div>
        <p class="text-sm font-medium text-slate-600">Belum ada riwayat</p>
        <p class="mt-1 max-w-sm text-xs text-slate-400">
          Belum ada Employee Movement yang tercatat. Riwayat transfer, promosi, atau perubahan status akan muncul di sini.
        </p>
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50/60 text-xs uppercase tracking-wider text-slate-400">
              <tr>
                <th class="px-5 py-3 font-medium">Employee</th>
                <th class="px-3 py-3 font-medium">Type</th>
                <th class="px-3 py-3 font-medium">Effective Date</th>
                <th class="px-3 py-3 font-medium">Status</th>
                <th class="px-3 py-3 font-medium">Requested By</th>
                <th class="px-3 py-3 text-right font-medium">Detail</th>
              </tr>
            </thead>
            <TransitionGroup tag="tbody" name="row-fade" class="divide-y divide-slate-50">
              <tr
                v-for="(m, i) in movements"
                :key="m.id"
                :style="{ transitionDelay: `${Math.min(i, 8) * 30}ms` }"
                class="transition-colors hover:bg-slate-50/50"
              >
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div
                      class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-xs font-semibold text-primary-dark ring-2 ring-white shadow-sm"
                    >
                      <img
                        v-if="m.employee.photo_url"
                        :src="m.employee.photo_url"
                        :alt="`${m.employee.first_name} ${m.employee.last_name ?? ''}`"
                        class="h-full w-full object-cover"
                      />

                      <span v-else>
                        {{ initials(m.employee.first_name, m.employee.last_name) }}
                      </span>
                    </div>
                    <RouterLink
                      :to="{ name: 'employee-detail', params: { id: m.employee.id } }"
                      class="font-medium text-slate-800 hover:text-primary hover:underline"
                    >
                      {{ m.employee.first_name }} {{ m.employee.last_name }}
                    </RouterLink>
                  </div>
                </td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="typeColor(m.movement_type)">
                    <component :is="typeIcon(m.movement_type)" class="h-3 w-3" />
                    {{ MOVEMENT_TYPE_LABELS[m.movement_type] ?? m.movement_type }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-slate-600">
                  <span class="inline-flex items-center gap-1.5">
                    <Calendar class="h-3.5 w-3.5 text-slate-300" />
                    {{ m.effective_date }}
                  </span>
                </td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_STYLE[m.status] ?? 'bg-slate-100 text-slate-500'">
                    <span class="h-1.5 w-1.5 rounded-full" :class="STATUS_DOT[m.status] ?? 'bg-slate-400'"></span>
                    {{ statusLabel(m.status) }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-slate-500">
                  <span class="inline-flex items-center gap-1.5">
                    <User class="h-3.5 w-3.5 text-slate-300" />
                    {{ m.requested_by?.name ?? '-' }}
                  </span>
                </td>
                <td class="px-3 py-3.5 text-right">
                  <button
                    type="button"
                    title="Lihat Detail"
                    @click="detailTarget = m"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-primary-soft hover:text-primary-dark"
                  >
                    <Eye class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </TransitionGroup>
          </table>
        </div>
      </div>

      <!-- Modal: Detail before/after -->
      <Transition name="overlay">
        <div
          v-if="detailTarget"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="detailTarget = null"
        >
          <Transition name="panel" appear>
            <div v-if="detailTarget" class="max-h-[85vh] w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-100">
              <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl" :class="typeColor(detailTarget.movement_type)">
                    <component :is="typeIcon(detailTarget.movement_type)" class="h-5 w-5" />
                  </span>
                  <div>
                    <h2 class="text-sm font-semibold text-slate-800">
                      {{ MOVEMENT_TYPE_LABELS[detailTarget.movement_type] ?? detailTarget.movement_type }}
                    </h2>
                    <p class="text-xs text-slate-400">{{ detailTarget.employee.first_name }} {{ detailTarget.employee.last_name }}</p>
                  </div>
                </div>
                <button type="button" @click="detailTarget = null" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600">
                  <X class="h-4 w-4" />
                </button>
              </div>

              <div class="max-h-[calc(85vh-140px)] space-y-5 overflow-y-auto px-6 py-5">
                <!-- Meta pills -->
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-600">
                    <Calendar class="h-3 w-3 text-slate-400" /> {{ detailTarget.effective_date }}
                  </span>
                  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_STYLE[detailTarget.status] ?? 'bg-slate-100 text-slate-500'">
                    <component :is="statusIcon(detailTarget.status)" class="h-3 w-3" />
                    {{ statusLabel(detailTarget.status) }}
                  </span>
                  <span v-if="detailTarget.requested_by" class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-600">
                    <User class="h-3 w-3 text-slate-400" /> {{ detailTarget.requested_by.name }}
                  </span>
                  <span v-if="detailTarget.applied_at" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700">
                    <CheckCircle2 class="h-3 w-3" /> Applied {{ detailTarget.applied_at }}
                  </span>
                </div>

                <!-- Diff list -->
                <div>
                  <div class="mb-2 flex items-center justify-between px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    <span>Field</span>
                    <span>Before → After</span>
                  </div>
                  <div class="space-y-1">
                    <div
                      v-for="(after, field) in detailTarget.after_snapshot"
                      :key="field"
                      class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition-colors"
                      :class="isChanged(String(field), after) ? 'bg-primary-soft/40' : ''"
                    >
                      <span class="w-32 shrink-0 text-xs font-medium text-slate-500">{{ FIELD_LABELS[field] ?? field }}</span>
                      <div class="flex flex-1 items-center justify-end gap-2 text-xs">
                        <template v-if="isChanged(String(field), after)">
                          <span class="text-slate-400 line-through decoration-slate-300">{{ detailTarget.before_snapshot[field] ?? '-' }}</span>
                          <ArrowRight class="h-3 w-3 shrink-0 text-primary" />
                          <span class="font-semibold text-primary-dark">{{ after ?? '-' }}</span>
                        </template>
                        <span v-else class="text-slate-500">{{ after ?? '-' }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <p v-if="detailTarget.reason" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                  <span class="font-medium text-slate-700">Reason:</span> {{ detailTarget.reason }}
                </p>
              </div>

              <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                <button type="button" @click="detailTarget = null" class="rounded-xl px-4 py-2 text-sm text-slate-500 transition-colors hover:bg-slate-50">
                  Tutup
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