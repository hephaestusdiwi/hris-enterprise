<script setup lang="ts">
import {
  ref,
  onMounted,
  computed,
  watch,
} from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Send,
  RotateCcw,
  Play,
  Lock,
  Eye,
  EyeOff,
  Ban,
  X,
  ChevronRight,
  ChevronDown,
  Check,
  CheckCircle2,
  Clock3,
  AlertCircle,
  XCircle,
  Users,
  WalletCards,
  Calculator,
  FileCheck2,
  ShieldCheck,
  History,
  Building2,
  CalendarDays,
  Download,
  Upload,
  Landmark,
  RefreshCw,
  Info,
  CircleDollarSign,
  FileText,
  MoreHorizontal,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'

/* ==========================================================================
 * TYPES
 * ========================================================================== */

type RunStatus =
  | 'draft'
  | 'pending_approval'
  | 'approved'
  | 'processed'
  | 'locked'
  | 'cancelled'

interface Employee {
  id: number
  first_name: string
  last_name: string | null
  bank_name?: string | null
  bank_account_number?: string | null
  bank_account_holder_name?: string | null
}

interface PayslipLine {
  id: number
  type: string
  source: string
  label: string
  amount: string
}

interface Payslip {
  id: number
  employee: Employee
  gross_earning: string
  structural_deduction: string
  manual_deduction_total: string
  bpjs_employee_total: string
  bpjs_employer_total: string
  tax_amount: string
  loan_deduction_total: string
  net_pay: string
  is_published: boolean
  lines: PayslipLine[]
}

interface StepDecision {
  id: number
  sequence: number
  status: string
  approval_step: {
    name: string | null
    sequence: number
  }
}

interface RevisionEntry {
  id: number
  revision_number: number
  calculated_at: string
  note: string | null
  payslips: Payslip[]
}

interface ApprovalRequestEntry {
  id: number
  status: 'pending' | 'approved' | 'rejected'
  requested_at: string
  decided_at: string | null
  step_decisions: StepDecision[]
}

type DisbursementStatus =
  | 'generated'
  | 'sent'
  | 'confirmed'
  | 'failed'

interface DisbursementBatch {
  id: number
  status: DisbursementStatus
  total_amount: string
  total_employee_count: number
  generated_at: string
  sent_at: string | null
  decided_at: string | null
  failure_reason: string | null
  revision: {
    revision_number: number
  }
}

interface RunDetail {
  id: number
  period_year: number
  period_month: number
  status: RunStatus
  current_revision: number
  published_at: string | null
  participants: Employee[]

  current_revision_data?: {
    revision_number: number
    payslips: Payslip[]
  } | null

  revisions?: RevisionEntry[]

  approval_request?: {
    step_decisions: StepDecision[]
  } | null

  approval_requests?: ApprovalRequestEntry[]
}

/* ==========================================================================
 * CONSTANTS
 * ========================================================================== */

const PRIMARY = '#BD2028'
const PRIMARY_DARK = '#9F1B22'
const PRIMARY_SOFT = '#FCEBED'

const monthNames = [
  'Januari',
  'Februari',
  'Maret',
  'April',
  'Mei',
  'Juni',
  'Juli',
  'Agustus',
  'September',
  'Oktober',
  'November',
  'Desember',
]

const statusLabels: Record<RunStatus, string> = {
  draft: 'Draft',
  pending_approval: 'Menunggu Approval',
  approved: 'Approved',
  processed: 'Processed',
  locked: 'Locked',
  cancelled: 'Dibatalkan',
}

const statusBadgeClass: Record<RunStatus, string> = {
  draft: 'bg-slate-100 text-slate-600',
  pending_approval: 'bg-amber-50 text-amber-700',
  approved: 'bg-blue-50 text-blue-700',
  processed: 'bg-[#FCEBED] text-[#BD2028]',
  locked: 'bg-emerald-50 text-emerald-700',
  cancelled: 'bg-red-50 text-red-700',
}

const statusDotClass: Record<RunStatus, string> = {
  draft: 'bg-slate-400',
  pending_approval: 'bg-amber-500',
  approved: 'bg-blue-500',
  processed: 'bg-[#BD2028]',
  locked: 'bg-emerald-500',
  cancelled: 'bg-red-500',
}

const lineTypeLabels: Record<string, string> = {
  earning: 'Penambah',
  deduction: 'Potongan',
  bpjs_employee: 'BPJS Karyawan',
  bpjs_employer: 'BPJS Company',
  tax: 'PPh 21',
  loan_installment: 'Cicilan Loan',
}

const approvalRequestStatusLabels: Record<string, string> = {
  pending: 'Menunggu',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const disbursementStatusLabels: Record<
  DisbursementStatus,
  string
> = {
  generated: 'Digenerate',
  sent: 'Sudah Dikirim ke Bank',
  confirmed: 'Terkonfirmasi',
  failed: 'Gagal',
}

const disbursementStatusBadgeClass: Record<
  DisbursementStatus,
  string
> = {
  generated: 'bg-slate-100 text-slate-600',
  sent: 'bg-amber-50 text-amber-700',
  confirmed: 'bg-emerald-50 text-emerald-700',
  failed: 'bg-red-50 text-red-700',
}

/* ==========================================================================
 * HELPERS
 * ========================================================================== */

function employeeName(e: {
  first_name: string
  last_name: string | null
}) {
  return [e.first_name, e.last_name]
    .filter(Boolean)
    .join(' ')
}

function formatCurrency(value: string | number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value))
}

function formatDateTime(value: string) {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatDate(value: string | null) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function revisionNetTotal(revision: RevisionEntry) {
  return revision.payslips.reduce(
    (sum, p) => sum + Number(p.net_pay),
    0,
  )
}

function statusLabel(status: RunStatus) {
  return statusLabels[status]
}

function statusBadge(status: RunStatus) {
  return statusBadgeClass[status]
}

function statusDot(status: RunStatus) {
  return statusDotClass[status]
}

/* ==========================================================================
 * ROUTER / PAGE STATE
 * ========================================================================== */

const route = useRoute()
const router = useRouter()
const runId = route.params.id as string

const run = ref<RunDetail | null>(null)

const loading = ref(true)
const actionError = ref('')
const actionProcessing = ref(false)

const activeTab = ref<
  'overview' | 'history' | 'disbursement'
>('overview')

const viewMode = ref<'overview' | 'detail'>(
  'overview',
)

const selectedPayslip = ref<Payslip | null>(
  null,
)

const expandedRevisionId = ref<number | null>(
  null,
)

function toggleRevisionExpand(id: number) {
  expandedRevisionId.value =
    expandedRevisionId.value === id
      ? null
      : id
}

/* ==========================================================================
 * COMPUTED
 * ========================================================================== */

const payslips = computed(
  () =>
    run.value?.current_revision_data
      ?.payslips ?? [],
)

const totalNetPay = computed(() =>
  payslips.value.reduce(
    (sum, payslip) =>
      sum + Number(payslip.net_pay),
    0,
  ),
)

const totalGross = computed(() =>
  payslips.value.reduce(
    (sum, payslip) =>
      sum +
      Number(payslip.gross_earning),
    0,
  ),
)

const totalBpjsEmployee = computed(() =>
  payslips.value.reduce(
    (sum, payslip) =>
      sum +
      Number(payslip.bpjs_employee_total),
    0,
  ),
)

const totalTax = computed(() =>
  payslips.value.reduce(
    (sum, payslip) =>
      sum + Number(payslip.tax_amount),
    0,
  ),
)

const totalLoan = computed(() =>
  payslips.value.reduce(
    (sum, payslip) =>
      sum +
      Number(payslip.loan_deduction_total),
    0,
  ),
)

const totalPublishedPayslip = computed(
  () =>
    payslips.value.filter(
      (payslip) =>
        payslip.is_published,
    ).length,
)

const employeesMissingBankData = computed(
  () =>
    (run.value?.participants ?? []).filter(
      (employee) =>
        !employee.bank_account_number ||
        !employee.bank_name ||
        !employee.bank_account_holder_name,
    ),
)

const revisionHistory = computed(() =>
  [...(run.value?.revisions ?? [])].sort(
    (a, b) =>
      b.revision_number -
      a.revision_number,
  ),
)

const approvalHistory = computed(() =>
  [...(run.value?.approval_requests ?? [])].sort(
    (a, b) => b.id - a.id,
  ),
)

const approvalProgress = computed(() => {
  const decisions =
    run.value?.approval_request
      ?.step_decisions ?? []

  if (decisions.length === 0) {
    return {
      total: 0,
      completed: 0,
      percentage: 0,
    }
  }

  const completed =
    decisions.filter(
      (decision) =>
        decision.status === 'approved',
    ).length

  return {
    total: decisions.length,
    completed,
    percentage: Math.round(
      (completed / decisions.length) * 100,
    ),
  }
})

const periodLabel = computed(() => {
  if (!run.value) return ''

  return `${monthNames[run.value.period_month - 1]} ${run.value.period_year}`
})

const payrollStatusDescription = computed(() => {
  if (!run.value) return ''

  const descriptions: Record<
    RunStatus,
    string
  > = {
    draft:
      'Payroll masih dalam tahap persiapan dan belum diproses.',
    pending_approval:
      'Payroll sudah dihitung dan sedang menunggu approval.',
    approved:
      'Payroll sudah disetujui dan siap dikunci.',
    processed:
      'Payroll sudah selesai dihitung dan menunggu approval.',
    locked:
      'Payroll sudah dikunci dan siap untuk proses publish atau disbursement.',
    cancelled:
      'Payroll run ini telah dibatalkan.',
  }

  return descriptions[run.value.status]
})

/* ==========================================================================
 * LOAD RUN
 * ========================================================================== */

async function loadRun() {
  loading.value = true
  actionError.value = ''

  try {
    const response =
      await apiClient.get(
        `/api/payroll-runs/${runId}`,
      )

    run.value = response.data.data
  } catch {
    actionError.value =
      'Gagal memuat payroll run.'
  } finally {
    loading.value = false
  }
}

/* ==========================================================================
 * DETAIL VIEW
 * ========================================================================== */

function openDetail(payslip: Payslip) {
  selectedPayslip.value = payslip
  viewMode.value = 'detail'
  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}

function backToOverview() {
  viewMode.value = 'overview'
  selectedPayslip.value = null
}

/* ==========================================================================
 * ACTIONS
 * ========================================================================== */

async function proceedPayslip() {
  if (
    !confirm(
      'Generate payslip untuk semua peserta? Ini akan membuat revisi baru.',
    )
  ) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/proceed-payslip`,
    )

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal generate payslip.'
  } finally {
    actionProcessing.value = false
  }
}

async function requestApproval() {
  if (
    !confirm(
      'Ajukan payroll run ini untuk approval Lock?',
    )
  ) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/request-approval`,
    )

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal mengajukan approval.'
  } finally {
    actionProcessing.value = false
  }
}

/* ==========================================================================
 * RECALCULATE
 * ========================================================================== */

const showRecalcModal = ref(false)
const recalcReason = ref('')

function openRecalcModal() {
  recalcReason.value = ''
  showRecalcModal.value = true
}

function closeRecalcModal() {
  if (actionProcessing.value) return

  showRecalcModal.value = false
}

async function submitRecalculate() {
  if (!recalcReason.value.trim()) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/proceed-payslip`,
      {
        note: recalcReason.value,
      },
    )

    showRecalcModal.value = false
    recalcReason.value = ''

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal recalculate.'
  } finally {
    actionProcessing.value = false
  }
}

/* ==========================================================================
 * LOCK
 * ========================================================================== */

async function lockRun() {
  if (
    !confirm(
      'Lock payroll run ini? Setelah Lock, data TIDAK BISA diubah lagi melalui flow normal.',
    )
  ) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/lock`,
    )

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal lock payroll.'
  } finally {
    actionProcessing.value = false
  }
}

/* ==========================================================================
 * PUBLISH
 * ========================================================================== */

async function publishRun() {
  if (
    !confirm(
      'Publish payslip untuk payroll run ini?',
    )
  ) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/publish`,
    )

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal publish.'
  } finally {
    actionProcessing.value = false
  }
}

async function unpublishRun() {
  if (
    !confirm(
      'Unpublish payslip pada payroll run ini?',
    )
  ) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/unpublish`,
    )

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal unpublish.'
  } finally {
    actionProcessing.value = false
  }
}

/* ==========================================================================
 * CANCEL
 * ========================================================================== */

const showCancelModal = ref(false)
const cancelReason = ref('')

function openCancelModal() {
  cancelReason.value = ''
  showCancelModal.value = true
}

function closeCancelModal() {
  if (actionProcessing.value) return

  showCancelModal.value = false
}

async function submitCancel() {
  if (!cancelReason.value.trim()) {
    return
  }

  actionProcessing.value = true
  actionError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/cancel`,
      {
        reason: cancelReason.value,
      },
    )

    showCancelModal.value = false
    cancelReason.value = ''

    await loadRun()
  } catch (err: any) {
    actionError.value =
      err.response?.data?.message ||
      'Gagal membatalkan payroll.'
  } finally {
    actionProcessing.value = false
  }
}

/* ==========================================================================
 * DISBURSEMENT
 * ========================================================================== */

const disbursementBatches =
  ref<DisbursementBatch[]>([])

const disbursementLoading = ref(false)
const disbursementError = ref('')
const disbursementProcessing =
  ref(false)

const showFailForm =
  ref<number | null>(null)

const failReason = ref('')

async function loadDisbursements() {
  disbursementLoading.value = true
  disbursementError.value = ''

  try {
    const response =
      await apiClient.get(
        `/api/payroll-runs/${runId}/disbursements`,
      )

    disbursementBatches.value =
      response.data.data
  } catch (err: any) {
    disbursementError.value =
      err.response?.data?.message ||
      'Gagal memuat disbursement.'
  } finally {
    disbursementLoading.value = false
  }
}

async function generateDisbursement() {
  if (
    !confirm(
      'Generate file disbursement dari revisi aktif saat ini?',
    )
  ) {
    return
  }

  disbursementProcessing.value = true
  disbursementError.value = ''

  try {
    await apiClient.post(
      `/api/payroll-runs/${runId}/disbursements`,
    )

    await loadDisbursements()
  } catch (err: any) {
    disbursementError.value =
      err.response?.data?.message ||
      'Gagal generate disbursement.'
  } finally {
    disbursementProcessing.value =
      false
  }
}

function downloadDisbursement(
  batchId: number,
) {
  window.open(
    `/api/disbursements/${batchId}/download`,
    '_blank',
  )
}

async function markSent(
  batchId: number,
) {
  disbursementProcessing.value = true
  disbursementError.value = ''

  try {
    await apiClient.post(
      `/api/disbursements/${batchId}/mark-sent`,
    )

    await loadDisbursements()
  } catch (err: any) {
    disbursementError.value =
      err.response?.data?.message ||
      'Gagal update status.'
  } finally {
    disbursementProcessing.value =
      false
  }
}

async function markConfirmed(
  batchId: number,
) {
  disbursementProcessing.value = true
  disbursementError.value = ''

  try {
    await apiClient.post(
      `/api/disbursements/${batchId}/mark-confirmed`,
    )

    await loadDisbursements()
  } catch (err: any) {
    disbursementError.value =
      err.response?.data?.message ||
      'Gagal update status.'
  } finally {
    disbursementProcessing.value =
      false
  }
}

async function submitMarkFailed(
  batchId: number,
) {
  if (!failReason.value.trim()) {
    return
  }

  disbursementProcessing.value = true
  disbursementError.value = ''

  try {
    await apiClient.post(
      `/api/disbursements/${batchId}/mark-failed`,
      {
        reason: failReason.value,
      },
    )

    showFailForm.value = null
    failReason.value = ''

    await loadDisbursements()
  } catch (err: any) {
    disbursementError.value =
      err.response?.data?.message ||
      'Gagal update status.'
  } finally {
    disbursementProcessing.value =
      false
  }
}

watch(activeTab, (tab) => {
  if (
    tab === 'disbursement' &&
    disbursementBatches.value
      .length === 0
  ) {
    loadDisbursements()
  }
})

/* ==========================================================================
 * LIFECYCLE
 * ========================================================================== */

onMounted(loadRun)
</script>

<template>
  <div class="min-h-full pb-10">
    <!-- ================================================================== -->
    <!-- LOADING                                                            -->
    <!-- ================================================================== -->

    <div
      v-if="loading"
      class="space-y-6"
    >
      <div class="h-5 w-48 animate-pulse rounded bg-slate-100" />

      <div
        class="rounded-2xl border border-slate-200 bg-white p-6"
      >
        <div class="space-y-3">
          <div class="h-7 w-56 animate-pulse rounded bg-slate-100" />
          <div class="h-4 w-80 animate-pulse rounded bg-slate-100" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="i in 4"
          :key="i"
          class="h-32 animate-pulse rounded-2xl bg-slate-100"
        />
      </div>

      <div class="h-80 animate-pulse rounded-2xl bg-slate-100" />
    </div>

    <!-- ================================================================== -->
    <!-- ERROR                                                              -->
    <!-- ================================================================== -->

    <div
      v-else-if="!run"
      class="mx-auto max-w-xl py-20 text-center"
    >
      <div
        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600"
      >
        <AlertCircle class="h-6 w-6" />
      </div>

      <h2 class="mt-4 text-lg font-semibold text-slate-800">
        Payroll tidak ditemukan
      </h2>

      <p class="mt-1 text-sm text-slate-500">
        {{
          actionError ||
          'Data payroll run tidak dapat dimuat.'
        }}
      </p>

      <button
        type="button"
        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#9F1B22]"
        @click="router.push('/payroll')"
      >
        <ArrowLeft class="h-4 w-4" />
        Kembali ke Payroll History
      </button>
    </div>

    <!-- ================================================================== -->
    <!-- PAGE                                                               -->
    <!-- ================================================================== -->

    <template v-else>
      <!-- ================================================================== -->
      <!-- BREADCRUMB                                                         -->
      <!-- ================================================================== -->

      <div
        class="mb-5 flex items-center gap-1.5 text-xs font-medium text-slate-400"
      >
        <button
          type="button"
          class="transition hover:text-slate-600"
          @click="router.push('/payroll')"
        >
          Payroll
        </button>

        <ChevronRight class="h-3.5 w-3.5" />

        <span>
          Payroll History
        </span>

        <ChevronRight class="h-3.5 w-3.5" />

        <span class="text-slate-600">
          {{ periodLabel }}
        </span>
      </div>

      <!-- ================================================================== -->
      <!-- HEADER                                                             -->
      <!-- ================================================================== -->

      <section
        class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <div
          class="flex flex-col gap-5 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-start lg:justify-between lg:px-6"
        >
          <div class="flex items-start gap-4">
            <button
              type="button"
              class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
              title="Kembali"
              @click="router.push('/payroll')"
            >
              <ArrowLeft class="h-4 w-4" />
            </button>

            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h1
                  class="text-2xl font-semibold tracking-tight text-slate-900"
                >
                  {{ periodLabel }}
                </h1>

                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                  :class="statusBadge(run.status)"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="statusDot(run.status)"
                  />

                  {{ statusLabel(run.status) }}
                </span>
              </div>

              <div
                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-400"
              >
                <span>
                  Payroll Run #{{ run.id }}
                </span>

                <span
                  v-if="run.current_revision"
                  class="inline-flex items-center gap-1"
                >
                  <History class="h-3.5 w-3.5" />
                  Revisi ke-{{
                    run.current_revision
                  }}
                </span>

                <span
                  v-if="run.published_at"
                  class="inline-flex items-center gap-1 text-emerald-600"
                >
                  <CheckCircle2 class="h-3.5 w-3.5" />
                  Published
                  {{ formatDate(run.published_at) }}
                </span>
              </div>

              <p
                class="mt-3 max-w-2xl text-sm leading-6 text-slate-500"
              >
                {{ payrollStatusDescription }}
              </p>
            </div>
          </div>

          <!-- Header quick actions -->
          <div
            class="flex flex-wrap items-center gap-2 lg:max-w-md lg:justify-end"
          >
            <button
              v-if="run.status === 'draft'"
              type="button"
              :disabled="actionProcessing"
              class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9F1B22] disabled:cursor-not-allowed disabled:opacity-50"
              @click="proceedPayslip"
            >
              <Play class="h-4 w-4" />
              Proses Payroll
            </button>

            <button
              v-if="run.status === 'processed'"
              type="button"
              :disabled="actionProcessing"
              class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#9F1B22] disabled:cursor-not-allowed disabled:opacity-50"
              @click="requestApproval"
            >
              <Send class="h-4 w-4" />
              Request Approval
            </button>

            <button
              v-if="
                ['processed', 'pending_approval', 'approved']
                  .includes(run.status)
              "
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
              @click="openRecalcModal"
            >
              <RotateCcw class="h-4 w-4" />
              Recalculate
            </button>

            <button
              v-if="run.status === 'approved'"
              type="button"
              :disabled="actionProcessing"
              class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
              @click="lockRun"
            >
              <Lock class="h-4 w-4" />
              Lock Payroll
            </button>

            <button
              v-if="
                run.status === 'locked' &&
                !run.published_at
              "
              type="button"
              :disabled="actionProcessing"
              class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9F1B22] disabled:cursor-not-allowed disabled:opacity-50"
              @click="publishRun"
            >
              <Eye class="h-4 w-4" />
              Publish Payslip
            </button>

            <button
              v-if="
                run.status === 'locked' &&
                run.published_at
              "
              type="button"
              :disabled="actionProcessing"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
              @click="unpublishRun"
            >
              <EyeOff class="h-4 w-4" />
              Unpublish
            </button>

            <button
              v-if="
                !['locked', 'cancelled'].includes(
                  run.status,
                )
              "
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50"
              @click="openCancelModal"
            >
              <Ban class="h-4 w-4" />
              Batalkan
            </button>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- WORKFLOW STEPPER                                                  -->
        <!-- ================================================================= -->

        <div
          class="overflow-x-auto px-5 py-4 lg:px-6"
        >
          <div
            class="flex min-w-[680px] items-center"
          >
            <!-- Step 1 -->
            <div class="flex items-center gap-2">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="
                  run.status === 'draft'
                    ? 'bg-[#BD2028] text-white'
                    : 'bg-emerald-500 text-white'
                "
              >
                <Check
                  v-if="run.status !== 'draft'"
                  class="h-4 w-4"
                />
                <span
                  v-else
                  class="text-xs font-bold"
                >
                  1
                </span>
              </div>

              <div>
                <p
                  class="text-xs font-semibold text-slate-700"
                >
                  Setup
                </p>

                <p class="text-[11px] text-slate-400">
                  Payroll ready
                </p>
              </div>
            </div>

            <div
              class="mx-4 h-px flex-1"
              :class="
                run.status !== 'draft'
                  ? 'bg-emerald-200'
                  : 'bg-slate-200'
              "
            />

            <!-- Step 2 -->
            <div class="flex items-center gap-2">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="
                  ['processed', 'pending_approval', 'approved', 'locked']
                    .includes(run.status)
                    ? 'bg-emerald-500 text-white'
                    : run.status === 'draft'
                      ? 'border border-slate-200 bg-white text-slate-400'
                      : 'bg-slate-100 text-slate-400'
                "
              >
                <Check
                  v-if="
                    ['pending_approval', 'approved', 'locked']
                      .includes(run.status)
                  "
                  class="h-4 w-4"
                />

                <span
                  v-else
                  class="text-xs font-bold"
                >
                  2
                </span>
              </div>

              <div>
                <p
                  class="text-xs font-semibold text-slate-700"
                >
                  Calculated
                </p>

                <p class="text-[11px] text-slate-400">
                  Review payroll
                </p>
              </div>
            </div>

            <div
              class="mx-4 h-px flex-1"
              :class="
                ['pending_approval', 'approved', 'locked']
                  .includes(run.status)
                  ? 'bg-emerald-200'
                  : 'bg-slate-200'
              "
            />

            <!-- Step 3 -->
            <div class="flex items-center gap-2">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="
                  ['approved', 'locked'].includes(run.status)
                    ? 'bg-emerald-500 text-white'
                    : run.status === 'pending_approval'
                      ? 'bg-amber-500 text-white'
                      : 'border border-slate-200 bg-white text-slate-400'
                "
              >
                <Check
                  v-if="
                    ['approved', 'locked'].includes(
                      run.status,
                    )
                  "
                  class="h-4 w-4"
                />

                <Clock3
                  v-else-if="
                    run.status === 'pending_approval'
                  "
                  class="h-4 w-4"
                />

                <span
                  v-else
                  class="text-xs font-bold"
                >
                  3
                </span>
              </div>

              <div>
                <p
                  class="text-xs font-semibold text-slate-700"
                >
                  Approval
                </p>

                <p class="text-[11px] text-slate-400">
                  Review & approve
                </p>
              </div>
            </div>

            <div
              class="mx-4 h-px flex-1"
              :class="
                ['approved', 'locked'].includes(run.status)
                  ? 'bg-emerald-200'
                  : 'bg-slate-200'
              "
            />

            <!-- Step 4 -->
            <div class="flex items-center gap-2">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="
                  run.status === 'locked'
                    ? 'bg-emerald-500 text-white'
                    : 'border border-slate-200 bg-white text-slate-400'
                "
              >
                <Check
                  v-if="run.status === 'locked'"
                  class="h-4 w-4"
                />

                <Lock
                  v-else
                  class="h-4 w-4"
                />
              </div>

              <div>
                <p
                  class="text-xs font-semibold text-slate-700"
                >
                  Locked
                </p>

                <p class="text-[11px] text-slate-400">
                  Final payroll
                </p>
              </div>
            </div>

            <div
              class="mx-4 h-px flex-1"
              :class="
                run.status === 'locked'
                  ? 'bg-emerald-200'
                  : 'bg-slate-200'
              "
            />

            <!-- Step 5 -->
            <div class="flex items-center gap-2">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                :class="
                  run.published_at
                    ? 'bg-emerald-500 text-white'
                    : 'border border-slate-200 bg-white text-slate-400'
                "
              >
                <Check
                  v-if="run.published_at"
                  class="h-4 w-4"
                />

                <Eye
                  v-else
                  class="h-4 w-4"
                />
              </div>

              <div>
                <p
                  class="text-xs font-semibold text-slate-700"
                >
                  Published
                </p>

                <p class="text-[11px] text-slate-400">
                  Payslip release
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================================================================== -->
      <!-- ERROR ALERT                                                        -->
      <!-- ================================================================== -->

      <div
        v-if="actionError"
        class="mt-5 rounded-2xl border border-red-100 bg-red-50 px-4 py-4"
      >
        <div class="flex items-start gap-3">
          <AlertCircle
            class="mt-0.5 h-5 w-5 shrink-0 text-red-600"
          />

          <div>
            <p
              class="text-sm font-semibold text-red-800"
            >
              Terjadi kesalahan
            </p>

            <p
              class="mt-1 text-sm leading-5 text-red-700"
            >
              {{ actionError }}
            </p>
          </div>
        </div>
      </div>

      <!-- ================================================================== -->
      <!-- SUMMARY CARDS                                                      -->
      <!-- ================================================================== -->

      <section
        class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
      >
        <!-- Employee -->
        <div
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
        >
          <div class="flex items-start justify-between">
            <div>
              <p
                class="text-xs font-medium uppercase tracking-wide text-slate-400"
              >
                Total Employee
              </p>

              <p
                class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
              >
                {{ payslips.length || run.participants.length }}
              </p>
            </div>

            <div
              class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
            >
              <Users class="h-5 w-5" />
            </div>
          </div>

          <p
            class="mt-3 text-xs text-slate-400"
          >
            Employee yang diproses pada payroll
          </p>
        </div>

        <!-- Gross -->
        <div
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
        >
          <div class="flex items-start justify-between">
            <div class="min-w-0">
              <p
                class="text-xs font-medium uppercase tracking-wide text-slate-400"
              >
                Total Gross
              </p>

              <p
                class="mt-2 truncate text-xl font-semibold tracking-tight text-slate-900"
              >
                {{
                  payslips.length
                    ? formatCurrency(totalGross)
                    : 'Rp 0'
                }}
              </p>
            </div>

            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
              :style="{
                backgroundColor: PRIMARY_SOFT,
                color: PRIMARY,
              }"
            >
              <Calculator class="h-5 w-5" />
            </div>
          </div>

          <p
            class="mt-3 text-xs text-slate-400"
          >
            Total earnings sebelum deductions
          </p>
        </div>

        <!-- Net -->
        <div
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
        >
          <div class="flex items-start justify-between">
            <div class="min-w-0">
              <p
                class="text-xs font-medium uppercase tracking-wide text-slate-400"
              >
                Total Net Pay
              </p>

              <p
                class="mt-2 truncate text-xl font-semibold tracking-tight text-slate-900"
              >
                {{
                  payslips.length
                    ? formatCurrency(totalNetPay)
                    : 'Rp 0'
                }}
              </p>
            </div>

            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
            >
              <WalletCards class="h-5 w-5" />
            </div>
          </div>

          <p
            class="mt-3 text-xs text-slate-400"
          >
            Total take home pay employee
          </p>
        </div>

        <!-- Payslip -->
        <div
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
        >
          <div class="flex items-start justify-between">
            <div>
              <p
                class="text-xs font-medium uppercase tracking-wide text-slate-400"
              >
                Payslip Published
              </p>

              <p
                class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
              >
                {{ totalPublishedPayslip }}
                <span
                  class="text-sm font-medium text-slate-400"
                >
                  / {{ payslips.length }}
                </span>
              </p>
            </div>

            <div
              class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
            >
              <FileCheck2 class="h-5 w-5" />
            </div>
          </div>

          <p
            class="mt-3 text-xs text-slate-400"
          >
            Status publish payslip employee
          </p>
        </div>
      </section>

      <!-- ================================================================== -->
      <!-- TABS                                                               -->
      <!-- ================================================================== -->

      <section class="mt-6">
        <div
          class="flex overflow-x-auto border-b border-slate-200"
        >
          <button
            type="button"
            class="relative inline-flex shrink-0 items-center gap-2 px-4 py-3 text-sm font-semibold transition"
            :class="
              activeTab === 'overview'
                ? 'text-[#BD2028]'
                : 'text-slate-400 hover:text-slate-600'
            "
            @click="
              activeTab = 'overview';
              viewMode = 'overview'
            "
          >
            <WalletCards class="h-4 w-4" />
            Overview

            <span
              v-if="activeTab === 'overview'"
              class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#BD2028]"
            />
          </button>

          <button
            type="button"
            class="relative inline-flex shrink-0 items-center gap-2 px-4 py-3 text-sm font-semibold transition"
            :class="
              activeTab === 'history'
                ? 'text-[#BD2028]'
                : 'text-slate-400 hover:text-slate-600'
            "
            @click="activeTab = 'history'"
          >
            <History class="h-4 w-4" />
            Riwayat

            <span
              v-if="activeTab === 'history'"
              class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#BD2028]"
            />
          </button>

          <button
            type="button"
            class="relative inline-flex shrink-0 items-center gap-2 px-4 py-3 text-sm font-semibold transition"
            :class="
              activeTab === 'disbursement'
                ? 'text-[#BD2028]'
                : 'text-slate-400 hover:text-slate-600'
            "
            @click="activeTab = 'disbursement'"
          >
            <Landmark class="h-4 w-4" />
            Disbursement

            <span
              v-if="activeTab === 'disbursement'"
              class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-[#BD2028]"
            />
          </button>
        </div>
      </section>

      <!-- ================================================================== -->
      <!-- OVERVIEW                                                           -->
      <!-- ================================================================== -->

      <template v-if="activeTab === 'overview'">
        <!-- -------------------------------------------------------------- -->
        <!-- DETAIL VIEW                                                      -->
        <!-- -------------------------------------------------------------- -->

        <template
          v-if="
            viewMode === 'detail' &&
            selectedPayslip
          "
        >
          <section
            class="mt-5 space-y-5"
          >
            <!-- Detail header -->
            <div
              class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
            >
              <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                    @click="backToOverview"
                  >
                    <ArrowLeft class="h-4 w-4" />
                  </button>

                  <div>
                    <p
                      class="text-xs font-medium uppercase tracking-wide text-slate-400"
                    >
                      Employee Payroll
                    </p>

                    <h2
                      class="mt-1 text-lg font-semibold text-slate-900"
                    >
                      {{
                        employeeName(
                          selectedPayslip.employee,
                        )
                      }}
                    </h2>
                  </div>
                </div>

                <div
                  class="flex items-center gap-2"
                >
                  <span
                    class="rounded-full px-2.5 py-1.5 text-xs font-semibold"
                    :class="
                      selectedPayslip.is_published
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-slate-100 text-slate-500'
                    "
                  >
                    {{
                      selectedPayslip.is_published
                        ? 'Published'
                        : 'Belum Published'
                    }}
                  </span>
                </div>
              </div>

              <!-- Detail cards -->
              <div
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2"
              >
                <div
                  class="rounded-2xl bg-slate-50 p-5"
                >
                  <p
                    class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                  >
                    Earning
                  </p>

                  <div
                    class="mt-4 space-y-3"
                  >
                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        Gross Earning
                      </span>

                      <span
                        class="font-semibold text-slate-800"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.gross_earning,
                          )
                        }}
                      </span>
                    </div>

                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        Structural Deduction
                      </span>

                      <span
                        class="font-medium text-slate-700"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.structural_deduction,
                          )
                        }}
                      </span>
                    </div>

                    <div
                      class="border-t border-slate-200 pt-3"
                    >
                      <div
                        class="flex items-center justify-between gap-4"
                      >
                        <span
                          class="text-sm font-semibold text-slate-700"
                        >
                          Gross After Structural
                        </span>

                        <span
                          class="text-base font-semibold text-slate-900"
                        >
                          {{
                            formatCurrency(
                              Number(
                                selectedPayslip.gross_earning,
                              ) -
                                Number(
                                  selectedPayslip.structural_deduction,
                                ),
                            )
                          }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <div
                  class="rounded-2xl bg-slate-50 p-5"
                >
                  <p
                    class="text-xs font-semibold uppercase tracking-wide text-slate-400"
                  >
                    Deductions
                  </p>

                  <div
                    class="mt-4 space-y-3"
                  >
                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        Manual Deduction
                      </span>

                      <span
                        class="font-medium text-slate-700"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.manual_deduction_total,
                          )
                        }}
                      </span>
                    </div>

                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        BPJS Karyawan
                      </span>

                      <span
                        class="font-medium text-slate-700"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.bpjs_employee_total,
                          )
                        }}
                      </span>
                    </div>

                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        PPh 21
                      </span>

                      <span
                        class="font-medium text-slate-700"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.tax_amount,
                          )
                        }}
                      </span>
                    </div>

                    <div
                      class="flex items-center justify-between gap-4 text-sm"
                    >
                      <span class="text-slate-500">
                        Cicilan Loan
                      </span>

                      <span
                        class="font-medium text-slate-700"
                      >
                        {{
                          formatCurrency(
                            selectedPayslip.loan_deduction_total,
                          )
                        }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Net pay -->
              <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#F1D1D4] bg-[#FDF6F6]"
              >
                <div
                  class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div>
                    <p
                      class="text-xs font-semibold uppercase tracking-wide text-[#BD2028]"
                    >
                      Take Home Pay
                    </p>

                    <p
                      class="mt-1 text-sm text-slate-500"
                    >
                      Total penghasilan bersih setelah seluruh deduction.
                    </p>
                  </div>

                  <p
                    class="text-2xl font-bold tracking-tight text-[#BD2028]"
                  >
                    {{
                      formatCurrency(
                        selectedPayslip.net_pay,
                      )
                    }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Component breakdown -->
            <div
              class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
            >
              <div
                class="border-b border-slate-100 px-5 py-4"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
                  >
                    <CircleDollarSign
                      class="h-4.5 w-4.5"
                    />
                  </div>

                  <div>
                    <h3
                      class="text-sm font-semibold text-slate-800"
                    >
                      Breakdown Komponen
                    </h3>

                    <p
                      class="mt-0.5 text-xs text-slate-400"
                    >
                      Detail setiap komponen yang membentuk payroll employee.
                    </p>
                  </div>
                </div>
              </div>

              <div
                v-if="
                  selectedPayslip.lines.length === 0
                "
                class="px-5 py-10 text-center text-sm text-slate-400"
              >
                Tidak ada breakdown komponen.
              </div>

              <div
                v-else
                class="divide-y divide-slate-100"
              >
                <div
                  v-for="line in selectedPayslip.lines"
                  :key="line.id"
                  class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div>
                    <p
                      class="text-sm font-medium text-slate-700"
                    >
                      {{ line.label }}
                    </p>

                    <div
                      class="mt-1 flex items-center gap-2 text-xs text-slate-400"
                    >
                      <span>
                        {{
                          lineTypeLabels[line.type] ??
                          line.type
                        }}
                      </span>

                      <span>·</span>

                      <span>
                        {{ line.source }}
                      </span>
                    </div>
                  </div>

                  <span
                    class="text-sm font-semibold text-slate-800"
                  >
                    {{ formatCurrency(line.amount) }}
                  </span>
                </div>
              </div>
            </div>
          </section>
        </template>

        <!-- -------------------------------------------------------------- -->
        <!-- OVERVIEW LIST                                                   -->
        <!-- -------------------------------------------------------------- -->

        <template v-else>
          <!-- Approval banner -->
          <section
            v-if="run.approval_request"
            class="mt-5 rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
            >
              <div class="flex items-start gap-3">
                <div
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                  <ShieldCheck
                    class="h-5 w-5"
                  />
                </div>

                <div>
                  <p
                    class="text-sm font-semibold text-slate-800"
                  >
                    Approval Payroll
                  </p>

                  <p
                    class="mt-0.5 text-xs text-slate-500"
                  >
                    {{
                      approvalProgress.completed
                    }}
                    dari
                    {{
                      approvalProgress.total
                    }}
                    approval step selesai.
                  </p>
                </div>
              </div>

              <div
                class="flex items-center gap-4"
              >
                <div class="w-40">
                  <div
                    class="mb-1.5 flex items-center justify-between"
                  >
                    <span
                      class="text-[11px] font-medium text-slate-400"
                    >
                      Progress
                    </span>

                    <span
                      class="text-[11px] font-semibold text-slate-600"
                    >
                      {{
                        approvalProgress.percentage
                      }}%
                    </span>
                  </div>

                  <div
                    class="h-1.5 overflow-hidden rounded-full bg-slate-100"
                  >
                    <div
                      class="h-full rounded-full bg-[#BD2028] transition-all"
                      :style="{
                        width:
                          approvalProgress.percentage +
                          '%',
                      }"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div
              class="border-t border-slate-100 px-5 py-3"
            >
              <div
                class="flex flex-wrap gap-3"
              >
                <div
                  v-for="decision in run.approval_request.step_decisions"
                  :key="decision.id"
                  class="flex items-center gap-2 text-xs"
                >
                  <span
                    class="flex h-6 w-6 items-center justify-center rounded-full"
                    :class="
                      decision.status ===
                      'approved'
                        ? 'bg-emerald-50 text-emerald-600'
                        : decision.status ===
                            'rejected'
                          ? 'bg-red-50 text-red-600'
                          : 'bg-amber-50 text-amber-600'
                    "
                  >
                    <Check
                      v-if="
                        decision.status ===
                        'approved'
                      "
                      class="h-3.5 w-3.5"
                    />

                    <XCircle
                      v-else-if="
                        decision.status ===
                        'rejected'
                      "
                      class="h-3.5 w-3.5"
                    />

                    <Clock3
                      v-else
                      class="h-3.5 w-3.5"
                    />
                  </span>

                  <span
                    class="font-medium text-slate-600"
                  >
                    {{
                      decision.approval_step
                        .name ??
                      `Step ${decision.approval_step.sequence}`
                    }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- Participant draft -->
          <section
            v-if="run.status === 'draft'"
            class="mt-5 rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="border-b border-slate-100 px-5 py-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
                >
                  <Users class="h-4.5 w-4.5" />
                </div>

                <div>
                  <h3
                    class="text-sm font-semibold text-slate-800"
                  >
                    Peserta Payroll
                  </h3>

                  <p
                    class="mt-0.5 text-xs text-slate-400"
                  >
                    {{
                      run.participants.length
                    }}
                    employee terdaftar dalam payroll run ini.
                  </p>
                </div>
              </div>
            </div>

            <div class="p-5">
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="participant in run.participants"
                  :key="participant.id"
                  class="inline-flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600"
                >
                  <span
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-[10px] font-bold text-slate-500 shadow-sm"
                  >
                    {{
                      employeeName(
                        participant,
                      )
                        .charAt(0)
                        .toUpperCase()
                    }}
                  </span>

                  {{
                    employeeName(
                      participant,
                    )
                  }}
                </span>
              </div>
            </div>
          </section>

          <!-- Payroll table -->
          <section
            class="mt-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
            >
              <div>
                <h2
                  class="text-sm font-semibold text-slate-800"
                >
                  Payroll Employee
                </h2>

                <p
                  class="mt-0.5 text-xs text-slate-400"
                >
                  {{
                    payslips.length
                  }}
                  employee dalam revisi aktif.
                </p>
              </div>

              <div
                class="flex flex-wrap items-center gap-2"
              >
                <span
                  class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500"
                >
                  <Calculator class="h-3.5 w-3.5" />
                  Gross
                  {{
                    formatCurrency(
                      totalGross,
                    )
                  }}
                </span>

                <span
                  class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
                >
                  <WalletCards class="h-3.5 w-3.5" />
                  Net
                  {{
                    formatCurrency(
                      totalNetPay,
                    )
                  }}
                </span>
              </div>
            </div>

            <!-- Empty -->
            <div
              v-if="payslips.length === 0"
              class="px-5 py-16 text-center"
            >
              <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
              >
                <FileText class="h-6 w-6" />
              </div>

              <h3
                class="mt-4 text-sm font-semibold text-slate-800"
              >
                Payslip belum tersedia
              </h3>

              <p
                class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500"
              >
                {{
                  run.status === 'draft'
                    ? 'Jalankan proses payroll untuk menghasilkan perhitungan employee.'
                    : 'Belum ada payslip pada revisi aktif.'
                }}
              </p>

              <button
                v-if="
                  run.status === 'draft'
                "
                type="button"
                :disabled="actionProcessing"
                class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#9F1B22] disabled:opacity-50"
                @click="proceedPayslip"
              >
                <Play class="h-4 w-4" />
                Proses Payroll
              </button>
            </div>

            <!-- Desktop table -->
            <div
              v-else
              class="hidden overflow-x-auto xl:block"
            >
              <table
                class="w-full min-w-[1100px] text-left text-sm"
              >
                <thead>
                  <tr
                    class="border-b border-slate-100 bg-slate-50/70"
                  >
                    <th
                      class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Employee
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Gross
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      BPJS
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      PPh 21
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Loan
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Net Pay
                    </th>

                    <th
                      class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Payslip
                    </th>

                    <th
                      class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Action
                    </th>
                  </tr>
                </thead>

                <tbody
                  class="divide-y divide-slate-100"
                >
                  <tr
                    v-for="payslip in payslips"
                    :key="payslip.id"
                    class="cursor-pointer transition hover:bg-slate-50/70"
                    @click="openDetail(payslip)"
                  >
                    <td class="px-5 py-4">
                      <div
                        class="flex items-center gap-3"
                      >
                        <div
                          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500"
                        >
                          {{
                            employeeName(
                              payslip.employee,
                            )
                              .charAt(0)
                              .toUpperCase()
                          }}
                        </div>

                        <div>
                          <p
                            class="font-semibold text-slate-800"
                          >
                            {{
                              employeeName(
                                payslip.employee,
                              )
                            }}
                          </p>

                          <p
                            class="mt-0.5 text-[11px] text-slate-400"
                          >
                            Employee #{{
                              payslip.employee
                                .id
                            }}
                          </p>
                        </div>
                      </div>
                    </td>

                    <td
                      class="px-5 py-4 text-right text-slate-600"
                    >
                      {{
                        formatCurrency(
                          payslip.gross_earning,
                        )
                      }}
                    </td>

                    <td
                      class="px-5 py-4 text-right text-slate-600"
                    >
                      {{
                        formatCurrency(
                          payslip.bpjs_employee_total,
                        )
                      }}
                    </td>

                    <td
                      class="px-5 py-4 text-right text-slate-600"
                    >
                      {{
                        formatCurrency(
                          payslip.tax_amount,
                        )
                      }}
                    </td>

                    <td
                      class="px-5 py-4 text-right text-slate-600"
                    >
                      {{
                        formatCurrency(
                          payslip.loan_deduction_total,
                        )
                      }}
                    </td>

                    <td
                      class="px-5 py-4 text-right"
                    >
                      <p
                        class="font-semibold text-slate-800"
                      >
                        {{
                          formatCurrency(
                            payslip.net_pay,
                          )
                        }}
                      </p>

                      <p
                        class="mt-0.5 text-[11px] text-slate-400"
                      >
                        Take home pay
                      </p>
                    </td>

                    <td
                      class="px-5 py-4 text-center"
                    >
                      <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                        :class="
                          payslip.is_published
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-slate-100 text-slate-500'
                        "
                      >
                        <CheckCircle2
                          v-if="
                            payslip.is_published
                          "
                          class="h-3.5 w-3.5"
                        />

                        <Clock3
                          v-else
                          class="h-3.5 w-3.5"
                        />

                        {{
                          payslip.is_published
                            ? 'Published'
                            : 'Draft'
                        }}
                      </span>
                    </td>

                    <td
                      class="px-5 py-4 text-right"
                    >
                      <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-[#BD2028] transition hover:bg-[#FCEBED]"
                        @click.stop="
                          openDetail(
                            payslip,
                          )
                        "
                      >
                        <Eye
                          class="h-3.5 w-3.5"
                        />
                        Detail
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile cards -->
            <div
              v-if="payslips.length > 0"
              class="divide-y divide-slate-100 xl:hidden"
            >
              <button
                v-for="payslip in payslips"
                :key="payslip.id"
                type="button"
                class="block w-full px-4 py-4 text-left transition hover:bg-slate-50"
                @click="openDetail(payslip)"
              >
                <div
                  class="flex items-start justify-between gap-3"
                >
                  <div
                    class="flex items-center gap-3"
                  >
                    <div
                      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500"
                    >
                      {{
                        employeeName(
                          payslip.employee,
                        )
                          .charAt(0)
                          .toUpperCase()
                      }}
                    </div>

                    <div class="min-w-0">
                      <p
                        class="truncate text-sm font-semibold text-slate-800"
                      >
                        {{
                          employeeName(
                            payslip.employee,
                          )
                        }}
                      </p>

                      <p
                        class="mt-0.5 text-xs text-slate-400"
                      >
                        Employee #{{
                          payslip.employee
                            .id
                        }}
                      </p>
                    </div>
                  </div>

                  <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                    :class="
                      payslip.is_published
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-slate-100 text-slate-500'
                    "
                  >
                    {{
                      payslip.is_published
                        ? 'Published'
                        : 'Draft'
                    }}
                  </span>
                </div>

                <div
                  class="mt-4 grid grid-cols-2 gap-3"
                >
                  <div>
                    <p
                      class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Gross
                    </p>

                    <p
                      class="mt-1 text-sm font-medium text-slate-700"
                    >
                      {{
                        formatCurrency(
                          payslip.gross_earning,
                        )
                      }}
                    </p>
                  </div>

                  <div>
                    <p
                      class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                    >
                      Net Pay
                    </p>

                    <p
                      class="mt-1 text-sm font-semibold text-slate-800"
                    >
                      {{
                        formatCurrency(
                          payslip.net_pay,
                        )
                      }}
                    </p>
                  </div>
                </div>
              </button>
            </div>
          </section>
        </template>
      </template>

      <!-- ================================================================== -->
      <!-- HISTORY                                                            -->
      <!-- ================================================================== -->

      <template v-else-if="activeTab === 'history'">
        <section
          class="mt-5 space-y-5"
        >
          <!-- Revision -->
          <div
            class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="border-b border-slate-100 px-5 py-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
                >
                  <History class="h-4.5 w-4.5" />
                </div>

                <div>
                  <h3
                    class="text-sm font-semibold text-slate-800"
                  >
                    Riwayat Revisi
                  </h3>

                  <p
                    class="mt-0.5 text-xs text-slate-400"
                  >
                    Semua hasil kalkulasi payroll disimpan sebagai histori.
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="revisionHistory.length === 0"
              class="px-5 py-12 text-center text-sm text-slate-400"
            >
              Belum ada revisi payroll.
            </div>

            <div
              v-else
              class="divide-y divide-slate-100"
            >
              <div
                v-for="revision in revisionHistory"
                :key="revision.id"
              >
                <button
                  type="button"
                  class="flex w-full flex-col gap-3 px-5 py-4 text-left transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between"
                  @click="
                    toggleRevisionExpand(
                      revision.id,
                    )
                  "
                >
                  <div
                    class="flex items-center gap-3"
                  >
                    <div
                      class="flex h-9 w-9 items-center justify-center rounded-xl"
                      :class="
                        revision.revision_number ===
                        run.current_revision
                          ? 'bg-[#FCEBED] text-[#BD2028]'
                          : 'bg-slate-100 text-slate-500'
                      "
                    >
                      {{
                        revision.revision_number
                      }}
                    </div>

                    <div>
                      <div
                        class="flex flex-wrap items-center gap-2"
                      >
                        <p
                          class="text-sm font-semibold text-slate-800"
                        >
                          Revisi ke-{{
                            revision.revision_number
                          }}
                        </p>

                        <span
                          v-if="
                            revision.revision_number ===
                            run.current_revision
                          "
                          class="rounded-full bg-[#FCEBED] px-2 py-1 text-[10px] font-semibold text-[#BD2028]"
                        >
                          Aktif
                        </span>
                      </div>

                      <p
                        class="mt-0.5 text-xs text-slate-400"
                      >
                        {{
                          formatDateTime(
                            revision.calculated_at,
                          )
                        }}
                      </p>
                    </div>
                  </div>

                  <div
                    class="flex items-center justify-between gap-4 sm:justify-end"
                  >
                    <div class="text-left sm:text-right">
                      <p
                        class="text-xs text-slate-400"
                      >
                        {{
                          revision.payslips
                            .length
                        }}
                        employee
                      </p>

                      <p
                        class="mt-0.5 text-sm font-semibold text-slate-800"
                      >
                        {{
                          formatCurrency(
                            revisionNetTotal(
                              revision,
                            ),
                          )
                        }}
                      </p>
                    </div>

                    <ChevronDown
                      class="h-4 w-4 text-slate-400 transition"
                      :class="
                        expandedRevisionId ===
                        revision.id
                          ? 'rotate-180'
                          : ''
                      "
                    />
                  </div>
                </button>

                <div
                  v-if="
                    expandedRevisionId ===
                    revision.id
                  "
                  class="border-t border-slate-100 bg-slate-50/50"
                >
                  <div
                    v-if="revision.note"
                    class="border-b border-slate-100 px-5 py-3"
                  >
                    <div
                      class="flex items-start gap-2"
                    >
                      <Info
                        class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                      />

                      <p
                        class="text-xs leading-5 text-slate-500"
                      >
                        <span
                          class="font-semibold text-slate-600"
                        >
                          Catatan:
                        </span>
                        {{
                          revision.note
                        }}
                      </p>
                    </div>
                  </div>

                  <div
                    class="overflow-x-auto"
                  >
                    <table
                      class="w-full min-w-[800px] text-left text-sm"
                    >
                      <thead>
                        <tr
                          class="border-b border-slate-100"
                        >
                          <th
                            class="px-5 py-3 text-xs font-semibold text-slate-400"
                          >
                            Employee
                          </th>

                          <th
                            class="px-5 py-3 text-right text-xs font-semibold text-slate-400"
                          >
                            Gross
                          </th>

                          <th
                            class="px-5 py-3 text-right text-xs font-semibold text-slate-400"
                          >
                            BPJS
                          </th>

                          <th
                            class="px-5 py-3 text-right text-xs font-semibold text-slate-400"
                          >
                            PPh 21
                          </th>

                          <th
                            class="px-5 py-3 text-right text-xs font-semibold text-slate-400"
                          >
                            Net Pay
                          </th>
                        </tr>
                      </thead>

                      <tbody
                        class="divide-y divide-slate-100"
                      >
                        <tr
                          v-for="payslip in revision.payslips"
                          :key="payslip.id"
                        >
                          <td
                            class="px-5 py-3.5 font-medium text-slate-700"
                          >
                            {{
                              employeeName(
                                payslip.employee,
                              )
                            }}
                          </td>

                          <td
                            class="px-5 py-3.5 text-right text-slate-600"
                          >
                            {{
                              formatCurrency(
                                payslip.gross_earning,
                              )
                            }}
                          </td>

                          <td
                            class="px-5 py-3.5 text-right text-slate-600"
                          >
                            {{
                              formatCurrency(
                                payslip.bpjs_employee_total,
                              )
                            }}
                          </td>

                          <td
                            class="px-5 py-3.5 text-right text-slate-600"
                          >
                            {{
                              formatCurrency(
                                payslip.tax_amount,
                              )
                            }}
                          </td>

                          <td
                            class="px-5 py-3.5 text-right font-semibold text-slate-800"
                          >
                            {{
                              formatCurrency(
                                payslip.net_pay,
                              )
                            }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Approval history -->
          <div
            class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="border-b border-slate-100 px-5 py-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                >
                  <ShieldCheck
                    class="h-4.5 w-4.5"
                  />
                </div>

                <div>
                  <h3
                    class="text-sm font-semibold text-slate-800"
                  >
                    Riwayat Approval
                  </h3>

                  <p
                    class="mt-0.5 text-xs text-slate-400"
                  >
                    Audit trail setiap request approval payroll.
                  </p>
                </div>
              </div>
            </div>

            <div
              v-if="approvalHistory.length === 0"
              class="px-5 py-12 text-center text-sm text-slate-400"
            >
              Payroll belum pernah diajukan approval.
            </div>

            <div
              v-else
              class="divide-y divide-slate-100"
            >
              <div
                v-for="request in approvalHistory"
                :key="request.id"
                class="p-5"
              >
                <div
                  class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                  <div>
                    <div
                      class="flex items-center gap-2"
                    >
                      <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                        :class="
                          request.status ===
                          'approved'
                            ? 'bg-emerald-50 text-emerald-700'
                            : request.status ===
                                'rejected'
                              ? 'bg-red-50 text-red-700'
                              : 'bg-amber-50 text-amber-700'
                        "
                      >
                        <CheckCircle2
                          v-if="
                            request.status ===
                            'approved'
                          "
                          class="h-3.5 w-3.5"
                        />

                        <XCircle
                          v-else-if="
                            request.status ===
                            'rejected'
                          "
                          class="h-3.5 w-3.5"
                        />

                        <Clock3
                          v-else
                          class="h-3.5 w-3.5"
                        />

                        {{
                          approvalRequestStatusLabels[
                            request.status
                          ]
                        }}
                      </span>
                    </div>

                    <p
                      class="mt-2 text-xs text-slate-400"
                    >
                      Request #{{ request.id }}
                    </p>
                  </div>

                  <div
                    class="text-left sm:text-right"
                  >
                    <p
                      class="text-xs text-slate-400"
                    >
                      Diajukan
                    </p>

                    <p
                      class="mt-0.5 text-xs font-medium text-slate-600"
                    >
                      {{
                        formatDateTime(
                          request.requested_at,
                        )
                      }}
                    </p>

                    <p
                      v-if="request.decided_at"
                      class="mt-1 text-xs text-slate-400"
                    >
                      Diputuskan
                      {{
                        formatDateTime(
                          request.decided_at,
                        )
                      }}
                    </p>
                  </div>
                </div>

                <div
                  class="mt-4 space-y-2"
                >
                  <div
                    v-for="decision in request.step_decisions"
                    :key="decision.id"
                    class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"
                  >
                    <div
                      class="flex items-center gap-3"
                    >
                      <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                        :class="
                          decision.status ===
                          'approved'
                            ? 'bg-emerald-50 text-emerald-600'
                            : decision.status ===
                                'rejected'
                              ? 'bg-red-50 text-red-600'
                              : 'bg-amber-50 text-amber-600'
                        "
                      >
                        <Check
                          v-if="
                            decision.status ===
                            'approved'
                          "
                          class="h-4 w-4"
                        />

                        <XCircle
                          v-else-if="
                            decision.status ===
                            'rejected'
                          "
                          class="h-4 w-4"
                        />

                        <Clock3
                          v-else
                          class="h-4 w-4"
                        />
                      </span>

                      <div>
                        <p
                          class="text-sm font-medium text-slate-700"
                        >
                          {{
                            decision.approval_step
                              .name ??
                            `Step ${decision.approval_step.sequence}`
                          }}
                        </p>

                        <p
                          class="text-[11px] text-slate-400"
                        >
                          Approval step
                          {{
                            decision.approval_step
                              .sequence
                          }}
                        </p>
                      </div>
                    </div>

                    <span
                      class="text-xs font-semibold"
                      :class="
                        decision.status ===
                        'approved'
                          ? 'text-emerald-600'
                          : decision.status ===
                              'rejected'
                            ? 'text-red-600'
                            : 'text-amber-600'
                      "
                    >
                      {{
                        decision.status ===
                        'approved'
                          ? 'Disetujui'
                          : decision.status ===
                              'rejected'
                            ? 'Ditolak'
                            : 'Menunggu'
                      }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </template>

      <!-- ================================================================== -->
      <!-- DISBURSEMENT                                                       -->
      <!-- ================================================================== -->

      <template v-else-if="activeTab === 'disbursement'">
        <section
          class="mt-5 space-y-5"
        >
          <!-- Error -->
          <div
            v-if="disbursementError"
            class="rounded-2xl border border-red-100 bg-red-50 px-4 py-4"
          >
            <div class="flex items-start gap-3">
              <AlertCircle
                class="mt-0.5 h-5 w-5 shrink-0 text-red-600"
              />

              <p
                class="text-sm text-red-700"
              >
                {{ disbursementError }}
              </p>
            </div>
          </div>

          <!-- Locked requirement -->
          <div
            v-if="run.status !== 'locked'"
            class="rounded-2xl border border-slate-200/80 bg-white px-5 py-12 text-center shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
          >
            <div
              class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
            >
              <Lock class="h-6 w-6" />
            </div>

            <h3
              class="mt-4 text-sm font-semibold text-slate-800"
            >
              Payroll belum terkunci
            </h3>

            <p
              class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500"
            >
              Disbursement hanya dapat dilakukan setelah payroll run berstatus Locked.
            </p>
          </div>

          <template v-else>
            <!-- Bank completeness -->
            <div
              v-if="
                employeesMissingBankData.length >
                0
              "
              class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4"
            >
              <div
                class="flex items-start gap-3"
              >
                <div
                  class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-amber-600"
                >
                  <Landmark class="h-4 w-4" />
                </div>

                <div>
                  <p
                    class="text-sm font-semibold text-amber-800"
                  >
                    Data rekening belum lengkap
                  </p>

                  <p
                    class="mt-1 text-xs leading-5 text-amber-700"
                  >
                    {{
                      employeesMissingBankData.length
                    }}
                    employee belum memiliki data rekening bank lengkap.
                  </p>

                  <div
                    class="mt-3 flex flex-wrap gap-1.5"
                  >
                    <span
                      v-for="employee in employeesMissingBankData"
                      :key="employee.id"
                      class="rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-amber-700"
                    >
                      {{
                        employeeName(
                          employee,
                        )
                      }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Disbursement toolbar -->
            <div
              class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
            >
              <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
              >
                <div>
                  <div
                    class="flex items-center gap-3"
                  >
                    <div
                      class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
                    >
                      <Landmark
                        class="h-5 w-5"
                      />
                    </div>

                    <div>
                      <h3
                        class="text-sm font-semibold text-slate-800"
                      >
                        Payroll Disbursement
                      </h3>

                      <p
                        class="mt-0.5 text-xs text-slate-400"
                      >
                        Generate dan tracking file transfer payroll ke bank.
                      </p>
                    </div>
                  </div>
                </div>

                <button
                  type="button"
                  :disabled="
                    disbursementProcessing ||
                    employeesMissingBankData.length >
                      0
                  "
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9F1B22] disabled:cursor-not-allowed disabled:opacity-50"
                  @click="generateDisbursement"
                >
                  <Upload
                    class="h-4 w-4"
                  />

                  {{
                    disbursementProcessing
                      ? 'Processing...'
                      : 'Generate File Disbursement'
                  }}
                </button>
              </div>
            </div>

            <!-- Loading -->
            <div
              v-if="disbursementLoading"
              class="rounded-2xl border border-slate-200 bg-white p-10 text-center"
            >
              <RefreshCw
                class="mx-auto h-6 w-6 animate-spin text-slate-400"
              />

              <p
                class="mt-3 text-sm text-slate-400"
              >
                Memuat data disbursement...
              </p>
            </div>

            <!-- Empty -->
            <div
              v-else-if="
                disbursementBatches.length === 0
              "
              class="rounded-2xl border border-slate-200/80 bg-white px-5 py-14 text-center shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
            >
              <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
              >
                <FileText class="h-6 w-6" />
              </div>

              <h3
                class="mt-4 text-sm font-semibold text-slate-800"
              >
                Belum ada disbursement
              </h3>

              <p
                class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500"
              >
                Generate file disbursement dari revisi payroll yang sudah dikunci.
              </p>
            </div>

            <!-- Batches -->
            <div
              v-else
              class="space-y-3"
            >
              <div
                v-for="batch in disbursementBatches"
                :key="batch.id"
                class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
              >
                <div
                  class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between"
                >
                  <div>
                    <div
                      class="flex flex-wrap items-center gap-2"
                    >
                      <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-semibold"
                        :class="
                          disbursementStatusBadgeClass[
                            batch.status
                          ]
                        "
                      >
                        <CheckCircle2
                          v-if="
                            batch.status ===
                            'confirmed'
                          "
                          class="h-3.5 w-3.5"
                        />

                        <AlertCircle
                          v-else-if="
                            batch.status ===
                            'failed'
                          "
                          class="h-3.5 w-3.5"
                        />

                        <Clock3
                          v-else
                          class="h-3.5 w-3.5"
                        />

                        {{
                          disbursementStatusLabels[
                            batch.status
                          ]
                        }}
                      </span>

                      <span
                        class="text-xs text-slate-400"
                      >
                        Batch #{{
                          batch.id
                        }}
                      </span>
                    </div>

                    <p
                      class="mt-2 text-xs text-slate-400"
                    >
                      Revisi ke-{{
                        batch.revision
                          .revision_number
                      }}
                      ·
                      {{
                        formatDateTime(
                          batch.generated_at,
                        )
                      }}
                    </p>
                  </div>

                  <div
                    class="flex items-center gap-6"
                  >
                    <div>
                      <p
                        class="text-[11px] uppercase tracking-wide text-slate-400"
                      >
                        Employee
                      </p>

                      <p
                        class="mt-1 text-sm font-semibold text-slate-700"
                      >
                        {{
                          batch.total_employee_count
                        }}
                      </p>
                    </div>

                    <div>
                      <p
                        class="text-[11px] uppercase tracking-wide text-slate-400"
                      >
                        Total
                      </p>

                      <p
                        class="mt-1 text-sm font-semibold text-slate-800"
                      >
                        {{
                          formatCurrency(
                            batch.total_amount,
                          )
                        }}
                      </p>
                    </div>
                  </div>
                </div>

                <div
                  v-if="
                    batch.status === 'failed' &&
                    batch.failure_reason
                  "
                  class="border-t border-red-100 bg-red-50 px-5 py-3"
                >
                  <p
                    class="text-xs leading-5 text-red-700"
                  >
                    <span
                      class="font-semibold"
                    >
                      Alasan gagal:
                    </span>
                    {{ batch.failure_reason }}
                  </p>
                </div>

                <div
                  class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4"
                >
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                    @click="
                      downloadDisbursement(
                        batch.id,
                      )
                    "
                  >
                    <Download
                      class="h-3.5 w-3.5"
                    />
                    Download CSV
                  </button>

                  <button
                    v-if="
                      batch.status ===
                      'generated'
                    "
                    type="button"
                    :disabled="
                      disbursementProcessing
                    "
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                    @click="
                      markSent(
                        batch.id,
                      )
                    "
                  >
                    <Send
                      class="h-3.5 w-3.5"
                    />
                    Tandai Sudah Dikirim
                  </button>

                  <button
                    v-if="
                      batch.status ===
                      'sent'
                    "
                    type="button"
                    :disabled="
                      disbursementProcessing
                    "
                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    @click="
                      markConfirmed(
                        batch.id,
                      )
                    "
                  >
                    <CheckCircle2
                      class="h-3.5 w-3.5"
                    />
                    Tandai Terkonfirmasi
                  </button>

                  <button
                    v-if="
                      batch.status ===
                      'sent'
                    "
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-white px-3.5 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                    @click="
                      showFailForm =
                        showFailForm ===
                        batch.id
                          ? null
                          : batch.id
                    "
                  >
                    <XCircle
                      class="h-3.5 w-3.5"
                    />
                    Tandai Gagal
                  </button>
                </div>

                <div
                  v-if="
                    showFailForm ===
                    batch.id
                  "
                  class="border-t border-slate-100 bg-white px-5 py-4"
                >
                  <div
                    class="flex flex-col gap-2 sm:flex-row"
                  >
                    <textarea
                      v-model="failReason"
                      rows="2"
                      placeholder="Alasan gagal (wajib)"
                      class="min-h-[80px] flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                    />

                    <button
                      type="button"
                      :disabled="
                        !failReason.trim() ||
                        disbursementProcessing
                      "
                      class="self-start rounded-xl bg-red-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                      @click="
                        submitMarkFailed(
                          batch.id,
                        )
                      "
                    >
                      Konfirmasi
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </section>
      </template>

      <!-- ================================================================== -->
      <!-- RECALCULATE MODAL                                                 -->
      <!-- ================================================================== -->

      <Teleport to="body">
        <div
          v-if="showRecalcModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 py-6 backdrop-blur-[2px]"
          @click.self="closeRecalcModal"
        >
          <div
            class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20"
          >
            <div
              class="flex items-start justify-between border-b border-slate-100 px-6 py-5"
            >
              <div>
                <p
                  class="text-xs font-semibold uppercase tracking-wide text-[#BD2028]"
                >
                  Payroll Revision
                </p>

                <h2
                  class="mt-1 text-lg font-semibold text-slate-900"
                >
                  Recalculate Payroll
                </h2>

                <p
                  class="mt-1 text-sm leading-5 text-slate-500"
                >
                  Recalculate akan membuat revisi baru dan mempertahankan histori revisi sebelumnya.
                </p>
              </div>

              <button
                type="button"
                class="rounded-xl p-2 text-slate-400 hover:bg-slate-50"
                @click="closeRecalcModal"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="p-6">
              <div
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"
              >
                <div
                  class="flex items-start gap-3"
                >
                  <AlertCircle
                    class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                  />

                  <p
                    class="text-xs leading-5 text-amber-700"
                  >
                    Jika payroll sedang dalam proses approval, approval tersebut akan dibatalkan dan payroll perlu diajukan kembali.
                  </p>
                </div>
              </div>

              <label
                class="mt-5 block text-sm font-semibold text-slate-700"
              >
                Alasan Recalculate
              </label>

              <textarea
                v-model="recalcReason"
                rows="4"
                placeholder="Contoh: Perubahan komponen allowance employee."
                class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
              />

              <p
                class="mt-1.5 text-xs text-slate-400"
              >
                Alasan wajib diisi untuk kebutuhan audit trail.
              </p>
            </div>

            <div
              class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4"
            >
              <button
                type="button"
                class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="closeRecalcModal"
              >
                Batal
              </button>

              <button
                type="button"
                :disabled="
                  !recalcReason.trim() ||
                  actionProcessing
                "
                class="inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#9F1B22] disabled:cursor-not-allowed disabled:opacity-50"
                @click="submitRecalculate"
              >
                <RefreshCw
                  v-if="actionProcessing"
                  class="h-4 w-4 animate-spin"
                />

                {{
                  actionProcessing
                    ? 'Memproses...'
                    : 'Recalculate'
                }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ================================================================== -->
      <!-- CANCEL MODAL                                                       -->
      <!-- ================================================================== -->

      <Teleport to="body">
        <div
          v-if="showCancelModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 py-6 backdrop-blur-[2px]"
          @click.self="closeCancelModal"
        >
          <div
            class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/20"
          >
            <div
              class="flex items-start justify-between border-b border-slate-100 px-6 py-5"
            >
              <div>
                <p
                  class="text-xs font-semibold uppercase tracking-wide text-red-600"
                >
                  Payroll Cancellation
                </p>

                <h2
                  class="mt-1 text-lg font-semibold text-slate-900"
                >
                  Batalkan Payroll
                </h2>

                <p
                  class="mt-1 text-sm leading-5 text-slate-500"
                >
                  Payroll yang dibatalkan tidak dapat dilanjutkan melalui flow normal.
                </p>
              </div>

              <button
                type="button"
                class="rounded-xl p-2 text-slate-400 hover:bg-slate-50"
                @click="closeCancelModal"
              >
                <X class="h-5 w-5" />
              </button>
            </div>

            <div class="p-6">
              <div
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3"
              >
                <div
                  class="flex items-start gap-3"
                >
                  <AlertCircle
                    class="mt-0.5 h-4 w-4 shrink-0 text-red-600"
                  />

                  <p
                    class="text-xs leading-5 text-red-700"
                  >
                    Pastikan payroll memang perlu dibatalkan sebelum melanjutkan.
                  </p>
                </div>
              </div>

              <label
                class="mt-5 block text-sm font-semibold text-slate-700"
              >
                Alasan Pembatalan
              </label>

              <textarea
                v-model="cancelReason"
                rows="4"
                placeholder="Alasan pembatalan payroll..."
                class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-red-500 focus:ring-4 focus:ring-red-500/10"
              />
            </div>

            <div
              class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4"
            >
              <button
                type="button"
                class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                @click="closeCancelModal"
              >
                Batal
              </button>

              <button
                type="button"
                :disabled="
                  !cancelReason.trim() ||
                  actionProcessing
                "
                class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submitCancel"
              >
                <Ban class="h-4 w-4" />

                {{
                  actionProcessing
                    ? 'Memproses...'
                    : 'Batalkan Payroll'
                }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </template>
  </div>
</template>