<template>

  <section class="charges-screen">

    <PageHeader

      title="Charges"

      subtitle="Monthly rent, service, water, and electricity charges per tenant."

    >

      <template #actions>

        <button

          type="button"

          class="btn-primary w-full sm:w-auto"

          :disabled="generating || !filters.building_id"

          :title="!filters.building_id ? 'Select a building first' : ''"

          @click="generate"

        >

          {{ generating ? 'Generating…' : 'Generate this month' }}

        </button>

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

      <select v-model="filters.billing_month" class="input-field" @change="load">

        <option value="">All months</option>

        <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>

      </select>

      <input

        v-model="filters.billing_year"

        type="number"

        min="2000"

        placeholder="Year"

        class="input-field w-full sm:w-28"

        @change="load"

      />

    </div>



    <p v-if="message" class="alert-success mb-3">{{ message }}</p>

    <p v-if="generateError" class="alert-error mb-3">{{ generateError }}</p>



    <ResponsiveDataList

      :items="charges"

      :columns="chargeColumns"

      empty-message="No charges found."

    >

      <template #card-title-tenant_name="{ item }">

        <TenantNameMenu

          :tenant-id="item.tenant_id"

          :tenant-name="item.tenant_name"

          :building-id="item.rental_building_id"

        />

      </template>

      <template #cell-tenant_name="{ item }">

        <TenantNameMenu

          :tenant-id="item.tenant_id"

          :tenant-name="item.tenant_name"

          :building-id="item.rental_building_id"

        />

      </template>

      <template #cell-charge_type="{ item }">

        <StatusBadge :variant="chargeTypeVariant(item.charge_type)" :label="chargeTypeLabel(item.charge_type)" />

      </template>

      <template #cell-period="{ item }">{{ item.billing_month }}/{{ item.billing_year }}</template>

      <template #cell-rent_amount="{ item }">

        {{ item.is_editable ? formatMoney(item.rent_amount, 'rental') : '—' }}

      </template>

      <template #cell-service_amount="{ item }">

        {{ item.is_editable ? formatMoney(item.service_amount, 'rental') : '—' }}

      </template>

      <template #actions="{ item }">

        <button

          v-if="item.is_editable"

          type="button"

          class="btn-secondary w-full sm:w-auto"

          @click="openEdit(item)"

        >

          Edit

        </button>

      </template>

    </ResponsiveDataList>



    <AppDialog v-model:open="showForm" title="Edit charge" size="sm" :close-on-backdrop="false">

      <p class="text-sm text-slate-600">{{ editing?.tenant_name }} — {{ editing?.billing_month }}/{{ editing?.billing_year }}</p>

      <div class="mt-4 grid gap-4">

        <label class="label-field">

          {{ moneyLabel('Rent', 'rental') }}

          <input v-model="form.rent_amount" type="number" min="0" step="0.01" class="input-field" required />

        </label>

        <label class="label-field">

          {{ moneyLabel('Service', 'rental') }}

          <input v-model="form.service_amount" type="number" min="0" step="0.01" class="input-field" required />

        </label>

        <label class="label-field">

          Purpose

          <input v-model="form.purpose" class="input-field" />

        </label>

      </div>

      <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

      <template #footer>

        <button type="button" class="btn-secondary w-full sm:w-auto" @click="closeForm">Cancel</button>

        <button type="button" class="btn-primary w-full sm:w-auto" @click="save">Save</button>

      </template>

    </AppDialog>

  </section>

</template>



<script setup>

import { onMounted, reactive, ref } from 'vue'

import PageHeader from '../../components/PageHeader.vue'

import AppDialog from '../../components/ui/AppDialog.vue'
import BuildingSearchSelect from '../../components/ui/BuildingSearchSelect.vue'

import StatusBadge from '../../components/ui/StatusBadge.vue'

import ResponsiveDataList from '../../components/data/ResponsiveDataList.vue'

import TenantNameMenu from '../../components/rental/TenantNameMenu.vue'

import { fetchBuildings, fetchCharges, generateCharges, updateCharge } from '../../api/rental'
import { formatMoney, moneyLabel } from '../../utils/money'


const buildings = ref([])

const charges = ref([])

const showForm = ref(false)

const editing = ref(null)

const error = ref('')

const message = ref('')

const generateError = ref('')

const generating = ref(false)

const now = new Date()

const filters = reactive({

  building_id: '',

  billing_month: now.getMonth() + 1,

  billing_year: now.getFullYear(),

})

const form = reactive({ rent_amount: 0, service_amount: 0, purpose: '' })



const chargeColumns = [

  { key: 'period', label: 'Period', mobileCard: true },

  { key: 'tenant_name', label: 'Tenant', cardTitle: true },

  { key: 'total_amount', label: 'Total', align: 'right', money: true, mobileCard: true },

  { key: 'charge_type', label: 'Type', mobileCard: true },

  { key: 'rent_amount', label: 'Rent', align: 'right', money: true, tabletCard: true },

  { key: 'service_amount', label: 'Service', align: 'right', money: true, tabletCard: true },

  { key: 'building_name', label: 'Building', tabletCard: true },

  { key: 'unit_label', label: 'Unit', tabletCard: true },

]



const months = [

  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },

  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },

  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },

  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },

]







function chargeTypeLabel(type) {

  if (type === 'Water') return 'Water'

  if (type === 'Electricity') return 'Electricity'

  return 'Rent & service'

}



function chargeTypeVariant(type) {

  if (type === 'Water') return 'info'

  if (type === 'Electricity') return 'accent'

  return 'neutral'

}



async function loadBuildings() {

  const response = await fetchBuildings()

  buildings.value = response.data

}



async function load() {

  const params = {}

  if (filters.building_id) params.building_id = filters.building_id

  if (filters.billing_month) params.billing_month = filters.billing_month

  if (filters.billing_year) params.billing_year = filters.billing_year

  const response = await fetchCharges(params)

  charges.value = response.data

}



async function generate() {

  generateError.value = ''

  message.value = ''



  if (!filters.building_id) {

    generateError.value = 'Select a building before generating charges for this month.'

    return

  }



  const billingMonth = Number(filters.billing_month) || now.getMonth() + 1

  const billingYear = Number(filters.billing_year) || now.getFullYear()



  generating.value = true

  try {

    const result = await generateCharges({

      building_id: Number(filters.building_id),

      billing_month: billingMonth,

      billing_year: billingYear,

    })



    filters.billing_month = billingMonth

    filters.billing_year = billingYear



    const batch = result.data

    const period = batch?.period_label ?? `${billingMonth}/${billingYear}`

    const building = batch?.building_name ?? 'selected building'

    message.value = `Draft charge batch created for ${building} · ${period}. Approve it in Charge batches to post charges to tenant balances.`

    await load()

  } catch (e) {

    const validation = e.response?.data?.errors

    generateError.value = validation

      ? Object.values(validation).flat().join(' ')

      : e.response?.data?.message || e.response?.data?.errors?.billing_month?.[0] || 'Could not generate charges.'

  } finally {

    generating.value = false

  }

}



function openEdit(charge) {

  editing.value = charge

  Object.assign(form, {

    rent_amount: charge.rent_amount,

    service_amount: charge.service_amount,

    purpose: charge.purpose || '',

  })

  error.value = ''

  showForm.value = true

}



function closeForm() {

  showForm.value = false

}



async function save() {

  error.value = ''

  try {

    await updateCharge(editing.value.id, form)

    closeForm()

    await load()

  } catch (e) {

    const validation = e.response?.data?.errors

    error.value = validation

      ? Object.values(validation).flat().join(' ')

      : e.response?.data?.message || 'Could not save charge.'

  }

}



onMounted(async () => {

  await loadBuildings()

  await load()

})

</script>


