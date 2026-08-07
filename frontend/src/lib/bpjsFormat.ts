import type { BpjsProgram } from '@/composables/bpjs'

export const programLabels: Record<BpjsProgram, string> = {
  kesehatan: 'BPJS Kesehatan',
  jht: 'JHT',
  jkk: 'JKK',
  jkm: 'JKM',
}

export function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function formatCurrency(value: string | null): string {
  if (value === null) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value))
}

export function isFutureOrToday(dateStr: string): boolean {
  const d = new Date(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return d >= today
}