<script setup lang="ts">
import { ref } from 'vue'
import { Plus, ShieldAlert } from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import RiskClassModal from './RiskClassModal.vue'
import { formatDate } from '@/lib/bpjsFormat'
import type { RiskClassRate } from '@/types/bpjs'

defineProps<{
  rates: RiskClassRate[]
  loading: boolean
  onCreate: (payload: Record<string, unknown>) => Promise<void>
}>()

const showModal = ref(false)
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <p class="text-xs text-slate-500">
        Tarif per kelas risiko bersifat global (regulasi pemerintah, sama untuk semua company).
      </p>
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        @click="showModal = true"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Tambah Tarif
      </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <EmptyState
        v-if="!loading && rates.length === 0"
        :icon="ShieldAlert"
        title="Belum ada tarif kelas risiko"
        description="Tambahkan tarif JKK per kelas risiko sesuai ketentuan pemerintah yang berlaku."
        action-label="Tambah Tarif"
        @action="showModal = true"
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="h-11 border-b border-slate-200 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
            <th class="px-4">Kelas Risiko</th>
            <th class="px-4">Berlaku Sejak</th>
            <th class="px-4 text-right">Tarif Employer</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rates"
            :key="row.id"
            class="h-[52px] border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70"
          >
            <td class="px-4 font-medium text-slate-800">Kelas {{ row.risk_class }}</td>
            <td class="px-4 text-slate-500">{{ formatDate(row.effective_date) }}</td>
            <td class="px-4 text-right tabular-nums text-slate-600">{{ row.employer_rate_percentage }}%</td>
          </tr>
        </tbody>
      </table>
    </div>

    <RiskClassModal v-if="showModal" :on-create="onCreate" @close="showModal = false" />
  </div>
</template>