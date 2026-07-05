<template>
  <section class="bulk-meter-page">
    <PageHeader
      title="Bulk meter readings"
      subtitle="Record water or electricity readings for every metered tenant in one fast, keyboard-friendly session."
      :breadcrumbs="[{ label: 'Rental', to: '/rental' }, { label: 'Bulk readings' }]"
    />

    <!-- Setup panel -->
    <div class="bulk-meter-setup content-panel bulk-meter-setup-panel">
      <div class="bulk-meter-setup-header">
        <div>
          <p class="bulk-meter-setup-eyebrow">Session setup</p>
          <h3 class="bulk-meter-setup-title">Choose utility, building, and billing period</h3>
        </div>
        <span v-if="grid" class="bulk-meter-session-badge">
          {{ grid.rows.length }} tenants loaded
        </span>
      </div>

      <div class="bulk-meter-utility-grid">
        <button
          type="button"
          class="bulk-meter-utility-card"
          :class="{ 'bulk-meter-utility-card-active': filters.utility === 'water' }"
          @click="setUtility('water')"
        >
          <span class="bulk-meter-utility-icon bulk-meter-utility-icon-water" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4 6-7 9.5-7 13a7 7 0 1 0 14 0c0-3.5-3-7-7-13Z" />
            </svg>
          </span>
          <span class="bulk-meter-utility-label">Water</span>
          <span class="bulk-meter-utility-hint">Tenant meter readings</span>
        </button>
        <button
          type="button"
          class="bulk-meter-utility-card"
          :class="{ 'bulk-meter-utility-card-active': filters.utility === 'electricity' }"
          @click="setUtility('electricity')"
        >
          <span class="bulk-meter-utility-icon bulk-meter-utility-icon-electricity" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z" />
            </svg>
          </span>
          <span class="bulk-meter-utility-label">Electricity</span>
          <span class="bulk-meter-utility-hint">Tenant meter readings</span>
        </button>
      </div>

      <div class="bulk-meter-filters-row">
        <label class="label-field bulk-meter-filter-building">
          Building
          <BuildingSearchSelect
            v-model="filters.building_id"
            :buildings="buildings"
            placeholder="Select building"
            required
            @change="onFiltersChange"
          />
        </label>

        <label class="label-field">
          Billing month
          <select v-model.number="filters.billing_month" class="input-field" @change="onFiltersChange">
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
        </label>

        <label class="label-field bulk-meter-filter-year">
          Year
          <input
            v-model.number="filters.billing_year"
            type="number"
            min="2000"
            class="input-field"
            @change="onFiltersChange"
          />
        </label>

        <button
          type="button"
          class="btn-primary bulk-meter-load-btn"
          :disabled="loading || !filters.building_id"
          @click="loadGrid"
        >
          <span v-if="loading" class="bulk-meter-load-spinner" aria-hidden="true" />
          {{ loading ? 'Loading tenants…' : 'Load tenant list' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="alert-error">{{ error }}</p>

    <!-- Empty: no building -->
    <div v-if="!filters.building_id && !loading" class="content-panel">
      <EmptyState
        title="Select a building to begin"
        description="Choose the property and billing period above, then load the tenant list to start entering readings."
      />
    </div>

    <!-- Empty: no metered tenants -->
    <div v-else-if="!loading && filters.building_id && loadedOnce && (!grid || grid.rows.length === 0)" class="content-panel">
      <EmptyState
        title="No metered tenants found"
        :description="`No active tenants in ${selectedBuildingName} require ${filters.utility === 'water' ? 'water' : 'electricity'} metering. Enable metering on tenant profiles or pick another building.`"
      />
    </div>

    <!-- Loaded grid -->
    <template v-else-if="grid && grid.rows.length > 0">
      <KpiStrip class="bulk-meter-stats">
        <KpiCard label="To enter" :value="String(editableCount)" accent="neutral" />
        <KpiCard label="Filled" :value="String(toSaveCount)" accent="info" />
        <KpiCard label="Already saved" :value="String(recordedCount)" accent="success" />
        <KpiCard label="Warnings" :value="String(warningCount)" accent="warning" />
      </KpiStrip>

      <div class="bulk-meter-progress content-panel">
        <div class="bulk-meter-progress-meta">
          <div>
            <p class="bulk-meter-progress-title">{{ selectedBuildingName }} · {{ periodLabel }}</p>
            <p class="bulk-meter-progress-sub">
              {{ filters.utility === 'water' ? 'Water' : 'Electricity' }} readings ·
              <span class="bulk-meter-kbd-hint">Enter</span> or
              <span class="bulk-meter-kbd-hint">Tab</span> for next row
            </p>
          </div>
          <p class="bulk-meter-progress-percent">{{ progressPercent }}%</p>
        </div>
        <div class="bulk-meter-progress-track">
          <div
            class="bulk-meter-progress-fill"
            :class="filters.utility === 'water' ? 'bulk-meter-progress-fill-water' : 'bulk-meter-progress-fill-electricity'"
            :style="{ width: `${progressPercent}%` }"
          />
        </div>
        <p v-if="submitSummary" class="bulk-meter-summary" :class="submitSummaryClass">
          {{ submitSummary }}
        </p>
      </div>

      <div class="table-shell bulk-meter-table-shell">
        <div class="bulk-meter-table-scroll">
          <table class="bulk-meter-table">
            <thead class="bulk-meter-thead">
              <tr>
                <th>#</th>
                <th>Unit</th>
                <th>Tenant</th>
                <th class="text-right">Previous</th>
                <th class="text-right">Current</th>
                <th class="text-right">Usage</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, index) in grid.rows"
                :key="row.tenant_id"
                class="bulk-meter-row"
                :class="rowClass(row)"
              >
                <td>
                  <span class="bulk-meter-row-num">{{ index + 1 }}</span>
                </td>
                <td>
                  <span class="bulk-meter-unit">{{ row.unit_label || '—' }}</span>
                  <span v-if="row.unit_floor" class="bulk-meter-unit-floor">Floor {{ row.unit_floor }}</span>
                </td>
                <td class="bulk-meter-tenant-name">{{ row.tenant_name }}</td>
                <td class="text-right">
                  <input
                    v-if="!row.already_recorded && row.is_first_reading"
                    v-model="openingEntries[row.tenant_id]"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="bulk-meter-input bulk-meter-input-opening"
                    placeholder="Opening"
                    :aria-label="`Opening reading for ${row.tenant_name}`"
                    @input="clearRowFeedback(row.tenant_id)"
                  />
                  <span
                    v-else
                    class="bulk-meter-reading-pill bulk-meter-reading-previous"
                    :title="row.previous_reading_locked ? 'Carried forward from last month' : undefined"
                  >
                    {{ row.previous_reading }}
                  </span>
                </td>
                <td class="text-right">
                  <input
                    v-if="!row.already_recorded"
                    :ref="(el) => setInputRef(el, index)"
                    v-model="entries[row.tenant_id]"
                    type="number"
                    min="0"
                    inputmode="numeric"
                    class="bulk-meter-input"
                    :class="{ 'bulk-meter-input-error': rowErrors[row.tenant_id], 'bulk-meter-input-filled': hasEntry(row.tenant_id) }"
                    placeholder="Enter"
                    :aria-label="`Current reading for ${row.tenant_name}`"
                    @keydown="onInputKeydown($event, index)"
                    @input="clearRowFeedback(row.tenant_id)"
                  />
                  <span v-else class="bulk-meter-reading-pill bulk-meter-reading-saved">
                    {{ row.existing_current_reading }}
                  </span>
                </td>
                <td class="text-right">
                  <span
                    v-if="displayConsumption(row) !== null"
                    class="bulk-meter-consumption"
                    :class="consumptionClass(row)"
                  >
                    {{ formatConsumption(row) }}
                  </span>
                  <span v-else class="bulk-meter-consumption-empty">—</span>
                </td>
                <td>
                  <StatusBadge
                    v-if="row.already_recorded"
                    variant="neutral"
                    label="Saved"
                  />
                  <StatusBadge
                    v-else-if="rowErrors[row.tenant_id]"
                    variant="danger"
                    :label="rowErrors[row.tenant_id]"
                  />
                  <StatusBadge
                    v-else-if="rowWarnings[row.tenant_id]"
                    variant="warning"
                    label="High usage"
                  />
                  <StatusBadge
                    v-else-if="hasEntry(row.tenant_id)"
                    variant="info"
                    label="Ready"
                  />
                  <span v-else class="bulk-meter-status-pending">Pending</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bulk-meter-sticky-bar">
        <div class="bulk-meter-sticky-inner">
          <div class="bulk-meter-sticky-summary">
            <p class="bulk-meter-sticky-title">
              {{ toSaveCount }} reading{{ toSaveCount === 1 ? '' : 's' }} ready to save
            </p>
            <p class="bulk-meter-sticky-sub">
              {{ skippedCount }} skipped · {{ warningCount }} warning{{ warningCount === 1 ? '' : 's' }}
            </p>
          </div>
          <button
            type="button"
            class="btn-primary bulk-meter-save-btn"
            :disabled="saving || toSaveCount === 0"
            @click="confirmSubmit"
          >
            {{ saving ? 'Saving…' : `Save ${toSaveCount} reading${toSaveCount === 1 ? '' : 's'}` }}
          </button>
        </div>
      </div>
    </template>

    <TableSkeleton v-else-if="loading" :rows="8" :columns="7" />

    <AppDialog v-model:open="showConfirm" title="Confirm bulk save" size="sm">
      <div class="bulk-meter-confirm">
        <p class="bulk-meter-confirm-lead">You are about to save readings for <strong>{{ periodLabel }}</strong>.</p>
        <ul class="bulk-meter-confirm-stats">
          <li><span>To save</span><strong>{{ toSaveCount }}</strong></li>
          <li><span>Skipped (blank)</span><strong>{{ skippedCount }}</strong></li>
          <li><span>Warnings</span><strong>{{ warningCount }}</strong></li>
        </ul>
        <p v-if="warningCount > 0" class="bulk-meter-confirm-note">
          Rows with high-usage warnings will still be saved unless you correct them first.
        </p>
      </div>
      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="showConfirm = false">Cancel</button>
        <button type="button" class="btn-primary w-full sm:w-auto" :disabled="saving" @click="submit">
          {{ saving ? 'Saving…' : 'Confirm save' }}
        </button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import KpiCard from '../../components/ui/KpiCard.vue'
import KpiStrip from '../../components/ui/KpiStrip.vue'
import StatusBadge from '../../components/ui/StatusBadge.vue'
import TableSkeleton from '../../components/data/TableSkeleton.vue'
import { useToast } from '../../composables/useToast'
import { fetchBulkMeterReadingGrid, storeBulkMeterReadings } from '../../api/bulkMeterReadings'
import { fetchBuildings } from '../../api/rental'

const toast = useToast()

const buildings = ref([])
const grid = ref(null)
const loading = ref(false)
const saving = ref(false)
const loadedOnce = ref(false)
const error = ref('')
const showConfirm = ref(false)
const submitSummary = ref('')
const submitSummaryClass = ref('')
const entries = reactive({})
const openingEntries = reactive({})
const rowErrors = reactive({})
const rowWarnings = reactive({})
const inputRefs = ref([])

const now = new Date()
const filters = reactive({
  utility: 'water',
  building_id: '',
  billing_month: now.getMonth() + 1,
  billing_year: now.getFullYear(),
})

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const periodLabel = computed(() => {
  const month = months.find((item) => item.value === filters.billing_month)?.label ?? filters.billing_month
  return `${month} ${filters.billing_year}`
})

const selectedBuildingName = computed(() => {
  const building = buildings.value.find((item) => String(item.id) === String(filters.building_id))
  return building?.name ?? 'Selected building'
})

const recordedCount = computed(() => {
  if (!grid.value) return 0
  return grid.value.rows.filter((row) => row.already_recorded).length
})

const editableCount = computed(() => {
  if (!grid.value) return 0
  return grid.value.rows.filter((row) => !row.already_recorded).length
})

const toSaveCount = computed(() => {
  if (!grid.value) return 0
  return grid.value.rows.filter((row) => !row.already_recorded && hasEntry(row.tenant_id)).length
})

const skippedCount = computed(() => {
  if (!grid.value) return 0
  return grid.value.rows.filter((row) => !row.already_recorded && !hasEntry(row.tenant_id)).length
})

const warningCount = computed(() => Object.keys(rowWarnings).length)

const progressPercent = computed(() => {
  if (editableCount.value === 0) return 100
  return Math.round((toSaveCount.value / editableCount.value) * 100)
})

function hasEntry(tenantId) {
  const value = entries[tenantId]
  return value !== '' && value !== null && value !== undefined
}

function setInputRef(el, index) {
  if (el) inputRefs.value[index] = el
}

function setUtility(utility) {
  if (filters.utility === utility) return
  filters.utility = utility
  onFiltersChange()
}

function resetEntryState() {
  Object.keys(entries).forEach((key) => delete entries[key])
  Object.keys(openingEntries).forEach((key) => delete openingEntries[key])
  Object.keys(rowErrors).forEach((key) => delete rowErrors[key])
  Object.keys(rowWarnings).forEach((key) => delete rowWarnings[key])
  inputRefs.value = []
  submitSummary.value = ''
}

function effectivePreviousReading(row) {
  if (row.is_first_reading) {
    const raw = openingEntries[row.tenant_id]
    if (raw === '' || raw === null || raw === undefined) return 0
    const value = Number(raw)
    return Number.isFinite(value) ? value : 0
  }

  return Number(row.previous_reading || 0)
}

function onFiltersChange() {
  grid.value = null
  resetEntryState()
  loadedOnce.value = false
}

function parseEntryValue(tenantId) {
  const raw = entries[tenantId]
  if (raw === '' || raw === null || raw === undefined) return null
  const value = Number(raw)
  return Number.isFinite(value) ? value : null
}

function displayConsumption(row) {
  if (row.already_recorded) return row.existing_consumption
  const current = parseEntryValue(row.tenant_id)
  if (current === null) return null
  return Math.max(0, current - effectivePreviousReading(row))
}

function formatConsumption(row) {
  const value = displayConsumption(row)
  if (value === null) return '—'
  return value > 0 ? `+${value}` : String(value)
}

function consumptionClass(row) {
  const value = displayConsumption(row)
  if (value === null || value === 0) return ''
  if (rowWarnings[row.tenant_id]) return 'bulk-meter-consumption-high'
  return 'bulk-meter-consumption-ok'
}

function validateRow(row) {
  delete rowErrors[row.tenant_id]
  delete rowWarnings[row.tenant_id]

  const current = parseEntryValue(row.tenant_id)
  if (current === null) return

  const previous = effectivePreviousReading(row)

  if (current < previous) {
    rowErrors[row.tenant_id] = `Min ${previous}`
    return
  }

  const consumption = current - previous
  const average = row.average_consumption

  if (average !== null && average > 0 && consumption > average * 3) {
    rowWarnings[row.tenant_id] = `High usage (${consumption} vs avg ${Math.round(average)}).`
  }
}

function rowClass(row) {
  if (row.already_recorded) return 'bulk-meter-row-recorded'
  if (rowErrors[row.tenant_id]) return 'bulk-meter-row-error'
  if (rowWarnings[row.tenant_id]) return 'bulk-meter-row-warning'
  if (hasEntry(row.tenant_id)) return 'bulk-meter-row-ready'
  return ''
}

function clearRowFeedback(tenantId) {
  const row = grid.value?.rows.find((item) => item.tenant_id === tenantId)
  if (row) validateRow(row)
}

function focusInput(index) {
  const input = inputRefs.value[index]
  if (input) {
    input.focus()
    input.select()
  }
}

function onInputKeydown(event, index) {
  if (event.key !== 'Enter' && event.key !== 'Tab') return

  const editableIndexes = grid.value.rows
    .map((row, rowIndex) => (!row.already_recorded ? rowIndex : null))
    .filter((rowIndex) => rowIndex !== null)

  const position = editableIndexes.indexOf(index)
  if (position === -1) return

  const nextIndex = editableIndexes[position + 1]
  if (nextIndex === undefined) return

  event.preventDefault()
  focusInput(nextIndex)
}

async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadGrid() {
  if (!filters.building_id) return

  loading.value = true
  error.value = ''
  resetEntryState()

  try {
    grid.value = await fetchBulkMeterReadingGrid({
      utility: filters.utility,
      building_id: filters.building_id,
      billing_month: filters.billing_month,
      billing_year: filters.billing_year,
    })
    grid.value.rows.forEach((row) => {
      if (row.is_first_reading && openingEntries[row.tenant_id] === undefined) {
        openingEntries[row.tenant_id] = row.previous_reading ?? 0
      }
    })
    loadedOnce.value = true

    await nextTick()
    const firstEditable = grid.value.rows.findIndex((row) => !row.already_recorded)
    if (firstEditable >= 0) focusInput(firstEditable)
  } catch (e) {
    const message = e.response?.data?.message || 'Could not load tenant readings.'
    error.value = message
    toast.error(message)
    grid.value = null
  } finally {
    loading.value = false
  }
}

function confirmSubmit() {
  if (!grid.value) return

  grid.value.rows.forEach((row) => {
    if (!row.already_recorded) validateRow(row)
  })

  if (Object.keys(rowErrors).length > 0) {
    submitSummary.value = 'Fix rows highlighted in red before saving.'
    submitSummaryClass.value = 'bulk-meter-summary-error'
    return
  }

  showConfirm.value = true
}

async function submit() {
  if (!grid.value) return

  saving.value = true
  error.value = ''

  const readings = grid.value.rows.map((row) => {
    const reading = {
      tenant_id: row.tenant_id,
      current_reading: row.already_recorded ? null : parseEntryValue(row.tenant_id),
    }

    if (!row.already_recorded && row.is_first_reading) {
      reading.previous_reading = effectivePreviousReading(row)
    }

    return reading
  })

  try {
    const result = await storeBulkMeterReadings({
      utility: filters.utility,
      rental_building_id: filters.building_id,
      billing_month: filters.billing_month,
      billing_year: filters.billing_year,
      readings,
    })

    showConfirm.value = false
    const summaryText = `Saved ${result.saved_count} readings · ${result.skipped_count} skipped · ${result.error_count} errors`
    submitSummary.value = summaryText
    submitSummaryClass.value = result.error_count > 0 ? 'bulk-meter-summary-warning' : 'bulk-meter-summary-success'
    if (result.error_count > 0) {
      toast.error(summaryText)
    } else {
      toast.success(`Saved ${result.saved_count} reading${result.saved_count === 1 ? '' : 's'}.`)
    }

    result.results
      .filter((item) => item.status === 'error')
      .forEach((item) => {
        rowErrors[item.tenant_id] = item.message
      })

    await loadGrid()
  } catch (e) {
    const message = e.response?.data?.message || 'Could not save bulk readings.'
    error.value = message
    toast.error(message)
    showConfirm.value = false
  } finally {
    saving.value = false
  }
}

onMounted(loadBuildings)
</script>
