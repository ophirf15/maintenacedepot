<template>
  <img
    :src="src"
    :alt="alt"
    :class="imgClass"
    draggable="false"
  />
</template>

<script setup>
import { computed } from 'vue';
import { brandingLogoUrl } from '../branding';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  /** mark = app icon square; horizontal = wordmark */
  variant: { type: String, default: 'mark' },
  alt: { type: String, default: 'Maintenance Depot' },
  class: { type: String, default: '' },
});

const auth = useAuthStore();

const src = computed(() =>
  brandingLogoUrl(auth.config?.branding, {
    prefer: props.variant === 'horizontal' ? 'horizontal' : 'mark',
  }),
);

const imgClass = computed(() => {
  if (props.variant === 'horizontal') {
    return ['h-10 w-auto max-w-[14rem] object-contain', props.class].filter(Boolean).join(' ');
  }
  return ['h-full w-full object-contain', props.class].filter(Boolean).join(' ');
});
</script>
