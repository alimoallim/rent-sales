<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="dialog-overlay"
      role="presentation"
      @click.self="onBackdrop"
    >
      <div
        class="dialog-panel flex flex-col"
        :class="maxWidthClass"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <div v-if="$slots.header || title" class="mb-4 flex shrink-0 items-start justify-between gap-4">
          <div>
            <h3 :id="titleId" class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">
              <slot name="header">{{ title }}</slot>
            </h3>
            <p v-if="subtitle" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="touch-target -mr-1 rounded-md p-1.5 text-zinc-400 transition-all duration-200 hover:bg-zinc-100 hover text-zinc-700 dark:text-zinc-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
            aria-label="Close"
            @click="close"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
          <slot />
        </div>

        <div
          v-if="$slots.footer"
          class="mt-4 flex shrink-0 flex-col-reverse gap-2 border-t border-zinc-200 dark:border-zinc-700 pt-3 sm:flex-row sm:justify-end"
        >
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, useId, watch, onUnmounted } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl'].includes(v),
  },
  closeOnBackdrop: { type: Boolean, default: true },
})

const emit = defineEmits(['update:open', 'close'])

const titleId = useId()

const maxWidthClass = computed(() => {
  const sizes = {
    sm: 'lg:max-w-md',
    md: 'lg:max-w-lg',
    lg: 'lg:max-w-2xl',
    xl: 'lg:max-w-3xl',
    '2xl': 'lg:max-w-5xl',
  }
  return sizes[props.size]
})

function close() {
  emit('update:open', false)
  emit('close')
}

function onBackdrop() {
  if (props.closeOnBackdrop) close()
}

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : ''
  },
)

onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>
