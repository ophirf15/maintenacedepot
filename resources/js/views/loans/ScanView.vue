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
              :class="form.action === a.value ? 'border-ink-900 bg-ink-900 text-white' : 'border-line bg-white text-neutral-700 hover:bg-neutral-50'"
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
              <p class="text-sm font-semibold text-neutral-900">
                {{ actionLabel(e.action) }}
                <span v-if="e.loan_id" class="muted font-normal">· loan #{{ e.loan_id }}</span>
              </p>
              <p class="font-mono text-xs muted truncate">{{ e.qr_token }}</p>
              <p class="mt-1 flex items-start gap-1.5 text-xs" :class="stateText(e.status)">
                <Icon :name="stateIcon(e.status)" :size="13" class="mt-0.5 shrink-0" />
                <span>{{ stateLabel(e.status) }}{{ e.error ? ` — ${e.error}` : '' }}</span>
              </p>
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
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { addScan, listPending, removeEvent, syncAll } from '../../offline/outbox';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';

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

let scanner = null;

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
    // syncAll records the reason on each event; the list shows it.
    toasts.error('Could not save. It stays on this phone — try again when you have signal.');
  } finally {
    syncing.value = false;
    refresh();
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

onMounted(() => {
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
});

onBeforeUnmount(() => {
  window.removeEventListener('online', handleOnline);
  window.removeEventListener('offline', handleOffline);
  stopScanner();
});
</script>
