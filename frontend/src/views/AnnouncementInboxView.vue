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
  ChevronRight,
  ChevronDown,
  CheckCircle2,
  Clock3,
  Inbox,
  MailOpen,
  CalendarDays,
  UserRound,
  X,
  SlidersHorizontal,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import EmptyState from '@/components/ui/EmptyState.vue'

/* ==========================================================================
 * TYPES
 * ========================================================================== */

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

/* ==========================================================================
 * CONSTANTS
 * ========================================================================== */

const PRIMARY = '#BD2028'
const PRIMARY_DARK = '#9F1B22'
const PRIMARY_SOFT = '#FCEBED'

/* ==========================================================================
 * DATA
 * ========================================================================== */

const recipients = ref<RecipientRow[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function load() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await apiClient.get('/api/my-announcements')

    recipients.value =
      response.data.data?.data ??
      response.data.data ??
      []
  } catch {
    errorMessage.value = 'Gagal memuat announcement.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

/* ==========================================================================
 * FILTER
 * ========================================================================== */

const search = ref('')
const categoryFilter = ref<number | ''>('')
const tab = ref<'all' | 'unread'>('all')
const showFilter = ref(true)

const availableCategories = computed(() => {
  const map = new Map<number, string>()

  recipients.value.forEach((recipient) => {
    const category = recipient.announcement.category

    if (!category) return

    map.set(category.id, category.name)
  })

  return Array.from(
    map,
    ([id, name]) => ({
      id,
      name,
    }),
  )
})

const unreadCount = computed(() =>
  recipients.value.filter(
    (recipient) => !recipient.read_at,
  ).length,
)

const readCount = computed(
  () =>
    recipients.value.length -
    unreadCount.value,
)

const totalAttachmentCount = computed(() =>
  recipients.value.reduce(
    (total, recipient) =>
      total +
      recipient.announcement.attachments.length,
    0,
  ),
)

const filteredRecipients = computed(() =>
  recipients.value.filter((recipient) => {
    /* Tab */
    if (
      tab.value === 'unread' &&
      recipient.read_at
    ) {
      return false
    }

    /* Category */
    if (
      categoryFilter.value &&
      recipient.announcement.category?.id !==
        categoryFilter.value
    ) {
      return false
    }

    /* Search */
    const keyword = search.value.trim().toLowerCase()

    if (keyword) {
      const title =
        recipient.announcement.title
          .toLowerCase()

      const content =
        recipient.announcement.content
          .toLowerCase()

      const creator =
        recipient.announcement.created_by?.name
          ?.toLowerCase() ?? ''

      const position =
        recipient.announcement.created_by?.position
          ?.toLowerCase() ?? ''

      const category =
        recipient.announcement.category?.name
          ?.toLowerCase() ?? ''

      const matches =
        title.includes(keyword) ||
        content.includes(keyword) ||
        creator.includes(keyword) ||
        position.includes(keyword) ||
        category.includes(keyword)

      if (!matches) {
        return false
      }
    }

    return true
  }),
)

/* ==========================================================================
 * DETAIL
 * ========================================================================== */

const detailTarget =
  ref<RecipientRow | null>(null)

async function openDetail(
  recipient: RecipientRow,
) {
  detailTarget.value = recipient

  if (!recipient.read_at) {
    try {
      await apiClient.post(
        `/api/announcements/${recipient.announcement.id}/read`,
      )

      recipient.read_at =
        new Date().toISOString()
    } catch {
      // Gagal mark read tidak menghalangi user membaca announcement.
    }
  }

  window.scrollTo({
    top: 0,
    behavior: 'smooth',
  })
}

function closeDetail() {
  detailTarget.value = null
}

/* ==========================================================================
 * FORMATTING
 * ========================================================================== */

function formatRelativeDate(
  dateStr: string | null,
) {
  if (!dateStr) return '-'

  const date = new Date(dateStr)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  const diffMin = Math.floor(
    (Date.now() - date.getTime()) /
      60000,
  )

  if (diffMin < 1) {
    return 'Baru saja'
  }

  if (diffMin < 60) {
    return `${diffMin} menit lalu`
  }

  const diffHour = Math.floor(
    diffMin / 60,
  )

  if (diffHour < 24) {
    return `${diffHour} jam lalu`
  }

  const diffDay = Math.floor(
    diffHour / 24,
  )

  if (diffDay < 7) {
    return `${diffDay} hari lalu`
  }

  return date.toLocaleDateString(
    'id-ID',
    {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    },
  )
}

function formatFullDate(
  dateStr: string | null,
) {
  if (!dateStr) return '-'

  const date = new Date(dateStr)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleDateString(
    'id-ID',
    {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    },
  )
}

function formatFullDateTime(
  dateStr: string | null,
) {
  if (!dateStr) return '-'

  const date = new Date(dateStr)

  if (Number.isNaN(date.getTime())) {
    return '-'
  }

  return date.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function initials(
  name?: string | null,
) {
  if (!name) return '?'

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(
      (word) =>
        word[0]?.toUpperCase(),
    )
    .join('')
}

/* ==========================================================================
 * ATTACHMENTS
 * ========================================================================== */

function fileIconFor(
  filename: string,
) {
  const ext =
    filename
      .split('.')
      .pop()
      ?.toLowerCase() ?? ''

  if (
    ext === 'pdf' ||
    ext === 'doc' ||
    ext === 'docx'
  ) {
    return FileText
  }

  if (
    ext === 'xls' ||
    ext === 'xlsx' ||
    ext === 'csv'
  ) {
    return FileSpreadsheet
  }

  if (
    ext === 'jpg' ||
    ext === 'jpeg' ||
    ext === 'png' ||
    ext === 'webp'
  ) {
    return ImageIcon
  }

  return FileIcon
}

function isImageFile(
  filename: string,
) {
  const ext =
    filename
      .split('.')
      .pop()
      ?.toLowerCase() ?? ''

  return [
    'jpg',
    'jpeg',
    'png',
    'webp',
  ].includes(ext)
}

const imageAttachments = computed(
  () =>
    detailTarget.value?.announcement.attachments.filter(
      (attachment) =>
        isImageFile(
          attachment.original_filename,
        ),
    ) ?? [],
)

const docAttachments = computed(
  () =>
    detailTarget.value?.announcement.attachments.filter(
      (attachment) =>
        !isImageFile(
          attachment.original_filename,
        ),
    ) ?? [],
)

/* ==========================================================================
 * UI HELPERS
 * ========================================================================== */

function resetFilters() {
  search.value = ''
  categoryFilter.value = ''
  tab.value = 'all'
}

function selectedCategoryName() {
  if (!categoryFilter.value) {
    return ''
  }

  return (
    availableCategories.value.find(
      (category) =>
        category.id ===
        categoryFilter.value,
    )?.name ?? ''
  )
}
</script>

<template>
  <div
    class="min-h-full bg-slate-50/30"
  >
    <!-- ================================================================== -->
    <!-- DETAIL VIEW                                                        -->
    <!-- ================================================================== -->

    <template v-if="detailTarget">
      <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <div
          class="mb-5 flex items-center gap-1.5 text-xs font-medium text-slate-400"
        >
          <button
            type="button"
            class="transition hover:text-slate-600"
            @click="closeDetail"
          >
            Announcement
          </button>

          <ChevronRight
            class="h-3.5 w-3.5"
          />

          <span class="truncate text-slate-600">
            {{
              detailTarget.announcement.title
            }}
          </span>
        </div>

        <!-- Back -->
        <button
          type="button"
          class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-[#BD2028]"
          @click="closeDetail"
        >
          <ArrowLeft class="h-4 w-4" />
          Kembali ke Announcement
        </button>

        <!-- Article -->
        <article
          class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_rgba(15,23,42,0.04)]"
        >
          <!-- Article Header -->
          <div
            class="border-b border-slate-100 px-5 py-6 sm:px-8 sm:py-8 lg:px-10"
          >
            <div
              class="flex flex-wrap items-center gap-2"
            >
              <span
                v-if="
                  detailTarget.announcement
                    .category
                "
                class="rounded-full bg-[#FCEBED] px-3 py-1.5 text-xs font-semibold text-[#BD2028]"
              >
                {{
                  detailTarget.announcement
                    .category.name
                }}
              </span>

              <span
                class="text-xs text-slate-400"
              >
                {{
                  formatFullDate(
                    detailTarget.announcement
                      .published_at,
                  )
                }}
              </span>

              <span
                v-if="
                  detailTarget.announcement
                    .attachments.length
                "
                class="inline-flex items-center gap-1 text-xs text-slate-400"
              >
                <Paperclip
                  class="h-3.5 w-3.5"
                />

                {{
                  detailTarget.announcement
                    .attachments.length
                }}
                attachment
              </span>
            </div>

            <h1
              class="mt-4 max-w-4xl text-2xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-3xl lg:text-[32px]"
            >
              {{
                detailTarget.announcement.title
              }}
            </h1>

            <!-- Publisher -->
            <div
              v-if="
                detailTarget.announcement.created_by
              "
              class="mt-6 flex items-center gap-3"
            >
              <img
                v-if="
                  detailTarget.announcement.created_by
                    .photo_url
                "
                :src="
                  detailTarget.announcement.created_by
                    .photo_url
                "
                :alt="
                  detailTarget.announcement.created_by
                    .name
                "
                class="h-10 w-10 rounded-full object-cover ring-2 ring-slate-100"
              />

              <div
                v-else
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FCEBED] text-xs font-bold text-[#BD2028]"
              >
                {{
                  initials(
                    detailTarget.announcement.created_by
                      .name,
                  )
                }}
              </div>

              <div class="min-w-0">
                <div
                  class="flex flex-wrap items-center gap-x-1.5"
                >
                  <span
                    class="text-sm font-semibold text-slate-700"
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
                    class="text-xs text-slate-400"
                  >
                    {{
                      detailTarget.announcement.created_by
                        .position
                    }}
                  </span>
                </div>

                <p
                  class="mt-0.5 text-xs text-slate-400"
                >
                  Dipublikasikan
                  {{
                    formatFullDateTime(
                      detailTarget.announcement
                        .published_at,
                    )
                  }}
                </p>
              </div>
            </div>
          </div>

          <!-- Article Content -->
          <div
            class="px-5 py-6 sm:px-8 sm:py-8 lg:px-10"
          >
            <div
              class="max-w-4xl whitespace-pre-line text-sm leading-7 text-slate-700 sm:text-[15px] sm:leading-8"
            >
              {{
                detailTarget.announcement.content
              }}
            </div>

            <!-- Image Attachments -->
            <div
              v-if="imageAttachments.length"
              class="mt-8"
            >
              <div
                class="mb-3 flex items-center gap-2"
              >
                <ImageIcon
                  class="h-4 w-4 text-slate-400"
                />

                <p
                  class="text-sm font-semibold text-slate-700"
                >
                  Lampiran Gambar
                </p>
              </div>

              <div
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
              >
                <a
                  v-for="attachment in imageAttachments"
                  :key="attachment.id"
                  :href="attachment.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                >
                  <img
                    :src="attachment.url"
                    :alt="
                      attachment.original_filename
                    "
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    loading="lazy"
                  />

                  <div
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent px-3 pb-3 pt-8 opacity-0 transition group-hover:opacity-100"
                  >
                    <p
                      class="truncate text-[11px] font-medium text-white"
                    >
                      {{
                        attachment.original_filename
                      }}
                    </p>
                  </div>
                </a>
              </div>
            </div>

            <!-- Document Attachments -->
            <div
              v-if="docAttachments.length"
              class="mt-8"
            >
              <div
                class="mb-3 flex items-center gap-2"
              >
                <Paperclip
                  class="h-4 w-4 text-slate-400"
                />

                <p
                  class="text-sm font-semibold text-slate-700"
                >
                  Lampiran Dokumen
                </p>
              </div>

              <div
                class="grid grid-cols-1 gap-2 md:grid-cols-2"
              >
                <a
                  v-for="attachment in docAttachments"
                  :key="attachment.id"
                  :href="attachment.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-[#BD2028]/30 hover:bg-[#FDF7F7]"
                >
                  <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 group-hover:bg-[#FCEBED] group-hover:text-[#BD2028]"
                  >
                    <component
                      :is="
                        fileIconFor(
                          attachment.original_filename,
                        )
                      "
                      class="h-5 w-5"
                    />
                  </div>

                  <div class="min-w-0 flex-1">
                    <p
                      class="truncate text-sm font-medium text-slate-700"
                    >
                      {{
                        attachment.original_filename
                      }}
                    </p>

                    <p
                      class="mt-0.5 text-xs text-slate-400"
                    >
                      Klik untuk membuka file
                    </p>
                  </div>

                  <ChevronRight
                    class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-[#BD2028]"
                  />
                </a>
              </div>
            </div>
          </div>
        </article>
      </div>
    </template>

    <!-- ================================================================== -->
    <!-- LIST VIEW                                                          -->
    <!-- ================================================================== -->

    <template v-else>
      <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
        <!-- ================================================================ -->
        <!-- HEADER                                                           -->
        <!-- ================================================================ -->

        <section
          class="rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_rgba(15,23,42,0.04)]"
        >
          <div
            class="flex flex-col gap-5 px-5 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
          >
            <div class="flex items-start gap-4">
              <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#FCEBED] text-[#BD2028]"
              >
                <Megaphone class="h-6 w-6" />
              </div>

              <div>
                <div
                  class="flex flex-wrap items-center gap-2"
                >
                  <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900"
                  >
                    Announcement
                  </h1>

                  <span
                    v-if="unreadCount > 0"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#FCEBED] px-2.5 py-1 text-[11px] font-semibold text-[#BD2028]"
                  >
                    <span
                      class="h-1.5 w-1.5 rounded-full bg-[#BD2028]"
                    />
                    {{ unreadCount }}
                    belum dibaca
                  </span>
                </div>

                <p
                  class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-500"
                >
                  Informasi dan pengumuman perusahaan yang ditujukan untuk kamu.
                </p>
              </div>
            </div>

            <!-- Right stats -->
            <div
              class="flex items-center gap-5"
            >
              <div>
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-400"
                >
                  Total
                </p>

                <p
                  class="mt-1 text-lg font-semibold text-slate-800"
                >
                  {{ recipients.length }}
                </p>
              </div>

              <div
                class="h-9 w-px bg-slate-200"
              />

              <div>
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-400"
                >
                  Sudah dibaca
                </p>

                <p
                  class="mt-1 text-lg font-semibold text-slate-800"
                >
                  {{ readCount }}
                </p>
              </div>

              <div
                class="hidden h-9 w-px bg-slate-200 sm:block"
              />

              <div class="hidden sm:block">
                <p
                  class="text-[11px] font-medium uppercase tracking-wide text-slate-400"
                >
                  Attachment
                </p>

                <p
                  class="mt-1 text-lg font-semibold text-slate-800"
                >
                  {{ totalAttachmentCount }}
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- ================================================================ -->
        <!-- LOADING                                                          -->
        <!-- ================================================================ -->

        <section
          v-if="loading"
          class="mt-5 space-y-3"
        >
          <div
            class="h-12 w-full animate-pulse rounded-2xl bg-slate-100"
          />

          <div
            v-for="index in 5"
            :key="index"
            class="rounded-2xl border border-slate-100 bg-white p-5"
          >
            <div
              class="flex gap-3"
            >
              <div
                class="h-10 w-10 shrink-0 animate-pulse rounded-full bg-slate-100"
              />

              <div class="flex-1 space-y-2">
                <div
                  class="h-4 w-2/3 animate-pulse rounded bg-slate-100"
                />

                <div
                  class="h-3 w-1/3 animate-pulse rounded bg-slate-100"
                />

                <div
                  class="h-3 w-full animate-pulse rounded bg-slate-100"
                />

                <div
                  class="h-3 w-4/5 animate-pulse rounded bg-slate-100"
                />
              </div>
            </div>
          </div>
        </section>

        <!-- ================================================================ -->
        <!-- ERROR                                                            -->
        <!-- ================================================================ -->

        <section
          v-else-if="errorMessage"
          class="mt-5 rounded-2xl border border-red-100 bg-red-50 p-5"
        >
          <div
            class="flex items-start gap-3"
          >
            <div
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-red-600"
            >
              <X class="h-4 w-4" />
            </div>

            <div>
              <p
                class="text-sm font-semibold text-red-800"
              >
                Tidak dapat memuat announcement
              </p>

              <p
                class="mt-1 text-sm text-red-700"
              >
                {{ errorMessage }}
              </p>

              <button
                type="button"
                class="mt-3 text-xs font-semibold text-red-700 underline underline-offset-2"
                @click="load"
              >
                Coba lagi
              </button>
            </div>
          </div>
        </section>

        <!-- ================================================================ -->
        <!-- EMPTY TOTAL                                                      -->
        <!-- ================================================================ -->

        <EmptyState
          v-else-if="recipients.length === 0"
          :icon="Megaphone"
          title="Belum ada announcement"
          description="Pengumuman yang ditujukan untuk kamu akan muncul di halaman ini."
          class="mt-5"
        />

        <!-- ================================================================ -->
        <!-- CONTENT                                                          -->
        <!-- ================================================================ -->

        <template v-else>
          <!-- ============================================================ -->
          <!-- TOOLBAR                                                      -->
          <!-- ============================================================ -->

          <section
            class="mt-5 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_8px_rgba(15,23,42,0.04)]"
          >
            <!-- Search row -->
            <div
              class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div
                class="relative w-full sm:max-w-md"
              >
                <Search
                  class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />

                <input
                  v-model="search"
                  type="search"
                  placeholder="Cari pengumuman..."
                  class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                />
              </div>

              <button
                type="button"
                class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-slate-200 px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 sm:self-auto"
                @click="
                  showFilter = !showFilter
                "
              >
                <SlidersHorizontal
                  class="h-4 w-4"
                />

                Filter

                <ChevronDown
                  class="h-4 w-4 transition-transform"
                  :class="
                    showFilter
                      ? 'rotate-180'
                      : ''
                  "
                />
              </button>
            </div>

            <!-- Filter panel -->
            <div
              v-if="showFilter"
              class="border-t border-slate-100 bg-slate-50/60 px-4 py-3"
            >
              <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
              >
                <!-- Tabs -->
                <div
                  class="inline-flex w-fit rounded-xl bg-slate-100 p-1"
                >
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition"
                    :class="
                      tab === 'all'
                        ? 'bg-white text-slate-800 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                    "
                    @click="tab = 'all'"
                  >
                    <Inbox
                      class="h-3.5 w-3.5"
                    />

                    Semua

                    <span
                      class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500"
                    >
                      {{ recipients.length }}
                    </span>
                  </button>

                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition"
                    :class="
                      tab === 'unread'
                        ? 'bg-white text-slate-800 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'
                    "
                    @click="tab = 'unread'"
                  >
                    <MailOpen
                      class="h-3.5 w-3.5"
                    />

                    Belum Dibaca

                    <span
                      v-if="unreadCount"
                      class="rounded-full bg-[#FCEBED] px-1.5 py-0.5 text-[10px] font-bold text-[#BD2028]"
                    >
                      {{ unreadCount }}
                    </span>
                  </button>
                </div>

                <!-- Category + reset -->
                <div
                  class="flex flex-wrap items-center gap-2"
                >
                  <select
                    v-if="
                      availableCategories.length
                    "
                    v-model="categoryFilter"
                    class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 outline-none transition focus:border-[#BD2028] focus:ring-4 focus:ring-[#BD2028]/10"
                  >
                    <option value="">
                      Semua Kategori
                    </option>

                    <option
                      v-for="category in availableCategories"
                      :key="category.id"
                      :value="category.id"
                    >
                      {{ category.name }}
                    </option>
                  </select>

                  <button
                    v-if="
                      search ||
                      categoryFilter ||
                      tab === 'unread'
                    "
                    type="button"
                    class="h-9 rounded-xl px-3 text-xs font-semibold text-[#BD2028] transition hover:bg-[#FCEBED]"
                    @click="resetFilters"
                  >
                    Reset Filter
                  </button>
                </div>
              </div>
            </div>

            <!-- Active filters -->
            <div
              v-if="
                search ||
                categoryFilter ||
                tab === 'unread'
              "
              class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-4 py-3"
            >
              <span
                class="text-[11px] font-medium text-slate-400"
              >
                Filter aktif:
              </span>

              <span
                v-if="search"
                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-medium text-slate-600"
              >
                Search: "{{ search }}"
              </span>

              <span
                v-if="tab === 'unread'"
                class="rounded-lg bg-[#FCEBED] px-2.5 py-1.5 text-[11px] font-semibold text-[#BD2028]"
              >
                Belum dibaca
              </span>

              <span
                v-if="categoryFilter"
                class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-medium text-slate-600"
              >
                {{ selectedCategoryName() }}
              </span>
            </div>
          </section>

          <!-- ============================================================ -->
          <!-- LIST META                                                    -->
          <!-- ============================================================ -->

          <div
            class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
          >
            <div>
              <p
                class="text-sm font-semibold text-slate-800"
              >
                Pengumuman
              </p>

              <p
                class="mt-0.5 text-xs text-slate-400"
              >
                Menampilkan
                {{
                  filteredRecipients.length
                }}
                dari
                {{
                  recipients.length
                }}
                announcement
              </p>
            </div>

            <div
              v-if="
                filteredRecipients.length
              "
              class="text-xs text-slate-400"
            >
              Klik announcement untuk melihat detail.
            </div>
          </div>

          <!-- ============================================================ -->
          <!-- NO RESULT                                                    -->
          <!-- ============================================================ -->

          <div
            v-if="
              filteredRecipients.length === 0
            "
            class="mt-3 rounded-2xl border border-slate-200/80 bg-white px-5 py-14 text-center shadow-[0_2px_8px_rgba(15,23,42,0.04)]"
          >
            <div
              class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
            >
              <Search class="h-6 w-6" />
            </div>

            <h3
              class="mt-4 text-sm font-semibold text-slate-800"
            >
              Announcement tidak ditemukan
            </h3>

            <p
              class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500"
            >
              Tidak ada announcement yang sesuai dengan pencarian atau filter yang dipilih.
            </p>

            <button
              type="button"
              class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
              @click="resetFilters"
            >
              Reset Filter
            </button>
          </div>

          <!-- ============================================================ -->
          <!-- ANNOUNCEMENT FEED                                             -->
          <!-- ============================================================ -->

          <section
            v-else
            class="mt-3 space-y-3"
          >
            <button
              v-for="recipient in filteredRecipients"
              :key="recipient.id"
              type="button"
              class="group block w-full rounded-2xl border bg-white p-4 text-left transition duration-200 hover:-translate-y-[1px] hover:shadow-[0_8px_24px_rgba(15,23,42,0.06)] sm:p-5"
              :class="
                recipient.read_at
                  ? 'border-slate-200/80'
                  : 'border-[#EBC7CA] bg-[#FFFCFC]'
              "
              @click="openDetail(recipient)"
            >
              <div
                class="flex items-start gap-3 sm:gap-4"
              >
                <!-- Unread / read marker -->
                <div
                  class="flex w-3 shrink-0 justify-center pt-2.5"
                >
                  <span
                    class="h-2 w-2 rounded-full"
                    :class="
                      recipient.read_at
                        ? 'bg-transparent'
                        : 'bg-[#BD2028] shadow-[0_0_0_3px_#FCEBED]'
                    "
                  />
                </div>

                <!-- Avatar -->
                <div
                  v-if="
                    recipient.announcement.created_by
                  "
                  class="shrink-0"
                >
                  <img
                    v-if="
                      recipient.announcement
                        .created_by.photo_url
                    "
                    :src="
                      recipient.announcement
                        .created_by.photo_url
                    "
                    :alt="
                      recipient.announcement
                        .created_by.name
                    "
                    class="h-11 w-11 rounded-xl object-cover ring-1 ring-slate-200"
                  />

                  <div
                    v-else
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FCEBED] text-xs font-bold text-[#BD2028]"
                  >
                    {{
                      initials(
                        recipient.announcement
                          .created_by.name,
                      )
                    }}
                  </div>
                </div>

                <div
                  v-else
                  class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400"
                >
                  <Megaphone
                    class="h-5 w-5"
                  />
                </div>

                <!-- Body -->
                <div class="min-w-0 flex-1">
                  <!-- Top line -->
                  <div
                    class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                  >
                    <div class="min-w-0">
                      <div
                        class="flex flex-wrap items-center gap-2"
                      >
                        <p
                          class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"
                        >
                          {{
                            recipient.announcement
                              .created_by
                              ?.name ??
                            'Company'
                          }}
                        </p>

                        <span
                          v-if="
                            recipient.announcement
                              .category
                          "
                          class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500"
                        >
                          {{
                            recipient.announcement
                              .category.name
                          }}
                        </span>

                        <span
                          v-if="
                            recipient.announcement.attachments
                              .length
                          "
                          class="inline-flex items-center gap-1 text-[10px] text-slate-400"
                        >
                          <Paperclip
                            class="h-3 w-3"
                          />

                          {{
                            recipient.announcement
                              .attachments
                              .length
                          }}
                        </span>
                      </div>

                      <h2
                        class="mt-1 line-clamp-2 text-[15px] leading-6"
                        :class="
                          recipient.read_at
                            ? 'font-medium text-slate-700'
                            : 'font-semibold text-slate-900'
                        "
                      >
                        {{
                          recipient.announcement
                            .title
                        }}
                      </h2>
                    </div>

                    <span
                      class="shrink-0 text-[11px] text-slate-400"
                    >
                      {{
                        formatRelativeDate(
                          recipient.announcement
                            .published_at,
                        )
                      }}
                    </span>
                  </div>

                  <!-- Publisher -->
                  <div
                    v-if="
                      recipient.announcement
                        .created_by
                    "
                    class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-400"
                  >
                    <span>
                      {{
                        recipient.announcement
                          .created_by.position
                      }}
                    </span>

                    <span
                      v-if="
                        recipient.announcement
                          .created_by.position
                      "
                      class="text-slate-300"
                    >
                      ·
                    </span>

                    <span>
                      {{
                        formatFullDate(
                          recipient.announcement
                            .published_at,
                        )
                      }}
                    </span>
                  </div>

                  <!-- Content -->
                  <p
                    class="mt-2 line-clamp-2 max-w-4xl text-sm leading-6 text-slate-500"
                  >
                    {{
                      recipient.announcement
                        .content
                    }}
                  </p>

                  <!-- Bottom -->
                  <div
                    class="mt-4 flex items-center justify-between gap-3"
                  >
                    <div
                      class="flex items-center gap-2"
                    >
                      <span
                        v-if="
                          !recipient.read_at
                        "
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#FCEBED] px-2 py-1 text-[10px] font-semibold text-[#BD2028]"
                      >
                        <span
                          class="h-1.5 w-1.5 rounded-full bg-[#BD2028]"
                        />
                        Belum dibaca
                      </span>

                      <span
                        v-else
                        class="inline-flex items-center gap-1 text-[10px] font-medium text-slate-400"
                      >
                        <CheckCircle2
                          class="h-3.5 w-3.5"
                        />
                        Sudah dibaca
                      </span>
                    </div>

                    <span
                      class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 transition group-hover:text-[#BD2028]"
                    >
                      Lihat detail

                      <ChevronRight
                        class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"
                      />
                    </span>
                  </div>
                </div>
              </div>
            </button>
          </section>
        </template>
      </div>
    </template>
  </div>
</template>