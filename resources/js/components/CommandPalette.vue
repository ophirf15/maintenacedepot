<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="command-palette fixed inset-0 z-[80] flex items-start justify-center px-3 pt-[12vh] sm:px-4"
      role="dialog"
      aria-modal="true"
      aria-label="Search"
      @keydown="onKeydown"
    >
      <button
        type="button"
        class="absolute inset-0 bg-ink-950/55 backdrop-blur-[2px]"
        aria-label="Close search"
        @click="close"
      />

      <div
        class="relative z-10 flex w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-line
               bg-surface-raised shadow-[0_24px_64px_-24px_rgba(0,0,0,0.55)] fade-in-up"
      >
        <div class="flex items-center gap-2 border-b border-line px-3">
          <Icon name="search" :size="18" class="text-content-muted shrink-0" />
          <input
            ref="inputEl"
            v-model="query"
            type="search"
            autocomplete="off"
            enterkeyhint="search"
            class="h-12 w-full min-w-0 border-0 bg-transparent text-base text-content outline-none
                   placeholder:text-content-muted"
            placeholder="Search tools, requests, loans…"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="activateSelected"
          />
          <kbd
            class="hidden sm:inline-flex shrink-0 rounded-md border border-line px-1.5 py-0.5
                   text-[0.65rem] font-medium text-content-muted"
          >
            Esc
          </kbd>
        </div>

        <div class="max-h-[min(28rem,55vh)] overflow-y-auto overscroll-contain py-2">
          <p v-if="loading" class="px-4 py-3 text-sm muted">Searching…</p>
          <p
            v-else-if="query.trim() && !flatRows.length"
            class="px-4 py-3 text-sm muted"
          >
            No results found.
          </p>

          <div role="listbox" :aria-label="query.trim() ? 'Search results' : 'Quick links'">
            <template v-for="group in groups" :key="group.heading">
              <p
                class="px-4 pt-2 pb-1 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-content-muted"
              >
                {{ group.heading }}
              </p>
              <ul>
                <li v-for="row in group.rows" :key="row.key" role="presentation">
                  <button
                    type="button"
                    role="option"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors"
                    :class="row.index === selectedIndex
                      ? 'bg-brand-100/70 text-content'
                      : 'hover:bg-surface text-content'"
                    :aria-selected="row.index === selectedIndex"
                    @mouseenter="selectedIndex = row.index"
                    @click="go(row.href)"
                  >
                    <span
                      class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-100
                             text-content-muted dark:bg-ink-800 shrink-0"
                    >
                      <Icon :name="row.icon" :size="16" />
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-medium">{{ row.title }}</span>
                      <span v-if="row.snippet" class="block truncate text-xs muted">{{ row.snippet }}</span>
                    </span>
                    <span
                      v-if="row.badge"
                      class="shrink-0 text-[0.65rem] uppercase tracking-wide text-content-muted"
                    >
                      {{ row.badge }}
                    </span>
                  </button>
                </li>
              </ul>
            </template>

            <div v-if="query.trim()" class="mt-1 border-t border-line px-2 pt-1">
              <button
                type="button"
                role="option"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm
                       text-content-muted hover:bg-surface hover:text-content"
                :class="selectedIndex === allResultsIndex ? 'bg-brand-100/70 text-content' : ''"
                :aria-selected="selectedIndex === allResultsIndex"
                @mouseenter="selectedIndex = allResultsIndex"
                @click="go(`/search?q=${encodeURIComponent(query.trim())}`)"
              >
                <Icon name="search" :size="16" />
                <span>View all results for “{{ query.trim() }}”</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { OPEN_PALETTE_EVENT } from '../command-palette';
import Icon from './Icon.vue';

const props = defineProps({
  navItems: {
    type: Array,
    default: () => [],
  },
});

const router = useRouter();
const open = ref(false);
const query = ref('');
const hits = ref([]);
const loading = ref(false);
const selectedIndex = ref(0);
const inputEl = ref(null);
const returnFocusEl = ref(null);

let searchTimer = null;
let searchAbort = null;

const navMatches = computed(() => {
  const q = query.value.trim().toLowerCase();
  const items = props.navItems || [];
  if (!q) return items.slice(0, 8);
  return items.filter((item) => String(item.label || '').toLowerCase().includes(q)).slice(0, 8);
});

const flatRows = computed(() => {
  const rows = [];
  for (const hit of hits.value) {
    rows.push({
      key: `${hit.entity_type}-${hit.entity_id}`,
      title: hit.title,
      snippet: hit.snippet,
      href: hit.href,
      icon: hit.icon || 'search',
      badge: String(hit.entity_type || '').replace(/_/g, ' '),
      group: 'Results',
    });
  }
  for (const item of navMatches.value) {
    rows.push({
      key: `nav-${item.to}`,
      title: item.label,
      snippet: '',
      href: item.to,
      icon: item.icon || 'grid',
      badge: '',
      group: query.value.trim() ? 'Navigation' : 'Quick links',
    });
  }
  return rows;
});

const groups = computed(() => {
  const map = new Map();
  flatRows.value.forEach((row, index) => {
    if (!map.has(row.group)) map.set(row.group, []);
    map.get(row.group).push({ ...row, index });
  });
  return [...map.entries()].map(([heading, rows]) => ({ heading, rows }));
});

const allResultsIndex = computed(() => flatRows.value.length);

watch(open, async (isOpen) => {
  document.documentElement.classList.toggle('command-palette-open', isOpen);
  if (isOpen) {
    selectedIndex.value = 0;
    await nextTick();
    inputEl.value?.focus();
  } else {
    query.value = '';
    hits.value = [];
    const el = returnFocusEl.value;
    returnFocusEl.value = null;
    await nextTick();
    if (el && typeof el.focus === 'function') el.focus();
  }
});

watch(query, (value) => {
  if (searchTimer) clearTimeout(searchTimer);
  if (searchAbort) searchAbort.abort();
  selectedIndex.value = 0;
  const q = value.trim();
  if (!q) {
    hits.value = [];
    loading.value = false;
    return;
  }
  loading.value = true;
  searchAbort = new AbortController();
  const ctrl = searchAbort;
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/search', {
        params: { q },
        signal: ctrl.signal,
      });
      hits.value = data.data || [];
    } catch (err) {
      if (err?.name !== 'CanceledError' && err?.code !== 'ERR_CANCELED') {
        hits.value = [];
      }
    } finally {
      if (searchAbort === ctrl) loading.value = false;
    }
  }, 180);
});

function close() {
  open.value = false;
}

function go(href) {
  close();
  if (href) router.push(href);
}

function move(delta) {
  const size = query.value.trim() ? flatRows.value.length + 1 : flatRows.value.length;
  if (!size) return;
  selectedIndex.value = (selectedIndex.value + delta + size) % size;
}

function activateSelected() {
  if (query.value.trim() && selectedIndex.value === allResultsIndex.value) {
    go(`/search?q=${encodeURIComponent(query.value.trim())}`);
    return;
  }
  const row = flatRows.value[selectedIndex.value];
  if (row) go(row.href);
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function onGlobalKey(event) {
  if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault();
    if (!open.value) {
      returnFocusEl.value = document.activeElement;
    }
    open.value = !open.value;
  }
}

function onOpenEvent(event) {
  if (!open.value) {
    returnFocusEl.value = document.activeElement;
  }
  open.value = true;
  if (event?.detail?.query) query.value = String(event.detail.query);
}

onMounted(() => {
  document.addEventListener('keydown', onGlobalKey);
  window.addEventListener(OPEN_PALETTE_EVENT, onOpenEvent);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onGlobalKey);
  window.removeEventListener(OPEN_PALETTE_EVENT, onOpenEvent);
  document.documentElement.classList.remove('command-palette-open');
  if (searchTimer) clearTimeout(searchTimer);
  if (searchAbort) searchAbort.abort();
});
</script>
