<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import GroomingStandardItemsEditor, { type GroomingItemDraft } from '@/components/grooming/GroomingStandardItemsEditor.vue'
import { ClipboardList } from 'lucide-vue-next'

interface StandardRow {
  id: number
  type: 'self' | 'store'
  name: string
  version_number: number
  effective_date: string
  status: 'draft' | 'active' | 'archived'
  items_count: number
}

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  active: 'bg-emerald-50 text-emerald-600',
  archived: 'bg-slate-100 text-slate-400',
}

const activeTab = ref<'self' | 'store'>('self')
const loading = ref(true)
const errorMessage = ref('')
const standards = ref<StandardRow[]>([])

async function loadStandards() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/grooming-standards', { params: { type: activeTab.value } })
    standards.value = response.data.data.data
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Grooming Standard.'
  } finally {
    loading.value = false
  }
}

function switchTab(tab: 'self' | 'store') {
  activeTab.value = tab
  loadStandards()
}

// ---- Create modal ----
const showCreateModal = ref(false)
const createForm = ref({ name: '', description: '', effective_date: '' })
const createItems = ref<GroomingItemDraft[]>([
  { name: '', description: '', mandatory: true, requires_note_on_fail: true },
])
const createError = ref('')
const createSaving = ref(false)

function openCreateModal() {
  createForm.value = { name: '', description: '', effective_date: '' }
  createItems.value = [{ name: '', description: '', mandatory: true, requires_note_on_fail: true }]
  createError.value = ''
  showCreateModal.value = true
}

async function submitCreate() {
  createSaving.value = true
  createError.value = ''
  try {
    await apiClient.post(`/api/grooming-standards/${activeTab.value}`, {
      ...createForm.value,
      items: createItems.value,
    })
    showCreateModal.value = false
    await loadStandards()
  } catch (err: any) {
    createError.value = err.response?.data?.message || 'Gagal membuat Grooming Standard.'
  } finally {
    createSaving.value = false
  }
}

const router = useRouter()
function openDetail(row: StandardRow) {
  router.push({ name: 'grooming-standards.show', params: { id: row.id } })
}

onMounted(loadStandards)
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Grooming Standard</h1>
        <p class="text-sm text-slate-400">Kelola checklist Grooming Self &amp; Grooming Store beserta versinya.</p>
      </div>
      <button class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="openCreateModal">
        Buat Standard Baru
      </button>
    </div>

    <div class="flex gap-2 border-b border-slate-100">
      <button
        class="px-4 py-2 text-sm font-medium"
        :class="activeTab === 'self' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400'"
        @click="switchTab('self')"
      >
        Grooming Self
      </button>
      <button
        class="px-4 py-2 text-sm font-medium"
        :class="activeTab === 'store' ? 'border-b-2 border-primary text-primary-dark' : 'text-slate-400'"
        @click="switchTab('store')"
      >
        Grooming Store
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="standards.length === 0" title="Belum ada Grooming Standard" description="Buat standard pertama untuk mulai memakai fitur ini." :icon="ClipboardList" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Version</th>
            <th class="px-4 py-3">Effective Date</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Items</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in standards"
            :key="row.id"
            class="cursor-pointer border-b border-slate-50 hover:bg-slate-50"
            @click="openDetail(row)"
          >
            <td class="px-4 py-3 font-medium text-slate-700">{{ row.name }}</td>
            <td class="px-4 py-3 text-slate-500">v{{ row.version_number }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.effective_date?.slice(0, 10) }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUS_BADGE[row.status]">
                {{ row.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ row.items_count }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <BaseModal v-if="showCreateModal" :title="`Buat Grooming ${activeTab === 'self' ? 'Self' : 'Store'} Standard`" @close="showCreateModal = false">
        <form class="space-y-4" @submit.prevent="submitCreate">
          <div v-if="createError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ createError }}</div>

          <div>
            <label class="text-xs font-medium text-slate-500">Nama Standard</label>
            <input v-model="createForm.name" type="text" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Deskripsi (opsional)</label>
            <textarea v-model="createForm.description" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Effective Date</label>
            <input v-model="createForm.effective_date" type="date" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>

          <div>
            <label class="mb-2 block text-xs font-medium text-slate-500">Checklist Items</label>
            <GroomingStandardItemsEditor v-model="createItems" />
          </div>

          <p class="text-xs text-slate-400">
            Standard akan dibuat sebagai <strong>Draft</strong> — belum berlaku sampai kamu klik Activate di halaman detail.
          </p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showCreateModal = false">Batal</button>
            <button type="submit" :disabled="createSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ createSaving ? 'Menyimpan...' : 'Simpan sebagai Draft' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>