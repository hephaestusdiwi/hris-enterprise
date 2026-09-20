<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import { Users, CheckCircle2, XCircle, Percent } from 'lucide-vue-next'

interface Summary {
  total_employees: number
  submitted_count: number
  not_submitted_count: number
  pass_count: number
  not_pass_count: number
  compliance_percent: number | null
}

interface SubmissionRow {
  id: number
  overall_result: 'pass' | 'not_pass'
  submitted_at: string
  employee: { first_name: string; last_name: string | null } | null
  branch: { name: string } | null
}

interface AnswerDetail {
  result: 'pass' | 'not_pass'
  note: string | null
  item: { name: string }
}

interface SubmissionDetail extends SubmissionRow {
  photo_url: string | null
  standard: { name: string; version_number: number };
  answers: AnswerDetail[]
}

function employeeName(e: SubmissionRow['employee']): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

// ---- Summary ----
const summaryDate = ref(new Date().toISOString().slice(0, 10))
const summary = ref<Summary | null>(null)
const summaryLoading = ref(true)

async function loadSummary() {
  summaryLoading.value = true
  try {
    const response = await apiClient.get('/api/grooming-self/monitoring-summary', { params: { date: summaryDate.value } })
    summary.value = response.data.data
  } catch {
    summary.value = null
  } finally {
    summaryLoading.value = false
  }
}

// ---- Table ----
const loading = ref(true)
const errorMessage = ref('')
const rows = ref<SubmissionRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const resultFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

async function loadRows(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/grooming-self', {
      params: {
        page,
        result: resultFilter.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
      },
    })
    rows.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadRows(page)
}

// ---- Detail modal ----
const showDetailModal = ref(false)
const detail = ref<SubmissionDetail | null>(null)
const detailLoading = ref(false)

async function openDetail(row: SubmissionRow) {
  showDetailModal.value = true
  detailLoading.value = true
  detail.value = null
  try {
    const response = await apiClient.get(`/api/grooming-self/${row.id}`)
    detail.value = response.data.data
  } catch {
    // biarkan modal nunjukin state kosong, jarang terjadi
  } finally {
    detailLoading.value = false
  }
}

onMounted(() => {
  loadSummary()
  loadRows()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-semibold text-slate-800">Grooming Self Monitoring</h1>
      <p class="text-sm text-slate-400">Ringkasan kepatuhan grooming karyawan.</p>
    </div>

    <!-- Summary -->
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <label class="text-xs font-medium text-slate-500">Tanggal</label>
      <input v-model="summaryDate" type="date" class="rounded-xl border border-slate-200 p-2 text-sm" @change="loadSummary" />
    </div>

    <div v-if="!summaryLoading && summary" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-slate-900">{{ summary.total_employees }}</p>
        <p class="text-xs text-slate-400">Total Employee</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-slate-900">{{ summary.submitted_count }}</p>
        <p class="text-xs text-slate-400">Submitted</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-amber-600">{{ summary.not_submitted_count }}</p>
        <p class="text-xs text-slate-400">Not Submitted</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-emerald-600">{{ summary.pass_count }}</p>
        <p class="text-xs text-slate-400">PASS</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-red-500">{{ summary.not_pass_count }}</p>
        <p class="text-xs text-slate-400">NOT PASS</p>
      </div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4">
        <p class="text-xl font-bold text-slate-900">{{ summary.compliance_percent ?? '-' }}%</p>
        <p class="text-xs text-slate-400">Compliance</p>
      </div>
    </div>

    <!-- Filters + Table -->
    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4">
      <div>
        <label class="text-xs font-medium text-slate-500">Dari</label>
        <input v-model="dateFrom" type="date" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadRows(1)" />
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500">Sampai</label>
        <input v-model="dateTo" type="date" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadRows(1)" />
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500">Result</label>
        <select v-model="resultFilter" class="mt-1 rounded-xl border border-slate-200 p-2 text-sm" @change="loadRows(1)">
          <option value="">Semua</option>
          <option value="pass">PASS</option>
          <option value="not_pass">NOT PASS</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Employee</th>
            <th class="px-4 py-3">Store</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Jam</th>
            <th class="px-4 py-3">Result</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="cursor-pointer border-b border-slate-50 hover:bg-slate-50" @click="openDetail(row)">
            <td class="px-4 py-3 font-medium text-slate-700">{{ employeeName(row.employee) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.branch?.name || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.submitted_at?.slice(0, 10) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.submitted_at?.slice(11, 16) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="row.overall_result === 'pass' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                {{ row.overall_result === 'pass' ? 'PASS' : 'NOT PASS' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
        <span class="text-xs text-slate-400">Halaman {{ currentPage }} dari {{ lastPage }}</span>
        <div class="flex gap-2">
          <button :disabled="currentPage === 1" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage - 1)">Prev</button>
          <button :disabled="currentPage === lastPage" class="rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:opacity-40" @click="goToPage(currentPage + 1)">Next</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showDetailModal" title="Detail Grooming Self" @close="showDetailModal = false">
        <div v-if="detailLoading" class="py-8 text-center text-sm text-slate-400">Memuat detail...</div>
        <div v-else-if="detail" class="space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div><p class="text-xs text-slate-400">Employee</p><p class="font-medium text-slate-700">{{ employeeName(detail.employee) }}</p></div>
            <div><p class="text-xs text-slate-400">Store</p><p class="font-medium text-slate-700">{{ detail.branch?.name || '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Tanggal &amp; Jam</p><p class="font-medium text-slate-700">{{ detail.submitted_at?.slice(0, 16).replace('T', ' ') }}</p></div>
            <div><p class="text-xs text-slate-400">Standard</p><p class="font-medium text-slate-700">{{ detail.standard.name }} (v{{ detail.standard.version_number }})</p></div>
          </div>

          <div v-if="detail.photo_url">
            <p class="mb-1 text-xs text-slate-400">Evidence Photo</p>
            <img :src="detail.photo_url" class="aspect-[4/3] w-full rounded-xl object-cover" alt="Evidence" />
          </div>

          <div>
            <p class="mb-2 text-xs text-slate-400">Checklist</p>
            <div class="space-y-2">
              <div v-for="(a, i) in detail.answers" :key="i" class="rounded-xl border border-slate-100 p-3">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-slate-700">{{ a.item.name }}</p>
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="a.result === 'pass' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                    {{ a.result === 'pass' ? 'PASS' : 'NOT PASS' }}
                  </span>
                </div>
                <p v-if="a.note" class="mt-1 text-xs text-slate-500">Catatan: {{ a.note }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-xl px-1 text-center text-sm font-medium" :class="detail.overall_result === 'pass' ? 'text-emerald-600' : 'text-red-500'">
            Overall: {{ detail.overall_result === 'pass' ? 'PASS' : 'NOT PASS' }}
          </div>
        </div>
      </BaseModal>
    </Teleport>
  </div>
</template>