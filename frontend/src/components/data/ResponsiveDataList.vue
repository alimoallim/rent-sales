<template>
  <div>
    <!-- Phone + tablet: stacked cards -->
    <div class="space-y-2 lg:hidden">
      <div
        v-for="(item, index) in items"
        :key="rowKeyValue(item, index)"
        class="card-surface p-3 transition-all duration-200"
        :class="resolveRowClass(item)"
      >
        <p v-if="titleColumn" class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
          <slot :name="`card-title-${titleColumn.key}`" :item="item">
            {{ formatValue(item, titleColumn) }}
          </slot>
        </p>

        <dl class="mt-2 space-y-1.5 text-sm" :class="titleColumn ? '' : 'mt-0'">
          <div
            v-for="col in mobileCardColumns"
            :key="col.key"
            class="flex justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 py-1 last:border-0"
          >
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ col.label }}</dt>
            <dd
              class="text-right text-sm font-medium text-zinc-900 dark:text-zinc-100"
              :class="col.align === 'right' || col.money ? 'tabular-nums' : ''"
            >
              <slot :name="`cell-${col.key}`" :item="item" :column="col">
                {{ formatValue(item, col) }}
              </slot>
            </dd>
          </div>
        </dl>

        <dl v-if="tabletCardColumns.length" class="mt-1.5 hidden space-y-1.5 text-sm md:block">
          <div
            v-for="col in tabletCardColumns"
            :key="col.key"
            class="flex justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 py-1 last:border-0"
          >
            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ col.label }}</dt>
            <dd
              class="text-right text-sm font-medium text-zinc-900 dark:text-zinc-100"
              :class="col.align === 'right' || col.money ? 'tabular-nums' : ''"
            >
              <slot :name="`cell-${col.key}`" :item="item" :column="col">
                {{ formatValue(item, col) }}
              </slot>
            </dd>
          </div>
        </dl>

        <div
          v-if="$slots.actions"
          class="mt-2 flex flex-col gap-1.5 border-t border-zinc-200 dark:border-zinc-700 pt-2 sm:flex-row sm:flex-wrap"
        >
          <slot name="actions" :item="item" />
        </div>
      </div>

      <p v-if="items.length === 0" class="card-surface empty-state">
        {{ emptyMessage }}
      </p>

      <div
        v-if="footerLabel && items.length"
        class="card-surface flex justify-between px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100"
      >
        <span>{{ footerLabel }}</span>
        <span class="tabular-nums">{{ footerValue }}</span>
      </div>
    </div>

    <!-- Desktop: ERP data grid -->
    <div class="table-shell hidden lg:block">
      <table class="min-w-full text-sm">
        <thead class="border-b border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 text-left">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"
              :class="col.align === 'right' ? 'text-right' : ''"
            >
              {{ col.label }}
            </th>
            <th v-if="$slots.actions" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
          <tr
            v-for="(item, index) in items"
            :key="rowKeyValue(item, index)"
            class="transition-colors duration-200"
            :class="resolveRowClass(item) || 'hover:bg-zinc-50 dark:hover:bg-zinc-900/50'"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300"
              :class="[
                col.align === 'right' ? 'text-right tabular-nums' : '',
                col.key === titleColumn?.key ? 'font-medium text-zinc-900 dark:text-zinc-100' : '',
              ]"
            >
              <slot :name="`cell-${col.key}`" :item="item" :column="col">
                {{ formatValue(item, col) }}
              </slot>
            </td>
            <td v-if="$slots.actions" class="px-3 py-2 text-right">
              <slot name="actions" :item="item" />
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td
              :colspan="columns.length + ($slots.actions ? 1 : 0)"
              class="empty-state"
            >
              {{ emptyMessage }}
            </td>
          </tr>
        </tbody>
        <tfoot v-if="footerLabel && items.length" class="border-t border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 font-semibold text-zinc-900 dark:text-zinc-100">
          <tr>
            <td :colspan="Math.max(columns.length - 1 + ($slots.actions ? 1 : 0), 1)" class="px-3 py-2 text-sm">
              {{ footerLabel }}
            </td>
            <td class="px-3 py-2 text-right text-sm tabular-nums">{{ footerValue }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney as formatModuleMoney } from '../../utils/money'

const props = defineProps({
  items: { type: Array, default: () => [] },
  columns: { type: Array, required: true },
  rowKey: { type: String, default: 'id' },
  emptyMessage: { type: String, default: 'No records found.' },
  footerLabel: { type: String, default: '' },
  footerValue: { type: [String, Number], default: '' },
  moneyModule: { type: String, default: 'rental' },
  rowClass: { type: Function, default: null },
})

const titleColumn = computed(() => props.columns.find((col) => col.cardTitle))

const mobileCardColumns = computed(() =>
  props.columns.filter((col) => !col.cardTitle && col.mobileCard !== false && !col.desktopOnly),
)

const tabletCardColumns = computed(() =>
  props.columns.filter((col) => !col.cardTitle && col.tabletCard === true),
)

function rowKeyValue(item, index) {
  return item?.[props.rowKey] ?? index
}

function resolveRowClass(item) {
  return props.rowClass ? props.rowClass(item) : ''
}

function formatValue(item, col) {
  if (col.format) return col.format(item)
  const value = item[col.key]
  if (col.money) return formatMoney(value)
  if (value === null || value === undefined || value === '') return '—'
  return value
}

function formatMoney(value) {
  return formatModuleMoney(value, props.moneyModule)
}
</script>
