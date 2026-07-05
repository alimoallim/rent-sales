<template>
  <ol class="password-reset-steps" aria-label="Password reset progress">
    <li
      v-for="(step, index) in steps"
      :key="step.id"
      class="password-reset-step"
      :class="{
        'password-reset-step-active': currentStep === step.id,
        'password-reset-step-complete': currentStep > step.id,
      }"
    >
      <span class="password-reset-step-marker" aria-hidden="true">
        <svg v-if="currentStep > step.id" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 0 1.42l-7.25 7.25a1 1 0 0 1-1.42 0l-3.25-3.25a1 1 0 1 1 1.42-1.42l2.54 2.54 6.54-6.54a1 1 0 0 1 1.42 0Z" clip-rule="evenodd" />
        </svg>
        <span v-else>{{ index + 1 }}</span>
      </span>
      <span class="password-reset-step-label">{{ step.label }}</span>
    </li>
  </ol>
</template>

<script setup>
defineProps({
  currentStep: { type: Number, required: true },
  steps: {
    type: Array,
    default: () => [
      { id: 1, label: 'Email' },
      { id: 2, label: 'Verify' },
      { id: 3, label: 'Password' },
    ],
  },
})
</script>

<style scoped>
.password-reset-steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.password-reset-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  text-align: center;
}

.password-reset-step-marker {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgb(228 228 231);
  background: rgb(250 250 250);
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(113 113 122);
}

.password-reset-step-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgb(113 113 122);
}

.password-reset-step-active .password-reset-step-marker {
  border-color: rgb(79 70 229);
  background: rgb(79 70 229);
  color: white;
}

.password-reset-step-active .password-reset-step-label {
  color: rgb(67 56 202);
}

.password-reset-step-complete .password-reset-step-marker {
  border-color: rgb(16 185 129);
  background: rgb(16 185 129);
  color: white;
}

.password-reset-step-complete .password-reset-step-label {
  color: rgb(5 150 105);
}

:root.dark .password-reset-step-marker,
:global(.dark) .password-reset-step-marker {
  border-color: rgb(63 63 70);
  background: rgb(39 39 42);
  color: rgb(161 161 170);
}

:root.dark .password-reset-step-label,
:global(.dark) .password-reset-step-label {
  color: rgb(161 161 170);
}

:root.dark .password-reset-step-active .password-reset-step-label,
:global(.dark) .password-reset-step-active .password-reset-step-label {
  color: rgb(165 180 252);
}
</style>
