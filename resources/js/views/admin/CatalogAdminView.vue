<template>
  <div class="space-y-5">
    <PageHeader
      title="Tool groups and types"
      subtitle="Name the equipment and give each one a picture so the crew can spot it fast."
      icon="grid"
    >
      <template #actions>
        <RouterLink to="/inventory" class="btn-secondary btn-sm">
          <Icon name="boxes" :size="17" />
          Equipment list
        </RouterLink>
      </template>
    </PageHeader>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in TABS"
        :key="t.key"
        type="button"
        :class="tab === t.key ? 'chip-active' : 'chip'"
        @click="tab = t.key"
      >
        <Icon :name="t.icon" :size="16" />
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-20" />
    </div>

    <!-- Groups -->
    <template v-else-if="tab === 'categories'">
      <form class="card space-y-4 p-4" @submit.prevent="saveCategory">
        <div class="flex items-center gap-2">
          <Icon name="plus" :size="18" class="text-brand-700" />
          <h2 class="font-semibold text-content">
            {{ categoryForm.id ? 'Edit this group' : 'Add a tool group' }}
          </h2>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="cat-name">Group name</label>
            <input
              id="cat-name"
              v-model="categoryForm.name"
              class="input"
              placeholder="e.g. Lawn Equipment"
              required
            />
            <p class="mt-1 text-xs muted">This is the tile people tap on the Browse tools screen.</p>
          </div>

          <div>
            <label class="label" for="cat-colour">Tile colour</label>
            <input id="cat-colour" v-model="categoryForm.color" type="color" class="input h-11 p-1" />
          </div>
        </div>

        <div>
          <span class="label">Picture</span>
          <IconPicker v-model="categoryForm.icon" :suggest-from="categoryForm.name" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="submit" class="btn-primary" :disabled="saving">
            <Icon name="check" :size="17" />
            {{ saving ? 'Saving…' : categoryForm.id ? 'Save changes' : 'Add group' }}
          </button>
          <button v-if="categoryForm.id" type="button" class="btn-ghost" @click="resetCategory">
            Cancel
          </button>
        </div>
      </form>

      <EmptyState
        v-if="!categories.length"
        icon="grid"
        title="No tool groups yet"
        hint="Add your first group above, for example “Lawn Equipment”."
      />

      <ul v-else class="grid gap-3 sm:grid-cols-2">
        <li v-for="cat in categories" :key="cat.id" class="card flex items-center gap-3 p-4">
          <span
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white"
            :style="{ background: cat.color || '#15694f' }"
          >
            <Icon :name="iconFor(cat)" :size="24" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-content">{{ cat.name }}</p>
            <p class="text-xs muted">{{ cat.tool_types_count || 0 }} tool types</p>
          </div>
          <button type="button" class="btn-secondary btn-sm" @click="editCategory(cat)">
            <Icon name="edit" :size="16" />
            Edit
          </button>
        </li>
      </ul>
    </template>

    <!-- Tool types -->
    <template v-else>
      <form class="card space-y-4 p-4" @submit.prevent="saveToolType">
        <div class="flex items-center gap-2">
          <Icon name="plus" :size="18" class="text-brand-700" />
          <h2 class="font-semibold text-content">
            {{ toolTypeForm.id ? 'Edit this tool type' : 'Add a tool type' }}
          </h2>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="tt-name">Tool name</label>
            <input
              id="tt-name"
              v-model="toolTypeForm.name"
              class="input"
              placeholder="e.g. Gas Pressure Washer"
              required
            />
          </div>

          <div>
            <label class="label" for="tt-category">Belongs to group</label>
            <select id="tt-category" v-model="toolTypeForm.category_id" class="input" required>
              <option value="">Choose a group…</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div>
            <label class="label" for="tt-prefix">Tag prefix</label>
            <input id="tt-prefix" v-model="toolTypeForm.sku_prefix" class="input" placeholder="PWG" />
            <p class="mt-1 text-xs muted">Used at the start of asset tags, e.g. PWG-DEMO-01.</p>
          </div>

          <div>
            <label class="label" for="tt-days">Normal borrow length (days)</label>
            <input id="tt-days" v-model.number="toolTypeForm.default_loan_days" type="number" min="1" class="input" />
          </div>
        </div>

        <div>
          <span class="label">Picture</span>
          <IconPicker v-model="toolTypeForm.icon" :suggest-from="toolTypeForm.name" />
        </div>

        <div v-if="toolTypeForm.id" class="space-y-3 rounded-xl border border-line bg-surface/80 p-3">
          <div class="flex items-center justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-content">Spec fields</p>
              <p class="text-xs muted">Things that differ per unit — PSI, ladder height, amps…</p>
            </div>
          </div>
          <ul v-if="editingSpecFields.length" class="space-y-2">
            <li
              v-for="field in editingSpecFields"
              :key="field.id"
              class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm"
            >
              <span class="min-w-0 flex-1 font-medium">{{ field.label }}<span v-if="field.unit" class="muted"> ({{ field.unit }})</span></span>
              <button type="button" class="btn-ghost btn-sm text-danger-600" @click="removeSpecField(field)">
                <Icon name="trash" :size="15" />
                Remove
              </button>
            </li>
          </ul>
          <div class="grid gap-2 sm:grid-cols-4">
            <input v-model="newSpec.label" class="input sm:col-span-2" placeholder="Label, e.g. PSI" />
            <input v-model="newSpec.unit" class="input" placeholder="Unit, e.g. psi" />
            <select v-model="newSpec.field_type" class="select">
              <option value="number">Number</option>
              <option value="text">Text</option>
            </select>
          </div>
          <button type="button" class="btn-secondary btn-sm" :disabled="!newSpec.label || savingSpec" @click="addSpecField">
            <Icon name="plus" :size="15" />
            {{ savingSpec ? 'Adding…' : 'Add spec field' }}
          </button>
        </div>

        <div v-if="toolTypeForm.id" class="space-y-3 rounded-xl border border-line bg-surface/80 p-3">
          <div>
            <p class="text-sm font-semibold text-content">Often needs</p>
            <p class="text-xs muted">Companion or consumable types suggested at pick-up (e.g. batteries for cordless drills).</p>
          </div>
          <ul v-if="typeLinks.length" class="space-y-2">
            <li
              v-for="(link, idx) in typeLinks"
              :key="`${link.child_tool_type_id}-${link.role}-${idx}`"
              class="flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm"
            >
              <span class="min-w-0 flex-1 font-medium">
                {{ linkLabel(link) }}
                <span class="muted capitalize"> · {{ link.role }}</span>
              </span>
              <button type="button" class="btn-ghost btn-sm text-danger-600" @click="typeLinks.splice(idx, 1)">
                Remove
              </button>
            </li>
          </ul>
          <div class="grid gap-2 sm:grid-cols-3">
            <select v-model="newTypeLink.child_tool_type_id" class="select sm:col-span-1">
              <option value="">Choose type…</option>
              <option v-for="tt in toolTypes" :key="tt.id" :value="tt.id" :disabled="tt.id === toolTypeForm.id">
                {{ tt.name }}
              </option>
            </select>
            <select v-model="newTypeLink.role" class="select">
              <option value="companion">Companion</option>
              <option value="consumable">Consumable</option>
            </select>
            <button type="button" class="btn-secondary btn-sm" :disabled="!newTypeLink.child_tool_type_id" @click="addTypeLink">
              Add
            </button>
          </div>
          <button type="button" class="btn-secondary btn-sm" :disabled="savingLinks" @click="saveTypeLinks">
            {{ savingLinks ? 'Saving…' : 'Save “often needs”' }}
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button type="submit" class="btn-primary" :disabled="saving">
            <Icon name="check" :size="17" />
            {{ saving ? 'Saving…' : toolTypeForm.id ? 'Save changes' : 'Add tool type' }}
          </button>
          <button v-if="toolTypeForm.id" type="button" class="btn-ghost" @click="resetToolType">
            Cancel
          </button>
        </div>
      </form>

      <EmptyState
        v-if="!toolTypes.length"
        icon="tools"
        title="No tool types yet"
        hint="Add a tool type, then add the individual units on the equipment list."
      />

      <ul v-else class="grid gap-3 sm:grid-cols-2">
        <li v-for="tt in toolTypes" :key="tt.id" class="card flex items-center gap-3 p-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
            <Icon :name="iconFor(tt)" :size="24" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-content">{{ tt.name }}</p>
            <p class="truncate text-xs muted">
              {{ tt.category?.name || 'No group' }} · {{ tt.items_count || 0 }} units
            </p>
          </div>
          <button type="button" class="btn-secondary btn-sm" @click="editToolType(tt)">
            <Icon name="edit" :size="16" />
            Edit
          </button>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../../api';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import IconPicker from '../../components/IconPicker.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import { guessIcon, iconFor } from '../../icons';

const TABS = [
  { key: 'categories', label: 'Tool groups', icon: 'grid' },
  { key: 'tool-types', label: 'Tool types', icon: 'tools' },
];

const toasts = useToastStore();

const tab = ref('categories');
const loading = ref(true);
const saving = ref(false);
const categories = ref([]);
const toolTypes = ref([]);

const categoryForm = reactive({ id: null, name: '', icon: '', color: '#15694f' });
const toolTypeForm = reactive({
  id: null,
  name: '',
  icon: '',
  category_id: '',
  sku_prefix: '',
  default_loan_days: null,
});
const editingSpecFields = ref([]);
const savingSpec = ref(false);
const newSpec = reactive({ label: '', unit: '', field_type: 'number' });
const typeLinks = ref([]);
const savingLinks = ref(false);
const newTypeLink = reactive({ child_tool_type_id: '', role: 'companion' });

function resetCategory() {
  Object.assign(categoryForm, { id: null, name: '', icon: '', color: '#15694f' });
}

function resetToolType() {
  Object.assign(toolTypeForm, {
    id: null,
    name: '',
    icon: '',
    category_id: '',
    sku_prefix: '',
    default_loan_days: null,
  });
  editingSpecFields.value = [];
  typeLinks.value = [];
  Object.assign(newSpec, { label: '', unit: '', field_type: 'number' });
  Object.assign(newTypeLink, { child_tool_type_id: '', role: 'companion' });
}

function linkLabel(link) {
  return link.child_type?.name
    || toolTypes.value.find((t) => t.id === link.child_tool_type_id)?.name
    || `Type #${link.child_tool_type_id}`;
}

function editCategory(cat) {
  Object.assign(categoryForm, {
    id: cat.id,
    name: cat.name,
    icon: cat.icon || '',
    color: cat.color || '#15694f',
  });
}

async function editToolType(tt) {
  Object.assign(toolTypeForm, {
    id: tt.id,
    name: tt.name,
    icon: tt.icon || '',
    category_id: tt.category_id,
    sku_prefix: tt.sku_prefix || '',
    default_loan_days: tt.default_loan_days,
  });
  editingSpecFields.value = tt.spec_fields || [];
  try {
    const { data } = await api.get(`/tool-types/${tt.id}/spec-fields`);
    editingSpecFields.value = data.data;
  } catch {
    // keep whatever came with the type list
  }
  try {
    const { data } = await api.get(`/tool-types/${tt.id}/links`);
    typeLinks.value = (data.data || []).map((l) => ({
      child_tool_type_id: l.child_tool_type_id,
      role: l.role,
      is_required: l.is_required,
      child_type: l.child_type,
    }));
  } catch {
    typeLinks.value = [];
  }
}

function addTypeLink() {
  const id = Number(newTypeLink.child_tool_type_id);
  if (!id) return;
  if (typeLinks.value.some((l) => l.child_tool_type_id === id && l.role === newTypeLink.role)) {
    return;
  }
  typeLinks.value = [
    ...typeLinks.value,
    {
      child_tool_type_id: id,
      role: newTypeLink.role,
      is_required: false,
      child_type: toolTypes.value.find((t) => t.id === id) || null,
    },
  ];
  Object.assign(newTypeLink, { child_tool_type_id: '', role: 'companion' });
}

async function saveTypeLinks() {
  if (!toolTypeForm.id) return;
  savingLinks.value = true;
  try {
    const { data } = await api.put(`/tool-types/${toolTypeForm.id}/links`, {
      links: typeLinks.value.map((l) => ({
        child_tool_type_id: l.child_tool_type_id,
        role: l.role,
        is_required: !!l.is_required,
      })),
    });
    typeLinks.value = (data.data || []).map((l) => ({
      child_tool_type_id: l.child_tool_type_id,
      role: l.role,
      is_required: l.is_required,
      child_type: l.child_type,
    }));
    toasts.success('Often-needs links saved.');
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not save type links.');
  } finally {
    savingLinks.value = false;
  }
}

async function addSpecField() {
  if (!toolTypeForm.id || !newSpec.label) return;
  savingSpec.value = true;
  try {
    const { data } = await api.post(`/tool-types/${toolTypeForm.id}/spec-fields`, {
      label: newSpec.label,
      unit: newSpec.unit || null,
      field_type: newSpec.field_type,
    });
    editingSpecFields.value = [...editingSpecFields.value, data.data];
    Object.assign(newSpec, { label: '', unit: '', field_type: 'number' });
    toasts.success('Spec field added');
    await load();
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not add that field.');
  } finally {
    savingSpec.value = false;
  }
}

async function removeSpecField(field) {
  try {
    await api.delete(`/tool-types/${toolTypeForm.id}/spec-fields/${field.id}`);
    editingSpecFields.value = editingSpecFields.value.filter((f) => f.id !== field.id);
    toasts.success('Spec field removed');
    await load();
  } catch {
    toasts.error('Could not remove that field.');
  }
}

async function load() {
  loading.value = true;
  try {
    const [cats, types] = await Promise.all([api.get('/categories'), api.get('/tool-types')]);
    categories.value = cats.data.data;
    toolTypes.value = types.data.data;
  } catch {
    toasts.error('Could not load the tool groups. Check your connection and try again.');
  } finally {
    loading.value = false;
  }
}

async function saveCategory() {
  saving.value = true;
  try {
    // Store the auto-suggestion so the picture stays put if the name changes later.
    const payload = {
      name: categoryForm.name,
      icon: categoryForm.icon || guessIcon(categoryForm.name),
      color: categoryForm.color,
    };

    if (categoryForm.id) {
      await api.put(`/categories/${categoryForm.id}`, payload);
      toasts.success(`Saved changes to ${payload.name}`);
    } else {
      await api.post('/categories', payload);
      toasts.success(`Added the ${payload.name} group`);
    }

    resetCategory();
    await load();
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not save that group.');
  } finally {
    saving.value = false;
  }
}

async function saveToolType() {
  saving.value = true;
  try {
    const payload = {
      name: toolTypeForm.name,
      icon: toolTypeForm.icon || guessIcon(toolTypeForm.name),
      category_id: toolTypeForm.category_id,
      sku_prefix: toolTypeForm.sku_prefix || null,
      default_loan_days: toolTypeForm.default_loan_days || null,
    };

    if (toolTypeForm.id) {
      await api.put(`/tool-types/${toolTypeForm.id}`, payload);
      toasts.success(`Saved changes to ${payload.name}`);
    } else {
      await api.post('/tool-types', payload);
      toasts.success(`Added ${payload.name}`);
    }

    resetToolType();
    await load();
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not save that tool type.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
