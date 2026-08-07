<script setup lang="ts">
import { ref, onMounted } from 'vue'

import { useCompaniesAndBranches } from '@/composables/useCompaniesAndBranches'
import { useRateConfigs } from '@/composables/useRateConfigs'
import { useRiskClassRates } from '@/composables/useRiskClassRates'
import { useCompanyRegistrations } from '@/composables/useCompanyRegistrations'
import { useDefaultPolicy } from '@/composables/useDefaultPolicy'

import RateConfigTab from '@/components/bpjs/RateConfigTab.vue'
import JkkRiskClassTab from '@/components/bpjs/JkkRiskClassTab.vue'
import RegistrationTab from '@/components/bpjs/RegistrationTab.vue'
import DefaultPolicyTab from '@/components/bpjs/DefaultPolicyTab.vue'

const tabs = [
  { key: 'rate', label: 'Rate Config' },
  { key: 'jkk', label: 'JKK Risk Class' },
  { key: 'registration', label: 'NPP / Registrasi' },
  { key: 'default', label: 'Default Policy' },
] as const

const activeTab = ref<(typeof tabs)[number]['key']>('rate')

const { companies, branches, selectedCompanyId, loadCompanies, loadBranches } = useCompaniesAndBranches()
const rateConfigs = useRateConfigs(selectedCompanyId)
const riskRates = useRiskClassRates()
const registrations = useCompanyRegistrations(selectedCompanyId)
const defaultPolicy = useDefaultPolicy(selectedCompanyId)

async function onCompanyChange() {
  await Promise.all([
    loadBranches(),
    rateConfigs.load(),
    registrations.load(),
    defaultPolicy.load(),
  ])
}

onMounted(async () => {
  await loadCompanies()
  await Promise.all([
    loadBranches(),
    rateConfigs.load(),
    riskRates.load(),
    registrations.load(),
    defaultPolicy.load(),
  ])
})
</script>

<template>
  <div class="mx-auto w-full max-w-[1440px] space-y-4">
    <!-- HEADER -->
    <div class="space-y-3">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Pengaturan BPJS</h1>
        <p class="mt-0.5 text-sm text-slate-500">
          Konfigurasi tarif & aturan BPJS yang dipakai Payroll Engine tiap kali payroll dijalankan.
        </p>
      </div>

      <!-- Company selector: langsung di bawah judul, bukan pojok kanan atas -->
      <div class="flex items-center gap-2">
        <label for="bpjs-company" class="text-xs font-medium text-slate-500">Company</label>
        <select
          id="bpjs-company"
          v-model.number="selectedCompanyId"
          class="h-9 rounded-lg border border-slate-200 px-3 text-sm text-slate-700 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/30"
          @change="onCompanyChange"
        >
          <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <!-- TABS -->
    <div class="flex gap-1 border-b border-slate-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="border-b-[2.5px] px-4 py-2.5 text-sm transition-colors duration-200"
        :class="
          activeTab === tab.key
            ? 'border-primary font-semibold text-primary'
            : 'border-transparent font-medium text-slate-500 hover:text-slate-700'
        "
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- TAB CONTENT -->
    <RateConfigTab
      v-if="activeTab === 'rate'"
      :configs="rateConfigs.rateConfigs.value"
      :loading="rateConfigs.loading.value"
      :on-create="rateConfigs.create"
      :on-delete="rateConfigs.remove"
    />

    <JkkRiskClassTab
      v-if="activeTab === 'jkk'"
      :rates="riskRates.riskRates.value"
      :loading="riskRates.loading.value"
      :on-create="riskRates.create"
    />

    <RegistrationTab
      v-if="activeTab === 'registration'"
      :registrations="registrations.registrations.value"
      :branches="branches"
      :loading="registrations.loading.value"
      :on-create="registrations.create"
      :on-delete="registrations.remove"
    />

    <DefaultPolicyTab
      v-if="activeTab === 'default'"
      :form="defaultPolicy.form"
      :saving="defaultPolicy.saving.value"
      :saved="defaultPolicy.saved.value"
      :on-save="defaultPolicy.save"
    />
  </div>
</template>