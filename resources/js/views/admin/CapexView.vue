<template>
  <div class="space-y-5">
    <PageHeader
      title="Budget plan"
      subtitle="When each machine is likely to need replacing, and what that will cost."
      icon="chart"
    >
      <template #actions>
        <button class="btn-secondary btn-sm" :disabled="downloading" @click="download('excel')">
          <Icon name="download" :size="16" />
          Excel
        </button>
        <button class="btn-secondary btn-sm" :disabled="downloading" @click="download('pdf')">
          <Icon name="file" :size="16" />
          PDF
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="space-y-4">
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <div class="skeleton h-24" />
        <div class="skeleton h-24" />
      </div>
      <div v-for="i in 3" :key="i" class="skeleton h-40" />
    </div>

    <EmptyState
      v-else-if="!rows.length"
      icon="chart"
      title="Nothing to plan yet"
      hint="Add a purchase date or an expected life to your tools and they will show up here."
    />

    <template v-else>
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <StatTile label="Tools in the plan" :value="rows.length" icon="boxes" tone="neutral" />
        <StatTile label="Total cost to replace" :value="money(sumCost(rows))" icon="money" tone="brand" />
      </div>

      <section v-for="(group, year) in grouped" :key="year" class="card overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-line p-4 sm:p-5">
          <div class="flex items-center gap-2">
            <Icon name="calendar" :size="18" class="text-neutral-400" />
            <p class="section-title">Replace in {{ year }}</p>
          </div>
          <p class="text-xs muted">
            {{ group.length }} {{ group.length === 1 ? 'tool' : 'tools' }} · {{ money(sumCost(group)) }}
          </p>
        </header>

        <ul class="divide-rows">
          <li v-for="row in group" :key="row.asset_tag" class="p-3 sm:p-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500">
                <Icon name="package" :size="20" />
              </span>

              <div class="min-w-0 flex-1 basis-48">
                <p class="truncate font-medium text-neutral-900">{{ row.name }}</p>
                <p class="font-mono text-xs muted">{{ row.asset_tag }}</p>
              </div>

              <div class="text-right">
                <p class="font-semibold text-neutral-900">{{ money(row.net_replacement_cost ?? row.replacement_cost) }}</p>
                <p class="text-xs muted">net to replace</p>
              </div>
            </div>

            <div class="mt-2 flex flex-wrap gap-1.5">
              <span
                v-for="reason in reasonList(row)"
                :key="reason"
                class="inline-flex items-center gap-1 rounded-full border border-line bg-neutral-50 px-2 py-0.5 text-[0.7rem] font-semibold text-neutral-700"
              >
                <Icon :name="reasonIcon(reason)" :size="12" />
                {{ reasonLabel(reason) }}
              </span>
            </div>

            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
              <span v-if="row.category || row.tool_type" class="flex items-center gap-1">
                <Icon name="grid" :size="13" />
                {{ [row.category, row.tool_type].filter(Boolean).join(' / ') }}
              </span>
              <span v-if="row.depot" class="flex items-center gap-1">
                <Icon name="pin" :size="13" />
                {{ row.depot }}
              </span>
              <span v-if="row.condition" class="flex items-center gap-1 capitalize">
                <Icon name="star" :size="13" />
                {{ row.condition }}
              </span>
              <span v-if="row.repair_spend" class="flex items-center gap-1">
                <Icon name="wrench" :size="13" />
                Repairs {{ money(row.repair_spend) }}
              </span>
            </p>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatTile from '../../components/StatTile.vue';

const toasts = useToastStore();

const rows = ref([]);
const loading = ref(true);
const downloading = ref(false);

const grouped = computed(() => {
  const map = {};
  for (const row of rows.value) {
    (map[row.planned_replacement_year] ||= []).push(row);
  }

  return map;
});

function sumCost(group) {
  return group.reduce((sum, r) => sum + (Number(r.net_replacement_cost ?? r.replacement_cost) || 0), 0);
}

function money(value) {
  return `$${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

const REASON_LABELS = {
  age: 'Age / lifespan',
  eol: 'End of life flag',
  condition: 'Poor condition',
  usage: 'High usage',
  repair_spend: 'High repair cost',
};

const REASON_ICONS = {
  age: 'calendar',
  eol: 'alert',
  condition: 'star',
  usage: 'truck',
  repair_spend: 'wrench',
};

function reasonList(row) {
  if (Array.isArray(row.suggest_replace_reasons)) return row.suggest_replace_reasons;
  if (row.suggest_replace_reason) return String(row.suggest_replace_reason).split(',').map((s) => s.trim());

  return ['age'];
}

function reasonLabel(reason) {
  return REASON_LABELS[reason] || reason;
}

function reasonIcon(reason) {
  return REASON_ICONS[reason] || 'info';
}

async function download(kind) {
  downloading.value = true;
  try {
    const response = await api.get(`/capex/export/${kind}`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `depot-capex-plan.${kind === 'excel' ? 'xlsx' : 'pdf'}`;
    a.click();
    URL.revokeObjectURL(url);
    toasts.success('Download started.');
  } catch {
    toasts.error('Could not make the file. Try again in a moment.');
  } finally {
    downloading.value = false;
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/capex/forecast');
    rows.value = data.data;
  } catch {
    rows.value = [];
    toasts.error('Could not load the budget plan.');
  } finally {
    loading.value = false;
  }
});
</script>
