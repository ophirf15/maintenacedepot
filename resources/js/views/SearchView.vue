<template>
  <div class="space-y-5">
    <PageHeader
      :title="term ? `Results for “${term}”` : 'Search'"
      subtitle="Tools, requests, loans, and damage reports."
      icon="search"
    />

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!term"
      icon="search"
      title="Search the depot"
      hint="Press Ctrl+K anywhere, or type a tool name, tag number, or request reference."
    />

    <EmptyState
      v-else-if="!hits.length"
      icon="search"
      title="Nothing matched"
      hint="Try a shorter word, or the tag number printed on the tool."
    />

    <template v-else>
      <section v-for="group in grouped" :key="group.type" class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon :name="group.icon" :size="18" class="text-content-muted" />
          <p class="section-title">{{ group.label }} ({{ group.hits.length }})</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li v-for="hit in group.hits" :key="`${hit.entity_type}-${hit.entity_id}`">
            <RouterLink :to="hit.href" class="flex items-center gap-3 px-4 py-3 hover:bg-surface">
              <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 text-content-muted shrink-0">
                <Icon :name="hit.icon || group.icon" :size="18" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-content truncate">{{ hit.title }}</p>
                <p v-if="hit.snippet" class="text-xs muted truncate">{{ hit.snippet }}</p>
              </div>
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
import Icon from '../components/Icon.vue';
import PageHeader from '../components/PageHeader.vue';
import EmptyState from '../components/EmptyState.vue';

const route = useRoute();
const hits = ref([]);
const loading = ref(false);

const term = computed(() => String(route.query.q || '').trim());

const GROUP_META = {
  item: { label: 'Tools', icon: 'boxes' },
  borrow_request: { label: 'Requests', icon: 'clipboard' },
  loan: { label: 'Loans', icon: 'handshake' },
  ticket: { label: 'Damage reports', icon: 'ticket' },
};

const grouped = computed(() => {
  const order = ['item', 'borrow_request', 'loan', 'ticket'];
  return order
    .map((type) => {
      const meta = GROUP_META[type];
      return {
        type,
        label: meta.label,
        icon: meta.icon,
        hits: hits.value.filter((hit) => hit.entity_type === type),
      };
    })
    .filter((group) => group.hits.length);
});

async function search() {
  if (!term.value) {
    hits.value = [];
    return;
  }

  loading.value = true;
  try {
    const { data } = await api.get('/search', { params: { q: term.value, limit: 40 } });
    hits.value = data.data || [];
  } catch {
    hits.value = [];
  } finally {
    loading.value = false;
  }
}

watch(term, search, { immediate: true });
</script>
