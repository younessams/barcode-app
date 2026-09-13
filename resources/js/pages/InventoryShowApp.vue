<script setup>
import { BrowserMultiFormatReader } from '@zxing/browser';
import {
    Camera,
    CheckCircle2,
    ChevronDown,
    CircleAlert,
    CircleStop,
    Flashlight,
    FlashlightOff,
    Keyboard,
    Minus,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Trash2,
    X,
    createIcons,
} from 'lucide';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    csrfToken: { type: String, required: true },
    inventory: { type: Object, required: true },
    urls: { type: Object, required: true },
    initialItems: { type: Array, default: () => [] },
});

const video = ref(null);
const cameraFrame = ref(null);
const scanGuide = ref(null);
const torchButton = ref(null);
const codeInput = ref(null);
const detectedPanel = ref(null);
const detectedQuantity = ref(null);
const toast = ref(null);
const toastIcon = ref(null);
const toastProgress = ref(null);
const items = ref([...props.initialItems]);
const search = ref('');
const cameraStatus = ref('Placez le QR code ou le code-barres dans le cadre bleu.');
const messageText = ref('');
const messageIsError = ref(false);
const manualOpen = ref(false);
const retryVisible = ref(false);
const startDisabled = ref(false);
const detectedOpen = ref(false);
const pendingCode = ref(null);
const detectedQuantityValue = ref('1');
const duplicate = ref(null);
const toastOpen = ref(false);
const toastType = ref('success');
const toastMessage = ref('');
const torchSupportedState = ref(false);
const torchEnabledState = ref(false);

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

const isCompleted = computed(() => props.inventory.completed);
const itemsCount = computed(() => items.value.length);
const totalQuantity = computed(() => items.value.reduce((total, item) => total + Number(item.quantity || 0), 0));
const filteredItems = computed(() => {
    const query = search.value.toLocaleLowerCase().trim();
    if (query === '') return items.value;

    return items.value.filter((item) => item.code_article.toLocaleLowerCase().includes(query));
});
const showEmptyItems = computed(() => items.value.length === 0);

function refreshIcons() {
    nextTick(() => createIcons({
        icons: {
            Camera,
            CheckCircle2,
            ChevronDown,
            CircleAlert,
            CircleStop,
            Flashlight,
            FlashlightOff,
            Keyboard,
            Minus,
            Pencil,
            Plus,
            RefreshCw,
            Save,
            Trash2,
            X,
        },
    }));
}

function syncQuantityModalViewport() {
    if (!detectedPanel.value || !detectedOpen.value) return;

    const viewport = window.visualViewport;

    if (!viewport) {
        detectedPanel.value.style.removeProperty('top');
        detectedPanel.value.style.removeProperty('height');
        return;
    }

    detectedPanel.value.style.top = `${viewport.offsetTop}px`;
    detectedPanel.value.style.height = `${viewport.height}px`;
}

function clearQuantityFocusTimer() {
    if (!quantityFocusTimer) return;

    clearTimeout(quantityFocusTimer);
    quantityFocusTimer = null;
}

function focusQuantityInput() {
    if (!detectedQuantity.value || !detectedOpen.value) return;

    clearQuantityFocusTimer();

    requestAnimationFrame(() => {
        if (!detectedOpen.value) return;

        syncQuantityModalViewport();

        try {
            detectedQuantity.value.focus({ preventScroll: true });
            detectedQuantity.value.select();
        } catch (error) {
            detectedQuantity.value.focus();
        }

        quantityFocusTimer = window.setTimeout(() => {
            quantityFocusTimer = null;

            if (!detectedOpen.value) return;

            syncQuantityModalViewport();

            detectedQuantity.value.scrollIntoView({
                block: 'center',
                inline: 'nearest',
                behavior: 'smooth',
            });
        }, 120);
    });
}

function adjustQuantity(amount) {
    const value = Number.parseInt(detectedQuantityValue.value, 10) || 0;
    detectedQuantityValue.value = String(Math.max(0, value + amount));
}

function sanitizeDetectedQuantity() {
    const digits = detectedQuantityValue.value.replace(/[^\d]/g, '');

    if (detectedQuantityValue.value !== digits) {
        detectedQuantityValue.value = digits;
    }
}

function saveDetectedOnEnter(event) {
    if (event.key !== 'Enter') return;

    event.preventDefault();
    saveItem(pendingCode.value, detectedQuantityValue.value);
}

function setMessage(text, error = false) {
    messageText.value = text;
    messageIsError.value = error;
}

function hideToast() {
    if (toastTimer) {
        clearTimeout(toastTimer);
        toastTimer = null;
    }

    if (toastFrame) {
        cancelAnimationFrame(toastFrame);
        toastFrame = null;
    }

    toastOpen.value = false;
}

function showToast(text, type = 'success', duration = TOAST_DURATION_MS) {
    hideToast();

    toastType.value = type;
    toastMessage.value = text;
    toastOpen.value = true;
    refreshIcons();

    nextTick(() => {
        if (!toastProgress.value) return;

        toastProgress.value.style.transition = 'none';
        toastProgress.value.style.transform = 'scaleX(1)';

        // Force the browser to paint the full bar before starting the countdown.
        void toastProgress.value.offsetWidth;

        toastFrame = requestAnimationFrame(() => {
            toastProgress.value.style.transition = `transform ${duration}ms linear`;
            toastProgress.value.style.transform = 'scaleX(0)';
            toastFrame = null;
        });
    });

    toastTimer = window.setTimeout(() => {
        toastOpen.value = false;
        toastTimer = null;
    }, duration);
}

function setCameraStatus(text) {
    cameraStatus.value = text;
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
    if (video.value && mediaStream && video.value.paused) {
        video.value.play().catch(() => {});
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

    if (!video.value || !mediaStream) return;

    video.value.pause();

    freezeTimer = window.setTimeout(() => {
        freezeTimer = null;
        resumeLiveVideo();
    }, SCAN_FREEZE_MS);
}

function getVideoTrack() {
    return mediaStream?.getVideoTracks?.()[0] || null;
}

function renderTorchButton() {
    torchSupportedState.value = torchSupported;
    torchEnabledState.value = torchEnabled;
    refreshIcons();
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
    renderTorchButton();
}

function updateSummary(payload) {
    if (payload.items_count === undefined && payload.total_quantity === undefined) return;
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
    if (video.value) video.value.srcObject = null;
    if (cameraFrame.value) cameraFrame.value.classList.remove('camera-active');
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
        !video.value
        || !scanGuide.value
        || !video.value.videoWidth
        || !video.value.videoHeight
    ) {
        return null;
    }

    const videoRect = video.value.getBoundingClientRect();
    const guideRect = scanGuide.value.getBoundingClientRect();

    if (!videoRect.width || !videoRect.height) {
        return null;
    }

    /*
     * The preview uses object-fit: cover.
     * Map the visible blue guide back to the camera's original pixels,
     * including the parts cropped by object-fit.
     */
    const scale = Math.max(
        videoRect.width / video.value.videoWidth,
        videoRect.height / video.value.videoHeight
    );

    const renderedWidth = video.value.videoWidth * scale;
    const renderedHeight = video.value.videoHeight * scale;

    const offsetX = (videoRect.width - renderedWidth) / 2;
    const offsetY = (videoRect.height - renderedHeight) / 2;

    const left = clamp(
        (guideRect.left - videoRect.left - offsetX) / scale,
        0,
        video.value.videoWidth
    );

    const top = clamp(
        (guideRect.top - videoRect.top - offsetY) / scale,
        0,
        video.value.videoHeight
    );

    const right = clamp(
        (guideRect.right - videoRect.left - offsetX) / scale,
        0,
        video.value.videoWidth
    );

    const bottom = clamp(
        (guideRect.bottom - videoRect.top - offsetY) / scale,
        0,
        video.value.videoHeight
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
        || !video.value
        || video.value.readyState < 2
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
            video.value,
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
    pendingCode.value = code;
    stopDecoder();

    if (source === 'camera') {
        playScanBeep();
        if (navigator.vibrate) navigator.vibrate(50);
        freezeCameraFrame();
    }

    detectedQuantityValue.value = '1';
    detectedOpen.value = true;
    document.body.classList.add('quantity-modal-open');
    duplicate.value = null;
    nextTick(() => {
        syncQuantityModalViewport();
        focusQuantityInput();
    });
    manualOpen.value = false;
    setCameraStatus('Code detecte. Saisissez la quantite.');
}

async function nativeScan() {
    if (
        scannerState !== READY
        || !nativeDetector
        || !video.value
        || video.value.readyState < 2
    ) {
        return;
    }

    try {
        const results = await nativeDetector.detect(video.value);
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
        manualOpen.value = true;
        return;
    }
    startDisabled.value = true;
    retryVisible.value = false;
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

        video.value.srcObject = mediaStream;
        await video.value.play();

        if (cameraFrame.value) {
            cameraFrame.value.classList.add('camera-active');
        }

        await configureCameraFocus();
        await configureTorch();
        await startDecoder();
    } catch (error) {
        stopCamera();
        setCameraStatus('Acces a la camera refuse ou indisponible.');
        retryVisible.value = true;
        manualOpen.value = true;
        setMessage('Vous pouvez saisir le code manuellement.', true);
    } finally {
        startDisabled.value = false;
    }
}

function resumeScanning() {
    clearFreezeTimer(true);
    clearQuantityFocusTimer();

    if (detectedPanel.value) {
        detectedPanel.value.style.removeProperty('top');
        detectedPanel.value.style.removeProperty('height');
    }

    pendingCode.value = null;
    pendingDuplicate = null;
    scannerState = READY;
    document.body.classList.remove('quantity-modal-open');
    detectedOpen.value = false;
    duplicate.value = null;
    setMessage('');
    if (mediaStream) startDecoder().catch(() => setCameraStatus('Relance du scanner impossible.'));
    else setCameraStatus('Demarrez la camera pour scanner.');
}

function renderItem(item) {
    const existingIndex = items.value.findIndex((currentItem) => currentItem.uuid === item.uuid);

    if (existingIndex >= 0) {
        items.value.splice(existingIndex, 1, item);
    } else {
        items.value.unshift(item);
    }

    refreshIcons();
}

async function saveItem(code, quantity, mode = null) {
    if (scannerState === SAVING) return;
    scannerState = SAVING;
    const data = new FormData();
    data.append('code_article', code);
    data.append('quantity', quantity);
    if (mode) data.append('mode', mode);
    try {
        const response = await fetch(props.urls.itemStore, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': props.csrfToken, Accept: 'application/json' } });
        const payload = await response.json();
        if (response.status === 409 && payload.duplicate) {
            scannerState = DETECTED;
            pendingDuplicate = { code: payload.item.code_article, quantity };
            duplicate.value = {
                code: payload.item.code_article,
                currentQuantity: Number(payload.item.quantity),
                newQuantity: Number(quantity),
            };
            refreshIcons();
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

        resumeScanning();
        if (codeInput.value) {
            codeInput.value.value = '';
        }
    } catch (error) {
        scannerState = DETECTED;
        setMessage(error.message, true);
        showToast(error.message || 'Enregistrement impossible.', 'danger', 3600);
    }
}

function toggleManual() {
    const open = !manualOpen.value;
    manualOpen.value = open;
    if (open) nextTick(() => codeInput.value?.focus());
}

function submitManual(event) {
    event.preventDefault();
    const code = codeInput.value.value.trim();
    if (code) showDetected(code, 'manual');
}

function duplicateAdd() {
    if (!pendingDuplicate) return;

    saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'add');
}

function duplicateReplace() {
    if (!pendingDuplicate) return;

    saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'replace');
}

async function deleteItem(item) {
    if (!window.confirm('Supprimer cet article de l inventaire ?')) return;

    const response = await fetch(`${props.urls.itemStore}/${item.uuid}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': props.csrfToken, Accept: 'application/json' } });
    const payload = await response.json();

    if (!response.ok) {
        const errorMessage = payload.message || 'Suppression impossible.';
        setMessage(errorMessage, true);
        showToast(errorMessage, 'danger', 3600);
        return;
    }

    items.value = items.value.filter((currentItem) => currentItem.uuid !== item.uuid);
    updateSummary(payload);
    showToast(`Article ${item.code_article} supprime`, 'danger');
}

async function editItem(item) {
    const quantity = window.prompt('Nouvelle quantite', item.quantity);
    if (quantity === null) return;
    const data = new FormData(); data.append('quantity', quantity); data.append('_method', 'PATCH');
    const response = await fetch(`${props.urls.itemStore}/${item.uuid}`, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': props.csrfToken, Accept: 'application/json' } });
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

function confirmComplete(event) {
    if (!window.confirm(`Vous avez compte ${itemsCount.value} references. Voulez-vous cloturer cet inventaire ?`)) event.preventDefault();
}

function onVisibilityChange() {
    if (document.hidden) {
        stopDecoder();
        clearFreezeTimer(false);
        return;
    }

    if (mediaStream) resumeLiveVideo();
    if (mediaStream && scannerState === READY) startDecoder().catch(() => {});
}

onMounted(() => {
    refreshIcons();
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncQuantityModalViewport);
        window.visualViewport.addEventListener('scroll', syncQuantityModalViewport);
    }

    window.addEventListener('pagehide', stopCamera);
});

onBeforeUnmount(() => {
    clearQuantityFocusTimer();
    stopCamera();
    hideToast();
    document.body.classList.remove('quantity-modal-open');
    document.removeEventListener('visibilitychange', onVisibilityChange);

    if (window.visualViewport) {
        window.visualViewport.removeEventListener('resize', syncQuantityModalViewport);
        window.visualViewport.removeEventListener('scroll', syncQuantityModalViewport);
    }

    window.removeEventListener('pagehide', stopCamera);
});
</script>

<template>
    <div class="app">
        <div ref="toast" class="action-toast" :class="[toastType, { show: toastOpen }]" role="status" aria-live="polite" aria-atomic="true">
            <div class="action-toast-content">
                <span ref="toastIcon" class="action-toast-icon" aria-hidden="true">
                    <i :data-lucide="toastType === 'danger' ? 'CircleAlert' : 'CheckCircle2'"></i>
                </span>
                <span class="action-toast-message">{{ toastMessage }}</span>
            </div>
            <div class="action-toast-progress-track" aria-hidden="true">
                <div ref="toastProgress" class="action-toast-progress"></div>
            </div>
        </div>

        <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" :href="urls.labelsIndex">Etiquettes</a><a class="app-nav-link active" :href="urls.inventoryIndex">Inventaire</a><a class="app-nav-link" :href="urls.catalogueIndex">Catalogue QR</a></nav>
        <div class="top">
            <div><h1>{{ inventory.name }}</h1><p class="meta">{{ inventory.zone || 'Zone non renseignee' }} · <span id="status">{{ inventory.completed ? 'Termine' : 'En cours' }}</span></p></div>
            <div class="toolbar">
                <form class="export-form" method="get" :action="urls.export">
                    <button class="button secondary" type="submit">Exporter Excel</button>
                    <label class="export-option">
                        <input type="checkbox" name="include_qr" value="1">
                        <span>Inclure les QR codes dans Excel</span>
                    </label>
                </form>
                <form v-if="inventory.completed" method="post" :action="urls.reopen">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button class="secondary" type="submit">Rouvrir</button>
                </form>
            </div>
        </div>

        <section v-if="!isCompleted" class="scanner" aria-labelledby="scanner-title">
            <div class="scanner-heading"><h2 id="scanner-title">Scanner un article</h2><button ref="torchButton" id="torch-toggle" class="torch-button" :class="{ 'is-on': torchEnabledState }" type="button" :aria-label="torchEnabledState ? 'Eteindre le flash' : 'Allumer le flash'" :aria-pressed="String(torchEnabledState)" :title="torchEnabledState ? 'Eteindre le flash' : 'Allumer le flash'" :hidden="!torchSupportedState" @click="toggleTorch"><i :data-lucide="torchEnabledState ? 'FlashlightOff' : 'Flashlight'"></i></button></div>
            <div ref="cameraFrame" class="camera-frame"><video id="camera-video" ref="video" playsinline muted aria-label="Apercu de la camera"></video><div ref="scanGuide" class="scan-guide"></div><div class="scan-line" aria-hidden="true"></div><p id="camera-status" class="camera-status">{{ cameraStatus }}</p></div>
            <div class="action-area"><div class="camera-actions"><button id="start-camera" class="icon-button" type="button" aria-label="Demarrer la camera" title="Demarrer la camera" :disabled="startDisabled" @click="startCamera"><i data-lucide="Camera"></i></button><button id="retry-camera" class="icon-button secondary" type="button" aria-label="Reessayer la camera" title="Reessayer" :hidden="!retryVisible" @click="startCamera"><i data-lucide="RefreshCw"></i></button><button id="manual-toggle" class="manual-link" type="button" :aria-expanded="String(manualOpen)" @click="toggleManual"><i data-lucide="Keyboard"></i><span>Saisir le code article manuellement</span></button></div></div>
            <div id="manual-entry" :hidden="!manualOpen"><form id="item-form" @submit="submitManual"><label class="quantity-label" for="code_article">Code Article</label><div class="manual-code-row"><input id="code_article" ref="codeInput" name="code_article" autocomplete="off" required><button class="button secondary" type="submit">Continuer</button></div></form></div>
            <div id="detected-panel" ref="detectedPanel" class="detected quantity-modal-overlay" :hidden="!detectedOpen">
                <div class="quantity-modal" role="dialog" aria-modal="true" aria-labelledby="quantity-modal-title">
                    <div class="quantity-modal-header">
                        <div class="quantity-modal-title-wrap">
                            <span id="quantity-modal-title" class="quantity-modal-kicker">Article détecté</span>
                            <strong id="detected-code" class="detected-code">{{ pendingCode }}</strong>
                        </div>
                    </div>

                    <div class="quantity-modal-content">
                        <span class="quantity-modal-label">Quantité</span>

                        <div class="quantity-control-row">
                            <div class="quantity-bar">
                                <button class="icon-button" type="button" data-detected-step="-1" aria-label="Diminuer la quantite" title="Diminuer" @click="adjustQuantity(-1)">
                                    <i data-lucide="Minus"></i>
                                </button>

                                <input id="detected-quantity" ref="detectedQuantity" v-model="detectedQuantityValue" type="text" inputmode="numeric" pattern="[0-9]*" enterkeyhint="done" autocomplete="off" aria-label="Quantite" @input="sanitizeDetectedQuantity" @keydown="saveDetectedOnEnter">

                                <button class="icon-button" type="button" data-detected-step="1" aria-label="Augmenter la quantite" title="Augmenter" @click="adjustQuantity(1)">
                                    <i data-lucide="Plus"></i>
                                </button>
                            </div>

                            <button id="cancel-detected" class="modal-cancel-button" type="button" aria-label="Annuler la saisie" title="Annuler" @click="resumeScanning">
                                <i data-lucide="X"></i>
                            </button>
                        </div>

                        <button id="save-detected" class="modal-save-button" type="button" @click="saveItem(pendingCode, detectedQuantityValue)">
                            <i data-lucide="Save"></i>
                            <span>Enregistrer</span>
                        </button>

                        <div id="duplicate-panel" class="duplicate" :hidden="!duplicate">
                            <strong>Article deja compte</strong>
                            <p>{{ duplicate?.code }}</p>
                            <p>Quantite actuelle : {{ duplicate?.currentQuantity }}<br>Nouvelle quantite : {{ duplicate?.newQuantity }}</p>
                            <div class="duplicate-actions">
                                <button type="button" class="duplicate-action duplicate-add" @click="duplicateAdd"><i data-lucide="Plus"></i><span>Ajouter</span></button>
                                <button type="button" class="duplicate-action duplicate-replace" @click="duplicateReplace"><i data-lucide="RefreshCw"></i><span>Remplacer</span></button>
                                <button type="button" class="duplicate-action duplicate-cancel" @click="resumeScanning"><i data-lucide="X"></i><span>Annuler</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p id="message" class="message" :class="{ error: messageIsError }" role="status">{{ messageText }}</p>
        </section>
        <section v-else class="scanner"><h2>Inventaire termine</h2><p class="meta">La camera et la modification des articles sont desactivees. Vous pouvez rouvrir cet inventaire pour continuer.</p></section>

        <div class="summary"><div class="stat"><small>References</small><strong id="items-count">{{ itemsCount }}</strong></div><div class="stat"><small>Quantite totale</small><strong id="total-quantity">{{ totalQuantity }}</strong></div></div>
        <form v-if="!isCompleted" id="complete-form" method="post" :action="urls.complete" @submit="confirmComplete">
            <input type="hidden" name="_token" :value="csrfToken">
            <button class="close-action" type="submit"><i data-lucide="CircleStop"></i>Cloturer l'inventaire</button>
        </form>

        <details id="items-section" class="items"><summary><i data-lucide="ChevronDown"></i>Voir les articles comptes ({{ itemsCount }})</summary><div class="items-toolbar"><h2>Articles comptes</h2><input id="search" v-model="search" type="search" placeholder="Rechercher un code" aria-label="Rechercher un code"></div><div class="table-wrap"><table><thead><tr><th>Code Article</th><th>Quantite</th><th>QR</th><th v-if="!isCompleted">Actions</th></tr></thead><tbody id="items-body"><tr v-for="item in filteredItems" :key="item.uuid" :data-code="item.code_article.toLocaleLowerCase()" :data-item="item.uuid"><td>{{ item.code_article }}</td><td class="quantity">{{ item.quantity }}</td><td>Disponible a l'export</td><td v-if="!isCompleted"><div class="actions item-actions"><button class="item-action-button edit-item" type="button" aria-label="Modifier l article" title="Modifier" @click="editItem(item)"><i data-lucide="Pencil"></i></button><button class="item-action-button delete-item" type="button" aria-label="Supprimer l article" title="Supprimer" @click="deleteItem(item)"><i data-lucide="Trash2"></i></button></div></td></tr></tbody></table></div><p id="empty-items" class="empty" :hidden="!showEmptyItems">Aucun article compte.</p></details>
    </div>
</template>
