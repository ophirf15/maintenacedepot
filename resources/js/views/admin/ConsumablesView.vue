<template>
  <div class="space-y-5">
    <PageHeader
      title="Consumables"
      subtitle="On-hand stock for rods, blades, cartridges, and other used-up supplies."
    />

    <form class="flex flex-wrap items-end gap-3" @submit.prevent="load">
      <div class="min-w-[14rem] flex-1">
        <label class="label" for="cons-q">Search</label>
        <input id="cons-q" v-model="q" type="search" class="input" placeholder="Name, supplier, or part number" />
      </div>
      <label class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2.5 text-sm">
        <input v-model="lowStockOnly" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
        Low stock only
      </label>
      <button type="submit" class="btn-secondary">
        <Icon name="search" :size="16" />
        Search
      </button>
    </form>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="boxes"
      title="No consumables yet"
      hint="Add an item with “It gets used up” ticked on the Equipment list."
    />

    <ul v-else class="space-y-3">
      <li v-for="item in items" :key="item.id" class="card p-4 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <router-link :to="`/inventory/items/${item.id}`" class="font-semibold text-neutral-900 hover:underline">
              {{ item.label || item.name }}
            </router-link>
            <p class="mt-0.5 text-xs muted">
              {{ item.tool_type?.name || 'Consumable' }}
              <span v-if="item.supplier_name"> · {{ item.supplier_name }}</span>
              <span v-if="item.supplier_part_number"> · #{{ item.supplier_part_number }}</span>
            </p>
          </div>
          <div class="text-right">
            <p
              class="text-xl font-semibold tabular-nums"
              :class="Number(item.stock_qty) <= Number(item.reorder_point || 0) ? 'text-warn-600' : 'text-neutral-900'"
            >
              {{ Number(item.stock_qty) }}
              <span class="text-sm font-normal muted">{{ item.stock_unit || 'ea' }}</span>
            </p>
            <p class="text-xs muted">Reorder at {{ Number(item.reorder_point || 0) }}</p>
            <p v-if="item.typical_cost != null" class="text-xs muted">~${{ Number(item.typical_cost).toFixed(2) }} each</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-secondary btn-sm" @click="openRestock(item)">
            <Icon name="plus" :size="15" />
            Restock
          </button>
          <button type="button" class="btn-ghost btn-sm" @click="openAdjust(item)">
            Set qty
          </button>
        </div>

        <div v-if="activeId === item.id" class="rounded-xl border border-line bg-neutral-50 p-3 space-y-2">
          <label class="label">{{ mode === 'restock' ? 'Add quantity' : 'Set on-hand quantity' }}</label>
          <input v-model.number="qtyInput" type="number" min="0" step="0.01" class="input" />
          <input v-model="notesInput" type="text" class="input" placeholder="Notes (optional)" />
          <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary btn-sm" :disabled="saving" @click="saveStock">
              {{ saving ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" class="btn-ghost btn-sm" @click="activeId = null">Cancel</button>
          </div>
          <p v-if="error" class="text-sm text-danger-600">{{ error }}</p>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import Icon from '../../components/Icon.vue';

const toasts = useToastStore();
const items = ref([]);
const loading = ref(true);
const q = ref('');
const lowStockOnly = ref(false);
const activeId = ref(null);
const mode = ref('restock');
const qtyInput = ref(0);
const notesInput = ref('');
const saving = ref(false);
const error = ref('');

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/stock/consumables', {
      params: {
        ...(q.value ? { q: q.value } : {}),
        ...(lowStockOnly.value ? { low_stock: 1 } : {}),
        per_page: 100,
      },
    });
    items.value = data.data.data || data.data || [];
  } catch {
    items.value = [];
    toasts.error('Could not load consumables.');
  } finally {
    loading.value = false;
  }
}

function openRestock(item) {
  activeId.value = item.id;
  mode.value = 'restock';
  qtyInput.value = Number(item.reorder_qty || 0) || 1;
  notesInput.value = '';
  error.value = '';
}

function openAdjust(item) {
  activeId.value = item.id;
  mode.value = 'adjust';
  qtyInput.value = Number(item.stock_qty || 0);
  notesInput.value = '';
  error.value = '';
}

async function saveStock() {
  if (!activeId.value) return;
  saving.value = true;
  error.value = '';
  try {
    const path = mode.value === 'restock'
      ? `/items/${activeId.value}/stock/restock`
      : `/items/${activeId.value}/stock/adjust`;
    await api.post(path, { qty: qtyInput.value, notes: notesInput.value || undefined });
    toasts.success(mode.value === 'restock' ? 'Stock restocked.' : 'Stock updated.');
    activeId.value = null;
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Could not update stock.';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
