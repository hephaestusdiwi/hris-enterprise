<script setup lang="ts">
import { ref } from 'vue'
import { Plus, Building2 } from 'lucide-vue-next'
import ActionMenu from '@/components/ui/ActionMenu.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import RegistrationModal from './RegistrationModal.vue'
import { formatDate, isFutureOrToday } from '@/lib/bpjsFormat'
import type { Registration, Branch } from '@/types/bpjs'

const props = defineProps<{
  registrations: Registration[]
  branches: Branch[]
  loading: boolean
  onCreate: (payload: Record<string, unknown>) => Promise<void>
  onDelete: (id: number) => Promise<void>
}>()

const showModal = ref(false)

async function handleDelete(reg: Registration) {
  if (!confirm('Hapus registrasi NPP ini?')) return
  try {
    await props.onDelete(reg.id)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus.')
  }
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex justify-end">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        @click="showModal = true"
      >
        <Plus class="h-4 w-4" :stroke-width="2" /> Tambah NPP
      </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <EmptyState
        v-if="!loading && registrations.length === 0"
        :icon="Building2"
        title="Belum ada NPP terdaftar"
        description="Tambahkan nomor pendaftaran perusahaan (NPP) untuk company ini agar payroll bisa memetakan kelas risiko dengan benar."
        action-label="Tambah NPP"
        @action="showModal = true"
      />
      <table v-else class="w-full text-left text-sm">
        <thead>
          <tr class="h-11 border-b border-slate-200 bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
            <th class="px-4">NPP</th>
            <th class="px-4">Office Location</th>
            <th class="px-4 text-center">Kelas Risiko</th>
            <th class="px-4">Berlaku Sejak</th>
            <th class="w-12 px-4"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in registrations"
            :key="row.id"
            class="h-[52px] border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70"
          >
            <td class="px-4">
              <p class="font-medium text-slate-800">{{ row.npp_number }}</p>
              <p v-if="row.label" class="text-xs text-slate-400">{{ row.label }}</p>
            </td>
            <td class="px-4 text-slate-500">{{ row.branch?.name ?? 'Company-wide' }}</td>
            <td class="px-4 text-center tabular-nums text-slate-600">Kelas {{ row.risk_class }}</td>
            <td class="px-4 text-slate-500">{{ formatDate(row.effective_date) }}</td>
            <td class="px-4 text-right">
              <ActionMenu
                v-if="isFutureOrToday(row.effective_date)"
                :show-edit="false"
                @delete="handleDelete(row)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <RegistrationModal
      v-if="showModal"
      :branches="branches"
      :on-create="onCreate"
      @close="showModal = false"
    />
  </div>
</template>