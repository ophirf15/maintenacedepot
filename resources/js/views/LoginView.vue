<template>
  <div class="min-h-screen bg-surface flex items-center justify-center p-4 relative">
    <button
      type="button"
      class="btn-ghost absolute top-3 right-3 h-10 w-10 px-0"
      :title="`Theme: ${theme.label}`"
      :aria-label="`Theme: ${theme.label}. Click to change.`"
      @click="theme.cycle()"
    >
      <Icon :name="theme.icon" :size="20" />
    </button>
    <div class="w-full max-w-md">
      <div class="mb-7 text-center">
        <div class="mx-auto flex justify-center">
          <BrandMark variant="horizontal" :alt="appName" class="h-14 max-w-[18rem]" />
        </div>
        <p class="mt-4 text-sm muted">Sign in to borrow and look after tools.</p>
      </div>

      <div class="card-pad space-y-5">
        <div class="flex gap-2">
          <button
            type="button"
            class="chip flex-1 justify-center"
            :class="mode === 'password' ? 'chip-active' : ''"
            @click="mode = 'password'"
          >
            <Icon name="key" :size="15" />
            Password
          </button>
          <button
            type="button"
            class="chip flex-1 justify-center"
            :class="mode === 'magic' ? 'chip-active' : ''"
            @click="mode = 'magic'"
          >
            <Icon name="mail" :size="15" />
            Email link
          </button>
        </div>

        <form v-if="mode === 'password'" class="space-y-4" @submit.prevent="onPasswordLogin">
          <div>
            <label class="label" for="login-email">Email</label>
            <input
              id="login-email"
              v-model="email"
              type="email"
              required
              autocomplete="username"
              class="input"
              placeholder="you@company.com"
            />
          </div>
          <div>
            <label class="label" for="login-password">Password</label>
            <input
              id="login-password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="input"
              placeholder="••••••••"
            />
          </div>
          <p v-if="error" class="flex items-center gap-1.5 text-sm text-danger-600">
            <Icon name="alert" :size="16" />
            {{ error }}
          </p>
          <button type="submit" :disabled="loading" class="btn-primary w-full">
            <Icon :name="loading ? 'refresh' : 'arrow-right'" :size="17" />
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <form v-else class="space-y-4" @submit.prevent="onMagicRequest">
          <div>
            <label class="label" for="magic-email">Email</label>
            <input
              id="magic-email"
              v-model="magicEmail"
              type="email"
              required
              autocomplete="username"
              class="input"
              placeholder="you@company.com"
            />
            <p class="mt-1.5 text-xs muted">We send you a link. Tap it and you are in — no password needed.</p>
          </div>

          <div
            v-if="debugLink"
            class="rounded-xl border border-info-600/25 bg-info-100 p-3 text-xs break-all text-neutral-800"
          >
            <p class="mb-1 flex items-center gap-1.5 font-semibold text-info-600">
              <Icon name="info" :size="14" />
              Test link (only shown in debug mode)
            </p>
            <RouterLink :to="debugLinkPath" class="underline">{{ debugLink }}</RouterLink>
          </div>

          <button type="submit" :disabled="loading" class="btn-primary w-full">
            <Icon :name="loading ? 'refresh' : 'mail'" :size="17" />
            {{ loading ? 'Sending…' : 'Send me a link' }}
          </button>
        </form>

        <div v-if="auth.config?.saml?.enabled" class="border-t border-line pt-5">
          <a :href="auth.config.saml.sso_url" class="btn-secondary w-full">
            <Icon name="shield" :size="17" />
            Use my company account
          </a>
        </div>

        <div class="flex items-start gap-2 rounded-xl bg-neutral-50 p-3 text-xs muted">
          <Icon name="info" :size="14" class="mt-0.5" />
          <span>
            <span class="font-semibold text-neutral-700">Demo login:</span>
            admin@depotborrow.test / password
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { useThemeStore } from '../stores/theme';
import BrandMark from '../components/BrandMark.vue';
import Icon from '../components/Icon.vue';

const auth = useAuthStore();
const toasts = useToastStore();
const theme = useThemeStore();
const route = useRoute();
const router = useRouter();

const mode = ref('password');
const email = ref('');
const password = ref('');
const magicEmail = ref('');
const debugLink = ref('');
const error = ref('');
const loading = ref(false);

const appName = computed(() => auth.config?.branding?.app_name || 'Maintenance Depot');

const debugLinkPath = computed(() => {
  if (!debugLink.value) return '/login/magic';
  try {
    const url = new URL(debugLink.value);

    return `/login/magic${url.search}`;
  } catch {
    return '/login/magic';
  }
});

async function onPasswordLogin() {
  error.value = '';
  loading.value = true;
  try {
    await auth.login(email.value, password.value);
    router.push(route.query.redirect?.toString() || '/');
  } catch (e) {
    error.value = e.response?.data?.message || 'That email or password is not right.';
  } finally {
    loading.value = false;
  }
}

async function onMagicRequest() {
  debugLink.value = '';
  loading.value = true;
  try {
    const { data } = await auth.requestMagic(magicEmail.value);
    toasts.success(data.message || 'Check your email for the link.');
    if (data.debug_link) {
      debugLink.value = data.debug_link;
    }
  } catch (e) {
    toasts.error(e.response?.data?.message || 'Could not send the link. Try again.');
  } finally {
    loading.value = false;
  }
}
</script>
