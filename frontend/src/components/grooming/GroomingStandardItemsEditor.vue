<script setup lang="ts">
import { Plus, Trash2, GripVertical } from 'lucide-vue-next'

export interface GroomingItemDraft {
  name: string
  description: string
  mandatory: boolean
  requires_note_on_fail: boolean
  requires_photo: boolean
}

withDefaults(defineProps<{ showPhotoToggle?: boolean }>(), { showPhotoToggle: false })

const items = defineModel<GroomingItemDraft[]>({ required: true })

function addItem() {
  items.value = [
    ...items.value,
    { name: '', description: '', mandatory: true, requires_note_on_fail: true, requires_photo: false },
  ]
}

function removeItem(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}

function moveUp(index: number) {
  if (index === 0) return
  const arr = [...items.value]
  ;[arr[index - 1], arr[index]] = [arr[index], arr[index - 1]]
  items.value = arr
}

function moveDown(index: number) {
  if (index === items.value.length - 1) return
  const arr = [...items.value]
  ;[arr[index], arr[index + 1]] = [arr[index + 1], arr[index]]
  items.value = arr
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(item, index) in items"
      :key="index"
      class="flex items-start gap-2 rounded-xl border border-slate-200 p-3"
    >
      <div class="flex flex-col pt-2 text-slate-300">
        <button type="button" class="hover:text-slate-500" @click="moveUp(index)">
          <GripVertical class="h-4 w-4" :stroke-width="1.75" />
        </button>
      </div>

      <div class="flex-1 space-y-2">
        <input
          v-model="item.name"
          type="text"
          placeholder="Nama item (mis. Rambut rapi)"
          required
          class="w-full rounded-lg border border-slate-200 p-2 text-sm"
        />
        <input
          v-model="item.description"
          type="text"
          placeholder="Deskripsi (opsional)"
          class="w-full rounded-lg border border-slate-200 p-2 text-sm"
        />
        <div class="flex flex-wrap gap-4 text-xs text-slate-500">
          <label class="flex items-center gap-1.5">
            <input v-model="item.mandatory" type="checkbox" class="rounded border-slate-300" />
            Mandatory
          </label>
          <label class="flex items-center gap-1.5">
            <input v-model="item.requires_note_on_fail" type="checkbox" class="rounded border-slate-300" />
            Wajib catatan kalau NOT PASS
          </label>
          <label v-if="showPhotoToggle" class="flex items-center gap-1.5">
            <input v-model="item.requires_photo" type="checkbox" class="rounded border-slate-300" />
            Wajib foto
          </label>
        </div>
      </div>

      <button
        type="button"
        class="mt-1 rounded-lg p-1.5 text-slate-300 hover:bg-red-50 hover:text-red-500"
        @click="removeItem(index)"
      >
        <Trash2 class="h-4 w-4" :stroke-width="1.75" />
      </button>
    </div>

    <button
      type="button"
      class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 py-2.5 text-sm text-slate-500 hover:border-primary/40 hover:text-primary-dark"
      @click="addItem"
    >
      <Plus class="h-4 w-4" :stroke-width="1.75" />
      Tambah Item
    </button>
  </div>
</template>