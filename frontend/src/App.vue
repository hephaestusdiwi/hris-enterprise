<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <header class="bg-slate-900 text-white shadow-md">
    <nav class="mx-auto flex max-w-7xl items-center gap-6 px-6 py-4">
      <span class="text-lg font-bold">HRIS Enterprise</span>
      <RouterLink to="/" class="text-sm text-slate-300 hover:text-white">Home</RouterLink>
      <RouterLink to="/about" class="text-sm text-slate-300 hover:text-white">About</RouterLink>
    </nav>
  </header>
  <main class="mx-auto max-w-7xl px-6 py-8">
    <RouterView />
  </main>
</template>
