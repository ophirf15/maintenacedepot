import { defineStore } from 'pinia';
import api from '../api';
import { fromLocalInput, toLocalInput } from '../datetime';

const LEGACY_CART_STORAGE_KEY = 'depot_cart_lines';
const SAVE_DEBOUNCE_MS = 300;

function readLegacyLines() {
  try {
    const raw = localStorage.getItem(LEGACY_CART_STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function clearLegacyLines() {
  try {
    localStorage.removeItem(LEGACY_CART_STORAGE_KEY);
  } catch {
    // Ignore storage errors.
  }
}

function emptyState() {
  return {
    lines: [],
    property_id: null,
    pickup_depot_id: null,
    needed_from: '',
    needed_until: '',
    purpose: '',
    priority: 'normal',
  };
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    ...emptyState(),
    loaded: false,
    saving: false,
    _saveTimer: null,
  }),
  actions: {
    applyServerCart(data) {
      this.lines = Array.isArray(data?.lines) ? data.lines.map((line) => ({ ...line })) : [];
      this.property_id = data?.property_id ?? null;
      this.pickup_depot_id = data?.pickup_depot_id ?? null;
      this.needed_from = toLocalInput(data?.needed_from) || '';
      this.needed_until = toLocalInput(data?.needed_until) || '';
      this.purpose = data?.purpose || '';
      this.priority = data?.priority || 'normal';
    },

    resetLocal() {
      if (this._saveTimer) {
        clearTimeout(this._saveTimer);
        this._saveTimer = null;
      }
      Object.assign(this, emptyState(), { loaded: false, saving: false });
    },

    payload() {
      return {
        property_id: this.property_id || null,
        pickup_depot_id: this.pickup_depot_id || null,
        needed_from: fromLocalInput(this.needed_from),
        needed_until: fromLocalInput(this.needed_until),
        purpose: this.purpose || null,
        priority: this.priority || 'normal',
        lines: this.lines.map((line) => ({
          request_mode: line.request_mode,
          item_id: line.item_id || null,
          tool_type_id: line.tool_type_id || null,
          quantity: line.quantity || 1,
          notes: line.notes || '',
          label: line.label || null,
          icon: line.icon || null,
          _key: line._key || null,
          image_url: line.image_url || null,
          specs: line.specs || null,
          depot_id: line.depot_id || null,
          depot_name: line.depot_name || null,
        })),
      };
    },

    scheduleSave() {
      if (!this.loaded) return;
      if (this._saveTimer) clearTimeout(this._saveTimer);
      this._saveTimer = setTimeout(() => {
        this._saveTimer = null;
        this.saveNow();
      }, SAVE_DEBOUNCE_MS);
    },

    async saveNow() {
      if (this._saveTimer) {
        clearTimeout(this._saveTimer);
        this._saveTimer = null;
      }

      this.saving = true;
      try {
        await api.put('/cart', this.payload());
      } catch {
        // Keep local state; next mutation retries.
      } finally {
        this.saving = false;
      }
    },

    async fetch() {
      try {
        const { data } = await api.get('/cart');
        this.applyServerCart(data.data);

        const legacy = readLegacyLines();
        if ((!data.data?.lines || data.data.lines.length === 0) && legacy.length) {
          this.lines = legacy.map((line) => ({ ...line }));
          this.loaded = true;
          await this.saveNow();
        }
        clearLegacyLines();
      } catch {
        this.applyServerCart(emptyState());
      } finally {
        this.loaded = true;
      }
    },

    addLine(line) {
      const key = line.item_id ? `item-${line.item_id}` : `type-${line.tool_type_id}`;
      const existing = this.lines.find((l) => l._key === key);

      if (existing) {
        this.lines = this.lines.map((l) =>
          l._key === key
            ? { ...l, quantity: (l.quantity || 1) + (line.quantity || 1) }
            : l
        );
      } else {
        this.lines = [...this.lines, { quantity: 1, ...line, _key: key }];
      }

      this.scheduleSave();
    },

    setQuantity(index, quantity) {
      const next = Math.max(1, Number(quantity) || 1);
      this.lines = this.lines.map((line, i) => (i === index ? { ...line, quantity: next } : line));
      this.scheduleSave();
    },

    removeLine(index) {
      this.lines = this.lines.filter((_, i) => i !== index);
      this.scheduleSave();
    },

    /** Persist form fields (property, dates, purpose, priority). */
    touchMeta() {
      this.scheduleSave();
    },

    async clear() {
      this.lines = [];
      this.purpose = '';
      this.property_id = null;
      this.pickup_depot_id = null;
      this.needed_from = '';
      this.needed_until = '';
      this.priority = 'normal';

      try {
        await api.delete('/cart');
      } catch {
        await this.saveNow();
      }
    },
  },
});
