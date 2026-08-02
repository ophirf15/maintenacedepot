<template>
  <div class="app-shell bg-surface md:flex overflow-x-hidden">
    <!-- Mobile slide-over backdrop -->
    <div
      v-if="navOpen"
      class="fixed inset-0 z-40 bg-black/40 md:hidden"
      @click="navOpen = false"
    />

    <aside
      class="fixed md:sticky top-0 z-50 md:z-auto h-dvh w-[17rem] shrink-0 bg-ink-950 text-white/80
             flex flex-col transition-transform duration-200 ease-out md:translate-x-0 md:h-screen"
      :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
      style="padding-top: env(safe-area-inset-top, 0px)"
    >
      <div class="flex items-center gap-2.5 px-4 h-14 shrink-0">
        <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
          <BrandMark variant="mark" :alt="appName" class="h-9 w-9" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-white truncate">{{ appName }}</p>
          <p class="text-[0.7rem] text-white/45 truncate">Maintenance tools</p>
        </div>
        <button
          type="button"
          class="md:hidden flex h-11 w-11 items-center justify-center -mr-1 text-white/60 hover:text-white"
          aria-label="Close menu"
          @click="navOpen = false"
        >
          <Icon name="x" :size="18" />
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto overscroll-contain px-3 pb-4 space-y-5 [-webkit-overflow-scrolling:touch]">
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

      <div
        class="border-t border-white/10 p-3 shrink-0 space-y-2"
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom, 0px))"
      >
        <button
          type="button"
          class="md:hidden flex w-full items-center gap-2.5 rounded-xl px-2 py-2.5 text-left text-white/70 hover:bg-white/5 hover:text-white"
          @click="theme.cycle()"
        >
          <Icon :name="theme.icon" :size="18" />
          <span class="text-sm font-medium">Theme: {{ theme.label }}</span>
        </button>
        <div class="flex items-center gap-2.5 rounded-xl px-2 py-2">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-white">
            {{ initials }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-[0.7rem] text-white/45 truncate">{{ roleLabel }}</p>
          </div>
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center text-white/50 hover:text-white"
            title="Sign out"
            @click="logout"
          >
            <Icon name="logout" :size="18" />
          </button>
        </div>
      </div>
    </aside>

    <div class="flex min-w-0 max-w-full flex-1 flex-col overflow-x-hidden max-md:h-full max-md:min-h-0">
      <!-- Fixed app chrome on phones; sticky on desktop -->
      <header
        class="app-topbar z-30 flex items-center gap-1.5 border-b border-line bg-surface-raised/95 px-2 sm:gap-2 sm:px-5 backdrop-blur
               fixed inset-x-0 top-0 md:sticky md:top-0"
        style="padding-top: env(safe-area-inset-top, 0px)"
      >
        <div class="flex h-14 w-full items-center gap-1.5 sm:gap-2">
          <button
            type="button"
            class="md:hidden btn-ghost h-11 w-11 px-0 shrink-0"
            aria-label="Open menu"
            @click="navOpen = true"
          >
            <Icon name="menu" :size="22" />
          </button>

          <form class="relative min-w-0 flex-1 max-w-xl" @submit.prevent="runSearch">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 muted">
              <Icon name="search" :size="17" />
            </span>
            <input
              v-model="searchTerm"
              type="search"
              enterkeyhint="search"
              autocomplete="off"
              :placeholder="searchPlaceholder"
              class="input h-11 pl-9 text-base md:text-sm"
            />
          </form>

          <button
            type="button"
            class="btn-ghost hidden md:inline-flex h-10 w-10 px-0"
            :title="`Theme: ${theme.label}`"
            :aria-label="`Theme: ${theme.label}. Click to change.`"
            @click="theme.cycle()"
          >
            <Icon :name="theme.icon" :size="20" />
          </button>

          <RouterLink
            to="/help"
            class="btn-ghost hidden md:inline-flex h-10 w-10 px-0"
            title="User manual"
            aria-label="Open user manual"
          >
            <Icon name="book" :size="20" />
          </RouterLink>

          <RouterLink to="/cart" class="btn-ghost relative h-11 w-11 px-0 shrink-0" title="Cart">
            <Icon name="cart" :size="22" />
            <span
              v-if="cart.lines.length"
              class="absolute top-1 right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[0.6rem] font-bold text-white"
            >
              {{ cart.lines.length }}
            </span>
          </RouterLink>

          <RouterLink to="/notifications" class="btn-ghost relative h-11 w-11 px-0 shrink-0" title="Notifications">
            <Icon name="bell" :size="22" />
            <span
              v-if="unread"
              class="absolute top-2.5 right-2.5 h-2 w-2 rounded-full bg-warn-600 ring-2 ring-[var(--color-surface-raised)]"
            />
          </RouterLink>
        </div>
      </header>

      <main
        ref="mainEl"
        class="app-main min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain
               [-webkit-overflow-scrolling:touch]
               px-4
               pt-[calc(3.5rem+env(safe-area-inset-top,0px)+1rem)]
               pb-[calc(3.75rem+env(safe-area-inset-bottom,0px)+1rem)]
               md:px-6 md:pt-6 md:pb-8"
      >
        <div class="mx-auto w-full max-w-6xl min-w-0">
          <RouterView />
        </div>
      </main>
    </div>

    <nav
      class="app-tabbar md:hidden fixed bottom-0 inset-x-0 z-40 border-t border-line bg-surface-raised/95 backdrop-blur"
      style="padding-bottom: env(safe-area-inset-bottom, 0px)"
      aria-label="Primary"
    >
      <ul class="grid h-14" :class="bottomTabs.length === 5 ? 'grid-cols-5' : 'grid-cols-4'">
        <li v-for="tab in bottomTabs" :key="tab.to" class="min-w-0">
          <RouterLink
            :to="tab.to"
            class="bottom-tab"
            :class="{ 'bottom-tab-active': isBottomTabActive(tab) }"
          >
            <span class="relative">
              <Icon :name="tab.icon" :size="22" />
              <span
                v-if="tab.badge"
                class="absolute -top-1 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-warn-600 px-1 text-[0.6rem] font-bold text-white leading-none"
              >
                {{ tab.badge > 9 ? '9+' : tab.badge }}
              </span>
            </span>
            <span class="truncate max-w-full px-0.5">{{ tab.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import api from '../api';
import BrandMark from './BrandMark.vue';
import Icon from './Icon.vue';
import { useAuthStore, useCartStore } from '../stores/auth';
import { useThemeStore } from '../stores/theme';

const auth = useAuthStore();
const cart = useCartStore();
const theme = useThemeStore();
const router = useRouter();
const route = useRoute();

const navOpen = ref(false);
const searchTerm = ref('');
const unread = ref(0);
const pendingApprovals = ref(0);
const mainEl = ref(null);

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

watch(appName, (name) => {
  if (name) document.title = name;
}, { immediate: true });

// App-like: reset scroll when changing screens.
watch(
  () => route.fullPath,
  async () => {
    navOpen.value = false;
    await nextTick();
    if (mainEl.value) {
      mainEl.value.scrollTop = 0;
    } else {
      window.scrollTo(0, 0);
    }
  },
);

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
  isApprover.value ? 'Search tools…' : 'Search tools…',
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
  const canScan = auth.can('checkout_items') || auth.can('borrow_items');

  const tabs = [
    { to: '/', label: 'Home', icon: 'home', match: ['/'] },
    { to: '/catalog', label: 'Catalog', icon: 'grid', match: ['/catalog', '/items'] },
  ];

  if (canScan) {
    tabs.push({ to: '/scan', label: 'Scan', icon: 'scan', match: ['/scan'] });
  }

  if (isApprover.value) {
    tabs.push({
      to: '/approvals',
      label: 'Approve',
      icon: 'check-circle',
      match: ['/approvals'],
      badge: pendingApprovals.value || null,
    });
  } else if (auth.can('borrow_items')) {
    tabs.push({ to: '/loans', label: 'Loans', icon: 'handshake', match: ['/loans'] });
  } else {
    tabs.push({ to: '/tickets', label: 'Reports', icon: 'ticket', match: ['/tickets'] });
  }

  return tabs;
});

function isBottomTabActive(tab) {
  const path = route.path;
  if (tab.to === '/') return path === '/';
  return (tab.match || [tab.to]).some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}

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
  padding: 0.7rem 0.65rem;
  border-radius: 0.7rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.62);
  transition: background 0.15s, color 0.15s;
  -webkit-tap-highlight-color: transparent;
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
  justify-content: center;
  gap: 0.15rem;
  height: 100%;
  min-height: 3.5rem;
  padding: 0.35rem 0.15rem;
  font-size: 0.65rem;
  font-weight: 600;
  line-height: 1.1;
  color: var(--color-content-muted);
  text-align: center;
  -webkit-tap-highlight-color: transparent;
  transition: color 0.12s, transform 0.12s;
}
.bottom-tab:active {
  transform: scale(0.94);
}
.bottom-tab-active {
  color: var(--color-brand-700);
}
</style>
