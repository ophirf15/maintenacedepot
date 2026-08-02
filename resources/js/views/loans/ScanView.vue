<template>
  <div class="space-y-5 max-w-xl">
    <PageHeader
      title="Scan pick-up or return"
      subtitle="Choose pick-up or return, scan the tag, then confirm. Works without signal."
      icon="scan"
    >
      <template #actions>
        <span
          class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
          :class="online ? 'border-brand-700/20 bg-brand-100 text-brand-700' : 'border-warn-600/25 bg-warn-100 text-warn-600'"
        >
          <Icon :name="online ? 'check-circle' : 'alert'" :size="14" :stroke-width="2" />
          {{ online ? 'You have signal' : 'No signal' }}
        </span>
      </template>
    </PageHeader>

    <!-- 1. Scan the tag -->
    <section class="card">
      <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-900 text-white text-sm font-semibold shrink-0">1</span>
        <div>
          <p class="section-title">Scan the tag</p>
          <p class="text-xs muted">Scan the QR or barcode, or type the full 6-digit number under the barcode.</p>
        </div>
      </header>

      <div class="space-y-4 border-t border-line p-4 sm:p-5">
        <button type="button" class="btn-secondary w-full" @click="toggleScanner">
          <Icon :name="scanning ? 'x' : 'camera'" :size="18" />
          {{ scanning ? 'Stop the camera' : 'Use the camera' }}
        </button>

        <div id="qr-reader" class="overflow-hidden rounded-xl bg-ink-900" :class="scanning ? 'block' : 'hidden'" />

        <p v-if="scanError" class="flex items-start gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" class="mt-0.5 shrink-0" />
          {{ scanError }}
        </p>

        <div>
          <label class="label" for="qr-token">Tool number</label>
          <input
            id="qr-token"
            v-model="form.qr_token"
            type="text"
            maxlength="6"
            minlength="6"
            inputmode="numeric"
            pattern="[0-9]*"
            enterkeyhint="done"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            class="input h-14 text-2xl font-mono tracking-wider"
            placeholder="6-digit number from the sticker"
            @keyup.enter="queueScan"
          />
          <p class="mt-1.5 text-xs muted">Camera accepts QR or barcode. Enter all 6 digits — short numbers will not match.</p>
        </div>

        <div>
          <p class="label">What are you doing?</p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="a in ACTIONS"
              :key="a.value"
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl border h-12 px-3 text-sm font-semibold transition"
              :class="form.action === a.value ? 'border-ink-900 bg-ink-900 text-white' : 'border-line bg-[var(--color-surface-raised)] text-neutral-700 hover:bg-[var(--color-surface)]'"
              @click="form.action = a.value"
            >
              <Icon :name="a.icon" :size="18" />
              {{ a.label }}
            </button>
          </div>
        </div>

        <div>
          <label class="label" for="loan-id">Loan number (only if you know it)</label>
          <input id="loan-id" v-model="form.loan_id" type="number" class="input" placeholder="Example: 42" />
        </div>

        <p v-if="formError" class="flex items-center gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" />
          {{ formError }}
        </p>

        <button type="button" class="btn-primary w-full" @click="queueScan">
          <Icon name="plus" :size="18" />
          Add to the list
        </button>
      </div>
    </section>

    <!-- 2. Check the list -->
    <section class="card">
      <header class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5 pb-3">
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-900 text-white text-sm font-semibold shrink-0">2</span>
          <div>
            <p class="section-title">Check the list</p>
            <p class="text-xs muted">{{ pending.length }} scanned · {{ unsentCount }} not sent yet</p>
          </div>
        </div>
      </header>

      <div class="border-t border-line p-4 sm:p-5">
        <EmptyState
          v-if="!pending.length"
          icon="qr"
          title="Nothing scanned yet"
          hint="Scan a tag above and it will show up here."
        />

        <ul v-else class="space-y-2.5">
          <li
            v-for="e in pending"
            :key="e.client_uuid"
            class="card flex items-start gap-3 p-3.5"
          >
            <span
              class="flex h-9 w-9 items-center justify-center rounded-lg shrink-0"
              :class="stateTile(e.status)"
              aria-hidden="true"
            >
              <Icon :name="stateIcon(e.status)" :size="18" />
            </span>

            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold">
                {{ actionLabel(e.action) }}
                <span v-if="e.loan_id" class="muted font-normal">· loan #{{ e.loan_id }}</span>
              </p>
              <p class="font-mono text-xs muted truncate">{{ e.qr_token }}</p>
              <p class="mt-1 flex items-start gap-1.5 text-xs" :class="stateText(e.status)">
                <Icon :name="stateIcon(e.status)" :size="13" class="mt-0.5 shrink-0" />
                <span>{{ stateLabel(e.status) }}{{ e.error ? ` — ${e.error}` : '' }}</span>
              </p>
              <button
                v-if="canException && isNoLoanError(e)"
                type="button"
                class="mt-2 text-xs font-semibold text-brand-700 underline"
                @click="openExceptionFromEvent(e)"
              >
                {{ e.action === 'return' ? 'Record orphan return…' : 'Start walk-in checkout…' }}
              </button>
            </div>

            <button type="button" class="btn-ghost btn-sm shrink-0" @click="remove(e.client_uuid)">
              <Icon name="trash" :size="16" />
              <span class="sr-only">Remove</span>
            </button>
          </li>
        </ul>
      </div>
    </section>

    <!-- 3. Confirm -->
    <section class="card">
      <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-900 text-white text-sm font-semibold shrink-0">3</span>
        <div>
          <p class="section-title">Confirm scans</p>
          <p class="text-xs muted">Save pick-ups and returns to the depot.</p>
        </div>
      </header>

      <div class="space-y-3 border-t border-line p-4 sm:p-5">
        <p
          v-if="!online && unsentCount"
          class="flex items-start gap-2 rounded-xl border border-warn-600/25 bg-warn-100 px-3 py-2.5 text-sm text-warn-600"
        >
          <Icon name="info" :size="16" class="mt-0.5 shrink-0" />
          Saved on this phone — will send when you have signal.
        </p>

        <button type="button" class="btn-primary w-full" :disabled="syncing || !unsentCount" @click="sync">
          <Icon :name="syncing ? 'refresh' : 'upload'" :size="18" />
          {{ syncing ? 'Saving…' : 'Confirm scans' }}
        </button>
      </div>
    </section>

    <!-- 4. Exceptions (staff) -->
    <section v-if="canException" class="card">
      <header class="flex items-center gap-2.5 p-4 sm:p-5 pb-3">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-900 text-white text-sm font-semibold shrink-0">4</span>
        <div>
          <p class="section-title">No matching loan?</p>
          <p class="text-xs muted">Walk-in pick-up or orphan return when the tool was never reserved.</p>
        </div>
      </header>

      <div class="space-y-4 border-t border-line p-4 sm:p-5">
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="flex items-center justify-center gap-2 rounded-xl border h-12 px-3 text-sm font-semibold transition"
            :class="exceptionMode === 'walk-in' ? 'border-ink-900 bg-ink-900 text-white' : 'border-line'"
            @click="exceptionMode = 'walk-in'"
          >
            Walk-in pick-up
          </button>
          <button
            type="button"
            class="flex items-center justify-center gap-2 rounded-xl border h-12 px-3 text-sm font-semibold transition"
            :class="exceptionMode === 'orphan' ? 'border-ink-900 bg-ink-900 text-white' : 'border-line'"
            @click="exceptionMode = 'orphan'"
          >
            Orphan return
          </button>
        </div>

        <div>
          <label class="label" for="ex-qr">Tool number</label>
          <input
            id="ex-qr"
            v-model="exception.qr_token"
            type="text"
            maxlength="64"
            class="input font-mono"
            placeholder="6-digit number or QR token"
          />
        </div>

        <div>
          <label class="label" for="ex-borrower">Who is borrowing / who had it?</label>
          <input
            id="ex-borrower"
            v-model="borrowerQuery"
            type="search"
            class="input"
            placeholder="Search name or email"
            @input="searchBorrowers"
          />
          <ul v-if="borrowers.length" class="mt-2 max-h-40 overflow-y-auto rounded-xl border border-line divide-y divide-line">
            <li v-for="u in borrowers" :key="u.id">
              <button
                type="button"
                class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-[var(--color-surface)]"
                :class="exception.borrower_id === u.id ? 'bg-brand-100' : ''"
                @click="selectBorrower(u)"
              >
                <span class="font-medium">{{ u.name }}</span>
                <span class="text-xs muted">{{ u.email }}</span>
              </button>
            </li>
          </ul>
          <p v-if="selectedBorrower" class="mt-1.5 text-xs text-brand-700">
            Selected: {{ selectedBorrower.name }}
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="label" for="ex-property">Property</label>
            <select id="ex-property" v-model="exception.property_id" class="input" required>
              <option disabled value="">Choose…</option>
              <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="label" for="ex-depot">Depot</label>
            <select id="ex-depot" v-model="exception.depot_id" class="input" required>
              <option disabled value="">Choose…</option>
              <option v-for="d in depots" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
        </div>

        <div v-if="exceptionMode === 'walk-in'">
          <label class="label" for="ex-due">Return by</label>
          <input id="ex-due" v-model="exception.due_at" type="datetime-local" class="input" required />
        </div>

        <div v-if="exceptionMode === 'orphan'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="label" for="ex-condition">Condition in</label>
            <select id="ex-condition" v-model="exception.condition" class="input">
              <option value="new">Like new</option>
              <option value="good">Good</option>
              <option value="fair">Okay</option>
              <option value="poor">Bad</option>
            </select>
          </div>
          <div>
            <label class="label" for="ex-fuel">Fuel %</label>
            <input id="ex-fuel" v-model.number="exception.fuel_pct" type="number" min="0" max="100" class="input" />
          </div>
          <div class="col-span-2">
            <label class="label" for="ex-hours">Hours used (estimate)</label>
            <input id="ex-hours" v-model.number="exception.usage_hours_estimate" type="number" min="0" step="0.1" class="input" />
          </div>
          <label class="col-span-2 flex items-center gap-2 text-sm">
            <input v-model="exception.damage_found" type="checkbox" class="rounded border-line" />
            Something is broken
          </label>
          <div v-if="exception.damage_found" class="col-span-2">
            <label class="label" for="ex-damage">What is wrong?</label>
            <textarea id="ex-damage" v-model="exception.damage_description" class="input min-h-[4.5rem]" />
          </div>
        </div>

        <div>
          <label class="label" for="ex-notes">Notes</label>
          <input
            id="ex-notes"
            v-model="exception.notes"
            type="text"
            class="input"
            :placeholder="exceptionMode === 'walk-in' ? 'Why no prior request?' : 'Where was it found?'"
          />
        </div>

        <p v-if="exceptionError" class="flex items-start gap-2 text-sm text-danger-600">
          <Icon name="alert" :size="16" class="mt-0.5 shrink-0" />
          {{ exceptionError }}
        </p>

        <button type="button" class="btn-primary w-full" :disabled="exceptionSaving" @click="submitException">
          <Icon :name="exceptionSaving ? 'refresh' : 'check'" :size="18" />
          {{
            exceptionSaving
              ? 'Saving…'
              : exceptionMode === 'walk-in'
                ? 'Create walk-in loan & check out'
                : 'Create loan & record return'
          }}
        </button>

        <RouterLink
          v-if="lastExceptionLoanId"
          :to="`/loans/${lastExceptionLoanId}`"
          class="btn-secondary w-full"
        >
          Open loan #{{ lastExceptionLoanId }}
        </RouterLink>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { addScan, listPending, removeEvent, syncAll } from '../../offline/outbox';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';

const auth = useAuthStore();
const toasts = useToastStore();

const ACTIONS = [
  { value: 'checkout', label: 'Pick-up', icon: 'package' },
  { value: 'return', label: 'Return', icon: 'arrow-left' },
];

const form = reactive({ qr_token: '', action: 'checkout', loan_id: '' });
const formError = ref('');
const pending = ref(listPending());
const scanning = ref(false);
const scanError = ref('');
const syncing = ref(false);
const online = ref(navigator.onLine);

const canException = computed(() => auth.can('checkout_items'));
const exceptionMode = ref('walk-in');
const exceptionSaving = ref(false);
const exceptionError = ref('');
const lastExceptionLoanId = ref(null);
const properties = ref([]);
const depots = ref([]);
const borrowers = ref([]);
const borrowerQuery = ref('');
const selectedBorrower = ref(null);

const exception = reactive({
  qr_token: '',
  borrower_id: null,
  property_id: '',
  depot_id: '',
  due_at: '',
  condition: 'good',
  fuel_pct: 50,
  usage_hours_estimate: null,
  damage_found: false,
  damage_description: '',
  notes: '',
});

let scanner = null;
let borrowerTimer = null;

const unsentCount = computed(() => pending.value.filter((e) => e.status !== 'synced').length);

function refresh() {
  pending.value = listPending();
}

function actionLabel(action) {
  return ACTIONS.find((a) => a.value === action)?.label || action;
}

const STATE_LABELS = {
  synced: 'Saved to the depot',
  failed: 'Did not send',
  pending: 'Saved on this phone — will send when you have signal',
};

function stateLabel(status) {
  return STATE_LABELS[status] || STATE_LABELS.pending;
}

function stateIcon(status) {
  if (status === 'synced') return 'check-circle';
  if (status === 'failed') return 'alert';

  return 'clock';
}

function stateTile(status) {
  if (status === 'synced') return 'bg-brand-100 text-brand-700';
  if (status === 'failed') return 'bg-danger-100 text-danger-600';

  return 'bg-neutral-100 text-neutral-500';
}

function stateText(status) {
  if (status === 'synced') return 'text-brand-700';
  if (status === 'failed') return 'text-danger-600';

  return 'muted';
}

function isNoLoanError(event) {
  if (event.status !== 'failed' || !event.error) return false;
  return /no active loan/i.test(event.error);
}

function defaultDueLocal() {
  const days = Number(auth.config?.public?.['defaults.default_max_loan_days'] || 7);
  const d = new Date(Date.now() + days * 24 * 60 * 60 * 1000);
  const pad = (n) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toApiDate(localValue) {
  if (!localValue) return undefined;
  const d = new Date(localValue);

  return Number.isNaN(d.getTime()) ? undefined : d.toISOString();
}

function openExceptionFromEvent(event) {
  exceptionMode.value = event.action === 'return' ? 'orphan' : 'walk-in';
  exception.qr_token = event.qr_token || '';
  exceptionError.value = '';
  document.getElementById('ex-qr')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function selectBorrower(user) {
  exception.borrower_id = user.id;
  selectedBorrower.value = user;
  borrowerQuery.value = user.name;
  borrowers.value = [];
}

async function searchBorrowers() {
  clearTimeout(borrowerTimer);
  borrowerTimer = setTimeout(async () => {
    const q = borrowerQuery.value.trim();
    if (q.length < 1) {
      borrowers.value = [];
      return;
    }
    try {
      const { data } = await api.get('/loans/borrowers', { params: { q } });
      borrowers.value = data.data || [];
    } catch {
      borrowers.value = [];
    }
  }, 250);
}

function queueScan() {
  formError.value = '';
  if (!form.qr_token.trim()) {
    formError.value = 'Scan a tag or type the tool number first.';
    return;
  }

  addScan({
    action: form.action,
    qrToken: form.qr_token,
    loanId: form.loan_id ? Number(form.loan_id) : null,
  });
  form.qr_token = '';
  form.loan_id = '';
  refresh();
  toasts.success('Added to the list.');
}

function remove(clientUuid) {
  removeEvent(clientUuid);
  refresh();
}

async function sync() {
  syncing.value = true;
  try {
    await syncAll();
    toasts.success('Scans confirmed and saved.');
  } catch {
    toasts.error('Could not save. It stays on this phone — try again when you have signal.');
  } finally {
    syncing.value = false;
    refresh();
  }
}

async function submitException() {
  exceptionError.value = '';
  lastExceptionLoanId.value = null;

  if (!exception.qr_token.trim()) {
    exceptionError.value = 'Enter the tool number.';
    return;
  }
  if (!exception.borrower_id) {
    exceptionError.value = 'Select who is borrowing or who had the tool.';
    return;
  }
  if (!exception.property_id || !exception.depot_id) {
    exceptionError.value = 'Choose property and depot.';
    return;
  }

  exceptionSaving.value = true;
  try {
    if (exceptionMode.value === 'walk-in') {
      if (!exception.due_at) {
        exceptionError.value = 'Choose a return-by date.';
        exceptionSaving.value = false;
        return;
      }
      const { data } = await api.post('/loans/walk-in', {
        borrower_id: exception.borrower_id,
        qr_token: exception.qr_token.trim(),
        property_id: Number(exception.property_id),
        depot_id: Number(exception.depot_id),
        due_at: toApiDate(exception.due_at),
        notes: exception.notes || undefined,
      });
      lastExceptionLoanId.value = data.data?.id;
      toasts.success(`Walk-in loan ${data.data?.reference || ''} checked out.`);
    } else {
      const { data } = await api.post('/loans/orphan-return', {
        borrower_id: exception.borrower_id,
        qr_token: exception.qr_token.trim(),
        property_id: Number(exception.property_id),
        depot_id: Number(exception.depot_id),
        condition: exception.condition,
        fuel_pct: exception.fuel_pct,
        usage_hours_estimate: exception.usage_hours_estimate || undefined,
        damage_found: exception.damage_found,
        damage_description: exception.damage_found ? exception.damage_description : undefined,
        overall_result: exception.damage_found ? 'fail' : 'pass',
        notes: exception.notes || undefined,
      });
      lastExceptionLoanId.value = data.data?.id;
      toasts.success(`Orphan return recorded on ${data.data?.reference || 'loan'}.`);
    }
  } catch (error) {
    const errors = error.response?.data?.errors;
    const first = errors ? Object.values(errors).flat()[0] : null;
    exceptionError.value = first || error.response?.data?.message || 'Could not save exception.';
  } finally {
    exceptionSaving.value = false;
  }
}

async function toggleScanner() {
  scanError.value = '';
  if (scanning.value) {
    await stopScanner();
    return;
  }

  try {
    const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
    scanner = new Html5Qrcode('qr-reader', {
      formatsToSupport: [
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E,
      ],
      verbose: false,
    });
    scanning.value = true;
    await scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 260, height: 160 } },
      (decodedText) => {
        form.qr_token = decodedText.trim();
      },
      () => {},
    );
  } catch {
    scanning.value = false;
    scanError.value = 'The camera did not open. You can type the number instead.';
  }
}

async function stopScanner() {
  try {
    await scanner?.stop();
    await scanner?.clear();
  } catch {
    // scanner may already be stopped
  }
  scanning.value = false;
}

function handleOnline() {
  online.value = true;
}
function handleOffline() {
  online.value = false;
}

watch(exceptionMode, () => {
  exceptionError.value = '';
});

onMounted(async () => {
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  exception.due_at = defaultDueLocal();

  if (canException.value) {
    try {
      const [propsRes, depotsRes] = await Promise.all([
        api.get('/properties'),
        api.get('/depots', { params: { active_only: 1 } }),
      ]);
      const propPage = propsRes.data.data;
      properties.value = Array.isArray(propPage) ? propPage : (propPage?.data || []);
      const depotPage = depotsRes.data.data;
      depots.value = Array.isArray(depotPage) ? depotPage : (depotPage?.data || []);
      if (properties.value[0]) exception.property_id = properties.value[0].id;
      if (depots.value[0]) exception.depot_id = depots.value[0].id;
    } catch {
      // picker stays empty; submit will validate
    }
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  clearTimeout(borrowerTimer);
  stopScanner();
});
</script>
