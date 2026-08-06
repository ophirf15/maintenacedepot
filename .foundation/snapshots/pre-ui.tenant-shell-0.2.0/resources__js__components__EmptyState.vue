<template>
  <div class="card flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-neutral-100 text-content-muted">
      <Icon :name="icon" :size="28" />
    </span>
    <div>
      <p class="font-semibold text-content">{{ title }}</p>
      <p v-if="hint" class="text-sm muted mt-1 max-w-sm">{{ hint }}</p>
    </div>
    <slot />
  </div>
</template>

<script setup>
import Icon from './Icon.vue';

defineProps({
  icon: { type: String, default: 'package' },
  title: { type: String, required: true },
  hint: { type: String, default: '' },
});
</script>
