<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-24 sm:bottom-6 z-50 flex flex-col items-center gap-2 px-4">
    <TransitionGroup name="toast">
      <div
        v-for="item in toasts.items"
        :key="item.id"
        class="pointer-events-auto flex max-w-sm items-center gap-2.5 rounded-full border px-4 py-2.5 text-sm font-medium shadow-lg"
        :class="TONE_CLASSES[item.tone] || TONE_CLASSES.brand"
      >
        <Icon v-if="item.icon" :name="item.icon" :size="18" :stroke-width="2" />
        <span>{{ item.message }}</span>
        <button class="opacity-60 hover:opacity-100" aria-label="Dismiss" @click="toasts.dismiss(item.id)">
          <Icon name="x" :size="15" :stroke-width="2.2" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import Icon from './Icon.vue';
import { useToastStore } from '../stores/toast';

const toasts = useToastStore();

const TONE_CLASSES = {
  brand: 'bg-white border-brand-700/20 text-brand-700',
  danger: 'bg-white border-danger-600/25 text-danger-600',
  info: 'bg-white border-info-600/20 text-info-600',
  warn: 'bg-white border-warn-600/25 text-warn-600',
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.22s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px) scale(0.97);
}
</style>
