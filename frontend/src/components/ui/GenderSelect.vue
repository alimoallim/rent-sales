<template>
  <select
    :value="modelValue"
    class="input-field"
    :required="required"
    @change="onChange"
  >
    <option value="">{{ placeholder }}</option>
    <option v-for="option in options" :key="option" :value="option">
      {{ option }}
    </option>
  </select>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  required: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Select gender' },
})

const emit = defineEmits(['update:modelValue'])

const options = ['Male', 'Female']

function onChange(event) {
  emit('update:modelValue', event.target.value)
}
</script>
