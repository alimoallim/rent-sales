<template>
  <RouterView />
  <AppToast />
  <ConfirmDialog />
</template>

<script setup>
import AppToast from './components/ui/AppToast.vue'
import ConfirmDialog from './components/ui/ConfirmDialog.vue'
</script>
