import { BrowserMultiFormatReader } from '@zxing/browser';
import { Camera, CheckCircle2, ChevronDown, CircleAlert, createIcons, Flashlight, FlashlightOff, Keyboard, Minus, Pencil, Plus, RefreshCw, Save, Trash2, X } from 'lucide';

const app = document.querySelector('.app');
const video = document.querySelector('#camera-video');
const cameraFrame = document.querySelector('.camera-frame');
const scanGuide = document.querySelector('.scan-guide');
const torchButton = document.querySelector('#torch-toggle');
const form = document.querySelector('#item-form');
const codeInput = document.querySelector('#code_article');
const detectedPanel = document.querySelector('#detected-panel');
const detectedCode = document.querySelector('#detected-code');
const detectedQuantity = document.querySelector('#detected-quantity');
const duplicatePanel = document.querySelector('#duplicate-panel');
const message = document.querySelector('#message');
const cameraStatus = document.querySelector('#camera-status');
const startButton = document.querySelector('#start-camera');
const retryButton = document.querySelector('#retry-camera');
const manualToggle = document.querySelector('#manual-toggle');
const manualEntry = document.querySelector('#manual-entry');
const manualSave = document.querySelector('#manual-save');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const toast = document.querySelector('#action-toast');
const toastIcon = document.querySelector('#action-toast-icon');
const toastMessage = document.querySelector('#action-toast-message');
const toastProgress = document.querySelector('#action-toast-progress');

const READY = 'READY';
const DETECTED = 'DETECTED';
const SAVING = 'SAVING';
let scannerState = READY;
let mediaStream = null;
let nativeDetector = null;
let nativeFrame = 0;
let zxingTimer = null;
let zxingReader = null;
let zxingCanvas = null;
let zxingBusy = false;
let scanCandidateCode = null;
let scanCandidateCount = 0;
let scanCandidateSeenAt = 0;
let pendingCode = null;
let pendingDuplicate = null;
let freezeTimer = null;
let scanAudioContext = null;
let torchSupported = false;
let torchEnabled = false;
let toastTimer = null;
let toastFrame = null;
let quantityFocusTimer = null;
const SCAN_FREEZE_MS = 1800;
const TOAST_DURATION_MS = 2800;
const SCAN_CONFIRMATIONS = 2;
const SCAN_CONFIRM_WINDOW_MS = 650;
const SCAN_MIN_OVERLAP = 0.45;
const ZXING_SCAN_DELAY_MS = 80;

createIcons({ icons: { Camera, ChevronDown, Keyboard, Minus, Pencil, Plus, RefreshCw, Save, Trash2, X } });

function syncQuantityModalViewport() {
    if (!detectedPanel || detectedPanel.hidden) return;

    const viewport = window.visualViewport;

    if (!viewport) {
        detectedPanel.style.removeProperty('top');
        detectedPanel.style.removeProperty('height');
        return;
    }

    detectedPanel.style.top = `${viewport.offsetTop}px`;
    detectedPanel.style.height = `${viewport.height}px`;
}

function clearQuantityFocusTimer() {
    if (!quantityFocusTimer) return;

    clearTimeout(quantityFocusTimer);
    quantityFocusTimer = null;
}

function focusQuantityInput() {
    if (!detectedQuantity || !detectedPanel || detectedPanel.hidden) return;

    clearQuantityFocusTimer();

    requestAnimationFrame(() => {
        if (detectedPanel.hidden) return;

        syncQuantityModalViewport();

        try {
            detectedQuantity.focus({ preventScroll: true });
            detectedQuantity.select();
        } catch (error) {
            detectedQuantity.focus();
        }

        quantityFocusTimer = window.setTimeout(() => {
            quantityFocusTimer = null;

            if (detectedPanel.hidden) return;

            syncQuantityModalViewport();

            detectedQuantity.scrollIntoView({
                block: 'center',
                inline: 'nearest',
                behavior: 'smooth',
            });
        }, 120);
    });
}

function adjustQuantity(input, amount) {
    if (!input) return;
    const value = Number.parseInt(input.value, 10) || 0;
    input.value = String(Math.max(0, value + amount));
}

function setMessage(text, error = false) {
    if (!message) return;
    message.textContent = text;
    message.classList.toggle('error', error);
}

function hideToast() {
    if (!toast) return;

    if (toastTimer) {
        clearTimeout(toastTimer);
        toastTimer = null;
    }

    if (toastFrame) {
        cancelAnimationFrame(toastFrame);
        toastFrame = null;
    }

    toast.classList.remove('show');
}

function showToast(text, type = 'success', duration = TOAST_DURATION_MS) {
    if (!toast || !toastMessage || !toastProgress || !toastIcon) return;

    hideToast();

    toast.classList.remove('success', 'info', 'danger');
    toast.classList.add(type);

    toastMessage.textContent = text;

    const icon = type === 'danger' ? 'CircleAlert' : 'CheckCircle2';
    toastIcon.innerHTML = `<i data-lucide="${icon}"></i>`;

    createIcons({ icons: { CheckCircle2, CircleAlert } });

    toastProgress.style.transition = 'none';
    toastProgress.style.transform = 'scaleX(1)';

    // Force the browser to paint the full bar before starting the countdown.
    void toastProgress.offsetWidth;

    toast.classList.add('show');

    toastFrame = requestAnimationFrame(() => {
        toastProgress.style.transition = `transform ${duration}ms linear`;
        toastProgress.style.transform = 'scaleX(0)';
        toastFrame = null;
    });

    toastTimer = window.setTimeout(() => {
        toast.classList.remove('show');
        toastTimer = null;
    }, duration);
}
function setCameraStatus(text) {
    if (cameraStatus) cameraStatus.textContent = text;
}

function primeScanAudio() {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    try {
        if (!scanAudioContext) scanAudioContext = new AudioContextClass();
        if (scanAudioContext.state === 'suspended') {
            scanAudioContext.resume().catch(() => {});
        }
    } catch (error) {
        // Scan sound is optional and must never block scanning.
    }
}

function playScanBeep() {
    try {
        primeScanAudio();
        if (!scanAudioContext || scanAudioContext.state !== 'running') return;

        const now = scanAudioContext.currentTime;
        const oscillator = scanAudioContext.createOscillator();
        const gain = scanAudioContext.createGain();

        oscillator.type = 'square';

        // Short, stronger scanner-style chirp.
        oscillator.frequency.setValueAtTime(1650, now);
        oscillator.frequency.exponentialRampToValueAtTime(1150, now + 0.115);

        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.18, now + 0.004);
        gain.gain.setValueAtTime(0.18, now + 0.055);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.12);

        oscillator.connect(gain);
        gain.connect(scanAudioContext.destination);

        oscillator.start(now);
        oscillator.stop(now + 0.125);
    } catch (error) {
        // Scan sound is optional and must never block scanning.
    }
}

function resumeLiveVideo() {
    if (video && mediaStream && video.paused) {
        video.play().catch(() => {});
    }
}

function clearFreezeTimer(resumeVideo = true) {
    if (freezeTimer) {
        clearTimeout(freezeTimer);
        freezeTimer = null;
    }

    if (resumeVideo) resumeLiveVideo();
}

function freezeCameraFrame() {
    clearFreezeTimer(false);

    if (!video || !mediaStream) return;

    video.pause();

    freezeTimer = window.setTimeout(() => {
        freezeTimer = null;
        resumeLiveVideo();
    }, SCAN_FREEZE_MS);
}

function getVideoTrack() {
    return mediaStream?.getVideoTracks?.()[0] || null;
}

function renderTorchButton() {
    if (!torchButton) return;

    torchButton.hidden = !torchSupported;

    if (!torchSupported) return;

    torchButton.classList.toggle('is-on', torchEnabled);

    torchButton.innerHTML = torchEnabled
        ? '<i data-lucide="FlashlightOff"></i>'
        : '<i data-lucide="Flashlight"></i>';

    const label = torchEnabled
        ? 'Eteindre le flash'
        : 'Allumer le flash';

    torchButton.setAttribute('aria-label', label);
    torchButton.setAttribute('title', label);
    torchButton.setAttribute('aria-pressed', String(torchEnabled));

    createIcons({ icons: { Flashlight, FlashlightOff } });
}

async function configureTorch() {
    torchSupported = false;
    torchEnabled = false;

    const track = getVideoTrack();

    if (!track || typeof track.getCapabilities !== 'function') {
        renderTorchButton();
        return;
    }

    try {
        const capabilities = track.getCapabilities();

        if (!capabilities?.torch) {
            renderTorchButton();
            return;
        }

        torchSupported = true;

        // Every new camera session starts with the torch OFF.
        try {
            await track.applyConstraints({
                advanced: [{ torch: false }],
            });
        } catch (error) {
            // Some browsers expose torch capability but reject an explicit OFF.
            // The camera still remains usable.
        }

        renderTorchButton();
    } catch (error) {
        torchSupported = false;
        torchEnabled = false;
        renderTorchButton();
    }
}


async function configureCameraFocus() {
    const track = getVideoTrack();

    if (
        !track
        || typeof track.getCapabilities !== 'function'
    ) {
        return;
    }

    try {
        const capabilities = track.getCapabilities();

        const focusModes = Array.isArray(
            capabilities?.focusMode
        )
            ? capabilities.focusMode
            : [];

        if (!focusModes.includes('continuous')) {
            return;
        }

        await track.applyConstraints({
            advanced: [
                {
                    focusMode: 'continuous',
                },
            ],
        });
    } catch (error) {
        // Autofocus enhancement is optional.
        // Never block scanning if a device rejects it.
    }
}

async function toggleTorch() {
    if (!torchSupported) return;

    const track = getVideoTrack();

    if (!track) return;

    const nextState = !torchEnabled;

    try {
        await track.applyConstraints({
            advanced: [{ torch: nextState }],
        });

        torchEnabled = nextState;
        renderTorchButton();
    } catch (error) {
        torchEnabled = false;
        renderTorchButton();
        setMessage('Flash indisponible sur cet appareil.', true);
    }
}

function resetTorchState() {
    torchEnabled = false;
    torchSupported = false;

    if (torchButton) {
        torchButton.hidden = true;
        torchButton.classList.remove('is-on');
        torchButton.setAttribute('aria-pressed', 'false');
    }
}

function updateSummary(payload) {
    const itemsCount = document.querySelector('#items-count');
    const totalQuantity = document.querySelector('#total-quantity');
    if (itemsCount && payload.items_count !== undefined) itemsCount.textContent = payload.items_count;
    if (totalQuantity && payload.total_quantity !== undefined) totalQuantity.textContent = payload.total_quantity;
}

function stopDecoder() {
    if (nativeFrame) cancelAnimationFrame(nativeFrame);
    nativeFrame = 0;

    if (zxingTimer) {
        clearTimeout(zxingTimer);
        zxingTimer = null;
    }

    zxingBusy = false;
    resetScanCandidate();
}

function stopCamera() {
    stopDecoder();
    clearFreezeTimer(false);
    resetTorchState();
    if (mediaStream) mediaStream.getTracks().forEach((track) => track.stop());
    mediaStream = null;
    if (video) video.srcObject = null;
    if (cameraFrame) cameraFrame.classList.remove('camera-active');
}


function resetScanCandidate() {
    scanCandidateCode = null;
    scanCandidateCount = 0;
    scanCandidateSeenAt = 0;
}

function confirmScanCandidate(code) {
    const now = performance.now();

    if (
        scanCandidateCode === code
        && now - scanCandidateSeenAt <= SCAN_CONFIRM_WINDOW_MS
    ) {
        scanCandidateCount += 1;
    } else {
        scanCandidateCode = code;
        scanCandidateCount = 1;
    }

    scanCandidateSeenAt = now;

    if (scanCandidateCount < SCAN_CONFIRMATIONS) {
        return false;
    }

    resetScanCandidate();

    return true;
}

function expireScanCandidate() {
    if (
        scanCandidateSeenAt
        && performance.now() - scanCandidateSeenAt > SCAN_CONFIRM_WINDOW_MS
    ) {
        resetScanCandidate();
    }
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function getScanRegionInVideoPixels() {
    if (
        !video
        || !scanGuide
        || !video.videoWidth
        || !video.videoHeight
    ) {
        return null;
    }

    const videoRect = video.getBoundingClientRect();
    const guideRect = scanGuide.getBoundingClientRect();

    if (!videoRect.width || !videoRect.height) {
        return null;
    }

    /*
     * The preview uses object-fit: cover.
     * Map the visible blue guide back to the camera's original pixels,
     * including the parts cropped by object-fit.
     */
    const scale = Math.max(
        videoRect.width / video.videoWidth,
        videoRect.height / video.videoHeight
    );

    const renderedWidth = video.videoWidth * scale;
    const renderedHeight = video.videoHeight * scale;

    const offsetX = (videoRect.width - renderedWidth) / 2;
    const offsetY = (videoRect.height - renderedHeight) / 2;

    const left = clamp(
        (guideRect.left - videoRect.left - offsetX) / scale,
        0,
        video.videoWidth
    );

    const top = clamp(
        (guideRect.top - videoRect.top - offsetY) / scale,
        0,
        video.videoHeight
    );

    const right = clamp(
        (guideRect.right - videoRect.left - offsetX) / scale,
        0,
        video.videoWidth
    );

    const bottom = clamp(
        (guideRect.bottom - videoRect.top - offsetY) / scale,
        0,
        video.videoHeight
    );

    const width = right - left;
    const height = bottom - top;

    if (width < 10 || height < 10) {
        return null;
    }

    return {
        left,
        top,
        right,
        bottom,
        width,
        height,
        centerX: left + (width / 2),
        centerY: top + (height / 2),
    };
}

function getNativeBarcodeBox(result) {
    const box = result?.boundingBox;

    if (box && box.width > 0 && box.height > 0) {
        return {
            left: box.x,
            top: box.y,
            right: box.x + box.width,
            bottom: box.y + box.height,
            width: box.width,
            height: box.height,
        };
    }

    const points = Array.isArray(result?.cornerPoints)
        ? result.cornerPoints
        : [];

    if (!points.length) {
        return null;
    }

    const xs = points.map((point) => Number(point.x));
    const ys = points.map((point) => Number(point.y));

    const left = Math.min(...xs);
    const top = Math.min(...ys);
    const right = Math.max(...xs);
    const bottom = Math.max(...ys);

    if (
        !Number.isFinite(left)
        || !Number.isFinite(top)
        || !Number.isFinite(right)
        || !Number.isFinite(bottom)
        || right <= left
        || bottom <= top
    ) {
        return null;
    }

    return {
        left,
        top,
        right,
        bottom,
        width: right - left,
        height: bottom - top,
    };
}

function getBoxOverlapRatio(box, region) {
    const left = Math.max(box.left, region.left);
    const top = Math.max(box.top, region.top);
    const right = Math.min(box.right, region.right);
    const bottom = Math.min(box.bottom, region.bottom);

    const intersectionWidth = Math.max(0, right - left);
    const intersectionHeight = Math.max(0, bottom - top);
    const intersectionArea = intersectionWidth * intersectionHeight;
    const boxArea = Math.max(1, box.width * box.height);

    return intersectionArea / boxArea;
}

function pickBestNativeCandidate(results) {
    const region = getScanRegionInVideoPixels();

    if (!region) {
        return null;
    }

    const candidates = [];

    for (const result of results) {
        const code = String(result?.rawValue ?? '');

        if (!code) {
            continue;
        }

        const box = getNativeBarcodeBox(result);

        if (!box) {
            continue;
        }

        const centerX = box.left + (box.width / 2);
        const centerY = box.top + (box.height / 2);

        const centerInside = (
            centerX >= region.left
            && centerX <= region.right
            && centerY >= region.top
            && centerY <= region.bottom
        );

        if (!centerInside) {
            continue;
        }

        const overlap = getBoxOverlapRatio(box, region);

        if (overlap < SCAN_MIN_OVERLAP) {
            continue;
        }

        const distanceX = (
            centerX - region.centerX
        ) / Math.max(1, region.width / 2);

        const distanceY = (
            centerY - region.centerY
        ) / Math.max(1, region.height / 2);

        const centerDistance = Math.hypot(
            distanceX,
            distanceY
        );

        candidates.push({
            code,
            score: (overlap * 4) - centerDistance,
        });
    }

    candidates.sort((a, b) => b.score - a.score);

    return candidates[0] ?? null;
}

function ensureZxingCanvas() {
    if (!zxingCanvas) {
        zxingCanvas = document.createElement('canvas');
    }

    return zxingCanvas;
}

function scheduleZxingScan(delay = ZXING_SCAN_DELAY_MS) {
    if (
        scannerState !== READY
        || !mediaStream
        || !zxingReader
    ) {
        return;
    }

    if (zxingTimer) {
        clearTimeout(zxingTimer);
    }

    zxingTimer = window.setTimeout(() => {
        zxingTimer = null;
        zxingScan();
    }, delay);
}

function zxingScan() {
    if (
        scannerState !== READY
        || !mediaStream
        || !zxingReader
        || !video
        || video.readyState < 2
    ) {
        return;
    }

    if (zxingBusy) {
        scheduleZxingScan();
        return;
    }

    const region = getScanRegionInVideoPixels();

    if (!region) {
        scheduleZxingScan();
        return;
    }

    zxingBusy = true;

    try {
        const canvas = ensureZxingCanvas();

        const width = Math.max(
            1,
            Math.round(region.width)
        );

        const height = Math.max(
            1,
            Math.round(region.height)
        );

        if (
            canvas.width !== width
            || canvas.height !== height
        ) {
            canvas.width = width;
            canvas.height = height;
        }

        const context = canvas.getContext(
            '2d',
            {
                alpha: false,
                willReadFrequently: true,
            }
        );

        if (!context) {
            return;
        }

        context.drawImage(
            video,
            region.left,
            region.top,
            region.width,
            region.height,
            0,
            0,
            width,
            height
        );

        const result = zxingReader.decodeFromCanvas(canvas);

        if (
            result
            && confirmScanCandidate(result.getText())
        ) {
            showDetected(result.getText(), 'camera');
        }
    } catch (error) {
        expireScanCandidate();
    } finally {
        zxingBusy = false;
    }

    if (scannerState === READY) {
        scheduleZxingScan();
    }
}

function showDetected(code, source = 'manual') {
    if (scannerState !== READY || !code) return;

    scannerState = DETECTED;
    pendingCode = code;
    stopDecoder();

    if (source === 'camera') {
        playScanBeep();
        if (navigator.vibrate) navigator.vibrate(50);
        freezeCameraFrame();
    }

    detectedCode.textContent = code;
    detectedQuantity.value = '1';
    detectedPanel.hidden = false;
    document.body.classList.add('quantity-modal-open');
    syncQuantityModalViewport();
    focusQuantityInput();
    manualEntry.hidden = true;
    manualToggle.setAttribute('aria-expanded', 'false');
    setCameraStatus('Code detecte. Saisissez la quantite.');
}

async function nativeScan() {
    if (
        scannerState !== READY
        || !nativeDetector
        || !video
        || video.readyState < 2
    ) {
        return;
    }

    try {
        const results = await nativeDetector.detect(video);
        const candidate = pickBestNativeCandidate(results);

        if (candidate) {
            if (confirmScanCandidate(candidate.code)) {
                showDetected(candidate.code, 'camera');
            }
        } else {
            expireScanCandidate();
        }
    } catch (error) {
        // Camera frames can be unavailable briefly while mobile Chrome rotates or focuses.
    }

    if (scannerState === READY) {
        nativeFrame = requestAnimationFrame(nativeScan);
    }
}

async function startDecoder() {
    stopDecoder();

    zxingReader = null;
    nativeDetector = null;

    try {
        const supported = (
            window.BarcodeDetector
            && await window.BarcodeDetector.getSupportedFormats()
        );

        if (
            supported?.includes('qr_code')
            && supported.includes('code_128')
        ) {
            nativeDetector = new window.BarcodeDetector({
                formats: ['qr_code', 'code_128'],
            });

            setCameraStatus(
                'Cadrez le code dans la zone bleue.'
            );

            nativeFrame = requestAnimationFrame(nativeScan);

            return;
        }
    } catch (error) {
        nativeDetector = null;
    }

    /*
     * ZXing fallback:
     * decode only the real scan zone instead of the full video.
     */
    zxingReader = new BrowserMultiFormatReader();

    setCameraStatus(
        'Cadrez le code dans la zone bleue.'
    );

    scheduleZxingScan(0);
}

async function startCamera() {
    primeScanAudio();
    if (scannerState !== READY || mediaStream) return;
    if (!navigator.mediaDevices?.getUserMedia) {
        setCameraStatus('Camera indisponible dans ce navigateur.');
        manualEntry.hidden = false;
        return;
    }
    startButton.disabled = true;
    retryButton.hidden = true;
    setCameraStatus('Demande d acces a la camera...');
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        });

        video.srcObject = mediaStream;
        await video.play();

        if (cameraFrame) {
            cameraFrame.classList.add('camera-active');
        }

        await configureCameraFocus();
        await configureTorch();
        await startDecoder();
    } catch (error) {
        stopCamera();
        setCameraStatus('Acces a la camera refuse ou indisponible.');
        retryButton.hidden = false;
        manualEntry.hidden = false;
        setMessage('Vous pouvez saisir le code manuellement.', true);
    } finally {
        startButton.disabled = false;
    }
}

function resumeScanning() {
    clearFreezeTimer(true);
    clearQuantityFocusTimer();

    if (detectedPanel) {
        detectedPanel.style.removeProperty('top');
        detectedPanel.style.removeProperty('height');
    }

    pendingCode = null;
    pendingDuplicate = null;
    scannerState = READY;
    document.body.classList.remove('quantity-modal-open');
    detectedPanel.hidden = true;
    duplicatePanel.hidden = true;
    duplicatePanel.replaceChildren();
    setMessage('');
    if (mediaStream) startDecoder().catch(() => setCameraStatus('Relance du scanner impossible.'));
    else setCameraStatus('Demarrez la camera pour scanner.');
}

function renderItem(item) {
    let row = document.querySelector(`[data-item="${CSS.escape(item.uuid)}"]`);
    if (!row) {
        row = document.createElement('tr');
        row.dataset.item = item.uuid;
        row.innerHTML = '<td></td><td class="quantity"></td><td>Disponible a l export</td><td><div class="actions item-actions"><button class="item-action-button edit-item" type="button" aria-label="Modifier l article" title="Modifier"><i data-lucide="Pencil"></i></button><button class="item-action-button delete-item" type="button" aria-label="Supprimer l article" title="Supprimer"><i data-lucide="Trash2"></i></button></div></td>';
        document.querySelector('#items-body').prepend(row);
    }
    row.dataset.code = item.code_article.toLocaleLowerCase();
    row.firstElementChild.textContent = item.code_article;
    row.querySelector('.quantity').textContent = item.quantity;
    createIcons({ icons: { Pencil, Trash2 } });
    document.querySelector('#empty-items').hidden = true;
}

async function saveItem(code, quantity, mode = null) {
    if (scannerState === SAVING) return;
    scannerState = SAVING;
    const data = new FormData();
    data.append('code_article', code);
    data.append('quantity', quantity);
    if (mode) data.append('mode', mode);
    try {
        const response = await fetch(app.dataset.itemUrl, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const payload = await response.json();
        if (response.status === 409 && payload.duplicate) {
            scannerState = DETECTED;
            pendingDuplicate = { code: payload.item.code_article, quantity };
            duplicatePanel.hidden = false;
            duplicatePanel.replaceChildren();
            const title = document.createElement('strong'); title.textContent = 'Article deja compte';
            const code = document.createElement('p'); code.textContent = payload.item.code_article;
            const quantities = document.createElement('p'); quantities.innerHTML = `Quantite actuelle : ${Number(payload.item.quantity)}<br>Nouvelle quantite : ${Number(quantity)}`;
            const actions = document.createElement('div');
            actions.className = 'duplicate-actions';

            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'duplicate-action duplicate-add';
            add.innerHTML = '<i data-lucide="Plus"></i><span>Ajouter</span>';
            add.addEventListener('click', () => saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'add'));

            const replace = document.createElement('button');
            replace.type = 'button';
            replace.className = 'duplicate-action duplicate-replace';
            replace.innerHTML = '<i data-lucide="RefreshCw"></i><span>Remplacer</span>';
            replace.addEventListener('click', () => saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'replace'));

            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'duplicate-action duplicate-cancel';
            cancel.innerHTML = '<i data-lucide="X"></i><span>Annuler</span>';
            cancel.addEventListener('click', resumeScanning);

            actions.append(add, replace, cancel);
            duplicatePanel.append(title, code, quantities, actions);
            createIcons({ icons: { Plus, RefreshCw, X } });
            return;
        }
        if (!response.ok) throw new Error(payload.message || 'La saisie n a pas pu etre enregistree.');
        renderItem(payload.item);
        updateSummary(payload);

        if (mode === 'add') {
            showToast(`Quantite ajoutee a ${payload.item.code_article}`, 'success');
        } else if (mode === 'replace') {
            showToast(`Quantite remplacee pour ${payload.item.code_article}`, 'info');
        } else {
            showToast(`Article ${payload.item.code_article} enregistre`, 'success');
        }

        if (form) form.reset();
        resumeScanning();
    } catch (error) {
        scannerState = DETECTED;
        setMessage(error.message, true);
        showToast(error.message || 'Enregistrement impossible.', 'danger', 3600);
    }
}

function toggleManual() {
    const open = manualEntry.hidden;
    manualEntry.hidden = !open;
    manualSave.hidden = !open;
    manualToggle.setAttribute('aria-expanded', String(open));
    if (open) codeInput.focus();
}

if (startButton) startButton.addEventListener('click', startCamera);
if (torchButton) torchButton.addEventListener('click', toggleTorch);
if (retryButton) retryButton.addEventListener('click', startCamera);
if (manualToggle) manualToggle.addEventListener('click', toggleManual);
if (document.querySelector('#save-detected')) document.querySelector('#save-detected').addEventListener('click', () => saveItem(pendingCode, detectedQuantity.value));
if (document.querySelector('#cancel-detected')) document.querySelector('#cancel-detected').addEventListener('click', resumeScanning);
if (form) form.addEventListener('submit', (event) => {
    event.preventDefault();
    const code = codeInput.value.trim();
    if (code) showDetected(code, 'manual');
});

if (detectedQuantity) {
    detectedQuantity.addEventListener('input', () => {
        const digits = detectedQuantity.value.replace(/[^\d]/g, '');

        if (detectedQuantity.value !== digits) {
            detectedQuantity.value = digits;
        }
    });

    detectedQuantity.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;

        event.preventDefault();

        const saveButton = document.querySelector('#save-detected');

        if (saveButton && !saveButton.disabled) {
            saveButton.click();
        }
    });
}

document.querySelectorAll('[data-detected-step]').forEach((button) => button.addEventListener('click', () => adjustQuantity(detectedQuantity, Number(button.dataset.detectedStep))));

const search = document.querySelector('#search');
if (search) search.addEventListener('input', (event) => {
    const query = event.target.value.toLocaleLowerCase().trim();
    document.querySelectorAll('#items-body tr').forEach((row) => { row.hidden = query !== '' && !row.dataset.code.includes(query); });
});

const itemsBody = document.querySelector('#items-body');
if (itemsBody) itemsBody.addEventListener('click', async (event) => {
    const row = event.target.closest('tr');
    if (!row) return;
    const itemUuid = row.dataset.item;
    if (event.target.closest('.delete-item')) {
        if (!window.confirm('Supprimer cet article de l inventaire ?')) return;

        const articleCode = row.firstElementChild?.textContent?.trim() || 'Article';
        const response = await fetch(`${app.dataset.itemUrl}/${itemUuid}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const payload = await response.json();

        if (!response.ok) {
            const errorMessage = payload.message || 'Suppression impossible.';
            setMessage(errorMessage, true);
            showToast(errorMessage, 'danger', 3600);
            return;
        }

        row.remove();
        updateSummary(payload);
        document.querySelector('#empty-items').hidden = document.querySelectorAll('#items-body tr').length > 0;
        showToast(`Article ${articleCode} supprime`, 'danger');
    }
    if (event.target.closest('.edit-item')) {
        const quantity = window.prompt('Nouvelle quantite', row.querySelector('.quantity').textContent);
        if (quantity === null) return;
        const data = new FormData(); data.append('quantity', quantity); data.append('_method', 'PATCH');
        const response = await fetch(`${app.dataset.itemUrl}/${itemUuid}`, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const payload = await response.json();

        if (!response.ok) {
            const errorMessage = payload.message || 'Quantite invalide.';
            setMessage(errorMessage, true);
            showToast(errorMessage, 'danger', 3600);
            return;
        }

        renderItem(payload.item);
        updateSummary(payload);
        showToast(`Quantite de ${payload.item.code_article} mise a jour`, 'info');
    }
});

const completeForm = document.querySelector('#complete-form');
if (completeForm) completeForm.addEventListener('submit', (event) => {
    const count = document.querySelector('#items-count').textContent;
    if (!window.confirm(`Vous avez compte ${count} references. Voulez-vous cloturer cet inventaire ?`)) event.preventDefault();
});

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopDecoder();
        clearFreezeTimer(false);
        return;
    }

    if (mediaStream) resumeLiveVideo();
    if (mediaStream && scannerState === READY) startDecoder().catch(() => {});
});

if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', syncQuantityModalViewport);
    window.visualViewport.addEventListener('scroll', syncQuantityModalViewport);
}

window.addEventListener('pagehide', () => {
    clearQuantityFocusTimer();
    stopCamera();
});
