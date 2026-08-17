<script setup lang="ts">
import { X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title: string
    maxWidth?: string
  }>(),
  {
    maxWidth: 'max-w-xl',
  },
)

defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-6 sm:px-6"
      @click.self="$emit('close')"
    >
      <div
        class="flex max-h-[calc(100vh-3rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5"
        :class="maxWidth"
      >
        <!-- Header -->
        <div
          class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4"
        >
          <div class="min-w-0">
            <h2 class="truncate text-base font-semibold text-slate-900">
              {{ title }}
            </h2>
          </div>

          <button
            type="button"
            aria-label="Tutup"
            class="ml-4 shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
            @click="$emit('close')"
          >
            <X class="h-5 w-5" :stroke-width="1.75" />
          </button>
        </div>

        <!-- Body -->
        <div class="min-h-0 flex-1 overflow-y-auto">
          <div class="px-6 py-5">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>