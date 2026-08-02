<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    class="shrink-0"
    v-html="path"
  />
</template>

<script setup>
import { computed } from 'vue';
import { iconPath } from '../icons';

const props = defineProps({
  name: { type: String, default: 'package' },
  size: { type: [Number, String], default: 20 },
  strokeWidth: { type: [Number, String], default: 1.7 },
});

const path = computed(() => iconPath(props.name));
</script>
