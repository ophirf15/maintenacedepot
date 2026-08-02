<template>
  <div class="space-y-5">
    <PageHeader :title="term ? `Results for “${term}”` : 'Search'" subtitle="Tools and requests that match." icon="search" />

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!term"
      icon="search"
      title="Type something in the search box"
      hint="Look for a tool name, a tag number, or a request number."
    />

    <EmptyState
      v-else-if="!items.length && !requests.length"
      icon="search"
      title="Nothing matched"
      hint="Try a shorter word, or the tag number printed on the tool."
    />

    <template v-else>
      <section v-if="items.length" class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon name="boxes" :size="18" class="text-neutral-400" />
          <p class="section-title">Tools ({{ items.length }})</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li v-for="item in items" :key="item.id">
            <RouterLink :to="itemLink(item)" class="flex items-center gap-3 px-4 py-3 hover:bg-neutral-50">
              <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500 shrink-0">
                <Icon :name="iconForItem(item)" :size="18" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ item.label || item.asset_tag }}</p>
                <p class="text-xs muted font-mono">{{ item.asset_tag }} · {{ item.depot?.name || 'No depot' }}</p>
              </div>
              <StatusBadge :status="item.status?.slug" :label="item.status?.name" :color="item.status?.color" />
              <Icon name="chevron-right" :size="16" class="text-neutral-300" />
            </RouterLink>
          </li>
        </ul>
      </section>

      <section v-if="requests.length" class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon name="clipboard" :size="18" class="text-neutral-400" />
          <p class="section-title">Requests ({{ requests.length }})</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li v-for="r in requests" :key="r.id">
            <RouterLink :to="`/requests/${r.id}`" class="flex items-center gap-3 px-4 py-3 hover:bg-neutral-50">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ r.summary }}</p>
                <p class="text-xs muted font-mono">{{ r.reference }}</p>
              </div>
              <StatusBadge :status="r.status" />
              <Icon name="chevron-right" :size="16" class="text-neutral-300" />
            </RouterLink>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../api';
import { useAuthStore } from '../stores/auth';
import Icon from '../components/Icon.vue';
import PageHeader from '../components/PageHeader.vue';
import EmptyState from '../components/EmptyState.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { iconForItem } from '../icons';

const route = useRoute();
const auth = useAuthStore();

const items = ref([]);
const requests = ref([]);
const loading = ref(false);

const term = computed(() => String(route.query.q || '').trim());
const canSeeInventory = computed(() => auth.can('manage_inventory') || auth.can('manage_catalog'));

function itemLink(item) {
  return canSeeInventory.value
    ? `/inventory/items/${item.id}`
    : `/catalog/${item.tool_type?.category_id || ''}`;
}

async function search() {
  if (!term.value) {
    items.value = [];
    requests.value = [];
    return;
  }

  loading.value = true;
  const needle = term.value.toLowerCase();

  const [itemResult, requestResult] = await Promise.allSettled([
    api.get('/items', { params: { q: term.value, per_page: 10 } }),
    api.get('/borrow-requests', { params: { per_page: 50 } }),
  ]);

  items.value = itemResult.status === 'fulfilled' ? itemResult.value.data.data.data || [] : [];

  requests.value =
    requestResult.status === 'fulfilled'
      ? (requestResult.value.data.data.data || []).filter((r) =>
          `${r.reference} ${r.summary}`.toLowerCase().includes(needle),
        )
      : [];

  loading.value = false;
}

watch(term, search, { immediate: true });
</script>
