import { defineStore } from 'pinia';

let nextId = 1;

export const useToastStore = defineStore('toast', {
  state: () => ({
    items: [],
  }),
  actions: {
    push(message, { tone = 'brand', icon = null, timeout = 6000 } = {}) {
      const id = nextId++;
      this.items = [...this.items, { id, message, tone, icon }];

      if (timeout) {
        setTimeout(() => this.dismiss(id), timeout);
      }

      return id;
    },
    success(message) {
      return this.push(message, { tone: 'brand', icon: 'check-circle' });
    },
    error(message) {
      return this.push(message, { tone: 'danger', icon: 'alert', timeout: 8000 });
    },
    info(message) {
      return this.push(message, { tone: 'info', icon: 'info' });
    },
    dismiss(id) {
      this.items = this.items.filter((item) => item.id !== id);
    },
  },
});
