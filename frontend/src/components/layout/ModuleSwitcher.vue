<template>
  <div
    class="module-switcher"
    :class="compact ? 'module-switcher-compact' : 'module-switcher-full'"
    role="group"
    aria-label="Switch module"
  >
    <button
      type="button"
      class="module-switcher-option"
      :class="{ 'module-switcher-option-active': currentModule === 'rental' }"
      :aria-pressed="currentModule === 'rental'"
      @click="$emit('switch', 'rental')"
    >
      {{ compact ? 'Rent' : 'Rental' }}
    </button>
    <button
      type="button"
      class="module-switcher-option"
      :class="{ 'module-switcher-option-active': currentModule === 'sales' }"
      :aria-pressed="currentModule === 'sales'"
      @click="$emit('switch', 'sales')"
    >
      {{ compact ? 'Sale' : 'Sales' }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  currentModule: { type: String, default: 'rental' },
  compact: { type: Boolean, default: false },
})

defineEmits(['switch'])
</script>
