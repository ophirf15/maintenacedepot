<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-0 sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    :aria-label="item?.label || 'Unit details'"
    @click.self="emit('close')"
  >
    <div class="card flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl sm:rounded-2xl">
      <header class="flex items-start gap-3 border-b border-line p-4">
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold text-content">{{ item?.label || item?.asset_tag || 'Unit' }}</p>
          <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs muted">
            <span class="font-mono font-semibold text-content">#{{ item?.numeric_id }}</span>
            <span v-if="item?.asset_tag">· {{ item.asset_tag }}</span>
            <span v-if="item?.depot?.name">· {{ item.depot.name }}</span>
          </p>
        </div>
        <StatusBadge
          v-if="item?.status"
          :status="item.status?.slug"
          :label="item.status?.name"
          :color="item.status?.color"
        />
        <button type="button" class="btn-ghost btn-sm shrink-0" aria-label="Close" @click="emit('close')">
          <Icon name="x" :size="18" />
        </button>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <!-- Photo carousel -->
        <div class="relative bg-neutral-100">
          <div
            ref="track"
            class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth"
            @scroll.passive="onScroll"
          >
            <div
              v-for="(photo, index) in photos"
              :key="photo.id || index"
              class="flex h-56 w-full shrink-0 snap-center items-center justify-center sm:h-64"
            >
              <img
                v-if="photo.url"
                :src="photo.url"
                :alt="photo.label || `Photo ${index + 1}`"
                class="h-full w-full object-contain"
              />
              <div v-else class="flex flex-col items-center gap-2 text-content-muted">
                <Icon :name="fallbackIcon" :size="48" />
                <span class="text-xs">No photo yet</span>
              </div>
            </div>
          </div>

          <template v-if="photos.length > 1">
            <button
              type="button"
              class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow"
              aria-label="Previous photo"
              @click="go(-1)"
            >
              <Icon name="chevron-left" :size="18" />
            </button>
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 shadow"
              aria-label="Next photo"
              @click="go(1)"
            >
              <Icon name="chevron-right" :size="18" />
            </button>
            <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
              <span
                v-for="(_, i) in photos"
                :key="i"
                class="h-1.5 w-1.5 rounded-full"
                :class="i === activePhoto ? 'bg-brand-solid' : 'bg-surface-raised/70'"
              />
            </div>
          </template>
        </div>

        <div class="space-y-4 p-4">
          <div v-if="detailLoading" class="space-y-2">
            <div class="skeleton h-8" />
            <div class="skeleton h-8" />
          </div>

          <template v-else>
            <div>
              <p class="label mb-2">Specs</p>
              <div v-if="specs.length" class="flex flex-wrap gap-2">
                <span
                  v-for="spec in specs"
                  :key="spec.key"
                  class="inline-flex items-center rounded-xl border border-line bg-surface px-3 py-1.5 text-sm font-medium text-content"
                >
                  <span class="muted mr-1.5 text-xs">{{ spec.label }}</span>
                  {{ spec.display }}
                </span>
              </div>
              <p v-else class="text-sm muted">No specs recorded for this unit yet.</p>
            </div>

            <p v-if="item?.description" class="text-sm text-content-muted">{{ item.description }}</p>
            <p v-if="item?.condition" class="text-sm capitalize muted">Condition: {{ item.condition }}</p>
          </template>
        </div>
      </div>

      <footer class="flex flex-wrap gap-2 border-t border-line p-4">
        <button
          v-if="canBorrow"
          type="button"
          class="btn-primary flex-1"
          @click="emit('add', item)"
        >
          <Icon name="plus" :size="17" />
          Add this unit to tool bag
        </button>
        <button type="button" class="btn-secondary" @click="emit('close')">Close</button>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import api from '../api';
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
  itemId: { type: [Number, String], required: true },
  /** Seed from the list row so the sheet opens instantly. */
  preview: { type: Object, default: null },
  fallbackIcon: { type: String, default: 'package' },
});

const emit = defineEmits(['close', 'add']);

const detailLoading = ref(true);
const detail = ref(null);
const damagePhotos = ref([]);
const activePhoto = ref(0);
const track = ref(null);

const item = computed(() => detail.value || props.preview);

const specs = computed(() => item.value?.specs || []);

const canBorrow = computed(
  () => item.value?.is_loanable && item.value?.status?.availability_effect === 'available'
);

const photos = computed(() => {
  const list = [];
  if (item.value?.image_url) {
    list.push({ id: 'primary', url: item.value.image_url, label: 'Primary photo' });
  }
  for (const photo of damagePhotos.value) {
    list.push({ id: `d-${photo.id}`, url: photo.url, label: photo.original_name || 'Damage photo' });
  }
  if (!list.length) {
    list.push({ id: 'empty', url: null, label: 'No photo' });
  }
  return list;
});

function onScroll() {
  const el = track.value;
  if (!el || !el.clientWidth) return;
  activePhoto.value = Math.round(el.scrollLeft / el.clientWidth);
}

function go(delta) {
  const el = track.value;
  if (!el) return;
  const next = Math.min(Math.max(activePhoto.value + delta, 0), photos.value.length - 1);
  el.scrollTo({ left: next * el.clientWidth, behavior: 'smooth' });
  activePhoto.value = next;
}

async function load() {
  detailLoading.value = true;
  try {
    const { data } = await api.get(`/items/${props.itemId}`);
    detail.value = data.data;
    damagePhotos.value = data.damage_photos || [];
  } catch {
    // Keep the preview row if the detail call fails.
  } finally {
    detailLoading.value = false;
    await nextTick();
    activePhoto.value = 0;
  }
}

watch(() => props.itemId, load, { immediate: true });
</script>
