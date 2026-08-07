import { ref } from 'vue'
import apiClient from '@/lib/axios'
import type { RiskClassRate } from '@/types/bpjs'

export function useRiskClassRates() {
  const riskRates = ref<RiskClassRate[]>([])
  const loading = ref(false)

  async function load() {
    loading.value = true
    try {
      const response = await apiClient.get('/api/bpjs/jkk-risk-class-rates')
      riskRates.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>) {
    await apiClient.post('/api/bpjs/jkk-risk-class-rates', payload)
    await load()
  }

  return { riskRates, loading, load, create }
}