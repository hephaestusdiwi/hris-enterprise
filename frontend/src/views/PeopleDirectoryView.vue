<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Search } from 'lucide-vue-next'
import apiClient from '@/lib/axios'
import OrgChart from '@/components/employee/OrgChart.vue'

interface DirectoryEntry {
  id: number
  name: string
  photo_url: string | null
  position: string | null
  department: string | null
  manager_name: string | null
}

const activeTab = ref<'directory' | 'org-chart'>('directory')
const people = ref<DirectoryEntry[]>([])
const loading = ref(true)
const errorMessage = ref('')
const search = ref('')

const filteredPeople = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return people.value
  return people.value.filter((p) =>
    [p.name, p.position, p.department].filter(Boolean).some((field) => field!.toLowerCase().includes(q)),
  )
})

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

async function loadDirectory() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/people-directory')
    people.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat People Directory.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDirectory)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">People Directory</h1>
      <p class="mt-1 text-sm text-slate-500">Semua rekan kerja Anda di perusahaan.</p>
    </div>

    <div class="flex gap-1 border-b border-slate-200">
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium"
        :class="activeTab === 'directory' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = 'directory'"
      >
        Directory
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium"
        :class="activeTab === 'org-chart' ? 'border-b-2 border-primary text-primary' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = 'org-chart'"
      >
        Org Chart
      </button>
    </div>

    <template v-if="activeTab === 'directory'">
      <div class="relative max-w-sm">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama, posisi, atau department..."
          class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none"
        />
      </div>

      <p v-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</p>
      <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>
      <div v-else-if="filteredPeople.length === 0" class="rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-400">
        Tidak ada yang cocok.
      </div>
      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="person in filteredPeople"
          :key="person.id"
          :to="`/people-directory/${person.id}`"
          class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(15,23,42,0.08)]"
        >
          <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-sm font-semibold text-primary-dark">
            <img v-if="person.photo_url" :src="person.photo_url" :alt="person.name" class="h-full w-full object-cover" />
            <span v-else>{{ initials(person.name) }}</span>
          </div>
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900">{{ person.name }}</p>
            <p class="truncate text-xs text-slate-500">{{ person.position ?? '-' }}</p>
            <p class="truncate text-xs text-slate-400">
              {{ person.department ?? '-' }}
              <span v-if="person.manager_name"> &middot; Manager: {{ person.manager_name }}</span>
            </p>
          </div>
        </RouterLink>
      </div>
    </template>

    <!-- detail-route-name diarahin ke PersonProfileView (read-only, terbuka
         buat semua orang) -- BUKAN 'employee-detail' (halaman admin) yang
         bakal 403 buat employee biasa. -->
    <OrgChart v-else api-url="/api/company-org-chart" detail-route-name="people-directory-profile" />
  </div>
</template>