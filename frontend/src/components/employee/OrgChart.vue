<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import OrgChartNode from '@/components/employee/OrgChartNode.vue'

interface OrgNode {
  id: number
  name: string
  position: string | null
  photo_url: string | null
  children: OrgNode[]
}

const roots = ref<OrgNode[]>([])
const loading = ref(true)
const errorMessage = ref('')
const expandedIds = ref<Set<number>>(new Set())

function collectAutoExpandIds(nodes: OrgNode[], depth: number, ids: Set<number>) {
  // 2 level pertama auto-expand (perilaku sebelumnya), sisanya collapsed —
  // supaya tree besar (full company, admin/hr) tidak langsung nge-render
  // semua node sekaligus.
  if (depth >= 2) return
  for (const node of nodes) {
    if (node.children.length) {
      ids.add(node.id)
      collectAutoExpandIds(node.children, depth + 1, ids)
    }
  }
}

function toggleNode(id: number) {
  const next = new Set(expandedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedIds.value = next
}

async function loadOrgChart() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/employees/org-chart')
    roots.value = response.data.data
    const ids = new Set<number>()
    collectAutoExpandIds(roots.value, 0, ids)
    expandedIds.value = ids
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
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>
    <div v-else-if="roots.length === 0" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-400">
      Belum ada struktur organisasi yang bisa ditampilkan.
    </div>
    <div
        v-else
        class="overflow-x-auto rounded-2xl border border-slate-100 bg-white p-10"
        style="background-image: radial-gradient(circle, rgb(226 232 240) 1px, transparent 1px); background-size: 24px 24px;"
        >
        <div class="flex justify-center gap-12">
            <OrgChartNode
              v-for="root in roots"
              :key="root.id"
              :node="root"
              :expanded-ids="expandedIds"
              @toggle="toggleNode"
            />
        </div>
        </div>
  </div>
</template>