<template>
  <div class="space-y-5 max-w-3xl">
    <PageHeader title="Messages" subtitle="Updates about your requests and tools." icon="bell">
      <template #actions>
        <button v-if="unread" class="btn-secondary btn-sm" :disabled="working" @click="markAllRead">
          <Icon name="check" :size="16" />
          Mark all read
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="skeleton h-20" />
    </div>

    <EmptyState v-else-if="!notifications.length" icon="bell" title="No messages" hint="We will tell you here when something changes." />

    <ul v-else class="space-y-2.5">
      <li v-for="n in notifications" :key="n.id">
        <component
          :is="link(n) ? RouterLink : 'div'"
          :to="link(n)"
          class="card flex items-start gap-3 p-4"
          :class="{ 'card-hover': link(n), 'border-brand-700/25 bg-brand-100/30': !n.read_at }"
          @click="markRead(n)"
        >
          <span
            class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
            :class="n.read_at ? 'bg-neutral-100 text-neutral-400' : 'bg-brand-100 text-brand-700'"
          >
            <Icon :name="n.data?.icon || 'bell'" :size="19" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-neutral-900">{{ n.data?.title || 'Update' }}</p>
            <p class="text-sm text-neutral-700 mt-0.5">{{ n.data?.body }}</p>
            <p class="text-xs muted mt-1">{{ formatWhen(n.created_at) }}</p>
          </div>
          <span v-if="!n.read_at" class="mt-1.5 h-2 w-2 rounded-full bg-brand-600 shrink-0" />
        </component>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import Icon from '../components/Icon.vue';
import PageHeader from '../components/PageHeader.vue';
import EmptyState from '../components/EmptyState.vue';

const notifications = ref([]);
const loading = ref(true);
const working = ref(false);

const unread = computed(() => notifications.value.filter((n) => !n.read_at).length);

function link(notification) {
  return notification.data?.action_url || null;
}

function formatWhen(value) {
  if (!value) return '';

  return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

async function markRead(notification) {
  if (notification.read_at) return;

  notification.read_at = new Date().toISOString();
  try {
    await api.post(`/notifications/${notification.id}/read`);
  } catch {
    // Local state already reflects the click; a refresh will resync.
  }
}

async function markAllRead() {
  working.value = true;
  try {
    await api.post('/notifications/read-all');
    notifications.value = notifications.value.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }));
  } finally {
    working.value = false;
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/notifications');
    notifications.value = data.data?.data || data.data || [];
  } catch {
    notifications.value = [];
  } finally {
    loading.value = false;
  }
});
</script>
