<template>
  <div>
    <!-- Phone + tablet: stacked cards -->
    <div class="space-y-2 lg:hidden">
      <div
        v-for="(item, index) in items"
        :key="rowKeyValue(item, index)"
        class="card-surface p-3.5 transition-all duration-200 hover:border-zinc-300 hover:shadow-md dark:hover:border-zinc-600"
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
          class="mt-2 flex flex-wrap items-center justify-end gap-1.5 border-t border-zinc-200 dark:border-zinc-700 pt-2"
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
      <div class="table-scroll">
        <table class="data-grid min-w-full text-sm">
          <thead class="data-grid-head">
            <tr>
              <th
                v-for="col in columns"
                :key="col.key"
                class="data-grid-th"
                :class="col.align === 'right' ? 'text-right' : ''"
              >
                {{ col.label }}
              </th>
              <th v-if="$slots.actions" class="data-grid-th text-right">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="data-grid-body">
            <tr
              v-for="(item, index) in items"
              :key="rowKeyValue(item, index)"
              class="data-grid-row"
              :class="resolveRowClass(item)"
            >
              <td
                v-for="col in columns"
                :key="col.key"
                class="data-grid-td"
                :class="[
                  col.align === 'right' ? 'text-right tabular-nums' : '',
                  col.key === titleColumn?.key ? 'font-medium text-zinc-900 dark:text-zinc-100' : '',
                ]"
              >
                <slot :name="`cell-${col.key}`" :item="item" :column="col">
                  {{ formatValue(item, col) }}
                </slot>
              </td>
              <td v-if="$slots.actions" class="data-grid-td w-px whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-1">
                  <slot name="actions" :item="item" />
                </div>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td
                :colspan="columns.length + ($slots.actions ? 1 : 0)"
                class="data-grid-empty"
              >
                {{ emptyMessage }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="footerLabel && items.length" class="data-grid-foot">
            <tr>
              <td :colspan="Math.max(columns.length - 1 + ($slots.actions ? 1 : 0), 1)" class="data-grid-td">
                {{ footerLabel }}
              </td>
              <td class="data-grid-td text-right tabular-nums">{{ footerValue }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
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
