<script setup lang="ts">
import {
  ref,
  onMounted,
  computed,
  onBeforeUnmount,
} from 'vue'
import apiClient from '@/lib/axios'
import OrgChartNode from '@/components/employee/OrgChartNode.vue'
import {
  Network,
  RefreshCw,
  ZoomIn,
  ZoomOut,
  Maximize2,
  Minimize2,
  Expand,
  Shrink,
  ChevronRight,
  Users,
} from 'lucide-vue-next'

/* ==========================================================================
 * TYPES
 * ========================================================================== */

interface OrgNode {
  id: number
  name: string
  position: string | null
  photo_url: string | null
  children: OrgNode[]
}

/* ==========================================================================
 * PROPS
 * ========================================================================== */

/*
 * Default:
 * - Admin Employee List       -> /api/employees/org-chart
 * - People Directory          -> bisa pass endpoint lain
 *
 * detailRouteName:
 * - 'employee-detail' -> klik node membuka detail employee
 * - null              -> node tidak melakukan navigasi
 */
const props = withDefaults(
  defineProps<{
    apiUrl?: string
    detailRouteName?: string | null
  }>(),
  {
    apiUrl: '/api/employees/org-chart',
    detailRouteName: 'employee-detail',
  },
)

/* ==========================================================================
 * CONSTANTS
 * ========================================================================== */

const PRIMARY = '#BD2028'
const PRIMARY_DARK = '#9F1B22'
const PRIMARY_SOFT = '#FCEBED'

/* ==========================================================================
 * STATE
 * ========================================================================== */

const roots = ref<OrgNode[]>([])
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')

const expandedIds = ref<Set<number>>(
  new Set(),
)

const zoom = ref(100)

const isFullscreen = ref(false)

/* ==========================================================================
 * TREE HELPERS
 * ========================================================================== */

/**
 * Auto expand 2 level pertama.
 *
 * Ini mempertahankan behavior lama:
 * root + level berikutnya dibuka,
 * sementara tree yang lebih dalam tetap collapsed.
 */
function collectAutoExpandIds(
  nodes: OrgNode[],
  depth: number,
  ids: Set<number>,
) {
  if (depth >= 2) {
    return
  }

  for (const node of nodes) {
    if (node.children.length) {
      ids.add(node.id)

      collectAutoExpandIds(
        node.children,
        depth + 1,
        ids,
      )
    }
  }
}

/**
 * Collect seluruh node yang punya children.
 * Dipakai untuk Expand All.
 */
function collectAllExpandableIds(
  nodes: OrgNode[],
  ids: Set<number>,
) {
  for (const node of nodes) {
    if (node.children.length) {
      ids.add(node.id)

      collectAllExpandableIds(
        node.children,
        ids,
      )
    }
  }
}

/* ==========================================================================
 * EXPAND / COLLAPSE
 * ========================================================================== */

function toggleNode(id: number) {
  const next = new Set(
    expandedIds.value,
  )

  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }

  expandedIds.value = next
}

function expandAll() {
  const ids = new Set<number>()

  collectAllExpandableIds(
    roots.value,
    ids,
  )

  expandedIds.value = ids
}

function collapseAll() {
  expandedIds.value = new Set()
}

function resetTree() {
  const ids = new Set<number>()

  collectAutoExpandIds(
    roots.value,
    0,
    ids,
  )

  expandedIds.value = ids
  zoom.value = 100
}

/* ==========================================================================
 * ZOOM
 * ========================================================================== */

function zoomIn() {
  zoom.value = Math.min(
    zoom.value + 10,
    150,
  )
}

function zoomOut() {
  zoom.value = Math.max(
    zoom.value - 10,
    60,
  )
}

function resetZoom() {
  zoom.value = 100
}

/* ==========================================================================
 * FULLSCREEN
 * ========================================================================== */

function toggleFullscreen() {
  isFullscreen.value =
    !isFullscreen.value
}

function handleEscape(event: KeyboardEvent) {
  if (
    event.key === 'Escape' &&
    isFullscreen.value
  ) {
    isFullscreen.value = false
  }
}

/* ==========================================================================
 * STATS
 * ========================================================================== */

const totalPeople = computed(() => {
  let count = 0

  function walk(nodes: OrgNode[]) {
    for (const node of nodes) {
      count++

      if (node.children.length) {
        walk(node.children)
      }
    }
  }

  walk(roots.value)

  return count
})

const totalManagers = computed(() => {
  let count = 0

  function walk(nodes: OrgNode[]) {
    for (const node of nodes) {
      if (node.children.length > 0) {
        count++
      }

      if (node.children.length) {
        walk(node.children)
      }
    }
  }

  walk(roots.value)

  return count
})

const totalRoots = computed(
  () => roots.value.length,
)

const totalLevels = computed(() => {
  let deepest = 0

  function walk(
    nodes: OrgNode[],
    depth: number,
  ) {
    deepest = Math.max(
      deepest,
      depth,
    )

    for (const node of nodes) {
      if (node.children.length) {
        walk(
          node.children,
          depth + 1,
        )
      }
    }
  }

  walk(roots.value, 1)

  return deepest
})

const expandedCount = computed(
  () => expandedIds.value.size,
)

/* ==========================================================================
 * LOAD
 * ========================================================================== */

async function loadOrgChart(
  showInitialLoading = true,
) {
  if (showInitialLoading) {
    loading.value = true
  } else {
    refreshing.value = true
  }

  errorMessage.value = ''

  try {
    const response =
      await apiClient.get(
        props.apiUrl,
      )

    roots.value =
      response.data.data ?? []

    resetTree()
  } catch {
    errorMessage.value =
      'Gagal memuat struktur organisasi.'
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

/* ==========================================================================
 * LIFECYCLE
 * ========================================================================== */

onMounted(() => {
  loadOrgChart()
  window.addEventListener(
    'keydown',
    handleEscape,
  )
})

onBeforeUnmount(() => {
  window.removeEventListener(
    'keydown',
    handleEscape,
  )
})
</script>

<template>
  <div
    class="space-y-4"
    :class="
      isFullscreen
        ? 'fixed inset-0 z-50 flex flex-col bg-slate-50 p-4 sm:p-5 lg:p-6'
        : ''
    "
  >
    <!-- ================================================================== -->
    <!-- HEADER                                                             -->
    <!-- ================================================================== -->

    <section
      class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <!-- Main Header -->
      <div
        class="flex flex-col gap-4 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between"
      >
        <!-- Title -->
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCEBED] text-[#BD2028]"
          >
            <Network class="h-5 w-5" />
          </div>

          <div>
            <div
              class="flex flex-wrap items-center gap-2"
            >
              <h2
                class="text-sm font-semibold text-slate-800"
              >
                Organization Chart
              </h2>

              <span
                class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500"
              >
                {{ totalPeople }} People
              </span>
            </div>

            <p
              class="mt-0.5 text-xs text-slate-400"
            >
              Struktur organisasi dan hubungan reporting antar employee.
            </p>
          </div>
        </div>

        <!-- Stats -->
        <div
          class="flex items-center gap-5"
        >
          <div>
            <p
              class="text-[10px] font-medium uppercase tracking-wide text-slate-400"
            >
              People
            </p>

            <p
              class="mt-0.5 text-sm font-semibold text-slate-800"
            >
              {{ totalPeople }}
            </p>
          </div>

          <div
            class="h-7 w-px bg-slate-200"
          />

          <div>
            <p
              class="text-[10px] font-medium uppercase tracking-wide text-slate-400"
            >
              Managers
            </p>

            <p
              class="mt-0.5 text-sm font-semibold text-slate-800"
            >
              {{ totalManagers }}
            </p>
          </div>

          <div
            class="hidden h-7 w-px bg-slate-200 sm:block"
          />

          <div
            class="hidden sm:block"
          >
            <p
              class="text-[10px] font-medium uppercase tracking-wide text-slate-400"
            >
              Levels
            </p>

            <p
              class="mt-0.5 text-sm font-semibold text-slate-800"
            >
              {{ totalLevels }}
            </p>
          </div>
        </div>
      </div>

      <!-- ================================================================= -->
      <!-- TOOLBAR                                                           -->
      <!-- ================================================================= -->

      <div
        class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5"
      >
        <!-- Left -->
        <div
          class="flex items-center gap-2"
        >
          <span
            class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200"
          >
            <Users class="h-3.5 w-3.5" />
            {{ expandedCount }}
            expanded
          </span>

          <span
            class="text-[11px] text-slate-400"
          >
            Klik employee untuk membuka profile
            <template
              v-if="
                !props.detailRouteName
              "
            >
              · mode view only
            </template>
          </span>
        </div>

        <!-- Controls -->
        <div
          class="flex flex-wrap items-center gap-2"
        >
          <!-- Reset -->
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-800"
            @click="resetTree"
          >
            Reset View
          </button>

          <!-- Expand -->
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-800"
            @click="expandAll"
          >
            <Expand class="h-3.5 w-3.5" />
            Expand All
          </button>

          <!-- Collapse -->
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-800"
            @click="collapseAll"
          >
            <Shrink class="h-3.5 w-3.5" />
            Collapse All
          </button>

          <!-- Zoom -->
          <div
            class="hidden items-center overflow-hidden rounded-xl border border-slate-200 bg-white sm:flex"
          >
            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center text-slate-500 transition hover:bg-slate-50 hover:text-[#BD2028]"
              title="Zoom out"
              @click="zoomOut"
            >
              <ZoomOut
                class="h-3.5 w-3.5"
              />
            </button>

            <button
              type="button"
              class="h-9 min-w-12 border-x border-slate-200 px-2 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-[#BD2028]"
              title="Reset zoom"
              @click="resetZoom"
            >
              {{ zoom }}%
            </button>

            <button
              type="button"
              class="flex h-9 w-9 items-center justify-center text-slate-500 transition hover:bg-slate-50 hover:text-[#BD2028]"
              title="Zoom in"
              @click="zoomIn"
            >
              <ZoomIn
                class="h-3.5 w-3.5"
              />
            </button>
          </div>

          <!-- Refresh -->
          <button
            type="button"
            :disabled="refreshing"
            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-[#BD2028] disabled:cursor-not-allowed disabled:opacity-50"
            title="Refresh"
            @click="loadOrgChart(false)"
          >
            <RefreshCw
              class="h-4 w-4"
              :class="
                refreshing
                  ? 'animate-spin'
                  : ''
              "
            />
          </button>

          <!-- Fullscreen -->
          <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-[#BD2028]"
            :title="
              isFullscreen
                ? 'Keluar fullscreen'
                : 'Fullscreen'
            "
            @click="
              toggleFullscreen
            "
          >
            <Minimize2
              v-if="isFullscreen"
              class="h-4 w-4"
            />

            <Maximize2
              v-else
              class="h-4 w-4"
            />
          </button>
        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- ERROR                                                              -->
    <!-- ================================================================== -->

    <section
      v-if="errorMessage"
      class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <div
        class="flex min-h-[360px] items-center justify-center px-5"
      >
        <div
          class="max-w-md text-center"
        >
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600"
          >
            <Network class="h-6 w-6" />
          </div>

          <h3
            class="mt-4 text-sm font-semibold text-slate-800"
          >
            Gagal memuat Organization Chart
          </h3>

          <p
            class="mt-1 text-sm leading-6 text-slate-500"
          >
            {{ errorMessage }}
          </p>

          <button
            type="button"
            class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#BD2028] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9F1B22]"
            @click="loadOrgChart()"
          >
            <RefreshCw
              class="h-4 w-4"
            />

            Coba Lagi
          </button>
        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- LOADING                                                            -->
    <!-- ================================================================== -->

    <section
      v-else-if="loading"
      class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <div
        class="flex min-h-[560px] items-center justify-center"
      >
        <div
          class="text-center"
        >
          <div
            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
          >
            <RefreshCw
              class="h-5 w-5 animate-spin"
            />
          </div>

          <p
            class="mt-3 text-sm font-medium text-slate-600"
          >
            Memuat struktur organisasi...
          </p>

          <p
            class="mt-1 text-xs text-slate-400"
          >
            Mohon tunggu sebentar.
          </p>
        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- EMPTY                                                              -->
    <!-- ================================================================== -->

    <section
      v-else-if="roots.length === 0"
      class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
    >
      <div
        class="flex min-h-[420px] items-center justify-center"
      >
        <div
          class="max-w-md px-5 text-center"
        >
          <div
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
          >
            <Network class="h-6 w-6" />
          </div>

          <h3
            class="mt-4 text-sm font-semibold text-slate-800"
          >
            Belum ada struktur organisasi
          </h3>

          <p
            class="mt-1 text-sm leading-6 text-slate-500"
          >
            Struktur organisasi akan muncul setelah employee dan hubungan reporting tersedia.
          </p>
        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- ORGANIZATION CANVAS                                                -->
    <!-- ================================================================== -->

    <section
      v-else
      class="flex overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.04)]"
      :class="
        isFullscreen
          ? 'min-h-0 flex-1'
          : ''
      "
    >
      <div
        class="relative min-h-[560px] w-full overflow-auto"
        :class="
          isFullscreen
            ? 'min-h-0'
            : 'max-h-[calc(100vh-340px)]'
        "
      >
        <!-- ============================================================ -->
        <!-- DOT GRID                                                       -->
        <!-- ============================================================ -->

        <div
          class="pointer-events-none absolute inset-0"
          style="
            background-image: radial-gradient(
              circle,
              rgb(203 213 225 / 0.65) 1px,
              transparent 1px
            );
            background-size: 24px 24px;
          "
        />

        <!-- ============================================================ -->
        <!-- TOP CANVAS LABEL                                               -->
        <!-- ============================================================ -->

        <div
          class="pointer-events-none absolute left-4 top-4 z-20"
        >
          <div
            class="rounded-xl border border-slate-200/80 bg-white/90 px-3 py-2 shadow-sm backdrop-blur"
          >
            <div
              class="flex items-center gap-2"
            >
              <span
                class="h-2 w-2 rounded-full bg-[#BD2028]"
              />

              <span
                class="text-[11px] font-semibold text-slate-600"
              >
                Organizational Structure
              </span>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- CANVAS                                                        -->
        <!-- ============================================================ -->

        <div
          class="relative min-w-max px-14 pb-16 pt-20 transition-transform duration-200"
          :style="{
            transform: `scale(${zoom / 100})`,
            transformOrigin: 'top center',
          }"
        >
          <div
            class="flex justify-center gap-20"
          >
            <OrgChartNode
              v-for="root in roots"
              :key="root.id"
              :node="root"
              :expanded-ids="expandedIds"
              :detail-route-name="detailRouteName"
              @toggle="toggleNode"
            />
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- BOTTOM RIGHT ZOOM INDICATOR                                  -->
        <!-- ============================================================ -->

        <div
          class="pointer-events-none fixed bottom-6 right-6 z-20"
        >
          <div
            class="rounded-xl border border-slate-200/80 bg-white/90 px-3 py-2 text-[11px] font-semibold text-slate-500 shadow-sm backdrop-blur"
          >
            {{ zoom }}%
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- FULLSCREEN FOOTER                                                 -->
    <!-- ================================================================== -->

    <div
      v-if="isFullscreen"
      class="shrink-0 pt-3"
    >
      <div
        class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3"
      >
        <p
          class="text-[11px] text-slate-400"
        >
          Tekan
          <kbd
            class="mx-1 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-500"
          >
            ESC
          </kbd>
          untuk keluar fullscreen.
        </p>

        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl bg-[#BD2028] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#9F1B22]"
          @click="isFullscreen = false"
        >
          <Minimize2
            class="h-3.5 w-3.5"
          />

          Keluar Fullscreen
        </button>
      </div>
    </div>
  </div>
</template>