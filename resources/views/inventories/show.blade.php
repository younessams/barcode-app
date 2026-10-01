<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inventaire</title>
    @vite(['resources/css/app.css', 'resources/js/inventory.js'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        .app { max-width: 920px; margin: auto; padding: 20px; }
        .top, .toolbar, .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .top { justify-content: space-between; align-items: flex-start; }
        h1 { margin: 0; font-size: 22px; letter-spacing: -.025em; }
        h2 { margin: 0; font-size: 16px; }
        .meta, .empty { color: #607080; margin: 6px 0 18px; }
        .button, button { border: 0; border-radius: 6px; padding: 11px 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .button.secondary, button.secondary { background: #edf1f4; color: #16202a; }
        .button:disabled, button:disabled { opacity: .55; cursor: not-allowed; }
        .alert { padding: 12px; color: #8b3e12; background: #fff4e9; border: 1px solid #f0c89d; border-radius: 7px; }
        .scanner, .items, .summary { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; margin-top: 16px; }
        .scanner { padding: 14px; max-width: 760px; }
        .scanner-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .camera-frame { position: relative; background: #101820; border-radius: 6px; overflow: hidden; aspect-ratio: 16 / 10; max-height: 42vh; margin-top: 10px; }
        #camera-video { display: block; width: 100%; height: 100%; object-fit: cover; }
        .scan-guide { position: absolute; z-index: 2; inset: 20% 12%; border: 2px solid #8fd3ff; border-radius: 8px; box-shadow: 0 0 0 999px rgba(7, 13, 18, .28); pointer-events: none; }
        .scan-line { position: absolute; z-index: 3; top: 50%; left: 10%; right: 10%; height: 1px; transform: translateY(-50%); background: #ff5f5f; opacity: 0; pointer-events: none; }
        .camera-frame.camera-active .scan-line { opacity: .95; }
        .camera-status { position: absolute; z-index: 4; inset: auto 10px 10px; margin: 0; color: #fff; text-align: center; font-size: 12px; text-shadow: 0 1px 3px #000; }
        .camera-actions { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
        .icon-button, .torch-button, .item-action-button { display: inline-grid; place-items: center; width: 38px; height: 38px; padding: 0; }
        .manual-link { background: transparent; color: #1769aa; padding: 8px; }
        .manual-code-row { display: flex; gap: 8px; margin-top: 8px; }
        input { min-width: 0; padding: 10px; border: 1px solid #b8c2cc; border-radius: 6px; font: inherit; }
        .manual-code-row input, .items-toolbar input { width: 100%; }
        .quantity-modal-overlay { position: fixed; z-index: 50; inset: 0; display: grid; place-items: center; padding: 16px; background: rgba(15, 24, 32, .56); }
        .quantity-modal { width: min(100%, 390px); padding: 20px; border-radius: 8px; background: #fff; box-shadow: 0 16px 44px rgba(0, 0, 0, .28); }
        .detected-code { margin: 12px 0 0; font-size: 18px; font-weight: 800; text-align: center; overflow-wrap: anywhere; }
        .detected-emplacement { margin: 3px 0 14px; color: #1769aa; font-size: 21px; font-weight: 800; text-align: center; overflow-wrap: anywhere; }
        .quantity-controls { display: grid; grid-template-columns: 38px 1fr 38px; gap: 8px; align-items: center; }
        .quantity-controls input { width: 100%; text-align: center; }
        .duplicate-panel { margin-top: 12px; padding: 13px; border: 1px solid #f0c89d; border-radius: 7px; background: #fff8ef; }
        .duplicate-panel p { margin: 5px 0; }
        .duplicate-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .summary { display: flex; gap: 24px; padding: 14px; }
        .stat { display: grid; gap: 3px; }
        .stat small { color: #607080; }
        .items { padding: 14px; }
        .items-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin: 4px 0 12px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 620px; }
        th, td { padding: 11px 8px; border-bottom: 1px solid #e5e9ed; text-align: left; vertical-align: middle; }
        th { color: #607080; font-size: 12px; }
        .item-actions { display: inline-flex; gap: 6px; }
        .delete-item { background: #fff1f0; color: #b42318; }
        .action-toast { position: fixed; z-index: 80; top: 14px; left: 50%; width: min(calc(100% - 28px), 440px); transform: translate(-50%, -18px); opacity: 0; pointer-events: none; overflow: hidden; border: 1px solid #dce2e7; border-radius: 8px; background: #fff; box-shadow: 0 12px 30px rgba(20, 32, 42, .16); transition: opacity .2s ease, transform .2s ease; }
        .action-toast.show { opacity: 1; transform: translate(-50%, 0); }
        .action-toast-content { display: flex; gap: 10px; align-items: center; min-height: 50px; padding: 10px 13px; }
        .action-toast-progress-track { height: 3px; background: #edf1f4; }.action-toast-progress { width: 100%; height: 100%; transform-origin: left center; background: #28733f; }
        @media (max-width: 700px) { .app { padding: 14px; } .top, .items-toolbar { align-items: stretch; flex-direction: column; } .manual-code-row { flex-direction: column; } }
    </style>
</head>
<body>
<div class="action-toast" id="action-toast" role="status" aria-live="polite"><div class="action-toast-content"><span id="action-toast-icon"></span><span id="action-toast-message"></span></div><div class="action-toast-progress-track"><div id="action-toast-progress" class="action-toast-progress"></div></div></div>
<div class="app" data-inventory="{{ $inventoryUuid }}" data-export-url="{{ route('inventories.export') }}">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link active" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <section id="inventory-missing" class="alert" hidden><strong>Inventaire introuvable.</strong><p>Il n existe pas dans le stockage local de ce navigateur.</p><a class="button secondary" href="{{ route('inventories.index') }}">Retour aux inventaires</a></section>
    <main id="inventory-workspace" hidden>
        <div class="top"><div><h1 id="inventory-name"></h1><p id="inventory-meta" class="meta"></p></div><div class="toolbar"><button id="export-inventory" class="secondary" type="button"><i data-lucide="Download"></i>Exporter Excel</button><button id="reopen-inventory" class="secondary" type="button" hidden>Rouvrir</button></div></div>
        <p id="export-message" class="empty" role="status"></p>
        <section id="scanner-section" class="scanner" aria-labelledby="scanner-title"><div class="scanner-heading"><h2 id="scanner-title">Scanner un article</h2><button id="torch-toggle" class="torch-button secondary" type="button" aria-label="Allumer le flash" title="Allumer le flash" aria-pressed="false" hidden><i data-lucide="Flashlight"></i></button></div><div class="camera-frame"><video id="camera-video" playsinline muted aria-label="Apercu de la camera"></video><div class="scan-guide"></div><div class="scan-line" aria-hidden="true"></div><p id="camera-status" class="camera-status">Placez le QR code ou le code-barres dans le cadre bleu.</p></div><div class="camera-actions"><button id="start-camera" class="icon-button" type="button" aria-label="Demarrer la camera" title="Demarrer la camera"><i data-lucide="Camera"></i></button><button id="retry-camera" class="icon-button secondary" type="button" aria-label="Reessayer la camera" title="Reessayer" hidden><i data-lucide="RefreshCw"></i></button><button id="manual-toggle" class="manual-link" type="button" aria-expanded="false"><i data-lucide="Keyboard"></i><span>Saisir le code article manuellement</span></button></div><div id="manual-entry" hidden><form id="item-form"><label for="code_article">Code Article</label><div class="manual-code-row"><input id="code_article" name="code_article" autocomplete="off" required><button id="manual-save" class="button secondary" type="submit">Continuer</button></div></form></div><p id="message" class="empty" role="status"></p>
            <div id="detected-panel" class="quantity-modal-overlay" hidden><div class="quantity-modal" role="dialog" aria-modal="true" aria-labelledby="quantity-modal-title"><h2 id="quantity-modal-title">Quantite detectee</h2><p id="detected-code" class="detected-code"></p><p id="detected-emplacement" class="detected-emplacement" hidden></p><div class="quantity-controls"><button type="button" class="secondary" data-detected-step="-1" aria-label="Diminuer">-</button><input id="detected-quantity" inputmode="decimal" value="1" aria-label="Quantite"><button type="button" class="secondary" data-detected-step="1" aria-label="Augmenter">+</button></div><div class="actions" style="margin-top:14px"><button id="cancel-detected" class="secondary" type="button">Annuler</button><button id="save-detected" type="button">Enregistrer</button></div><div id="duplicate-panel" class="duplicate-panel" hidden></div></div></div>
        </section>
        <div class="summary"><div class="stat"><small>References</small><strong id="items-count">0</strong></div><div class="stat"><small>Quantite totale</small><strong id="total-quantity">0.000</strong></div></div>
        <button id="complete-inventory" class="button" type="button">Cloturer l'inventaire</button>
        <section class="items"><div class="items-toolbar"><h2>Articles comptes</h2><input id="search" type="search" placeholder="Rechercher un code ou emplacement" aria-label="Rechercher un code ou emplacement"></div><div class="table-wrap"><table><thead><tr><th>Code Article</th><th>Emplacement</th><th>Quantite</th><th>Actions</th></tr></thead><tbody id="items-body"></tbody></table></div><p id="empty-items" class="empty">Aucun article compte.</p></section>
    </main>
</div>
</body>
</html>
