<template>
  <RouterView />
  <ToastHost />
</template>

<script setup>
import { watch } from 'vue';
import { RouterView } from 'vue-router';
import { applyBrandingToDocument } from './branding';
import ToastHost from './components/ToastHost.vue';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();

watch(
  () => auth.config?.branding,
  (branding) => applyBrandingToDocument(branding || {}),
  { immediate: true, deep: true },
);
</script>
