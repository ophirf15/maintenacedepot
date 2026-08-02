<template>
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="flex items-start gap-3 min-w-0">
      <span
        v-if="icon"
        class="hidden sm:flex h-11 w-11 items-center justify-center rounded-xl bg-ink-900 text-white shrink-0"
      >
        <Icon :name="icon" :size="22" />
      </span>
      <div class="min-w-0">
        <RouterLink
          v-if="backTo"
          :to="backTo"
          class="inline-flex items-center gap-1 text-sm font-medium text-neutral-500 hover:text-neutral-900 mb-1"
        >
          <Icon name="chevron-left" :size="16" />
          {{ backLabel }}
        </RouterLink>
        <h1 class="page-title truncate">{{ title }}</h1>
        <p v-if="subtitle" class="text-sm muted mt-1">{{ subtitle }}</p>
      </div>
    </div>
    <div class="flex items-center gap-2 shrink-0">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
import Icon from './Icon.vue';

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  backTo: { type: [String, Object], default: null },
  backLabel: { type: String, default: 'Back' },
});
</script>
