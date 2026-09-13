import { createApp } from 'vue';
import InventoryIndexApp from './pages/InventoryIndexApp.vue';
import InventoryShowApp from './pages/InventoryShowApp.vue';

const inventoryIndexRoot = document.querySelector('#inventory-index-app');
const inventoryShowRoot = document.querySelector('#inventory-show-app');

if (inventoryIndexRoot) {
    createApp(InventoryIndexApp, window.InventoryIndexPage || {}).mount(inventoryIndexRoot);
}

if (inventoryShowRoot) {
    createApp(InventoryShowApp, window.InventoryShowPage || {}).mount(inventoryShowRoot);
}
