<template>
  <AppDialog
    :open="state.open"
    :title="state.title"
    size="sm"
    :close-on-backdrop="false"
    @update:open="onDialogOpen"
  >
    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ state.message }}</p>

    <label v-if="state.prompt" class="mt-4 block text-sm">
      <span class="label-field">{{ state.promptLabel || 'Reason' }}</span>
      <input
        v-model="state.promptValue"
        type="text"
        class="input-field"
        :placeholder="state.promptLabel || 'Enter reason…'"
        @keydown.enter.prevent="accept()"
      />
    </label>

    <template #footer>
      <button type="button" class="btn-secondary w-full sm:w-auto" @click="cancel()">
        {{ state.cancelLabel }}
      </button>
      <button
        type="button"
        class="w-full sm:w-auto"
        :class="state.variant === 'danger' ? 'btn-destructive' : 'btn-primary'"
        @click="accept()"
      >
        {{ state.confirmLabel }}
      </button>
    </template>
  </AppDialog>
</template>

<script setup>
import AppDialog from './AppDialog.vue'
import { useConfirm } from '../../composables/useConfirm'

const { state, accept, cancel } = useConfirm()

function onDialogOpen(open) {
  if (!open) cancel()
}
</script>
