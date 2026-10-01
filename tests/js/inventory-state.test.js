import assert from 'node:assert/strict';
import test from 'node:test';
import {
    INVENTORY_STORAGE_KEY,
    InventoryStore,
    inventoryIdentity,
    inventorySummary,
    parseInventoryPayload,
} from '../../resources/js/inventory-state.js';

class MemoryStorage {
    values = new Map();

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, value);
    }
}

class FailingStorage extends MemoryStorage {
    setItem() {
        throw new Error('quota exceeded');
    }
}

test('parses payloads with and without an emplacement', () => {
    assert.deepEqual(parseInventoryPayload(' 6roulement-086&a11 '), {
        payload: '6ROULEMENT-086&A11',
        codeArticle: '6ROULEMENT-086',
        emplacement: 'A11',
    });
    assert.deepEqual(parseInventoryPayload('VIS-125'), {
        payload: 'VIS-125',
        codeArticle: 'VIS-125',
        emplacement: null,
    });
});

test('rejects malformed inventory payloads', () => {
    for (const payload of ['&A11', 'CODE&', 'CODE&A11&OTHER', '   ']) {
        assert.throws(() => parseInventoryPayload(payload));
    }
});

test('uses code article and emplacement as the exact duplicate identity', () => {
    const store = new InventoryStore(new MemoryStorage());
    const session = store.createSession({ name: 'Zone A' });
    const a11 = parseInventoryPayload('6ROULEMENT-086&A11');
    const b21 = parseInventoryPayload('6ROULEMENT-086&B21');

    assert.equal(store.saveItem(session.uuid, a11, '2').duplicate, false);
    assert.equal(store.saveItem(session.uuid, b21, '3').duplicate, false);
    assert.equal(store.saveItem(session.uuid, a11, '1').duplicate, true);
    assert.equal(store.getSession(session.uuid).items.length, 2);
    assert.equal(inventoryIdentity(a11), '["6ROULEMENT-086","A11"]');
});

test('treats a null emplacement as part of the identity', () => {
    const store = new InventoryStore(new MemoryStorage());
    const session = store.createSession({ name: 'Zone A' });
    const article = parseInventoryPayload('VIS-125');

    store.saveItem(session.uuid, article, '1');

    assert.equal(store.saveItem(session.uuid, article, '1').duplicate, true);
});

test('restores persisted sessions after a reload', () => {
    const storage = new MemoryStorage();
    const firstStore = new InventoryStore(storage);
    const session = firstStore.createSession({ name: 'Septembre', zone: 'A' });
    firstStore.saveItem(session.uuid, parseInventoryPayload('VIS-125'), '1.250');

    const restored = new InventoryStore(storage).getSession(session.uuid);

    assert.equal(restored.name, 'Septembre');
    assert.equal(restored.items[0].quantity, '1.250');
});

test('corrupt or incompatible storage fails safely', () => {
    const storage = new MemoryStorage();
    storage.setItem(INVENTORY_STORAGE_KEY, '{not json');
    assert.deepEqual(new InventoryStore(storage).sessions(), []);

    storage.setItem(INVENTORY_STORAGE_KEY, JSON.stringify({ version: 99, sessions: [] }));
    assert.deepEqual(new InventoryStore(storage).sessions(), []);

    storage.setItem(INVENTORY_STORAGE_KEY, JSON.stringify({
        version: 1,
        sessions: [{
            uuid: 'valid-session',
            name: 'Valid',
            status: 'in_progress',
            startedAt: new Date().toISOString(),
            items: [{ uuid: 'broken-item', codeArticle: 'CODE', emplacement: null, quantity: 'not-a-number' }],
        }],
    }));
    assert.deepEqual(new InventoryStore(storage).getSession('valid-session').items, []);

    assert.throws(
        () => new InventoryStore(new FailingStorage()).createSession({ name: 'Cannot persist' }),
        /Impossible d enregistrer localement/,
    );
});

test('adds, replaces, deletes, completes, and reopens with exact thousandths', () => {
    const store = new InventoryStore(new MemoryStorage());
    const session = store.createSession({ name: 'Zone A' });
    const article = parseInventoryPayload('VIS-125');

    store.saveItem(session.uuid, article, '1.250');
    store.saveItem(session.uuid, article, '2.500', 'add');
    let restored = store.getSession(session.uuid);
    assert.equal(restored.items[0].quantity, '3.750');
    assert.equal(inventorySummary(restored).totalQuantity, '3.750');

    store.saveItem(session.uuid, article, '0.125', 'replace');
    restored = store.getSession(session.uuid);
    assert.equal(restored.items[0].quantity, '0.125');

    store.complete(session.uuid);
    assert.equal(store.getSession(session.uuid).status, 'completed');
    assert.throws(() => store.saveItem(session.uuid, article, '1'));

    store.reopen(session.uuid);
    restored = store.getSession(session.uuid);
    assert.equal(restored.status, 'in_progress');

    store.deleteItem(session.uuid, restored.items[0].uuid);
    assert.equal(store.getSession(session.uuid).items.length, 0);
});
