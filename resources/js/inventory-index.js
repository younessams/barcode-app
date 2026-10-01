import { InventoryStore, inventorySummary } from './inventory-state';

const app = document.querySelector('.app');
const form = document.querySelector('#inventory-create-form');
const list = document.querySelector('#inventory-list');
const empty = document.querySelector('#empty-inventories');
const error = document.querySelector('#create-error');
const store = new InventoryStore();

function inventoryUrl(uuid) {
    return app.dataset.showUrlTemplate.replace('__INVENTORY_UUID__', encodeURIComponent(uuid));
}

function renderInventories() {
    const sessions = store.sessions().sort((left, right) => right.startedAt.localeCompare(left.startedAt));

    list.replaceChildren();
    empty.hidden = sessions.length > 0;

    for (const session of sessions) {
        const summary = inventorySummary(session);
        const row = document.createElement('article');
        row.className = 'inventory-row';

        const details = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = session.name;
        const meta = document.createElement('div');
        meta.className = 'inventory-meta';
        meta.textContent = `${session.zone || 'Zone non renseignee'} · ${summary.itemsCount} references · ${summary.totalQuantity} total · ${new Intl.DateTimeFormat('fr-FR').format(new Date(session.startedAt))}`;
        details.append(name, meta);

        const action = document.createElement('a');
        action.className = 'button';
        action.href = inventoryUrl(session.uuid);
        action.textContent = session.status === 'completed' ? 'Consulter' : 'Continuer';

        row.append(details, action);
        list.append(row);
    }
}

function showError(message) {
    error.textContent = message;
    error.hidden = false;
}

if (form) {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        error.hidden = true;

        try {
            const session = store.createSession({
                name: form.elements.name.value,
                zone: form.elements.zone.value,
            });

            window.location.assign(inventoryUrl(session.uuid));
        } catch (exception) {
            showError(exception.message || 'La creation de l inventaire a echoue.');
        }
    });
}

renderInventories();
