<script setup lang="ts">
import { X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title: string
    maxWidth?: string
  }>(),
  { maxWidth: 'max-w-md' },
)

defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-8"
      @click.self="$emit('close')"
    >
      <div class="w-full rounded-xl bg-white shadow-xl" :class="maxWidth">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
          <button
            type="button"
            aria-label="Tutup"
            class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            @click="$emit('close')"
          >
            <X class="h-5 w-5" :stroke-width="1.75" />
          </button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>