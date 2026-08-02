<template>
  <div class="min-h-screen bg-surface md:flex">
    <!-- Mobile slide-over backdrop -->
    <div
      v-if="navOpen"
      class="fixed inset-0 z-40 bg-black/40 md:hidden"
      @click="navOpen = false"
    />

    <aside
      class="fixed md:sticky top-0 z-50 md:z-auto h-screen w-[17rem] shrink-0 bg-ink-950 text-white/80
             flex flex-col transition-transform md:translate-x-0"
      :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center gap-2.5 px-4 h-16 shrink-0">
        <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
          <BrandMark variant="mark" :alt="appName" class="h-9 w-9" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-white truncate">{{ appName }}</p>
          <p class="text-[0.7rem] text-white/45 truncate">Maintenance tools</p>
        </div>
        <button class="md:hidden p-2 -mr-1 text-white/60 hover:text-white" aria-label="Close menu" @click="navOpen = false">
          <Icon name="x" :size="18" />
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 pb-4 space-y-5">
        <div v-for="group in navGroups" :key="group.label">
          <p class="px-2.5 pb-1.5 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-white/35">
            {{ group.label }}
          </p>
          <ul class="space-y-0.5">
            <li v-for="link in group.links" :key="link.to">
              <RouterLink :to="link.to" class="nav-link" @click="navOpen = false">
                <Icon :name="link.icon" :size="18" />
                <span class="flex-1 truncate">{{ link.label }}</span>
                <span
                  v-if="link.badge"
                  class="rounded-full bg-warn-600 px-1.5 py-0.5 text-[0.65rem] font-bold text-white leading-none min-w-[1.15rem] text-center"
                >
                  {{ link.badge }}
                </span>
              </RouterLink>
            </li>
          </ul>
        </div>
      </nav>

      <div class="border-t border-white/10 p-3 shrink-0">
        <div class="flex items-center gap-2.5 rounded-xl px-2 py-2">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">
            {{ initials }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-[0.7rem] text-white/45 truncate">{{ roleLabel }}</p>
          </div>
          <button class="p-2 text-white/50 hover:text-white" title="Sign out" @click="logout">
            <Icon name="logout" :size="18" />
          </button>
        </div>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-line bg-surface-raised/85 px-3 sm:px-5 backdrop-blur">
        <button class="md:hidden btn-ghost h-10 w-10 px-0" aria-label="Open menu" @click="navOpen = true">
          <Icon name="menu" :size="20" />
        </button>

        <form class="relative flex-1 max-w-xl" @submit.prevent="runSearch">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 muted">
            <Icon name="search" :size="17" />
          </span>
          <input
            v-model="searchTerm"
            type="search"
            :placeholder="searchPlaceholder"
            class="input h-10 pl-9"
          />
        </form>

        <button
          type="button"
          class="btn-ghost h-10 w-10 px-0"
          :title="`Theme: ${theme.label}`"
          :aria-label="`Theme: ${theme.label}. Click to change.`"
          @click="theme.cycle()"
        >
          <Icon :name="theme.icon" :size="20" />
        </button>

        <RouterLink
          to="/help"
          class="btn-ghost h-10 w-10 px-0"
          title="User manual"
          aria-label="Open user manual"
        >
          <Icon name="book" :size="20" />
        </RouterLink>

        <RouterLink to="/cart" class="btn-ghost relative h-10 w-10 px-0" title="Cart">
          <Icon name="cart" :size="20" />
          <span
            v-if="cart.lines.length"
            class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[0.65rem] font-bold text-white"
          >
            {{ cart.lines.length }}
          </span>
        </RouterLink>

        <RouterLink to="/notifications" class="btn-ghost relative h-10 w-10 px-0" title="Notifications">
          <Icon name="bell" :size="20" />
          <span
            v-if="unread"
            class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-warn-600 ring-2 ring-[var(--color-surface-raised)]"
          />
        </RouterLink>
      </header>

      <main class="flex-1 p-4 sm:p-6 pb-24 md:pb-8">
        <div class="mx-auto w-full max-w-6xl">
          <RouterView />
        </div>
      </main>

      <!-- Phone bottom bar: the four things field crews actually do -->
      <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 border-t border-line bg-surface-raised/95 backdrop-blur">
        <ul class="grid grid-cols-4">
          <li v-for="tab in bottomTabs" :key="tab.to">
            <RouterLink :to="tab.to" class="bottom-tab">
              <Icon :name="tab.icon" :size="21" />
              <span>{{ tab.label }}</span>
            </RouterLink>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import api from '../api';
import BrandMark from './BrandMark.vue';
import Icon from './Icon.vue';
import { useAuthStore, useCartStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';

const auth = useAuthStore();
const cart = useCartStore();
const theme = useThemeStore();
const router = useRouter();

const navOpen = ref(false);
const searchTerm = ref('');
const unread = ref(0);
const pendingApprovals = ref(0);

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

watch(appName, (name) => {
  if (name) document.title = name;
}, { immediate: true });

const initials = computed(() =>
  (auth.user?.name || '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join(''),
);

const ROLE_LABELS = {
  it_admin: 'IT admin',
  depot_admin: 'Depot admin',
  depot_maintenance: 'Depot mechanic',
  property_manager: 'Property manager',
  borrower: 'Maintenance crew',
};

const roleLabel = computed(() =>
  (auth.user?.roles || []).map((role) => ROLE_LABELS[role] || role).join(' · ') || 'Team member',
);

const isApprover = computed(() => auth.can('approve_requests'));

const searchPlaceholder = computed(() =>
  isApprover.value ? 'Search tools, requests, people…' : 'Search tools…',
);

const navGroups = computed(() => {
  const groups = [
    {
      label: 'Borrow',
      links: [
        { to: '/', label: 'Home', icon: 'home', show: true },
        { to: '/catalog', label: 'Browse tools', icon: 'grid', show: true },
        { to: '/requests', label: 'My requests', icon: 'clipboard', show: auth.can('borrow_items') },
        { to: '/loans', label: 'Active loans', icon: 'handshake', show: true },
        {
          to: '/scan',
          label: 'Scan pick-up or return',
          icon: 'scan',
          show: auth.can('checkout_items') || auth.can('borrow_items'),
        },
        { to: '/help', label: 'User manual', icon: 'book', show: true },
      ],
    },
    {
      label: 'Depot',
      links: [
        {
          to: '/approvals',
          label: 'Approvals',
          icon: 'check-circle',
          show: isApprover.value,
          badge: pendingApprovals.value || null,
        },
        { to: '/tickets', label: 'Damage reports', icon: 'ticket', show: true },
        { to: '/maintenance', label: 'Servicing', icon: 'wrench', show: auth.can('manage_maintenance') },
        {
          to: '/inventory',
          label: 'Equipment list',
          icon: 'boxes',
          show: auth.can('manage_inventory') || auth.can('manage_catalog'),
        },
        {
          to: '/consumables',
          label: 'Consumables',
          icon: 'package',
          show: auth.can('manage_inventory'),
        },
        {
          to: '/catalog-admin',
          label: 'Tool groups and types',
          icon: 'tools',
          show: auth.can('manage_catalog'),
        },
      ],
    },
    {
      label: 'Admin',
      links: [
        { to: '/capex', label: 'Budget plan', icon: 'chart', show: auth.can('view_capex') },
        { to: '/audit', label: 'Activity log', icon: 'history', show: auth.can('view_audit') },
        { to: '/it', label: 'Settings', icon: 'settings', show: auth.can('manage_it') || auth.can('manage_settings') },
      ],
    },
  ];

  return groups
    .map((group) => ({ ...group, links: group.links.filter((link) => link.show) }))
    .filter((group) => group.links.length);
});

const bottomTabs = computed(() => {
  if (isApprover.value) {
    return [
      { to: '/', label: 'Home', icon: 'home' },
      { to: '/approvals', label: 'Approve', icon: 'check-circle' },
      { to: '/scan', label: 'Scan', icon: 'scan' },
      { to: '/loans', label: 'Loans', icon: 'handshake' },
    ];
  }

  return [
    { to: '/', label: 'Home', icon: 'home' },
    { to: '/catalog', label: 'Browse', icon: 'grid' },
    { to: '/requests', label: 'Requests', icon: 'clipboard' },
    { to: '/loans', label: 'My loans', icon: 'handshake' },
  ];
});

function runSearch() {
  const term = searchTerm.value.trim();
  if (!term) return;

  router.push({ path: '/search', query: { q: term } });
}

async function logout() {
  await auth.logout();
  router.push('/login');
}

onMounted(async () => {
  try {
    const { data } = await api.get('/notifications');
    const list = data.data?.data || data.data || [];
    unread.value = list.filter((n) => !n.read_at).length;
  } catch {
    unread.value = 0;
  }

  if (isApprover.value) {
    try {
      const { data } = await api.get('/borrow-requests', { params: { status: 'submitted', per_page: 1 } });
      pendingApprovals.value = data.data?.total ?? 0;
    } catch {
      pendingApprovals.value = 0;
    }
  }
});
</script>

<style scoped>
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.6rem 0.65rem;
  border-radius: 0.7rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.62);
  transition: background 0.15s, color 0.15s;
}
.nav-link:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
}
.nav-link.router-link-exact-active {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
.bottom-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: 0.5rem 0 0.6rem;
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--color-content-muted);
}
.bottom-tab.router-link-exact-active {
  color: var(--color-brand-700);
}
</style>
