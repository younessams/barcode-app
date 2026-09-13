import { createApp } from 'vue';
import BarcodeLabelsApp from './pages/BarcodeLabelsApp.vue';
import ManualCatalogueApp from './pages/ManualCatalogueApp.vue';

const labelsRoot = document.querySelector('#barcode-labels-app');
const manualCatalogueRoot = document.querySelector('#manual-catalogue-app');

if (labelsRoot) {
    createApp(BarcodeLabelsApp, window.BarcodeLabelsPage || {}).mount(labelsRoot);
}

if (manualCatalogueRoot) {
    createApp(ManualCatalogueApp, window.ManualCataloguePage || {}).mount(manualCatalogueRoot);
}
