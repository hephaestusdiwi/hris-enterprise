<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { ShieldOff, Search } from 'lucide-vue-next'

interface BlacklistRow {
  id: number
  email: string
  full_name: string | null
  reason: string
  blacklisted_at: string
  blacklisted_by: { first_name: string; last_name: string | null } | null
}

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')

const blacklists = ref<BlacklistRow[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const search = ref('')

function blacklisterName(e: BlacklistRow['blacklisted_by']): string {
  if (!e) return '-'
  return [e.first_name, e.last_name].filter(Boolean).join(' ')
}

async function loadBlacklists(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/candidate-blacklists', {
      params: { page, search: search.value || undefined },
    })
    blacklists.value = response.data.data.data
    currentPage.value = response.data.data.current_page
    lastPage.value = response.data.data.last_page
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Gagal memuat Candidate Blacklist.'
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) {
  if (page < 1 || page > lastPage.value) return
  loadBlacklists(page)
}

let searchTimeout: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadBlacklists(1), 300)
}

// ---- Add to blacklist ----
const showAddModal = ref(false)
const addForm = ref({ email: '', full_name: '', reason: '' })
const addSaving = ref(false)

function openAddModal() {
  addForm.value = { email: '', full_name: '', reason: '' }
  showAddModal.value = true
}

async function submitAdd() {
  addSaving.value = true
  actionError.value = ''
  try {
    await apiClient.post('/api/candidate-blacklists', addForm.value)
    showAddModal.value = false
    await loadBlacklists(1)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal menambahkan ke Blacklist.'
  } finally {
    addSaving.value = false
  }
}

// ---- Remove from blacklist ----
async function removeBlacklist(row: BlacklistRow) {
  if (!confirm(`Keluarkan ${row.email} dari Blacklist?`)) return
  actionError.value = ''
  try {
    await apiClient.delete(`/api/candidate-blacklists/${row.id}`)
    await loadBlacklists(currentPage.value)
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Gagal mengeluarkan dari Blacklist.'
  }
}

onMounted(() => loadBlacklists())
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">Candidate Blacklist</h1>
        <p class="text-sm text-slate-400">Email yang terdaftar di sini otomatis ditolak saat mencoba melamar lagi.</p>
      </div>
      <button class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="openAddModal">
        Tambah ke Blacklist
      </button>
    </div>

    <div v-if="actionError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ actionError }}</div>

    <div class="relative max-w-xs">
      <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300" />
      <input v-model="search" type="text" placeholder="Cari email/nama..." class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm" @input="onSearchInput" />
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</div>
    <EmptyState v-else-if="blacklists.length === 0" title="Belum ada yang di-blacklist" description="Kandidat yang di-blacklist dari sini tidak akan bisa melamar lagi." :icon="ShieldOff" />
    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 text-xs uppercase text-slate-400">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Alasan</th>
            <th class="px-4 py-3">Ditambahkan Oleh</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in blacklists" :key="row.id" class="border-b border-slate-50 align-top">
            <td class="px-4 py-3 font-medium text-slate-700">{{ row.full_name || '-' }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.email }}</td>
            <td class="px-4 py-3 max-w-xs text-slate-500">{{ row.reason }}</td>
            <td class="px-4 py-3 text-slate-500">{{ blacklisterName(row.blacklisted_by) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ row.blacklisted_at?.slice(0, 10) }}</td>
            <td class="px-4 py-3 text-right">
              <button class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs hover:bg-slate-50" @click="removeBlacklist(row)">
                Keluarkan
              </button>
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
      <BaseModal v-if="showAddModal" title="Tambah ke Blacklist" @close="showAddModal = false">
        <form class="space-y-3" @submit.prevent="submitAdd">
          <div>
            <label class="text-xs font-medium text-slate-500">Email</label>
            <input v-model="addForm.email" type="email" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Nama (opsional)</label>
            <input v-model="addForm.full_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Alasan</label>
            <textarea v-model="addForm.reason" required rows="3" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm" @click="showAddModal = false">Batal</button>
            <button type="submit" :disabled="addSaving" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {{ addSaving ? 'Menyimpan...' : 'Blacklist' }}
            </button>
          </div>
        </form>
      </BaseModal>
    </Teleport>
  </div>
</template>