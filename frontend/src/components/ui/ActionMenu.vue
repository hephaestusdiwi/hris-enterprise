<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { MoreVertical, Pencil, Trash2 } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    showEdit?: boolean
    showDelete?: boolean
    editLabel?: string
    deleteLabel?: string
  }>(),
  { showEdit: true, showDelete: true, editLabel: 'Edit', deleteLabel: 'Hapus' },
)

const emit = defineEmits<{ edit: []; delete: [] }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)

function toggle() {
  open.value = !open.value
}
function close() {
  open.value = false
}
function onDocumentClick(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) close()
}
function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="root" class="relative inline-block text-left">
    <button
      type="button"
      aria-haspopup="true"
      :aria-expanded="open"
      class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
      @click.stop="toggle"
    >
      <MoreVertical class="h-4 w-4" :stroke-width="1.75" />
    </button>
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="absolute right-0 z-20 mt-1 w-36 origin-top-right overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
      >
        <button
          v-if="showEdit"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
          @click="close(); emit('edit')"
        >
          <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" /> {{ editLabel }}
        </button>
        <button
          v-if="showDelete"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
          @click="close(); emit('delete')"
        >
          <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" /> {{ deleteLabel }}
        </button>
      </div>
    </Transition>
  </div>
</template>