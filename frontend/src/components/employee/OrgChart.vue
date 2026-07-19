<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import OrgChartNode from '@/components/employee/OrgChartNode.vue'

interface OrgNode {
  id: number
  name: string
  position: string | null
  children: OrgNode[]
}

const roots = ref<OrgNode[]>([])
const loading = ref(true)
const errorMessage = ref('')

async function loadOrgChart() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employees/org-chart')
    roots.value = response.data.data
  } catch {
    errorMessage.value = 'Gagal memuat struktur organisasi.'
  } finally {
    loading.value = false
  }
}

onMounted(loadOrgChart)
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div
        v-else
        class="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-10"
        style="background-image: radial-gradient(circle, rgb(226 232 240) 1px, transparent 1px); background-size: 24px 24px;"
        >
        <div class="flex justify-center gap-12">
            <OrgChartNode v-for="root in roots" :key="root.id" :node="root" />
        </div>
        </div>
  </div>
</template>