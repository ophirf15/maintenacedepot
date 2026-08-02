<template>
  <div class="space-y-5">
    <PageHeader
      title="Servicing"
      subtitle="Keep the tools working: service plans, kinds of service, and the work you log."
      icon="wrench"
    />

    <div v-if="loadingPlans" class="grid sm:grid-cols-2 gap-3">
      <div v-for="i in 2" :key="i" class="skeleton h-20" />
    </div>
    <div v-else class="grid sm:grid-cols-2 gap-3">
      <StatTile
        label="Due for service"
        :value="dueSoonCount"
        icon="wrench"
        tone="warn"
        hint="In the next 7 days"
      />
      <StatTile
        label="Overdue for service"
        :value="overdueCount"
        icon="alert"
        tone="danger"
        hint="Service these first"
      />
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in tabs"
        :key="t.value"
        type="button"
        class="chip"
        :class="tab === t.value ? 'chip-active' : ''"
        @click="tab = t.value"
      >
        <Icon :name="t.icon" :size="16" />
        {{ t.label }}
      </button>
    </div>

    <!-- Kinds of service -->
    <div v-if="tab === 'types'" class="space-y-3">
      <form v-if="canManage" class="card-pad space-y-4" @submit.prevent="createType">
        <div class="flex items-center gap-2">
          <Icon name="plus" :size="18" class="text-content-muted" />
          <p class="section-title">Add a kind of service</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
          <div class="flex-1 min-w-[12rem]">
            <label class="label">Name</label>
            <input v-model="typeForm.name" type="text" required class="input" placeholder="Example: oil change" />
          </div>
          <div class="min-w-[10rem]">
            <label class="label">Kind</label>
            <select v-model="typeForm.kind" class="select">
              <option v-for="kind in TYPE_KINDS" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
            </select>
          </div>
        </div>

        <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
          <input v-model="typeForm.requires_downtime" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
          <span>
            Nobody can use the tool during this service
            <span class="block text-xs muted">The tool is out of use until the work is done.</span>
          </span>
        </label>

        <p v-if="typeError" class="flex items-center gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" />
          {{ typeError }}
        </p>

        <button type="submit" class="btn-primary" :disabled="savingType">
          <Icon :name="savingType ? 'refresh' : 'plus'" :size="18" />
          {{ savingType ? 'Saving…' : 'Add it' }}
        </button>
      </form>

      <div v-if="loadingTypes" class="space-y-3">
        <div v-for="i in 3" :key="i" class="skeleton h-16" />
      </div>

      <EmptyState
        v-else-if="!types.length"
        icon="settings"
        title="No kinds of service yet"
        hint="Add one, then you can build service plans from it."
      />

      <ul v-else class="space-y-2">
        <li v-for="t in types" :key="t.id" class="card flex flex-wrap items-center gap-3 p-4">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-content-muted shrink-0">
            <Icon :name="kindMeta(t.kind).icon" :size="20" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-content">{{ t.name }}</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5 text-xs muted">
              <span>{{ kindMeta(t.kind).label }}</span>
              <span v-if="t.requires_downtime" class="flex items-center gap-1 text-warn-600 font-medium">
                <Icon name="alert" :size="13" />
                Tool is out of use
              </span>
            </p>
          </div>
          <StatusBadge
            :status="t.is_active ? 'available' : 'unavailable'"
            :label="t.is_active ? 'In use' : 'Turned off'"
          />
        </li>
      </ul>
    </div>

    <!-- Service plans -->
    <div v-else-if="tab === 'plans'" class="space-y-3">
      <form v-if="canManage" class="card-pad space-y-4" @submit.prevent="createPlan">
        <div class="flex items-center gap-2">
          <Icon name="calendar" :size="18" class="text-content-muted" />
          <p class="section-title">Add a service plan</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Plan name</label>
            <input v-model="planForm.name" type="text" required class="input" placeholder="Example: mower oil every 90 days" />
          </div>
          <div>
            <label class="label">Kind of service</label>
            <select v-model.number="planForm.maintenance_type_id" required class="select">
              <option disabled :value="null">Pick one…</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Which tool?</label>
            <select v-model.number="planForm.item_id" class="select">
              <option :value="null">None — use a tool group instead</option>
              <option v-for="item in catalogItems" :key="item.id" :value="item.id">
                {{ item.label || item.asset_tag }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Or whole tool group</label>
            <select v-model.number="planForm.tool_type_id" class="select">
              <option :value="null">None</option>
              <option v-for="tt in toolTypes" :key="tt.id" :value="tt.id">{{ tt.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">When is it due?</label>
            <select v-model="planForm.trigger_type" class="select">
              <option v-for="trigger in TRIGGERS" :key="trigger.value" :value="trigger.value">{{ trigger.label }}</option>
            </select>
          </div>
          <div v-if="planForm.trigger_type === 'calendar'">
            <label class="label">How many days between services?</label>
            <input v-model.number="planForm.interval_days" type="number" min="1" class="input" />
          </div>
          <div v-else-if="planForm.trigger_type === 'usage_hours'">
            <label class="label">How many hours of use?</label>
            <input v-model.number="planForm.interval_hours" type="number" min="0.1" step="0.1" class="input" />
          </div>
          <div v-else-if="planForm.trigger_type === 'loan_count'">
            <label class="label">How many loans between services?</label>
            <input v-model.number="planForm.interval_loans" type="number" min="1" class="input" />
          </div>
          <div v-else-if="planForm.trigger_type === 'fuel_cycles'">
            <label class="label">How many fuel fills between services?</label>
            <input v-model.number="planForm.interval_fuel_cycles" type="number" min="1" class="input" />
          </div>
        </div>

        <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
          <input v-model="planForm.blocks_checkout_when_overdue" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
          <span>
            Block pick-up until serviced
            <span class="block text-xs muted">Off = warn at pick-up. On = depot must override with a reason.</span>
          </span>
        </label>

        <p v-if="planError" class="flex items-center gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" />
          {{ planError }}
        </p>

        <button type="submit" class="btn-primary" :disabled="savingPlan">
          <Icon :name="savingPlan ? 'refresh' : 'plus'" :size="18" />
          {{ savingPlan ? 'Saving…' : 'Add the plan' }}
        </button>
      </form>

      <div v-if="loadingPlans" class="space-y-3">
        <div v-for="i in 3" :key="i" class="skeleton h-20" />
      </div>

      <EmptyState
        v-else-if="!plans.length"
        icon="calendar"
        title="No service plans yet"
        hint="A plan tells you when a tool needs servicing again."
      />

      <ul v-else class="space-y-2">
        <li
          v-for="p in plans"
          :key="p.id"
          class="card flex flex-wrap items-start gap-3 p-4"
          :class="planTint(p).card"
        >
          <span
            class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
            :class="planTint(p).badge"
          >
            <Icon :name="planTint(p).icon" :size="20" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-content">{{ p.name }}</p>
            <p class="text-xs text-content-muted mt-0.5">{{ planTarget(p) }}</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs muted">
              <span v-if="p.maintenance_type?.name" class="flex items-center gap-1">
                <Icon name="settings" :size="13" />
                {{ p.maintenance_type.name }}
              </span>
              <span class="flex items-center gap-1">
                <Icon name="refresh" :size="13" />
                {{ triggerText(p) }}
              </span>
              <span v-if="dueHint(p)" class="flex items-center gap-1">
                <Icon name="calendar" :size="13" />
                {{ dueHint(p) }}
              </span>
              <span class="flex items-center gap-1" :class="p.blocks_checkout_when_overdue ? 'text-danger-600' : ''">
                <Icon :name="p.blocks_checkout_when_overdue ? 'alert' : 'info'" :size="13" />
                {{ p.blocks_checkout_when_overdue ? 'Blocks pick-up' : 'Warn at pick-up' }}
              </span>
            </p>
          </div>
          <StatusBadge
            :status="planTint(p).status"
            :label="planTint(p).label"
            :icon="planTint(p).icon"
          />
        </li>
      </ul>
    </div>

    <!-- Jobs -->
    <div v-else class="space-y-3">
      <form v-if="canManage" class="card-pad space-y-4" @submit.prevent="createWorkOrder">
        <div class="flex items-center gap-2">
          <Icon name="hammer" :size="18" class="text-content-muted" />
          <p class="section-title">Add a job</p>
        </div>

        <div>
          <label class="label">What needs doing?</label>
          <input v-model="woForm.title" type="text" required class="input" placeholder="Example: change the mower blade" />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Which tool? (number)</label>
            <input v-model.number="woForm.item_id" type="number" required class="input" placeholder="Example: 12" />
          </div>
          <div>
            <label class="label">How soon?</label>
            <select v-model="woForm.priority" class="select">
              <option v-for="level in PRIORITIES" :key="level.value" :value="level.value">{{ level.label }}</option>
            </select>
          </div>
        </div>

        <p v-if="woError" class="flex items-center gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" />
          {{ woError }}
        </p>

        <button type="submit" class="btn-primary" :disabled="savingWorkOrder">
          <Icon :name="savingWorkOrder ? 'refresh' : 'plus'" :size="18" />
          {{ savingWorkOrder ? 'Saving…' : 'Add the job' }}
        </button>
      </form>

      <div v-if="loadingWorkOrders" class="space-y-3">
        <div v-for="i in 3" :key="i" class="skeleton h-24" />
      </div>

      <EmptyState
        v-else-if="!workOrders.length"
        icon="hammer"
        title="No jobs yet"
        hint="Add a job when a tool needs work."
      />

      <ul v-else class="space-y-3">
        <li v-for="wo in workOrders" :key="wo.id" class="card p-4 space-y-3">
          <div class="flex flex-wrap items-start gap-3">
            <span
              class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
              :class="wo.status === 'completed' ? 'bg-brand-100 text-brand-700' : 'bg-warn-100 text-warn-600'"
            >
              <Icon :name="wo.status === 'completed' ? 'check-circle' : 'wrench'" :size="20" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-content">{{ wo.title }}</p>
              <p class="text-xs text-content-muted mt-0.5">{{ wo.item?.label || `Tool #${wo.item_id}` }}</p>
              <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs muted">
                <span class="font-mono">{{ wo.reference }}</span>
                <span v-if="wo.item?.asset_tag" class="flex items-center gap-1">
                  <Icon name="qr" :size="13" />
                  <span class="font-mono">{{ wo.item.asset_tag }}</span>
                </span>
                <span v-if="wo.completed_at" class="flex items-center gap-1">
                  <Icon name="check" :size="13" />
                  {{ formatDate(wo.completed_at) }}
                </span>
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              <StatusBadge
                v-if="wo.priority && wo.priority !== 'normal'"
                :status="wo.priority"
                :label="priorityLabel(wo.priority)"
              />
              <StatusBadge :status="wo.status" :label="workOrderLabel(wo.status)" />
            </div>
          </div>

          <div
            v-if="canManage && wo.status !== 'completed' && completeForms[wo.id]"
            class="rounded-xl bg-surface p-3 space-y-3"
          >
            <p class="section-title flex items-center gap-2">
              <Icon name="clipboard" :size="16" class="text-content-muted" />
              Log the work
            </p>

            <div class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="label">Hours worked</label>
                <input v-model.number="completeForms[wo.id].labour_hours" type="number" min="0" step="0.25" class="input" placeholder="0" />
              </div>
              <div>
                <label class="label">Parts cost</label>
                <input v-model.number="completeForms[wo.id].parts_cost" type="number" min="0" step="0.01" class="input" placeholder="0.00" />
              </div>
            </div>

            <div>
              <label class="label">What did you do?</label>
              <input v-model="completeForms[wo.id].completion_notes" type="text" class="input" placeholder="Example: new blade fitted" />
            </div>

            <p v-if="completeForms[wo.id].error" class="flex items-center gap-2 text-sm text-danger-600">
              <Icon name="alert" :size="16" />
              {{ completeForms[wo.id].error }}
            </p>

            <button class="btn-primary btn-sm" :disabled="completeForms[wo.id].saving" @click="completeWorkOrder(wo)">
              <Icon :name="completeForms[wo.id].saving ? 'refresh' : 'check'" :size="16" />
              {{ completeForms[wo.id].saving ? 'Saving…' : 'Log the work' }}
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import StatTile from '../../components/StatTile.vue';

const auth = useAuthStore();
const toasts = useToastStore();

const canManage = computed(() => auth.can('manage_maintenance'));

const tabs = [
  { value: 'types', label: 'Kinds of service', icon: 'settings' },
  { value: 'plans', label: 'Service plans', icon: 'calendar' },
  { value: 'work_orders', label: 'Jobs', icon: 'hammer' },
];
const tab = ref('types');

const TYPE_KINDS = [
  { value: 'preventive', label: 'Planned service', icon: 'calendar' },
  { value: 'corrective', label: 'Repair', icon: 'wrench' },
  { value: 'inspection', label: 'Check', icon: 'eye' },
];

const TRIGGERS = [
  { value: 'calendar', label: 'After a number of days' },
  { value: 'usage_hours', label: 'After hours of use' },
  { value: 'loan_count', label: 'After a number of loans' },
  { value: 'fuel_cycles', label: 'After a number of fuel fills' },
];

const PRIORITIES = [
  { value: 'low', label: 'Low — it can wait' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High — this week' },
  { value: 'urgent', label: 'Urgent — today' },
];

const PRIORITY_LABELS = { low: 'Can wait', high: 'This week', urgent: 'Today' };

/** Work orders start as "draft", which reads oddly next to a repair job. */
const WORK_ORDER_LABELS = {
  draft: 'Not started',
  in_progress: 'Being fixed',
  completed: 'Done',
  cancelled: 'Cancelled',
};

const DUE_SOON_DAYS = 7;

const types = ref([]);
const plans = ref([]);
const workOrders = ref([]);
const catalogItems = ref([]);
const toolTypes = ref([]);
const loadingTypes = ref(true);
const loadingPlans = ref(true);
const loadingWorkOrders = ref(true);
const completeForms = reactive({});

const savingType = ref(false);
const savingPlan = ref(false);
const savingWorkOrder = ref(false);
const typeError = ref('');
const planError = ref('');
const woError = ref('');

const typeForm = reactive({ name: '', kind: 'preventive', requires_downtime: false, is_active: true });
const planForm = reactive({
  name: '',
  maintenance_type_id: null,
  item_id: null,
  tool_type_id: null,
  trigger_type: 'calendar',
  interval_days: 90,
  interval_hours: 100,
  interval_loans: 10,
  interval_fuel_cycles: 8,
  blocks_checkout_when_overdue: false,
});
const woForm = reactive({ item_id: null, title: '', priority: 'normal', maintenance_type_id: null });

const overdueCount = computed(() => plans.value.filter((p) => p.is_overdue).length);

const dueSoonCount = computed(() => plans.value.filter((p) => isDueSoon(p)).length);

function isDueSoon(plan) {
  if (plan.is_overdue || !plan.next_due_at) return false;

  const days = (new Date(plan.next_due_at) - Date.now()) / 86_400_000;

  return days >= 0 && days <= DUE_SOON_DAYS;
}

function kindMeta(kind) {
  return TYPE_KINDS.find((k) => k.value === kind) || { label: 'Service', icon: 'wrench' };
}

function planTint(plan) {
  if (plan.is_overdue) {
    return {
      card: 'border-danger-600/25 bg-danger-100/40',
      badge: 'bg-danger-100 text-danger-600',
      icon: 'alert',
      status: 'overdue',
      label: 'Overdue for service',
    };
  }

  if (isDueSoon(plan)) {
    return {
      card: 'border-warn-600/25 bg-warn-100/40',
      badge: 'bg-warn-100 text-warn-600',
      icon: 'wrench',
      status: 'medium',
      label: 'Due for service',
    };
  }

  return {
    card: '',
    badge: 'bg-neutral-100 text-content-muted',
    icon: 'check-circle',
    status: 'available',
    label: 'On track',
  };
}

function planTarget(plan) {
  if (plan.item?.label) return plan.item.label;
  if (plan.tool_type?.name) return `Every ${plan.tool_type.name}`;
  if (plan.tool_type_id) return 'Every tool in one group';

  return 'No tool picked';
}

function triggerText(plan) {
  if (plan.trigger_type === 'calendar' && plan.interval_days) return `Every ${plan.interval_days} days`;
  if (plan.trigger_type === 'usage_hours' && plan.interval_hours) return `Every ${Number(plan.interval_hours)} hours of use`;
  if (plan.trigger_type === 'loan_count' && plan.interval_loans) return `Every ${plan.interval_loans} loans`;
  if (plan.trigger_type === 'fuel_cycles' && plan.interval_fuel_cycles) return `Every ${plan.interval_fuel_cycles} fuel fills`;

  return TRIGGERS.find((t) => t.value === plan.trigger_type)?.label || 'No repeat set';
}

function dueHint(plan) {
  if (plan.trigger_type === 'calendar' && plan.next_due_at) return formatDate(plan.next_due_at);
  if (plan.trigger_type === 'usage_hours' && plan.next_due_hours != null) return `Due at ${Number(plan.next_due_hours)} hours`;
  if (plan.trigger_type === 'loan_count' && plan.next_due_loans != null) return `Due after loan #${plan.next_due_loans}`;
  if (plan.trigger_type === 'fuel_cycles' && plan.next_due_fuel_cycles != null) return `Due after fill #${plan.next_due_fuel_cycles}`;

  return '';
}

function priorityLabel(priority) {
  return PRIORITY_LABELS[priority] || null;
}

function workOrderLabel(status) {
  return WORK_ORDER_LABELS[status] || null;
}

/** Every job keeps its own "log the work" draft so half-typed notes survive a reload of the list. */
function ensureLogForm(wo) {
  if (!completeForms[wo.id]) {
    completeForms[wo.id] = { labour_hours: null, parts_cost: null, completion_notes: '', saving: false, error: '' };
  }

  return completeForms[wo.id];
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
    : '—';
}

async function loadTypes() {
  loadingTypes.value = true;
  try {
    const { data } = await api.get('/maintenance/types');
    types.value = data.data;
  } catch {
    types.value = [];
    toasts.error('Could not load the kinds of service.');
  } finally {
    loadingTypes.value = false;
  }
}

async function loadPlans() {
  loadingPlans.value = true;
  try {
    const { data } = await api.get('/maintenance/plans');
    plans.value = data.data;
  } catch {
    plans.value = [];
    toasts.error('Could not load the service plans.');
  } finally {
    loadingPlans.value = false;
  }
}

async function loadWorkOrders() {
  loadingWorkOrders.value = true;
  try {
    const { data } = await api.get('/maintenance/work-orders');
    const rows = data.data.data || data.data;
    rows.forEach(ensureLogForm);
    workOrders.value = rows;
  } catch {
    workOrders.value = [];
    toasts.error('Could not load the jobs.');
  } finally {
    loadingWorkOrders.value = false;
  }
}

async function createType() {
  savingType.value = true;
  typeError.value = '';
  try {
    await api.post('/maintenance/types', typeForm);
    typeForm.name = '';
    toasts.success('Kind of service added.');
    await loadTypes();
  } catch (e) {
    typeError.value = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    savingType.value = false;
  }
}

async function loadCatalogPickers() {
  try {
    const [itemsRes, typesRes] = await Promise.all([
      api.get('/items', { params: { per_page: 200 } }),
      api.get('/tool-types', { params: { active_only: 1 } }).catch(() => api.get('/catalog/categories')),
    ]);
    const itemPayload = itemsRes.data.data;
    catalogItems.value = itemPayload.data || itemPayload || [];
    const typePayload = typesRes.data.data;
    toolTypes.value = Array.isArray(typePayload)
      ? typePayload.flatMap((c) => c.tool_types || [c]).filter((t) => t?.id && t?.name)
      : [];
  } catch {
    catalogItems.value = [];
    toolTypes.value = [];
  }
}

async function createPlan() {
  savingPlan.value = true;
  planError.value = '';
  try {
    const payload = {
      name: planForm.name,
      maintenance_type_id: planForm.maintenance_type_id,
      item_id: planForm.item_id || undefined,
      tool_type_id: planForm.tool_type_id || undefined,
      trigger_type: planForm.trigger_type,
      blocks_checkout_when_overdue: planForm.blocks_checkout_when_overdue,
      interval_days: planForm.trigger_type === 'calendar' ? planForm.interval_days : undefined,
      interval_hours: planForm.trigger_type === 'usage_hours' ? planForm.interval_hours : undefined,
      interval_loans: planForm.trigger_type === 'loan_count' ? planForm.interval_loans : undefined,
      interval_fuel_cycles: planForm.trigger_type === 'fuel_cycles' ? planForm.interval_fuel_cycles : undefined,
    };
    await api.post('/maintenance/plans', payload);
    planForm.name = '';
    toasts.success('Service plan added.');
    await loadPlans();
  } catch (e) {
    planError.value = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    savingPlan.value = false;
  }
}

async function createWorkOrder() {
  savingWorkOrder.value = true;
  woError.value = '';
  try {
    await api.post('/maintenance/work-orders', woForm);
    woForm.title = '';
    woForm.item_id = null;
    toasts.success('Job added.');
    await loadWorkOrders();
  } catch (e) {
    woError.value = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    savingWorkOrder.value = false;
  }
}

async function completeWorkOrder(wo) {
  const form = ensureLogForm(wo);
  form.saving = true;
  form.error = '';
  try {
    await api.post(`/maintenance/work-orders/${wo.id}/complete`, {
      labour_hours: form.labour_hours,
      parts_cost: form.parts_cost,
      completion_notes: form.completion_notes,
    });
    toasts.success('Work logged. Thanks.');
    await loadWorkOrders();
  } catch (e) {
    form.error = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    form.saving = false;
  }
}

watch(tab, (value) => {
  if (value === 'types' && !types.value.length) loadTypes();
  if (value === 'plans' && !plans.value.length) loadPlans();
  if (value === 'work_orders' && !workOrders.value.length) loadWorkOrders();
});

onMounted(() => {
  loadTypes();
  loadPlans();
  loadWorkOrders();
  loadCatalogPickers();
});
</script>
