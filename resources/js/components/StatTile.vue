<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to"
    class="card-pad card-hover flex items-start gap-2.5 sm:gap-3 min-h-[4.75rem]"
  >
    <span
      class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl shrink-0"
      :class="accentClasses"
    >
      <Icon :name="icon" :size="19" />
    </span>
    <div class="min-w-0 flex-1">
      <p class="text-xl sm:text-2xl font-semibold leading-none tracking-tight text-neutral-900">{{ value }}</p>
      <p class="text-[0.8rem] sm:text-sm font-medium text-neutral-700 mt-1 leading-snug line-clamp-2">{{ label }}</p>
      <p v-if="hint" class="hidden sm:block text-xs muted mt-0.5 truncate">{{ hint }}</p>
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
