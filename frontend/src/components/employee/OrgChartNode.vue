<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  ChevronDown,
  ChevronRight,
  Users,
} from 'lucide-vue-next'

interface OrgNode {
  id: number
  name: string
  position: string | null
  photo_url: string | null
  children: OrgNode[]
}

// -----------------------------------------------------------------------------
// Props
// -----------------------------------------------------------------------------
//
// detailRouteName:
// - 'employee-detail'              → behavior admin lama
// - 'people-directory-profile'     → profile read-only/self-service
// - null                           → card tidak clickable
//
// expandedIds tetap dikelola oleh OrgChart.vue sebagai source of truth.
// -----------------------------------------------------------------------------

const props = withDefaults(
  defineProps<{
    node: OrgNode
    depth?: number
    expandedIds: Set<number>
    detailRouteName?: string | null
  }>(),
  {
    depth: 0,
    detailRouteName: 'employee-detail',
  },
)

const emit = defineEmits<{
  toggle: [id: number]
}>()

const router = useRouter()

// -----------------------------------------------------------------------------
// Computed
// -----------------------------------------------------------------------------

const depth = computed(() => props.depth ?? 0)

const expanded = computed(() =>
  props.expandedIds.has(props.node.id),
)

const hasChildren = computed(
  () => props.node.children.length > 0,
)

const childCount = computed(
  () => props.node.children.length,
)

const clickable = computed(
  () => props.detailRouteName !== null,
)

const initials = computed(() =>
  props.node.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase() ?? '')
    .join(''),
)

// -----------------------------------------------------------------------------
// Methods
// -----------------------------------------------------------------------------

function openDetail() {
  if (!props.detailRouteName) {
    return
  }

  router.push({
    name: props.detailRouteName,
    params: {
      id: props.node.id,
    },
  })
}

function handleCardKeydown(
  event: KeyboardEvent,
) {
  if (!clickable.value) {
    return
  }

  if (
    event.key === 'Enter' ||
    event.key === ' '
  ) {
    event.preventDefault()
    openDetail()
  }
}

function toggleExpand() {
  if (!hasChildren.value) {
    return
  }

  emit('toggle', props.node.id)
}

function handleExpandKeydown(
  event: KeyboardEvent,
) {
  if (
    event.key === 'Enter' ||
    event.key === ' '
  ) {
    event.preventDefault()
    event.stopPropagation()
    toggleExpand()
  }
}
</script>

<template>
  <div
    class="flex flex-col items-center"
  >
    <!-- ================================================================= -->
    <!-- EMPLOYEE CARD                                                     -->
    <!-- ================================================================= -->

    <div
      :role="
        clickable
          ? 'button'
          : undefined
      "
      :tabindex="
        clickable
          ? 0
          : undefined
      "
      class="group relative z-10 flex min-w-[190px] flex-col items-center rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-[0_1px_3px_rgba(15,23,42,0.05)] transition-all duration-200"
      :class="[
        clickable
          ? 'cursor-pointer hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-[0_8px_24px_rgba(15,23,42,0.08)]'
          : '',
        depth === 0
          ? 'ring-2 ring-[#BD2028]/15'
          : '',
      ]"
      @click="openDetail"
      @keydown="handleCardKeydown"
    >
      <!-- Root accent -->
      <span
        v-if="depth === 0"
        class="absolute -top-px left-1/2 h-1 w-12 -translate-x-1/2 rounded-full bg-[#BD2028]"
      />

      <!-- Hover accent -->
      <span
        v-if="clickable"
        class="pointer-events-none absolute inset-x-6 bottom-0 h-px origin-center scale-x-0 bg-[#BD2028] transition-transform duration-200 group-hover:scale-x-100"
      />

      <!-- Avatar -->
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#FCEBED] text-sm font-semibold text-[#9F1B22] ring-4 ring-white"
      >
        <img
          v-if="node.photo_url"
          :src="node.photo_url"
          :alt="node.name"
          class="h-full w-full object-cover"
          loading="lazy"
        />

        <span
          v-else
          aria-hidden="true"
        >
          {{ initials }}
        </span>
      </div>

      <!-- Name -->
      <p
        class="mt-3 max-w-[170px] truncate text-center text-sm font-semibold leading-5 text-slate-800"
        :title="node.name"
      >
        {{ node.name }}
      </p>

      <!-- Position -->
      <p
        v-if="node.position"
        class="mt-0.5 max-w-[170px] truncate text-center text-xs leading-5 text-slate-500"
        :title="node.position"
      >
        {{ node.position }}
      </p>

      <!-- No position fallback -->
      <p
        v-else
        class="mt-0.5 text-xs leading-5 text-slate-300"
      >
        -
      </p>

      <!-- ================================================================= -->
      <!-- EXPAND / COLLAPSE BADGE                                           -->
      <!-- ================================================================= -->

      <span
        v-if="hasChildren"
        role="button"
        tabindex="0"
        :aria-expanded="expanded"
        :aria-label="
          expanded
            ? `Collapse ${childCount} direct reports`
            : `Expand ${childCount} direct reports`
        "
        class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-slate-100 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-400 transition-all duration-150 hover:border-[#FCEBED] hover:bg-[#FCEBED] hover:text-[#9F1B22] focus:outline-none focus:ring-2 focus:ring-[#BD2028]/20"
        @click.stop="toggleExpand"
        @keydown.stop="handleExpandKeydown"
      >
        <Users
          class="h-3.5 w-3.5"
          :stroke-width="2"
        />

        <span>
          {{ childCount }}
        </span>

        <ChevronDown
          v-if="expanded"
          class="h-3.5 w-3.5"
          :stroke-width="2"
        />

        <ChevronRight
          v-else
          class="h-3.5 w-3.5"
          :stroke-width="2"
        />
      </span>
    </div>

    <!-- ================================================================= -->
    <!-- CHILDREN                                                          -->
    <!-- ================================================================= -->

    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="hasChildren && expanded"
        class="flex flex-col items-center"
      >
        <!-- Vertical connector from parent -->
        <div
          class="h-8 w-0.5 rounded-full bg-slate-200"
        />

        <!-- Children row -->
        <div
          class="flex items-start"
        >
          <div
            v-for="(child, index) in node.children"
            :key="child.id"
            class="relative flex flex-col items-center px-5"
          >
            <!-- ========================================================= -->
            <!-- Horizontal connector                                      -->
            <!-- ========================================================= -->

            <span
              v-if="node.children.length > 1"
              class="absolute top-0 h-0.5 bg-slate-200"
              :class="{
                'left-1/2 right-0 rounded-r-full':
                  index === 0,

                'left-0 right-1/2 rounded-l-full':
                  index ===
                  node.children.length - 1,

                'left-0 right-0':
                  index > 0 &&
                  index <
                    node.children.length - 1,
              }"
            />

            <!-- Vertical connector to child -->
            <div
              class="h-8 w-0.5 rounded-full bg-slate-200"
            />

            <!-- Recursive child -->
            <OrgChartNode
              :node="child"
              :depth="depth + 1"
              :expanded-ids="expandedIds"
              :detail-route-name="detailRouteName"
              @toggle="emit('toggle', $event)"
            />
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>