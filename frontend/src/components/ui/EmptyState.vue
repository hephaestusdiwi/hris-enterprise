<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  icon: Component
  title: string
  description: string
  actionLabel?: string
}>()

defineEmits<{ action: [] }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100">
      <component :is="icon" class="h-5 w-5 text-slate-400" :stroke-width="1.75" />
    </div>
    <div class="space-y-1">
      <p class="text-sm font-medium text-slate-700">{{ title }}</p>
      <p class="max-w-xs text-sm text-slate-500">{{ description }}</p>
    </div>
    <button
      v-if="actionLabel"
      type="button"
      class="mt-1 inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
      @click="$emit('action')"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>