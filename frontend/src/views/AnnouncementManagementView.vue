<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import {
  Megaphone,
  Plus,
  Paperclip,
  Trash2,
  ChevronRight,
  Users,
  Building2,
  Network,
  Briefcase,
  BarChart3,
  UploadCloud,
  FileText,
  FileSpreadsheet,
  Image as ImageIcon,
  File as FileIcon,
  Info,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Category {
  id: number
  name: string
  code: string
}

interface Ref {
  id: number
  name: string
}

interface Attachment {
  id: number
  original_filename: string
  url: string
  size?: number
  mime_type?: string
}

interface AnnouncementRow {
  id: number
  title: string
  content: string
  status: 'draft' | 'published'
  target_type: 'all' | 'criteria'
  published_at: string | null
  category: Category | null
  created_by: { id: number; name: string } | null
  targets?: { target_type: string; target_id: number }[]
  attachments?: Attachment[]
}

const announcements = ref<AnnouncementRow[]>([])
const categories = ref<Category[]>([])
const branches = ref<Ref[]>([])
const departments = ref<Ref[]>([])
const positions = ref<Ref[]>([])
const jobLevels = ref<Ref[]>([])

const loading = ref(true)
const errorMessage = ref('')

const search = ref('')
const categoryFilter = ref('')
const statusFilter = ref('')

async function loadLookups() {
  const [c, b, d, p, jl] = await Promise.all([
    apiClient.get('/api/announcement-categories'),
    apiClient.get('/api/branches'),
    apiClient.get('/api/departments'),
    apiClient.get('/api/positions'),
    apiClient.get('/api/job-levels'),
  ])

  categories.value = c.data.data.data
  branches.value = b.data.data.data
  departments.value = d.data.data.data
  positions.value = p.data.data.data
  jobLevels.value = jl.data.data.data
}

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/announcements', {
      params: {
        search: search.value || undefined,
        category_id: categoryFilter.value || undefined,
        status: statusFilter.value || undefined,
      },
    })

    announcements.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar announcement.'
  } finally {
    loading.value = false
  }
}

let searchTimeout: ReturnType<typeof setTimeout>

watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 300)
})

watch([categoryFilter, statusFilter], load)

onMounted(() => {
  loadLookups()
  load()
})

// --------------------------------------------------
// Create / Edit
// --------------------------------------------------

const showForm = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
  title: '',
  content: '',
  announcement_category_id: null as number | null,
  target_type: 'all' as 'all' | 'criteria',
  targets: [] as { type: string; id: number }[],
})

const formSaving = ref(false)
const formError = ref('')

const activeTargetKey = ref('all')
const formAttachments = ref<Attachment[]>([])
const uploadingFile = ref(false)
const isDragging = ref(false)

const criteriaOptions = computed(() => [
  {
    type: 'branch',
    label: 'Branch',
    options: branches.value,
  },
  {
    type: 'department',
    label: 'Department',
    options: departments.value,
  },
  {
    type: 'position',
    label: 'Position',
    options: positions.value,
  },
  {
    type: 'job_level',
    label: 'Job Level',
    options: jobLevels.value,
  },
])

const targetCards = computed(() => [
  {
    key: 'all',
    label: 'All employees',
    desc: 'Kirim ke seluruh karyawan',
    icon: Users,
  },
  {
    key: 'branch',
    label: 'Branch',
    desc: 'Pilih berdasarkan cabang',
    icon: Building2,
  },
  {
    key: 'department',
    label: 'Organization',
    desc: 'Pilih berdasarkan departemen',
    icon: Network,
  },
  {
    key: 'position',
    label: 'Job Position',
    desc: 'Pilih berdasarkan jabatan',
    icon: Briefcase,
  },
  {
    key: 'job_level',
    label: 'Job Level',
    desc: 'Pilih berdasarkan level jabatan',
    icon: BarChart3,
  },
])

const activeCriteriaGroup = computed(() =>
  criteriaOptions.value.find(
    (group) => group.type === activeTargetKey.value,
  ),
)

const targetSummaryText = computed(() => {
  if (form.value.target_type === 'all') {
    return 'All employees (Seluruh karyawan)'
  }

  const group = activeCriteriaGroup.value

  if (!group) {
    return '-'
  }

  const names = form.value.targets
    .filter((target) => target.type === activeTargetKey.value)
    .map((target) =>
      group.options.find((option) => option.id === target.id)?.name,
    )
    .filter(Boolean)

  return names.length
    ? `${group.label}: ${names.join(', ')}`
    : `${group.label} (belum ada yang dipilih)`
})

function isTargetChecked(type: string, id: number) {
  return form.value.targets.some(
    (target) => target.type === type && target.id === id,
  )
}

function toggleTarget(type: string, id: number) {
  const index = form.value.targets.findIndex(
    (target) => target.type === type && target.id === id,
  )

  if (index >= 0) {
    form.value.targets.splice(index, 1)
  } else {
    form.value.targets.push({
      type,
      id,
    })
  }
}

function selectTargetCard(key: string) {
  if (key === 'all') {
    form.value.target_type = 'all'
    form.value.targets = []
  } else {
    if (activeTargetKey.value !== key) {
      form.value.targets = []
    }

    form.value.target_type = 'criteria'
  }

  activeTargetKey.value = key
}

function resetForm() {
  form.value = {
    title: '',
    content: '',
    announcement_category_id: null,
    target_type: 'all',
    targets: [],
  }

  activeTargetKey.value = 'all'
  formAttachments.value = []
  formError.value = ''
}

function openCreate() {
  editingId.value = null
  resetForm()
  showForm.value = true
}

async function openEdit(row: AnnouncementRow) {
  try {
    const response = await apiClient.get(
      `/api/announcements/${row.id}`,
    )

    const full = response.data.data as AnnouncementRow

    editingId.value = full.id

    form.value = {
      title: full.title,
      content: full.content,
      announcement_category_id: full.category?.id ?? null,
      target_type: full.target_type,
      targets: (full.targets ?? []).map((target) => ({
        type: target.target_type,
        id: target.target_id,
      })),
    }

    activeTargetKey.value =
      full.target_type === 'all'
        ? 'all'
        : (full.targets?.[0]?.target_type ?? 'branch')

    formAttachments.value = full.attachments ?? []
    formError.value = ''
    showForm.value = true
  } catch {
    alert('Gagal memuat data announcement.')
  }
}

function closeForm() {
  showForm.value = false
}

async function persistForm(): Promise<boolean> {
  if (!form.value.title.trim() || !form.value.content.trim()) {
    formError.value = 'Title dan Content wajib diisi.'
    return false
  }

  if (
    form.value.target_type === 'criteria' &&
    form.value.targets.length === 0
  ) {
    formError.value = 'Pilih minimal satu target penerima.'
    return false
  }

  formSaving.value = true
  formError.value = ''

  try {
    const payload = {
      title: form.value.title,
      content: form.value.content,
      announcement_category_id:
        form.value.announcement_category_id,
      target_type: form.value.target_type,
      targets:
        form.value.target_type === 'criteria'
          ? form.value.targets
          : undefined,
    }

    if (editingId.value) {
      await apiClient.put(
        `/api/announcements/${editingId.value}`,
        payload,
      )
    } else {
      const response = await apiClient.post(
        '/api/announcements',
        payload,
      )

      editingId.value = response.data.data.id
      formAttachments.value =
        response.data.data.attachments ?? []
    }

    await load()

    return true
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    formError.value =
      message ?? 'Gagal menyimpan announcement.'

    return false
  } finally {
    formSaving.value = false
  }
}

async function handleSaveDraft() {
  await persistForm()
}

async function handlePublish() {
  const ok = await persistForm()

  if (!ok || !editingId.value) {
    return
  }

  try {
    await apiClient.post(
      `/api/announcements/${editingId.value}/publish`,
    )

    showForm.value = false
    await load()
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    formError.value =
      message ?? 'Gagal publish announcement.'
  }
}

// --------------------------------------------------
// Quick Add Category
// --------------------------------------------------

async function quickAddCategory() {
  const name = window.prompt('Nama category baru:')

  if (!name || !name.trim()) {
    return
  }

  const code = window.prompt('Code category:')

  if (!code || !code.trim()) {
    return
  }

  try {
    const response = await apiClient.post(
      '/api/announcement-categories',
      {
        name: name.trim(),
        code: code.trim().toUpperCase(),
        is_active: true,
      },
    )

    const created = response.data.data

    // Masukkan category baru ke dropdown
    categories.value.push(created)

    // Langsung pilih category yang baru dibuat
    form.value.announcement_category_id = created.id
  } catch (err: unknown) {
    const responseData = (
      err as {
        response?: {
          data?: {
            message?: string
            errors?: Record<string, string[]>
          }
        }
      }
    )?.response?.data

    const validationErrors = responseData?.errors

    const firstValidationError = validationErrors
      ? Object.values(validationErrors).flat()[0]
      : undefined

    alert(
      firstValidationError ??
        responseData?.message ??
        'Gagal membuat category baru.',
    )
  }
}

// --------------------------------------------------
// Attachment
// --------------------------------------------------

async function uploadFormAttachments(
  files: FileList | File[],
) {
  if (!editingId.value) {
    return
  }

  uploadingFile.value = true

  try {
    for (const file of Array.from(files)) {
      const formData = new FormData()

      formData.append('file', file)

      const response = await apiClient.post(
        `/api/announcements/${editingId.value}/attachments`,
        formData,
      )

      formAttachments.value.push(response.data.data)
    }
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    alert(message ?? 'Gagal upload attachment.')
  } finally {
    uploadingFile.value = false
  }
}

function onFileInputChange(event: Event) {
  const input = event.target as HTMLInputElement

  if (input.files?.length) {
    uploadFormAttachments(input.files)
  }

  input.value = ''
}

function onDropFile(event: DragEvent) {
  event.preventDefault()
  isDragging.value = false

  if (event.dataTransfer?.files?.length) {
    uploadFormAttachments(event.dataTransfer.files)
  }
}

async function removeFormAttachment(
  attachmentId: number,
) {
  if (!editingId.value) {
    return
  }

  try {
    await apiClient.delete(
      `/api/announcements/${editingId.value}/attachments/${attachmentId}`,
    )

    formAttachments.value =
      formAttachments.value.filter(
        (attachment) => attachment.id !== attachmentId,
      )
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    alert(
      message ?? 'Gagal menghapus attachment.',
    )
  }
}

function fileIconFor(filename: string) {
  const ext =
    filename.split('.').pop()?.toLowerCase() ?? ''

  if (
    ext === 'pdf' ||
    ext === 'doc' ||
    ext === 'docx'
  ) {
    return FileText
  }

  if (
    ext === 'xls' ||
    ext === 'xlsx'
  ) {
    return FileSpreadsheet
  }

  if (
    ext === 'jpg' ||
    ext === 'jpeg' ||
    ext === 'png'
  ) {
    return ImageIcon
  }

  return FileIcon
}

function formatFileSize(bytes?: number) {
  if (!bytes) {
    return ''
  }

  if (bytes < 1024) {
    return `${bytes} B`
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// --------------------------------------------------
// Detail
// --------------------------------------------------

const detailTarget = ref<AnnouncementRow | null>(null)

async function openDetail(row: AnnouncementRow) {
  const response = await apiClient.get(
    `/api/announcements/${row.id}`,
  )

  detailTarget.value = response.data.data
}

async function uploadAttachment(event: Event) {
  if (!detailTarget.value) {
    return
  }

  const file = (
    event.target as HTMLInputElement
  ).files?.[0]

  if (!file) {
    return
  }

  uploadingFile.value = true

  try {
    const formData = new FormData()

    formData.append('file', file)

    await apiClient.post(
      `/api/announcements/${detailTarget.value.id}/attachments`,
      formData,
    )

    await openDetail(detailTarget.value)
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    alert(
      message ?? 'Gagal upload attachment.',
    )
  } finally {
    uploadingFile.value = false
  }
}

async function removeAttachment(
  attachmentId: number,
) {
  if (!detailTarget.value) {
    return
  }

  try {
    await apiClient.delete(
      `/api/announcements/${detailTarget.value.id}/attachments/${attachmentId}`,
    )

    await openDetail(detailTarget.value)
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    alert(
      message ?? 'Gagal menghapus attachment.',
    )
  }
}

async function publish() {
  if (!detailTarget.value) {
    return
  }

  if (
    !confirm(
      'Publish announcement ini? Setelah publish, announcement tidak bisa diedit lagi.',
    )
  ) {
    return
  }

  try {
    await apiClient.post(
      `/api/announcements/${detailTarget.value.id}/publish`,
    )

    await openDetail(detailTarget.value)
    await load()
  } catch (err: unknown) {
    const message = (
      err as {
        response?: {
          data?: {
            message?: string
          }
        }
      }
    )?.response?.data?.message

    alert(
      message ?? 'Gagal publish announcement.',
    )
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-5 p-6">

    <!-- LIST -->
    <template v-if="!showForm">

      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">
            Announcement Management
          </h1>

          <p class="mt-0.5 text-sm text-slate-500">
            Kelola pengumuman untuk seluruh atau sebagian employee.
          </p>
        </div>

        <button
          type="button"
          @click="openCreate"
          class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
        >
          <Plus class="h-4 w-4" :stroke-width="2" />
          Buat Announcement
        </button>
      </div>

      <div class="flex flex-wrap gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul..."
          class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm"
        />

        <select
          v-model="categoryFilter"
          class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >
          <option value="">Semua Category</option>

          <option
            v-for="c in categories"
            :key="c.id"
            :value="c.id"
          >
            {{ c.name }}
          </option>
        </select>

        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
        </select>
      </div>

      <div
        v-if="loading"
        class="text-sm text-slate-400"
      >
        Memuat data...
      </div>

      <div
        v-else-if="errorMessage"
        class="rounded-xl bg-red-50 p-4 text-sm text-red-600"
      >
        {{ errorMessage }}
      </div>

      <EmptyState
        v-else-if="announcements.length === 0"
        :icon="Megaphone"
        title="Belum ada announcement"
        description="Buat announcement pertama untuk mulai mengirim pengumuman ke employee."
        action-label="Buat Announcement"
        @action="openCreate"
      />

      <div
        v-else
        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      >
        <table class="w-full text-left text-sm">
          <thead
            class="border-b border-slate-100 bg-slate-50/60 text-xs uppercase tracking-wider text-slate-400"
          >
            <tr>
              <th class="px-5 py-3 font-medium">
                Title
              </th>

              <th class="px-3 py-3 font-medium">
                Category
              </th>

              <th class="px-3 py-3 font-medium">
                Target
              </th>

              <th class="px-3 py-3 font-medium">
                Status
              </th>

              <th class="px-3 py-3 font-medium"></th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-50">
            <tr
              v-for="a in announcements"
              :key="a.id"
              class="hover:bg-slate-50/50"
            >
              <td class="px-5 py-3.5 font-medium text-slate-800">
                {{ a.title }}
              </td>

              <td class="px-3 py-3.5 text-slate-500">
                {{ a.category?.name ?? '-' }}
              </td>

              <td class="px-3 py-3.5 text-slate-500">
                {{
                  a.target_type === 'all'
                    ? 'All Employees'
                    : 'Criteria'
                }}
              </td>

              <td class="px-3 py-3.5">
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="
                    a.status === 'published'
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'
                  "
                >
                  {{ a.status }}
                </span>
              </td>

              <td class="px-3 py-3.5 text-right">
                <button
                  type="button"
                  @click="openDetail(a)"
                  class="text-xs font-medium text-primary-dark hover:underline"
                >
                  Detail
                </button>

                <button
                  v-if="a.status === 'draft'"
                  type="button"
                  @click="openEdit(a)"
                  class="ml-3 text-xs font-medium text-slate-500 hover:underline"
                >
                  Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>

    <!-- FORM -->
    <template v-else>

      <nav class="flex items-center gap-1.5 text-xs text-slate-400">
        <span>Announcement</span>

        <ChevronRight class="h-3 w-3" />

        <button
          type="button"
          @click="closeForm"
          class="hover:text-primary-dark hover:underline"
        >
          Management
        </button>

        <ChevronRight class="h-3 w-3" />

        <span class="font-medium text-slate-600">
          {{
            editingId
              ? 'Edit Announcement'
              : 'Post Announcement'
          }}
        </span>
      </nav>

      <div>
        <h1 class="text-2xl font-semibold text-slate-900">
          {{
            editingId
              ? 'Edit Announcement'
              : 'Post Announcement'
          }}
        </h1>

        <p class="mt-1 text-sm text-slate-500">
          Buat pengumuman untuk disampaikan kepada karyawan.
        </p>
      </div>

      <p
        v-if="formError"
        class="rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-600"
      >
        {{ formError }}
      </p>

      <div
        class="space-y-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)] sm:p-8"
      >

        <!-- TITLE -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Title
            <span class="text-red-500">*</span>
          </label>

          <input
            v-model="form.title"
            type="text"
            placeholder="Masukkan judul pengumuman"
            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>

        <!-- CONTENT -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Content
            <span class="text-red-500">*</span>
          </label>

          <textarea
            v-model="form.content"
            rows="6"
            placeholder="Tulis isi pengumuman di sini..."
            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          ></textarea>
        </div>

        <!-- TARGET -->
        <div>
          <label class="mb-0.5 block text-sm font-medium text-slate-700">
            Target recipient
            <span class="text-red-500">*</span>
          </label>

          <p class="mb-3 text-xs text-slate-400">
            Pilih karyawan yang akan menerima pengumuman ini.
          </p>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <button
              v-for="card in targetCards"
              :key="card.key"
              type="button"
              @click="selectTargetCard(card.key)"
              class="relative flex items-start gap-3 rounded-xl border p-3.5 text-left transition"
              :class="
                activeTargetKey === card.key
                  ? 'border-primary bg-primary/5'
                  : 'border-slate-200 hover:border-slate-300'
              "
            >
              <component
                :is="card.icon"
                class="mt-0.5 h-5 w-5 shrink-0 text-slate-500"
                :stroke-width="1.75"
              />

              <span class="flex-1">
                <span class="block text-sm font-medium text-slate-800">
                  {{ card.label }}
                </span>

                <span class="block text-xs text-slate-400">
                  {{ card.desc }}
                </span>
              </span>

              <span
                class="mt-0.5 h-4 w-4 shrink-0 rounded-full border"
                :class="
                  activeTargetKey === card.key
                    ? 'border-primary bg-primary'
                    : 'border-slate-300'
                "
              ></span>
            </button>
          </div>

          <div
            v-if="activeTargetKey !== 'all' && activeCriteriaGroup"
            class="mt-3 rounded-xl bg-slate-50 p-3.5"
          >
            <p class="mb-2 text-xs font-medium text-slate-500">
              Pilih {{ activeCriteriaGroup.label }}
            </p>

            <div class="flex flex-wrap gap-2">
              <label
                v-for="opt in activeCriteriaGroup.options"
                :key="opt.id"
                class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs"
              >
                <input
                  type="checkbox"
                  :checked="
                    isTargetChecked(
                      activeTargetKey,
                      opt.id,
                    )
                  "
                  @change="
                    toggleTarget(
                      activeTargetKey,
                      opt.id,
                    )
                  "
                />

                {{ opt.name }}
              </label>

              <p
                v-if="!activeCriteriaGroup.options.length"
                class="text-xs text-slate-400"
              >
                Belum ada data
                {{ activeCriteriaGroup.label.toLowerCase() }}.
              </p>
            </div>
          </div>

          <div
            class="mt-3 rounded-xl bg-blue-50/60 px-3.5 py-2.5 text-xs text-slate-600"
          >
            Target yang dipilih:

            <span class="font-medium text-primary-dark">
              {{ targetSummaryText }}
            </span>
          </div>
        </div>

        <!-- CATEGORY -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Category
            <span class="text-red-500">*</span>
          </label>

          <div class="flex gap-2">
            <select
              v-model="form.announcement_category_id"
              class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option :value="null">
                Pilih kategori
              </option>

              <option
                v-for="c in categories"
                :key="c.id"
                :value="c.id"
              >
                {{ c.name }}
              </option>
            </select>

            <button
              type="button"
              @click="quickAddCategory"
              class="flex shrink-0 items-center gap-1.5 rounded-xl border border-primary px-3.5 py-2.5 text-sm font-medium text-primary-dark hover:bg-primary/5"
            >
              <Plus
                class="h-4 w-4"
                :stroke-width="2"
              />

              New Category
            </button>
          </div>
        </div>

        <!-- ATTACHMENT -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Attachment (Optional)
          </label>

          <div
            v-if="!editingId"
            class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-6 text-center text-xs text-slate-400"
          >
            Simpan sebagai draft terlebih dahulu untuk menambahkan attachment.
          </div>

          <template v-else>
            <label
              class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-8 text-center transition"
              :class="
                isDragging
                  ? 'border-primary bg-primary/5'
                  : 'border-slate-200 hover:border-slate-300'
              "
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop="onDropFile"
            >
              <UploadCloud
                class="h-6 w-6 text-slate-400"
                :stroke-width="1.5"
              />

              <span class="text-sm text-slate-500">
                {{
                  uploadingFile
                    ? 'Mengupload...'
                    : 'Drag & drop file di sini atau klik untuk upload'
                }}
              </span>

              <span class="text-xs text-slate-400">
                Tipe file: pdf, doc, docx, xls, xlsx, jpg, jpeg, png
              </span>

              <input
                type="file"
                multiple
                class="hidden"
                :disabled="uploadingFile"
                @change="onFileInputChange"
              />
            </label>

            <div
              v-if="formAttachments.length"
              class="mt-3 space-y-1.5"
            >
              <div
                v-for="att in formAttachments"
                :key="att.id"
                class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
              >
                <a
                  :href="att.url"
                  target="_blank"
                  class="flex min-w-0 items-center gap-2 text-sm text-slate-700 hover:text-primary-dark"
                >
                  <component
                    :is="fileIconFor(att.original_filename)"
                    class="h-4 w-4 shrink-0 text-slate-400"
                    :stroke-width="1.75"
                  />

                  <span class="truncate">
                    {{ att.original_filename }}
                  </span>

                  <span
                    v-if="att.size"
                    class="shrink-0 text-xs text-slate-400"
                  >
                    {{ formatFileSize(att.size) }}
                  </span>
                </a>

                <button
                  type="button"
                  @click="removeFormAttachment(att.id)"
                >
                  <Trash2
                    class="h-4 w-4 text-red-400 hover:text-red-600"
                    :stroke-width="1.75"
                  />
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- ACTION -->
        <div
          class="flex flex-col-reverse items-stretch justify-between gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center"
        >
          <div
            class="flex items-start gap-2 rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs text-slate-500"
          >
            <Info
              class="mt-0.5 h-3.5 w-3.5 shrink-0"
              :stroke-width="1.75"
            />

            <span>
              Simpan sebagai draft terlebih dahulu atau langsung publish.
            </span>
          </div>

          <div class="flex gap-2">
            <button
              type="button"
              :disabled="formSaving"
              @click="handleSaveDraft"
              class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
            >
              Save as Draft
            </button>

            <button
              type="button"
              :disabled="formSaving"
              @click="handlePublish"
              class="rounded-xl bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark disabled:opacity-50"
            >
              {{
                formSaving
                  ? 'Menyimpan...'
                  : 'Publish'
              }}
            </button>
          </div>
        </div>
      </div>

      <div
        class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
      >
        <Info
          class="mt-0.5 h-3.5 w-3.5 shrink-0"
          :stroke-width="1.75"
        />

        Pengumuman hanya dapat diubah atau dihapus selama masih berstatus Draft.
      </div>

    </template>

    <!-- DETAIL MODAL -->
    <BaseModal
      v-if="detailTarget"
      :title="detailTarget.title"
      max-width="max-w-lg"
      @close="detailTarget = null"
    >
      <div class="space-y-4 p-6">

        <span
          class="rounded-full px-2.5 py-0.5 text-xs font-medium"
          :class="
            detailTarget.status === 'published'
              ? 'bg-emerald-50 text-emerald-700'
              : 'bg-slate-100 text-slate-500'
          "
        >
          {{ detailTarget.status }}
        </span>

        <p class="whitespace-pre-line text-sm text-slate-700">
          {{ detailTarget.content }}
        </p>

        <div>
          <p class="mb-2 text-xs font-medium text-slate-500">
            Attachment
          </p>

          <div
            v-if="detailTarget.attachments?.length"
            class="space-y-1.5"
          >
            <div
              v-for="att in detailTarget.attachments"
              :key="att.id"
              class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs"
            >
              <a
                :href="att.url"
                target="_blank"
                class="flex items-center gap-1.5 text-primary-dark hover:underline"
              >
                <Paperclip
                  class="h-3.5 w-3.5"
                  :stroke-width="2"
                />

                {{ att.original_filename }}
              </a>

              <button
                v-if="detailTarget.status === 'draft'"
                type="button"
                @click="removeAttachment(att.id)"
              >
                <Trash2
                  class="h-3.5 w-3.5 text-red-400 hover:text-red-600"
                  :stroke-width="2"
                />
              </button>
            </div>
          </div>

          <p
            v-else
            class="text-xs text-slate-400"
          >
            Belum ada attachment.
          </p>

          <label
            v-if="detailTarget.status === 'draft'"
            class="mt-2 inline-block cursor-pointer text-xs font-medium text-primary-dark hover:underline"
          >
            {{
              uploadingFile
                ? 'Mengupload...'
                : '+ Tambah attachment'
            }}

            <input
              type="file"
              class="hidden"
              :disabled="uploadingFile"
              @change="uploadAttachment"
            />
          </label>
        </div>

        <button
          v-if="detailTarget.status === 'draft'"
          type="button"
          @click="publish"
          class="w-full rounded-xl bg-primary py-2 text-sm font-medium text-white hover:bg-primary-dark"
        >
          Publish
        </button>

      </div>
    </BaseModal>

  </div>
</template>