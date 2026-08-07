import { reactive, ref, type Ref } from 'vue'
import apiClient from '@/lib/axios'
import type { CostBearer, JhtCostBearer } from '@/types/bpjs'

export function useDefaultPolicy(selectedCompanyId: Ref<number | null>) {
  const form = reactive<{
    default_health_cost_bearer: CostBearer
    default_jht_cost_bearer: JhtCostBearer
  }>({
    default_health_cost_bearer: 'employee_borne',
    default_jht_cost_bearer: 'employee_borne',
  })
  const saving = ref(false)
  const saved = ref(false)

  async function load() {
    if (!selectedCompanyId.value) return
    const response = await apiClient.get('/api/bpjs/company-setting', {
      params: { company_id: selectedCompanyId.value },
    })
    const data = response.data.data
    form.default_health_cost_bearer = data?.default_health_cost_bearer ?? 'employee_borne'
    form.default_jht_cost_bearer = data?.default_jht_cost_bearer ?? 'employee_borne'
  }

  async function save() {
    saving.value = true
    saved.value = false
    try {
      await apiClient.put('/api/bpjs/company-setting', {
        ...form,
        company_id: selectedCompanyId.value,
      })
      saved.value = true
    } finally {
      saving.value = false
    }
  }

  return { form, saving, saved, load, save }
}