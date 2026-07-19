<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Mail, Lock } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const isLoading = ref(false)

async function handleSubmit() {
  errorMessage.value = ''
  isLoading.value = true

  try {
    await authStore.login(email.value, password.value)
    router.push('/')
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-stretch bg-slate-100">
    <!-- Left: form -->
    <div class="flex w-full flex-col justify-between px-10 py-10 sm:px-16 lg:w-[45%] lg:px-20">
      <div>
        <span class="text-lg font-bold tracking-tight text-slate-900">Mindway HRIS</span>
      </div>

      <div class="mx-auto w-full max-w-sm">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Selamat Datang</h1>
        <p class="mt-1 text-sm text-slate-500">Masuk untuk mengakses dashboard HRIS Anda.</p>

        <form @submit.prevent="handleSubmit" class="mt-8 space-y-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
            <div class="flex items-center gap-2.5 rounded-2xl border border-slate-200 px-4 py-3 transition-colors focus-within:border-primary">
              <Mail class="h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
              <input
                v-model="email"
                type="email"
                required
                placeholder="nama@perusahaan.com"
                class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <div class="flex items-center gap-2.5 rounded-2xl border border-slate-200 px-4 py-3 transition-colors focus-within:border-primary">
              <Lock class="h-4 w-4 shrink-0 text-slate-400" :stroke-width="1.75" />
              <input
                v-model="password"
                type="password"
                required
                placeholder="••••••••"
                class="w-full bg-transparent text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
              />
            </div>
          </div>

          <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full rounded-2xl bg-primary py-3 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
          >
            {{ isLoading ? 'Memproses...' : 'Masuk' }}
          </button>
        </form>
      </div>

      <p class="mx-auto max-w-sm text-center text-xs leading-relaxed text-slate-400">
        Kelola data karyawan, approval cuti, dan payroll dalam satu platform HRIS terintegrasi.
      </p>
    </div>

    <!-- Right: visual -->
    <div class="relative hidden overflow-hidden lg:block lg:w-[55%]">
      <div class="absolute inset-0 bg-gradient-to-br from-primary-soft via-primary/20 to-primary/50"></div>

      <!-- decorative dashes -->
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-[12%] top-[15%] h-16 w-px bg-slate-400"></div>
        <div class="absolute left-[85%] top-[10%] h-20 w-px bg-slate-400"></div>
        <div class="absolute left-[8%] top-[70%] h-14 w-px bg-slate-400"></div>
        <div class="absolute left-[90%] top-[65%] h-16 w-px bg-slate-400"></div>
        <div class="absolute left-[75%] top-[85%] h-12 w-px bg-slate-400"></div>
      </div>

      <div class="relative flex h-full items-center justify-center">
        <div class="absolute h-72 w-72 rounded-full bg-white/40 blur-3xl"></div>

        <!-- Placeholder building illustration — ganti ke <img> kalau sudah ada foto asli -->
        <svg viewBox="0 0 220 240" class="relative h-64 w-64 drop-shadow-xl">
          <rect x="40" y="60" width="140" height="170" rx="10" class="fill-primary-dark" />
          <rect x="55" y="80" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="98" y="80" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="141" y="80" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="55" y="120" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="98" y="120" width="24" height="24" rx="4" class="fill-white/50" />
          <rect x="141" y="120" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="55" y="160" width="24" height="24" rx="4" class="fill-white/50" />
          <rect x="98" y="160" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="141" y="160" width="24" height="24" rx="4" class="fill-white/85" />
          <rect x="90" y="200" width="40" height="30" rx="3" class="fill-white/95" />
          <rect x="20" y="223" width="180" height="8" rx="4" class="fill-black/10" />
        </svg>
      </div>
    </div>
  </div>
</template>