import { ref } from 'vue'
import apiClient from '@/lib/axios'
import type { Company, Branch } from '@/types/bpjs'

export function useCompaniesAndBranches() {
  const companies = ref<Company[]>([])
  const branches = ref<Branch[]>([])
  const selectedCompanyId = ref<number | null>(null)

  async function loadCompanies() {
    const response = await apiClient.get('/api/companies')
    companies.value = response.data.data.data ?? response.data.data
    selectedCompanyId.value = companies.value[0]?.id ?? null
  }

  async function loadBranches() {
    if (!selectedCompanyId.value) return
    const response = await apiClient.get('/api/branches', {
      params: { company_id: selectedCompanyId.value, per_page: 100 },
    })
    branches.value = response.data.data.data ?? response.data.data
  }

  return { companies, branches, selectedCompanyId, loadCompanies, loadBranches }
}