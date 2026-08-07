import { ref, type Ref } from 'vue'
import apiClient from '@/lib/axios'
import type { RateConfig } from '@/types/bpjs'

export function useRateConfigs(selectedCompanyId: Ref<number | null>) {
  const rateConfigs = ref<RateConfig[]>([])
  const loading = ref(false)

  async function load() {
    if (!selectedCompanyId.value) return
    loading.value = true
    try {
      const response = await apiClient.get('/api/bpjs/rate-configs', {
        params: { company_id: selectedCompanyId.value },
      })
      rateConfigs.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>) {
    await apiClient.post('/api/bpjs/rate-configs', {
      ...payload,
      company_id: selectedCompanyId.value,
    })
    await load()
  }

  async function remove(id: number) {
    await apiClient.delete(`/api/bpjs/rate-configs/${id}`)
    await load()
  }

  return { rateConfigs, loading, load, create, remove }
}