<template>
  <div class="min-h-screen bg-surface flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="mb-7 text-center">
        <div class="mx-auto flex justify-center">
          <BrandMark variant="horizontal" :alt="appName" class="h-12 max-w-[16rem]" />
        </div>
      </div>

      <div class="card-pad space-y-4 py-8 text-center">
        <template v-if="status === 'loading'">
          <span class="mx-auto block h-10 w-10 animate-spin rounded-full border-4 border-brand-100 border-t-brand-600" />
          <p class="text-sm muted">Checking your link…</p>
        </template>

        <template v-else-if="status === 'error'">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-danger-100 text-danger-600">
            <Icon name="x-circle" :size="30" />
          </span>
          <div>
            <p class="font-semibold text-content">This link no longer works</p>
            <p class="mt-1 text-sm muted">{{ error }}</p>
          </div>
          <RouterLink to="/login" class="btn-primary">
            <Icon name="arrow-left" :size="17" />
            Back to sign in
          </RouterLink>
        </template>

        <template v-else>
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
            <Icon name="check-circle" :size="30" />
          </span>
          <p class="font-semibold text-content">You're in. Taking you to the app…</p>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import BrandMark from '../components/BrandMark.vue';
import Icon from '../components/Icon.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const status = ref('loading');
const error = ref('');

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

onMounted(async () => {
  const token = route.query.token?.toString();
  if (!token) {
    status.value = 'error';
    error.value = 'The link is missing its code. Ask for a new one.';

    return;
  }

  try {
    await auth.consumeMagic(token);
    status.value = 'success';
    setTimeout(() => router.push('/'), 700);
  } catch (e) {
    status.value = 'error';
    error.value = e.response?.data?.message || 'It may have been used already, or it has expired.';
  }
});
</script>
