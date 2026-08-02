import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
    { path: '/login/magic', name: 'magic', component: () => import('../views/MagicConsumeView.vue'), meta: { guest: true } },
    { path: '/install', name: 'install', component: () => import('../views/InstallView.vue'), meta: { guest: true } },
    {
        path: '/',
        component: () => import('../components/AppLayout.vue'),
        meta: { auth: true },
        children: [
            { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
            { path: 'search', name: 'search', component: () => import('../views/SearchView.vue') },
            { path: 'notifications', name: 'notifications', component: () => import('../views/NotificationsView.vue') },
            { path: 'catalog', name: 'catalog', component: () => import('../views/catalog/CatalogView.vue') },
            { path: 'catalog/:categoryId', name: 'catalog-category', component: () => import('../views/catalog/CategoryView.vue') },
            { path: 'items/:id', name: 'unit-detail', component: () => import('../views/catalog/UnitDetailView.vue') },
            { path: 'cart', name: 'cart', component: () => import('../views/requests/CartView.vue') },
            { path: 'requests', name: 'requests', component: () => import('../views/requests/RequestListView.vue') },
            { path: 'requests/:id', name: 'request-detail', component: () => import('../views/requests/RequestDetailView.vue') },
            { path: 'approvals', name: 'approvals', component: () => import('../views/requests/ApprovalQueueView.vue') },
            { path: 'loans', name: 'loans', component: () => import('../views/loans/LoanListView.vue') },
            { path: 'loans/:id', name: 'loan-detail', component: () => import('../views/loans/LoanDetailView.vue') },
            { path: 'scan', name: 'scan', component: () => import('../views/loans/ScanView.vue') },
            { path: 'tickets', name: 'tickets', component: () => import('../views/tickets/TicketListView.vue') },
            { path: 'tickets/:id', name: 'ticket-detail', component: () => import('../views/tickets/TicketDetailView.vue') },
            { path: 'maintenance', name: 'maintenance', component: () => import('../views/maintenance/MaintenanceView.vue') },
            { path: 'inventory', name: 'inventory', component: () => import('../views/admin/InventoryView.vue') },
            { path: 'consumables', name: 'consumables', component: () => import('../views/admin/ConsumablesView.vue') },
            { path: 'catalog-admin', name: 'catalog-admin', component: () => import('../views/admin/CatalogAdminView.vue') },
            { path: 'inventory/items/:id', name: 'item-detail', component: () => import('../views/admin/ItemDetailView.vue') },
            { path: 'capex', name: 'capex', component: () => import('../views/admin/CapexView.vue') },
            { path: 'it', name: 'it', component: () => import('../views/it/ItAdminView.vue') },
            { path: 'audit', name: 'audit', component: () => import('../views/it/AuditView.vue') },
            { path: 'help', name: 'help', component: () => import('../views/HelpManualView.vue') },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.loaded) {
        try {
            await auth.bootstrap();
        } catch {
            auth.loaded = true;
        }
    }

    if (!auth.config?.installed && to.name !== 'install') {
        return { name: 'install' };
    }

    if (auth.config?.installed && to.name === 'install') {
        return auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' };
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
