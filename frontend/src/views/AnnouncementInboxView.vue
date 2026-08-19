<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  Megaphone,
  Paperclip,
  Search,
  ArrowLeft,
  FileText,
  FileSpreadsheet,
  Image as ImageIcon,
  File as FileIcon,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Attachment {
  id: number
  original_filename: string
  url: string
}

interface RecipientRow {
  id: number
  read_at: string | null
  announcement: {
    id: number
    title: string
    content: string
    published_at: string | null
    category: {
      id: number
      name: string
    } | null
    attachments: Attachment[]
    created_by?: {
      id: number
      name: string
      photo_url?: string | null
      position?: string | null
    } | null
  }
}

const recipients = ref<RecipientRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/my-announcements')
    recipients.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat announcement.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ---- Filter ----
const search = ref('')
const categoryFilter = ref<number | ''>('')
const tab = ref<'all' | 'unread'>('all')

const availableCategories = computed(() => {
  const map = new Map<number, string>()

  recipients.value.forEach((r) => {
    if (r.announcement.category) {
      map.set(
        r.announcement.category.id,
        r.announcement.category.name,
      )
    }
  })

  return Array.from(
    map,
    ([id, name]) => ({ id, name }),
  )
})

const unreadCount = computed(() =>
  recipients.value.filter((r) => !r.read_at).length,
)

const filteredRecipients = computed(() =>
  recipients.value.filter((r) => {
    if (tab.value === 'unread' && r.read_at) {
      return false
    }

    if (
      categoryFilter.value &&
      r.announcement.category?.id !== categoryFilter.value
    ) {
      return false
    }

    if (search.value.trim()) {
      const q = search.value.toLowerCase()

      const inTitle =
        r.announcement.title.toLowerCase().includes(q)

      const inContent =
        r.announcement.content.toLowerCase().includes(q)

      const inCreator =
        r.announcement.created_by?.name
          ?.toLowerCase()
          .includes(q) ?? false

      const inPosition =
        r.announcement.created_by?.position
          ?.toLowerCase()
          .includes(q) ?? false

      if (
        !inTitle &&
        !inContent &&
        !inCreator &&
        !inPosition
      ) {
        return false
      }
    }

    return true
  }),
)

// ---- Detail ----
const detailTarget = ref<RecipientRow | null>(null)

async function openDetail(recipient: RecipientRow) {
  detailTarget.value = recipient

  if (!recipient.read_at) {
    try {
      await apiClient.post(
        `/api/announcements/${recipient.announcement.id}/read`,
      )

      recipient.read_at = new Date().toISOString()
    } catch {
      // Gagal mark read tidak menghalangi user membaca announcement.
    }
  }
}

function closeDetail() {
  detailTarget.value = null
}

// ---- Formatting ----
function formatRelativeDate(dateStr: string | null) {
  if (!dateStr) return '-'

  const date = new Date(dateStr)
  const diffMin = Math.floor(
    (Date.now() - date.getTime()) / 60000,
  )

  if (diffMin < 1) return 'Baru saja'
  if (diffMin < 60) return `${diffMin} menit lalu`

  const diffHour = Math.floor(diffMin / 60)

  if (diffHour < 24) return `${diffHour} jam lalu`

  const diffDay = Math.floor(diffHour / 24)

  if (diffDay < 7) return `${diffDay} hari lalu`

  return date.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

function formatFullDate(dateStr: string | null) {
  if (!dateStr) return '-'

  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
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

  if (ext === 'xls' || ext === 'xlsx') {
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

function isImageFile(filename: string) {
  const ext =
    filename.split('.').pop()?.toLowerCase() ?? ''

  return (
    ext === 'jpg' ||
    ext === 'jpeg' ||
    ext === 'png'
  )
}

function initials(name?: string | null) {
  if (!name) return '?'

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

// ---- Attachments ----
const imageAttachments = computed(() =>
  detailTarget.value?.announcement.attachments.filter(
    (a) => isImageFile(a.original_filename),
  ) ?? [],
)

const docAttachments = computed(() =>
  detailTarget.value?.announcement.attachments.filter(
    (a) => !isImageFile(a.original_filename),
  ) ?? [],
)
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-5 p-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-slate-900">
          Announcement
        </h1>

        <p class="mt-0.5 text-sm text-slate-500">
          Pengumuman yang ditujukan untuk kamu.
        </p>
      </div>

      <span
        v-if="unreadCount > 0"
        class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary-dark"
      >
        {{ unreadCount }} belum dibaca
      </span>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="text-sm text-slate-400"
    >
      Memuat data...
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMessage"
      class="rounded-xl bg-red-50 p-4 text-sm text-red-600"
    >
      {{ errorMessage }}
    </div>

    <!-- Empty -->
    <EmptyState
      v-else-if="recipients.length === 0"
      :icon="Megaphone"
      title="Belum ada announcement"
      description="Pengumuman yang ditujukan untuk kamu akan muncul di sini."
    />

    <template v-else>

      <!-- ========================= -->
      <!-- LIST -->
      <!-- ========================= -->

      <div
        v-if="!detailTarget"
        class="flex flex-col gap-3"
      >

        <!-- Search -->
        <div class="relative">
          <Search
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            :stroke-width="1.75"
          />

          <input
            v-model="search"
            type="text"
            placeholder="Cari announcement..."
            class="w-full max-w-sm rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>

        <!-- Filter -->
        <div class="flex flex-wrap items-center gap-2">

          <div
            class="flex rounded-xl bg-slate-100 p-1 text-xs font-medium"
          >
            <button
              type="button"
              @click="tab = 'all'"
              class="rounded-lg px-3 py-1.5 transition"
              :class="
                tab === 'all'
                  ? 'bg-white text-slate-800 shadow-sm'
                  : 'text-slate-500'
              "
            >
              All
            </button>

            <button
              type="button"
              @click="tab = 'unread'"
              class="rounded-lg px-3 py-1.5 transition"
              :class="
                tab === 'unread'
                  ? 'bg-white text-slate-800 shadow-sm'
                  : 'text-slate-500'
              "
            >
              Unread
              {{ unreadCount > 0 ? ` (${unreadCount})` : '' }}
            </button>
          </div>

          <select
            v-if="availableCategories.length"
            v-model="categoryFilter"
            class="rounded-xl border border-slate-200 px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          >
            <option value="">
              Semua Category
            </option>

            <option
              v-for="c in availableCategories"
              :key="c.id"
              :value="c.id"
            >
              {{ c.name }}
            </option>
          </select>
        </div>

        <!-- Announcement List -->
        <div class="space-y-2">

          <p
            v-if="!filteredRecipients.length"
            class="px-1 pt-6 text-center text-xs text-slate-400"
          >
            Tidak ada announcement yang cocok.
          </p>

          <button
            v-for="r in filteredRecipients"
            :key="r.id"
            type="button"
            @click="openDetail(r)"
            class="flex w-full items-start gap-3 rounded-2xl border border-slate-100 bg-white p-3.5 text-left shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition hover:border-primary/30"
          >
            <!-- Unread Indicator -->
            <span
              class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
              :class="
                r.read_at
                  ? 'bg-transparent'
                  : 'bg-primary'
              "
            />

            <div class="min-w-0 flex-1">

              <!-- Title + Date -->
              <div class="flex items-center justify-between gap-2">
                <p
                  class="truncate text-sm"
                  :class="
                    r.read_at
                      ? 'text-slate-600'
                      : 'font-semibold text-slate-900'
                  "
                >
                  {{ r.announcement.title }}
                </p>

                <span
                  class="shrink-0 text-[11px] text-slate-400"
                >
                  {{
                    formatRelativeDate(
                      r.announcement.published_at,
                    )
                  }}
                </span>
              </div>

              <!-- Publisher + Position -->
              <div
                v-if="r.announcement.created_by"
                class="mt-0.5 flex min-w-0 items-center gap-1.5"
              >
                <span
                  class="truncate text-xs text-slate-500"
                >
                  {{ r.announcement.created_by.name }}
                </span>

                <span
                  v-if="r.announcement.created_by.position"
                  class="shrink-0 text-xs text-slate-400"
                >
                  ·
                </span>

                <span
                  v-if="r.announcement.created_by.position"
                  class="truncate text-xs text-slate-400"
                >
                  {{ r.announcement.created_by.position }}
                </span>
              </div>

              <!-- Content -->
              <p
                class="mt-0.5 line-clamp-2 text-xs text-slate-500"
              >
                {{ r.announcement.content }}
              </p>

              <!-- Category -->
              <span
                v-if="r.announcement.category"
                class="mt-1.5 inline-block rounded-full bg-slate-50 px-2 py-0.5 text-[11px] text-slate-400"
              >
                {{ r.announcement.category.name }}
              </span>

            </div>
          </button>
        </div>
      </div>

      <!-- ========================= -->
      <!-- DETAIL -->
      <!-- ========================= -->

      <div v-else>

        <!-- Back -->
        <button
          type="button"
          @click="closeDetail"
          class="flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-primary-dark"
        >
          <ArrowLeft
            class="h-3.5 w-3.5"
            :stroke-width="2"
          />

          Kembali ke daftar
        </button>

        <!-- Article -->
        <div
          class="mx-auto mt-4 max-w-6xl rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)] sm:p-10"
        >

          <!-- Category + Date -->
          <div
            class="flex items-center gap-2 text-xs text-slate-400"
          >
            <span
              v-if="detailTarget.announcement.category"
              class="rounded-full bg-slate-50 px-2 py-0.5 text-slate-500"
            >
              {{
                detailTarget.announcement.category.name
              }}
            </span>

            <span>
              {{
                formatFullDate(
                  detailTarget.announcement.published_at,
                )
              }}
            </span>
          </div>

          <!-- Title -->
          <h2
            class="mt-2 text-2xl font-semibold text-slate-900"
          >
            {{ detailTarget.announcement.title }}
          </h2>

          <!-- Publisher + Position -->
          <div
            v-if="detailTarget.announcement.created_by"
            class="mt-4 flex items-center gap-2.5"
          >

            <!-- Photo -->
            <img
              v-if="
                detailTarget.announcement.created_by.photo_url
              "
              :src="
                detailTarget.announcement.created_by.photo_url
              "
              :alt="
                detailTarget.announcement.created_by.name
              "
              class="h-9 w-9 rounded-full object-cover"
            />

            <!-- Initials -->
            <div
              v-else
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-medium text-slate-500"
            >
              {{
                initials(
                  detailTarget.announcement.created_by.name,
                )
              }}
            </div>

            <!-- Name + Position -->
            <div class="min-w-0">
              <div
                class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs"
              >
                <span
                  class="font-medium text-slate-700"
                >
                  {{
                    detailTarget.announcement.created_by
                      .name
                  }}
                </span>

                <span
                  v-if="
                    detailTarget.announcement.created_by
                      .position
                  "
                  class="text-slate-300"
                >
                  ·
                </span>

                <span
                  v-if="
                    detailTarget.announcement.created_by
                      .position
                  "
                  class="text-slate-400"
                >
                  {{
                    detailTarget.announcement.created_by
                      .position
                  }}
                </span>
              </div>

              <p class="mt-0.5 text-[11px] text-slate-400">
                Dipost pada
                {{
                  formatFullDate(
                    detailTarget.announcement.published_at,
                  )
                }}
              </p>
            </div>
          </div>

          <!-- Content -->
          <p
            class="mt-5 whitespace-pre-line text-sm leading-relaxed text-slate-700"
          >
            {{ detailTarget.announcement.content }}
          </p>

          <!-- Attachments -->
          <div
            v-if="
              detailTarget.announcement.attachments.length
            "
            class="mt-8 space-y-3"
          >
            <p class="text-xs font-medium text-slate-500">
              Attachment
            </p>

            <!-- Image Attachments -->
            <div
              v-if="imageAttachments.length"
              class="flex flex-wrap gap-3"
            >
              <a
                v-for="att in imageAttachments"
                :key="att.id"
                :href="att.url"
                target="_blank"
                class="group block h-20 w-20 overflow-hidden rounded-lg border border-slate-100"
                :title="att.original_filename"
              >
                <img
                  :src="att.url"
                  :alt="att.original_filename"
                  class="h-full w-full object-cover transition group-hover:opacity-90"
                  loading="lazy"
                />
              </a>
            </div>

            <!-- Document Attachments -->
            <div
              v-if="docAttachments.length"
              class="space-y-1.5"
            >
              <a
                v-for="att in docAttachments"
                :key="att.id"
                :href="att.url"
                target="_blank"
                class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 text-sm text-slate-700 hover:text-primary-dark"
              >
                <component
                  :is="
                    fileIconFor(
                      att.original_filename,
                    )
                  "
                  class="h-4 w-4 shrink-0 text-slate-400"
                  :stroke-width="1.75"
                />

                <span class="truncate">
                  {{ att.original_filename }}
                </span>

                <Paperclip
                  class="ml-auto h-3.5 w-3.5 shrink-0 text-slate-300"
                  :stroke-width="2"
                />
              </a>
            </div>
          </div>

        </div>
      </div>

    </template>
  </div>
</template>