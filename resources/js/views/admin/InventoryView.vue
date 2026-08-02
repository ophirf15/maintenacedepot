<template>
  <div class="space-y-5">
    <PageHeader
      title="Equipment list"
      subtitle="Every tool and machine, where it is kept, and whether it can go out."
      icon="boxes"
    >
      <template #actions>
        <button class="btn-primary btn-sm" @click="showForm = !showForm">
          <Icon :name="showForm ? 'x' : 'plus'" :size="16" />
          {{ showForm ? 'Close' : 'Add a tool' }}
        </button>
      </template>
    </PageHeader>

    <section class="card-pad space-y-3">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem] flex-1">
          <label class="label" for="label-size">Label size</label>
          <select id="label-size" v-model="labelSize" class="select" @change="onLabelSizeChange">
            <option v-for="s in labelSizes" :key="s.key" :value="s.key">
              {{ s.label }}
            </option>
          </select>
          <p class="mt-1 text-xs muted">{{ activeSizeHint }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="btn-secondary btn-sm" :disabled="exporting || printing" @click="exportAllSheet">
            <Icon name="file" :size="16" />
            PDF all
          </button>
          <button class="btn-secondary btn-sm" :disabled="exporting || printing" @click="exportAllZip">
            <Icon name="download" :size="16" />
            ZIP all
          </button>
          <button class="btn-secondary btn-sm" :disabled="!selected.length || exporting || printing" @click="exportSheet">
            <Icon name="file" :size="16" />
            PDF selected<span v-if="selected.length"> ({{ selected.length }})</span>
          </button>
          <button class="btn-secondary btn-sm" :disabled="!selected.length || exporting || printing" @click="exportLabels">
            <Icon name="qr" :size="16" />
            ZIP selected<span v-if="selected.length"> ({{ selected.length }})</span>
          </button>
          <button
            class="btn-primary btn-sm"
            :disabled="!canPrintNiimbot || printing || (!selected.length && !items.length)"
            @click="printNiimbotSelected"
          >
            <Icon :name="printing ? 'refresh' : 'qr'" :size="16" />
            {{ printing ? printStatus : 'Print to NiimBot' }}
          </button>
        </div>
      </div>
      <p v-if="!canPrintNiimbot" class="text-xs text-warn-600">
        Direct NiimBot print needs Chrome or Edge on HTTPS (or localhost). You can still download PNG/PDF.
      </p>
      <p v-else class="text-xs muted">
        Print to NiimBot uses the selected size and prints checked tools (or the whole list if none are checked). Pair the printer when Chrome asks.
      </p>
    </section>

    <form v-if="showForm" class="card overflow-hidden" @submit.prevent="createItem">
      <header class="flex items-center gap-2 border-b border-line p-4 sm:p-5">
        <Icon name="plus" :size="18" class="text-neutral-400" />
        <p class="section-title">Add a tool</p>
      </header>

      <div class="grid gap-4 p-4 sm:p-5 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <label class="label">Name</label>
          <input v-model="form.name" type="text" class="input" placeholder="Leave blank to use the type name" />
        </div>
        <div>
          <label class="label">Kind of tool</label>
          <select v-model.number="form.tool_type_id" required class="select">
            <option disabled :value="null">Choose one…</option>
            <option v-for="tt in toolTypes" :key="tt.id" :value="tt.id">{{ tt.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model.number="form.custom_status_id" required class="select">
            <option disabled :value="null">Choose one…</option>
            <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Depot it lives at</label>
          <select v-if="depots.length" v-model.number="form.depot_id" required class="select">
            <option disabled :value="null">Choose one…</option>
            <option v-for="d in depots" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <input v-else v-model.number="form.depot_id" type="number" required class="input" placeholder="Depot number" />
        </div>
        <div>
          <label class="label">Asset tag</label>
          <input v-model="form.asset_tag" type="text" class="input" placeholder="We make one if you leave this blank" />
        </div>
        <div>
          <label class="label">QR sticker token</label>
          <input
            v-model="form.qr_token"
            type="text"
            class="input font-mono"
            placeholder="Leave blank to auto-make, or paste a pre-printed sticker"
          />
          <p class="mt-1 text-xs muted">Slap a blank QR sticker on the tool, scan/paste its code here to claim it.</p>
        </div>
        <div>
          <label class="label">Serial number</label>
          <input v-model="form.serial_number" type="text" class="input" placeholder="From the maker's plate" />
        </div>
        <div>
          <label class="label">Purchase date</label>
          <input v-model="form.purchase_date" type="date" class="input" />
        </div>
        <div>
          <label class="label">Price paid</label>
          <input v-model.number="form.purchase_price" type="number" step="0.01" class="input" placeholder="0.00" />
        </div>
        <div>
          <label class="label">Cost to replace</label>
          <input v-model.number="form.replacement_cost" type="number" step="0.01" class="input" placeholder="0.00" />
        </div>
        <div>
          <label class="label">Salvage value</label>
          <input v-model.number="form.salvage_value" type="number" step="0.01" class="input" placeholder="0.00" />
        </div>
        <div>
          <label class="label">Years it should last</label>
          <input v-model.number="form.lifespan_years" type="number" min="1" class="input" placeholder="5" />
        </div>
        <div>
          <label class="label">Warranty ends</label>
          <input v-model="form.warranty_expires_on" type="date" class="input" />
        </div>

        <label class="flex items-start gap-2.5 rounded-xl bg-neutral-50 p-3 text-sm text-neutral-700">
          <input v-model="form.is_loanable" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
          <span>
            People can borrow it
            <span class="block text-xs muted">Untick for tools that stay at the depot.</span>
          </span>
        </label>
        <label class="flex items-start gap-2.5 rounded-xl bg-neutral-50 p-3 text-sm text-neutral-700">
          <input v-model="form.is_consumable" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
          <span>
            It gets used up
            <span class="block text-xs muted">Tick for things like blades, oil or bags.</span>
          </span>
        </label>

        <template v-if="form.is_consumable">
          <div>
            <label class="label">Starting stock</label>
            <input v-model.number="form.stock_qty" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Unit</label>
            <input v-model="form.stock_unit" type="text" class="input" placeholder="ea, box, lb…" />
          </div>
          <div>
            <label class="label">Reorder when at or below</label>
            <input v-model.number="form.reorder_point" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Typical restock qty</label>
            <input v-model.number="form.reorder_qty" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Supplier</label>
            <input v-model="form.supplier_name" type="text" class="input" placeholder="Where you buy it" />
          </div>
          <div>
            <label class="label">Supplier part number</label>
            <input v-model="form.supplier_part_number" type="text" class="input" />
          </div>
          <div>
            <label class="label">Typical cost each</label>
            <input v-model.number="form.typical_cost" type="number" min="0" step="0.01" class="input" />
          </div>
        </template>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 sm:px-5 py-3">
        <p v-if="error" class="flex items-center gap-1.5 text-sm text-danger-600">
          <Icon name="alert" :size="16" />
          {{ error }}
        </p>
        <span v-else />
        <button type="submit" class="btn-primary btn-sm" :disabled="submitting">
          <Icon :name="submitting ? 'refresh' : 'check'" :size="17" />
          {{ submitting ? 'Saving…' : 'Save tool' }}
        </button>
      </div>
    </form>

    <form class="flex flex-wrap items-center gap-2" @submit.prevent="load()">
      <div class="relative min-w-0 flex-1 sm:max-w-sm">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400">
          <Icon name="search" :size="17" />
        </span>
        <input
          v-model="q"
          type="search"
          class="input pl-9"
          placeholder="Search by name, tag or serial number"
          aria-label="Search equipment"
        />
      </div>
      <button type="submit" class="btn-secondary btn-sm">
        <Icon name="search" :size="16" />
        Search
      </button>
      <label
        v-if="items.length"
        class="chip cursor-pointer"
        :class="allSelected ? 'chip-active' : ''"
      >
        <input type="checkbox" class="sr-only" :checked="allSelected" @change="toggleAll($event)" />
        <Icon :name="allSelected ? 'check-circle' : 'check'" :size="15" />
        Select all
      </label>
    </form>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="boxes"
      title="No tools found"
      hint="Try a different search word, or add a tool with the button above."
    />

    <ul v-else class="card divide-rows overflow-hidden">
      <li
        v-for="item in items"
        :key="item.id"
        class="flex flex-wrap items-center gap-3 p-3 sm:p-4 hover:bg-neutral-50"
      >
        <input
          v-model="selected"
          type="checkbox"
          :value="item.id"
          class="h-4 w-4 shrink-0 rounded border-neutral-300"
          :aria-label="`Pick ${item.label || item.asset_tag} for labels`"
        />

        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-neutral-100 text-neutral-500">
          <img v-if="item.image_url" :src="item.image_url" alt="" class="h-full w-full object-cover" />
          <Icon v-else :name="iconForItem(item)" :size="20" />
        </span>

        <div class="min-w-0 flex-1 basis-48">
          <RouterLink
            :to="`/inventory/items/${item.id}`"
            class="block truncate font-medium text-neutral-900 hover:text-brand-700"
          >
            {{ item.label || item.asset_tag }}
          </RouterLink>
          <p class="font-mono text-xs muted">
            #{{ item.numeric_id }}
            <span aria-hidden="true"> · </span>
            {{ item.asset_tag }}
          </p>
          <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs muted">
            <span v-if="item.tool_type?.name" class="flex items-center gap-1">
              <Icon name="wrench" :size="13" />
              {{ item.tool_type.name }}
            </span>
            <span v-if="item.depot?.name" class="flex items-center gap-1">
              <Icon name="pin" :size="13" />
              {{ item.depot.name }}
            </span>
          </p>
        </div>

        <StatusBadge :status="item.status?.slug" :label="item.status?.name" :color="item.status?.color" />

        <button type="button" class="btn-secondary btn-sm" @click="generateQr(item)">
          <Icon name="qr" :size="16" />
          Download
        </button>
        <button
          type="button"
          class="btn-secondary btn-sm"
          :disabled="!canPrintNiimbot || printing"
          @click="printNiimbotOne(item)"
        >
          <Icon name="qr" :size="16" />
          NiimBot
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { iconForItem } from '../../icons';
import { downloadBlob } from '../../download';
import {
  bluetoothPrintSupported,
  loadSavedLabelSize,
  printPngBlobsToNiimbot,
  printPngBlobToNiimbot,
  saveLabelSize,
} from '../../niimbotPrint';

const toasts = useToastStore();

const items = ref([]);
const toolTypes = ref([]);
const statuses = ref([]);
const depots = ref([]);
const selected = ref([]);
const loading = ref(true);
const exporting = ref(false);
const printing = ref(false);
const printStatus = ref('Printing…');
const showForm = ref(false);
const submitting = ref(false);
const error = ref('');
const q = ref('');
const labelSizes = ref([]);
const labelSize = ref(loadSavedLabelSize('standard'));
const canPrintNiimbot = bluetoothPrintSupported();

const activeSizeHint = computed(() => {
  const found = labelSizes.value.find((s) => s.key === labelSize.value);
  return found?.hint || 'Choose a sticker size for downloads and NiimBot printing.';
});

function onLabelSizeChange() {
  saveLabelSize(labelSize.value);
}

const form = reactive({
  name: '',
  tool_type_id: null,
  custom_status_id: null,
  depot_id: null,
  asset_tag: '',
  qr_token: '',
  serial_number: '',
  purchase_date: '',
  purchase_price: null,
  replacement_cost: null,
  salvage_value: null,
  lifespan_years: null,
  warranty_expires_on: '',
  is_loanable: true,
  is_consumable: false,
  stock_qty: 0,
  stock_unit: 'ea',
  reorder_point: 0,
  reorder_qty: null,
  supplier_name: '',
  supplier_part_number: '',
  typical_cost: null,
});

const allSelected = computed(() => items.value.length > 0 && selected.value.length === items.value.length);

function toggleAll(e) {
  selected.value = e.target.checked ? items.value.map((i) => i.id) : [];
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get('/items', {
      params: { ...(q.value ? { q: q.value } : {}), per_page: 200 },
    });
    items.value = data.data.data || data.data;
  } catch {
    items.value = [];
    toasts.error('Could not load the equipment list.');
  } finally {
    loading.value = false;
  }
}

function blankForm() {
  Object.assign(form, {
    name: '',
    tool_type_id: null,
    custom_status_id: null,
    depot_id: null,
    asset_tag: '',
    qr_token: '',
    serial_number: '',
    purchase_date: '',
    purchase_price: null,
    replacement_cost: null,
    salvage_value: null,
    lifespan_years: null,
    warranty_expires_on: '',
    is_loanable: true,
    is_consumable: false,
    stock_qty: 0,
    stock_unit: 'ea',
    reorder_point: 0,
    reorder_qty: null,
    supplier_name: '',
    supplier_part_number: '',
    typical_cost: null,
  });
}

async function createItem() {
  submitting.value = true;
  error.value = '';
  try {
    await api.post('/items', {
      ...form,
      asset_tag: form.asset_tag || undefined,
      qr_token: form.qr_token || undefined,
    });
    showForm.value = false;
    blankForm();
    await load();
    toasts.success('The tool was added to the list.');
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save this tool. Check the boxes above.';
  } finally {
    submitting.value = false;
  }
}

async function generateQr(item) {
  try {
    await api.post(`/qr/items/${item.id}/generate`, { size: labelSize.value });
    const response = await api.get(`/qr/items/${item.id}/label`, {
      params: { size: labelSize.value },
      responseType: 'blob',
    });
    downloadBlob(
      new Blob([response.data], { type: 'image/png' }),
      `${item.asset_tag}-${labelSize.value}-label.png`,
    );
    toasts.success('Label downloaded.');
  } catch {
    toasts.error('Could not make the label.');
  }
}

async function fetchLabelBlob(itemId) {
  await api.post(`/qr/items/${itemId}/generate`, { size: labelSize.value });
  const response = await api.get(`/qr/items/${itemId}/label`, {
    params: { size: labelSize.value },
    responseType: 'blob',
  });
  return new Blob([response.data], { type: 'image/png' });
}

async function printNiimbotOne(item) {
  printing.value = true;
  printStatus.value = 'Connecting…';
  try {
    const blob = await fetchLabelBlob(item.id);
    await printPngBlobToNiimbot(blob, {
      onStatus: (msg) => {
        printStatus.value = msg;
      },
    });
    toasts.success(`Printed ${item.asset_tag} to NiimBot.`);
  } catch (e) {
    toasts.error(e?.message || 'Could not print to NiimBot.');
  } finally {
    printing.value = false;
  }
}

async function printNiimbotSelected() {
  const ids = selected.value.length ? [...selected.value] : items.value.map((i) => i.id);
  if (!ids.length) {
    toasts.error('No tools to print.');
    return;
  }

  printing.value = true;
  printStatus.value = 'Preparing…';
  try {
    const blobs = [];
    for (let i = 0; i < ids.length; i++) {
      printStatus.value = `Building label ${i + 1} of ${ids.length}…`;
      blobs.push(await fetchLabelBlob(ids[i]));
    }
    await printPngBlobsToNiimbot(blobs, {
      onStatus: (msg) => {
        printStatus.value = msg;
      },
      onProgress: (n, total) => {
        printStatus.value = `Printing ${n} of ${total}…`;
      },
    });
    toasts.success(`Printed ${ids.length} label${ids.length === 1 ? '' : 's'} to NiimBot.`);
  } catch (e) {
    toasts.error(e?.message || 'Could not print to NiimBot.');
  } finally {
    printing.value = false;
  }
}

async function exportLabels() {
  await downloadExport(
    '/qr/export-zip',
    { item_ids: selected.value, size: labelSize.value },
    `equipment-labels-${labelSize.value}.zip`,
    'Labels downloaded.',
  );
}

async function exportSheet() {
  await downloadExport(
    '/qr/sheet',
    { item_ids: selected.value, size: labelSize.value },
    `equipment-label-sheet-${labelSize.value}.pdf`,
    'Label sheet downloaded — open the PDF to print.',
    'application/pdf',
  );
}

async function exportAllZip() {
  await downloadExport(
    '/qr/export-zip',
    { all: true, size: labelSize.value },
    `equipment-labels-all-${labelSize.value}.zip`,
    'All labels downloaded as a ZIP.',
  );
}

async function exportAllSheet() {
  await downloadExport(
    '/qr/sheet',
    { all: true, size: labelSize.value },
    `equipment-label-sheet-all-${labelSize.value}.pdf`,
    'All labels downloaded as a PDF sheet.',
    'application/pdf',
  );
}

async function downloadExport(path, body, filename, successMessage, mime = null) {
  exporting.value = true;
  try {
    const response = await api.post(path, body, { responseType: 'blob' });
    downloadBlob(new Blob([response.data], mime ? { type: mime } : undefined), filename);
    toasts.success(successMessage);
  } catch {
    toasts.error('Could not download the labels.');
  } finally {
    exporting.value = false;
  }
}

onMounted(async () => {
  await load();
  try {
    const [tt, st, sizes] = await Promise.all([
      api.get('/tool-types'),
      api.get('/custom-statuses'),
      api.get('/qr/sizes'),
    ]);
    toolTypes.value = tt.data.data;
    statuses.value = st.data.data;
    labelSizes.value = sizes.data.data || [];
    if (!labelSizes.value.some((s) => s.key === labelSize.value) && labelSizes.value[0]) {
      labelSize.value = labelSizes.value[0].key;
      saveLabelSize(labelSize.value);
    }
  } catch {
    // reference data optional
  }
  try {
    const { data } = await api.get('/depots');
    depots.value = data.data;
  } catch {
    depots.value = [];
  }
});
</script>
