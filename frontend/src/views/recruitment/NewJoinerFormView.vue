<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import apiClient from '@/lib/axios'

const route = useRoute()
const token = computed(() => String(route.params.token))

const loading = ref(true)
const loadError = ref('')
const candidateName = ref('')
const alreadySubmitted = ref(false)
const expired = ref(false)

const form = ref({
  gender: '',
  birth_place: '',
  birth_date: '',
  marital_status: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  national_id_number: '',
  tax_number: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_holder_name: '',
})

const submitting = ref(false)
const submitError = ref('')
const submitted = ref(false)

async function loadForm() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await apiClient.get(`/api/new-joiner-form/${token.value}`)
    const data = response.data.data

    candidateName.value = data.full_name
    alreadySubmitted.value = data.status !== 'sent'
    expired.value = !!data.expires_at && new Date(data.expires_at) < new Date()
  } catch {
    loadError.value = 'Link form tidak valid atau sudah tidak berlaku.'
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post(`/api/new-joiner-form/${token.value}`, {
      ...form.value,
      tax_number: form.value.tax_number || null,
    })
    submitted.value = true
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Gagal mengirim data. Periksa kembali isian Anda.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadForm)
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <div class="mx-auto max-w-xl px-4 py-12">
      <div v-if="loading" class="text-sm text-slate-400">Memuat...</div>

      <div v-else-if="loadError" class="rounded-2xl border border-red-100 bg-red-50 p-8 text-center">
        <h1 class="text-lg font-semibold text-red-700">Link Tidak Valid</h1>
        <p class="mt-2 text-sm text-red-600">{{ loadError }}</p>
      </div>

      <div v-else-if="expired" class="rounded-2xl border border-amber-100 bg-amber-50 p-8 text-center">
        <h1 class="text-lg font-semibold text-amber-700">Link Sudah Kedaluwarsa</h1>
        <p class="mt-2 text-sm text-amber-600">Silakan hubungi tim HR untuk mendapatkan link baru.</p>
      </div>

      <div v-else-if="alreadySubmitted && !submitted" class="rounded-2xl border border-slate-100 bg-white p-8 text-center">
        <h1 class="text-lg font-semibold text-slate-800">Data Sudah Pernah Dikirim</h1>
        <p class="mt-2 text-sm text-slate-500">Form ini sudah pernah disubmit sebelumnya. Hubungi tim HR jika ada yang perlu diperbaiki.</p>
      </div>

      <div v-else-if="submitted" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
        <h1 class="text-lg font-semibold text-emerald-700">Data Terkirim</h1>
        <p class="mt-2 text-sm text-emerald-600">Terima kasih, {{ candidateName }}. Tim HR akan memproses data Anda selanjutnya.</p>
      </div>

      <div v-else class="rounded-2xl border border-slate-100 bg-white p-8 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <h1 class="text-lg font-semibold text-slate-800">Data New Joiner</h1>
        <p class="mt-1 text-sm text-slate-400">Halo {{ candidateName }}, lengkapi data berikut sebelum proses onboarding dilanjutkan.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submitForm">
          <div v-if="submitError" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ submitError }}</div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500">Jenis Kelamin</label>
              <select v-model="form.gender" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih</option>
                <option value="male">Laki-laki</option>
                <option value="female">Perempuan</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Status Pernikahan</label>
              <select v-model="form.marital_status" required class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm">
                <option value="" disabled>Pilih</option>
                <option value="single">Belum Menikah</option>
                <option value="married">Menikah</option>
                <option value="divorced">Cerai</option>
                <option value="widowed">Janda/Duda</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500">Tempat Lahir</label>
              <input v-model="form.birth_place" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Tanggal Lahir</label>
              <input v-model="form.birth_date" required type="date" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Alamat</label>
            <textarea v-model="form.address" required rows="2" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500">Nama Kontak Darurat</label>
              <input v-model="form.emergency_contact_name" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">No. Telepon Kontak Darurat</label>
              <input v-model="form.emergency_contact_phone" required type="tel" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500">NIK (No. KTP)</label>
              <input v-model="form.national_id_number" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">NPWP (opsional)</label>
              <input v-model="form.tax_number" type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <div class="border-t border-slate-100 pt-4">
            <p class="mb-3 text-xs font-medium uppercase text-slate-400">Data Rekening</p>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-medium text-slate-500">Nama Bank</label>
                <input v-model="form.bank_name" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
              </div>
              <div>
                <label class="text-xs font-medium text-slate-500">No. Rekening</label>
                <input v-model="form.bank_account_number" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
              </div>
            </div>
            <div class="mt-4">
              <label class="text-xs font-medium text-slate-500">Nama Pemilik Rekening</label>
              <input v-model="form.bank_account_holder_name" required type="text" class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-sm" />
            </div>
          </div>

          <button type="submit" :disabled="submitting" class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-medium text-white disabled:opacity-50">
            {{ submitting ? 'Mengirim...' : 'Kirim Data' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>