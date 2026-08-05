<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, AlertTriangle, CheckCircle2 } from 'lucide-vue-next'

interface Props {
  modelValue: string
  label: string
  required?: boolean
  min?: string
  max?: string
  /**
   * age       -> "32 tahun 4 bulan" (buat birth_date)
   * tenure    -> "Masa kerja 1 tahun 8 bulan" (buat join_date)
   * countdown -> "Berakhir dalam 45 hari" dengan warna sesuai urgensi (buat contract/probation end)
   */
  helper?: 'age' | 'tenure' | 'countdown' | 'none'
  countdownWarnDays?: number
  countdownDangerDays?: number
  disabled?: boolean
  hint?: string
}

const props = withDefaults(defineProps<Props>(), {
  required: false,
  min: undefined,
  max: undefined,
  helper: 'none',
  countdownWarnDays: 90,
  countdownDangerDays: 30,
  disabled: false,
  hint: undefined,
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

function onInput(e: Event) {
  emit('update:modelValue', (e.target as HTMLInputElement).value)
}

function diffYearsMonths(from: Date, to: Date) {
  let years = to.getFullYear() - from.getFullYear()
  let months = to.getMonth() - from.getMonth()
  if (to.getDate() < from.getDate()) months -= 1
  if (months < 0) {
    years -= 1
    months += 12
  }
  return { years, months }
}

type Tone = 'neutral' | 'ok' | 'warn' | 'danger'

const helperInfo = computed<{ text: string; tone: Tone } | null>(() => {
  if (!props.modelValue || props.helper === 'none') return null
  const target = new Date(`${props.modelValue}T00:00:00`)
  if (Number.isNaN(target.getTime())) return null
  const now = new Date()

  if (props.helper === 'age') {
    const { years, months } = diffYearsMonths(target, now)
    if (years < 0) return { text: 'Tanggal belum valid', tone: 'danger' }
    return { text: `${years} tahun ${months} bulan`, tone: 'neutral' }
  }

  if (props.helper === 'tenure') {
    if (target > now) return { text: 'Belum dimulai', tone: 'neutral' }
    const { years, months } = diffYearsMonths(target, now)
    const parts: string[] = []
    if (years > 0) parts.push(`${years} tahun`)
    parts.push(`${months} bulan`)
    return { text: `Masa kerja ${parts.join(' ')}`, tone: 'neutral' }
  }

  if (props.helper === 'countdown') {
    const diffDays = Math.ceil((target.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
    if (diffDays < 0) return { text: `Sudah lewat ${Math.abs(diffDays)} hari`, tone: 'danger' }
    if (diffDays <= props.countdownDangerDays) return { text: `Berakhir dalam ${diffDays} hari`, tone: 'danger' }
    if (diffDays <= props.countdownWarnDays) return { text: `Berakhir dalam ${diffDays} hari`, tone: 'warn' }
    return { text: `Berakhir dalam ${diffDays} hari`, tone: 'ok' }
  }

  return null
})

const toneClass = computed(() => {
  switch (helperInfo.value?.tone) {
    case 'danger':
      return 'bg-red-50 text-red-600'
    case 'warn':
      return 'bg-amber-50 text-amber-600'
    case 'ok':
      return 'bg-emerald-50 text-emerald-600'
    default:
      return 'bg-slate-50 text-slate-500'
  }
})
</script>

<template>
  <div>
    <label class="mb-1 flex items-center gap-1 text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <div class="relative">
      <Calendar class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" :stroke-width="1.75" />
      <input
        :value="modelValue"
        type="date"
        :required="required"
        :min="min"
        :max="max"
        :disabled="disabled"
        class="w-full rounded-xl border border-slate-200 py-2 pl-9 pr-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
        @input="onInput"
      />
    </div>
    <p v-if="helperInfo" class="mt-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium" :class="toneClass">
      <AlertTriangle v-if="helperInfo.tone === 'danger' || helperInfo.tone === 'warn'" class="h-3 w-3" :stroke-width="2" />
      <CheckCircle2 v-else-if="helperInfo.tone === 'ok'" class="h-3 w-3" :stroke-width="2" />
      {{ helperInfo.text }}
    </p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-slate-400">{{ hint }}</p>
  </div>
</template>