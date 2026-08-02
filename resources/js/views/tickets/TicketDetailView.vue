<template>
  <div class="space-y-5 max-w-3xl">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div class="skeleton h-40" />
    </div>

    <template v-else-if="ticket">
      <PageHeader
        :title="ticket.title"
        :subtitle="ticket.item?.label || 'No tool on this report'"
        icon="ticket"
        back-to="/tickets"
        back-label="All damage reports"
      >
        <template #actions>
          <StatusBadge :status="ticket.status" />
          <StatusBadge :status="ticket.severity" />
        </template>
      </PageHeader>

      <p class="text-xs muted font-mono -mt-2">{{ ticket.reference }}</p>

      <!-- Unsafe tools must be impossible to miss. -->
      <div
        v-if="ticket.takes_out_of_service"
        class="flex items-start gap-2.5 rounded-2xl border border-danger-600/20 bg-danger-100 px-4 py-3 text-sm text-danger-600"
      >
        <Icon name="alert" :size="20" class="mt-0.5 shrink-0" />
        <span>
          <strong class="font-semibold">Do not use this tool.</strong>
          It is out of service until someone fixes it.
        </span>
      </div>

      <section class="card-pad space-y-4">
        <div class="flex items-center gap-2">
          <Icon name="alert" :size="18" class="text-warn-600" />
          <p class="section-title">What is wrong</p>
        </div>

        <p class="text-sm text-neutral-800 whitespace-pre-line">
          {{ ticket.description || 'Nobody wrote extra details.' }}
        </p>

        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
          <div class="flex items-start gap-2.5">
            <Icon name="user" :size="18" class="mt-0.5 text-neutral-400" />
            <div>
              <dt class="text-xs muted">Reported by</dt>
              <dd class="font-medium text-neutral-900">{{ ticket.reporter?.name || 'Unknown' }}</dd>
            </div>
          </div>
          <div class="flex items-start gap-2.5">
            <Icon name="calendar" :size="18" class="mt-0.5 text-neutral-400" />
            <div>
              <dt class="text-xs muted">Reported on</dt>
              <dd class="font-medium text-neutral-900">{{ formatDateTime(ticket.created_at) }}</dd>
            </div>
          </div>
          <div class="flex items-start gap-2.5">
            <Icon :name="typeMeta.icon" :size="18" class="mt-0.5 text-neutral-400" />
            <div>
              <dt class="text-xs muted">Kind of problem</dt>
              <dd class="font-medium text-neutral-900">{{ typeMeta.label }}</dd>
            </div>
          </div>
          <div class="flex items-start gap-2.5">
            <Icon name="wrench" :size="18" class="mt-0.5 text-neutral-400" />
            <div>
              <dt class="text-xs muted">Who is fixing it</dt>
              <dd class="font-medium text-neutral-900">{{ ticket.assignee?.name || 'Nobody yet' }}</dd>
            </div>
          </div>
        </dl>
      </section>

      <section class="card-pad space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="camera" :size="18" class="text-neutral-400" />
          <p class="section-title">Damage photos</p>
        </div>
        <div v-if="photos.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <a
            v-for="photo in photos"
            :key="photo.id"
            :href="photo.url"
            target="_blank"
            class="aspect-square overflow-hidden rounded-xl border border-line"
          >
            <img :src="photo.url" :alt="photo.original_name" class="h-full w-full object-cover" />
          </a>
        </div>
        <p v-else class="text-sm muted">No photos yet.</p>
        <form class="flex flex-wrap items-end gap-2" @submit.prevent="uploadPhoto">
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="input h-auto flex-1 py-2 text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-2 file:py-1 file:text-xs"
            @change="onPhotoChange"
          />
          <button type="submit" class="btn-secondary btn-sm" :disabled="!photoFile || uploadingPhoto">
            <Icon :name="uploadingPhoto ? 'refresh' : 'upload'" :size="15" />
            {{ uploadingPhoto ? 'Uploading…' : 'Add photo' }}
          </button>
        </form>
      </section>

      <section class="card-pad">
        <div class="flex items-center gap-2 mb-3">
          <Icon name="package" :size="18" class="text-neutral-400" />
          <p class="section-title">The tool</p>
        </div>

        <div v-if="ticket.item" class="flex items-start gap-3">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 shrink-0">
            <Icon name="package" :size="21" />
          </span>
          <div class="min-w-0">
            <p class="font-medium text-neutral-900">{{ ticket.item.label }}</p>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5 text-xs muted">
              <span v-if="ticket.item.asset_tag" class="flex items-center gap-1">
                <Icon name="qr" :size="13" />
                <span class="font-mono">{{ ticket.item.asset_tag }}</span>
              </span>
              <span v-if="ticket.item.serial_number" class="flex items-center gap-1">
                <Icon name="key" :size="13" />
                <span class="font-mono">{{ ticket.item.serial_number }}</span>
              </span>
            </p>
          </div>
        </div>

        <p v-else class="text-sm muted">This report is not about one tool.</p>
      </section>

      <section class="card">
        <header class="flex items-center gap-2 p-4 pb-3">
          <Icon name="hammer" :size="18" class="text-neutral-400" />
          <p class="section-title">What we did</p>
        </header>

        <ul v-if="ticket.work_orders?.length" class="divide-rows border-t border-line">
          <li v-for="wo in ticket.work_orders" :key="wo.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-neutral-900">{{ wo.title }}</p>
              <p class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5 text-xs muted">
                <span class="font-mono">{{ wo.reference }}</span>
                <span v-if="wo.completed_at" class="flex items-center gap-1">
                  <Icon name="check" :size="13" />
                  {{ formatDateTime(wo.completed_at) }}
                </span>
              </p>
            </div>
            <StatusBadge :status="wo.status" :label="workOrderLabel(wo.status)" />
          </li>
        </ul>

        <p v-else class="px-4 pb-4 text-sm muted">No work has been logged yet.</p>
      </section>

      <section v-if="ticket.resolved_at" class="card-pad border-brand-700/25 bg-brand-100/40 space-y-2">
        <div class="flex items-center gap-2">
          <Icon name="check-circle" :size="18" class="text-brand-700" />
          <p class="section-title">Fixed</p>
        </div>
        <p class="text-sm text-neutral-800 whitespace-pre-line">
          {{ ticket.resolution_notes || 'No notes were written.' }}
        </p>
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs muted">
          <span class="flex items-center gap-1">
            <Icon name="calendar" :size="13" />
            {{ formatDateTime(ticket.resolved_at) }}
          </span>
          <span v-if="ticket.resolution_code" class="flex items-center gap-1">
            <Icon name="file" :size="13" />
            <span class="font-mono">{{ ticket.resolution_code }}</span>
          </span>
          <span v-if="ticket.total_cost" class="flex items-center gap-1">
            <Icon name="money" :size="13" />
            {{ ticket.total_cost }}
          </span>
        </p>
      </section>

      <section v-if="canManage && isOpen" class="card-pad space-y-4">
        <div class="flex items-center gap-2">
          <Icon name="tool" :size="18" class="text-neutral-400" />
          <p class="section-title">What do you want to do?</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button v-if="ticket.status === 'open'" class="btn-secondary" :disabled="acting" @click="startFixing">
            <Icon name="wrench" :size="18" />
            Start fixing
          </button>
          <button v-if="!ticket.takes_out_of_service" class="btn-danger" :disabled="acting" @click="takeOutOfService">
            <Icon name="alert" :size="18" />
            Take out of service
          </button>
        </div>

        <div class="border-t border-line pt-4 space-y-4">
          <p class="section-title flex items-center gap-2">
            <Icon name="check-circle" :size="18" class="text-brand-700" />
            The tool is fixed
          </p>

          <div>
            <label class="label">What did you do?</label>
            <textarea v-model="resolveForm.resolution_notes" rows="3" class="textarea" placeholder="Example: put in a new pull cord" />
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Short code</label>
              <input v-model="resolveForm.resolution_code" type="text" class="input" placeholder="Example: REPAIRED" />
            </div>
            <div>
              <label class="label">What did it cost?</label>
              <input v-model.number="resolveForm.total_cost" type="number" min="0" step="0.01" class="input" placeholder="0.00" />
            </div>
          </div>

          <label class="flex items-start gap-2.5 rounded-xl bg-neutral-50 p-3 text-sm text-neutral-700">
            <input v-model="resolveForm.restore_to_service" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
            <span>
              This tool can be used again
              <span class="block text-xs muted">People will be able to borrow it.</span>
            </span>
          </label>

          <p v-if="error" class="flex items-center gap-2 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ error }}
          </p>

          <button class="btn-primary" :disabled="resolving" @click="resolve">
            <Icon :name="resolving ? 'refresh' : 'check'" :size="18" />
            {{ resolving ? 'Saving…' : 'Mark fixed' }}
          </button>
        </div>
      </section>
    </template>

    <EmptyState v-else icon="alert" title="Report not found" hint="It may have been removed.">
      <RouterLink to="/tickets" class="btn-secondary btn-sm">
        <Icon name="arrow-left" :size="16" />
        Back to problems
      </RouterLink>
    </EmptyState>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';

const route = useRoute();
const auth = useAuthStore();
const toasts = useToastStore();

const TICKET_TYPES = {
  defect: { label: 'Not working', icon: 'x-circle' },
  damage: { label: 'Damaged', icon: 'hammer' },
  inspection: { label: 'Found in a check', icon: 'clipboard' },
  other: { label: 'Something else', icon: 'help' },
};

/** Work orders start as "draft", which reads oddly next to a repair job. */
const WORK_ORDER_LABELS = {
  draft: 'Not started',
  in_progress: 'Being fixed',
  completed: 'Done',
  cancelled: 'Cancelled',
};

const ticket = ref(null);
const loading = ref(true);
const resolving = ref(false);
const acting = ref(false);
const error = ref('');
const photos = ref([]);
const photoFile = ref(null);
const uploadingPhoto = ref(false);

const canManage = computed(() => auth.can('manage_tickets'));
const isOpen = computed(() => ticket.value && !['resolved', 'closed'].includes(ticket.value.status));
const typeMeta = computed(() => TICKET_TYPES[ticket.value?.ticket_type] || TICKET_TYPES.other);

const resolveForm = reactive({ resolution_code: '', total_cost: null, resolution_notes: '', restore_to_service: true });

function workOrderLabel(status) {
  return WORK_ORDER_LABELS[status] || null;
}

function formatDateTime(value) {
  return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function onPhotoChange(e) {
  photoFile.value = e.target.files?.[0] || null;
}

async function uploadPhoto() {
  if (!photoFile.value || !ticket.value) return;
  uploadingPhoto.value = true;
  try {
    const formData = new FormData();
    formData.append('photo', photoFile.value);
    formData.append('collection', 'damage');
    const { data } = await api.post(`/tickets/${ticket.value.id}/photos`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    photos.value = [...photos.value, { id: data.data.id, url: data.url, original_name: data.data.original_name }];
    photoFile.value = null;
    toasts.success('Photo added.');
  } catch {
    toasts.error('Could not upload that photo.');
  } finally {
    uploadingPhoto.value = false;
  }
}

async function load() {
  loading.value = true;
  try {
    const { data } = await api.get(`/tickets/${route.params.id}`);
    ticket.value = data.data;
    photos.value = data.photos || [];
  } catch {
    ticket.value = null;
  } finally {
    loading.value = false;
  }
}

/**
 * Update/resolve responses only reload some relations, so merge instead of
 * replacing — otherwise the reporter and the logged work vanish from the page.
 */
function merge(fresh) {
  ticket.value = { ...ticket.value, ...fresh };
}

async function resolve() {
  resolving.value = true;
  error.value = '';
  try {
    const { data } = await api.post(`/tickets/${ticket.value.id}/resolve`, resolveForm);
    merge(data.data);
    toasts.success('Marked as fixed.');
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    resolving.value = false;
  }
}

async function act(payload, successMessage) {
  acting.value = true;
  error.value = '';
  try {
    const { data } = await api.put(`/tickets/${ticket.value.id}`, payload);
    merge(data.data);
    toasts.success(successMessage);
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save this. Please try again.';
  } finally {
    acting.value = false;
  }
}

const startFixing = () => act({ status: 'in_progress' }, 'Marked as being fixed.');

const takeOutOfService = () => act({ takes_out_of_service: true }, 'Marked as not safe to use.');

onMounted(load);
</script>
