import './bootstrap';
import {
    ArrowLeft,
    Barcode,
    Check,
    CircleCheck,
    Copy,
    Download,
    FileSpreadsheet,
    Grid3X3,
    Info,
    Magnet,
    Maximize2,
    Minimize2,
    Plus,
    Printer,
    Save,
    ScanBarcode,
    Settings,
    SquarePen,
    Trash2,
    TriangleAlert,
    Upload,
    X,
    createIcons,
} from 'lucide';
import Moveable from 'moveable';

const defaults = window.BarcodeDesignerDefaults;
const lucideIcons = {
    ArrowLeft,
    Barcode,
    Check,
    CircleCheck,
    Copy,
    Download,
    FileSpreadsheet,
    Grid3X3,
    Info,
    Magnet,
    Maximize2,
    Minimize2,
    Plus,
    Printer,
    Save,
    ScanBarcode,
    Settings,
    SquarePen,
    Trash2,
    TriangleAlert,
    Upload,
    X,
};

function renderIcons(root = document) {
    try {
        createIcons({
            attrs: {
                'aria-hidden': 'true',
                'stroke-width': 2,
            },
            icons: lucideIcons,
            nameAttr: 'data-lucide',
            root,
        });
    } catch (error) {
        console.warn('Lucide icons could not be rendered.', error);
    }
}

if (defaults) {
    const pageWidth = 210;
    const pageHeight = 297;
    const minBarcodeWidth = 40;
    const minBarcodeHeight = 18;
    const textGap = 0.25;
    const textHeight = 4.6;
    const barcodeOffsetX = 6.75;
    const barcodeOffsetY = 1;
    const bottomSafe = 4.325;

    const form = document.getElementById('upload-form');
    const fileInput = document.getElementById('excel_file');
    const dropzone = document.getElementById('dropzone');
    const chooseFileButton = document.getElementById('choose-file-button');
    const changeFileButton = document.getElementById('change-file-button');
    const fileName = document.getElementById('file-name');
    const submitButton = document.getElementById('submit-button');
    const submitLabel = document.getElementById('submit-label');
    const hiddenLayout = document.getElementById('layout_json');
    const layoutError = document.getElementById('layout-error');
    const designerError = document.getElementById('designer-error');
    const metricSlots = document.getElementById('metric-slots');
    const metricSize = document.getElementById('metric-size');
    const previewMode = document.getElementById('preview-mode');
    const previewBoard = document.getElementById('a4-preview');
    const designerModal = document.getElementById('designer-modal');
    const designerWorkspace = document.getElementById('designer-workspace');
    const designerBoard = document.getElementById('designer-board');
    const openDesigner = document.getElementById('open-designer');
    const saveDesigner = document.getElementById('designer-save');
    const addDesigner = document.getElementById('designer-add');
    const duplicateDesigner = document.getElementById('designer-duplicate');
    const deleteDesigner = document.getElementById('designer-delete');
    const fullscreenDesigner = document.getElementById('designer-fullscreen');
    const fullscreenDesignerLabel = document.getElementById('designer-fullscreen-label');
    const snapDesigner = document.getElementById('designer-snap');
    const alignDesigner = document.getElementById('designer-align');
    const fields = [...document.querySelectorAll('.layout-field')];

    let guides = { ...defaults.guides };
    let mode = 'quick';
    let customElements = defaults.elements.map((element) => ({ ...element }));
    let designerDraft = customElements.map((element) => ({ ...element }));
    let selectedId = null;
    let ignoreFieldChanges = false;
    let previewFrame = null;

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return value.replace(/["\\]/g, '\\$&');
    }

    const moveable = new Moveable(designerWorkspace, {
        target: null,
        container: designerWorkspace,
        rootContainer: designerWorkspace,
        className: 'designer-moveable',
        draggable: true,
        resizable: true,
        renderDirections: ['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se'],
        keepRatio: false,
        origin: false,
        edge: false,
        rotatable: false,
        throttleDrag: 0,
        throttleResize: 0,
        snappable: true,
        snapGap: true,
        snapGridWidth: 8,
        snapGridHeight: 8,
    });

    function mmToPx(mm, board) {
        return (mm * board.clientWidth) / pageWidth;
    }

    function pxToMm(px, board) {
        return (px * pageWidth) / board.clientWidth;
    }

    function roundMm(value) {
        return Math.round(value * 10000) / 10000;
    }

    function parseNumber(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : NaN;
    }

    function readGuidesFromFields() {
        const next = {};

        fields.forEach((field) => {
            const key = field.dataset.key;
            const value = parseNumber(field.value);
            next[key] = key === 'columns' || key === 'rows' ? Math.trunc(value) : value;
        });

        return next;
    }

    function writeGuidesToFields(nextGuides) {
        ignoreFieldChanges = true;
        fields.forEach((field) => {
            field.value = nextGuides[field.dataset.key] ?? 0;
        });
        ignoreFieldChanges = false;
    }

    function calculateMetrics(nextGuides) {
        const columns = Math.trunc(nextGuides.columns);
        const rows = Math.trunc(nextGuides.rows);
        const gapX = Number(nextGuides.gapXMm);
        const gapY = Number(nextGuides.gapYMm);
        const marginTop = Number(nextGuides.marginTopMm);
        const marginRight = Number(nextGuides.marginRightMm);
        const marginBottom = Number(nextGuides.marginBottomMm);
        const marginLeft = Number(nextGuides.marginLeftMm);

        if (!Number.isInteger(columns) || columns < 1 || columns > 50) {
            throw new Error('Nombre de colonnes invalide.');
        }

        if (!Number.isInteger(rows) || rows < 1 || rows > 80) {
            throw new Error('Nombre de lignes invalide.');
        }

        for (const value of [gapX, gapY, marginTop, marginRight, marginBottom, marginLeft]) {
            if (!Number.isFinite(value) || value < 0) {
                throw new Error('Les espacements et marges doivent etre positifs.');
            }
        }

        const availableWidth = pageWidth - marginLeft - marginRight;
        const availableHeight = pageHeight - marginTop - marginBottom;
        const labelWidth = (availableWidth - gapX * (columns - 1)) / columns;
        const labelHeight = (availableHeight - gapY * (rows - 1)) / rows;

        if (labelWidth <= 0 || labelHeight <= 0) {
            throw new Error('La zone etiquette calculee est invalide.');
        }

        return {
            ...nextGuides,
            columns,
            rows,
            gapXMm: gapX,
            gapYMm: gapY,
            marginTopMm: marginTop,
            marginRightMm: marginRight,
            marginBottomMm: marginBottom,
            marginLeftMm: marginLeft,
            labelWidthMm: roundMm(labelWidth),
            labelHeightMm: roundMm(labelHeight),
            slotsPerPage: columns * rows,
        };
    }

    function buildQuickElements(nextGuides) {
        const elements = [];
        const width = roundMm(nextGuides.labelWidthMm - barcodeOffsetX * 2);
        const height = roundMm(nextGuides.labelHeightMm - barcodeOffsetY - textHeight - bottomSafe);

        for (let row = 0; row < nextGuides.rows; row += 1) {
            for (let column = 0; column < nextGuides.columns; column += 1) {
                const slotX = nextGuides.marginLeftMm + column * (nextGuides.labelWidthMm + nextGuides.gapXMm);
                const slotY = nextGuides.marginTopMm + row * (nextGuides.labelHeightMm + nextGuides.gapYMm);

                elements.push({
                    id: `barcode-${row}-${column}`,
                    type: 'barcode',
                    xMm: roundMm(slotX + barcodeOffsetX),
                    yMm: roundMm(slotY + barcodeOffsetY),
                    widthMm: width,
                    heightMm: height,
                });
            }
        }

        return elements;
    }

    function orderedElements(elements) {
        return [...elements].sort((a, b) => (a.yMm - b.yMm) || (a.xMm - b.xMm));
    }

    function validateElements(elements) {
        if (!elements.length) {
            throw new Error('Ajoutez au moins un code-barres.');
        }

        if (elements.length > 400) {
            throw new Error('La mise en page contient trop de codes-barres.');
        }

        elements.forEach((element) => {
            if (element.type !== 'barcode') {
                throw new Error('Element non pris en charge.');
            }

            const values = [element.xMm, element.yMm, element.widthMm, element.heightMm];
            if (values.some((value) => !Number.isFinite(Number(value)))) {
                throw new Error('Coordonnees invalides.');
            }

            if (element.xMm < 0 || element.yMm < 0 || element.widthMm <= 0 || element.heightMm <= 0) {
                throw new Error('Coordonnees invalides.');
            }

            if (element.widthMm < minBarcodeWidth || element.heightMm < minBarcodeHeight) {
                throw new Error('Un code-barres est trop petit pour une lecture fiable.');
            }

            if (element.xMm + element.widthMm > pageWidth || element.yMm + element.heightMm + textGap + textHeight > pageHeight) {
                throw new Error('Un code-barres depasse la page A4.');
            }
        });
    }

    function currentElements() {
        return mode === 'quick' ? buildQuickElements(guides) : orderedElements(customElements);
    }

    function currentLayout() {
        return {
            mode,
            page: {
                widthMm: pageWidth,
                heightMm: pageHeight,
                orientation: 'portrait',
            },
            guides,
            elements: orderedElements(currentElements()),
        };
    }

    function syncHidden() {
        const layout = currentLayout();
        hiddenLayout.value = JSON.stringify(layout);

        return layout;
    }

    function styleElement(node, element, board) {
        node.style.left = `${mmToPx(element.xMm, board)}px`;
        node.style.top = `${mmToPx(element.yMm, board)}px`;
        node.style.width = `${mmToPx(element.widthMm, board)}px`;
        node.style.height = `${mmToPx(element.heightMm + textGap + textHeight, board)}px`;
    }

    function renderGuides(board) {
        const fragment = document.createDocumentFragment();

        for (let row = 0; row < guides.rows; row += 1) {
            for (let column = 0; column < guides.columns; column += 1) {
                const cell = document.createElement('div');
                cell.className = 'guide-cell';
                const x = guides.marginLeftMm + column * (guides.labelWidthMm + guides.gapXMm);
                const y = guides.marginTopMm + row * (guides.labelHeightMm + guides.gapYMm);
                cell.style.left = `${mmToPx(x, board)}px`;
                cell.style.top = `${mmToPx(y, board)}px`;
                cell.style.width = `${mmToPx(guides.labelWidthMm, board)}px`;
                cell.style.height = `${mmToPx(guides.labelHeightMm, board)}px`;
                fragment.appendChild(cell);
            }
        }

        return fragment;
    }

    function barcodeNode(element, board, index = null) {
        const node = document.createElement('div');
        node.className = 'barcode-preview';
        node.dataset.id = element.id;
        styleElement(node, element, board);
        node.style.gridTemplateRows = `${mmToPx(element.heightMm, board)}px ${Math.max(6, mmToPx(textHeight, board))}px`;
        node.style.gap = `${mmToPx(textGap, board)}px`;

        const bars = document.createElement('div');
        bars.className = 'bars';
        bars.style.height = `${mmToPx(element.heightMm, board)}px`;

        const text = document.createElement('div');
        text.className = 'code-text';
        text.textContent = 'CODE ARTICLE';

        node.appendChild(bars);
        node.appendChild(text);

        if (index !== null) {
            const badge = document.createElement('span');
            badge.className = 'order-badge';
            badge.textContent = String(index + 1);
            node.appendChild(badge);
        }

        return node;
    }

    function renderPreview() {
        if (!previewBoard || previewBoard.clientWidth <= 0) {
            schedulePreviewRender();
            return;
        }

        previewBoard.replaceChildren(renderGuides(previewBoard));
        currentElements().forEach((element) => {
            previewBoard.appendChild(barcodeNode(element, previewBoard));
        });

        previewMode.textContent = mode === 'quick' ? 'Disposition rapide' : 'Disposition personnalisee';
    }

    function schedulePreviewRender() {
        if (previewFrame !== null) {
            cancelAnimationFrame(previewFrame);
        }

        previewFrame = requestAnimationFrame(() => {
            previewFrame = null;
            if (previewBoard && previewBoard.clientWidth > 0) {
                renderPreview();
            }
        });
    }

    function renderDesigner() {
        fitDesignerBoard();
        designerBoard.replaceChildren(renderGuides(designerBoard));
        orderedElements(designerDraft).forEach((element, index) => {
            const node = barcodeNode(element, designerBoard, index);
            node.classList.add('designer-element');
            node.classList.toggle('is-selected', element.id === selectedId);
            node.addEventListener('pointerdown', () => {
                if (selectedId !== element.id) {
                    selectElement(element.id);
                }
            });
            designerBoard.appendChild(node);
        });

        renderFloatingToolbar();
        refreshMoveable();
    }

    function refreshMoveable() {
        const target = selectedId ? designerBoard.querySelector(`[data-id="${cssEscape(selectedId)}"]`) : null;
        moveable.target = target;
        moveable.snapContainer = designerBoard;
        moveable.container = designerWorkspace;
        moveable.rootContainer = designerWorkspace;
        moveable.bounds = {
            left: designerBoard.getBoundingClientRect().left,
            top: designerBoard.getBoundingClientRect().top,
            right: designerBoard.getBoundingClientRect().right,
            bottom: designerBoard.getBoundingClientRect().bottom,
        };
        moveable.snappable = snapDesigner.checked || alignDesigner.checked;
        moveable.snapGap = alignDesigner.checked;
        moveable.isDisplayGridGuidelines = snapDesigner.checked;
        moveable.snapGridWidth = mmToPx(guides.labelWidthMm || 10, designerBoard);
        moveable.snapGridHeight = mmToPx(guides.labelHeightMm || 10, designerBoard);
        moveable.updateTarget?.();
        moveable.updateRect();
        syncDesignerButtons();
    }

    function renderFloatingToolbar() {
        designerBoard.querySelectorAll('.floating-element-toolbar').forEach((toolbar) => toolbar.remove());

        const selected = selectedId ? designerDraft.find((element) => element.id === selectedId) : null;
        if (!selected) {
            return;
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'floating-element-toolbar';
        toolbar.innerHTML = `
            <button type="button" title="Dupliquer" aria-label="Dupliquer" data-floating-action="duplicate">
                <i data-lucide="Copy"></i>
            </button>
            <button type="button" title="Supprimer" aria-label="Supprimer" data-floating-action="delete">
                <i data-lucide="Trash2"></i>
            </button>
        `;

        const toolbarWidth = 68;
        const toolbarHeight = 38;
        const gap = 8;
        const elementX = mmToPx(selected.xMm, designerBoard);
        const elementY = mmToPx(selected.yMm, designerBoard);
        const elementWidth = mmToPx(selected.widthMm, designerBoard);
        const elementTotalHeight = mmToPx(selected.heightMm + textGap + textHeight, designerBoard);
        const left = Math.max(4, Math.min(elementX + (elementWidth - toolbarWidth) / 2, designerBoard.clientWidth - toolbarWidth - 4));
        const aboveTop = elementY - toolbarHeight - gap;
        const belowTop = elementY + elementTotalHeight + gap;
        const top = aboveTop >= 4
            ? aboveTop
            : Math.min(belowTop, designerBoard.clientHeight - toolbarHeight - 4);

        toolbar.style.left = `${left}px`;
        toolbar.style.top = `${Math.max(4, top)}px`;
        toolbar.addEventListener('pointerdown', (event) => event.stopPropagation());
        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-floating-action]');
            if (!button) {
                return;
            }

            if (button.dataset.floatingAction === 'duplicate') {
                duplicateElement();
            } else {
                deleteElement();
            }
        });

        designerBoard.appendChild(toolbar);
        renderIcons(toolbar);
    }

    function fitDesignerBoard() {
        const visualMargin = 40;
        const availableWidth = Math.max(180, designerWorkspace.clientWidth - visualMargin);
        const availableHeight = Math.max(280, designerWorkspace.clientHeight - visualMargin);
        const heightFromWidth = availableWidth * (pageHeight / pageWidth);
        const boardHeight = Math.min(availableHeight, heightFromWidth);
        const boardWidth = boardHeight * (pageWidth / pageHeight);

        designerBoard.style.setProperty('--designer-board-width', `${boardWidth}px`);
        designerBoard.style.setProperty('--designer-board-height', `${boardHeight}px`);
    }

    function syncDesignerButtons() {
        const hasSelection = Boolean(selectedId && designerDraft.some((element) => element.id === selectedId));
        duplicateDesigner.disabled = !hasSelection;
        deleteDesigner.disabled = !hasSelection;
        const fullscreenLabel = document.fullscreenElement ? 'Quitter plein ecran' : 'Plein ecran';
        fullscreenDesignerLabel.textContent = fullscreenLabel;
        fullscreenDesigner.title = fullscreenLabel;
        fullscreenDesigner.setAttribute('aria-label', fullscreenLabel);
        fullscreenDesigner.querySelector('[data-lucide], svg')?.setAttribute('data-lucide', document.fullscreenElement ? 'Minimize2' : 'Maximize2');
        renderIcons(fullscreenDesigner);
    }

    function selectElement(id) {
        selectedId = id;
        renderDesigner();
    }

    function deselectElement() {
        if (!selectedId) {
            return;
        }

        selectedId = null;
        renderDesigner();
    }

    function updateElementFromTarget(target) {
        const element = designerDraft.find((item) => item.id === target.dataset.id);
        if (!element) {
            return;
        }

        const left = Math.max(0, Math.min(target.offsetLeft, designerBoard.clientWidth - target.offsetWidth));
        const top = Math.max(0, Math.min(target.offsetTop, designerBoard.clientHeight - target.offsetHeight));
        const width = Math.max(mmToPx(minBarcodeWidth, designerBoard), Math.min(target.offsetWidth, designerBoard.clientWidth - left));
        const totalHeight = Math.max(
            mmToPx(minBarcodeHeight + textGap + textHeight, designerBoard),
            Math.min(target.offsetHeight, designerBoard.clientHeight - top),
        );

        element.xMm = roundMm(pxToMm(left, designerBoard));
        element.yMm = roundMm(pxToMm(top, designerBoard));
        element.widthMm = roundMm(pxToMm(width, designerBoard));
        element.heightMm = roundMm(pxToMm(totalHeight, designerBoard) - textGap - textHeight);

        styleElement(target, element, designerBoard);
        target.querySelector('.bars').style.height = `${mmToPx(element.heightMm, designerBoard)}px`;
        renderFloatingToolbar();
        refreshMoveable();
    }

    moveable.on('drag', ({ target, left, top }) => {
        const maxLeft = designerBoard.clientWidth - target.offsetWidth;
        const maxTop = designerBoard.clientHeight - target.offsetHeight;
        target.style.left = `${Math.max(0, Math.min(left, maxLeft))}px`;
        target.style.top = `${Math.max(0, Math.min(top, maxTop))}px`;
        updateElementFromTarget(target);
    });

    moveable.on('resize', ({ target, width, height, drag }) => {
        if (drag) {
            target.style.left = `${drag.left}px`;
            target.style.top = `${drag.top}px`;
        }

        target.style.width = `${width}px`;
        target.style.height = `${height}px`;
        updateElementFromTarget(target);
    });

    function addElement() {
        const element = {
            id: `barcode-${Date.now()}`,
            type: 'barcode',
            xMm: barcodeOffsetX,
            yMm: barcodeOffsetY,
            widthMm: defaults.elements[0].widthMm,
            heightMm: defaults.elements[0].heightMm,
        };

        designerDraft.push(element);
        selectedId = element.id;
        renderDesigner();
    }

    function duplicateElement() {
        const selected = designerDraft.find((item) => item.id === selectedId);
        if (!selected) {
            return;
        }

        const clone = {
            ...selected,
            id: `barcode-${Date.now()}`,
            xMm: roundMm(Math.min(pageWidth - selected.widthMm, selected.xMm + 4)),
            yMm: roundMm(Math.min(pageHeight - selected.heightMm - textGap - textHeight, selected.yMm + 4)),
        };

        designerDraft.push(clone);
        selectedId = clone.id;
        renderDesigner();
    }

    function deleteElement() {
        if (!selectedId) {
            return;
        }

        designerDraft = designerDraft.filter((item) => item.id !== selectedId);
        selectedId = designerDraft[0]?.id ?? null;
        renderDesigner();
    }

    function applyLayout() {
        try {
            guides = calculateMetrics(readGuidesFromFields());
            const elements = currentElements();
            validateElements(elements);
            metricSlots.textContent = String(guides.slotsPerPage);
            metricSize.textContent = `${guides.labelWidthMm} x ${guides.labelHeightMm} mm`;
            layoutError.textContent = '';
            submitButton.disabled = false;
            syncHidden();
            schedulePreviewRender();
        } catch (error) {
            layoutError.textContent = error.message;
            submitButton.disabled = true;
        }
    }

    function openDesignerModal() {
        designerDraft = currentElements().map((element) => ({ ...element }));
        selectedId = designerDraft[0]?.id ?? null;
        designerError.textContent = '';
        designerModal.classList.add('is-open');
        designerModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('designer-open');
        requestAnimationFrame(renderDesigner);
    }

    function closeDesignerModal() {
        designerModal.classList.remove('is-open');
        designerModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('designer-open');
        moveable.target = null;

        if (document.fullscreenElement === designerModal) {
            document.exitFullscreen();
        }
    }

    fields.forEach((field) => {
        field.addEventListener('input', () => {
            if (ignoreFieldChanges) {
                return;
            }

            if (mode === 'custom' && !window.confirm('Modifier cette disposition reinitialisera votre mise en page personnalisee. Continuer ?')) {
                writeGuidesToFields(guides);
                return;
            }

            mode = 'quick';
            applyLayout();
        });
    });

    openDesigner.addEventListener('click', openDesignerModal);
    addDesigner.addEventListener('click', addElement);
    duplicateDesigner.addEventListener('click', duplicateElement);
    deleteDesigner.addEventListener('click', deleteElement);
    snapDesigner.addEventListener('change', refreshMoveable);
    alignDesigner.addEventListener('change', refreshMoveable);

    designerBoard.addEventListener('pointerdown', (event) => {
        if (event.target.closest('.designer-element') || event.target.closest('.floating-element-toolbar')) {
            return;
        }

        deselectElement();
    });

    fullscreenDesigner.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await designerModal.requestFullscreen();
            }
        } finally {
            requestAnimationFrame(renderDesigner);
        }
    });

    designerWorkspace.addEventListener('scroll', () => {
        moveable.updateRect();
    });

    document.addEventListener('fullscreenchange', () => {
        syncDesignerButtons();
        requestAnimationFrame(renderDesigner);
    });

    document.addEventListener('keydown', (event) => {
        if (!designerModal.classList.contains('is-open')) {
            return;
        }

        const editable = event.target instanceof HTMLElement
            && event.target.matches('input, textarea, select, [contenteditable="true"]');
        if (editable) {
            return;
        }

        if (event.key === 'Escape' && !document.fullscreenElement) {
            event.preventDefault();
            deselectElement();
        }

        if ((event.key === 'Delete' || event.key === 'Backspace') && selectedId) {
            event.preventDefault();
            deleteElement();
        }
    });

    saveDesigner.addEventListener('click', () => {
        try {
            validateElements(designerDraft);
            customElements = orderedElements(designerDraft).map((element) => ({ ...element }));
            mode = 'custom';
            designerError.textContent = '';
            closeDesignerModal();
            applyLayout();
        } catch (error) {
            designerError.textContent = error.message;
        }
    });

    document.querySelectorAll('[data-close-designer]').forEach((button) => {
        button.addEventListener('click', closeDesignerModal);
    });

    function openFilePicker() {
        fileInput.click();
    }

    function openFilePickerFromKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        openFilePicker();
    }

    function showSelectedFile() {
        const file = fileInput.files && fileInput.files[0];
        dropzone.classList.toggle('has-file', Boolean(file));
        fileName.textContent = file ? file.name : '';
        fileName.title = file ? file.name : '';
    }

    fileInput.addEventListener('change', showSelectedFile);
    chooseFileButton.addEventListener('click', (event) => {
        event.preventDefault();
        openFilePicker();
    });
    changeFileButton.addEventListener('click', (event) => {
        event.preventDefault();
        openFilePicker();
    });
    chooseFileButton.addEventListener('keydown', openFilePickerFromKeyboard);
    changeFileButton.addEventListener('keydown', openFilePickerFromKeyboard);

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        if (!event.dataTransfer.files.length) {
            return;
        }

        fileInput.files = event.dataTransfer.files;
        showSelectedFile();
    });

    form.addEventListener('submit', (event) => {
        applyLayout();

        if (submitButton.disabled) {
            event.preventDefault();
            return;
        }

        submitButton.disabled = true;
        form.classList.add('is-submitting');
        submitLabel.textContent = 'Generation en cours...';
    });

    writeGuidesToFields(guides);
    applyLayout();
    renderIcons();

    if ('ResizeObserver' in window) {
        new ResizeObserver(schedulePreviewRender).observe(previewBoard);
    }

    window.addEventListener('resize', () => {
        schedulePreviewRender();
        if (designerModal.classList.contains('is-open')) {
            renderDesigner();
        }
    });
}
