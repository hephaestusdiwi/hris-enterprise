<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDown, ChevronRight, Users } from 'lucide-vue-next'

interface OrgNode {
  id: number
  name: string
  position: string | null
  photo_url: string | null
  children: OrgNode[]
}

const props = defineProps<{ node: OrgNode; depth?: number; expandedIds: Set<number> }>()
const emit = defineEmits<{ toggle: [id: number] }>()

const router = useRouter()
const depth = props.depth ?? 0
const expanded = computed(() => props.expandedIds.has(props.node.id))

const initials = computed(() =>
  props.node.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
)

function openDetail() {
  router.push({ name: 'employee-detail', params: { id: props.node.id } })
}

function toggleExpand() {
  if (props.node.children.length) emit('toggle', props.node.id)
}
</script>

<template>
  <div class="flex flex-col items-center">
    <!-- Card: klik = buka Employee Detail. Pakai div (bukan button) karena
         di dalamnya ada elemen interaktif lain (badge expand/collapse) —
         button di dalam button itu HTML tidak valid. -->
    <div
      role="button"
      tabindex="0"
      @click="openDetail"
      @keydown.enter="openDetail"
      class="group relative z-10 flex min-w-[190px] cursor-pointer flex-col items-center rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.06)] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(15,23,42,0.08)]"
      :class="depth === 0 ? 'ring-2 ring-primary/30' : ''"
    >
      <span
        v-if="depth === 0"
        class="absolute -top-px left-1/2 h-1 w-12 -translate-x-1/2 rounded-full bg-primary"
      />

      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-sm font-semibold text-primary-dark"
      >
        <img
          v-if="node.photo_url"
          :src="node.photo_url"
          :alt="node.name"
          class="h-full w-full object-cover"
        />
        <span v-else>
          {{ initials }}
        </span>
      </div>

      <p class="mt-2.5 text-sm font-semibold text-slate-800">{{ node.name }}</p>
      <p v-if="node.position" class="mt-0.5 text-xs text-slate-500">{{ node.position }}</p>

      <!-- Badge: klik = expand/collapse, TIDAK ikut navigasi -->
      <span
        v-if="node.children.length"
        role="button"
        tabindex="0"
        @click.stop="toggleExpand"
        @keydown.enter.stop="toggleExpand"
        class="mt-2.5 flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-400 transition-colors hover:bg-primary-soft hover:text-primary-dark"
      >
        <Users class="h-3 w-3" :stroke-width="2" />
        {{ node.children.length }}
        <ChevronDown v-if="expanded" class="h-3 w-3" :stroke-width="2" />
        <ChevronRight v-else class="h-3 w-3" :stroke-width="2" />
      </span>
    </div>

    <!-- Children -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="node.children.length && expanded" class="flex flex-col items-center">
        <div class="h-8 w-0.5 rounded-full bg-slate-200"></div>

        <div class="flex">
          <div
            v-for="(child, i) in node.children"
            :key="child.id"
            class="relative flex flex-col items-center px-5"
          >
            <span
              v-if="node.children.length > 1"
              class="absolute top-0 h-0.5 bg-slate-200"
              :class="{
                'left-1/2 right-0 rounded-r-full': i === 0,
                'left-0 right-1/2 rounded-l-full': i === node.children.length - 1,
                'left-0 right-0': i > 0 && i < node.children.length - 1,
              }"
            />
            <div class="h-8 w-0.5 rounded-full bg-slate-200"></div>
            <OrgChartNode :node="child" :depth="depth + 1" :expanded-ids="expandedIds" @toggle="emit('toggle', $event)" />
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>