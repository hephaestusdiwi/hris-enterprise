export interface Company {
  id: number
  name: string
}

export interface Branch {
  id: number
  name: string
}

export type BpjsProgram = 'kesehatan' | 'jht' | 'jkk' | 'jkm'

export interface RateConfig {
  id: number
  company_id: number
  program: BpjsProgram
  effective_date: string
  is_active: boolean
  employee_rate_percentage: string | null
  employer_rate_percentage: string | null
  wage_base_cap: string | null
  notes: string | null
}

export interface RiskClassRate {
  id: number
  risk_class: number
  effective_date: string
  employer_rate_percentage: string
}

export interface Registration {
  id: number
  company_id: number
  branch_id: number | null
  npp_number: string
  risk_class: number
  label: string | null
  effective_date: string
  branch: Branch | null
}

export type CostBearer = 'employee_borne' | 'company_borne'
export type JhtCostBearer = CostBearer | 'not_participating'

export interface DefaultPolicySetting {
  default_health_cost_bearer: CostBearer
  default_jht_cost_bearer: JhtCostBearer
}