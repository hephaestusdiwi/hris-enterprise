<script setup lang="ts">
import { ref, computed } from 'vue'
import { X, Calendar, Check } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

const emit = defineEmits<{
  close: []
  imported: []
}>()

type PreviewStatus = 'new' | 'update' | 'unchanged' | 'manual-locked'

interface PreviewItem {
  external_id: string
  date: string
  name: string
  type: string
  status: PreviewStatus
  existing_name: string | null
}

const step = ref<'select-year' | 'preview'>('select-year')
const currentYear = new Date().getFullYear()
const year = ref(currentYear)
const years = Array.from({ length: 5 }, (_, i) => currentYear - 1 + i)

const loadingPreview = ref(false)
const previewError = ref('')
const previewItems = ref<PreviewItem[]>([])
const selected = ref<Set<string>>(new Set())

const importing = ref(false)
const importError = ref('')

const selectedCount = computed(() => selected.value.size)

const statusLabels: Record<PreviewStatus, string> = {
  new: 'Baru',
  update: 'Update',
  unchanged: 'Tidak berubah',
  'manual-locked': 'Diedit manual',
}

const statusClasses: Record<PreviewStatus, string> = {
  new: 'bg-primary-soft text-primary-dark',
  update: 'bg-amber-50 text-amber-600',
  unchanged: 'bg-slate-100 text-slate-500',
  'manual-locked': 'bg-slate-100 text-slate-400',
}

function isSelectable(item: PreviewItem) {
  return item.status === 'new' || item.status === 'update'
}

function toggle(item: PreviewItem) {
  if (!isSelectable(item)) return
  if (selected.value.has(item.external_id)) {
    selected.value.delete(item.external_id)
  } else {
    selected.value.add(item.external_id)
  }
}

async function loadPreview() {
  loadingPreview.value = true
  previewError.value = ''
  try {
    const response = await apiClient.get('/api/holidays/import/national/preview', {
      params: { year: year.value },
    })
    previewItems.value = response.data.data
    selected.value = new Set(previewItems.value.filter(isSelectable).map((i) => i.external_id))
    step.value = 'preview'
  } catch (err: any) {
    previewError.value = err.response?.data?.message || 'Gagal mengambil data hari libur nasional.'
  } finally {
    loadingPreview.value = false
  }
}

async function handleImport() {
  importing.value = true
  importError.value = ''
  try {
    await apiClient.post('/api/holidays/import/national', {
      year: year.value,
      external_ids: Array.from(selected.value),
    })
    emit('imported')
    emit('close')
  } catch (err: any) {
    importError.value = err.response?.data?.message || 'Gagal mengimport hari libur nasional.'
  } finally {
    importing.value = false
  }
}

function backToYearSelect() {
  step.value = 'select-year'
  previewItems.value = []
  selected.value = new Set()
  previewError.value = ''
  importError.value = ''
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4">
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Import National Holidays</h2>
          <button @click="emit('close')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Step 1: pilih tahun -->
        <div v-if="step === 'select-year'" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Tahun</label>
            <select
              v-model="year"
              class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
            >
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <p class="mt-2 text-xs text-slate-400">
              Data akan ditampilkan dulu untuk direview — belum tersimpan sampai kamu menekan Import.
            </p>
          </div>

          <p v-if="previewError" class="text-sm text-red-600">{{ previewError }}</p>

          <button
            @click="loadPreview"
            :disabled="loadingPreview"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
          >
            <Calendar class="h-4 w-4" :stroke-width="2" />
            {{ loadingPreview ? 'Memuat...' : 'Tampilkan Preview' }}
          </button>
        </div>

        <!-- Step 2: preview -->
        <div v-else class="space-y-4">
          <div class="max-h-80 overflow-y-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-sm">
              <thead class="sticky top-0 bg-slate-50">
                <tr>
                  <th class="w-10 px-3 py-2"></th>
                  <th class="px-3 py-2 font-medium text-slate-500">Tanggal</th>
                  <th class="px-3 py-2 font-medium text-slate-500">Nama</th>
                  <th class="px-3 py-2 font-medium text-slate-500">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in previewItems"
                  :key="item.external_id"
                  class="border-t border-slate-50"
                  :class="isSelectable(item) ? 'cursor-pointer hover:bg-slate-50/50' : 'opacity-60'"
                  @click="toggle(item)"
                >
                  <td class="px-3 py-2">
                    <input
                      type="checkbox"
                      :checked="selected.has(item.external_id)"
                      :disabled="!isSelectable(item)"
                      class="rounded border-slate-300 text-primary focus:ring-primary"
                      @click.stop="toggle(item)"
                    />
                  </td>
                  <td class="px-3 py-2 text-slate-500">{{ item.date }}</td>
                  <td class="px-3 py-2 font-medium text-slate-800">
                    {{ item.name }}
                    <div v-if="item.status === 'update'" class="text-xs font-normal text-slate-400">
                      sebelumnya: {{ item.existing_name }}
                    </div>
                  </td>
                  <td class="px-3 py-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClasses[item.status]">
                      {{ statusLabels[item.status] }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="importError" class="text-sm text-red-600">{{ importError }}</p>

          <div class="flex items-center justify-between gap-3">
            <button
              @click="backToYearSelect"
              class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 hover:bg-slate-50"
            >
              Kembali
            </button>
            <button
              @click="handleImport"
              :disabled="importing || selectedCount === 0"
              class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              <Check class="h-4 w-4" :stroke-width="2" />
              {{ importing ? 'Mengimport...' : `Import ${selectedCount} Holiday` }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>