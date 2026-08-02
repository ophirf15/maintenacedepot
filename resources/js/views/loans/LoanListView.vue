<template>
  <div class="space-y-5">
    <PageHeader
      :title="isDepotStaff ? 'Active loans' : 'My loans'"
      subtitle="Open a loan to confirm pick-up, submit a return, or inspect returned tools."
      icon="handshake"
    >
      <template #actions>
        <RouterLink to="/scan" class="btn-secondary btn-sm">
          <Icon name="scan" :size="17" />
          Scan pick-up or return
        </RouterLink>
      </template>
    </PageHeader>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in statusOptions"
        :key="opt.value"
        type="button"
        class="chip"
        :class="isActive(opt) ? 'chip-active' : ''"
        @click="setFilter(opt)"
      >
        <Icon :name="opt.icon" :size="16" />
        {{ opt.label }}
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-24" />
    </div>

    <EmptyState
      v-else-if="!loans.length"
      icon="handshake"
      title="Nothing here"
      hint="No loans match this filter. Try another one, or borrow a tool."
    >
      <RouterLink to="/catalog" class="btn-primary btn-sm">
        <Icon name="grid" :size="17" />
        Browse tools
      </RouterLink>
    </EmptyState>

    <ul v-else class="space-y-3">
      <li v-for="l in loans" :key="l.id">
        <RouterLink
          :to="`/loans/${l.id}`"
          class="card card-hover flex items-start gap-3 p-4"
          :class="isOverdue(l) ? 'border-l-4 border-l-danger-600' : ''"
        >
          <span
            class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0"
            :class="tileClasses(l)"
            aria-hidden="true"
          >
            <Icon :name="tileIcon(l)" :size="21" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="font-semibold text-content leading-snug">{{ l.summary || l.items_label || 'Loan' }}</p>

            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
              <span class="font-mono">{{ l.reference || `#${l.id}` }}</span>
              <span class="flex items-center gap-1" :class="isOverdue(l) ? 'text-danger-600 font-semibold' : ''">
                <Icon name="calendar" :size="13" />
                {{ dueLabel(l) }}
              </span>
            </p>

            <p v-if="hintFor(l)" class="mt-1.5 flex items-start gap-1.5 text-xs text-content-muted">
              <Icon name="info" :size="13" class="mt-0.5" />
              {{ hintFor(l) }}
            </p>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <StatusBadge :status="badgeStatus(l)" class="hidden sm:inline-flex" />
            <Icon name="chevron-right" :size="18" class="text-neutral-300" />
          </div>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { nextStepHint } from '../../status';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toasts = useToastStore();

const statusOptions = [
  { value: '', label: 'All', icon: 'boxes' },
  { value: 'reserved', label: 'Ready for pick-up', icon: 'package' },
  { value: 'checked_out', label: 'Out with borrower', icon: 'truck' },
  { value: 'return_pending', label: 'Return submitted', icon: 'hourglass' },
  { value: 'overdue', label: 'Overdue', icon: 'alert' },
  { value: 'closed', label: 'Closed', icon: 'check' },
];

const status = ref(route.query.status?.toString() || '');
const overdue = ref(route.query.overdue === '1');
const loans = ref([]);
const loading = ref(true);

const isDepotStaff = computed(() => auth.can('checkout_items'));

function isActive(opt) {
  if (opt.value === 'overdue') return overdue.value;
  return !overdue.value && status.value === opt.value;
}

function setFilter(opt) {
  if (opt.value === 'overdue') {
    overdue.value = true;
    status.value = '';
  } else {
    overdue.value = false;
    status.value = opt.value;
  }
  router.replace({ query: overdue.value ? { overdue: '1' } : status.value ? { status: status.value } : {} });
}

function isOverdue(loan) {
  return loan.due_at && new Date(loan.due_at) < new Date() && ['checked_out', 'return_pending'].includes(loan.status);
}

function badgeStatus(loan) {
  return isOverdue(loan) ? 'overdue' : loan.status;
}

function hintFor(loan) {
  return nextStepHint(badgeStatus(loan), isDepotStaff.value);
}

const TILE_CLASSES = {
  reserved: 'bg-brand-100 text-brand-700',
  checked_out: 'bg-info-100 text-info-600',
  return_pending: 'bg-warn-100 text-warn-600',
  overdue: 'bg-danger-100 text-danger-600',
};

function tileClasses(loan) {
  return TILE_CLASSES[badgeStatus(loan)] || 'bg-neutral-100 text-content-muted';
}

const TILE_ICONS = {
  reserved: 'package',
  checked_out: 'truck',
  return_pending: 'hourglass',
  overdue: 'alert',
};

function tileIcon(loan) {
  return TILE_ICONS[badgeStatus(loan)] || 'handshake';
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '—';
}

function dueLabel(loan) {
  if (!loan.due_at) return 'No date set';
  if (isOverdue(loan)) return `Overdue — was due ${formatDate(loan.due_at)}`;
  if (['returned', 'closed'].includes(loan.status)) return `Returned on ${formatDate(loan.returned_at || loan.due_at)}`;

  return `Return by ${formatDate(loan.due_at)}`;
}

async function load() {
  loading.value = true;
  try {
    const params = {};
    if (overdue.value) params.overdue = 1;
    else if (status.value) params.status = status.value;
    const { data } = await api.get('/loans', { params });
    loans.value = data.data.data || data.data;
  } catch {
    loans.value = [];
    toasts.error('Could not load the loans. Check your signal and try again.');
  } finally {
    loading.value = false;
  }
}

watch([status, overdue], load);
onMounted(load);
</script>
