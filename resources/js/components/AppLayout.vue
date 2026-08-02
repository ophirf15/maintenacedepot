<template>
  <div class="app-shell bg-surface md:flex overflow-x-hidden">
    <a href="#main-content" class="skip-link">
      Skip to main content
    </a>
    <!-- Desktop sidebar (Material-style permanent rail content) -->
    <aside
      class="hidden md:sticky md:top-0 md:z-auto md:flex md:h-screen w-[17rem] shrink-0
             flex-col bg-ink-950 text-white/80"
    >
      <div class="flex items-center gap-2.5 px-4 h-14 shrink-0">
        <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/15">
          <BrandMark variant="mark" :alt="appName" class="h-9 w-9" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-white truncate">{{ appName }}</p>
          <p class="text-[0.7rem] text-white/45 truncate">Maintenance tools</p>
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto overscroll-contain px-3 pb-4 space-y-5">
        <div v-for="group in navGroups" :key="group.label">
          <p class="px-2.5 pb-1.5 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-white/35">
            {{ group.label }}
          </p>
          <ul class="space-y-0.5">
            <li v-for="link in group.links" :key="link.to">
              <RouterLink :to="link.to" class="nav-link">
                <Icon :name="link.icon" :size="18" />
                <span class="flex-1 truncate">{{ link.label }}</span>
                <span
                  v-if="link.badge"
                  class="rounded-full bg-warn-solid px-1.5 py-0.5 text-[0.65rem] font-bold text-white leading-none min-w-[1.15rem] text-center"
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

    <MobileNavSheet
      :open="navOpen"
      :groups="navGroups"
      :app-name="appName"
      :user-name="auth.user?.name || ''"
      :role-label="roleLabel"
      :theme-label="theme.label"
      :theme-icon="theme.icon"
      @close="navOpen = false"
      @cycle-theme="theme.cycle()"
      @logout="logout"
    />

    <div class="flex min-w-0 max-w-full flex-1 flex-col overflow-x-hidden max-md:h-full max-md:min-h-0">
      <header
        class="app-topbar z-30 flex items-center gap-1.5 px-2 sm:gap-2 sm:px-5
               fixed inset-x-0 top-0 md:sticky md:top-0
               border-b transition-[background,box-shadow,border-color] duration-200"
        :class="headerElevated
          ? 'border-line bg-surface-raised shadow-[0_1px_0_rgba(0,0,0,0.04),0_8px_24px_-16px_rgba(0,0,0,0.35)]'
          : 'border-transparent bg-surface-raised/92 backdrop-blur-xl md:border-line'"
        style="padding-top: env(safe-area-inset-top, 0px)"
      >
        <div class="flex h-14 w-full items-center gap-1.5 sm:gap-2">
          <button
            type="button"
            class="md:hidden btn-ghost h-11 w-11 px-0 shrink-0"
            aria-label="Open menu"
            :aria-expanded="navOpen"
            aria-controls="mobile-nav-sheet"
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
              class="input h-11 pl-9 text-base md:text-sm rounded-2xl border-transparent bg-neutral-100/90 dark:bg-white/5 focus:border-line focus:bg-surface-raised"
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

          <RouterLink to="/notifications" class="btn-ghost relative h-11 w-11 px-0 shrink-0" title="Notifications">
            <Icon name="bell" :size="22" />
            <span
              v-if="unread"
              class="absolute top-2.5 right-2.5 h-2 w-2 rounded-full bg-warn-600 ring-2 ring-[var(--color-surface-raised)]"
            />
          </RouterLink>

          <RouterLink
            to="/cart"
            class="btn-ghost relative ml-1.5 h-11 w-11 px-0 shrink-0 sm:ml-2.5"
            title="Tool bag"
            aria-label="Tool bag"
          >
            <Icon name="toolbag" :size="22" />
            <span
              v-if="cart.lines.length"
              class="absolute top-1 right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-solid px-1 text-[0.6rem] font-bold text-white"
            >
              {{ cart.lines.length }}
            </span>
          </RouterLink>
        </div>
      </header>

      <main
        id="main-content"
        ref="mainEl"
        tabindex="-1"
        class="app-main min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain
               [-webkit-overflow-scrolling:touch]
               px-4
               pt-[calc(3.5rem+env(safe-area-inset-top,0px)+0.85rem)]
               pb-[calc(3.75rem+env(safe-area-inset-bottom,0px)+1rem)]
               md:px-6 md:pt-6 md:pb-8"
        @scroll="onMainScroll"
      >
        <div class="mx-auto w-full max-w-6xl min-w-0">
          <RouterView v-slot="{ Component }">
            <Transition name="page-fade" mode="out-in">
              <component :is="Component" />
            </Transition>
          </RouterView>
        </div>
      </main>
    </div>

    <nav
      class="app-tabbar md:hidden fixed bottom-0 inset-x-0 z-40
             border-t border-line/70 bg-surface-raised/94 backdrop-blur-2xl
             shadow-[0_-8px_28px_-18px_rgba(0,0,0,0.35)]"
      style="padding-bottom: env(safe-area-inset-bottom, 0px)"
      aria-label="Primary"
    >
      <ul class="grid h-[3.75rem]" :class="bottomTabs.length === 5 ? 'grid-cols-5' : 'grid-cols-4'">
        <li v-for="tab in bottomTabs" :key="tab.to" class="min-w-0">
          <RouterLink
            :to="tab.to"
            class="bottom-tab"
            :class="{ 'bottom-tab-active': isBottomTabActive(tab) }"
          >
            <span class="bottom-tab-icon" :class="{ 'bottom-tab-icon-active': isBottomTabActive(tab) }">
              <Icon :name="tab.icon" :size="22" />
              <span
                v-if="tab.badge"
                class="absolute -top-0.5 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-warn-solid px-1 text-[0.58rem] font-bold text-white leading-none"
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import api from '../api';
import BrandMark from './BrandMark.vue';
import Icon from './Icon.vue';
import MobileNavSheet from './MobileNavSheet.vue';
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
const headerElevated = ref(false);

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

watch(appName, (name) => {
  if (name) document.title = name;
}, { immediate: true });

watch(
  () => route.fullPath,
  async () => {
    navOpen.value = false;
    await nextTick();
    if (mainEl.value) mainEl.value.scrollTop = 0;
    else window.scrollTo(0, 0);
    headerElevated.value = false;
  },
);

watch(navOpen, (open) => {
  document.documentElement.classList.toggle('nav-sheet-open', open);
});

onUnmounted(() => {
  document.documentElement.classList.remove('nav-sheet-open');
});

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
const searchPlaceholder = computed(() => 'Search tools…');

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

function onMainScroll(event) {
  headerElevated.value = (event.target?.scrollTop || 0) > 4;
}

function runSearch() {
  const term = searchTerm.value.trim();
  if (!term) return;
  router.push({ path: '/search', query: { q: term } });
}

async function logout() {
  navOpen.value = false;
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
  border-radius: 999px;
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
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.bottom-tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
  height: 100%;
  min-height: 3.5rem;
  padding: 0.25rem 0.15rem 0.35rem;
  font-size: 0.62rem;
  font-weight: 600;
  line-height: 1.1;
  color: var(--color-content-muted);
  text-align: center;
  -webkit-tap-highlight-color: transparent;
  transition: color 0.15s ease;
}
.bottom-tab:active {
  opacity: 0.75;
}
.bottom-tab-active {
  color: var(--color-brand-700);
}
.bottom-tab-icon {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 4rem;
  height: 2rem;
  border-radius: 999px;
  transition: background 0.2s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.bottom-tab-icon-active {
  background: color-mix(in srgb, var(--color-brand-600) 18%, transparent);
  transform: translateY(-1px) scale(1.02);
}
.bottom-tab-active .bottom-tab-icon-active :deep(svg) {
  stroke-width: 2;
}

.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}
.page-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.page-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
