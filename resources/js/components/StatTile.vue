<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to"
    class="card-pad card-hover flex items-start gap-3"
  >
    <span
      class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
      :class="accentClasses"
    >
      <Icon :name="icon" :size="20" />
    </span>
    <div class="min-w-0">
      <p class="text-2xl font-semibold leading-none tracking-tight text-neutral-900">{{ value }}</p>
      <p class="text-sm font-medium text-neutral-700 mt-1.5 truncate">{{ label }}</p>
      <p v-if="hint" class="text-xs muted truncate">{{ hint }}</p>
    </div>
  </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import Icon from './Icon.vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '–' },
  icon: { type: String, default: 'package' },
  hint: { type: String, default: '' },
  tone: { type: String, default: 'neutral' },
  to: { type: [String, Object], default: null },
});

const TONE_CLASSES = {
  neutral: 'bg-neutral-100 text-neutral-600',
  brand: 'bg-brand-100 text-brand-700',
  info: 'bg-info-100 text-info-600',
  warn: 'bg-warn-100 text-warn-600',
  danger: 'bg-danger-100 text-danger-600',
};

const accentClasses = computed(() => TONE_CLASSES[props.tone] || TONE_CLASSES.neutral);
</script>
