import { createIcons, Barcode, Download, FileSpreadsheet, Printer, TriangleAlert, Upload } from 'lucide';

const presets = Object.fromEntries((window.BarcodePresets || []).map((preset) => [preset.id, preset]));
const app = document.querySelector('.app');
const form = document.querySelector('#upload-form');
const fileInput = document.querySelector('#excel_file');
const dropzone = document.querySelector('#dropzone');
const columnSelect = document.querySelector('#excel_column');
const presetSelect = document.querySelector('#preset-selector');
const preview = document.querySelector('#a4-preview');
const codeTypeInputs = document.querySelectorAll('input[name="code_type"]');

createIcons({ icons: { Barcode, Download, FileSpreadsheet, Printer, TriangleAlert, Upload } });

function setFile(file) {
    if (!file || !/\.(xlsx|xls)$/i.test(file.name)) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    fileInput.files = transfer.files;
    dropzone.classList.add('has-file');
    document.querySelector('#file-name').textContent = file.name;
    loadHeaders(file);
}

async function loadHeaders(file) {
    columnSelect.disabled = true;
    columnSelect.innerHTML = '<option value="">Lecture des colonnes...</option>';
    const data = new FormData();
    data.append('excel_file', file);
    try {
        const response = await fetch(app.dataset.headersUrl, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Le fichier Excel ne peut pas etre lu.');
        const oldValue = columnSelect.dataset.oldValue;
        columnSelect.innerHTML = '<option value="">Choisissez une colonne</option>';
        payload.headers.forEach((header) => columnSelect.add(new Option(header, header)));
        if (oldValue && [...columnSelect.options].some((option) => option.value === oldValue)) columnSelect.value = oldValue;
        else {
            const defaultHeader = findDefaultHeader(payload.headers);
            if (defaultHeader) columnSelect.value = defaultHeader;
        }
        columnSelect.disabled = payload.headers.length === 0;
    } catch (error) {
        columnSelect.innerHTML = `<option value="">${error.message}</option>`;
        columnSelect.disabled = true;
    }
}

function normalizeHeader(value) {
    return value.replace(/[\s\u00a0]+/g, ' ').trim().toLocaleLowerCase();
}

function findDefaultHeader(headers) {
    const counts = headers.reduce((result, header) => {
        const normalized = normalizeHeader(header);
        result[normalized] = (result[normalized] || 0) + 1;
        return result;
    }, {});
    const codeArticle = headers.find((header) => normalizeHeader(header) === normalizeHeader('Code Article'));
    if (codeArticle && counts[normalizeHeader(codeArticle)] === 1) return codeArticle;
    return headers.find((header) => counts[normalizeHeader(header)] === 1) || '';
}

function renderPreview() {
    const preset = presets[presetSelect.value] || presets['52_5x29_7'];
    if (!preset || !preview) return;
    const width = preview.clientWidth;
    if (!width) return requestAnimationFrame(renderPreview);
    const scale = width / 210;
    preview.innerHTML = '';
    for (let row = 0; row < preset.rows; row += 1) {
        for (let column = 0; column < preset.columns; column += 1) {
            const left = preset.marginLeftMm + column * (preset.labelWidthMm + preset.gapXMm);
            const top = preset.marginTopMm + row * (preset.labelHeightMm + preset.gapYMm);
            const guide = document.createElement('div');
            guide.className = 'guide'; guide.style.cssText = `left:${left * scale}px;top:${top * scale}px;width:${preset.labelWidthMm * scale}px;height:${preset.labelHeightMm * scale}px`;
            const codeType = document.querySelector('input[name="code_type"]:checked').value;
            const barcode = codeType === 'qr' ? createQrPreview(left, top, preset, scale) : createBarcodePreview(left, top, preset, scale);
            const text = document.createElement('div'); text.className = 'placeholder-text'; text.textContent = 'CODE ARTICLE';
            const textTop = codeType === 'qr' ? barcode.dataset.textTop : (top + preset.barcode.yMm + preset.barcode.heightMm + preset.barcode.textGapMm) * scale;
            const textLeft = codeType === 'qr' ? barcode.dataset.textLeft : (left + preset.barcode.xMm) * scale;
            const textWidth = codeType === 'qr' ? barcode.dataset.textWidth : preset.barcode.widthMm * scale;
            text.style.cssText = `left:${textLeft}px;top:${textTop}px;width:${textWidth}px;height:${preset.barcode.textHeightMm * scale}px`;
            preview.append(guide, barcode, text);
        }
    }
    document.querySelector('#metric-slots').textContent = preset.labelsPerSheet;
    document.querySelector('#metric-size').textContent = `${preset.displayWidthMm} x ${preset.displayHeightMm} mm`;
    document.querySelector('#preview-mode').textContent = `${preset.displayWidthMm} x ${preset.displayHeightMm} mm`;
    document.querySelector('#preset-warning').hidden = preset.id !== '38x21_2';
}

function createBarcodePreview(left, top, preset, scale) {
    const barcode = document.createElement('div');
    barcode.className = 'placeholder';
    barcode.style.cssText = `left:${(left + preset.barcode.xMm) * scale}px;top:${(top + preset.barcode.yMm) * scale}px;width:${preset.barcode.widthMm * scale}px;height:${preset.barcode.heightMm * scale}px`;
    return barcode;
}

function createQrPreview(left, top, preset, scale) {
    let sizeMm = Math.min(preset.labelWidthMm - 2, preset.labelHeightMm - 1 - preset.barcode.textHeightMm - preset.barcode.textGapMm);

    if (preset.id === '70x37') {
        sizeMm = Math.min(sizeMm, 24);
    }

    const horizontal = preset.id === '52_5x29_7';
    if (horizontal) sizeMm = Math.min(preset.labelHeightMm - 4, preset.labelWidthMm - 4);

    const sizePx = sizeMm * scale;
    const qr = document.createElement('div');
    qr.className = 'qr-preview';
    const qrLeft = horizontal ? left + 2 : left + ((preset.labelWidthMm - sizeMm) / 2);
    const qrTop = horizontal ? top + ((preset.labelHeightMm - sizeMm) / 2) : top + 0.5;
    qr.style.cssText = `left:${qrLeft * scale}px;top:${qrTop * scale}px;width:${sizePx}px;height:${sizePx}px;position:absolute`;
    for (let row = 0; row < 29; row += 1) {
        for (let column = 0; column < 29; column += 1) {
            const module = document.createElement('span');
            module.className = 'qr-module';
            if (isQrPreviewDark(row, column)) module.classList.add('on');
            qr.append(module);
        }
    }
    if (horizontal) {
        const textLeft = qrLeft + sizeMm + 2;
        const textTop = top + ((preset.labelHeightMm - 10) / 2);
        qr.dataset.textTop = `${textTop * scale}`;
        qr.dataset.textLeft = `${textLeft * scale}`;
        qr.dataset.textWidth = `${(preset.labelWidthMm - textLeft + left - 2) * scale}`;
        const emplacement = document.createElement('span');
        emplacement.className = 'qr-emplacement-preview';
        emplacement.style.cssText = `left:${(textLeft - qrLeft) * scale}px;top:${(textTop - qrTop + 5) * scale}px;width:${(preset.labelWidthMm - textLeft + left - 2) * scale}px`;
        qr.append(emplacement);
    } else {
        qr.dataset.textTop = `${(top + 0.5 + sizeMm + preset.barcode.textGapMm) * scale}`;
        qr.dataset.textLeft = `${left * scale}`;
        qr.dataset.textWidth = `${preset.labelWidthMm * scale}`;
    }
    return qr;
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

document.querySelector('#choose-file-button').addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); fileInput.click(); });
document.querySelector('#change-file-button').addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); fileInput.click(); });
document.querySelector('#choose-file-button').addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput.click(); } });
document.querySelector('#change-file-button').addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); fileInput.click(); } });
fileInput.addEventListener('change', () => setFile(fileInput.files[0]));
['dragenter', 'dragover'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); }));
['dragleave', 'drop'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => { event.preventDefault(); dropzone.classList.remove('is-dragging'); }));
dropzone.addEventListener('drop', (event) => setFile(event.dataTransfer.files[0]));
presetSelect.addEventListener('change', renderPreview);
codeTypeInputs.forEach((input) => input.addEventListener('change', renderPreview));
new ResizeObserver(renderPreview).observe(preview);
window.addEventListener('resize', renderPreview);
form.addEventListener('submit', (event) => { if (columnSelect.disabled || !columnSelect.value) { event.preventDefault(); columnSelect.focus(); } });
document.querySelector('#code-type-qr').checked = true;
renderPreview();
