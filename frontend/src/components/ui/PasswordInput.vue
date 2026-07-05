<template>
  <div class="password-input">
    <input
      :id="id"
      :value="modelValue"
      :type="isVisible ? 'text' : 'password'"
      :autocomplete="autocomplete"
      :required="required"
      :disabled="disabled"
      :placeholder="placeholder"
      class="input-field password-input-field"
      :class="{ 'input-field-error': invalid }"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <button
      type="button"
      class="password-input-toggle"
      :aria-label="isVisible ? 'Hide password' : 'Show password'"
      :aria-pressed="isVisible"
      :disabled="disabled"
      @click="toggleVisible"
    >
      <svg v-if="isVisible" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
      </svg>
      <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  visible: { type: Boolean, default: undefined },
  id: { type: String, default: '' },
  autocomplete: { type: String, default: 'current-password' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:visible'])

const internalVisible = ref(false)

const isControlled = computed(() => props.visible !== undefined)

const isVisible = computed(() => (isControlled.value ? props.visible : internalVisible.value))

function toggleVisible() {
  const next = !isVisible.value
  if (isControlled.value) {
    emit('update:visible', next)
  } else {
    internalVisible.value = next
  }
}

watch(
  () => props.visible,
  (value) => {
    if (value !== undefined) internalVisible.value = value
  },
)
</script>

<style scoped>
.password-input {
  position: relative;
}

.password-input-field {
  padding-right: 2.5rem;
}

.password-input-toggle {
  position: absolute;
  right: 0.625rem;
  top: 50%;
  margin-top: 0.125rem;
  transform: translateY(-50%);
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  color: rgb(113 113 122);
  transition: color 0.15s ease, background-color 0.15s ease;
}

.password-input-toggle:hover:not(:disabled) {
  color: rgb(63 63 70);
  background: rgb(244 244 245);
}

.password-input-toggle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

:global(.dark) .password-input-toggle:hover:not(:disabled) {
  color: rgb(212 212 216);
  background: rgb(39 39 42);
}
</style>
