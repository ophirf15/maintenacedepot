<template>
  <div class="space-y-5">
    <PageHeader
      title="Damage reports"
      subtitle="Report a broken, damaged, or unsafe tool to the depot."
      icon="ticket"
    >
      <template #actions>
        <button class="btn-primary" @click="showForm = !showForm">
          <Icon :name="showForm ? 'x' : 'plus'" :size="18" />
          {{ showForm ? 'Cancel' : 'Report damage' }}
        </button>
      </template>
    </PageHeader>

    <form v-if="showForm" class="card-pad space-y-4" @submit.prevent="createTicket">
      <div class="flex items-center gap-2">
        <Icon name="alert" :size="18" class="text-warn-600" />
        <p class="section-title">Report damage or a problem</p>
      </div>

      <div>
        <label class="label">What is wrong?</label>
        <input v-model="form.title" type="text" required class="input" placeholder="Example: pull cord is broken" />
      </div>

      <div>
        <label class="label">Tell us more</label>
        <textarea v-model="form.description" rows="3" class="textarea" placeholder="What happened? Where is the tool now?" />
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="label">What kind of problem?</label>
          <select v-model="form.ticket_type" class="select">
            <option v-for="type in TICKET_TYPES" :key="type.value" :value="type.value">{{ type.label }}</option>
          </select>
        </div>
        <div>
          <label class="label">How bad is it?</label>
          <select v-model="form.severity" class="select">
            <option v-for="level in SEVERITY_OPTIONS" :key="level.value" :value="level.value">{{ level.label }}</option>
          </select>
        </div>
        <div>
          <label class="label">Which tool? (number)</label>
          <input v-model.number="form.item_id" type="number" class="input" placeholder="Example: 12" />
          <p class="mt-1.5 text-xs muted">Leave this empty if it is not about one tool.</p>
        </div>
        <div>
          <label class="label">Loan number</label>
          <input v-model.number="form.loan_id" type="number" class="input" placeholder="Example: 40" />
          <p class="mt-1.5 text-xs muted">Only if it happened while you had the tool out.</p>
        </div>
      </div>

      <label
        class="flex items-start gap-2.5 rounded-xl border p-3 text-sm transition"
        :class="form.takes_out_of_service ? 'border-danger-600/25 bg-danger-100 text-danger-600' : 'border-line bg-neutral-50 text-neutral-700'"
      >
        <input v-model="form.takes_out_of_service" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
        <span class="flex items-start gap-2">
          <Icon name="alert" :size="18" class="mt-px shrink-0" />
          <span>
            Not safe to use — take it out of service now
            <span class="block text-xs" :class="form.takes_out_of_service ? 'text-danger-600/80' : 'muted'">
              Nobody can borrow it until someone fixes it.
            </span>
          </span>
        </span>
      </label>

      <p v-if="error" class="flex items-center gap-2 text-sm text-danger-600">
        <Icon name="alert" :size="16" />
        {{ error }}
      </p>

      <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn-primary" :disabled="submitting">
          <Icon :name="submitting ? 'refresh' : 'check'" :size="18" />
          {{ submitting ? 'Saving…' : 'Submit report' }}
        </button>
        <button type="button" class="btn-ghost" @click="showForm = false">
          <Icon name="x" :size="18" />
          Cancel
        </button>
      </div>
    </form>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in STATUS_FILTERS"
        :key="opt.value"
        type="button"
        class="chip"
        :class="status === opt.value ? 'chip-active' : ''"
        @click="status = opt.value"
      >
        <Icon :name="opt.icon" :size="16" />
        {{ opt.label }}
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-24" />
    </div>

    <EmptyState
      v-else-if="!tickets.length"
      icon="check-circle"
      title="No problems here"
      :hint="status ? 'Try another filter above.' : 'Nothing is reported broken right now.'"
    >
      <button class="btn-primary btn-sm" @click="showForm = true">
        <Icon name="plus" :size="16" />
        Report a problem
      </button>
    </EmptyState>

    <ul v-else class="space-y-3">
      <li v-for="t in tickets" :key="t.id">
        <RouterLink :to="`/tickets/${t.id}`" class="card card-hover flex items-start gap-3 p-4">
          <span
            class="flex h-11 w-11 items-center justify-center rounded-xl shrink-0"
            :class="severityTint(t.severity)"
            aria-hidden="true"
          >
            <Icon :name="rowIcon(t)" :size="21" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="font-semibold text-neutral-900 leading-snug">{{ summarise(t) }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
              <span class="font-mono">{{ t.reference }}</span>
              <span v-if="t.item?.asset_tag" class="flex items-center gap-1">
                <Icon name="qr" :size="13" />
                <span class="font-mono">{{ t.item.asset_tag }}</span>
              </span>
              <span class="flex items-center gap-1">
                <Icon name="calendar" :size="13" />
                {{ formatDate(t.created_at) }}
              </span>
              <span v-if="t.takes_out_of_service" class="flex items-center gap-1 font-semibold text-danger-600">
                <Icon name="alert" :size="13" />
                Do not use
              </span>
            </p>

            <div class="mt-2 flex flex-wrap items-center gap-1.5">
              <StatusBadge :status="t.status" />
              <StatusBadge :status="t.severity" />
              <span class="chip h-7 px-2.5 text-xs">
                <Icon :name="typeMeta(t.ticket_type).icon" :size="13" />
                {{ typeMeta(t.ticket_type).label }}
              </span>
            </div>
          </div>

          <Icon name="chevron-right" :size="18" class="mt-3 text-neutral-400" />
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const toasts = useToastStore();

const STATUS_FILTERS = [
  { value: '', label: 'All', icon: 'filter' },
  { value: 'open', label: 'Open', icon: 'alert' },
  { value: 'in_progress', label: 'Being fixed', icon: 'wrench' },
  { value: 'resolved', label: 'Fixed', icon: 'check-circle' },
  { value: 'closed', label: 'Closed', icon: 'check' },
];

const TICKET_TYPES = [
  { value: 'defect', label: 'Not working', icon: 'x-circle' },
  { value: 'damage', label: 'Damaged', icon: 'hammer' },
  { value: 'inspection', label: 'Found in a check', icon: 'clipboard' },
  { value: 'other', label: 'Something else', icon: 'help' },
];

const SEVERITY_OPTIONS = [
  { value: 'low', label: 'Low — it can wait' },
  { value: 'medium', label: 'Medium — fix it soon' },
  { value: 'high', label: 'High — fix it today' },
  { value: 'critical', label: 'Unsafe — do not use it' },
];

const SEVERITY_TINTS = {
  critical: 'bg-danger-100 text-danger-600',
  high: 'bg-danger-100 text-danger-600',
  medium: 'bg-warn-100 text-warn-600',
  low: 'bg-neutral-100 text-neutral-500',
};

const status = ref('');
const tickets = ref([]);
const loading = ref(true);
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');

const form = reactive({
  ticket_type: 'defect',
  item_id: null,
  loan_id: null,
  severity: 'medium',
  title: '',
  description: '',
  takes_out_of_service: false,
});

function typeMeta(value) {
  return TICKET_TYPES.find((type) => type.value === value) || { label: 'Something else', icon: 'help' };
}

function severityTint(severity) {
  return SEVERITY_TINTS[severity] || SEVERITY_TINTS.low;
}

function rowIcon(ticket) {
  if (ticket.takes_out_of_service || ['critical', 'high'].includes(ticket.severity)) return 'alert';

  return typeMeta(ticket.ticket_type).icon;
}

/** One readable line: who reported what, and on which tool. */
function summarise(ticket) {
  const who = ticket.reporter?.name || 'Someone';
  const tool = ticket.item?.label;

  return tool
    ? `${who} reported “${ticket.title}” on ${tool}`
    : `${who} reported “${ticket.title}”`;
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
    : '—';
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/tickets', { params: status.value ? { status: status.value } : {} });
    tickets.value = data.data.data || data.data;
  } catch {
    tickets.value = [];
    toasts.error('Could not load the problem list.');
  } finally {
    loading.value = false;
  }
}

async function createTicket() {
  submitting.value = true;
  error.value = '';
  try {
    await api.post('/tickets', {
      ...form,
      item_id: form.item_id || undefined,
      loan_id: form.loan_id || undefined,
    });
    showForm.value = false;
    Object.assign(form, { ticket_type: 'defect', item_id: null, loan_id: null, severity: 'medium', title: '', description: '', takes_out_of_service: false });
    toasts.success('Thanks — the depot can see this problem now.');
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not send this. Please try again.';
  } finally {
    submitting.value = false;
  }
}

watch(status, load);
onMounted(load);
</script>
