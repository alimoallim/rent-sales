<template>
  <div ref="root" class="searchable-select" :class="{ 'searchable-select-open': open }">
    <div class="searchable-select-control">
      <input
        ref="inputRef"
        type="text"
        class="input-field searchable-select-input"
        :class="{ 'searchable-select-input-placeholder': !open && !query && !selectedOption }"
        :value="inputValue"
        :placeholder="open ? searchPlaceholder : (selectedOption?.label || placeholder)"
        :disabled="disabled"
        :aria-expanded="open"
        :aria-controls="listboxId"
        :aria-activedescendant="activeOptionId"
        role="combobox"
        autocomplete="off"
        @focus="onFocus"
        @input="onInput"
        @keydown="onKeydown"
      />
      <button
        type="button"
        class="searchable-select-toggle"
        tabindex="-1"
        :disabled="disabled"
        :aria-label="open ? 'Close list' : 'Open list'"
        @click="toggle"
      >
        <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
      </button>
      <input
        v-if="required"
        class="searchable-select-hidden"
        tabindex="-1"
        :value="hasValue ? '1' : ''"
        required
        aria-hidden="true"
      />
    </div>

    <div v-if="open" class="searchable-select-panel">
      <p v-if="filteredOptions.length === 0" class="searchable-select-empty">No matches found</p>
      <ul
        v-else
        :id="listboxId"
        class="searchable-select-list"
        role="listbox"
      >
        <li
          v-for="(option, index) in filteredOptions"
          :id="`${listboxId}-option-${index}`"
          :key="String(option.value)"
          role="option"
          class="searchable-select-option"
          :class="{ 'searchable-select-option-active': index === highlightedIndex }"
          :aria-selected="isSelected(option)"
          @mousedown.prevent="selectOption(option)"
          @mouseenter="highlightedIndex = index"
        >
          <span class="searchable-select-option-label">{{ option.label }}</span>
          <span v-if="option.hint" class="searchable-select-option-hint">{{ option.hint }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select…' },
  searchPlaceholder: { type: String, default: 'Type to search…' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  clearable: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const root = ref(null)
const inputRef = ref(null)
const open = ref(false)
const query = ref('')
const highlightedIndex = ref(0)
const listboxId = `searchable-select-${Math.random().toString(36).slice(2, 9)}`

const selectedOption = computed(() =>
  props.options.find((option) => valuesMatch(option.value, props.modelValue)) ?? null,
)

const hasValue = computed(() => props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined)

const filteredOptions = computed(() => {
  const term = query.value.trim().toLowerCase()
  if (!term) return props.options

  return props.options.filter((option) => {
    const haystack = [option.label, option.hint, option.keywords]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(term)
  })
})

const inputValue = computed(() => {
  if (open.value) return query.value
  return selectedOption.value?.label ?? ''
})

const activeOptionId = computed(() => {
  if (!open.value || filteredOptions.value.length === 0) return undefined
  return `${listboxId}-option-${highlightedIndex.value}`
})

watch(
  () => props.modelValue,
  () => {
    if (!open.value) query.value = ''
  },
)

watch(filteredOptions, (options) => {
  if (highlightedIndex.value >= options.length) {
    highlightedIndex.value = Math.max(0, options.length - 1)
  }
})

function valuesMatch(a, b) {
  return String(a) === String(b)
}

function isSelected(option) {
  return valuesMatch(option.value, props.modelValue)
}

function onFocus() {
  if (props.disabled) return
  openDropdown()
}

function openDropdown() {
  if (props.disabled) return
  open.value = true
  query.value = ''
  highlightedIndex.value = Math.max(
    0,
    filteredOptions.value.findIndex((option) => isSelected(option)),
  )
}

function closeDropdown() {
  open.value = false
  query.value = ''
}

function toggle() {
  if (props.disabled) return
  if (open.value) {
    closeDropdown()
    inputRef.value?.blur()
  } else {
    openDropdown()
    nextTick(() => inputRef.value?.focus())
  }
}

function onInput(event) {
  query.value = event.target.value
  open.value = true
  highlightedIndex.value = 0
}

function selectOption(option) {
  emit('update:modelValue', option.value)
  emit('change', option.value)
  closeDropdown()
  inputRef.value?.blur()
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    closeDropdown()
    inputRef.value?.blur()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!open.value) {
      openDropdown()
      return
    }
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!open.value) {
      openDropdown()
      return
    }
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
    return
  }

  if (event.key === 'Enter' && open.value) {
    event.preventDefault()
    const option = filteredOptions.value[highlightedIndex.value]
    if (option) selectOption(option)
    return
  }

  if (event.key === 'Tab') {
    closeDropdown()
  }
}

function onDocumentClick(event) {
  if (!root.value?.contains(event.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>
