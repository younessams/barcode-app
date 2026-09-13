import { createApp } from 'vue';
import BarcodeLabelsApp from './pages/BarcodeLabelsApp.vue';

const labelsRoot = document.querySelector('#barcode-labels-app');

if (labelsRoot) {
    createApp(BarcodeLabelsApp, window.BarcodeLabelsPage || {}).mount(labelsRoot);
}
