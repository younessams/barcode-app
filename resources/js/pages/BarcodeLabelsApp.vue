<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Barcode,
    Download,
    FileSpreadsheet,
    Printer,
    TriangleAlert,
    Upload,
    createIcons,
} from 'lucide';

const props = defineProps({
    presets: { type: Array, required: true },
    headersUrl: { type: String, required: true },
    generateUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    oldExcelColumn: { type: String, default: '' },
    errors: { type: Array, default: () => [] },
    result: { type: Object, default: null },
});

const fileInput = ref(null);
const columnSelect = ref(null);
const preview = ref(null);
const selectedFileName = ref('');
const isDragging = ref(false);
const headers = ref([]);
const selectedColumn = ref(props.oldExcelColumn || '');
const headerStatus = ref('Choisissez un fichier Excel');
const headersLoading = ref(false);
const headersError = ref('');
const selectedPresetId = ref(props.presets.find((preset) => preset.default)?.id || props.presets[0]?.id || '');
const codeType = ref('code128');
const previewWidth = ref(0);
let previewObserver = null;

const presetsById = computed(() => Object.fromEntries(props.presets.map((preset) => [preset.id, preset])));
const selectedPreset = computed(() => presetsById.value[selectedPresetId.value] || presetsById.value['70x37'] || props.presets[0]);
const previewMode = computed(() => `${selectedPreset.value.displayWidthMm} x ${selectedPreset.value.displayHeightMm}`);
const hasFile = computed(() => selectedFileName.value !== '');
const columnDisabled = computed(() => headersLoading.value || headers.value.length === 0 || headersError.value !== '');
const columnPlaceholder = computed(() => {
    if (headersLoading.value) return 'Lecture des colonnes...';
    if (headersError.value) return headersError.value;
    if (headers.value.length > 0) return 'Choisissez une colonne';
    return headerStatus.value;
});
const previewScale = computed(() => (previewWidth.value > 0 ? previewWidth.value / 210 : 0));
const labels = computed(() => {
    const preset = selectedPreset.value;
    const scale = previewScale.value;

    if (!preset || !scale) return [];

    const items = [];

    for (let row = 0; row < preset.rows; row += 1) {
        for (let column = 0; column < preset.columns; column += 1) {
            const left = preset.marginLeftMm + column * (preset.labelWidthMm + preset.gapXMm);
            const top = preset.marginTopMm + row * (preset.labelHeightMm + preset.gapYMm);
            const qr = codeType.value === 'qr' ? getQrPreview(left, top, preset, scale) : null;

            items.push({
                key: `${row}-${column}`,
                guideStyle: {
                    left: `${left * scale}px`,
                    top: `${top * scale}px`,
                    width: `${preset.labelWidthMm * scale}px`,
                    height: `${preset.labelHeightMm * scale}px`,
                },
                barcodeStyle: qr ? null : {
                    left: `${(left + preset.barcode.xMm) * scale}px`,
                    top: `${(top + preset.barcode.yMm) * scale}px`,
                    width: `${preset.barcode.widthMm * scale}px`,
                    height: `${preset.barcode.heightMm * scale}px`,
                },
                qr,
                textStyle: getTextStyle(left, top, preset, scale, qr),
            });
        }
    }

    return items;
});

function refreshIcons() {
    nextTick(() => createIcons({
        icons: { Barcode, Download, FileSpreadsheet, Printer, TriangleAlert, Upload },
    }));
}

function chooseFile(event) {
    event.preventDefault();
    event.stopPropagation();
    fileInput.value?.click();
}

function chooseFileWithKeyboard(event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    chooseFile(event);
}

function setFile(file) {
    if (!file || !/\.(xlsx|xls)$/i.test(file.name)) return;

    const transfer = new DataTransfer();
    transfer.items.add(file);
    fileInput.value.files = transfer.files;
    selectedFileName.value = file.name;
    loadHeaders(file);
}

function onFileChange() {
    setFile(fileInput.value?.files?.[0]);
}

function onDragEnter(event) {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave(event) {
    event.preventDefault();
    isDragging.value = false;
}

function onDrop(event) {
    event.preventDefault();
    isDragging.value = false;
    setFile(event.dataTransfer.files[0]);
}

async function loadHeaders(file) {
    headersLoading.value = true;
    headersError.value = '';
    headers.value = [];
    selectedColumn.value = '';

    const data = new FormData();
    data.append('excel_file', file);

    try {
        const response = await fetch(props.headersUrl, {
            method: 'POST',
            body: data,
            headers: {
                'X-CSRF-TOKEN': props.csrfToken,
                Accept: 'application/json',
            },
        });
        const payload = await response.json();

        if (!response.ok) throw new Error(payload.message || 'Le fichier Excel ne peut pas etre lu.');

        headers.value = payload.headers;

        if (props.oldExcelColumn && payload.headers.includes(props.oldExcelColumn)) {
            selectedColumn.value = props.oldExcelColumn;
        } else {
            selectedColumn.value = findDefaultHeader(payload.headers);
        }
    } catch (error) {
        headersError.value = error.message;
    } finally {
        headersLoading.value = false;
    }
}

function normalizeHeader(value) {
    return value.replace(/[\s\u00a0]+/g, ' ').trim().toLocaleLowerCase();
}

function findDefaultHeader(availableHeaders) {
    const counts = availableHeaders.reduce((result, header) => {
        const normalized = normalizeHeader(header);
        result[normalized] = (result[normalized] || 0) + 1;
        return result;
    }, {});
    const codeArticle = availableHeaders.find((header) => normalizeHeader(header) === normalizeHeader('Code Article'));

    if (codeArticle && counts[normalizeHeader(codeArticle)] === 1) return codeArticle;

    return availableHeaders.find((header) => counts[normalizeHeader(header)] === 1) || '';
}

function onSubmit(event) {
    if (!columnDisabled.value && selectedColumn.value) return;

    event.preventDefault();
    columnSelect.value?.focus();
}

function updatePreviewWidth() {
    if (!preview.value) return;

    previewWidth.value = preview.value.clientWidth;

    if (!previewWidth.value) {
        requestAnimationFrame(updatePreviewWidth);
    }
}

function getQrPreview(left, top, preset, scale) {
    let sizeMm = Math.min(
        preset.labelWidthMm - 2,
        preset.labelHeightMm - 1 - preset.barcode.textHeightMm - preset.barcode.textGapMm
    );

    if (preset.id === '70x37') {
        sizeMm = Math.min(sizeMm, 24);
    }

    const sizePx = sizeMm * scale;

    return {
        modules: getQrModules(),
        style: {
            left: `${(left + ((preset.labelWidthMm - sizeMm) / 2)) * scale}px`,
            top: `${(top + 0.5) * scale}px`,
            width: `${sizePx}px`,
            height: `${sizePx}px`,
            position: 'absolute',
        },
        textTop: (top + 0.5 + sizeMm + preset.barcode.textGapMm) * scale,
        textLeft: left * scale,
        textWidth: preset.labelWidthMm * scale,
    };
}

function getTextStyle(left, top, preset, scale, qr) {
    const textTop = qr ? qr.textTop : (top + preset.barcode.yMm + preset.barcode.heightMm + preset.barcode.textGapMm) * scale;
    const textLeft = qr ? qr.textLeft : (left + preset.barcode.xMm) * scale;
    const textWidth = qr ? qr.textWidth : preset.barcode.widthMm * scale;

    return {
        left: `${textLeft}px`,
        top: `${textTop}px`,
        width: `${textWidth}px`,
        height: `${preset.barcode.textHeightMm * scale}px`,
    };
}

function getQrModules() {
    const modules = [];

    for (let row = 0; row < 29; row += 1) {
        for (let column = 0; column < 29; column += 1) {
            modules.push({
                key: `${row}-${column}`,
                on: isQrPreviewDark(row, column),
            });
        }
    }

    return modules;
}

function isQrPreviewDark(row, column) {
    for (const [startRow, startColumn] of [[4, 4], [4, 18], [18, 4]]) {
        if (row >= startRow && row < startRow + 7 && column >= startColumn && column < startColumn + 7) {
            const edge = row === startRow || row === startRow + 6 || column === startColumn || column === startColumn + 6;
            const center = row >= startRow + 2 && row <= startRow + 4 && column >= startColumn + 2 && column <= startColumn + 4;
            return edge || center;
        }
    }

    return ((row * 17 + column * 31 + row * column) % 7) < 3;
}

watch([selectedPresetId, codeType, labels], refreshIcons);

onMounted(() => {
    refreshIcons();
    updatePreviewWidth();
    previewObserver = new ResizeObserver(updatePreviewWidth);
    previewObserver.observe(preview.value);
    window.addEventListener('resize', updatePreviewWidth);
});

onBeforeUnmount(() => {
    previewObserver?.disconnect();
    window.removeEventListener('resize', updatePreviewWidth);
});
</script>

<template>
    <main>
        <section class="panel">
            <div class="panel-body">
                <div v-for="error in errors" :key="error" class="alert" role="alert">
                    <i data-lucide="TriangleAlert"></i>
                    <div>{{ error }}</div>
                </div>

                <section v-if="result" class="result">
                    <div>
                        <p class="result-title">PDF genere avec succes</p>
                        <p class="result-metrics">{{ result.labelsFormatted }} etiquettes &middot; {{ result.pages }} pages A4</p>
                    </div>
                    <div class="actions">
                        <a class="action-primary" :href="result.downloadUrl">
                            <i data-lucide="Download"></i>Telecharger
                        </a>
                        <a class="action-secondary" :href="result.printUrl" target="_blank" rel="noopener">
                            <i data-lucide="Printer"></i>Imprimer
                        </a>
                    </div>
                </section>

                <form id="upload-form" :action="generateUrl" method="post" enctype="multipart/form-data" @submit="onSubmit">
                    <input type="hidden" name="_token" :value="csrfToken">

                    <div class="section" style="border-top:0;padding-top:0;margin-top:0">
                        <h2>Fichier Excel</h2>
                        <label
                            id="dropzone"
                            class="dropzone"
                            :class="{ 'has-file': hasFile, 'is-dragging': isDragging }"
                            for="excel_file"
                            @dragenter="onDragEnter"
                            @dragover="onDragEnter"
                            @dragleave="onDragLeave"
                            @drop="onDrop"
                        >
                            <input
                                id="excel_file"
                                ref="fileInput"
                                class="file-input"
                                name="excel_file"
                                type="file"
                                accept=".xlsx,.xls"
                                required
                                @change="onFileChange"
                            >
                            <span class="upload-state">
                                <span class="icon-disc"><i data-lucide="FileSpreadsheet"></i></span>
                                <span>Glissez-deposez votre fichier</span>
                                <span id="choose-file-button" class="secondary-button" role="button" tabindex="0" @click="chooseFile" @keydown="chooseFileWithKeyboard">
                                    <i data-lucide="Upload"></i>Choisir un fichier
                                </span>
                                <span class="field-help">XLSX ou XLS</span>
                            </span>
                            <span class="selected-state">
                                <strong id="file-name">{{ selectedFileName }}</strong>
                                <span>Fichier pret</span>
                                <span id="change-file-button" class="secondary-button" role="button" tabindex="0" @click="chooseFile" @keydown="chooseFileWithKeyboard">Modifier</span>
                            </span>
                        </label>
                        <div class="field">
                            <label for="excel_column">Colonne a convertir en code-barres</label>
                            <select id="excel_column" ref="columnSelect" v-model="selectedColumn" name="excel_column" required :disabled="columnDisabled" :data-old-value="oldExcelColumn">
                                <option value="">{{ columnPlaceholder }}</option>
                                <option v-for="header in headers" :key="header" :value="header">{{ header }}</option>
                            </select>
                            <div class="field-help">SKU, Reference, Code article, N serie, N commande, Tracking...</div>
                        </div>
                    </div>

                    <div class="section">
                        <h2>Format d'etiquette</h2>
                        <p class="preset-help">Les formats disponibles sont adaptes aux planches A4 pre-decoupees.</p>
                        <select id="preset-selector" v-model="selectedPresetId" name="preset_id" required>
                            <option v-for="preset in presets" :key="preset.id" :value="preset.id">
                                {{ preset.displayWidthMm }} x {{ preset.displayHeightMm }} mm &middot; {{ preset.labelsPerSheet }} / A4 &middot; {{ preset.columns }}x{{ preset.rows }}{{ preset.recommended ? ' &middot; Recommande' : '' }}
                            </option>
                        </select>
                        <div class="metrics">
                            <div class="metric">Etiquettes / page <strong id="metric-slots">{{ selectedPreset.labelsPerSheet }}</strong></div>
                            <div class="metric">Zone etiquette <strong id="metric-size">{{ previewMode }} mm</strong></div>
                        </div>
                        <p id="preset-warning" class="warning" :hidden="selectedPreset.id !== '38x21_2'">Les valeurs longues peuvent etre plus compactes sur ce petit format.</p>
                    </div>

                    <div class="section">
                        <h2>Type de code</h2>
                        <div class="code-types">
                            <div class="code-type">
                                <input id="code-type-code128" v-model="codeType" type="radio" name="code_type" value="code128">
                                <label for="code-type-code128">
                                    <span class="code-type-title">Code-barres</span>
                                    <span class="code-type-help">Code 128 &middot; Lecteurs classiques</span>
                                </label>
                            </div>
                            <div class="code-type">
                                <input id="code-type-qr" v-model="codeType" type="radio" name="code_type" value="qr">
                                <label for="code-type-qr">
                                    <span class="code-type-title">QR Code</span>
                                    <span class="code-type-help">Lecture rapide avec smartphone</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button id="submit-button" class="primary-button" type="submit">
                        <i data-lucide="Barcode"></i>Generer le PDF
                    </button>
                </form>
            </div>
        </section>

        <section class="preview-panel">
            <div class="preview-head">
                <h2>Apercu A4</h2>
                <span id="preview-mode" class="field-help">{{ previewMode }} mm</span>
            </div>
            <div class="paper-wrap">
                <div id="a4-preview" ref="preview" class="a4-board" aria-label="Apercu de la page A4">
                    <template v-for="label in labels" :key="label.key">
                        <div class="guide" :style="label.guideStyle"></div>
                        <div v-if="label.qr" class="qr-preview" :style="label.qr.style">
                            <span v-for="module in label.qr.modules" :key="module.key" class="qr-module" :class="{ on: module.on }"></span>
                        </div>
                        <div v-else class="placeholder" :style="label.barcodeStyle"></div>
                        <div class="placeholder-text" :style="label.textStyle">CODE ARTICLE</div>
                    </template>
                </div>
            </div>
        </section>
    </main>
</template>
