export const INVENTORY_STORAGE_KEY = 'barcode-app.inventory.v1';
export const INVENTORY_STORAGE_VERSION = 1;
const MAX_QUANTITY_UNITS = 4_294_967_295_000;

export function parseInventoryPayload(value) {
    const input = String(value ?? '').trim();

    if (input === '') {
        throw new Error('Le Code Article est obligatoire.');
    }

    const separatorCount = (input.match(/&/g) || []).length;

    if (separatorCount > 1) {
        throw new Error('Le format QR ne peut contenir qu un seul separateur "&".');
    }

    if (separatorCount === 0) {
        const codeArticle = normalizePart(input);

        return {
            payload: codeArticle,
            codeArticle,
            emplacement: null,
        };
    }

    const [rawCodeArticle, rawEmplacement] = input.split('&');
    const codeArticle = normalizePart(rawCodeArticle);
    const emplacement = normalizePart(rawEmplacement);

    if (codeArticle === '') {
        throw new Error('Le Code Article avant le separateur "&" est obligatoire.');
    }

    if (emplacement === '') {
        throw new Error('L emplacement apres le separateur "&" est obligatoire.');
    }

    return {
        payload: `${codeArticle}&${emplacement}`,
        codeArticle,
        emplacement,
    };
}

export function inventoryIdentity({ codeArticle, emplacement }) {
    return JSON.stringify([codeArticle, emplacement ?? null]);
}

export function quantityToUnits(value) {
    const quantity = String(value ?? '').trim().replace(',', '.');

    if (!/^\d+(?:\.\d{1,3})?$/.test(quantity)) {
        throw new Error('La quantite doit contenir au maximum trois decimales.');
    }

    const [whole, decimal = ''] = quantity.split('.');
    const units = (Number(whole) * 1000) + Number(decimal.padEnd(3, '0'));

    if (!Number.isSafeInteger(units) || units > MAX_QUANTITY_UNITS) {
        throw new Error('La quantite totale depasse la limite autorisee.');
    }

    return units;
}

export function formatQuantity(units) {
    if (!Number.isSafeInteger(units) || units < 0 || units > MAX_QUANTITY_UNITS) {
        throw new Error('La quantite est invalide.');
    }

    return `${Math.floor(units / 1000)}.${String(units % 1000).padStart(3, '0')}`;
}

export function createInventoryUuid() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    if (globalThis.crypto?.getRandomValues) {
        const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    return `inventory-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export class InventoryStore {
    constructor(storage = browserStorage()) {
        this.storage = storage;
    }

    sessions() {
        return clone(this.read().sessions);
    }

    getSession(uuid) {
        const session = this.read().sessions.find((candidate) => candidate.uuid === uuid);

        return session ? clone(session) : null;
    }

    createSession({ name, zone }) {
        const normalizedName = String(name ?? '').trim();

        if (normalizedName === '') {
            throw new Error('Le nom de l inventaire est obligatoire.');
        }

        const session = {
            uuid: createInventoryUuid(),
            name: normalizedName,
            zone: normalizeNullable(zone),
            status: 'in_progress',
            startedAt: new Date().toISOString(),
            finishedAt: null,
            items: [],
        };
        const state = this.read();
        state.sessions.push(session);
        this.write(state);

        return clone(session);
    }

    saveItem(sessionUuid, article, quantity, mode = null) {
        const state = this.read();
        const session = this.requireEditableSession(state, sessionUuid);
        const identity = inventoryIdentity(article);
        const item = session.items.find((candidate) => inventoryIdentity(candidate) === identity);

        if (item && mode === null) {
            return { duplicate: true, item: clone(item), session: clone(session) };
        }

        const quantityUnits = quantityToUnits(quantity);

        if (item) {
            item.quantity = mode === 'add'
                ? formatQuantity(quantityToUnits(item.quantity) + quantityUnits)
                : formatQuantity(quantityUnits);
        } else {
            session.items.push({
                uuid: createInventoryUuid(),
                codeArticle: article.codeArticle,
                emplacement: article.emplacement ?? null,
                quantity: formatQuantity(quantityUnits),
            });
        }

        this.write(state);

        return {
            duplicate: false,
            item: clone(item ?? session.items[session.items.length - 1]),
            session: clone(session),
        };
    }

    updateItem(sessionUuid, itemUuid, quantity) {
        const state = this.read();
        const session = this.requireEditableSession(state, sessionUuid);
        const item = session.items.find((candidate) => candidate.uuid === itemUuid);

        if (!item) {
            throw new Error('Article introuvable.');
        }

        item.quantity = formatQuantity(quantityToUnits(quantity));
        this.write(state);

        return { item: clone(item), session: clone(session) };
    }

    deleteItem(sessionUuid, itemUuid) {
        const state = this.read();
        const session = this.requireEditableSession(state, sessionUuid);
        const index = session.items.findIndex((candidate) => candidate.uuid === itemUuid);

        if (index === -1) {
            throw new Error('Article introuvable.');
        }

        session.items.splice(index, 1);
        this.write(state);

        return clone(session);
    }

    complete(sessionUuid) {
        return this.setStatus(sessionUuid, 'completed');
    }

    reopen(sessionUuid) {
        return this.setStatus(sessionUuid, 'in_progress');
    }

    read() {
        try {
            const raw = this.storage?.getItem(INVENTORY_STORAGE_KEY);

            if (!raw) {
                return emptyState();
            }

            const state = JSON.parse(raw);

            if (
                !state
                || state.version !== INVENTORY_STORAGE_VERSION
                || !Array.isArray(state.sessions)
            ) {
                return emptyState();
            }

            return {
                version: INVENTORY_STORAGE_VERSION,
            sessions: state.sessions
                .filter(isValidSession)
                .map((session) => ({
                    ...session,
                    items: session.items.filter(isValidItem),
                })),
            };
        } catch (error) {
            return emptyState();
        }
    }

    write(state) {
        if (!this.storage) {
            throw new Error('Le stockage local est indisponible dans ce navigateur.');
        }

        try {
            this.storage.setItem(INVENTORY_STORAGE_KEY, JSON.stringify({
                version: INVENTORY_STORAGE_VERSION,
                sessions: state.sessions,
            }));
        } catch (error) {
            throw new Error('Impossible d enregistrer localement cet inventaire.');
        }
    }

    requireEditableSession(state, uuid) {
        const session = state.sessions.find((candidate) => candidate.uuid === uuid);

        if (!session) {
            throw new Error('Inventaire introuvable.');
        }

        if (session.status !== 'in_progress') {
            throw new Error('Cet inventaire est termine. Reouvrez-le pour le modifier.');
        }

        return session;
    }

    setStatus(sessionUuid, status) {
        const state = this.read();
        const session = state.sessions.find((candidate) => candidate.uuid === sessionUuid);

        if (!session) {
            throw new Error('Inventaire introuvable.');
        }

        session.status = status;
        session.finishedAt = status === 'completed' ? new Date().toISOString() : null;
        this.write(state);

        return clone(session);
    }
}

export function inventorySummary(session) {
    const totalUnits = session.items.reduce(
        (total, item) => total + quantityToUnits(item.quantity),
        0,
    );

    return {
        itemsCount: session.items.length,
        totalQuantity: formatQuantity(totalUnits),
    };
}

function normalizePart(value) {
    return String(value ?? '').trim().toLocaleUpperCase();
}

function normalizeNullable(value) {
    const normalized = String(value ?? '').trim();

    return normalized === '' ? null : normalized;
}

function emptyState() {
    return { version: INVENTORY_STORAGE_VERSION, sessions: [] };
}

function browserStorage() {
    try {
        return globalThis.localStorage ?? null;
    } catch (error) {
        return null;
    }
}

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function isValidSession(session) {
    return (
        session
        && typeof session.uuid === 'string'
        && typeof session.name === 'string'
        && session.name !== ''
        && ['in_progress', 'completed'].includes(session.status)
        && typeof session.startedAt === 'string'
        && Array.isArray(session.items)
    );
}

function isValidItem(item) {
    if (
        !item
        || typeof item.uuid !== 'string'
        || typeof item.codeArticle !== 'string'
        || item.codeArticle === ''
        || (item.emplacement !== null && typeof item.emplacement !== 'string')
        || item.emplacement === ''
    ) {
        return false;
    }

    try {
        quantityToUnits(item.quantity);

        return true;
    } catch (error) {
        return false;
    }
}
