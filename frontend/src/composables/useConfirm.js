import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'default',
  prompt: false,
  promptLabel: '',
  promptValue: '',
  promptRequired: false,
  resolve: null,
})

export function useConfirm() {
  function confirm(options) {
    return new Promise((resolve) => {
      state.title = options.title ?? 'Confirm'
      state.message = options.message ?? ''
      state.confirmLabel = options.confirmLabel ?? 'Confirm'
      state.cancelLabel = options.cancelLabel ?? 'Cancel'
      state.variant = options.variant ?? 'default'
      state.prompt = Boolean(options.prompt)
      state.promptLabel = options.promptLabel ?? ''
      state.promptValue = options.promptValue ?? ''
      state.promptRequired = options.promptRequired ?? false
      state.resolve = resolve
      state.open = true
    })
  }

  function accept(value) {
    if (state.prompt && state.promptRequired && !String(value ?? state.promptValue).trim()) {
      return
    }
    state.open = false
    state.resolve?.(state.prompt ? String(value ?? state.promptValue).trim() : true)
    state.resolve = null
  }

  function cancel() {
    state.open = false
    state.resolve?.(state.prompt ? null : false)
    state.resolve = null
  }

  return {
    state,
    confirm,
    accept,
    cancel,
  }
}
