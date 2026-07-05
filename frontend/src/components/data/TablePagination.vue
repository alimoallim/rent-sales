<template>
  <div v-if="total > 0" class="table-pagination">
    <p class="table-pagination-summary">
      Showing
      <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ from }}</span>
      –
      <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ to }}</span>
      of
      <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ total }}</span>
    </p>

    <div class="table-pagination-controls">
      <label class="table-pagination-per-page">
        <span class="sr-only">Rows per page</span>
        <select
          class="input-field table-pagination-select"
          :value="perPage"
          @change="onPerPageChange"
        >
          <option v-for="size in pageSizeOptions" :key="size" :value="size">
            {{ size }} / page
          </option>
        </select>
      </label>

      <div class="table-pagination-nav">
        <button
          type="button"
          class="table-pagination-btn"
          :disabled="currentPage <= 1"
          aria-label="Previous page"
          @click="$emit('update:page', currentPage - 1)"
        >
          Previous
        </button>

        <div class="hidden items-center gap-1 sm:flex">
          <button
            v-for="page in visiblePages"
            :key="page.key"
            type="button"
            class="table-pagination-page"
            :class="{ 'table-pagination-page-active': page.number === currentPage }"
            :disabled="page.number === null"
            @click="page.number && $emit('update:page', page.number)"
          >
            {{ page.label }}
          </button>
        </div>

        <span class="table-pagination-mobile-page sm:hidden">
          Page {{ currentPage }} of {{ lastPage }}
        </span>

        <button
          type="button"
          class="table-pagination-btn"
          :disabled="currentPage >= lastPage"
          aria-label="Next page"
          @click="$emit('update:page', currentPage + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 25 },
  total: { type: Number, default: 0 },
  from: { type: Number, default: 0 },
  to: { type: Number, default: 0 },
  pageSizeOptions: { type: Array, default: () => [25, 50, 100] },
})

const emit = defineEmits(['update:page', 'update:perPage'])

const visiblePages = computed(() => {
  const pages = []
  const current = props.currentPage
  const last = props.lastPage
  const add = (number, label = null) => pages.push({ key: `p-${number ?? label}`, number, label: label ?? String(number) })

  if (last <= 7) {
    for (let i = 1; i <= last; i += 1) add(i)
    return pages
  }

  add(1)
  if (current > 3) add(null, '…')

  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  for (let i = start; i <= end; i += 1) add(i)

  if (current < last - 2) add(null, '…')
  add(last)

  return pages
})

function onPerPageChange(event) {
  const value = Number(event.target.value)
  if (value > 0) {
    emit('update:perPage', value)
  }
}
</script>
