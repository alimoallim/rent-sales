<template>
  <Teleport to="body">
    <div
      class="pointer-events-none fixed inset-x-0 bottom-0 z-[100] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
      aria-live="polite"
      aria-relevant="additions"
    >
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-md"
          :class="variantClass(toast.variant)"
          role="status"
        >
          <p class="flex-1 text-sm font-medium">{{ toast.message }}</p>
          <button
            type="button"
            class="shrink-0 rounded p-0.5 opacity-70 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            aria-label="Dismiss"
            @click="dismiss(toast.id)"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '../../composables/useToast'

const { toasts, dismiss } = useToast()

function variantClass(variant) {
  return ({
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/80 dark:bg-emerald-950/90 dark:text-emerald-200',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/80 dark:bg-red-950/90 dark:text-red-200',
    warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800/80 dark:bg-amber-950/90 dark:text-amber-200',
    info: 'border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100',
  })[variant] ?? 'border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100'
}
</script>
