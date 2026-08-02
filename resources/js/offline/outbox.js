import api from '../api';

const STORAGE_KEY = 'depot_scan_outbox';

function readAll() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function writeAll(events) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(events));
}

function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function addScan({ action, qrToken, loanId = null, payload = null }) {
    const events = readAll();
    const event = {
        client_uuid: uuid(),
        action,
        qr_token: qrToken,
        loan_id: loanId,
        payload: payload || null,
        scanned_at: new Date().toISOString(),
        status: 'pending',
        error: null,
    };
    events.push(event);
    writeAll(events);
    return event;
}

export function listPending() {
    return readAll();
}

export function removeEvent(clientUuid) {
    writeAll(readAll().filter((e) => e.client_uuid !== clientUuid));
}

export function clearSynced() {
    writeAll(readAll().filter((e) => e.status !== 'synced'));
}

export async function syncAll() {
    const events = readAll().filter((e) => e.status !== 'synced');
    if (!events.length) {
        return { synced: 0, failed: 0, results: [] };
    }

    try {
        const { data } = await api.post('/loans/sync-offline', {
            events: events.map(({ client_uuid, action, qr_token, loan_id, payload, scanned_at }) => ({
                client_uuid,
                action,
                qr_token,
                loan_id: loan_id || undefined,
                payload: payload || undefined,
                scanned_at,
            })),
        });

        const results = data.data || [];
        const byUuid = new Map(results.map((r) => [r.client_uuid, r]));

        const updated = readAll().map((e) => {
            const result = byUuid.get(e.client_uuid);
            if (!result) return e;

            // API returns OfflineScanEvent with status synced|failed and error_message.
            const synced = result.status === 'synced' || Boolean(result.synced_at);
            const failed = result.status === 'failed';

            if (synced) {
                return { ...e, status: 'synced', error: null, loan_id: result.loan_id || e.loan_id };
            }
            if (failed) {
                return {
                    ...e,
                    status: 'failed',
                    error: result.error_message || result.error || result.message || 'Sync failed',
                };
            }

            return e;
        });
        writeAll(updated);

        const synced = updated.filter((e) => e.status === 'synced').length;
        const failed = updated.filter((e) => e.status === 'failed').length;

        return { synced, failed, results };
    } catch (error) {
        const message = error.response?.data?.message || 'Network error while syncing.';
        const updated = readAll().map((e) => (e.status === 'pending' ? { ...e, status: 'failed', error: message } : e));
        writeAll(updated);
        throw error;
    }
}
