import { reactive } from 'vue'

const state = reactive({
  items: [],
})

let nextId = 1

export function useToast() {
  function dismiss(id) {
    const index = state.items.findIndex((t) => t.id === id)
    if (index !== -1) state.items.splice(index, 1)
  }

  function show(message, { variant = 'info', duration = 4500 } = {}) {
    const id = nextId++
    state.items.push({ id, message, variant })
    if (duration > 0) {
      window.setTimeout(() => dismiss(id), duration)
    }
    return id
  }

  return {
    toasts: state.items,
    success: (message, opts) => show(message, { ...opts, variant: 'success' }),
    error: (message, opts) => show(message, { ...opts, variant: 'error', duration: 6000 }),
    warning: (message, opts) => show(message, { ...opts, variant: 'warning' }),
    info: (message, opts) => show(message, { ...opts, variant: 'info' }),
    dismiss,
  }
}
