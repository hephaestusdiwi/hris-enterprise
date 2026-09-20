<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/axios'
import { Check } from 'lucide-vue-next'

interface BranchOption {
  id: number
  name: string
}

interface StandardItem {
  id: number
  name: string
  description: string | null
  mandatory: boolean
  requires_note_on_fail: boolean
}

interface ActiveStandard {
  id: number
  name: string
  version_number: number
  active_items: StandardItem[]
}

const loading = ref(true)
const loadError = ref('')
const branches = ref<BranchOption[]>([])
const selectedBranchId = ref<number | ''>('')
const standard = ref<ActiveStandard | null>(null)
const answers = ref<Record<number, { result: 'pass' | 'not_pass'; note: string }>>({})

async function loadInitial() {
  loading.value = true
  loadError.value = ''
  try {
    const [branchRes, standardRes] = await Promise.all([
      apiClient.get('/api/grooming-store/accessible-branches'),
      apiClient.get('/api/grooming-store/active-standard'),
    ])
    branches.value = branchRes.data.data
    if (branches.value.length === 1) selectedBranchId.value = branches.value[0].id

    standard.value = standardRes.data.data
    answers.value = {}
    for (const item of standard.value?.active_items ?? []) {
      answers.value[item.id] = { result: 'pass', note: '' }
    }
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}

const overallResult = computed<'pass' | 'not_pass'>(() => {
  const items = standard.value?.active_items ?? []
  const anyFail = items.some((item) => item.mandatory && answers.value[item.id]?.result === 'not_pass')
  return anyFail ? 'not_pass' : 'pass'
})

function canSubmit(): boolean {
  if (!standard.value || !selectedBranchId.value) return false
  for (const item of standard.value.active_items) {
    const a = answers.value[item.id]
    if (!a) return false
    if (a.result === 'not_pass' && item.requires_note_on_fail && !a.note.trim()) return false
  }
  return true
}

const submitting = ref(false)
const submitError = ref('')
const submitted = ref(false)

async function submit() {
  if (!standard.value) return
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/api/grooming-store', {
      branch_id: selectedBranchId.value,
      answers: Object.entries(answers.value).map(([itemId, a]) => ({
        grooming_standard_item_id: Number(itemId),
        result: a.result,
        note: a.note || null,
      })),
    })
    submitted.value = true
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Gagal submit Grooming Store.'
  } finally {
    submitting.value = false
  }
}

function resetForm() {
  submitted.value = false
  loadInitial()
}

onMounted(loadInitial)
</script>

<template>
  <div class="mx-auto max-w-lg space-y-4">
    <h1 class="text-lg font-semibold text-slate-800">Grooming Store</h1>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="loadError" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ loadError }}</div>

    <div v-else-if="branches.length === 0" class="rounded-2xl border border-amber-100 bg-amber-50 p-6 text-center text-sm text-amber-700">
      Kamu tidak punya akses ke store manapun untuk Grooming Store.
    </div>

    <div v-else-if="submitted" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
      <Check class="mx-auto h-10 w-10 text-emerald-500" :stroke-width="1.75" />
      <p class="mt-3 text-sm font-medium text-emerald-700">Grooming Store berhasil disubmit</p>
      <p class="mt-1 text-xs text-emerald-600">Hasil: {{ overallResult === 'pass' ? 'PASS' : 'NOT PASS' }}</p>
      <button class="mt-4 rounded-xl bg-white px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm" @click="resetForm">
        Assessment Lagi
      </button>
    </div>

    <div v-else-if="standard" class="space-y-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <label class="text-xs font-medium text-slate-500">Store</label>
        <select
          v-model="selectedBranchId"
          :disabled="branches.length === 1"
          class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm disabled:bg-slate-50"
        >
          <option value="" disabled>Pilih Store</option>
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <p class="mb-3 text-xs text-slate-400">{{ standard.name }} &middot; v{{ standard.version_number }}</p>

        <div v-for="item in standard.active_items" :key="item.id" class="mb-4 last:mb-0">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-700">{{ item.name }}</p>
            <div class="flex gap-1.5">
              <button
                type="button"
                class="rounded-lg px-3 py-1 text-xs font-medium"
                :class="answers[item.id]?.result === 'pass' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500'"
                @click="answers[item.id].result = 'pass'"
              >
                PASS
              </button>
              <button
                type="button"
                class="rounded-lg px-3 py-1 text-xs font-medium"
                :class="answers[item.id]?.result === 'not_pass' ? 'bg-red-500 text-white' : 'bg-slate-100 text-slate-500'"
                @click="answers[item.id].result = 'not_pass'"
              >
                NOT PASS
              </button>
            </div>
          </div>
          <textarea
            v-if="answers[item.id]?.result === 'not_pass'"
            v-model="answers[item.id].note"
            placeholder="Catatan (wajib diisi)"
            rows="2"
            class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-xs"
          ></textarea>
        </div>
      </div>

      <div class="rounded-xl px-1 text-center text-sm font-medium" :class="overallResult === 'pass' ? 'text-emerald-600' : 'text-red-500'">
        Overall: {{ overallResult === 'pass' ? 'PASS' : 'NOT PASS' }}
      </div>

      <div v-if="submitError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ submitError }}</div>

      <button
        type="button"
        :disabled="!canSubmit() || submitting"
        class="w-full rounded-xl bg-primary py-3 text-sm font-medium text-white disabled:opacity-40"
        @click="submit"
      >
        {{ submitting ? 'Mengirim...' : 'Submit' }}
      </button>
    </div>
  </div>
</template>