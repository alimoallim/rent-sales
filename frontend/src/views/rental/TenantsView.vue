<template>
  <section>
    <PageHeader
      :title="status === 'active' ? 'Tenants' : 'Moved-out tenants'"
      :subtitle="status === 'active' ? 'Register tenants and set rental agreement terms, including metering requirements.' : 'Tenants who have vacated their units.'"
    >
      <template v-if="status === 'active'" #actions>
        <button type="button" class="btn-primary w-full sm:w-auto" @click="openCreate">Register tenant</button>
      </template>
    </PageHeader>

    <div class="filter-bar">
      <BuildingSearchSelect
        v-model="filters.building_id"
        :buildings="buildings"
        include-all
        placeholder="All buildings"
        @change="load"
      />
      <div class="segmented-control">
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': status === 'active' }"
          @click="setStatus('active')"
        >
          Active
        </button>
        <button
          type="button"
          class="segmented-option"
          :class="{ 'segmented-option-active': status === 'inactive' }"
          @click="setStatus('inactive')"
        >
          Moved out
        </button>
      </div>
    </div>

    <ResponsiveDataList
      :items="tenants"
      :columns="tenantColumns"
      :empty-message="status === 'active' ? 'No tenants found for this building yet.' : 'No moved-out tenants found.'"
    >
      <template #card-title-name="{ item }">
        <TenantNameMenu
          :tenant-id="item.id"
          :tenant-name="item.name"
          :building-id="item.rental_building_id"
        />
      </template>
      <template #cell-name="{ item }">
        <TenantNameMenu
          :tenant-id="item.id"
          :tenant-name="item.name"
          :building-id="item.rental_building_id"
        />
      </template>
      <template #cell-agreement="{ item }">
        <div class="flex flex-wrap justify-end gap-1 lg:justify-start">
          <span v-if="item.requires_water_metering" class="badge badge-info">Water</span>
          <span v-if="item.requires_electricity_metering" class="badge badge-accent">Electricity</span>
          <span
            v-if="!item.requires_water_metering && !item.requires_electricity_metering"
            class="text-xs text-zinc-500 dark:text-zinc-400"
          >
            No metering
          </span>
        </div>
      </template>
      <template #cell-balance="{ item }">
        <span
          class="font-medium tabular-nums"
          :class="Number(item.balance) > 0 ? 'text-amber-700' : 'text-emerald-700'"
        >
          {{ formatMoney(item.balance, 'rental') }}
        </span>
      </template>
      <template v-if="status === 'active'" #actions="{ item }">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="openEdit(item)">Edit</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="openMoveOut(item)">Move out</button>
      </template>
    </ResponsiveDataList>

    <AppDialog
      v-model:open="showTenantForm"
      :title="editing ? 'Edit tenant' : 'Register tenant'"
      size="2xl"
      :close-on-backdrop="false"
    >
      <form id="tenant-form" class="grid gap-4 lg:grid-cols-2 lg:items-start lg:gap-x-8" @submit.prevent="saveTenant">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Unit &amp; contact</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="label-field sm:col-span-2">
              Building
              <BuildingSearchSelect
                v-model="form.rental_building_id"
                :buildings="buildings"
                required
                @change="onBuildingChange"
              />
            </label>

            <label class="label-field sm:col-span-2">
              Vacant unit
              <UnitSearchSelect
                v-model="form.rental_unit_id"
                :units="vacantUnits"
                required
              />
            </label>

            <label class="label-field">
              Full name
              <input v-model="form.name" class="input-field" required />
            </label>

            <label class="label-field">
              Phone
              <input v-model="form.phone" class="input-field" required />
            </label>

            <label class="label-field sm:col-span-2">
              ID / passport
              <input v-model="form.passport_or_id" class="input-field" />
            </label>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rental agreement</p>
          <p class="text-xs text-zinc-500 dark:text-zinc-400">Financial terms and metering requirements from the tenant's contract.</p>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="label-field sm:col-span-2">
              Agreement start date
              <input v-model="form.start_date" type="date" class="input-field" />
            </label>

            <label class="label-field">
              {{ moneyLabel('Deposit', 'rental') }}
              <input v-model="form.deposit" type="number" min="0" step="0.01" class="input-field" />
            </label>

            <label class="label-field">
              {{ moneyLabel('Monthly service charge', 'rental') }}
              <input v-model="form.service_amount" type="number" min="0" step="0.01" class="input-field" />
            </label>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 p-3">
              <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Water meter reading</p>
              <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Required monthly under this agreement?</p>
              <div class="segmented-control mt-2 w-full">
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="form.requires_water_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_water_metering = true"
                >
                  Yes
                </button>
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="!form.requires_water_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_water_metering = false"
                >
                  No
                </button>
              </div>
            </div>

            <div class="rounded-md border border-zinc-200 bg-zinc-50 dark:bg-zinc-900/50 p-3">
              <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Electricity meter reading</p>
              <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Required monthly under this agreement?</p>
              <div class="segmented-control mt-2 w-full">
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="form.requires_electricity_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_electricity_metering = true"
                >
                  Yes
                </button>
                <button
                  type="button"
                  class="toggle-option flex-1"
                  :class="!form.requires_electricity_metering ? 'toggle-option-active' : ''"
                  @click="form.requires_electricity_metering = false"
                >
                  No
                </button>
              </div>
            </div>
          </div>
        </div>

        <p v-if="error" class="alert-error lg:col-span-2">{{ error }}</p>
      </form>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeTenantForm">Cancel</button>
        <button type="submit" form="tenant-form" class="btn-primary w-full sm:w-auto">Save tenant</button>
      </template>
    </AppDialog>

    <AppDialog
      v-model:open="showMoveOutForm"
      :title="`Move out: ${moveOutTarget?.name || ''}`"
      size="sm"
      :close-on-backdrop="false"
    >
      <div class="grid gap-4">
        <label class="label-field">
          Move-out date
          <input v-model="moveOutForm.moved_out_at" type="date" class="input-field" required />
        </label>
        <label class="label-field">
          {{ moneyLabel('Refund amount', 'rental') }}
          <input v-model="moveOutForm.refund_amount" type="number" min="0" step="0.01" class="input-field" />
        </label>
        <label class="label-field">
          Reason
          <textarea v-model="moveOutForm.reason" rows="3" class="input-field" required />
        </label>
      </div>
      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

      <template #footer>
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeMoveOutForm">Cancel</button>
        <button type="button" class="btn-destructive w-full sm:w-auto" @click="submitMoveOut">Confirm move out</button>
      </template>
    </AppDialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import PageHeader from '../../components/PageHeader.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'
import UnitSearchSelect from '../../components/ui/UnitSearchSelect.vue'
import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'
import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'
import {
  createTenant,
  fetchBuildings,
  fetchTenants,
  fetchUnits,
  moveOutTenant,
  updateTenant,
} from '../../api/rental'
import { formatMoney, moneyLabel } from '../../utils/money'

const buildings = ref([])
const tenants = ref([])
const vacantUnits = ref([])
const status = ref('active')
const showTenantForm = ref(false)
const showMoveOutForm = ref(false)
const editing = ref(null)
const moveOutTarget = ref(null)
const error = ref('')
const filters = reactive({ building_id: '' })
const form = reactive({
  rental_building_id: '',
  rental_unit_id: '',
  name: '',
  phone: '',
  deposit: 0,
  service_amount: 0,
  start_date: '',
  passport_or_id: '',
  requires_water_metering: false,
  requires_electricity_metering: false,
})
const moveOutForm = reactive({
  moved_out_at: new Date().toISOString().slice(0, 10),
  refund_amount: 0,
  reason: '',
})

const tenantColumns = computed(() => {
  const base = [
    { key: 'name', label: 'Name', cardTitle: true },
    { key: 'phone', label: 'Phone', mobileCard: true },
    { key: 'building_name', label: 'Building', mobileCard: true },
    { key: 'unit_label', label: 'Unit', tabletCard: true },
    { key: 'deposit', label: 'Deposit', money: true, tabletCard: true },
  ]

  if (status.value === 'active') {
    return [
      ...base,
      { key: 'agreement', label: 'Agreement', mobileCard: true },
      { key: 'balance', label: 'Balance', align: 'right', mobileCard: true },
    ]
  }

  return base
})



async function loadBuildings() {
  const response = await fetchBuildings()
  buildings.value = response.data
}

async function loadVacantUnits(buildingId, includeUnitId = null) {
  if (!buildingId) {
    vacantUnits.value = []
    return
  }
  const params = { building_id: buildingId, status: 'vacant' }
  const response = await fetchUnits(params)
  vacantUnits.value = response.data
  if (includeUnitId && !vacantUnits.value.find((unit) => unit.id === includeUnitId)) {
    const allUnits = await fetchUnits({ building_id: buildingId })
    const current = allUnits.data.find((unit) => unit.id === includeUnitId)
    if (current) vacantUnits.value = [current, ...vacantUnits.value]
  }
}

async function load() {
  const params = { status: status.value }
  if (filters.building_id) params.building_id = filters.building_id
  const response = await fetchTenants(params)
  tenants.value = response.data
}

function setStatus(next) {
  status.value = next
  load()
}

function onBuildingChange() {
  form.rental_unit_id = ''
  loadVacantUnits(form.rental_building_id, editing.value?.rental_unit_id)
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    rental_building_id: filters.building_id || '',
    rental_unit_id: '',
    name: '',
    phone: '',
    deposit: 0,
    service_amount: 0,
    start_date: new Date().toISOString().slice(0, 10),
    passport_or_id: '',
    requires_water_metering: false,
    requires_electricity_metering: false,
  })
  error.value = ''
  showTenantForm.value = true
  loadVacantUnits(form.rental_building_id)
}

async function openEdit(tenant) {
  editing.value = tenant
  Object.assign(form, {
    rental_building_id: tenant.rental_building_id,
    rental_unit_id: tenant.rental_unit_id,
    name: tenant.name,
    phone: tenant.phone,
    deposit: tenant.deposit,
    service_amount: tenant.service_amount,
    start_date: tenant.start_date || '',
    passport_or_id: tenant.passport_or_id || '',
    requires_water_metering: Boolean(tenant.requires_water_metering),
    requires_electricity_metering: Boolean(tenant.requires_electricity_metering),
  })
  error.value = ''
  showTenantForm.value = true
  await loadVacantUnits(tenant.rental_building_id, tenant.rental_unit_id)
}

function closeTenantForm() {
  showTenantForm.value = false
}

async function saveTenant() {
  error.value = ''
  try {
    const payload = { ...form }
    if (editing.value) {
      await updateTenant(editing.value.id, payload)
    } else {
      await createTenant(payload)
    }
    closeTenantForm()
    await load()
  } catch (e) {
    const validation = e.response?.data?.errors
    error.value = validation
      ? Object.values(validation).flat().join(' ')
      : e.response?.data?.message || 'Could not save tenant.'
  }
}

function openMoveOut(tenant) {
  moveOutTarget.value = tenant
  Object.assign(moveOutForm, {
    moved_out_at: new Date().toISOString().slice(0, 10),
    refund_amount: tenant.deposit || 0,
    reason: '',
  })
  error.value = ''
  showMoveOutForm.value = true
}

function closeMoveOutForm() {
  showMoveOutForm.value = false
}

async function submitMoveOut() {
  error.value = ''
  try {
    await moveOutTenant(moveOutTarget.value.id, moveOutForm)
    closeMoveOutForm()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not record move-out.'
  }
}

watch(() => form.rental_building_id, (buildingId) => {
  if (showTenantForm.value) {
    loadVacantUnits(buildingId, editing.value?.rental_unit_id)
  }
})

onMounted(async () => {
  await loadBuildings()
  await load()
})
</script>
