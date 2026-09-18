<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import GroomingStandardItemsEditor, { type GroomingItemDraft } from '@/components/grooming/GroomingStandardItemsEditor.vue'
import { ArrowLeft } from 'lucide-vue-next'

interface StandardItem {
  id: number
  name: string
  description: string | null
  mandatory: boolean
  requires_note_on_fail: boolean
  is_active: boolean
}

interface StandardDetail {
  id: number
  type: 'self' | 'store'
  name: string
  description: string | null
  version_number: number
  effective_date: string
  status: 'draft' | 'active' | 'archived'
  items: StandardItem[]
}

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  active: 'bg-emerald-50 text-emerald-600',
  archived: 'bg-slate-100 text-slate-400',
}

const route = useRoute()
const router = useRouter()
const standardId = computed(() => Number(route.params.id))

const standard = ref<StandardDetail | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const actionLoading = ref(false)

const editItems = ref<GroomingItemDraft[]>([])
const editSaving = ref(false)

async function loadStandard() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/grooming-standards/${standardId.value}`)
    standard.value = response.data.data
    editItems.value = standard.value.items.map((i) => ({
      name: i.name,
      description: i.description || '',
      mandatory: i.mandatory,
      requires_note_on_fail: i.requires_note_on_fail,
    }))
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Grooming Standard.'
  } finally {
    loading.value = false
  }
}

async function saveDraft() {
  editSaving.value = true
  actionError.value = ''
  try {
    const response = await apiClient.put(`/api/grooming-standards/${standardId.value}`, { items: editItems.value })
    standard.value = response.data.data
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menyimpan perubahan.'
  } finally {
    editSaving.value = false
  }
}

async function activateStandard() {
  if (!confirm('Aktifkan standard ini? Standard aktif lain dengan tipe sama akan otomatis di-archive, dan item di standard ini tidak bisa diedit lagi setelah aktif.')) return
  actionLoading.value = true
  actionError.value = ''
  try {
    const response = await apiClient.post(`/api/grooming-standards/${standardId.value}/activate`)
    standard.value = response.data.data
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mengaktifkan standard.'
  } finally {
    actionLoading.value = false
  }
}

async function archiveStandard() {
  if (!confirm('Archive standard ini? Standard ini tidak akan lagi dipakai untuk submission baru.')) return
  actionLoading.value = true
  actionError.value = ''
  try {
    const response = await apiClient.post(`/api/grooming-standards/${standardId.value}/archive`)
    standard.value = response.data.data
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal meng-archive standard.'
  } finally {
    actionLoading.value = false
  }
}

// ---- Buat versi baru ----
const showVersionModal = ref(false)
const versionForm = ref({ name: '', effective_date: '' })
const versionItems = ref<GroomingItemDraft[]>([])
const versionError = ref('')
const versionSaving = ref(false)

function openVersionModal() {
  if (!standard.value) return
  versionForm.value = { name: standard.value.name, effective_date: '' }
  versionItems.value = standard.value.items.map((i) => ({
    name: i.name,
    description: i.description || '',
    mandatory: i.mandatory,
    requires_note_on_fail: i.requires_note_on_fail,
  }))
  versionError.value = ''
  showVersionModal.value = true
}

async function submitVersion() {
  versionSaving.value = true
  versionError.value = ''
  try {
    const response = await apiClient.post(`/api/grooming-standards/${standardId.value}/new-version`, {
      ...versionForm.value,
      items: versionItems.value,
    })
    showVersionModal.value = false
    router.push({ name: 'grooming-standards.show', params: { id: response.data.data.id } })
  } catch (err: any) {
    versionError.value = err.response?.data?.message || 'Gagal membuat versi baru.'
  } finally {
    versionSaving.value = false
  }
}

onMounted(loadStandard)
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-600" @click="router.push({ name: 'grooming-standards.index' })">
      <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
      Kembali
    </button>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>

    <template v-else-if="standard">
      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-slate-800">{{ standard.name }}</h1>
            <p class="text-sm text-slate-400">
              Grooming {{ standard.type === 'self' ? 'Self' : 'Store' }} &middot; v{{ standard.version_number }} &middot; Effective {{ standard.effective_date?.slice(0, 10) }}
            </p>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-medium" :class="STATUS_BADGE[standard.status]">
            {{ standard.status }}
          </span>
        </div>

        <div v-if="actionError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
          <button
            v-if="standard.status === 'draft'"
            :disabled="actionLoading"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            @click="activateStandard"
          >
            Activate
          </button>
          <button
            v-if="standard.status === 'active'"
            :disabled="actionLoading"
            class="rounded-xl border border-red-100 bg-red-50 px-4 py-2 text-sm text-red-600 disabled:opacity-50"
            @click="archiveStandard"
          >
            Archive
          </button>
          <button
            v-if="standard.status !== 'draft'"
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm hover:bg-slate-50"
            @click="openVersionModal"
          >
            Buat Versi Baru
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Checklist Items</h3>

        <template v-if="standard.status === 'draft'">
          <GroomingStandardItemsEditor v-model="editItems" />
          <div class="mt-4 flex justify-end">
            <button :disabled="editSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50" @click="saveDraft">
              {{ editSaving ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
          </div>
        </template>

        <div v-else class="space-y-2">
          <div v-for="item in standard.items" :key="item.id" class="flex items-center justify-between rounded-xl border border-slate-100 p-3">
            <div>
              <p class="text-sm font-medium text-slate-700">{{ item.name }}</p>
              <p v-if="item.description" class="text-xs text-slate-400">{{ item.description }}</p>
            </div>
            <div class="flex gap-2 text-xs text-slate-400">
              <span v-if="item.mandatory" class="rounded-full bg-slate-100 px-2 py-0.5">Mandatory</span>
              <span v-if="item.requires_note_on_fail" class="rounded-full bg-slate-100 px-2 py-0.5">Wajib catatan</span>
            </div>
          </div>
          <p class="pt-2 text-xs text-slate-400">
            Standard yang sudah {{ standard.status }} tidak bisa diedit — buat versi baru untuk mengubah kebijakan.
          </p>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <BaseModal v-if="showVersionModal" title="Buat Versi Baru" @close="showVersionModal = false">
        <form class="space-y-4" @submit.prevent="submitVersion">
          <div v-if="versionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ versionError }}</div>

          <div>
            <label class="text-xs font-medium text-slate-500">Nama Standard</label>
            <input v-model="versionForm.name" type="text" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Effective Date</label>
            <input v-model="versionForm.effective_date" type="date" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="mb-2 block text-xs font-medium text-slate-500">Checklist Items (sudah diisi dari versi sebelumnya, silakan ubah)</label>
            <GroomingStandardItemsEditor v-model="versionItems" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showVersionModal = false">Batal</button>
            <button type="submit" :disabled="versionSaving" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ versionSaving ? 'Menyimpan...' : 'Buat Versi Baru' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>