<template>
  <div class="space-y-5">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="skeleton h-64" />
        <div class="skeleton h-64" />
      </div>
    </div>

    <template v-else-if="item">
      <PageHeader
        :title="item.label || item.asset_tag"
        :subtitle="subtitle"
        icon="package"
        back-to="/inventory"
        back-label="Equipment list"
      >
        <template #actions>
          <StatusBadge :status="item.status?.slug" :label="item.status?.name" :color="item.status?.color" />
        </template>
      </PageHeader>

      <p class="-mt-2 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs muted">
        <span class="font-mono font-semibold text-neutral-800">#{{ item.numeric_id }}</span>
        <span aria-hidden="true">·</span>
        <Icon name="qr" :size="13" />
        <span class="font-mono">{{ item.asset_tag }}</span>
      </p>

      <div class="grid gap-4 sm:gap-5 sm:grid-cols-2">
        <section class="card overflow-hidden">
          <header class="flex items-center gap-2 border-b border-line p-4 sm:p-5">
            <Icon name="info" :size="18" class="text-neutral-400" />
            <p class="section-title">About this tool</p>
          </header>
          <dl class="divide-rows text-sm">
            <div v-for="row in detailRows" :key="row.label" class="flex items-center justify-between gap-3 px-4 sm:px-5 py-2.5">
              <dt class="flex items-center gap-2 muted">
                <Icon :name="row.icon" :size="15" />
                {{ row.label }}
              </dt>
              <dd class="text-right font-medium text-neutral-900" :class="row.capitalize ? 'capitalize' : ''">
                {{ row.value }}
              </dd>
            </div>
          </dl>
        </section>

        <div class="space-y-4 sm:space-y-5">
          <section class="card-pad space-y-4">
            <div class="flex items-center gap-2">
              <Icon name="camera" :size="18" class="text-neutral-400" />
              <p class="section-title">Photo of this unit</p>
            </div>
            <p class="text-sm muted">Helps crews pick the right one when units look alike.</p>

            <div v-if="item.image_url" class="overflow-hidden rounded-xl border border-line">
              <img :src="item.image_url" :alt="item.label" class="max-h-56 w-full object-cover" />
            </div>
            <form class="space-y-3" @submit.prevent="uploadImage">
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="input h-auto py-2.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-neutral-700"
                aria-label="Choose a photo"
                @change="onImageChange"
              />
              <button type="submit" class="btn-secondary btn-sm" :disabled="!imageFile || uploadingImage">
                <Icon :name="uploadingImage ? 'refresh' : 'upload'" :size="16" />
                {{ uploadingImage ? 'Uploading…' : item.image_url ? 'Replace photo' : 'Upload photo' }}
              </button>
            </form>
          </section>

          <section v-if="specFields.length" class="card-pad space-y-3">
            <div class="flex items-center gap-2">
              <Icon name="ruler" :size="18" class="text-neutral-400" />
              <p class="section-title">Specs for this unit</p>
            </div>
            <p class="text-sm muted">Things that differ between units of this type, like PSI or height.</p>
            <div class="grid gap-3 sm:grid-cols-2">
              <div v-for="field in specFields" :key="field.id">
                <label class="label" :for="`spec-${field.key}`">
                  {{ field.label }}<span v-if="field.unit" class="muted"> ({{ field.unit }})</span>
                </label>
                <input
                  :id="`spec-${field.key}`"
                  v-model="specForm[field.key]"
                  class="input"
                  :type="field.field_type === 'number' ? 'number' : 'text'"
                  :placeholder="field.label"
                />
              </div>
            </div>
            <button type="button" class="btn-primary btn-sm" :disabled="savingSpecs" @click="saveSpecs">
              <Icon :name="savingSpecs ? 'refresh' : 'check'" :size="16" />
              {{ savingSpecs ? 'Saving…' : 'Save specs' }}
            </button>
          </section>

          <section class="card-pad space-y-4">
            <div class="flex items-center gap-2">
              <Icon name="qr" :size="18" class="text-neutral-400" />
              <p class="section-title">Printable label</p>
            </div>
            <p class="text-sm muted">QR for scanning, barcode (larger sizes) + 6-digit ID, and the tool name.</p>

            <div>
              <label class="label" for="item-label-size">Label size</label>
              <select id="item-label-size" v-model="labelSize" class="select" @change="onLabelSizeChange">
                <option v-for="s in labelSizes" :key="s.key" :value="s.key">{{ s.label }}</option>
              </select>
              <p class="mt-1 text-xs muted">{{ activeSizeHint }}</p>
            </div>

            <div v-if="qrUrl" class="flex flex-col items-center gap-3">
              <img
                :src="qrUrl"
                alt="Label preview"
                class="max-h-48 w-full rounded-xl border border-line bg-white object-contain p-2"
              />
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary btn-sm" @click="downloadQr">
                  <Icon name="download" :size="16" />
                  Download sticker
                </button>
                <button
                  type="button"
                  class="btn-primary btn-sm"
                  :disabled="!canPrintNiimbot || printing"
                  @click="printToNiimbot"
                >
                  <Icon :name="printing ? 'refresh' : 'qr'" :size="16" />
                  {{ printing ? 'Printing…' : 'Print to NiimBot' }}
                </button>
              </div>
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <button type="button" class="btn-primary btn-sm" :disabled="generating" @click="generateQr">
                <Icon :name="generating ? 'refresh' : 'qr'" :size="17" />
                {{ generating ? 'Making it…' : 'Make sticker' }}
              </button>
            </div>
            <p v-if="!canPrintNiimbot" class="text-xs text-warn-600">
              Direct NiimBot print needs Chrome or Edge on HTTPS (or localhost).
            </p>
          </section>

          <section class="card-pad space-y-3">
            <div class="flex items-center gap-2">
              <Icon name="book" :size="18" class="text-neutral-400" />
              <p class="section-title">Instruction book</p>
            </div>
            <p class="text-sm muted">The maker's manual, so crews can look up how to use it safely.</p>

            <a
              v-if="item.manual_path"
              :href="`/storage/${item.manual_path}`"
              target="_blank"
              class="btn-secondary btn-sm"
            >
              <Icon name="file" :size="16" />
              Open the manual
            </a>
            <form v-else class="space-y-3" @submit.prevent="uploadManual">
              <input
                type="file"
                accept=".pdf,.doc,.docx"
                class="input h-auto py-2.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-neutral-700"
                aria-label="Choose a manual file"
                @change="onFileChange"
              />
              <button type="submit" class="btn-secondary btn-sm" :disabled="!manualFile || uploading">
                <Icon :name="uploading ? 'refresh' : 'upload'" :size="16" />
                {{ uploading ? 'Uploading…' : 'Upload manual' }}
              </button>
            </form>
          </section>
        </div>
      </div>

      <section class="card-pad space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="boxes" :size="18" class="text-neutral-400" />
          <p class="section-title">Goes together with{{ item.is_kit ? ' (this is a set)' : '' }}</p>
        </div>
        <p class="text-sm muted">
          Companions (battery, mask, jerry can) are scanned onto the loan. Consumables (rods, blades) are issued by quantity.
        </p>

        <ul v-if="item.linked_children?.length" class="space-y-2">
          <li
            v-for="child in item.linked_children"
            :key="child.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-neutral-50 px-3 py-2 text-sm"
          >
            <span class="min-w-0">
              <span class="font-medium text-neutral-900">{{ child.label || child.asset_tag }}</span>
              <span class="ml-2 text-xs muted capitalize">{{ child.pivot?.role || 'companion' }}</span>
              <span v-if="child.pivot?.is_required" class="ml-1 text-xs text-warn-600">· suggested</span>
            </span>
            <button type="button" class="btn-ghost btn-sm text-danger-600" :disabled="unlinking === child.id" @click="unlinkChild(child)">
              Unlink
            </button>
          </li>
        </ul>
        <p v-else class="text-sm muted">Nothing linked yet.</p>

        <div v-if="item.linked_parents?.length" class="pt-1">
          <p class="label">Part of these sets</p>
          <ul class="flex flex-wrap gap-2">
            <li v-for="parent in item.linked_parents" :key="parent.id" class="chip">
              <Icon name="boxes" :size="15" />
              {{ parent.label || parent.asset_tag }}
            </li>
          </ul>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 pt-1">
          <div>
            <label class="label">Search item to link</label>
            <input v-model="linkSearch" type="search" class="input" placeholder="Name or tag" @input="searchLinkTargets" />
            <ul v-if="linkResults.length" class="mt-1 max-h-40 overflow-auto rounded-xl border border-line bg-white text-sm">
              <li
                v-for="hit in linkResults"
                :key="hit.id"
                class="cursor-pointer border-b border-line px-3 py-2 hover:bg-neutral-50 last:border-0"
                @click="selectLinkTarget(hit)"
              >
                {{ hit.label || hit.name }} <span class="muted">#{{ hit.id }}</span>
              </li>
            </ul>
            <p v-if="linkTarget" class="mt-1 text-xs text-neutral-700">Selected: {{ linkTarget.label || linkTarget.name }}</p>
          </div>
          <div>
            <label class="label">Link as</label>
            <select v-model="linkRole" class="select">
              <option value="companion">Companion (returnable)</option>
              <option value="consumable">Consumable (stock qty)</option>
            </select>
            <label class="mt-2 flex items-center gap-2 text-sm">
              <input v-model="linkRequired" type="checkbox" class="h-4 w-4 rounded border-neutral-300" />
              Suggest at pick-up
            </label>
          </div>
        </div>
        <button type="button" class="btn-secondary" :disabled="!linkTarget || linking" @click="linkItems">
          <Icon :name="linking ? 'refresh' : 'plus'" :size="17" />
          {{ linking ? 'Linking…' : 'Link them' }}
        </button>
      </section>

      <section v-if="item.is_consumable" class="card-pad space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="package" :size="18" class="text-neutral-400" />
          <p class="section-title">Stock &amp; reordering</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="label">On hand</label>
            <input v-model.number="stockForm.stock_qty" type="number" min="0" step="0.01" class="input" disabled />
            <p class="mt-1 text-xs muted">Use Restock / Set qty to change on-hand.</p>
          </div>
          <div>
            <label class="label">Unit</label>
            <input v-model="stockForm.stock_unit" type="text" class="input" />
          </div>
          <div>
            <label class="label">Reorder point</label>
            <input v-model.number="stockForm.reorder_point" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Typical restock qty</label>
            <input v-model.number="stockForm.reorder_qty" type="number" min="0" step="0.01" class="input" />
          </div>
          <div>
            <label class="label">Supplier</label>
            <input v-model="stockForm.supplier_name" type="text" class="input" />
          </div>
          <div>
            <label class="label">Part number</label>
            <input v-model="stockForm.supplier_part_number" type="text" class="input" />
          </div>
          <div>
            <label class="label">Typical cost</label>
            <input v-model.number="stockForm.typical_cost" type="number" min="0" step="0.01" class="input" />
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-secondary btn-sm" :disabled="savingStock" @click="saveStockMeta">
            {{ savingStock ? 'Saving…' : 'Save supplier &amp; reorder' }}
          </button>
          <button type="button" class="btn-primary btn-sm" @click="quickRestock">Restock</button>
        </div>
        <ul v-if="movements.length" class="divide-rows text-sm">
          <li v-for="m in movements" :key="m.id" class="flex justify-between gap-2 py-2">
            <span class="muted">{{ m.reason }} · {{ m.notes || '—' }}</span>
            <span class="tabular-nums font-medium" :class="Number(m.delta) < 0 ? 'text-danger-600' : 'text-neutral-900'">
              {{ Number(m.delta) > 0 ? '+' : '' }}{{ Number(m.delta) }} → {{ Number(m.balance_after) }}
            </span>
          </li>
        </ul>
      </section>

      <section v-if="damagePhotos.length" class="card-pad space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="camera" :size="18" class="text-neutral-400" />
          <p class="section-title">Damage photos</p>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
          <a
            v-for="photo in damagePhotos"
            :key="photo.id"
            :href="photo.url"
            target="_blank"
            class="aspect-square overflow-hidden rounded-xl border border-line"
          >
            <img :src="photo.url" :alt="photo.original_name" class="h-full w-full object-cover" />
          </a>
        </div>
      </section>

      <section v-if="item.maintenance_plans?.length" class="card overflow-hidden">
        <header class="flex items-start gap-2 border-b border-line p-4 sm:p-5">
          <Icon name="wrench" :size="18" class="mt-0.5 text-neutral-400" />
          <div>
            <p class="section-title">Servicing plans</p>
            <p class="text-sm muted">Regular checks and services booked for this tool.</p>
          </div>
        </header>
        <ul class="divide-rows">
          <li
            v-for="p in item.maintenance_plans"
            :key="p.id"
            class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 text-sm"
          >
            <span class="min-w-0 truncate font-medium text-neutral-900">{{ p.name }}</span>
            <StatusBadge
              :status="p.is_active ? 'available' : 'unavailable'"
              :label="p.is_active ? 'Running' : 'Paused'"
            />
          </li>
        </ul>
      </section>
    </template>

    <EmptyState
      v-else
      icon="alert"
      title="Tool not found"
      hint="It may have been removed from the list."
    >
      <RouterLink to="/inventory" class="btn-secondary btn-sm">
        <Icon name="arrow-left" :size="16" />
        Back to the equipment list
      </RouterLink>
    </EmptyState>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import { downloadBlob } from '../../download';
import {
  bluetoothPrintSupported,
  loadSavedLabelSize,
  printPngBlobToNiimbot,
  saveLabelSize,
} from '../../niimbotPrint';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const toasts = useToastStore();

const item = ref(null);
const loading = ref(true);
const qrUrl = ref('');
const generating = ref(false);
const printing = ref(false);
const labelSizes = ref([]);
const labelSize = ref(loadSavedLabelSize('standard'));
const canPrintNiimbot = bluetoothPrintSupported();
const manualFile = ref(null);
const uploading = ref(false);
const imageFile = ref(null);
const uploadingImage = ref(false);
const linking = ref(false);
const unlinking = ref(null);
const linkSearch = ref('');
const linkResults = ref([]);
const linkTarget = ref(null);
const linkRole = ref('companion');
const linkRequired = ref(true);
const damagePhotos = ref([]);
const specFields = ref([]);
const specForm = reactive({});
const savingSpecs = ref(false);
const stockForm = reactive({
  stock_unit: 'ea',
  reorder_point: 0,
  reorder_qty: null,
  supplier_name: '',
  supplier_part_number: '',
  typical_cost: null,
});
const savingStock = ref(false);
const movements = ref([]);
let linkSearchTimer = null;

function money(value) {
  if (value === null || value === undefined) return '—';

  return `$${Number(value).toFixed(2)}`;
}

const subtitle = computed(() => {
  const parts = [item.value?.tool_type?.category?.name, item.value?.tool_type?.name].filter(Boolean);

  return parts.join(' · ');
});

const activeSizeHint = computed(() => {
  const found = labelSizes.value.find((s) => s.key === labelSize.value);
  return found?.hint || '';
});

function onLabelSizeChange() {
  saveLabelSize(labelSize.value);
  if (qrUrl.value) {
    generateQr();
  }
}

const detailRows = computed(() => {
  const i = item.value || {};

  return [
    { label: 'Tool number', icon: 'qr', value: i.numeric_id || i.numeric_code || '—' },
    { label: 'Asset tag', icon: 'qr', value: i.asset_tag || '—' },
    { label: 'Where it is now', icon: 'pin', value: i.depot?.name || '—' },
    { label: 'Depot it belongs to', icon: 'depot', value: i.home_depot?.name || '—' },
    ...(i.current_property ? [{ label: 'On site', icon: 'building', value: i.current_property.name }] : []),
    { label: 'Serial number', icon: 'qr', value: i.serial_number || '—' },
    { label: 'Condition', icon: 'star', value: i.condition || '—', capitalize: true },
    { label: 'Hours used', icon: 'clock', value: i.usage_hours ?? '—' },
    { label: 'Fuel left', icon: 'fuel', value: i.fuel_pct !== null && i.fuel_pct !== undefined ? `${i.fuel_pct}%` : '—' },
    { label: 'Cost to replace', icon: 'money', value: money(i.replacement_cost) },
    { label: 'Bought on', icon: 'calendar', value: i.purchase_date || '—' },
  ];
});

function hydrateSpecs(data) {
  const fields = data.tool_type?.spec_fields || [];
  specFields.value = fields;
  Object.keys(specForm).forEach((k) => delete specForm[k]);
  const byKey = Object.fromEntries((data.specs || []).map((s) => [s.key, s.value]));
  fields.forEach((f) => {
    specForm[f.key] = byKey[f.key] || '';
  });
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get(`/items/${route.params.id}`);
    item.value = data.data;
    damagePhotos.value = data.damage_photos || [];
    hydrateSpecs(data.data);
    Object.assign(stockForm, {
      stock_unit: data.data.stock_unit || 'ea',
      reorder_point: Number(data.data.reorder_point || 0),
      reorder_qty: data.data.reorder_qty != null ? Number(data.data.reorder_qty) : null,
      supplier_name: data.data.supplier_name || '',
      supplier_part_number: data.data.supplier_part_number || '',
      typical_cost: data.data.typical_cost != null ? Number(data.data.typical_cost) : null,
    });
    if (data.data.is_consumable) {
      await loadMovements();
    }
  } catch {
    item.value = null;
    toasts.error('Could not open this tool.');
  } finally {
    loading.value = false;
  }
}

async function generateQr() {
  generating.value = true;
  try {
    await api.post(`/qr/items/${item.value.id}/generate`, { size: labelSize.value });
    const png = await api.get(`/qr/items/${item.value.id}/label`, {
      params: { size: labelSize.value },
      responseType: 'blob',
    });
    if (qrUrl.value?.startsWith('blob:')) {
      URL.revokeObjectURL(qrUrl.value);
    }
    qrUrl.value = URL.createObjectURL(new Blob([png.data], { type: 'image/png' }));
    toasts.success('Sticker ready.');
  } catch {
    toasts.error('Could not make the sticker.');
  } finally {
    generating.value = false;
  }
}

async function downloadQr() {
  try {
    const png = await api.get(`/qr/items/${item.value.id}/label`, {
      params: { size: labelSize.value },
      responseType: 'blob',
    });
    downloadBlob(
      new Blob([png.data], { type: 'image/png' }),
      `${item.value.asset_tag}-${labelSize.value}-label.png`,
    );
  } catch {
    toasts.error('Could not download the sticker.');
  }
}

async function printToNiimbot() {
  printing.value = true;
  try {
    await api.post(`/qr/items/${item.value.id}/generate`, { size: labelSize.value });
    const png = await api.get(`/qr/items/${item.value.id}/label`, {
      params: { size: labelSize.value },
      responseType: 'blob',
    });
    await printPngBlobToNiimbot(new Blob([png.data], { type: 'image/png' }));
    toasts.success('Sent to NiimBot.');
  } catch (e) {
    toasts.error(e?.message || 'Could not print to NiimBot.');
  } finally {
    printing.value = false;
  }
}

function onFileChange(e) {
  manualFile.value = e.target.files[0] || null;
}

function onImageChange(e) {
  imageFile.value = e.target.files[0] || null;
}

async function uploadImage() {
  if (!imageFile.value) return;
  uploadingImage.value = true;
  try {
    const formData = new FormData();
    formData.append('image', imageFile.value);
    const { data } = await api.post(`/items/${item.value.id}/image`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    item.value = { ...item.value, ...data.data, image_url: data.url || data.data.image_url };
    imageFile.value = null;
    toasts.success('Photo saved.');
  } catch {
    toasts.error('Could not upload that photo. Use a JPG or PNG under 10 MB.');
  } finally {
    uploadingImage.value = false;
  }
}

async function saveSpecs() {
  savingSpecs.value = true;
  try {
    const { data } = await api.put(`/items/${item.value.id}`, {
      depot_id: item.value.depot_id,
      tool_type_id: item.value.tool_type_id,
      custom_status_id: item.value.custom_status_id,
      specs: { ...specForm },
    });
    item.value = { ...item.value, ...data.data };
    hydrateSpecs(data.data);
    toasts.success('Specs saved.');
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not save specs.');
  } finally {
    savingSpecs.value = false;
  }
}

async function uploadManual() {
  if (!manualFile.value) return;
  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('manual', manualFile.value);
    const { data } = await api.post(`/items/${item.value.id}/manual`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    item.value = { ...item.value, ...data.data };
    manualFile.value = null;
    toasts.success('Manual uploaded.');
  } catch {
    toasts.error('Could not upload that file. It must be a PDF or Word file under 20 MB.');
  } finally {
    uploading.value = false;
  }
}

async function linkItems() {
  if (!linkTarget.value) return;

  linking.value = true;
  try {
    const { data } = await api.post(`/items/${item.value.id}/link-items`, {
      child_item_ids: [linkTarget.value.id],
      role: linkRole.value,
      is_required: linkRequired.value,
    });
    item.value = {
      ...item.value,
      linked_children: data.data.linked_children,
      is_kit: data.data.is_kit,
    };
    linkTarget.value = null;
    linkSearch.value = '';
    linkResults.value = [];
    toasts.success('Linked.');
  } catch {
    toasts.error('Could not link that item.');
  } finally {
    linking.value = false;
  }
}

async function unlinkChild(child) {
  unlinking.value = child.id;
  try {
    const { data } = await api.delete(`/items/${item.value.id}/link-items/${child.id}`);
    item.value = {
      ...item.value,
      linked_children: data.data.linked_children,
      is_kit: data.data.is_kit,
    };
    toasts.success('Unlinked.');
  } catch {
    toasts.error('Could not unlink.');
  } finally {
    unlinking.value = null;
  }
}

function searchLinkTargets() {
  clearTimeout(linkSearchTimer);
  linkSearchTimer = setTimeout(async () => {
    const q = linkSearch.value.trim();
    if (q.length < 2) {
      linkResults.value = [];
      return;
    }
    try {
      const { data } = await api.get('/items', { params: { q, per_page: 12 } });
      const rows = data.data.data || data.data || [];
      linkResults.value = rows.filter((r) => r.id !== item.value?.id);
    } catch {
      linkResults.value = [];
    }
  }, 250);
}

function selectLinkTarget(hit) {
  linkTarget.value = hit;
  linkSearch.value = hit.label || hit.name || '';
  linkResults.value = [];
}

async function loadMovements() {
  try {
    const { data } = await api.get('/stock/movements', { params: { item_id: item.value.id, per_page: 20 } });
    movements.value = data.data.data || data.data || [];
  } catch {
    movements.value = [];
  }
}

async function saveStockMeta() {
  savingStock.value = true;
  try {
    const { data } = await api.put(`/items/${item.value.id}`, {
      depot_id: item.value.depot_id,
      tool_type_id: item.value.tool_type_id,
      custom_status_id: item.value.custom_status_id,
      stock_unit: stockForm.stock_unit,
      reorder_point: stockForm.reorder_point,
      reorder_qty: stockForm.reorder_qty,
      supplier_name: stockForm.supplier_name || null,
      supplier_part_number: stockForm.supplier_part_number || null,
      typical_cost: stockForm.typical_cost,
    });
    item.value = { ...item.value, ...data.data };
    toasts.success('Stock settings saved.');
  } catch {
    toasts.error('Could not save stock settings.');
  } finally {
    savingStock.value = false;
  }
}

async function quickRestock() {
  const qty = window.prompt('How many to add?', String(stockForm.reorder_qty || 1));
  if (qty === null) return;
  const n = Number(qty);
  if (!n || n <= 0) {
    toasts.error('Enter a positive quantity.');
    return;
  }
  try {
    const { data } = await api.post(`/items/${item.value.id}/stock/restock`, { qty: n });
    item.value = { ...item.value, ...data.data };
    await loadMovements();
    toasts.success('Restocked.');
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not restock.');
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/qr/sizes');
    labelSizes.value = data.data || [];
    if (!labelSizes.value.some((s) => s.key === labelSize.value) && labelSizes.value[0]) {
      labelSize.value = labelSizes.value[0].key;
      saveLabelSize(labelSize.value);
    }
  } catch {
    labelSizes.value = [];
  }
  await load();
});
</script>

