<template>
  <div class="space-y-5 max-w-3xl">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div class="skeleton h-56" />
    </div>

    <template v-else-if="item">
      <PageHeader
        :title="item.label || item.asset_tag"
        :subtitle="item.tool_type?.name || 'Equipment unit'"
        icon="package"
        :back-to="backTo"
        back-label="Back"
      >
        <template #actions>
          <StatusBadge :status="item.status?.slug" :label="item.status?.name" :color="item.status?.color" />
        </template>
      </PageHeader>

      <div class="card overflow-hidden">
        <div class="aspect-[16/10] bg-neutral-100">
          <img
            v-if="item.image_url"
            :src="item.image_url"
            :alt="item.label"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full items-center justify-center text-content-muted">
            <Icon :name="iconFor(item.tool_type)" :size="48" />
          </div>
        </div>
        <div class="space-y-3 p-4">
          <p class="text-xs muted flex flex-wrap items-center gap-2">
            <span class="font-mono">{{ item.asset_tag }}</span>
            <span aria-hidden="true">·</span>
            <Icon name="pin" :size="13" />
            {{ item.depot?.name || 'No depot' }}
            <span v-if="item.condition" class="capitalize">· {{ item.condition }} condition</span>
          </p>

          <div v-if="item.specs?.length" class="flex flex-wrap gap-2">
            <span
              v-for="spec in item.specs"
              :key="spec.key"
              class="inline-flex items-center rounded-xl border border-line bg-surface px-3 py-1.5 text-sm font-medium text-content"
            >
              <span class="muted mr-1.5 text-xs">{{ spec.label }}</span>
              {{ spec.display }}
            </span>
          </div>

          <p v-if="item.description" class="text-sm text-content-muted">{{ item.description }}</p>
        </div>
      </div>

      <section v-if="damagePhotos.length" class="card-pad space-y-3">
        <div class="flex items-center gap-2">
          <Icon name="camera" :size="18" class="text-content-muted" />
          <p class="section-title">Damage photos</p>
        </div>
        <p class="text-sm muted">Pictures from defect reports on this unit.</p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <a
            v-for="photo in damagePhotos"
            :key="photo.id"
            :href="photo.url"
            target="_blank"
            class="aspect-square overflow-hidden rounded-xl border border-line bg-surface"
          >
            <img :src="photo.url" :alt="photo.original_name" class="h-full w-full object-cover" />
          </a>
        </div>
      </section>

      <div class="flex flex-wrap gap-2">
        <button
          v-if="canBorrow"
          type="button"
          class="btn-primary"
          @click="addToCart"
        >
          <Icon name="plus" :size="17" />
          Add this unit to tool bag
        </button>
        <RouterLink v-if="canManage" :to="`/inventory/items/${item.id}`" class="btn-secondary">
          <Icon name="edit" :size="17" />
          Edit in inventory
        </RouterLink>
      </div>
    </template>

    <EmptyState v-else icon="alert" title="Unit not found" hint="It may have been removed." />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import api from '../../api';
import { useAuthStore, useCartStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { iconFor } from '../../icons';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const cart = useCartStore();
const toasts = useToastStore();

const loading = ref(true);
const item = ref(null);
const damagePhotos = ref([]);

const canBorrow = computed(
  () => item.value?.is_loanable && item.value?.status?.availability_effect === 'available'
);
const canManage = computed(() => auth.can('manage_inventory'));
const backTo = computed(() => {
  const catId = item.value?.tool_type?.category_id;
  return catId ? `/catalog/${catId}` : '/catalog';
});

function addToCart() {
  const label = item.value.label || item.value.asset_tag;
  const specHint = (item.value.specs || []).slice(0, 2).map((s) => s.display).join(', ');

  cart.addLine({
    request_mode: 'specific_item',
    item_id: item.value.id,
    tool_type_id: item.value.tool_type_id,
    quantity: 1,
    notes: '',
    label: specHint ? `${label} (${specHint})` : label,
    icon: iconFor(item.value.tool_type),
    specs: item.value.specs || [],
    image_url: item.value.image_url || null,
    depot_id: item.value.depot_id || item.value.depot?.id || null,
    depot_name: item.value.depot?.name || null,
  });
  toasts.success(`Added ${label} to your tool bag`);
  router.push('/cart');
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/items/${route.params.id}`);
    item.value = data.data;
    damagePhotos.value = data.damage_photos || [];
  } catch {
    item.value = null;
  } finally {
    loading.value = false;
  }
});
</script>
