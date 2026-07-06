<template>
  <div class="space-y-5">
    <section>
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Contact</h4>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div v-for="item in contactFields" :key="item.label">
          <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ item.label }}</dt>
          <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">
            <a v-if="item.href" :href="item.href" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ item.value }}</a>
            <span v-else>{{ item.value }}</span>
          </dd>
        </div>
      </dl>
    </section>

    <section>
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Property</h4>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div>
          <dt class="text-xs text-zinc-500 dark:text-zinc-400">Building</dt>
          <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ entity.building_name || '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs text-zinc-500 dark:text-zinc-400">Unit</dt>
          <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ entity.unit_label || '—' }}</dd>
        </div>
      </dl>
    </section>

    <section>
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        {{ module === 'rental' ? 'Rental agreement' : 'Sale terms' }}
      </h4>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div v-for="item in agreementFields" :key="item.label">
          <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ item.label }}</dt>
          <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ item.value }}</dd>
        </div>
      </dl>
    </section>

    <section v-if="hasNextOfKin">
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Next of kin</h4>
      <dl class="grid gap-3 sm:grid-cols-2">
        <div v-for="item in nextOfKinFields" :key="item.label">
          <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ item.label }}</dt>
          <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">
            <a v-if="item.href" :href="item.href" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ item.value }}</a>
            <span v-else>{{ item.value }}</span>
          </dd>
        </div>
      </dl>
    </section>

    <section>
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Documents</h4>
      <DocumentGallery :documents="entity.documents || []" :kinds="documentKinds" />
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DocumentGallery from './DocumentGallery.vue'
import { formatMoney } from '../../utils/money'

const props = defineProps({
  entity: {
    type: Object,
    required: true,
  },
  module: {
    type: String,
    required: true,
    validator: (value) => ['rental', 'sales'].includes(value),
  },
})

const documentKinds = computed(() => (
  props.module === 'rental'
    ? ['photo', 'id_document']
    : ['photo', 'signature', 'id_document']
))

function formatDate(value) {
  if (!value) return '—'
  return new Date(`${value}T12:00:00`).toLocaleDateString('en-KE', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function display(value) {
  return value || '—'
}

const contactFields = computed(() => {
  const items = [
    { label: 'Full name', value: display(props.entity.name) },
    { label: 'Phone', value: display(props.entity.phone), href: props.entity.phone ? `tel:${props.entity.phone}` : null },
    { label: 'Email', value: display(props.entity.email), href: props.entity.email ? `mailto:${props.entity.email}` : null },
    { label: 'ID / passport', value: display(props.entity.passport_or_id) },
  ]

  if (props.entity.gender) {
    items.push({ label: 'Gender', value: props.entity.gender })
  }

  return items
})

const agreementFields = computed(() => {
  if (props.module === 'rental') {
    return [
      { label: 'Agreement start', value: formatDate(props.entity.start_date) },
      { label: 'Deposit', value: formatMoney(props.entity.deposit, 'rental') },
      { label: 'Monthly service charge', value: formatMoney(props.entity.service_amount, 'rental') },
      { label: 'Water meter required', value: props.entity.requires_water_metering ? 'Yes' : 'No' },
      { label: 'Electricity meter required', value: props.entity.requires_electricity_metering ? 'Yes' : 'No' },
      { label: 'Status', value: props.entity.status === 'inactive' ? 'Moved out' : 'Active' },
    ]
  }

  return [
    { label: 'Agreed sale price', value: formatMoney(props.entity.agreed_sale_price, 'sales') },
    { label: 'Deposit', value: formatMoney(props.entity.deposit, 'sales') },
    { label: 'Voucher / reference', value: display(props.entity.voucher_number) },
    { label: 'Registration date', value: formatDate(props.entity.registration_date) },
    { label: 'Status', value: props.entity.status === 'active' ? 'Active' : 'Disabled' },
  ]
})

const nextOfKinFields = computed(() => [
  { label: 'Name', value: display(props.entity.next_of_kin_name) },
  { label: 'Phone', value: display(props.entity.next_of_kin_phone), href: props.entity.next_of_kin_phone ? `tel:${props.entity.next_of_kin_phone}` : null },
  { label: 'ID number', value: display(props.entity.next_of_kin_id) },
  { label: 'Address', value: display(props.entity.next_of_kin_address) },
])

const hasNextOfKin = computed(() => nextOfKinFields.value.some((item) => item.value !== '—'))
</script>
