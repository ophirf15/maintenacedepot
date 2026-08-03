import { defineStore } from 'pinia';
import api from '../api';
import { useCartStore } from './cart';

export { useCartStore } from './cart';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    config: null,
    loaded: false,
  }),
  getters: {
    isAuthenticated: (s) => !!s.user,
    roles: (s) => s.user?.roles || [],
    permissions: (s) => s.user?.permissions || [],
    can: (s) => (perm) => (s.user?.permissions || []).includes(perm),
    hasRole: (s) => (role) => (s.user?.roles || []).includes(role),
  },
  actions: {
    async loadCartIfAllowed() {
      if (!this.user) return;
      if (!this.can('borrow_items')) return;

      await useCartStore().fetch();
    },

    async bootstrap() {
      const { data } = await api.get('/auth/config');
      this.config = data;
      const token = localStorage.getItem('depot_token');
      if (token) {
        try {
          const me = await api.get('/auth/me');
          this.user = me.data.user;
          await this.loadCartIfAllowed();
        } catch {
          localStorage.removeItem('depot_token');
          useCartStore().resetLocal();
        }
      }
      this.loaded = true;
    },
    async login(email, password) {
      const { data } = await api.post('/auth/login', { email, password });
      localStorage.setItem('depot_token', data.token);
      this.user = data.user;
      await this.loadCartIfAllowed();
    },
    async requestMagic(email) {
      return api.post('/auth/magic', { email });
    },
    async consumeMagic(token) {
      const { data } = await api.post('/auth/magic/consume', { token });
      localStorage.setItem('depot_token', data.token);
      this.user = data.user;
      await this.loadCartIfAllowed();
    },
    async logout() {
      try {
        await api.post('/auth/logout');
      } catch {
        // Session may already be gone.
      }
      localStorage.removeItem('depot_token');
      this.user = null;
      useCartStore().resetLocal();
    },
  },
});
