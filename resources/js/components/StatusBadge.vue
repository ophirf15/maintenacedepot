<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
    :class="customStyle ? '' : toneClasses(meta.tone)"
    :style="customStyle"
  >
    <Icon :name="meta.icon" :size="14" :stroke-width="2" />
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';
import { statusMeta, toneClasses } from '../status';

const props = defineProps({
  status: { type: String, default: '' },
  label: { type: String, default: null },
  color: { type: String, default: null },
  icon: { type: String, default: null },
});

const meta = computed(() => {
  const base = statusMeta(props.status);

  return { ...base, icon: props.icon || base.icon };
});

const label = computed(() => props.label || meta.value.label);

// Admin-defined statuses can carry their own colour; honour it when present.
const customStyle = computed(() =>
  props.color
    ? { color: props.color, background: `${props.color}14`, borderColor: `${props.color}33` }
    : null,
);
</script>
