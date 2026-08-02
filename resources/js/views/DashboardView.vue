<template>
  <div class="space-y-6">
    <!-- Hero: greeting, plain-language status line, and the buttons people press most -->
    <section class="rounded-2xl bg-ink-950 p-5 sm:p-7 text-white">
      <p class="text-sm text-white/50">{{ greeting }},</p>
      <h1 class="mt-1 text-2xl sm:text-3xl font-semibold tracking-tight flex items-center gap-2">
        {{ firstName }}
        <Icon name="sparkles" :size="22" class="text-brand-500" />
      </h1>
      <p class="mt-2 max-w-2xl text-sm text-white/65 leading-relaxed">{{ heroLine }}</p>

      <div class="mt-5 flex flex-wrap gap-2">
        <RouterLink v-for="action in quickActions" :key="action.to" :to="action.to" class="hero-action">
          <Icon :name="action.icon" :size="17" />
          {{ action.label }}
        </RouterLink>
      </div>
    </section>

    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div v-for="i in 4" :key="i" class="skeleton h-24" />
    </div>

    <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <StatTile v-for="tile in tiles" :key="tile.label" v-bind="tile" />
    </div>

    <div class="grid lg:grid-cols-2 gap-4 sm:gap-6">
      <section class="card">
        <header class="flex items-center justify-between gap-3 p-4 sm:p-5 pb-3">
          <div class="flex items-center gap-2">
            <Icon name="clipboard" :size="18" class="text-neutral-400" />
            <p class="section-title">{{ isAdmin ? 'Latest requests' : 'My requests' }}</p>
          </div>
          <RouterLink to="/requests" class="text-sm font-medium text-brand-700 hover:underline">See all</RouterLink>
        </header>
        <ul v-if="recentRequests.length" class="divide-rows border-t border-line">
          <li v-for="r in recentRequests" :key="r.id">
            <RouterLink :to="`/requests/${r.id}`" class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-neutral-50">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ r.summary }}</p>
                <p class="text-xs muted mt-0.5 font-mono">{{ r.reference }}</p>
              </div>
              <StatusBadge :status="r.status" />
              <Icon name="chevron-right" :size="16" class="text-neutral-300" />
            </RouterLink>
          </li>
        </ul>
        <p v-else class="px-5 py-8 text-center text-sm muted">Nothing here yet.</p>
      </section>

      <section class="card">
        <header class="flex items-center justify-between gap-3 p-4 sm:p-5 pb-3">
          <div class="flex items-center gap-2">
            <Icon name="handshake" :size="18" class="text-neutral-400" />
            <p class="section-title">{{ isAdmin ? 'Active loans' : 'My loans' }}</p>
          </div>
          <RouterLink to="/loans" class="text-sm font-medium text-brand-700 hover:underline">See all</RouterLink>
        </header>
        <ul v-if="recentLoans.length" class="divide-rows border-t border-line">
          <li v-for="l in recentLoans" :key="l.id">
            <RouterLink :to="`/loans/${l.id}`" class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-neutral-50">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-neutral-900 truncate">{{ l.summary }}</p>
                <p class="text-xs muted mt-0.5 flex items-center gap-1.5">
                  <Icon name="calendar" :size="13" />
                  {{ dueLabel(l) }}
                </p>
              </div>
              <StatusBadge :status="l.status" />
              <Icon name="chevron-right" :size="16" class="text-neutral-300" />
            </RouterLink>
          </li>
        </ul>
        <p v-else class="px-5 py-8 text-center text-sm muted">Nothing out right now.</p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import { useAuthStore } from '../stores/auth';
import Icon from '../components/Icon.vue';
import StatTile from '../components/StatTile.vue';
import StatusBadge from '../components/StatusBadge.vue';

const auth = useAuthStore();
const loading = ref(true);
const stats = ref({});

const firstName = computed(() => (auth.user?.name || '').split(' ')[0] || 'there');
const isAdmin = computed(() => auth.can('approve_requests') || auth.can('checkout_items'));

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
});

const recentRequests = computed(() => stats.value.recent_requests || []);
const recentLoans = computed(() => stats.value.recent_loans || []);

const heroLine = computed(() => {
  const s = stats.value;

  if (isAdmin.value) {
    const parts = [
      `${s.pending_requests ?? 0} request${s.pending_requests === 1 ? '' : 's'} ready to approve`,
      `${s.active_loans ?? 0} active loan${s.active_loans === 1 ? '' : 's'}`,
    ];
    if (s.awaiting_borrower_requests) parts.push(`${s.awaiting_borrower_requests} waiting for borrower to accept changes`);
    if (s.overdue_loans) parts.push(`${s.overdue_loans} overdue`);
    if (s.open_tickets) parts.push(`${s.open_tickets} damage report${s.open_tickets === 1 ? '' : 's'} open`);

    return `${parts.join(' · ')}.`;
  }

  if (s.my_overdue_loans) {
    return `You have ${s.my_overdue_loans} tool${s.my_overdue_loans === 1 ? '' : 's'} past the due date. Please bring them back.`;
  }
  if (s.my_active_loans) {
    return `You have ${s.my_active_loans} tool${s.my_active_loans === 1 ? '' : 's'} with you right now.`;
  }

  return 'Pick a tool from the catalog and send a request to the depot.';
});

const quickActions = computed(() => {
  if (isAdmin.value) {
    return [
      { to: '/approvals', label: 'Approve requests', icon: 'check-circle' },
      { to: '/scan', label: 'Scan pick-up or return', icon: 'scan' },
      { to: '/catalog', label: 'Browse tools', icon: 'grid' },
      { to: '/tickets', label: 'Damage reports', icon: 'ticket' },
    ];
  }

  return [
    { to: '/catalog', label: 'Borrow a tool', icon: 'plus' },
    { to: '/requests', label: 'My requests', icon: 'clipboard' },
    { to: '/loans', label: 'My loans', icon: 'handshake' },
    { to: '/scan', label: 'Scan return', icon: 'scan' },
  ];
});

const tiles = computed(() => {
  const s = stats.value;

  if (isAdmin.value) {
    return [
      {
        label: 'Ready to approve',
        value: s.pending_requests ?? 0,
        icon: 'hourglass',
        tone: 'info',
        to: '/approvals?status=submitted',
      },
      {
        label: 'Waiting for borrower',
        value: s.awaiting_borrower_requests ?? 0,
        icon: 'clock',
        tone: (s.awaiting_borrower_requests || 0) > 0 ? 'warn' : 'neutral',
        hint: 'Borrower must accept your changes',
        to: '/approvals?status=pending_modification_accept',
      },
      { label: 'Active loans', value: s.active_loans ?? 0, icon: 'truck', tone: 'neutral', to: '/loans' },
      {
        label: 'Overdue returns',
        value: s.overdue_loans ?? 0,
        icon: 'alert',
        tone: (s.overdue_loans || 0) > 0 ? 'danger' : 'neutral',
        to: '/loans?overdue=1',
      },
      {
        label: 'Open damage reports',
        value: s.open_tickets ?? 0,
        icon: 'ticket',
        tone: (s.open_tickets || 0) > 0 ? 'warn' : 'neutral',
        to: '/tickets',
      },
      {
        label: 'Ready to borrow',
        value: s.available_items ?? 0,
        icon: 'check-circle',
        tone: 'brand',
        hint: `of ${s.total_items ?? 0} items`,
        to: '/catalog',
      },
    ];
  }

  return [
    { label: 'My requests', value: s.my_requests ?? 0, icon: 'clipboard', tone: 'neutral', to: '/requests' },
    { label: 'My loans', value: s.my_active_loans ?? 0, icon: 'handshake', tone: 'brand', to: '/loans' },
    {
      label: 'Overdue returns',
      value: s.my_overdue_loans ?? 0,
      icon: 'alert',
      tone: (s.my_overdue_loans || 0) > 0 ? 'danger' : 'neutral',
      to: '/loans?overdue=1',
    },
    {
      label: 'Needs my answer',
      value: s.my_action_items ?? 0,
      icon: 'help',
      tone: (s.my_action_items || 0) > 0 ? 'warn' : 'neutral',
      to: '/requests?status=pending_modification_accept',
    },
  ];
});

function dueLabel(loan) {
  if (!loan.due_at) return 'No due date';
  const due = new Date(loan.due_at);
  const formatted = due.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

  return due < new Date() ? `Was due ${formatted}` : `Due ${formatted}`;
}

onMounted(async () => {
  try {
    const { data } = await api.get('/dashboard/stats');
    stats.value = data.data;
  } catch {
    stats.value = {};
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.hero-action {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 0.7rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 0.55rem 0.9rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #fff;
  transition: background 0.15s;
}
.hero-action:hover {
  background: rgba(255, 255, 255, 0.18);
}
</style>
