import { defineStore } from 'pinia';
import api from '../api';

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
        async bootstrap() {
            const { data } = await api.get('/auth/config');
            this.config = data;
            const token = localStorage.getItem('depot_token');
            if (token) {
                try {
                    const me = await api.get('/auth/me');
                    this.user = me.data.user;
                } catch {
                    localStorage.removeItem('depot_token');
                }
            }
            this.loaded = true;
        },
        async login(email, password) {
            const { data } = await api.post('/auth/login', { email, password });
            localStorage.setItem('depot_token', data.token);
            this.user = data.user;
        },
        async requestMagic(email) {
            return api.post('/auth/magic', { email });
        },
        async consumeMagic(token) {
            const { data } = await api.post('/auth/magic/consume', { token });
            localStorage.setItem('depot_token', data.token);
            this.user = data.user;
        },
        async logout() {
            try {
                await api.post('/auth/logout');
            } catch {}
            localStorage.removeItem('depot_token');
            this.user = null;
        },
    },
});

const CART_STORAGE_KEY = 'depot_cart_lines';

function readStoredLines() {
    try {
        const raw = localStorage.getItem(CART_STORAGE_KEY);

        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        // Survives reloads so a half-finished pick list is never lost on a phone.
        lines: readStoredLines(),
        property_id: null,
        pickup_depot_id: null,
        needed_from: '',
        needed_until: '',
        purpose: '',
        priority: 'normal',
    }),
    actions: {
        persist() {
            try {
                localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.lines));
            } catch {
                // Private browsing / storage full: cart simply stays in memory.
            }
        },
        addLine(line) {
            const key = line.item_id ? `item-${line.item_id}` : `type-${line.tool_type_id}`;
            const existing = this.lines.find((l) => l._key === key);

            if (existing) {
                existing.quantity = (existing.quantity || 1) + (line.quantity || 1);
            } else {
                this.lines.push({ quantity: 1, ...line, _key: key });
            }

            this.persist();
        },
        setQuantity(index, quantity) {
            const line = this.lines[index];
            if (!line) return;

            line.quantity = Math.max(1, Number(quantity) || 1);
            this.persist();
        },
        removeLine(index) {
            this.lines.splice(index, 1);
            this.persist();
        },
        clear() {
            this.lines = [];
            this.purpose = '';
            this.persist();
        },
    },
});
