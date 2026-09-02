import { createIcons, Barcode, Download, FileSpreadsheet, Printer, TriangleAlert, Upload } from 'lucide';

const presets = Object.fromEntries((window.BarcodePresets || []).map((preset) => [preset.id, preset]));
const app = document.querySelector('.app');
const form = document.querySelector('#upload-form');
const fileInput = document.querySelector('#excel_file');
const dropzone = document.querySelector('#dropzone');
const columnSelect = document.querySelector('#excel_column');
const presetSelect = document.querySelector('#preset-selector');
const preview = document.querySelector('#a4-preview');

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
    const preset = presets[presetSelect.value] || presets['70x37'];
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
            const barcode = document.createElement('div'); barcode.className = 'placeholder'; barcode.style.cssText = `left:${(left + preset.barcode.xMm) * scale}px;top:${(top + preset.barcode.yMm) * scale}px;width:${preset.barcode.widthMm * scale}px;height:${preset.barcode.heightMm * scale}px`;
            const text = document.createElement('div'); text.className = 'placeholder-text'; text.textContent = 'CODE ARTICLE'; text.style.cssText = `left:${(left + preset.barcode.xMm) * scale}px;top:${(top + preset.barcode.yMm + preset.barcode.heightMm + preset.barcode.textGapMm) * scale}px;width:${preset.barcode.widthMm * scale}px;height:${preset.barcode.textHeightMm * scale}px`;
            preview.append(guide, barcode, text);
        }
    }
    document.querySelector('#metric-slots').textContent = preset.labelsPerSheet;
    document.querySelector('#metric-size').textContent = `${preset.displayWidthMm} x ${preset.displayHeightMm} mm`;
    document.querySelector('#preview-mode').textContent = `${preset.displayWidthMm} x ${preset.displayHeightMm} mm`;
    document.querySelector('#preset-warning').hidden = preset.id !== '38x21_2';
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
new ResizeObserver(renderPreview).observe(preview);
window.addEventListener('resize', renderPreview);
form.addEventListener('submit', (event) => { if (columnSelect.disabled || !columnSelect.value) { event.preventDefault(); columnSelect.focus(); } });
renderPreview();
