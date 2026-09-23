<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface PersonProfile {
  id: number
  name: string
  photo_url: string | null
  position: string | null
  department: string | null
  manager_name: string | null
}

const route = useRoute()
const personId = computed(() => Number(route.params.id))

const person = ref<PersonProfile | null>(null)
const loading = ref(true)
const errorMessage = ref('')

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

async function loadPerson(id: number) {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get(`/api/people-directory/${id}`)
    person.value = response.data.data
  } catch {
    errorMessage.value = 'Data tidak ditemukan.'
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPerson(personId.value))
watch(personId, (id) => {
  if (!Number.isNaN(id)) loadPerson(id)
})
</script>

<template>
  <div class="space-y-6">
    <RouterLink to="/people-directory" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
      <ArrowLeft class="h-4 w-4" />
      Kembali ke People Directory
    </RouterLink>

    <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>
    <p v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ errorMessage }}</p>

    <div v-else-if="person" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <div class="flex items-center gap-4">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-lg font-semibold text-primary-dark">
          <img v-if="person.photo_url" :src="person.photo_url" :alt="person.name" class="h-full w-full object-cover" />
          <span v-else>{{ initials(person.name) }}</span>
        </div>
        <div>
          <p class="text-lg font-semibold text-slate-900">{{ person.name }}</p>
          <p class="text-sm text-slate-500">{{ person.position ?? '-' }}</p>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-2 gap-4 text-sm">
        <div>
          <p class="text-slate-500">Department</p>
          <p class="font-medium text-slate-900">{{ person.department ?? '-' }}</p>
        </div>
        <div>
          <p class="text-slate-500">Manager</p>
          <p class="font-medium text-slate-900">{{ person.manager_name ?? '-' }}</p>
        </div>
      </div>

      <p class="mt-6 text-xs text-slate-400">
        Ini profile ringkas (People Directory). Data lengkap hanya bisa diakses HR/Admin lewat halaman Employee Detail.
      </p>
    </div>
  </div>
</template>