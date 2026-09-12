<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $inventory->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/inventory.js'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }

        .action-toast {
            position: fixed;
            z-index: 9999;
            top: 14px;
            left: 50%;
            width: min(calc(100% - 28px), 440px);
            transform: translate(-50%, -18px);
            opacity: 0;
            pointer-events: none;
            overflow: hidden;
            border: 1px solid #dce2e7;
            border-radius: 12px;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 12px 30px rgba(20, 32, 42, .16);
            transition: opacity .2s ease, transform .2s ease;
        }

        .action-toast.show {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        .action-toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 52px;
            padding: 10px 13px 11px;
        }

        .action-toast-icon {
            display: grid;
            place-items: center;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
        }

        .action-toast-icon svg {
            width: 18px;
            height: 18px;
            stroke-width: 2.4;
        }

        .action-toast-message {
            min-width: 0;
            font-size: 14px;
            line-height: 1.3;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .action-toast.success .action-toast-icon {
            color: #28733f;
            background: #eaf6ed;
        }

        .action-toast.info .action-toast-icon {
            color: #1769aa;
            background: #eaf4fb;
        }

        .action-toast.danger .action-toast-icon {
            color: #b42318;
            background: #fff0ef;
        }

        .action-toast-progress-track {
            height: 3px;
            background: #edf1f4;
        }

        .action-toast-progress {
            width: 100%;
            height: 100%;
            transform-origin: left center;
            background: #28733f;
        }

        .action-toast.info .action-toast-progress {
            background: #1769aa;
        }

        .action-toast.danger .action-toast-progress {
            background: #b42318;
        }

        @media (prefers-reduced-motion: reduce) {
            .action-toast {
                transition: none;
            }

            .action-toast-progress {
                transition: none !important;
            }
        }        .app { max-width: 920px; margin: auto; padding: 20px; }
        .top { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 22px; letter-spacing: -.025em; }
        h2 { margin: 0; font-size: 16px; }
        .meta { color: #607080; margin: 6px 0 18px; }
        .toolbar, .actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .export-form {
            display: grid;
            gap: 8px;
            min-width: 230px;
        }

        .export-form .button {
            width: 100%;
            text-align: center;
        }

        .export-option {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            color: #607080;
            font-size: 12px;
            font-weight: 650;
            cursor: pointer;
            user-select: none;
        }

        .export-option input {
            width: 16px;
            height: 16px;
            padding: 0;
            margin: 0;
            border-radius: 4px;
            accent-color: #1769aa;
            cursor: pointer;
        }
        .button, button { border: 0; border-radius: 6px; padding: 11px 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .button.secondary, button.secondary { background: #edf1f4; color: #16202a; }
        .button:disabled, button:disabled { opacity: .55; cursor: not-allowed; }

        .item-actions {
            display: inline-flex;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .item-action-button {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            transition: background .15s ease, border-color .15s ease, transform .1s ease;
        }

        .item-action-button svg {
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }

        .item-action-button.edit-item {
            color: #1769aa;
            background: #edf6fd;
            border-color: #cfe5f6;
        }

        .item-action-button.delete-item {
            color: #b42318;
            background: #fff1f0;
            border-color: #f4cfcc;
        }

        .item-action-button:active {
            transform: scale(.94);
        }

        @media (hover: hover) and (pointer: fine) {
            .item-action-button.edit-item:hover {
                background: #dceefa;
            }

            .item-action-button.delete-item:hover {
                background: #fde3e1;
            }
        }        .scanner, .items { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; margin-top: 16px; }
        .scanner { padding: 14px; max-width: 760px; }

        .scanner-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .torch-button {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0;
            border: 1px solid #e0e6ea;
            border-radius: 9px;
            background: #f7f9fa;
            color: #52616d;
            box-shadow: none;
            transition: background .15s ease, color .15s ease, border-color .15s ease, transform .1s ease;
        }

        .torch-button svg {
            width: 19px;
            height: 19px;
            stroke-width: 2;
        }

        .torch-button.is-on {
            color: #9a6700;
            background: #fff8df;
            border-color: #eadba4;
        }

        .torch-button:active {
            transform: scale(.94);
        }        .camera-frame { position: relative; background: #101820; border-radius: 6px; overflow: hidden; aspect-ratio: 16 / 10; max-height: 42vh; margin-top: 10px; }
        #camera-video { display: block; width: 100%; height: 100%; object-fit: cover; }
        .scan-guide { position: absolute; inset: 20% 12%; border: 2px solid #8fd3ff; border-radius: 8px; pointer-events: none; }
        .scan-line {
            position: absolute;
            z-index: 3;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 1px;
            transform: translateY(-50%);
            background: linear-gradient(
                90deg,
                rgba(220, 45, 45, .25) 0%,
                rgba(255, 95, 95, 1) 35%,
                rgba(255, 255, 255, .95) 50%,
                rgba(255, 95, 95, 1) 65%,
                rgba(220, 45, 45, .25) 100%
            );
            background-size: 90px 100%;
            box-shadow: 0 0 2px rgba(220, 45, 45, .45);
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s ease;
            animation: scanner-wave .85s linear infinite;
        }

        .camera-frame.camera-active .scan-line {
            opacity: .95;
        }

        @keyframes scanner-wave {
            from { background-position: 0 0; }
            to { background-position: 90px 0; }
        }
        .camera-status { color: #d8e5ee; text-align: center; padding: 10px; margin: 0; font-size: 14px; }
        .action-area { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .camera-actions { display: flex; gap: 6px; flex: 0 0 auto; }
        .manual-link { display: inline-flex; align-items: center; gap: 6px; padding: 6px 3px; color: #52616d; background: transparent; font-size: 13px; font-weight: 650; }
        .manual-link svg { width: 17px; height: 17px; }
        .icon-button { display: inline-grid; place-items: center; width: 44px; height: 44px; padding: 0; }
        .icon-button svg { width: 20px; height: 20px; }
        .detected, .duplicate { border-top: 1px solid #dce2e7; margin-top: 12px; padding-top: 12px; }
        .detected-header { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
        .detected-code { overflow-wrap: anywhere; font-size: clamp(20px, 5vw, 28px); line-height: 1.15; }
        .quantity-label { display: block; font-weight: 700; margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border: 1px solid #b8c2cc; border-radius: 6px; font: inherit; }
        #detected-quantity { font-size: 16px; max-width: 180px; }
        .detected-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }

        body.quantity-modal-open {
            overflow: hidden;
        }

        .quantity-modal-overlay {
            position: fixed;
            z-index: 7000;
            inset: 0;
            display: grid;
            place-items: center;
            overscroll-behavior: contain;
            margin: 0;
            padding: 16px;
            border: 0;
            background: rgba(12, 20, 27, .52);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            animation: quantity-overlay-in .16s ease-out;
        }

        .quantity-modal {
            width: min(100%, 420px);
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dce2e7;
            border-radius: 18px;
            box-shadow: 0 22px 55px rgba(13, 25, 34, .24);
            animation: quantity-modal-in .2s cubic-bezier(.2, .8, .2, 1);
        }

        .quantity-modal-header {
            padding: 20px 20px 15px;
            border-bottom: 1px solid #edf1f4;
        }

        .quantity-modal-title-wrap {
            display: grid;
            gap: 5px;
            min-width: 0;
        }

        .quantity-modal-kicker {
            color: #607080;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .045em;
        }

        .quantity-modal .detected-code {
            display: block;
            font-size: clamp(22px, 6vw, 29px);
            font-weight: 750;
            line-height: 1.15;
            color: #16202a;
            overflow-wrap: anywhere;
        }

        .quantity-modal-content {
            padding: 17px 20px 20px;
        }

        .quantity-modal-label {
            display: block;
            margin-bottom: 8px;
            color: #607080;
            font-size: 13px;
            font-weight: 700;
        }

        .quantity-control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .quantity-modal .quantity-bar {
            margin-left: 0;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .quantity-modal .quantity-bar .icon-button {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #edf1f4;
            color: #16202a;
        }

        .quantity-modal #detected-quantity {
            width: 92px;
            height: 52px;
            padding: 6px 10px;
            border: 1px solid #aebbc5;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            line-height: 1;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            background: #fff;
            color: #16202a;
            caret-color: #1769aa;
        }

        .quantity-modal #detected-quantity:focus {
            outline: 3px solid rgba(23, 105, 170, .14);
            border-color: #1769aa;
        }

        .modal-cancel-button {
            display: inline-grid;
            place-items: center;
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            padding: 0;
            border: 1px solid #f0c9c5;
            border-radius: 50%;
            background: #fff0ef;
            color: #b42318;
            box-shadow: none;
            transition: transform .12s ease, background .15s ease;
        }

        .modal-cancel-button svg {
            width: 21px;
            height: 21px;
            stroke-width: 2.3;
        }

        .modal-cancel-button:active {
            transform: scale(.92);
            background: #fbdedb;
        }

        .modal-save-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 50px;
            margin-top: 18px;
            padding: 12px 18px;
            border: 0;
            border-radius: 12px;
            background: #28733f;
            color: #fff;
            font-size: 15px;
            font-weight: 750;
            box-shadow: 0 7px 18px rgba(40, 115, 63, .18);
            transition: transform .12s ease, filter .15s ease;
        }

        .modal-save-button svg {
            width: 19px;
            height: 19px;
        }

        .modal-save-button:active {
            transform: scale(.985);
            filter: brightness(.94);
        }

        .quantity-modal .duplicate {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e5e9ed;
        }

        .quantity-modal .duplicate > strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
            color: #16202a;
        }

        .quantity-modal .duplicate > p {
            margin: 7px 0;
            color: #394955;
            line-height: 1.45;
        }

        .duplicate-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
            margin-top: 16px;
        }

        .duplicate-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-width: 0;
            min-height: 46px;
            padding: 10px 11px;
            border: 1px solid transparent;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 750;
            line-height: 1;
            box-shadow: none;
            transition:
                transform .12s ease,
                background .15s ease,
                border-color .15s ease;
        }

        .duplicate-action svg {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            stroke-width: 2.3;
        }

        .duplicate-action span {
            min-width: 0;
            white-space: nowrap;
        }

        .duplicate-add {
            color: #28733f;
            background: #edf8f0;
            border-color: #cbe7d2;
        }

        .duplicate-replace {
            color: #1769aa;
            background: #edf6fd;
            border-color: #cfe5f6;
        }

        .duplicate-cancel {
            color: #b42318;
            background: #fff0ef;
            border-color: #f2cdca;
        }

        .duplicate-action:active {
            transform: scale(.96);
        }

        @media (hover: hover) and (pointer: fine) {
            .duplicate-add:hover {
                background: #dff2e4;
            }

            .duplicate-replace:hover {
                background: #dceefa;
            }

            .duplicate-cancel:hover {
                background: #fbe1df;
            }
        }

        @keyframes quantity-overlay-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes quantity-modal-in {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.97);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .quantity-modal-overlay,
            .quantity-modal {
                animation: none;
            }
        }
        .manual-code-row { display: flex; gap: 8px; align-items: center; }
        .manual-code-row input { min-width: 0; }
        .quantity-bar { display: inline-flex; align-items: center; gap: 5px; margin-left: auto; }
        .quantity-bar input { width: 58px; height: 44px; padding: 8px 4px; text-align: center; font-size: 16px; }
        .quantity-bar .icon-button { background: #edf1f4; color: #16202a; }
        .quantity-bar .save-button { background: #28733f; color: #fff; }
        .message { min-height: 20px; margin: 10px 0 0; font-size: 14px; color: #28733f; }
        .message.error { color: #8b3e12; }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .stat { flex: 0 1 150px; background: #fff; border: 1px solid #dce2e7; border-radius: 6px; padding: 8px 11px; }
        .stat small { display: block; color: #607080; font-size: 12px; }
        .stat strong { display: block; font-size: 20px; margin-top: 3px; }
        .items { padding: 14px; }
        .items > summary { cursor: pointer; font-weight: 700; }
        .items[open] > summary { margin-bottom: 12px; }
        .items > summary::marker { color: #1769aa; }
        .items-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
        input[type=search] { max-width: 320px; }
        .table-wrap { overflow-x: auto; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e5e9ed; text-align: left; vertical-align: middle; }
        th { color: #607080; font-size: 12px; }
        .empty { color: #607080; }
        [hidden] { display: none !important; }
        .close-action { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; background: transparent; color: #8b3e12; border: 1px solid #e7c7b2; }
        .close-action svg { width: 17px; height: 17px; }
        @media (max-width: 600px) {
            .app { padding: 14px; }
            .top .toolbar { width: 100%; }
            .top .toolbar > * { flex: 1 1 150px; text-align: center; }
            .scanner, .items { padding: 14px; }
            .camera-frame { margin-left: -14px; margin-right: -14px; border-radius: 0; width: calc(100% + 28px); }
            .action-area { gap: 8px; }
            .quantity-bar { margin-left: 0; }

            .quantity-modal-overlay {
                padding: 12px;
            }

            .quantity-modal {
                width: 100%;
                max-height: calc(100% - 16px);
                border-radius: 16px;
            }

            .quantity-control-row {
                align-items: center;
            }

            .quantity-modal #detected-quantity {
                width: 100px;
                height: 54px;
                font-size: 26px;
            }

            .quantity-modal-header {
                padding: 18px 17px 14px;
            }

            .quantity-modal-content {
                padding: 16px 17px 18px;
            }

            .duplicate-actions {
                gap: 7px;
            }

            .duplicate-action {
                min-height: 44px;
                padding: 9px 7px;
                gap: 5px;
                font-size: 12px;
            }
            .manual-code-row .button { flex: 0 0 auto; }
            .detected-actions .button, .detected-actions button { flex: 1 1 140px; }
            input[type=search] { max-width: none; }
        }
        @media (max-width: 360px) {
            .duplicate-actions {
                grid-template-columns: 1fr;
            }

            .duplicate-action {
                width: 100%;
                font-size: 13px;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
<div id="action-toast" class="action-toast success" role="status" aria-live="polite" aria-atomic="true">
    <div class="action-toast-content">
        <span id="action-toast-icon" class="action-toast-icon" aria-hidden="true"></span>
        <span id="action-toast-message" class="action-toast-message"></span>
    </div>
    <div class="action-toast-progress-track" aria-hidden="true">
        <div id="action-toast-progress" class="action-toast-progress"></div>
    </div>
</div>

<div class="app" data-inventory="{{ $inventory->uuid }}" data-item-url="{{ route('inventories.items.store', $inventory->uuid) }}" data-completed="{{ $inventory->isCompleted() ? '1' : '0' }}">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link active" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <div class="top">
        <div><h1>{{ $inventory->name }}</h1><p class="meta">{{ $inventory->zone ?: 'Zone non renseignee' }} · <span id="status">{{ $inventory->isCompleted() ? 'Termine' : 'En cours' }}</span></p></div>
        <div class="toolbar">
            <form class="export-form" method="get" action="{{ route('inventories.export', $inventory->uuid) }}">
                <button class="button secondary" type="submit">Exporter Excel</button>
                <label class="export-option">
                    <input type="checkbox" name="include_qr" value="1">
                    <span>Inclure les QR codes dans Excel</span>
                </label>
            </form>
            @if ($inventory->isCompleted())
                <form method="post" action="{{ route('inventories.reopen', $inventory->uuid) }}">@csrf<button class="secondary" type="submit">Rouvrir</button></form>
            @endif
        </div>
    </div>

    @if (!$inventory->isCompleted())
        <section class="scanner" aria-labelledby="scanner-title">
            <div class="scanner-heading"><h2 id="scanner-title">Scanner un article</h2><button id="torch-toggle" class="torch-button" type="button" aria-label="Allumer le flash" aria-pressed="false" title="Allumer le flash" hidden><i data-lucide="Flashlight"></i></button></div>
            <div class="camera-frame"><video id="camera-video" playsinline muted aria-label="Apercu de la camera"></video><div class="scan-guide"></div><div class="scan-line" aria-hidden="true"></div><p id="camera-status" class="camera-status">Placez le QR code ou le code-barres devant la camera.</p></div>
            <div class="action-area"><div class="camera-actions"><button id="start-camera" class="icon-button" type="button" aria-label="Demarrer la camera" title="Demarrer la camera"><i data-lucide="Camera"></i></button><button id="retry-camera" class="icon-button secondary" type="button" aria-label="Reessayer la camera" title="Reessayer" hidden><i data-lucide="RefreshCw"></i></button><button id="manual-toggle" class="manual-link" type="button" aria-expanded="false"><i data-lucide="Keyboard"></i><span>Saisir le code article manuellement</span></button></div></div>
            <div id="manual-entry" hidden><form id="item-form"><label class="quantity-label" for="code_article">Code Article</label><div class="manual-code-row"><input id="code_article" name="code_article" autocomplete="off" required><button class="button secondary" type="submit">Continuer</button></div></form></div>
            <div id="detected-panel" class="detected quantity-modal-overlay" hidden>
                <div class="quantity-modal" role="dialog" aria-modal="true" aria-labelledby="quantity-modal-title">
                    <div class="quantity-modal-header">
                        <div class="quantity-modal-title-wrap">
                            <span id="quantity-modal-title" class="quantity-modal-kicker">Article détecté</span>
                            <strong id="detected-code" class="detected-code"></strong>
                        </div>
                    </div>

                    <div class="quantity-modal-content">
                        <span class="quantity-modal-label">Quantité</span>

                        <div class="quantity-control-row">
                            <div class="quantity-bar">
                                <button class="icon-button" type="button" data-detected-step="-1" aria-label="Diminuer la quantite" title="Diminuer">
                                    <i data-lucide="Minus"></i>
                                </button>

                                <input id="detected-quantity" type="text" inputmode="numeric" pattern="[0-9]*" enterkeyhint="done" autocomplete="off" value="1" aria-label="Quantite">

                                <button class="icon-button" type="button" data-detected-step="1" aria-label="Augmenter la quantite" title="Augmenter">
                                    <i data-lucide="Plus"></i>
                                </button>
                            </div>

                            <button id="cancel-detected" class="modal-cancel-button" type="button" aria-label="Annuler la saisie" title="Annuler">
                                <i data-lucide="X"></i>
                            </button>
                        </div>

                        <button id="save-detected" class="modal-save-button" type="button">
                            <i data-lucide="Save"></i>
                            <span>Enregistrer</span>
                        </button>

                        <div id="duplicate-panel" class="duplicate" hidden></div>
                    </div>
                </div>
            </div>
            <p id="message" class="message" role="status"></p>
        </section>
    @else
        <section class="scanner"><h2>Inventaire termine</h2><p class="meta">La camera et la modification des articles sont desactivees. Vous pouvez rouvrir cet inventaire pour continuer.</p></section>
    @endif

    <div class="summary"><div class="stat"><small>References</small><strong id="items-count">{{ $inventory->items->count() }}</strong></div><div class="stat"><small>Quantite totale</small><strong id="total-quantity">{{ $inventory->items->sum('quantity') }}</strong></div></div>
    @if (!$inventory->isCompleted())
        <form id="complete-form" method="post" action="{{ route('inventories.complete', $inventory->uuid) }}">@csrf<button class="close-action" type="submit"><i data-lucide="CircleStop"></i>Cloturer l'inventaire</button></form>
    @endif

    <details id="items-section" class="items"><summary><i data-lucide="ChevronDown"></i>Voir les articles comptes ({{ $inventory->items->count() }})</summary><div class="items-toolbar"><h2>Articles comptes</h2><input id="search" type="search" placeholder="Rechercher un code" aria-label="Rechercher un code"></div><div class="table-wrap"><table><thead><tr><th>Code Article</th><th>Quantite</th><th>QR</th>@if (!$inventory->isCompleted())<th>Actions</th>@endif</tr></thead><tbody id="items-body">@foreach ($inventory->items as $item)<tr data-code="{{ strtolower($item->code_article) }}" data-item="{{ $item->uuid }}"><td>{{ $item->code_article }}</td><td class="quantity">{{ $item->quantity }}</td><td>Disponible a l'export</td>@if (!$inventory->isCompleted())<td><div class="actions item-actions"><button class="item-action-button edit-item" type="button" aria-label="Modifier l article" title="Modifier"><i data-lucide="Pencil"></i></button><button class="item-action-button delete-item" type="button" aria-label="Supprimer l article" title="Supprimer"><i data-lucide="Trash2"></i></button></div></td>@endif</tr>@endforeach</tbody></table></div><p id="empty-items" class="empty" @if ($inventory->items->isNotEmpty()) hidden @endif>Aucun article compte.</p></details>
</div>
</body>
</html>
