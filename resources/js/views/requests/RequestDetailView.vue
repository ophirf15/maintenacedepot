<template>
  <div class="space-y-5 max-w-3xl">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div class="skeleton h-40" />
    </div>

    <template v-else-if="request">
      <PageHeader
        :title="request.summary"
        :subtitle="`${request.property?.name || 'No property'} · pick up at ${request.pickup_depot?.name || 'the depot'}`"
        icon="clipboard"
        back-to="/requests"
        back-label="All requests"
      >
        <template #actions>
          <StatusBadge :status="request.status" />
        </template>
      </PageHeader>

      <p class="text-xs muted font-mono -mt-2">{{ request.reference }}</p>

      <!-- What to do next, in plain words -->
      <div
        v-if="hint"
        class="flex items-start gap-2.5 rounded-2xl border px-4 py-3 text-sm"
        :class="request.status === 'overdue' ? 'border-danger-600/20 bg-danger-100 text-danger-600' : 'border-info-600/20 bg-info-100 text-info-600'"
      >
        <Icon name="info" :size="18" class="mt-0.5 shrink-0" />
        <span>{{ hint }}</span>
      </div>

      <section v-if="request.status === 'pending_modification_accept'" class="card-pad border-warn-600/30 bg-warn-100/60 space-y-3">
        <div class="flex items-start gap-2.5">
          <Icon name="help" :size="20" class="mt-0.5 text-warn-600 shrink-0" />
          <div>
            <p class="font-semibold text-neutral-900">The depot changed something</p>
            <p v-if="request.modification_note" class="mt-1 text-sm text-neutral-700">“{{ request.modification_note }}”</p>
            <ul class="mt-2 space-y-3 text-sm text-neutral-700">
              <li v-for="line in request.lines" :key="line.id" class="rounded-xl border border-warn-600/20 bg-white/70 p-3">
                <p class="flex items-center gap-1.5 font-medium text-neutral-900">
                  <Icon name="arrow-right" :size="14" />
                  You asked for {{ line.label }}
                </p>
                <div class="mt-2 flex flex-wrap items-start gap-3">
                  <div class="min-w-0 flex-1">
                    <p class="text-xs muted">You asked for</p>
                    <p>{{ line.item?.label || line.label }}</p>
                    <div v-if="line.item?.specs?.length" class="mt-1 flex flex-wrap gap-1">
                      <span
                        v-for="spec in line.item.specs"
                        :key="'req-'+spec.key"
                        class="rounded-md bg-neutral-100 px-1.5 py-0.5 text-[0.65rem] font-medium"
                      >{{ spec.label }}: {{ spec.display }}</span>
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs muted">Depot offered</p>
                    <p class="font-semibold">{{ line.allocated_item?.label || 'waitlist' }}</p>
                    <div v-if="line.allocated_item?.specs?.length" class="mt-1 flex flex-wrap gap-1">
                      <span
                        v-for="spec in line.allocated_item.specs"
                        :key="'alloc-'+spec.key"
                        class="rounded-md bg-brand-50 px-1.5 py-0.5 text-[0.65rem] font-medium text-brand-800"
                      >{{ spec.label }}: {{ spec.display }}</span>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-primary btn-sm" :disabled="acting" @click="accept">
            <Icon name="check" :size="16" />
            Accept these changes
          </button>
          <button class="btn-secondary btn-sm" :disabled="acting" @click="reject">
            <Icon name="x" :size="16" />
            Reject changes
          </button>
        </div>
      </section>

      <section v-if="request.status === 'draft'" class="card-pad flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neutral-700">You have not sent this to the depot yet.</p>
        <button class="btn-primary btn-sm" :disabled="acting" @click="submitRequest">
          <Icon name="arrow-right" :size="16" />
          Send request to depot
        </button>
      </section>

      <section v-if="request.loan" class="card-pad flex flex-wrap items-center justify-between gap-3 border-brand-700/25 bg-brand-100/50">
        <div class="flex items-start gap-2.5">
          <Icon name="package" :size="20" class="mt-0.5 text-brand-700" />
          <div>
            <p class="font-semibold text-neutral-900">Your tools are reserved</p>
            <p class="text-sm text-neutral-700">Show the depot this loan when you pick up.</p>
          </div>
        </div>
        <RouterLink :to="`/loans/${request.loan.id}`" class="btn-primary btn-sm">
          <Icon name="qr" :size="16" />
          Open loan
        </RouterLink>
      </section>

      <section class="card-pad grid gap-4 sm:grid-cols-2">
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-700 shrink-0">
            <Icon name="calendar" :size="19" />
          </span>
          <div>
            <p class="label mb-0">Pick up</p>
            <p class="text-sm font-semibold text-neutral-900">{{ formatDateTime(request.needed_from) || 'Not set' }}</p>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-warn-100 text-warn-600 shrink-0">
            <Icon name="clock" :size="19" />
          </span>
          <div>
            <p class="label mb-0">Return by</p>
            <p class="text-sm font-semibold text-neutral-900">{{ formatDateTime(request.needed_until) || 'Not set' }}</p>
          </div>
        </div>
      </section>

      <section class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon name="boxes" :size="18" class="text-neutral-400" />
          <p class="section-title">Tools on this request</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li v-for="line in request.lines" :key="line.id" class="flex items-center gap-3 px-4 py-3">
            <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-lg bg-neutral-100 text-neutral-500 shrink-0">
              <img
                v-if="line.allocated_item?.image_url || line.item?.image_url"
                :src="line.allocated_item?.image_url || line.item?.image_url"
                alt=""
                class="h-full w-full object-cover"
              />
              <Icon v-else name="package" :size="18" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-neutral-900 truncate">{{ line.label }}</p>
              <p class="text-xs muted">
                Quantity {{ Number(line.quantity) }}
                <span v-if="line.allocated_item"> · you get <strong>{{ line.allocated_item.label }}</strong></span>
                <span v-if="line.reject_reason"> · {{ line.reject_reason }}</span>
              </p>
              <div
                v-if="(line.allocated_item?.specs || line.item?.specs || []).length"
                class="mt-1.5 flex flex-wrap gap-1"
              >
                <span
                  v-for="spec in (line.allocated_item?.specs || line.item?.specs || []).slice(0, 4)"
                  :key="spec.key"
                  class="rounded-md border border-line bg-neutral-50 px-1.5 py-0.5 text-[0.65rem] font-medium text-neutral-700"
                >{{ spec.label }}: {{ spec.display }}</span>
              </div>
            </div>
            <StatusBadge :status="line.status" />
          </li>
        </ul>
      </section>

      <section class="card-pad">
        <div class="flex items-center gap-2 mb-4">
          <Icon name="history" :size="18" class="text-neutral-400" />
          <p class="section-title">What happened so far</p>
        </div>
        <ol class="space-y-0">
          <li v-for="(event, idx) in timeline" :key="event.label" class="flex gap-3">
            <div class="flex flex-col items-center">
              <span
                class="flex h-6 w-6 items-center justify-center rounded-full"
                :class="event.at ? 'bg-brand-700 text-white' : 'bg-neutral-200 text-neutral-400'"
              >
                <Icon :name="event.at ? 'check' : event.icon" :size="13" :stroke-width="2.4" />
              </span>
              <span v-if="idx < timeline.length - 1" class="w-px flex-1 bg-line my-1" />
            </div>
            <div class="pb-4">
              <p class="text-sm font-medium" :class="event.at ? 'text-neutral-900' : 'text-neutral-400'">{{ event.label }}</p>
              <p v-if="event.at" class="text-xs muted">{{ formatDateTime(event.at) }}</p>
            </div>
          </li>
        </ol>
      </section>

      <div v-if="canCancel" class="flex justify-end">
        <button class="btn-danger btn-sm" :disabled="acting" @click="cancel">
          <Icon name="trash" :size="16" />
          Cancel this request
        </button>
      </div>

      <p v-if="error" class="flex items-center gap-2 text-sm text-danger-600">
        <Icon name="alert" :size="16" />
        {{ error }}
      </p>
    </template>

    <EmptyState v-else icon="alert" title="Request not found" hint="It may have been removed." />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { nextStepHint } from '../../status';
import { formatDateTime } from '../../datetime';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const auth = useAuthStore();
const toasts = useToastStore();

const request = ref(null);
const loading = ref(true);
const acting = ref(false);
const error = ref('');

const canCancel = computed(
  () => request.value && !['completed', 'cancelled', 'rejected'].includes(request.value.status),
);

const hint = computed(() =>
  request.value ? nextStepHint(request.value.status, auth.can('approve_requests')) : '',
);

const timeline = computed(() => {
  const r = request.value;
  if (!r) return [];

  return [
    { label: 'Request sent', at: r.submitted_at, icon: 'arrow-right' },
    { label: 'Depot changed something', at: r.modification_requested_at, icon: 'help', optional: true },
    { label: 'Approved', at: r.approved_at, icon: 'check-circle' },
    { label: 'Tools reserved', at: r.reserved_at, icon: 'package' },
    { label: 'Not approved', at: r.rejected_at, icon: 'x-circle', optional: true },
    { label: 'Cancelled', at: r.cancelled_at, icon: 'x', optional: true },
  ].filter((event) => event.at || !event.optional);
});

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get(`/borrow-requests/${route.params.id}`);
    request.value = data.data;
  } catch {
    request.value = null;
  } finally {
    loading.value = false;
  }
}

async function act(fn, successMessage) {
  acting.value = true;
  error.value = '';
  try {
    const { data } = await fn();
    request.value = data.data;
    if (successMessage) toasts.success(successMessage);
  } catch (e) {
    error.value = e.response?.data?.message || 'That did not work. Please try again.';
  } finally {
    acting.value = false;
  }
}

const submitRequest = () =>
  act(() => api.post(`/borrow-requests/${request.value.id}/submit`), 'Sent to the depot');

const accept = () =>
  act(() => api.post(`/borrow-requests/${request.value.id}/accept-modification`), 'Thanks — your tools are reserved');

const reject = () =>
  act(() => api.post(`/borrow-requests/${request.value.id}/reject-modification`), 'The depot will look at it again');

function cancel() {
  if (!window.confirm('Cancel this request?')) return;

  return act(() => api.post(`/borrow-requests/${request.value.id}/cancel`), 'Request cancelled');
}

onMounted(load);
</script>
