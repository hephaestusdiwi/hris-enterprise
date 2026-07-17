<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import apiClient from '@/lib/axios'

interface Role {
  id: number
  name: string
}

interface UserRow {
  id: number
  name: string
  email: string
  roles: Role[]
}

const users = ref<UserRow[]>([])
const availableRoles = ref<string[]>([])
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

const form = reactive({
  id: 0,
  name: '',
  email: '',
  password: '',
  role: '',
})

function initials(name: string) {
  return name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

async function loadUsers() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await apiClient.get('/api/users')
    users.value = response.data.data.data
  } catch {
    errorMessage.value = 'Gagal memuat daftar user.'
  } finally {
    loading.value = false
  }
}

async function loadRoles() {
  try {
    const response = await apiClient.get('/api/roles')
    availableRoles.value = response.data.data
  } catch {
    availableRoles.value = []
  }
}

function openCreateModal() {
  isEditing.value = false
  formError.value = ''
  form.id = 0
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = availableRoles.value[0] ?? ''
  showModal.value = true
}

function openEditModal(user: UserRow) {
  isEditing.value = true
  formError.value = ''
  form.id = user.id
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.role = user.roles[0]?.name ?? ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  formError.value = ''

  try {
    if (isEditing.value) {
      const payload: Record<string, string> = {
        name: form.name,
        email: form.email,
        role: form.role,
      }
      if (form.password) payload.password = form.password

      await apiClient.put(`/api/users/${form.id}`, payload)
    } else {
      await apiClient.post('/api/users', {
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role,
      })
    }

    showModal.value = false
    await loadUsers()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Terjadi kesalahan, silakan coba lagi.'
  } finally {
    saving.value = false
  }
}

async function handleDelete(user: UserRow) {
  if (!confirm(`Hapus user "${user.name}"?`)) return

  try {
    await apiClient.delete(`/api/users/${user.id}`)
    await loadUsers()
  } catch {
    alert('Gagal menghapus user.')
  }
}

onMounted(() => {
  loadUsers()
  loadRoles()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Users</h1>
        <p class="mt-1 text-sm text-slate-500">Kelola akun dan hak akses pengguna sistem.</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark"
      >
        <Plus class="h-4 w-4" :stroke-width="2" />
        Tambah User
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">Memuat data...</div>
    <div v-else-if="errorMessage" class="rounded-xl bg-red-50 p-4 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/60">
            <th class="px-5 py-3 font-medium text-slate-500">Nama</th>
            <th class="px-5 py-3 font-medium text-slate-500">Email</th>
            <th class="px-5 py-3 font-medium text-slate-500">Role</th>
            <th class="px-5 py-3 font-medium text-slate-500 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
            class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50"
          >
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div
                  class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-dark"
                >
                  {{ initials(user.name) }}
                </div>
                <span class="font-medium text-slate-800">{{ user.name }}</span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-slate-500">{{ user.email }}</td>
            <td class="px-5 py-3.5">
              <span
                v-for="role in user.roles"
                :key="role.id"
                class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-medium text-primary-dark"
              >
                {{ role.name }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center justify-end gap-1">
                <button
                  @click="openEditModal(user)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil class="h-4 w-4" :stroke-width="1.75" />
                </button>
                <button
                  @click="handleDelete(user)"
                  class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                >
                  <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ isEditing ? 'Edit User' : 'Tambah User' }}
            </h2>
            <button @click="closeModal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-50">
              <X class="h-5 w-5" />
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Nama</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
              <input
                v-model="form.email"
                type="email"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Password {{ isEditing ? '(kosongkan jika tidak diubah)' : '' }}
              </label>
              <input
                v-model="form.password"
                type="password"
                :required="!isEditing"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
              <select
                v-model="form.role"
                required
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
              >
                <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
              </select>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <button
              type="submit"
              :disabled="saving"
              class="w-full rounded-xl bg-primary py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-dark disabled:opacity-50"
            >
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>