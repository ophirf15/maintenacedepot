<template>
  <div class="space-y-5">
    <PageHeader
      title="Approvals"
      subtitle="Review what people asked for, assign units, then approve the request."
      icon="check-circle"
    />

    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in TABS"
        :key="t.value"
        type="button"
        :class="['chip', tab === t.value ? 'chip-active' : '']"
        @click="tab = t.value"
      >
        <Icon :name="t.icon" :size="15" />
        {{ t.label }}
        <span v-if="lists[t.value].length" class="ml-0.5 font-semibold">{{ lists[t.value].length }}</span>
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="skeleton h-24" />
    </div>

    <EmptyState
      v-else-if="!requests.length"
      :icon="activeTab.icon"
      :title="activeTab.emptyTitle"
      :hint="activeTab.emptyHint"
    />

    <div v-else class="space-y-3">
      <article v-for="r in requests" :key="r.id" class="card overflow-hidden">
        <button class="flex w-full items-center gap-3 p-4 text-left" :disabled="!canDecide" @click="toggle(r)">
          <span
            class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0"
            :class="canDecide ? 'bg-info-100 text-info-600' : 'bg-warn-100 text-warn-600'"
            aria-hidden="true"
          >
            <Icon :name="canDecide ? 'clipboard' : 'clock'" :size="21" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="font-semibold text-content leading-snug">{{ r.summary }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
              <span class="font-mono">{{ r.reference }}</span>
              <span class="flex items-center gap-1">
                <Icon name="calendar" :size="13" />
                {{ formatRange(r.needed_from, r.needed_until) }}
              </span>
              <span v-if="r.priority && r.priority !== 'normal'" class="flex items-center gap-1 text-warn-600 font-semibold">
                <Icon name="alert" :size="13" />
                {{ priorityLabel(r.priority) }}
              </span>
            </p>
          </div>

          <StatusBadge :status="r.status" class="hidden sm:inline-flex" />
          <Icon
            v-if="canDecide"
            :name="expanded === r.id ? 'chevron-up' : 'chevron-down'"
            :size="18"
            class="text-content-muted"
          />
        </button>

        <div v-if="!canDecide" class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface/70 px-4 py-3">
          <p class="flex items-start gap-1.5 text-sm text-content-muted">
            <Icon name="info" :size="16" class="mt-0.5 shrink-0" />
            You changed something, so {{ borrowerName(r) }} must accept before pick-up.
          </p>
          <RouterLink :to="`/requests/${r.id}`" class="btn-secondary btn-sm">
            <Icon name="eye" :size="16" />
            Full details
          </RouterLink>
        </div>

        <div v-else-if="expanded === r.id" class="border-t border-line bg-surface/70">
          <p v-if="detailLoading" class="p-4 text-sm muted">Loading the details…</p>

          <template v-else-if="forms[r.id]">
            <div class="space-y-3 p-4">
              <div
                v-for="line in forms[r.id].lines"
                :key="line.id"
                class="card p-3.5 space-y-3"
              >
                <div class="flex items-start gap-3">
                  <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-neutral-100 text-content-muted shrink-0">
                    <Icon name="package" :size="18" />
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-content">{{ line.label }}</p>
                    <p class="text-xs muted">
                      Asked for {{ formatQty(line.quantity) }}
                      <span v-if="line.request_mode === 'tool_type'"> · any free unit</span>
                    </p>
                  </div>
                  <div class="flex gap-1 shrink-0">
                    <button
                      v-for="choice in DECISIONS"
                      :key="choice.value"
                      type="button"
                      class="decision"
                      :class="line.status === choice.value ? `decision-on decision-${choice.value}` : ''"
                      :title="choice.hint"
                      @click="line.status = choice.value"
                    >
                      <Icon :name="choice.icon" :size="16" />
                      <span class="hidden sm:inline">{{ choice.label }}</span>
                    </button>
                  </div>
                </div>

                <div v-if="line.status === 'allocated'">
                  <label class="label">Which unit are they getting?</label>
                  <select v-model.number="line.allocated_item_id" class="select">
                    <option
                      v-for="candidate in line.candidates"
                      :key="candidate.id"
                      :value="candidate.id"
                      :disabled="!candidate.is_available"
                    >
                      {{ candidate.label }}{{ candidate.asset_tag ? ` — ${candidate.asset_tag}` : '' }}{{ candidate.specs?.length ? ` · ${candidate.specs.map((s) => s.display).join(', ')}` : '' }}{{ candidate.is_available ? '' : ' (not free)' }}
                    </option>
                    <option v-if="!line.candidates.length" :value="null">No free unit — use waitlist</option>
                  </select>

                  <p v-if="line.substituted" class="mt-2 flex items-start gap-1.5 text-xs text-warn-600">
                    <Icon name="info" :size="14" class="mt-0.5" />
                    The unit they asked for is not free, so a different one is picked. They will be asked to accept.
                  </p>
                  <p v-else-if="line.autoPicked" class="mt-2 flex items-start gap-1.5 text-xs text-brand-700">
                    <Icon name="sparkles" :size="14" class="mt-0.5" />
                    Picked for you automatically. Change it if you want.
                  </p>
                </div>

                <div v-else-if="line.status === 'rejected'">
                  <label class="label">Why not? (they will see this)</label>
                  <input v-model="line.reject_reason" type="text" class="input" placeholder="Example: broken, being repaired" />
                </div>

                <p v-else class="flex items-center gap-1.5 text-xs text-warn-600">
                  <Icon name="clock" :size="14" />
                  They go on the waitlist and get told when a unit is free.
                </p>
              </div>
            </div>

            <div class="border-t border-line px-4 py-3 space-y-3">
              <button type="button" class="flex items-center gap-1.5 text-sm font-medium text-content-muted hover:text-content" @click="forms[r.id].showMore = !forms[r.id].showMore">
                <Icon :name="forms[r.id].showMore ? 'chevron-up' : 'chevron-down'" :size="16" />
                {{ forms[r.id].showMore ? 'Hide extra options' : 'Change dates or add a note' }}
              </button>

              <div v-if="forms[r.id].showMore" class="space-y-4">
                <div class="grid sm:grid-cols-2 gap-3">
                  <div>
                    <label class="label">Pick-up date</label>
                    <input v-model="forms[r.id].needed_from" type="datetime-local" class="input" />
                  </div>
                  <div>
                    <label class="label">Return date</label>
                    <input v-model="forms[r.id].needed_until" type="datetime-local" class="input" />
                  </div>
                </div>
                <div>
                  <label class="label">Note for the depot (private)</label>
                  <input v-model="forms[r.id].approval_note" type="text" class="input" placeholder="Optional" />
                </div>
                <div>
                  <label class="label">Message to the borrower (if you changed something)</label>
                  <textarea v-model="forms[r.id].modification_note" rows="2" class="textarea" placeholder="Explain the change in simple words" />
                </div>
                <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
                  <input v-model="forms[r.id].force_finalize" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
                  <span>
                    Approve now without waiting for the borrower
                    <span class="block text-xs muted">Normally the borrower must accept your changes first.</span>
                  </span>
                </label>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface-raised px-4 py-3">
              <p v-if="forms[r.id].error" class="flex items-center gap-1.5 text-sm text-danger-600">
                <Icon name="alert" :size="16" />
                {{ forms[r.id].error }}
              </p>
              <p v-else class="text-xs muted">{{ summariseDecisions(forms[r.id]) }}</p>

              <div class="flex gap-2">
                <RouterLink :to="`/requests/${r.id}`" class="btn-secondary btn-sm">
                  <Icon name="eye" :size="16" />
                  Full details
                </RouterLink>
                <button class="btn-primary btn-sm" :disabled="forms[r.id].submitting" @click="approve(r)">
                  <Icon :name="forms[r.id].submitting ? 'refresh' : 'check'" :size="17" />
                  {{ forms[r.id].submitting ? 'Saving…' : 'Approve request' }}
                </button>
              </div>
            </div>
          </template>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import { formatDate, fromLocalInput, localInputChanged, toLocalInput } from '../../datetime';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const toasts = useToastStore();

const TABS = [
  {
    value: 'submitted',
    label: 'Ready to approve',
    icon: 'clipboard',
    emptyTitle: 'Nothing to approve',
    emptyHint: 'Every new request has been handled. Nice work.',
  },
  {
    value: 'pending_modification_accept',
    label: 'Waiting for borrower',
    icon: 'clock',
    emptyTitle: 'No changes waiting',
    emptyHint: 'If you change a request, it stays here until the borrower accepts.',
  },
];

const lists = reactive({ submitted: [], pending_modification_accept: [] });
const tab = ref(TABS.some((t) => t.value === route.query.status) ? route.query.status : 'submitted');
const loading = ref(true);
const expanded = ref(null);
const detailLoading = ref(false);
const forms = reactive({});

const activeTab = computed(() => TABS.find((t) => t.value === tab.value) || TABS[0]);
const requests = computed(() => lists[tab.value]);
const canDecide = computed(() => tab.value === 'submitted');

function borrowerName(r) {
  return r.on_behalf_of?.name || r.requester?.name || 'the borrower';
}

const DECISIONS = [
  { value: 'allocated', label: 'Approve', icon: 'check', hint: 'Include this tool in the loan' },
  { value: 'waitlisted', label: 'Waitlist', icon: 'clock', hint: 'Put them on the waitlist for this tool' },
  { value: 'rejected', label: 'Reject', icon: 'x', hint: 'Do not give them this tool' },
];

const PRIORITY_LABELS = { low: 'Not urgent', high: 'Needed soon', urgent: 'Needed today' };

function priorityLabel(value) {
  return PRIORITY_LABELS[value] || value;
}

function formatRange(from, until) {
  return `${formatDate(from)} → ${formatDate(until)}`;
}

function formatQty(quantity) {
  const n = Number(quantity) || 1;

  return n === 1 ? '1' : String(n);
}

function summariseDecisions(form) {
  const counts = form.lines.reduce((acc, line) => {
    acc[line.status] = (acc[line.status] || 0) + 1;
    return acc;
  }, {});

  const parts = [];
  if (counts.allocated) parts.push(`${counts.allocated} approved`);
  if (counts.waitlisted) parts.push(`${counts.waitlisted} waitlisted`);
  if (counts.rejected) parts.push(`${counts.rejected} rejected`);

  return parts.join(' · ');
}

/** Merge server allocation hints into editable line rows. */
function buildForm(full) {
  const hints = new Map((full.allocation || []).map((a) => [a.line_id, a]));

  return {
    lines: full.lines.map((line) => {
      const hint = hints.get(line.id) || {};
      const suggested = hint.suggested_item_id ?? line.allocated_item_id ?? null;

      return {
        id: line.id,
        label: line.label,
        quantity: line.quantity,
        request_mode: line.request_mode,
        candidates: hint.candidates || [],
        allocated_item_id: suggested,
        autoPicked: Boolean(suggested),
        substituted: Boolean(hint.requested_item_id && suggested && hint.requested_item_id !== suggested),
        status: suggested || (hint.candidates || []).length ? 'allocated' : 'waitlisted',
        reject_reason: '',
      };
    }),
    needed_from: toLocalInput(full.needed_from),
    needed_until: toLocalInput(full.needed_until),
    // Kept so we only resend dates the approver actually touched.
    original_needed_from: full.needed_from,
    original_needed_until: full.needed_until,
    approval_note: '',
    modification_note: '',
    force_finalize: false,
    showMore: false,
    submitting: false,
    error: '',
  };
}

async function toggle(r) {
  if (expanded.value === r.id) {
    expanded.value = null;
    return;
  }

  expanded.value = r.id;
  if (forms[r.id]) return;

  detailLoading.value = true;
  try {
    const { data } = await api.get(`/borrow-requests/${r.id}`);
    forms[r.id] = buildForm(data.data);
  } catch {
    toasts.error('Could not load this request.');
    expanded.value = null;
  } finally {
    detailLoading.value = false;
  }
}

async function approve(r) {
  const form = forms[r.id];
  form.submitting = true;
  form.error = '';

  try {
    const { data } = await api.post(`/borrow-requests/${r.id}/approve`, {
      lines: form.lines.map((l) => ({
        id: l.id,
        status: l.status,
        allocated_item_id: l.status === 'allocated' ? l.allocated_item_id || undefined : undefined,
        reject_reason: l.status === 'rejected' ? l.reject_reason || undefined : undefined,
      })),
      needed_from: localInputChanged(form.needed_from, form.original_needed_from)
        ? fromLocalInput(form.needed_from)
        : undefined,
      needed_until: localInputChanged(form.needed_until, form.original_needed_until)
        ? fromLocalInput(form.needed_until)
        : undefined,
      approval_note: form.approval_note || undefined,
      modification_note: form.modification_note || undefined,
      force_finalize: form.force_finalize,
    });

    lists.submitted = lists.submitted.filter((x) => x.id !== r.id);
    expanded.value = null;
    delete forms[r.id];

    if (data.data?.status === 'pending_modification_accept') {
      lists.pending_modification_accept = [data.data, ...lists.pending_modification_accept];
      toasts.info('Approved with changes — waiting for the borrower to accept.');
    } else {
      toasts.success('Approved. Tools are reserved for pick-up.');
    }
  } catch (e) {
    form.error = e.response?.data?.message || 'Could not save this approval.';
  } finally {
    form.submitting = false;
  }
}

async function load() {
  loading.value = true;
  try {
    const responses = await Promise.all(
      TABS.map((t) => api.get('/borrow-requests', { params: { status: t.value } })),
    );

    TABS.forEach((t, i) => {
      const payload = responses[i].data.data;
      lists[t.value] = payload.data || payload;
    });
  } catch {
    TABS.forEach((t) => {
      lists[t.value] = [];
    });
    toasts.error('Could not load the approval list.');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.decision {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border-radius: 0.6rem;
  border: 1px solid var(--color-line);
  background: #fff;
  padding: 0.4rem 0.6rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #52525b;
}
.decision:hover {
  background: #fafafa;
}
.decision-on {
  color: #fff;
}
.decision-on.decision-allocated {
  background: var(--color-brand-700);
  border-color: var(--color-brand-700);
}
.decision-on.decision-waitlisted {
  background: var(--color-warn-600);
  border-color: var(--color-warn-600);
}
.decision-on.decision-rejected {
  background: var(--color-danger-600);
  border-color: var(--color-danger-600);
}
</style>
