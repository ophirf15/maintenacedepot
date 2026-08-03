<template>
  <Teleport to="body">
    <Transition name="nav-scrim">
      <div
        v-if="open"
        class="md:hidden fixed inset-0 z-[60] bg-black/45 backdrop-blur-[2px]"
        aria-hidden="true"
        @click="$emit('close')"
      />
    </Transition>

    <Transition name="nav-sheet">
      <div
        v-if="open"
        ref="sheetEl"
        id="mobile-nav-sheet"
        class="nav-sheet-panel md:hidden fixed inset-x-0 bottom-0 z-[70] flex max-h-[92dvh] flex-col
               rounded-t-[1.75rem] border border-line border-b-0 bg-surface-raised
               shadow-[0_-12px_40px_rgba(0,0,0,0.28)]"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
        tabindex="-1"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)"
        :style="sheetStyle"
        @click.stop
        @touchstart.passive="onTouchStart"
        @touchmove="onTouchMove"
        @touchend="onTouchEnd"
        @touchcancel="onTouchEnd"
        @keydown="onDialogKeydown"
      >
        <!-- Grabber / drag handle -->
        <button
          type="button"
          class="nav-sheet-handle flex w-full shrink-0 flex-col items-center pb-1 pt-3"
          aria-label="Swipe down or tap to close"
          @click="$emit('close')"
        >
          <div class="h-1 w-11 rounded-full bg-neutral-300 dark:bg-neutral-600" />
        </button>

        <!-- Profile / brand -->
        <div class="flex items-center gap-3 px-5 pb-4 pt-2">
          <span
            class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl
                   bg-ink-950 text-white ring-1 ring-black/5 dark:ring-white/10"
          >
            <BrandMark variant="mark" :alt="appName" class="h-12 w-12" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[1.05rem] font-semibold tracking-tight text-content">{{ userName }}</p>
            <p class="truncate text-xs muted">{{ roleLabel }} · {{ appName }}</p>
          </div>
          <button
            ref="closeBtn"
            type="button"
            class="btn-ghost h-11 w-11 px-0"
            aria-label="Close menu"
            @click="$emit('close')"
          >
            <Icon name="x" :size="20" />
          </button>
        </div>

        <div
          ref="scrollEl"
          class="nav-sheet-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-3 [-webkit-overflow-scrolling:touch]"
        >
          <section
            v-for="group in groups"
            :key="group.label"
            class="mb-3 overflow-hidden rounded-2xl border border-line bg-surface"
          >
            <p class="px-4 pb-1 pt-3 text-[0.68rem] font-semibold uppercase tracking-[0.14em] muted">
              {{ group.label }}
            </p>
            <ul>
              <li v-for="(link, idx) in group.links" :key="link.to">
                <RouterLink
                  :to="link.to"
                  class="sheet-link"
                  :class="{
                    'sheet-link-active': isActive(link.to),
                    'border-t border-line': idx > 0,
                  }"
                  @click="$emit('close')"
                >
                  <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl"
                    :class="isActive(link.to)
                      ? 'bg-brand-100 text-brand-700'
                      : 'bg-neutral-100 text-content-muted dark:bg-white/5 dark:text-neutral-300'"
                  >
                    <Icon :name="link.icon" :size="18" />
                  </span>
                  <span class="min-w-0 flex-1 truncate text-[0.95rem] font-medium">{{ link.label }}</span>
                  <span
                    v-if="link.badge"
                    class="rounded-full bg-warn-solid px-1.5 py-0.5 text-[0.65rem] font-bold text-white leading-none min-w-[1.15rem] text-center"
                  >
                    {{ link.badge }}
                  </span>
                  <Icon name="chevron-right" :size="16" class="text-neutral-300 shrink-0 dark:text-content-muted" />
                </RouterLink>
              </li>
            </ul>
          </section>

          <section class="mb-2 overflow-hidden rounded-2xl border border-line bg-surface">
            <button type="button" class="sheet-link w-full text-left" @click="$emit('cycle-theme')">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-neutral-100 text-content-muted dark:bg-white/5 dark:text-neutral-300">
                <Icon :name="themeIcon" :size="18" />
              </span>
              <span class="min-w-0 flex-1 truncate text-[0.95rem] font-medium">Appearance</span>
              <span class="text-sm muted">{{ themeLabel }}</span>
            </button>
            <button type="button" class="sheet-link w-full border-t border-line text-left text-danger-600" @click="$emit('logout')">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-danger-100 text-danger-600">
                <Icon name="logout" :size="18" />
              </span>
              <span class="min-w-0 flex-1 truncate text-[0.95rem] font-medium">Sign out</span>
            </button>
          </section>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import BrandMark from './BrandMark.vue';
import Icon from './Icon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  groups: { type: Array, default: () => [] },
  appName: { type: String, default: 'Maintenance Depot' },
  userName: { type: String, default: '' },
  roleLabel: { type: String, default: '' },
  themeLabel: { type: String, default: 'System' },
  themeIcon: { type: String, default: 'monitor' },
});

const emit = defineEmits(['close', 'cycle-theme', 'logout']);

const route = useRoute();
const sheetEl = ref(null);
const scrollEl = ref(null);
const closeBtn = ref(null);
const dragY = ref(0);
const dragging = ref(false);

let startY = 0;
let fromHandle = false;
let tracking = false;
let previousFocus = null;

const sheetStyle = computed(() => {
  if (!dragging.value && dragY.value === 0) return undefined;
  return {
    transform: `translateY(${dragY.value}px)`,
    transition: dragging.value ? 'none' : 'transform 0.28s cubic-bezier(0.32, 0.72, 0, 1)',
  };
});

function isActive(to) {
  if (to === '/') return route.path === '/';
  return route.path === to || route.path.startsWith(`${to}/`);
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    emit('close');
  }
}

function onDialogKeydown(event) {
  if (event.key !== 'Tab' || !sheetEl.value) return;
  const focusable = sheetEl.value.querySelectorAll(
    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
  );
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function setBackgroundInert(active) {
  // Only lock the shell while the mobile sheet is open — never leave desktop inert.
  if (active && window.matchMedia('(min-width: 768px)').matches) {
    return;
  }
  const shell = document.querySelector('.app-shell');
  if (!shell) return;
  if (active) shell.setAttribute('inert', '');
  else shell.removeAttribute('inert');
}

watch(
  () => props.open,
  async (open) => {
    dragY.value = 0;
    dragging.value = false;
    if (open) {
      previousFocus = document.activeElement;
      window.addEventListener('keydown', onKeydown);
      setBackgroundInert(true);
      await nextTick();
      closeBtn.value?.focus?.();
    } else {
      window.removeEventListener('keydown', onKeydown);
      setBackgroundInert(false);
      if (previousFocus && typeof previousFocus.focus === 'function') {
        previousFocus.focus();
      }
      previousFocus = null;
    }
  },
);

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
  setBackgroundInert(false);
});

function onTouchStart(event) {
  const touch = event.touches?.[0];
  if (!touch) return;
  startY = touch.clientY;
  fromHandle = Boolean(event.target?.closest?.('.nav-sheet-handle'));
  const atTop = (scrollEl.value?.scrollTop ?? 0) <= 0;
  tracking = fromHandle || atTop;
  dragging.value = false;
}

function onTouchMove(event) {
  if (!tracking) return;
  const touch = event.touches?.[0];
  if (!touch) return;
  const dy = touch.clientY - startY;
  if (dy <= 0) {
    dragY.value = 0;
    return;
  }
  if (dy > 8) {
    dragging.value = true;
    event.preventDefault();
    dragY.value = dy;
  }
}

function onTouchEnd() {
  if (!tracking) return;
  const shouldClose = dragY.value > 110 || (dragging.value && dragY.value > 56);
  tracking = false;
  dragging.value = false;
  if (shouldClose) {
    dragY.value = 0;
    emit('close');
    return;
  }
  dragY.value = 0;
}
</script>

<style scoped>
.text-content {
  color: var(--color-content);
}

.sheet-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 0.9rem;
  color: var(--color-content);
  -webkit-tap-highlight-color: transparent;
  transition: background 0.12s ease;
}
.sheet-link:active {
  background: color-mix(in srgb, var(--color-content) 6%, transparent);
}
.sheet-link-active {
  background: color-mix(in srgb, var(--color-brand-600) 10%, transparent);
  color: var(--color-brand-700);
}

.nav-scrim-enter-active,
.nav-scrim-leave-active {
  transition: opacity 0.22s ease;
}
.nav-scrim-enter-from,
.nav-scrim-leave-to {
  opacity: 0;
}

.nav-sheet-enter-active {
  transition: transform 0.38s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.2s ease;
}
.nav-sheet-leave-active {
  transition: transform 0.28s cubic-bezier(0.4, 0, 1, 1), opacity 0.2s ease;
}
.nav-sheet-enter-from,
.nav-sheet-leave-to {
  transform: translateY(110%);
  opacity: 0.96;
}
</style>
