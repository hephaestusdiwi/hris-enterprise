<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  Megaphone, Paperclip, Search, ArrowLeft,
  FileText, FileSpreadsheet, Image as ImageIcon, File as FileIcon,
} from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import EmptyState from '@/components/ui/EmptyState.vue'

interface Attachment { id: number; original_filename: string; url: string }
interface RecipientRow {
  id: number
  read_at: string | null
  announcement: {
    id: number
    title: string
    content: string
    published_at: string | null
    category: { id: number; name: string } | null
    attachments: Attachment[]
    // OPSIONAL — belum dikirim oleh /api/my-announcements saat ini.
    // Begitu backend nambahin relasi createdBy (+ photo_url) di response,
    // UI di bawah otomatis nyala tanpa perlu ubah apa-apa lagi di sini.
    created_by?: { id: number; name: string; photo_url?: string | null } | null
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

// ---- Filter (client-side saja, tidak menyentuh endpoint) ----
const search = ref('')
const categoryFilter = ref<number | ''>('')
const tab = ref<'all' | 'unread'>('all')

const availableCategories = computed(() => {
  const map = new Map<number, string>()
  recipients.value.forEach((r) => {
    if (r.announcement.category) map.set(r.announcement.category.id, r.announcement.category.name)
  })
  return Array.from(map, ([id, name]) => ({ id, name }))
})

const unreadCount = computed(() => recipients.value.filter((r) => !r.read_at).length)

const filteredRecipients = computed(() =>
  recipients.value.filter((r) => {
    if (tab.value === 'unread' && r.read_at) return false
    if (categoryFilter.value && r.announcement.category?.id !== categoryFilter.value) return false
    if (search.value.trim()) {
      const q = search.value.toLowerCase()
      const inTitle = r.announcement.title.toLowerCase().includes(q)
      const inContent = r.announcement.content.toLowerCase().includes(q)
      if (!inTitle && !inContent) return false
    }
    return true
  }),
)

// ---- Detail (panel kanan, menggantikan modal lama) ----
const detailTarget = ref<RecipientRow | null>(null)

async function openDetail(recipient: RecipientRow) {
  detailTarget.value = recipient

  if (!recipient.read_at) {
    try {
      await apiClient.post(`/api/announcements/${recipient.announcement.id}/read`)
      recipient.read_at = new Date().toISOString()
    } catch {
      // Diam-diam gagal — bukan blocker buat lihat isi announcement-nya.
    }
  }
}

function closeDetail() {
  detailTarget.value = null
}

// ---- Formatting helper (murni tampilan, tidak ada library baru) ----
function formatRelativeDate(dateStr: string | null) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  const diffMin = Math.floor((Date.now() - date.getTime()) / 60000)
  if (diffMin < 1) return 'Baru saja'
  if (diffMin < 60) return `${diffMin} menit lalu`
  const diffHour = Math.floor(diffMin / 60)
  if (diffHour < 24) return `${diffHour} jam lalu`
  const diffDay = Math.floor(diffHour / 24)
  if (diffDay < 7) return `${diffDay} hari lalu`
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatFullDate(dateStr: string | null) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function fileIconFor(filename: string) {
  const ext = filename.split('.').pop()?.toLowerCase() ?? ''
  if (ext === 'pdf' || ext === 'doc' || ext === 'docx') return FileText
  if (ext === 'xls' || ext === 'xlsx') return FileSpreadsheet
  if (ext === 'jpg' || ext === 'jpeg' || ext === 'png') return ImageIcon
  return FileIcon
}

function isImageFile(filename: string) {
  const ext = filename.split('.').pop()?.toLowerCase() ?? ''
  return ext === 'jpg' || ext === 'jpeg' || ext === 'png'
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

// Attachment gambar ditampilkan sebagai thumbnail grid, sisanya tetap list.
const imageAttachments = computed(() => detailTarget.value?.announcement.attachments.filter((a) => isImageFile(a.original_filename)) ?? [])
const docAttachments = computed(() => detailTarget.value?.announcement.attachments.filter((a) => !isImageFile(a.original_filename)) ?? [])
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-slate-900">Announcement</h1>
        <p class="mt-0.5 text-sm text-slate-500">Pengumuman yang ditujukan untuk kamu.</p>
      </div>
      <span v-if="unreadCount > 0" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary-dark">
        {{ unreadCount }} belum dibaca
      </span>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState
      v-else-if="recipients.length === 0"
      :icon="Megaphone"
      title="Belum ada announcement"
      description="Pengumuman yang ditujukan untuk kamu akan muncul di sini."
    />

    <div v-else class="flex gap-5">
      <!-- LIST PANEL -->
      <div class="flex w-full flex-col gap-3 md:w-[360px] md:shrink-0" :class="detailTarget ? 'hidden md:flex' : 'flex'">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
          <input
            v-model="search"
            type="text"
            placeholder="Cari announcement..."
            class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>

        <div class="flex items-center gap-2">
          <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-medium">
            <button
              type="button"
              @click="tab = 'all'"
              class="rounded-lg px-3 py-1.5 transition"
              :class="tab === 'all' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
            >
              All
            </button>
            <button
              type="button"
              @click="tab = 'unread'"
              class="rounded-lg px-3 py-1.5 transition"
              :class="tab === 'unread' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500'"
            >
              Unread{{ unreadCount > 0 ? ` (${unreadCount})` : '' }}
            </button>
          </div>
          <select
            v-if="availableCategories.length"
            v-model="categoryFilter"
            class="flex-1 rounded-xl border border-slate-200 px-2.5 py-1.5 text-xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          >
            <option value="">Semua Category</option>
            <option v-for="c in availableCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div class="flex-1 space-y-2 overflow-y-auto pr-0.5">
          <p v-if="!filteredRecipients.length" class="px-1 pt-6 text-center text-xs text-slate-400">
            Tidak ada announcement yang cocok.
          </p>

          <button
            v-for="r in filteredRecipients"
            :key="r.id"
            type="button"
            @click="openDetail(r)"
            class="flex w-full items-start gap-3 rounded-2xl border bg-white p-3.5 text-left shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition"
            :class="detailTarget?.id === r.id ? 'border-primary bg-primary/5' : 'border-slate-100 hover:border-primary/30'"
          >
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="r.read_at ? 'bg-transparent' : 'bg-primary'" />
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-2">
                <p class="truncate text-sm" :class="r.read_at ? 'text-slate-600' : 'font-semibold text-slate-900'">
                  {{ r.announcement.title }}
                </p>
                <span class="shrink-0 text-[11px] text-slate-400">{{ formatRelativeDate(r.announcement.published_at) }}</span>
              </div>
              <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ r.announcement.content }}</p>
              <span v-if="r.announcement.category" class="mt-1.5 inline-block rounded-full bg-slate-50 px-2 py-0.5 text-[11px] text-slate-400">
                {{ r.announcement.category.name }}
              </span>
            </div>
          </button>
        </div>
      </div>

      <!-- DETAIL PANEL -->
      <div
        class="min-h-[60vh] flex-1 rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
        :class="detailTarget ? 'flex flex-col' : 'hidden md:flex md:items-center md:justify-center'"
      >
        <EmptyState
          v-if="!detailTarget"
          :icon="Megaphone"
          title="Pilih announcement"
          description="Klik salah satu pengumuman di sebelah kiri untuk melihat detailnya."
        />

        <div v-else class="flex h-full flex-col p-6 sm:p-8">
          <button type="button" @click="closeDetail" class="mb-4 flex items-center gap-1.5 text-xs font-medium text-slate-500 md:hidden">
            <ArrowLeft class="h-3.5 w-3.5" :stroke-width="2" />
            Kembali
          </button>

          <div class="flex items-center gap-2 text-xs text-slate-400">
            <span v-if="detailTarget.announcement.category" class="rounded-full bg-slate-50 px-2 py-0.5 text-slate-500">
              {{ detailTarget.announcement.category.name }}
            </span>
            <span>{{ formatFullDate(detailTarget.announcement.published_at) }}</span>
          </div>

          <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ detailTarget.announcement.title }}</h2>

          <!-- Publisher — muncul otomatis begitu backend kirim created_by -->
          <div v-if="detailTarget.announcement.created_by" class="mt-3 flex items-center gap-2">
            <img
              v-if="detailTarget.announcement.created_by.photo_url"
              :src="detailTarget.announcement.created_by.photo_url"
              :alt="detailTarget.announcement.created_by.name"
              class="h-7 w-7 rounded-full object-cover"
            />
            <div v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-[10px] font-medium text-slate-500">
              {{ initials(detailTarget.announcement.created_by.name) }}
            </div>
            <span class="text-xs text-slate-500">Dipublikasikan oleh <span class="font-medium text-slate-700">{{ detailTarget.announcement.created_by.name }}</span></span>
          </div>

          <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ detailTarget.announcement.content }}</p>

          <div v-if="detailTarget.announcement.attachments.length" class="mt-6 space-y-3">
            <p class="text-xs font-medium text-slate-500">Attachment</p>

            <!-- Gambar: langsung tampil sebagai thumbnail, klik buat buka full size -->
            <div v-if="imageAttachments.length" class="flex flex-wrap gap-3">
              <a
                v-for="att in imageAttachments"
                :key="att.id"
                :href="att.url"
                target="_blank"
                class="group block h-20 w-20 overflow-hidden rounded-lg border border-slate-100"
                :title="att.original_filename"
              >
                <img :src="att.url" :alt="att.original_filename" class="h-full w-full object-cover transition group-hover:opacity-90" loading="lazy" />
              </a>
            </div>

            <!-- Dokumen non-gambar: tetap list dengan icon sesuai tipe -->
            <div v-if="docAttachments.length" class="space-y-1.5">
              <a
                v-for="att in docAttachments"
                :key="att.id"
                :href="att.url"
                target="_blank"
                class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2 text-sm text-slate-700 hover:text-primary-dark"
              >
                <component :is="fileIconFor(att.original_filename)" class="h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
                <span class="truncate">{{ att.original_filename }}</span>
                <Paperclip class="ml-auto h-3.5 w-3.5 shrink-0 text-slate-300" :stroke-width="2" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>