<template>
  <div class="min-h-screen bg-surface flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
      <div class="mb-7 text-center">
        <div class="mx-auto flex justify-center">
          <BrandMark variant="horizontal" alt="Maintenance Depot" class="h-14 max-w-[18rem]" />
        </div>
        <p class="mt-4 text-sm muted">Let's get the app ready. It takes a couple of minutes.</p>
      </div>

      <div class="card-pad space-y-6">
        <ol class="flex items-center gap-1">
          <li
            v-for="(s, i) in steps"
            :key="s.label"
            class="flex flex-1 items-center gap-2 text-xs font-medium"
            :class="i <= step ? 'text-content' : 'muted'"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
              :class="i <= step ? 'bg-brand-solid text-white' : 'bg-neutral-100 text-content-muted'"
            >
              <Icon :name="i < step ? 'check' : s.icon" :size="15" :stroke-width="2" />
            </span>
            <span class="hidden sm:inline">{{ s.label }}</span>
            <span v-if="i < steps.length - 1" class="h-px flex-1 bg-line" />
          </li>
        </ol>

        <div v-if="step === 0" class="space-y-4">
          <div>
            <p class="section-title">Welcome</p>
            <p class="mt-1 text-sm muted">
              We will set up the database, make your first admin account, and — if you like — add some example tools so
              you can have a look around.
            </p>
          </div>
          <div>
            <label class="label" for="install-app-name">What should the app be called?</label>
            <input id="install-app-name" v-model="form.app_name" type="text" class="input" placeholder="Maintenance Depot" />
          </div>
          <button type="button" class="btn-primary w-full" @click="step = 1">
            Next
            <Icon name="arrow-right" :size="17" />
          </button>
        </div>

        <form v-else-if="step === 1" class="space-y-4" @submit.prevent="step = 2">
          <div>
            <p class="section-title">Your admin account</p>
            <p class="mt-1 text-sm muted">This is the account you will use to set everything else up.</p>
          </div>
          <div>
            <label class="label" for="install-name">Full name</label>
            <input id="install-name" v-model="form.admin_name" type="text" required class="input" placeholder="Jordan Smith" />
          </div>
          <div>
            <label class="label" for="install-email">Email</label>
            <input
              id="install-email"
              v-model="form.admin_email"
              type="email"
              required
              class="input"
              placeholder="admin@depotborrow.test"
            />
          </div>
          <div>
            <label class="label" for="install-password">Password</label>
            <input
              id="install-password"
              v-model="form.admin_password"
              type="password"
              required
              minlength="8"
              class="input"
              placeholder="At least 8 characters"
            />
          </div>
          <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3 text-sm text-content-muted">
            <input v-model="form.seed_demo_data" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-neutral-300" />
            <span>
              Add example data
              <span class="block text-xs muted">Sample sites, tool groups and tools you can delete later.</span>
            </span>
          </label>
          <div class="flex gap-3">
            <button type="button" class="btn-secondary flex-1" @click="step = 0">
              <Icon name="arrow-left" :size="17" />
              Back
            </button>
            <button type="submit" class="btn-primary flex-1">
              Next
              <Icon name="arrow-right" :size="17" />
            </button>
          </div>
        </form>

        <div v-else-if="step === 2" class="space-y-4">
          <div>
            <p class="section-title">Check it over</p>
            <p class="mt-1 text-sm muted">Have a quick look, then start the set-up.</p>
          </div>

          <dl class="card divide-rows overflow-hidden text-sm">
            <div v-for="row in reviewRows" :key="row.label" class="flex items-center justify-between gap-3 px-3.5 py-2.5">
              <dt class="flex items-center gap-2 muted">
                <Icon :name="row.icon" :size="15" />
                {{ row.label }}
              </dt>
              <dd class="truncate font-medium text-content">{{ row.value }}</dd>
            </div>
          </dl>

          <p v-if="error" class="flex items-start gap-1.5 text-sm text-danger-600">
            <Icon name="alert" :size="16" class="mt-0.5" />
            {{ error }}
          </p>

          <div class="flex gap-3">
            <button type="button" class="btn-secondary flex-1" :disabled="loading" @click="step = 1">
              <Icon name="arrow-left" :size="17" />
              Back
            </button>
            <button type="button" class="btn-primary flex-1" :disabled="loading" @click="runInstall">
              <Icon :name="loading ? 'refresh' : 'check'" :size="17" />
              {{ loading ? 'Setting up…' : 'Start set-up' }}
            </button>
          </div>
        </div>

        <div v-else class="space-y-4 py-4 text-center">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
            <Icon name="check-circle" :size="30" />
          </span>
          <div>
            <p class="font-semibold text-content">All done</p>
            <p class="mt-1 text-sm muted">You can now sign in as {{ result?.admin_email }}.</p>
          </div>
          <RouterLink to="/login" class="btn-primary">
            Go to sign in
            <Icon name="arrow-right" :size="17" />
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../api';
import BrandMark from '../components/BrandMark.vue';
import Icon from '../components/Icon.vue';

const steps = [
  { label: 'Welcome', icon: 'home' },
  { label: 'Admin', icon: 'user' },
  { label: 'Check', icon: 'clipboard' },
  { label: 'Done', icon: 'check' },
];

const step = ref(0);
const loading = ref(false);
const error = ref('');
const result = ref(null);

const form = reactive({
  app_name: 'Maintenance Depot',
  admin_name: '',
  admin_email: '',
  admin_password: '',
  seed_demo_data: true,
});

const reviewRows = computed(() => [
  { label: 'App name', icon: 'sparkles', value: form.app_name || 'Maintenance Depot' },
  { label: 'Admin', icon: 'user', value: form.admin_name },
  { label: 'Email', icon: 'mail', value: form.admin_email },
  { label: 'Example data', icon: 'boxes', value: form.seed_demo_data ? 'Yes' : 'No' },
]);

async function runInstall() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.post('/install/run', form);
    result.value = data.data;
    step.value = 3;
  } catch (e) {
    error.value = e.response?.data?.message || 'Set-up did not finish. Check the server logs and try again.';
  } finally {
    loading.value = false;
  }
}
</script>
