import { ref, type Ref } from 'vue'
import apiClient from '@/lib/axios'
import type { Registration } from '@/types/bpjs'

export function useCompanyRegistrations(selectedCompanyId: Ref<number | null>) {
  const registrations = ref<Registration[]>([])
  const loading = ref(false)

  async function load() {
    if (!selectedCompanyId.value) return
    loading.value = true
    try {
      const response = await apiClient.get('/api/bpjs/company-registrations', {
        params: { company_id: selectedCompanyId.value },
      })
      registrations.value = response.data.data
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, unknown>) {
    await apiClient.post('/api/bpjs/company-registrations', {
      ...payload,
      company_id: selectedCompanyId.value,
    })
    await load()
  }

  async function remove(id: number) {
    await apiClient.delete(`/api/bpjs/company-registrations/${id}`)
    await load()
  }

  return { registrations, loading, load, create, remove }
}