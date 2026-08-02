<template>
  <div class="space-y-5">
    <PageHeader
      :title="isApprover ? 'Requests' : 'My requests'"
      subtitle="Every borrow request and where it stands."
      icon="clipboard"
    >
      <template #actions>
        <RouterLink to="/catalog" class="btn-primary btn-sm">
          <Icon name="plus" :size="17" />
          New request
        </RouterLink>
      </template>
    </PageHeader>

    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
      <button
        v-for="opt in statusOptions"
        :key="opt.value"
        class="chip shrink-0"
        :class="{ 'chip-active': status === opt.value }"
        @click="status = opt.value"
      >
        <Icon :name="opt.icon" :size="15" />
        {{ opt.label }}
      </button>
    </div>

    <section v-if="waitlist.length" class="card-pad space-y-3">
      <div class="flex items-center gap-2">
        <Icon name="clock" :size="18" class="text-warn-600" />
        <p class="section-title">My waitlist</p>
      </div>
      <ul class="space-y-2">
        <li
          v-for="entry in waitlist"
          :key="entry.id"
          class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-neutral-50/80 px-3 py-2.5"
        >
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-neutral-900">
              {{ entry.tool_type?.name || 'Tool' }}
              <span v-if="entry.item" class="muted font-normal">· {{ entry.item.label }}</span>
            </p>
            <p class="text-xs muted">
              {{ formatDate(entry.desired_from) }} → {{ formatDate(entry.desired_until) }}
              · place #{{ entry.position }}
            </p>
          </div>
          <button type="button" class="btn-ghost btn-sm" @click="leaveWaitlist(entry)">
            <Icon name="x" :size="15" />
            Leave
          </button>
        </li>
      </ul>
    </section>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!requests.length"
      icon="clipboard"
      title="Nothing here"
      :hint="status ? 'Try another filter above.' : 'Add tools from the catalog to make your first request.'"
    >
      <RouterLink v-if="!status" to="/catalog" class="btn-primary btn-sm">
        <Icon name="grid" :size="16" />
        Browse tools
      </RouterLink>
    </EmptyState>

    <ul v-else class="space-y-2.5">
      <li v-for="r in requests" :key="r.id">
        <RouterLink :to="`/requests/${r.id}`" class="card card-hover flex items-center gap-3 p-4">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 shrink-0">
            <Icon name="clipboard" :size="20" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-medium text-neutral-900 leading-snug">{{ r.summary }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs muted">
              <span class="font-mono">{{ r.reference }}</span>
              <span class="flex items-center gap-1">
                <Icon name="calendar" :size="13" />
                {{ formatDate(r.needed_from) }} → {{ formatDate(r.needed_until) }}
              </span>
            </p>
            <p v-if="hint(r)" class="mt-1 text-xs font-medium text-warn-600">{{ hint(r) }}</p>
          </div>
          <StatusBadge :status="r.status" />
          <Icon name="chevron-right" :size="17" class="text-neutral-300" />
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { nextStepHint } from '../../status';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const auth = useAuthStore();
const isApprover = computed(() => auth.can('approve_requests'));

const statusOptions = [
  { value: '', label: 'All', icon: 'grid' },
  { value: 'draft', label: 'Not sent', icon: 'edit' },
  { value: 'submitted', label: 'Waiting for approval', icon: 'hourglass' },
  { value: 'pending_modification_accept', label: 'Waiting for borrower', icon: 'help' },
  { value: 'reserved', label: 'Ready to pick up', icon: 'package' },
  { value: 'completed', label: 'Finished', icon: 'check' },
  { value: 'rejected', label: 'Not approved', icon: 'x-circle' },
];

const status = ref('');
const requests = ref([]);
const waitlist = ref([]);
const loading = ref(true);

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '—';
}

function hint(request) {
  return nextStepHint(request.status, isApprover.value);
}

async function loadWaitlist() {
  if (!auth.can('borrow_items')) {
    waitlist.value = [];
    return;
  }
  try {
    const { data } = await api.get('/waitlist');
    waitlist.value = data.data;
  } catch {
    waitlist.value = [];
  }
}

async function leaveWaitlist(entry) {
  try {
    await api.delete(`/waitlist/${entry.id}`);
    waitlist.value = waitlist.value.filter((e) => e.id !== entry.id);
  } catch {
    // ignore
  }
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/borrow-requests', {
      params: status.value ? { status: status.value } : {},
    });
    requests.value = data.data.data || data.data;
  } catch {
    requests.value = [];
  } finally {
    loading.value = false;
  }
}

watch(status, load);
onMounted(async () => {
  await Promise.all([load(), loadWaitlist()]);
});
</script>
