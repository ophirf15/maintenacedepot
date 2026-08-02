<template>
  <div class="space-y-5">
    <PageHeader
      title="Browse tools"
      subtitle="Pick a group, then choose what you need."
      icon="grid"
    >
      <template #actions>
        <RouterLink to="/cart" class="btn-secondary btn-sm">
          <Icon name="toolbag" :size="17" />
          Tool bag
          <span
            v-if="cart.lines.length"
            class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-solid px-1 text-[0.65rem] font-bold text-white"
          >
            {{ cart.lines.length }}
          </span>
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
      <div v-for="i in 8" :key="i" class="skeleton h-40" />
    </div>

    <EmptyState
      v-else-if="error"
      icon="alert"
      title="Could not load the tool list"
      :hint="error"
    >
      <button class="btn-secondary btn-sm" @click="load">
        <Icon name="refresh" :size="16" />
        Try again
      </button>
    </EmptyState>

    <EmptyState
      v-else-if="!categories.length"
      icon="boxes"
      title="No tool groups yet"
      hint="Ask the depot admin to add categories and equipment."
    />

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
      <RouterLink
        v-for="cat in categories"
        :key="cat.id"
        :to="`/catalog/${cat.id}`"
        class="card card-hover group flex flex-col items-center gap-3 p-5 text-center"
      >
        <span
          class="flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-sm"
          :style="{ background: badgeColor(cat) }"
        >
          <Icon :name="iconFor(cat)" :size="30" :stroke-width="1.6" />
        </span>
        <div class="min-w-0">
          <p class="font-semibold text-content leading-tight">{{ cat.name }}</p>
          <p class="mt-1.5 text-xs font-medium" :class="cat.available_count ? 'text-brand-700' : 'text-warn-600'">
            <span v-if="cat.available_count">{{ cat.available_count }} ready to borrow</span>
            <span v-else>All out right now</span>
          </p>
          <p class="text-[0.7rem] text-content-muted">{{ cat.total_count }} total</p>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { useCartStore } from '../../stores/auth';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import { iconFor } from '../../icons';

const cart = useCartStore();
const categories = ref([]);
const loading = ref(true);
const error = ref('');

const FALLBACK_COLORS = ['#15694f', '#b45309', '#1570cd', '#7c3aed', '#0f766e', '#be123c'];

function badgeColor(cat) {
  if (cat.color) return cat.color;

  return FALLBACK_COLORS[(cat.id || 0) % FALLBACK_COLORS.length];
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/catalog/categories');
    categories.value = data.data;
  } catch {
    error.value = 'Check your connection and try again.';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
