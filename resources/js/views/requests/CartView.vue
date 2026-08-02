<template>
  <div class="space-y-5 max-w-3xl">
    <PageHeader
      title="Your cart"
      subtitle="Review the tools, choose pick-up and return dates, then submit the request."
      icon="cart"
      back-to="/catalog"
      back-label="Back to catalog"
    />

    <EmptyState
      v-if="!cart.lines.length"
      icon="cart"
      title="Your cart is empty"
      hint="Add tools from the catalog first."
    >
      <RouterLink to="/catalog" class="btn-primary btn-sm">
        <Icon name="grid" :size="16" />
        Browse tools
      </RouterLink>
    </EmptyState>

    <template v-else>
      <!-- Step 1 -->
      <section class="card">
        <header class="flex items-center gap-2.5 p-4 pb-3">
          <span class="step-badge">1</span>
          <p class="section-title">Tools you picked</p>
        </header>
        <ul class="divide-rows border-t border-line">
          <li
            v-for="(line, idx) in cart.lines"
            :key="line._key || idx"
            class="flex items-center gap-3 px-4 py-3"
          >
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 shrink-0">
              <Icon :name="line.icon || 'package'" :size="20" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-neutral-900 truncate">{{ lineLabel(line) }}</p>
              <p class="text-xs muted flex items-center gap-1">
                <Icon :name="line.request_mode === 'specific_item' ? 'pin' : 'sparkles'" :size="12" />
                {{ line.request_mode === 'specific_item' ? 'This exact unit' : 'Any free unit' }}
              </p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button class="qty-btn" aria-label="Less" @click="cart.setQuantity(idx, (line.quantity || 1) - 1)">
                <Icon name="minus" :size="16" />
              </button>
              <span class="w-8 text-center text-sm font-semibold">{{ line.quantity || 1 }}</span>
              <button class="qty-btn" aria-label="More" @click="cart.setQuantity(idx, (line.quantity || 1) + 1)">
                <Icon name="plus" :size="16" />
              </button>
            </div>
            <button class="btn-ghost btn-sm text-danger-600 px-2" aria-label="Remove" @click="cart.removeLine(idx)">
              <Icon name="trash" :size="17" />
            </button>
          </li>
        </ul>
      </section>

      <form class="space-y-5" @submit.prevent="submit">
        <!-- Step 2 -->
        <section class="card">
          <header class="flex items-center gap-2.5 p-4 pb-3">
            <span class="step-badge">2</span>
            <p class="section-title">When and where</p>
          </header>
          <div class="grid sm:grid-cols-2 gap-4 border-t border-line p-4">
            <div>
              <label class="label">
                <Icon name="building" :size="13" class="inline -mt-0.5 mr-1" />
                Which property
              </label>
              <select v-model.number="cart.property_id" required class="select">
                <option disabled :value="null">Choose a property</option>
                <option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">
                <Icon name="depot" :size="13" class="inline -mt-0.5 mr-1" />
                Pick up from
              </label>
              <select v-model.number="cart.pickup_depot_id" required class="select" :disabled="depotsLoading">
                <option disabled :value="null">{{ depotsLoading ? 'Loading…' : 'Choose a depot' }}</option>
                <option v-for="d in depots" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
              <p v-if="depotsError" class="text-xs text-warn-600 mt-1.5">{{ depotsError }}</p>
            </div>
            <div>
              <label class="label">
                <Icon name="calendar" :size="13" class="inline -mt-0.5 mr-1" />
                I need it from
              </label>
              <input v-model="cart.needed_from" type="datetime-local" required class="input" />
            </div>
            <div>
              <label class="label">
                <Icon name="calendar" :size="13" class="inline -mt-0.5 mr-1" />
                I will return it by
              </label>
              <input v-model="cart.needed_until" type="datetime-local" required class="input" />
            </div>
          </div>
          <div class="flex flex-wrap gap-2 border-t border-line px-4 py-3">
            <button v-for="preset in presets" :key="preset.label" type="button" class="chip" @click="applyPreset(preset)">
              <Icon name="clock" :size="15" />
              {{ preset.label }}
            </button>
          </div>
        </section>

        <!-- Step 3 -->
        <section class="card">
          <header class="flex items-center gap-2.5 p-4 pb-3">
            <span class="step-badge">3</span>
            <p class="section-title">Anything else? (optional)</p>
          </header>
          <div class="space-y-4 border-t border-line p-4">
            <div>
              <label class="label">How urgent is it</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="option in priorities"
                  :key="option.value"
                  type="button"
                  class="chip"
                  :class="{ 'chip-active': cart.priority === option.value }"
                  @click="cart.priority = option.value"
                >
                  <Icon :name="option.icon" :size="15" />
                  {{ option.label }}
                </button>
              </div>
            </div>
            <div>
              <label class="label">What is the job</label>
              <textarea v-model="cart.purpose" rows="2" class="textarea" placeholder="Example: cleaning the parking garage" />
            </div>
          </div>
        </section>

        <p v-if="error" class="flex items-start gap-2 rounded-xl border border-danger-600/20 bg-danger-100 px-4 py-3 text-sm text-danger-600">
          <Icon name="alert" :size="17" class="mt-0.5" />
          {{ error }}
        </p>

        <div class="flex items-center justify-between gap-3">
          <button type="button" class="btn-ghost btn-sm" @click="cart.clear()">
            <Icon name="trash" :size="16" />
            Empty cart
          </button>
          <button type="submit" :disabled="submitting" class="btn-primary">
            <Icon :name="submitting ? 'refresh' : 'arrow-right'" :size="18" />
            {{ submitting ? 'Submitting…' : 'Submit borrow request' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import api from '../../api';
import { useAuthStore, useCartStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { fromLocalInput, toLocalInput } from '../../datetime';
import Icon from '../../components/Icon.vue';
import PageHeader from '../../components/PageHeader.vue';
import EmptyState from '../../components/EmptyState.vue';

const auth = useAuthStore();
const cart = useCartStore();
const toasts = useToastStore();
const router = useRouter();

const depots = ref([]);
const depotsLoading = ref(true);
const depotsError = ref('');
const error = ref('');
const submitting = ref(false);

const properties = computed(() => auth.user?.properties || []);

const priorities = [
  { value: 'low', label: 'Not urgent', icon: 'info' },
  { value: 'normal', label: 'Normal', icon: 'clock' },
  { value: 'high', label: 'Soon', icon: 'alert' },
  { value: 'urgent', label: 'Today', icon: 'alert' },
];

const presets = [
  { label: 'Today only', fromHours: 1, days: 0 },
  { label: 'Tomorrow, 1 day', fromHours: 24, days: 1 },
  { label: 'Next week, 3 days', fromHours: 168, days: 3 },
];

function applyPreset(preset) {
  const from = new Date(Date.now() + preset.fromHours * 3600 * 1000);
  const until = new Date(from.getTime() + Math.max(preset.days, 1) * 24 * 3600 * 1000);

  cart.needed_from = toLocalInput(from);
  cart.needed_until = toLocalInput(until);
}

function lineLabel(line) {
  if (line.label) return line.label;

  return line.request_mode === 'specific_item' ? 'Selected unit' : 'Selected tool';
}

async function submit() {
  error.value = '';

  if (!cart.property_id || !cart.pickup_depot_id) {
    error.value = 'Please choose a property and a depot.';
    return;
  }
  if (!cart.needed_from || !cart.needed_until) {
    error.value = 'Please say when you need the tools and when you will return them.';
    return;
  }
  if (new Date(cart.needed_until) <= new Date(cart.needed_from)) {
    error.value = 'The return date must be after the pick-up date.';
    return;
  }

  submitting.value = true;
  try {
    const { data } = await api.post('/borrow-requests', {
      property_id: cart.property_id,
      pickup_depot_id: cart.pickup_depot_id,
      priority: cart.priority,
      purpose: cart.purpose,
      needed_from: fromLocalInput(cart.needed_from),
      needed_until: fromLocalInput(cart.needed_until),
      submit: true,
      lines: cart.lines.map((l) => ({
        request_mode: l.request_mode,
        item_id: l.item_id || undefined,
        tool_type_id: l.tool_type_id || undefined,
        quantity: l.quantity || 1,
        notes: l.notes || undefined,
      })),
    });

    cart.clear();
    toasts.success('Borrow request submitted');
    router.push(`/requests/${data.data.id}`);
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not send the request. Please check the fields and try again.';
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  if (!cart.property_id) {
    cart.property_id = auth.user?.default_property_id || properties.value[0]?.id || null;
  }
  if (!cart.needed_from || !cart.needed_until) {
    applyPreset(presets[1]);
  }

  try {
    const { data } = await api.get('/depots', { params: { active_only: 1 } });
    depots.value = data.data;
    if (!cart.pickup_depot_id && depots.value.length === 1) {
      cart.pickup_depot_id = depots.value[0].id;
    }
  } catch (e) {
    depotsError.value =
      e.response?.status === 403
        ? 'Ask an admin which depot to pick up from.'
        : 'Could not load the depot list right now.';
  } finally {
    depotsLoading.value = false;
  }
});
</script>

<style scoped>
.step-badge {
  display: inline-flex;
  height: 1.5rem;
  width: 1.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: var(--color-ink-900);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
}
.qty-btn {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.6rem;
  border: 1px solid var(--color-line);
  color: #3f3f46;
}
.qty-btn:hover {
  background: #fafafa;
}
</style>
