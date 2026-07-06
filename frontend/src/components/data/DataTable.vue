<template>
  <div class="data-table">
    <div v-if="showToolbar" class="data-table-toolbar">
      <div v-if="searchable" class="data-table-search">
        <label class="sr-only" for="data-table-search">Search table</label>
        <svg class="data-table-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" d="m21 21-5.2-5.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
        </svg>
        <input
          id="data-table-search"
          :value="search"
          type="search"
          class="input-field data-table-search-input"
          :placeholder="searchPlaceholder"
          autocomplete="off"
          @input="onSearchInput"
        />
      </div>

      <div class="data-table-toolbar-meta">
        <p v-if="summaryText" class="data-table-summary">{{ summaryText }}</p>
        <slot name="toolbar" />
      </div>
    </div>

    <TableSkeleton v-if="loading" :rows="skeletonRows" :columns="columns.length || 4" />

    <template v-else>
      <ResponsiveDataList
        :items="displayItems"
        :columns="resolvedColumns"
        :row-key="rowKey"
        :empty-message="resolvedEmptyMessage"
        :footer-label="footerLabel"
        :footer-value="footerValue"
        :money-module="moneyModule"
        :row-class="rowClass"
      >
        <template v-for="(_, name) in $slots" #[name]="slotData">
          <slot v-if="!isTableChromeSlot(name)" :name="name" v-bind="slotData ?? {}" />
        </template>
      </ResponsiveDataList>

      <TablePagination
        v-if="showPagination"
        :current-page="activePage"
        :last-page="activeLastPage"
        :per-page="activePerPage"
        :total="activeTotal"
        :from="activeFrom"
        :to="activeTo"
        :page-size-options="pageSizeOptions"
        @update:page="onPageChange"
        @update:per-page="onPerPageChange"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { valuesMatchSearch } from '../../utils/search'
import ResponsiveDataList from './ResponsiveDataList.vue'
import TablePagination from './TablePagination.vue'
import TableSkeleton from './TableSkeleton.vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  columns: { type: Array, required: true },
  rowKey: { type: String, default: 'id' },
  emptyMessage: { type: String, default: 'No records found.' },
  footerLabel: { type: String, default: '' },
  footerValue: { type: [String, Number], default: '' },
  moneyModule: { type: String, default: 'rental' },
  rowClass: { type: Function, default: null },
  loading: { type: Boolean, default: false },
  skeletonRows: { type: Number, default: 5 },
  sortKey: { type: String, default: '' },
  sortDir: { type: String, default: 'asc' },
  searchable: { type: Boolean, default: true },
  search: { type: String, default: '' },
  searchPlaceholder: { type: String, default: 'Search records…' },
  pagination: { type: Object, default: null },
  serverSide: { type: Boolean, default: false },
  pageSizeOptions: { type: Array, default: () => [25, 50, 100] },
  defaultPerPage: { type: Number, default: 25 },
})

const emit = defineEmits([
  'update:search',
  'update:page',
  'update:perPage',
  'search',
  'page-change',
  'per-page-change',
  'sort',
  'update:sortKey',
  'update:sortDir',
])

const clientPage = ref(1)
const clientPerPage = ref(props.defaultPerPage)
const clientSearch = ref('')

const isServerMode = computed(() => props.serverSide || props.pagination !== null)

const activeSearch = computed(() => (isServerMode.value ? props.search : clientSearch.value))

watch(
  () => props.search,
  (value) => {
    if (isServerMode.value) {
      clientSearch.value = value
    }
  },
)

const sortedItems = computed(() => {
  if (!props.sortKey) {
    return props.items
  }

  const key = props.sortKey
  const dir = props.sortDir === 'desc' ? -1 : 1
  const col = props.columns.find((column) => column.key === key)

  return [...props.items].sort((a, b) => {
    const av = col?.sortValue ? col.sortValue(a) : a[key]
    const bv = col?.sortValue ? col.sortValue(b) : b[key]
    if (av === bv) return 0
    if (av === null || av === undefined) return 1
    if (bv === null || bv === undefined) return -1
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
    return String(av).localeCompare(String(bv)) * dir
  })
})

const filteredItems = computed(() => {
  if (isServerMode.value) {
    return sortedItems.value
  }

  const query = props.search

  return sortedItems.value.filter((item) =>
    props.columns.some((column) => {
      if (column.searchable === false) {
        return false
      }

      const value = column.format ? column.format(item) : item[column.key]
      return valuesMatchSearch(value, query)
    }),
  )
})

const displayItems = computed(() => {
  if (isServerMode.value) {
    return filteredItems.value
  }

  const start = (clientPage.value - 1) * clientPerPage.value
  return filteredItems.value.slice(start, start + clientPerPage.value)
})

const activeTotal = computed(() => {
  if (isServerMode.value) {
    return props.pagination?.total ?? props.items.length
  }

  return filteredItems.value.length
})

const activePerPage = computed(() => {
  if (isServerMode.value) {
    return props.pagination?.per_page ?? props.defaultPerPage
  }

  return clientPerPage.value
})

const activePage = computed(() => {
  if (isServerMode.value) {
    return props.pagination?.current_page ?? 1
  }

  return clientPage.value
})

const activeLastPage = computed(() => {
  if (isServerMode.value) {
    return props.pagination?.last_page ?? 1
  }

  return Math.max(1, Math.ceil(activeTotal.value / activePerPage.value))
})

const activeFrom = computed(() => {
  if (activeTotal.value === 0) {
    return 0
  }

  if (isServerMode.value && props.pagination?.from) {
    return props.pagination.from
  }

  return (activePage.value - 1) * activePerPage.value + 1
})

const activeTo = computed(() => {
  if (activeTotal.value === 0) {
    return 0
  }

  if (isServerMode.value && props.pagination?.to) {
    return props.pagination.to
  }

  return Math.min(activePage.value * activePerPage.value, activeTotal.value)
})

const showToolbar = computed(() => props.searchable || Boolean(props.pagination) || activeTotal.value > 0)
const showPagination = computed(() => activeTotal.value > 0)

const summaryText = computed(() => {
  if (activeTotal.value === 0) {
    return ''
  }

  if (activeSearch.value.trim()) {
    return `${activeTotal.value} matching record${activeTotal.value === 1 ? '' : 's'}`
  }

  return `${activeTotal.value} record${activeTotal.value === 1 ? '' : 's'}`
})

const resolvedEmptyMessage = computed(() => {
  if (activeSearch.value.trim() && !props.loading) {
    return 'No records match your search.'
  }

  return props.emptyMessage
})

const resolvedColumns = computed(() => props.columns)

function isTableChromeSlot(name) {
  return name === 'toolbar'
}

let searchTimer = null

function onSearchInput(event) {
  const value = event.target.value

  if (isServerMode.value) {
    emit('update:search', value)
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => emit('search', value), 300)
    return
  }

  clientSearch.value = value
  clientPage.value = 1
}

function onPageChange(page) {
  if (isServerMode.value) {
    emit('update:page', page)
    emit('page-change', page)
    return
  }

  clientPage.value = page
}

function onPerPageChange(perPage) {
  if (isServerMode.value) {
    emit('update:perPage', perPage)
    emit('per-page-change', perPage)
    return
  }

  clientPerPage.value = perPage
  clientPage.value = 1
}
</script>
