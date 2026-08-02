<template>
  <div class="space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.14em] muted">User manual</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">How to use {{ appName }}</h1>
        <p class="mt-1 max-w-2xl text-sm muted">
          Guide tailored to your access — {{ roleLabel }}. Topics and buttons you cannot use are hidden.
        </p>
      </div>
      <p class="text-xs muted shrink-0">{{ filteredSections.length }} topics for you</p>
    </header>

    <div class="relative">
      <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 muted">
        <Icon name="search" :size="17" />
      </span>
      <input
        v-model="query"
        type="search"
        class="input h-11 pl-9"
        placeholder="Search how-tos, buttons, problems…"
        aria-label="Search the user manual"
      />
    </div>

    <div class="grid gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
      <nav class="card-pad h-fit lg:sticky lg:top-20 space-y-1" aria-label="Manual topics">
        <button
          v-for="section in filteredSections"
          :key="section.id"
          type="button"
          class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left text-sm transition"
          :class="section.id === activeId
            ? 'bg-brand-100 text-brand-700 font-semibold'
            : 'hover:bg-[var(--color-surface)]'"
          @click="selectSection(section.id)"
        >
          <Icon :name="section.icon" :size="16" class="shrink-0 opacity-80" />
          <span class="truncate">{{ section.title }}</span>
        </button>
        <p v-if="!filteredSections.length" class="px-2 py-3 text-sm muted">No topics match that search for your access.</p>
      </nav>

      <div class="space-y-4 min-w-0">
        <article
          v-for="section in filteredSections"
          :id="section.id"
          :key="section.id"
          class="card-pad scroll-mt-24"
          :class="section.id === activeId ? 'ring-2 ring-brand-600/25' : ''"
        >
          <div class="flex flex-wrap items-start justify-between gap-2 border-b border-line pb-3">
            <div class="flex items-start gap-3 min-w-0">
              <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                <Icon :name="section.icon" :size="18" />
              </span>
              <div class="min-w-0">
                <h2 class="text-lg font-semibold tracking-tight">{{ section.title }}</h2>
                <p class="mt-0.5 text-sm muted">{{ section.summary }}</p>
              </div>
            </div>
            <span class="rounded-full border border-line px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-wide muted">
              {{ section.audience }}
            </span>
          </div>

          <section v-if="section.howTos?.length" class="mt-4 space-y-3">
            <h3 class="text-sm font-semibold">How to</h3>
            <div v-for="(guide, gi) in section.howTos" :key="gi" class="rounded-xl border border-line p-3 sm:p-4">
              <h4 class="font-medium">{{ guide.title }}</h4>
              <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm leading-relaxed">
                <li v-for="(step, si) in guide.steps" :key="si">{{ step }}</li>
              </ol>
            </div>
          </section>

          <section v-if="section.actions?.length" class="mt-5 space-y-3">
            <h3 class="text-sm font-semibold">Buttons & actions</h3>
            <div class="overflow-x-auto rounded-xl border border-line">
              <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide muted">
                  <tr>
                    <th class="px-3 py-2 font-semibold">Control</th>
                    <th class="px-3 py-2 font-semibold">Where</th>
                    <th class="px-3 py-2 font-semibold">What it does</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(action, ai) in section.actions"
                    :key="ai"
                    class="border-t border-line align-top"
                  >
                    <td class="px-3 py-2 font-medium">{{ action.name }}</td>
                    <td class="px-3 py-2 muted whitespace-nowrap">{{ action.where }}</td>
                    <td class="px-3 py-2">{{ action.does }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section v-if="section.tips?.length" class="mt-5 space-y-2">
            <h3 class="text-sm font-semibold">Tips</h3>
            <ul class="space-y-1.5 text-sm">
              <li v-for="(tip, ti) in section.tips" :key="ti" class="flex gap-2">
                <Icon name="info" :size="16" class="mt-0.5 shrink-0 text-info-600" />
                <span>{{ tipLabel(tip) }}</span>
              </li>
            </ul>
          </section>

          <section v-if="section.troubles?.length" class="mt-5 space-y-3">
            <h3 class="text-sm font-semibold">Troubleshooting</h3>
            <div
              v-for="(item, ti) in section.troubles"
              :key="ti"
              class="rounded-xl border border-line bg-[var(--color-surface)]/60 p-3 sm:p-4"
            >
              <p class="flex gap-2 text-sm font-medium">
                <Icon name="alert" :size="16" class="mt-0.5 shrink-0 text-warn-600" />
                <span>{{ item.problem }}</span>
              </p>
              <p class="mt-1.5 pl-6 text-sm muted">{{ item.fix }}</p>
            </div>
          </section>
        </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import { filterManualForUser, tipText } from '../manual/access';
import { MANUAL_SECTIONS } from '../manual/sections';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const query = ref('');

const ROLE_LABELS = {
  it_admin: 'IT admin',
  depot_admin: 'Depot admin',
  depot_maintenance: 'Depot mechanic',
  property_manager: 'Property manager',
  borrower: 'Maintenance crew',
};

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

const roleLabel = computed(() =>
  (auth.user?.roles || []).map((role) => ROLE_LABELS[role] || role).join(' · ') || 'your account',
);

const visibleSections = computed(() => filterManualForUser(MANUAL_SECTIONS, auth.can));

const activeId = ref('');

const filteredSections = computed(() => {
  const sections = visibleSections.value;
  const q = query.value.trim().toLowerCase();
  if (!q) return sections;

  return sections.filter((section) => {
    const haystack = [
      section.title,
      section.summary,
      section.audience,
      ...(section.howTos || []).flatMap((h) => [h.title, ...(h.steps || [])]),
      ...(section.actions || []).flatMap((a) => [a.name, a.where, a.does]),
      ...(section.tips || []).map((t) => tipText(t)),
      ...(section.troubles || []).flatMap((t) => [t.problem, t.fix]),
    ]
      .join(' ')
      .toLowerCase();

    return haystack.includes(q);
  });
});

function tipLabel(tip) {
  return tipText(tip);
}

async function selectSection(id) {
  activeId.value = id;
  await router.replace({ query: { ...route.query, topic: id } });
  await nextTick();
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

watch(filteredSections, (list) => {
  if (!list.length) {
    activeId.value = '';
    return;
  }
  if (!list.some((s) => s.id === activeId.value)) {
    activeId.value = list[0].id;
  }
}, { immediate: true });

onMounted(() => {
  const fromQuery = typeof route.query.topic === 'string' ? route.query.topic : '';
  if (fromQuery && visibleSections.value.some((s) => s.id === fromQuery)) {
    selectSection(fromQuery);
  }
});
</script>
