<template>
  <div class="space-y-5">
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-16" />
      <div v-for="i in 4" :key="i" class="skeleton h-24" />
    </div>

    <template v-else-if="category">
      <PageHeader
        :title="category.name"
        :subtitle="`${category.available_count} of ${category.total_count} ready to borrow`"
        back-to="/catalog"
        back-label="All tool groups"
      >
        <template #actions>
          <RouterLink to="/cart" class="btn-primary btn-sm">
            <Icon name="toolbag" :size="17" />
            Tool bag ({{ cart.lines.length }})
          </RouterLink>
        </template>
      </PageHeader>

      <div class="space-y-3">
        <div v-for="tt in category.tool_types" :key="tt.id" class="card overflow-hidden">
          <div class="flex flex-wrap items-center gap-3 p-4">
            <span
              class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl shrink-0"
              :style="{ background: `${badgeColor(category)}14`, color: badgeColor(category) }"
            >
              <img v-if="tt.image_path" :src="tt.image_path" class="h-full w-full object-cover" alt="" />
              <Icon v-else :name="toolTypeIcon(tt)" :size="24" />
            </span>

            <div class="min-w-0 flex-1">
              <p class="font-semibold text-content truncate">{{ tt.name }}</p>
              <p class="text-xs font-medium mt-0.5" :class="tt.available_count ? 'text-brand-700' : 'text-warn-600'">
                <span v-if="tt.available_count">{{ tt.available_count }} of {{ tt.total_count }} free now</span>
                <span v-else-if="tt.allow_waitlist">None free — join the waitlist</span>
                <span v-else>None free right now</span>
              </p>
            </div>

            <button
              v-if="tt.available_count"
              type="button"
              class="btn-primary btn-sm"
              @click="borrowAnyAvailable(tt)"
            >
              <Icon name="plus" :size="16" />
              Add any available unit
            </button>
            <button
              v-else-if="tt.allow_waitlist"
              type="button"
              class="btn-primary btn-sm"
              @click="openWaitlist(tt)"
            >
              <Icon name="clock" :size="16" />
              Join waitlist
            </button>

            <button type="button" class="btn-secondary btn-sm" @click="toggleToolType(tt)">
              <Icon :name="expanded === tt.id ? 'chevron-up' : 'chevron-down'" :size="16" />
              {{ expanded === tt.id ? 'Hide units' : 'Choose a specific unit' }}
            </button>
          </div>

          <div v-if="expanded === tt.id" class="border-t border-line bg-surface/70 p-4">
            <p v-if="itemsLoading" class="text-sm muted py-2">Loading units…</p>
            <p v-else-if="!items.length" class="text-sm muted py-2">No units recorded for this tool.</p>
            <ul v-else class="space-y-2">
              <li
                v-for="item in items"
                :key="item.id"
                class="card flex flex-wrap items-center gap-3 px-3 py-2.5"
              >
                <button
                  type="button"
                  class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-neutral-100 text-content-muted"
                  :aria-label="`Details for ${item.label || item.asset_tag}`"
                  @click="openPeek(item, tt)"
                >
                  <img
                    v-if="item.image_url"
                    :src="item.image_url"
                    :alt="item.label"
                    class="h-full w-full object-cover"
                  />
                  <Icon v-else :name="toolTypeIcon(tt)" :size="22" />
                </button>

                <button type="button" class="min-w-0 flex-1 text-left" @click="openPeek(item, tt)">
                  <p class="truncate text-sm font-medium text-content">
                    {{ item.label || item.asset_tag }}
                  </p>
                  <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs muted">
                    <span class="font-mono font-semibold text-content-muted">#{{ item.numeric_id }}</span>
                    <span aria-hidden="true">·</span>
                    <Icon name="qr" :size="13" />
                    <span class="font-mono">{{ item.asset_tag }}</span>
                    <span aria-hidden="true">·</span>
                    <Icon name="pin" :size="13" />
                    {{ item.depot?.name || 'No depot' }}
                  </p>
                  <div v-if="item.specs?.length" class="mt-1.5 flex flex-wrap gap-1.5">
                    <span
                      v-for="spec in item.specs.slice(0, 3)"
                      :key="spec.key"
                      class="inline-flex items-center rounded-lg border border-line bg-surface-raised px-2 py-0.5 text-[0.7rem] font-medium text-content-muted"
                    >
                      {{ spec.label }}: {{ spec.display }}
                    </span>
                  </div>
                  <p v-else class="mt-1 text-[0.7rem] text-brand-700">Tap for specs &amp; photos</p>
                </button>

                <StatusBadge
                  :status="item.status?.slug"
                  :label="item.status?.name"
                  :color="item.status?.color"
                />
                <button
                  type="button"
                  class="btn-secondary btn-sm"
                  :disabled="!isBorrowable(item)"
                  @click="borrowSpecific(tt, item)"
                >
                  <Icon name="plus" :size="16" />
                  Add this unit to tool bag
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>

    <EmptyState v-else icon="alert" title="Tool group not found" hint="It may have been removed." />

    <Teleport to="body">
      <UnitPeekSheet
        v-if="peekItem"
        :item-id="peekItem.id"
        :preview="peekItem"
        :fallback-icon="peekIcon"
        @close="peekItem = null"
        @add="onPeekAdd"
      />

      <div
        v-if="waitlistFor"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/45 p-4 sm:items-center"
        @click.self="waitlistFor = null"
      >
        <form class="card w-full max-w-md space-y-4 p-5" @submit.prevent="joinWaitlist">
          <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warn-100 text-warn-600">
              <Icon name="clock" :size="20" />
            </span>
            <div>
              <h2 class="font-semibold text-content">Join the waitlist</h2>
              <p class="text-sm muted">We'll note you want {{ waitlistFor.name }} when a unit is free.</p>
            </div>
          </div>

          <div>
            <label class="label" for="wl-property">Borrowing for</label>
            <select id="wl-property" v-model.number="waitlistForm.property_id" class="select" required>
              <option disabled :value="null">Choose a property…</option>
              <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="!properties.length" class="mt-1 text-xs text-danger-600">
              No properties on your account. Ask an admin to assign you to a property.
            </p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label" for="wl-from">Needed from</label>
              <input id="wl-from" v-model="waitlistForm.desired_from" type="datetime-local" class="input" required />
            </div>
            <div>
              <label class="label" for="wl-until">Needed until</label>
              <input id="wl-until" v-model="waitlistForm.desired_until" type="datetime-local" class="input" required />
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn-primary" :disabled="waitlistSaving || !properties.length">
              <Icon name="check" :size="17" />
              {{ waitlistSaving ? 'Joining…' : 'Join waitlist' }}
            </button>
            <button type="button" class="btn-ghost" @click="waitlistFor = null">Cancel</button>
          </div>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import api from '../../api';
import { useAuthStore, useCartStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import UnitPeekSheet from '../../components/UnitPeekSheet.vue';
import { iconFor } from '../../icons';
import { toLocalInput, fromLocalInput } from '../../datetime';

const route = useRoute();
const auth = useAuthStore();
const cart = useCartStore();
const toasts = useToastStore();

const categories = ref([]);
const loading = ref(true);
const expanded = ref(null);
const items = ref([]);
const itemsLoading = ref(false);
const waitlistFor = ref(null);
const waitlistSaving = ref(false);
const waitlistForm = reactive({
  property_id: null,
  desired_from: '',
  desired_until: '',
});
const peekItem = ref(null);
const peekToolType = ref(null);

const FALLBACK_COLORS = ['#15694f', '#b45309', '#1570cd', '#7c3aed', '#0f766e', '#be123c'];

const category = computed(() =>
  categories.value.find((c) => String(c.id) === String(route.params.categoryId)),
);

const properties = computed(() => auth.user?.properties || []);

const peekIcon = computed(() =>
  peekToolType.value ? toolTypeIcon(peekToolType.value) : 'package'
);

function badgeColor(cat) {
  if (cat?.color) return cat.color;

  return FALLBACK_COLORS[(cat?.id || 0) % FALLBACK_COLORS.length];
}

function isBorrowable(item) {
  return item.is_loanable && item.status?.availability_effect === 'available';
}

function toolTypeIcon(tt) {
  return iconFor(tt, iconFor(category.value));
}

function openPeek(item, tt) {
  peekItem.value = item;
  peekToolType.value = tt;
}

function onPeekAdd(item) {
  if (!peekToolType.value || !item) return;
  borrowSpecific(peekToolType.value, item);
  peekItem.value = null;
}

function openWaitlist(tt) {
  const from = new Date();
  from.setHours(from.getHours() + 1, 0, 0, 0);
  const until = new Date(from);
  until.setDate(until.getDate() + (tt.default_loan_days || 3));

  waitlistForm.property_id =
    auth.user?.default_property_id || properties.value[0]?.id || null;
  waitlistForm.desired_from = toLocalInput(from);
  waitlistForm.desired_until = toLocalInput(until);
  waitlistFor.value = tt;
}

async function joinWaitlist() {
  if (!waitlistForm.property_id) {
    toasts.error('Choose which property you are borrowing for.');
    return;
  }

  waitlistSaving.value = true;
  try {
    await api.post('/waitlist', {
      tool_type_id: waitlistFor.value.id,
      property_id: waitlistForm.property_id,
      desired_from: fromLocalInput(waitlistForm.desired_from),
      desired_until: fromLocalInput(waitlistForm.desired_until),
    });
    toasts.success(`You're on the waitlist for ${waitlistFor.value.name}`);
    waitlistFor.value = null;
  } catch (error) {
    toasts.error(error.response?.data?.message || 'Could not join the waitlist.');
  } finally {
    waitlistSaving.value = false;
  }
}

async function toggleToolType(tt) {
  if (expanded.value === tt.id) {
    expanded.value = null;
    return;
  }

  expanded.value = tt.id;
  itemsLoading.value = true;
  items.value = [];
  try {
    const { data } = await api.get(`/catalog/tool-types/${tt.id}/items`);
    items.value = data.data;
  } catch {
    items.value = [];
    toasts.error('Could not load the units for this tool.');
  } finally {
    itemsLoading.value = false;
  }
}

function borrowAnyAvailable(tt) {
  cart.addLine({
    request_mode: 'tool_type',
    tool_type_id: tt.id,
    quantity: 1,
    notes: '',
    label: tt.name,
    icon: toolTypeIcon(tt),
  });
  toasts.success(`Added ${tt.name} (any free unit) to your tool bag`);
}

function borrowSpecific(tt, item) {
  const label = item.label || item.asset_tag;
  const specHint = (item.specs || []).slice(0, 2).map((s) => s.display).join(', ');

  cart.addLine({
    request_mode: 'specific_item',
    item_id: item.id,
    tool_type_id: tt.id,
    quantity: 1,
    notes: '',
    label: specHint ? `${label} (${specHint})` : label,
    icon: toolTypeIcon(tt),
    specs: item.specs || [],
    image_url: item.image_url || null,
  });
  toasts.success(`Added ${label} to your tool bag`);
}

onMounted(async () => {
  try {
    const { data } = await api.get('/catalog/categories');
    categories.value = data.data;
  } catch {
    toasts.error('Could not load the catalog.');
  } finally {
    loading.value = false;
  }
});
</script>
