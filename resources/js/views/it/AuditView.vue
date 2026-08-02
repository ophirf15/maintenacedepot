<template>
  <div class="space-y-5">
    <PageHeader
      title="Activity log"
      subtitle="Everything that happened in the app, with who did it and when."
      icon="history"
    >
      <template #actions>
        <button class="btn-secondary btn-sm" :disabled="exporting" @click="exportCsv">
          <Icon name="download" :size="16" />
          Download
        </button>
      </template>
    </PageHeader>

    <section class="card overflow-hidden">
      <header class="flex items-center gap-2 border-b border-line p-4 sm:p-5">
        <Icon name="filter" :size="18" class="text-content-muted" />
        <p class="section-title">Narrow it down</p>
      </header>
      <div class="grid gap-3 p-4 sm:p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="label">What happened</label>
          <input v-model="filters.event" type="text" class="input" placeholder="created" @keyup.enter="load()" />
        </div>
        <div>
          <label class="label">What it was about</label>
          <input v-model="filters.auditable_type" type="text" class="input" placeholder="Item" @keyup.enter="load()" />
        </div>
        <div>
          <label class="label">From</label>
          <input v-model="filters.from" type="date" class="input" @change="load()" />
        </div>
        <div>
          <label class="label">To</label>
          <input v-model="filters.until" type="date" class="input" @change="load()" />
        </div>
      </div>
    </section>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 6" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!events.length"
      icon="history"
      title="Nothing recorded"
      hint="No activity matches these filters. Try clearing the dates."
    />

    <template v-else>
      <ul class="card divide-rows overflow-hidden">
        <li v-for="e in events" :key="e.id" class="p-3 sm:p-4 hover:bg-surface">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-content-muted">
              <Icon name="user" :size="19" />
            </span>

            <div class="min-w-0 flex-1 basis-48">
              <p class="truncate font-medium text-content">{{ e.user?.name || e.actor_label || 'The system' }}</p>
              <p class="flex items-center gap-1.5 text-xs muted">
                <Icon name="clock" :size="13" />
                {{ formatDateTime(e.occurred_at) }}
              </p>
            </div>

            <StatusBadge :status="e.event" />
          </div>

          <p v-if="e.description" class="mt-2 text-sm text-content-muted">{{ e.description }}</p>

          <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
            <span class="flex items-center gap-1">
              <Icon name="file" :size="13" />
              <span class="font-mono">{{ entityLabel(e) }}</span>
            </span>
            <span v-if="e.ip_address" class="flex items-center gap-1">
              <Icon name="pin" :size="13" />
              <span class="font-mono">{{ e.ip_address }}</span>
            </span>
          </p>
        </li>
      </ul>

      <div v-if="meta" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm muted">
          Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} entries
        </p>
        <div class="flex gap-2">
          <button class="btn-secondary btn-sm" :disabled="!meta.prev_page_url" @click="changePage(meta.current_page - 1)">
            <Icon name="chevron-left" :size="16" />
            Back
          </button>
          <button class="btn-secondary btn-sm" :disabled="!meta.next_page_url" @click="changePage(meta.current_page + 1)">
            Next
            <Icon name="chevron-right" :size="16" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const toasts = useToastStore();

const events = ref([]);
const meta = ref(null);
const loading = ref(true);
const exporting = ref(false);
const filters = reactive({ event: '', auditable_type: '', from: '', until: '' });

function formatDateTime(value) {
  return value ? new Date(value).toLocaleString() : '—';
}

function entityLabel(e) {
  if (!e.auditable_type) return '—';
  const short = e.auditable_type.split('\\').pop();

  return `${short}#${e.auditable_id}`;
}

function activeFilters() {
  return Object.entries(filters).reduce((params, [key, value]) => {
    if (value) params[key] = value;

    return params;
  }, {});
}

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await api.get('/audit', { params: { page, ...activeFilters() } });
    events.value = data.data.data;
    meta.value = data.data;
  } catch {
    events.value = [];
    meta.value = null;
    toasts.error('Could not load the activity log.');
  } finally {
    loading.value = false;
  }
}

function changePage(page) {
  if (page < 1) return;
  load(page);
}

async function exportCsv() {
  exporting.value = true;
  try {
    const response = await api.get('/audit/export', { params: activeFilters(), responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-log-${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toasts.success('Download started.');
  } catch {
    toasts.error('Could not download the activity log.');
  } finally {
    exporting.value = false;
  }
}

onMounted(() => load());
</script>
