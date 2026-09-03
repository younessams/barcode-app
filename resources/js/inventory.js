import { BrowserMultiFormatReader } from '@zxing/browser';
import { Camera, ChevronDown, createIcons, Flashlight, FlashlightOff, Keyboard, Minus, Pencil, Plus, RefreshCw, Save, Trash2, X } from 'lucide';

const app = document.querySelector('.app');
const video = document.querySelector('#camera-video');
const cameraFrame = document.querySelector('.camera-frame');
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

const READY = 'READY';
const DETECTED = 'DETECTED';
const SAVING = 'SAVING';
let scannerState = READY;
let mediaStream = null;
let nativeDetector = null;
let nativeFrame = 0;
let zxingControls = null;
let pendingCode = null;
let pendingDuplicate = null;
let freezeTimer = null;
let scanAudioContext = null;
let torchSupported = false;
let torchEnabled = false;
const SCAN_FREEZE_MS = 1800;

createIcons({ icons: { Camera, ChevronDown, Keyboard, Minus, Pencil, Plus, RefreshCw, Save, Trash2, X } });

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
    if (zxingControls) zxingControls.stop();
    zxingControls = null;
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
    manualEntry.hidden = true;
    manualToggle.setAttribute('aria-expanded', 'false');
    setCameraStatus('Code detecte. Saisissez la quantite.');
}

async function nativeScan() {
    if (scannerState !== READY || !nativeDetector || !video || video.readyState < 2) return;
    try {
        const results = await nativeDetector.detect(video);
        if (results.length) showDetected(results[0].rawValue, 'camera');
    } catch (error) {
        // Camera frames can be unavailable briefly while mobile Chrome rotates or focuses.
    }
    if (scannerState === READY) nativeFrame = requestAnimationFrame(nativeScan);
}

async function startDecoder() {
    stopDecoder();
    try {
        const supported = window.BarcodeDetector && await window.BarcodeDetector.getSupportedFormats();
        if (supported?.includes('qr_code') && supported.includes('code_128')) {
            nativeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128'] });
            setCameraStatus('Pret a scanner un QR code ou un code-barres.');
            nativeFrame = requestAnimationFrame(nativeScan);
            return;
        }
    } catch (error) {
        nativeDetector = null;
    }

    nativeDetector = null;
    const reader = new BrowserMultiFormatReader();
    zxingControls = await reader.decodeFromVideoElementContinuously(video, (result) => {
        if (result) showDetected(result.getText(), 'camera');
    });
    setCameraStatus('Pret a scanner un QR code ou un code-barres.');
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
        mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        video.srcObject = mediaStream;
        await video.play();
        if (cameraFrame) cameraFrame.classList.add('camera-active');
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
    pendingCode = null;
    pendingDuplicate = null;
    scannerState = READY;
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
            const add = document.createElement('button'); add.type = 'button'; add.className = 'secondary'; add.innerHTML = '<i data-lucide="Plus"></i>Ajouter'; add.addEventListener('click', () => saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'add'));
            const replace = document.createElement('button'); replace.type = 'button'; replace.className = 'secondary'; replace.innerHTML = '<i data-lucide="RefreshCw"></i>Remplacer'; replace.addEventListener('click', () => saveItem(pendingDuplicate.code, pendingDuplicate.quantity, 'replace'));
            const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'secondary'; cancel.innerHTML = '<i data-lucide="X"></i>Annuler'; cancel.addEventListener('click', resumeScanning);
            duplicatePanel.append(title, code, quantities, add, replace, cancel);
            createIcons({ icons: { Plus, RefreshCw, X } });
            return;
        }
        if (!response.ok) throw new Error(payload.message || 'La saisie n a pas pu etre enregistree.');
        renderItem(payload.item);
        updateSummary(payload);
        setMessage('Article enregistre.');
        if (form) form.reset();
        resumeScanning();
    } catch (error) {
        scannerState = DETECTED;
        setMessage(error.message, true);
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
        const response = await fetch(`${app.dataset.itemUrl}/${itemUuid}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) { setMessage(payload.message || 'Suppression impossible.', true); return; }
        row.remove(); updateSummary(payload); document.querySelector('#empty-items').hidden = document.querySelectorAll('#items-body tr').length > 0; setMessage('Article supprime.');
    }
    if (event.target.closest('.edit-item')) {
        const quantity = window.prompt('Nouvelle quantite', row.querySelector('.quantity').textContent);
        if (quantity === null) return;
        const data = new FormData(); data.append('quantity', quantity); data.append('_method', 'PATCH');
        const response = await fetch(`${app.dataset.itemUrl}/${itemUuid}`, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) { setMessage(payload.message || 'Quantite invalide.', true); return; }
        renderItem(payload.item); updateSummary(payload); setMessage('Quantite mise a jour.');
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

window.addEventListener('pagehide', stopCamera);
