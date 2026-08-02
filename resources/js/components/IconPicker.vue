<template>
  <div class="space-y-2">
    <button
      type="button"
      class="input flex w-full items-center gap-3 text-left"
      @click="open = !open"
    >
      <span
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700"
      >
        <Icon :name="modelValue || suggested" :size="20" />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-content">
          {{ readable(modelValue || suggested) }}
        </span>
        <span class="block text-xs muted">
          {{ modelValue ? 'Tap to change the picture' : 'Chosen automatically — tap to change' }}
        </span>
      </span>
      <Icon :name="open ? 'chevron-up' : 'chevron-down'" :size="18" />
    </button>

    <div v-if="open" class="card space-y-3 p-3">
      <div class="flex items-center gap-2">
        <div class="relative flex-1">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-content-muted">
            <Icon name="search" :size="16" />
          </span>
          <input
            v-model="query"
            type="search"
            class="input pl-9"
            placeholder="Search pictures, e.g. mower, ladder, drill"
          />
        </div>
        <button v-if="modelValue" type="button" class="btn-ghost btn-sm" @click="choose('')">
          <Icon name="refresh" :size="16" />
          Automatic
        </button>
      </div>

      <p v-if="!results.length" class="py-4 text-center text-sm muted">
        No picture matches “{{ query }}”. Try a simpler word like “saw” or “water”.
      </p>

      <div v-else class="grid max-h-64 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
        <button
          v-for="name in results"
          :key="name"
          type="button"
          class="flex flex-col items-center gap-1 rounded-xl border p-2 transition"
          :class="
            name === modelValue
              ? 'border-brand-600 bg-brand-50 text-brand-800'
              : 'border-line text-content-muted hover:border-brand-300 hover:bg-surface'
          "
          :title="readable(name)"
          @click="choose(name)"
        >
          <Icon :name="name" :size="24" />
          <span class="w-full truncate text-[0.6rem] leading-tight">{{ readable(name) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';
import { guessIcon, searchIcons } from '../icons';

const props = defineProps({
  modelValue: { type: String, default: '' },
  /** Name of the thing being iconed, used for the automatic suggestion. */
  suggestFrom: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');

const suggested = computed(() => guessIcon(props.suggestFrom));
const results = computed(() => searchIcons(query.value));

function readable(name) {
  return String(name || '').replace(/-/g, ' ');
}

function choose(name) {
  emit('update:modelValue', name);
  open.value = false;
}
</script>
