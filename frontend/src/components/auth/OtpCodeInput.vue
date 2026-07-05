<template>
  <div class="otp-code-input">
    <label v-if="label" class="label-field mb-2 block">{{ label }}</label>
    <div class="otp-code-input-grid" role="group" :aria-label="label || 'Verification code'">
      <input
        v-for="(_, index) in digits"
        :key="index"
        :ref="(el) => setInputRef(el, index)"
        type="text"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="1"
        class="otp-code-input-box"
        :class="{ 'otp-code-input-box-error': error }"
        :value="digits[index]"
        :disabled="disabled"
        :aria-label="`Digit ${index + 1} of 6`"
        @input="onInput(index, $event)"
        @keydown="onKeydown(index, $event)"
        @paste="onPaste"
        @focus="onFocus(index)"
      />
    </div>
    <p v-if="error" class="mt-2 text-xs text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
    <p v-else-if="hint" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'complete'])

const inputRefs = ref([])

const digits = computed(() => {
  const chars = props.modelValue.replace(/\D/g, '').slice(0, 6).split('')
  while (chars.length < 6) chars.push('')
  return chars
})

watch(
  () => props.modelValue,
  (value) => {
    if (value.replace(/\D/g, '').length === 6) {
      emit('complete', value.replace(/\D/g, '').slice(0, 6))
    }
  },
)

function setInputRef(el, index) {
  if (el) inputRefs.value[index] = el
}

function updateDigits(nextDigits) {
  const code = nextDigits.join('').replace(/\D/g, '').slice(0, 6)
  emit('update:modelValue', code)
  return code
}

function focusInput(index) {
  nextTick(() => {
    inputRefs.value[index]?.focus()
    inputRefs.value[index]?.select()
  })
}

function onInput(index, event) {
  const value = event.target.value.replace(/\D/g, '')
  const next = [...digits.value]

  if (value.length === 0) {
    next[index] = ''
    updateDigits(next)
    return
  }

  next[index] = value.slice(-1)
  const code = updateDigits(next)

  if (index < 5) {
    focusInput(index + 1)
  } else if (code.length === 6) {
    inputRefs.value[index]?.blur()
  }
}

function onKeydown(index, event) {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    const next = [...digits.value]
    next[index - 1] = ''
    updateDigits(next)
    focusInput(index - 1)
    event.preventDefault()
  }

  if (event.key === 'ArrowLeft' && index > 0) {
    focusInput(index - 1)
    event.preventDefault()
  }

  if (event.key === 'ArrowRight' && index < 5) {
    focusInput(index + 1)
    event.preventDefault()
  }
}

function onPaste(event) {
  event.preventDefault()
  const pasted = event.clipboardData?.getData('text')?.replace(/\D/g, '').slice(0, 6) ?? ''
  if (!pasted) return

  const next = pasted.split('')
  while (next.length < 6) next.push('')
  const code = updateDigits(next)
  focusInput(Math.min(code.length, 5))
}

function onFocus(index) {
  inputRefs.value[index]?.select()
}

function focusFirst() {
  focusInput(0)
}

defineExpose({ focusFirst })
</script>

<style scoped>
.otp-code-input-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.625rem;
}

.otp-code-input-box {
  height: 3rem;
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(228 228 231);
  background: white;
  text-align: center;
  font-size: 1.125rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: rgb(24 24 27);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.otp-code-input-box:focus {
  border-color: rgb(79 70 229);
  box-shadow: 0 0 0 3px rgb(79 70 229 / 0.15);
}

.otp-code-input-box-error {
  border-color: rgb(239 68 68);
}

.otp-code-input-box-error:focus {
  border-color: rgb(239 68 68);
  box-shadow: 0 0 0 3px rgb(239 68 68 / 0.15);
}

:global(.dark) .otp-code-input-box {
  border-color: rgb(63 63 70);
  background: rgb(24 24 27);
  color: rgb(244 244 245);
}
</style>
