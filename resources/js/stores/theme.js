import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';

const STORAGE_KEY = 'md-theme';

/** @typedef {'light' | 'dark' | 'system'} ThemePreference */

function systemPrefersDark() {
  return typeof window !== 'undefined'
    && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolveMode(preference) {
  if (preference === 'system') {
    return systemPrefersDark() ? 'dark' : 'light';
  }
  return preference === 'dark' ? 'dark' : 'light';
}

function applyDom(mode) {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  root.classList.toggle('dark', mode === 'dark');
  root.style.colorScheme = mode;
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) {
    meta.setAttribute('content', mode === 'dark' ? '#0a0a0c' : '#f7f7f8');
  }
}

export const useThemeStore = defineStore('theme', () => {
  const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
  const preference = ref(
    stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system',
  );
  const resolved = ref(resolveMode(preference.value));

  function setPreference(next) {
    preference.value = next;
    resolved.value = resolveMode(next);
    localStorage.setItem(STORAGE_KEY, next);
    applyDom(resolved.value);
  }

  function cycle() {
    const order = ['light', 'dark', 'system'];
    const idx = order.indexOf(preference.value);
    setPreference(order[(idx + 1) % order.length]);
  }

  function init() {
    resolved.value = resolveMode(preference.value);
    applyDom(resolved.value);

    if (typeof window === 'undefined') return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => {
      if (preference.value === 'system') {
        resolved.value = resolveMode('system');
        applyDom(resolved.value);
      }
    };
    mq.addEventListener?.('change', onChange);
  }

  const label = computed(() => {
    if (preference.value === 'system') return 'System';
    return preference.value === 'dark' ? 'Dark' : 'Light';
  });

  const icon = computed(() => {
    if (preference.value === 'system') return 'monitor';
    return preference.value === 'dark' ? 'moon' : 'sun';
  });

  watch(resolved, (mode) => applyDom(mode));

  return { preference, resolved, label, icon, setPreference, cycle, init };
});
