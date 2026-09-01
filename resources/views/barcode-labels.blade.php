<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generateur d'etiquettes</title>
    @vite(['resources/js/app.js'])
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #172033;
            background: #f5f7fb;
        }

        * { box-sizing: border-box; }

        html,
        body {
            max-width: 100%;
        }

        body { margin: 0; min-height: 100vh; background: #f5f7fb; }
        button, input { font: inherit; }

        .page {
            width: min(1180px, 100%);
            margin: 0 auto;
            padding: 30px 18px 42px;
            min-width: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: #2563eb;
            background: #eaf2ff;
            border: 1px solid #cfe1ff;
        }

        h1 { margin: 0; font-size: 24px; line-height: 1.2; letter-spacing: 0; }
        h2 { margin: 0; font-size: 16px; letter-spacing: 0; }

        .subtitle {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        main {
            display: grid;
            grid-template-columns: minmax(330px, 440px) minmax(360px, 1fr);
            gap: 18px;
            align-items: start;
            min-width: 0;
        }

        .panel, .preview-panel {
            min-width: 0;
            background: #fff;
            border: 1px solid #dfe6f1;
            border-radius: 8px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, .08);
        }

        .panel { min-width: 0; }
        .panel-body { display: grid; gap: 18px; padding: 20px; }
        .section { display: grid; gap: 12px; }

        .section-head, .preview-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .dropzone {
            min-width: 0;
            position: relative;
            display: grid;
            place-items: center;
            min-height: 136px;
            padding: 18px;
            border: 1.5px dashed #b8c2d8;
            border-radius: 8px;
            background: #fbfcff;
            text-align: center;
            transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
        }

        .dropzone.is-dragging, .dropzone:hover {
            border-color: #2563eb;
            background: #f8fbff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .08);
        }

        .file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .upload-state, .selected-state {
            display: grid;
            justify-items: center;
            gap: 8px;
        }

        .selected-state, .dropzone.has-file .upload-state { display: none; }
        .dropzone.has-file .selected-state { display: grid; }

        .icon-disc {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: #2563eb;
            background: #eaf2ff;
            border: 1px solid #cfe1ff;
        }

        .drop-title, .file-name {
            margin: 0;
            max-width: min(360px, 100%);
            font-weight: 800;
        }

        .drop-title {
            overflow-wrap: anywhere;
        }

        .file-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .drop-meta, .ready, .field-help, .metric {
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }

        .secondary-button, .ghost-button, .icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #27364f;
            padding: 9px 12px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
        }

        .secondary-button:focus-visible,
        .ghost-button:focus-visible,
        .icon-button:focus-visible,
        .primary-button:focus-visible,
        .action-primary:focus-visible,
        .action-secondary:focus-visible,
        .floating-element-toolbar button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .24);
            outline-offset: 2px;
        }

        .ghost-button { background: #f8fafc; }
        .icon-button { width: 38px; height: 38px; padding: 0; }

        .field { display: grid; gap: 7px; }
        .field, .grid-2 > * { min-width: 0; }

        .field label {
            font-size: 13px;
            font-weight: 800;
            color: #334155;
        }

        .text-input, .number-input {
            width: 100%;
            height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #172033;
            padding: 8px 10px;
        }

        .text-input:focus, .number-input:focus {
            outline: 3px solid rgba(37, 99, 235, .14);
            border-color: #2563eb;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .advanced {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fbfcff;
        }

        .advanced summary {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 12px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .advanced-body { padding: 0 12px 12px; }

        .metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            min-width: 0;
        }

        .metric {
            min-height: 48px;
            display: grid;
            gap: 2px;
            padding: 9px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            min-width: 0;
        }

        .metric strong {
            color: #172033;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .alert {
            display: flex;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #991b1b;
            font-size: 14px;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .result {
            display: grid;
            gap: 12px;
            padding: 14px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #14532d;
            border-radius: 8px;
        }

        .result-title { margin: 0; font-weight: 800; }
        .result-metrics { margin: 3px 0 0; color: #166534; font-size: 14px; }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .primary-button, .action-primary, .action-secondary {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border-radius: 8px;
            padding: 11px 14px;
            font-weight: 900;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .primary-button, .action-primary {
            border: 0;
            background: #2563eb;
            color: #fff;
        }

        .primary-button { width: 100%; font-size: 15px; }
        .primary-button:disabled { cursor: not-allowed; opacity: .55; }

        .action-secondary {
            background: #fff;
            color: #27364f;
            border: 1px solid #cbd5e1;
        }

        .spinner {
            display: none;
            width: 17px;
            height: 17px;
            border: 2px solid rgba(255, 255, 255, .45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        form.is-submitting .spinner { display: inline-block; }
        form.is-submitting .button-icon { display: none; }

        .preview-panel { padding: 18px; }
        .preview-head { margin-bottom: 14px; }

        .paper-wrap {
            display: grid;
            place-items: center;
            width: 100%;
            min-height: 620px;
            padding: 18px;
            overflow: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #eef2f7;
        }

        .a4-board {
            position: relative;
            width: min(100%, 445px);
            aspect-ratio: 210 / 297;
            flex: 0 0 auto;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .14);
            overflow: visible;
            user-select: none;
        }

        .guide-cell {
            position: absolute;
            border: 1px solid rgba(148, 163, 184, .38);
            background: rgba(148, 163, 184, .035);
            pointer-events: none;
        }

        .barcode-preview {
            position: absolute;
            display: grid;
            grid-template-rows: minmax(0, 1fr) 13px;
            gap: 2px;
        }

        .bars {
            height: 100%;
            background: repeating-linear-gradient(90deg, #111827 0 2px, transparent 2px 4px, #111827 4px 5px, transparent 5px 8px);
        }

        .code-text {
            overflow: hidden;
            color: #111827;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
        }

        .layout-error {
            min-height: 20px;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.4;
        }

        .info {
            padding: 14px 20px;
            border-top: 1px solid #dfe6f1;
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            line-height: 1.55;
        }

        .info ul { margin: 0; padding-left: 18px; }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 40;
            width: 100vw;
            height: 100vh;
            background: #f1f5f9;
        }

        .modal.is-open {
            display: flex;
        }

        .designer-shell {
            width: 100vw;
            height: 100vh;
            display: flex;
            min-width: 0;
        }

        .designer-sidebar {
            width: 236px;
            height: 100vh;
            flex: 0 0 236px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 14px;
            border-right: 1px solid #d8e0ec;
            background: #fff;
            box-shadow: 8px 0 24px rgba(15, 23, 42, .04);
            overflow-y: auto;
            overflow-x: visible;
            z-index: 2;
        }

        .designer-title {
            margin: -6px 0 0;
            color: #172033;
            font-size: 14px;
            font-weight: 900;
            line-height: 1.25;
        }

        .designer-group {
            display: grid;
            gap: 7px;
        }

        .designer-sidebar .secondary-button,
        .designer-sidebar .ghost-button,
        .designer-sidebar .action-primary,
        .designer-sidebar .action-secondary {
            width: 100%;
            min-height: 36px;
            justify-content: flex-start;
            padding: 8px 10px;
            font-size: 13px;
            box-shadow: none;
        }

        .designer-sidebar .action-primary {
            justify-content: center;
        }

        .designer-sidebar .action-secondary {
            justify-content: center;
        }

        .designer-sidebar button:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .designer-option {
            min-height: 36px;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border: 1px solid #d8e0ec;
            border-radius: 8px;
            color: #334155;
            background: #f8fafc;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .designer-actions-bottom {
            display: grid;
            gap: 8px;
            margin-top: auto;
        }

        .designer-body {
            position: relative;
            flex: 1;
            min-width: 0;
            min-height: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: auto;
            background: #eef2f7;
        }

        .designer-board {
            width: var(--designer-board-width, 515px);
            height: var(--designer-board-height, 728px);
            max-width: none;
            min-width: 0;
            aspect-ratio: auto;
        }

        .designer-element {
            cursor: move;
            z-index: 3;
        }

        .designer-element.is-selected {
            outline: 1.5px solid #2563eb;
            outline-offset: 2px;
            z-index: 4;
        }

        .designer-moveable,
        .designer-body .moveable-control-box {
            z-index: 80 !important;
            pointer-events: auto;
        }

        .designer-moveable .moveable-line,
        .designer-body .moveable-line {
            background: #2563eb !important;
        }

        .designer-moveable .moveable-control,
        .designer-body .moveable-control {
            width: 10px !important;
            height: 10px !important;
            margin-top: -5px !important;
            margin-left: -5px !important;
            border: 2px solid #fff !important;
            border-radius: 999px !important;
            background: #2563eb !important;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .35);
        }

        .designer-moveable .moveable-n,
        .designer-moveable .moveable-s,
        .designer-body .moveable-n,
        .designer-body .moveable-s {
            cursor: ns-resize !important;
        }

        .designer-moveable .moveable-e,
        .designer-moveable .moveable-w,
        .designer-body .moveable-e,
        .designer-body .moveable-w {
            cursor: ew-resize !important;
        }

        .designer-moveable .moveable-nw,
        .designer-moveable .moveable-se,
        .designer-body .moveable-nw,
        .designer-body .moveable-se {
            cursor: nwse-resize !important;
        }

        .designer-moveable .moveable-ne,
        .designer-moveable .moveable-sw,
        .designer-body .moveable-ne,
        .designer-body .moveable-sw {
            cursor: nesw-resize !important;
        }

        .floating-element-toolbar {
            position: absolute;
            z-index: 30;
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 8px 18px rgba(15, 23, 42, .18);
        }

        .floating-element-toolbar button {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 0;
            border-radius: 6px;
            color: #1d4ed8;
            background: transparent;
            cursor: pointer;
        }

        .floating-element-toolbar button:hover {
            background: #eaf2ff;
        }

        .floating-element-toolbar svg {
            width: 16px;
            height: 16px;
        }

        .order-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            min-width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            pointer-events: none;
        }

        .toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        svg, .lucide {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
            stroke-width: 2;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 900px) {
            main { grid-template-columns: 1fr; }
            .paper-wrap { min-height: 460px; }
        }

        @media (max-width: 1024px) and (min-width: 641px) {
            .page { padding: 24px 16px 34px; }
            main { gap: 14px; }
            .panel-body { padding: 18px; gap: 14px; }
            .designer-sidebar {
                width: 204px;
                flex-basis: 204px;
                gap: 14px;
                padding: 12px;
            }
            .designer-sidebar .secondary-button,
            .designer-sidebar .ghost-button,
            .designer-sidebar .action-primary,
            .designer-sidebar .action-secondary,
            .designer-option {
                min-height: 38px;
                font-size: 12px;
            }
        }

        @media (max-width: 640px) {
            .page { padding: 16px 12px 24px; }
            .brand { gap: 10px; margin-bottom: 12px; }
            .mark { width: 36px; height: 36px; border-radius: 8px; }
            h1 { font-size: 20px; }
            h2 { font-size: 15px; }
            .subtitle { font-size: 12px; }
            main { grid-template-columns: minmax(0, 1fr); gap: 12px; }
            .panel-body, .preview-panel { padding: 16px; }
            .section { gap: 10px; }
            .dropzone { min-height: 118px; padding: 14px; }
            .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
            .metrics { grid-template-columns: 1fr; gap: 8px; }
            .actions a { width: 100%; }
            .action-primary,
            .action-secondary,
            .primary-button,
            .secondary-button,
            .ghost-button {
                font-size: 13px;
            }
            .preview-head { align-items: stretch; flex-direction: column; }
            .paper-wrap { min-height: 360px; padding: 12px; }
            .a4-board { width: min(100%, 315px); }
            .designer-sidebar {
                width: 56px;
                flex-basis: 56px;
                gap: 8px;
                padding: 8px;
            }
            .designer-title,
            .designer-button-label {
                display: none;
            }
            .designer-sidebar .secondary-button,
            .designer-sidebar .ghost-button,
            .designer-sidebar .action-primary,
            .designer-sidebar .action-secondary,
            .designer-option {
                width: 40px;
                min-height: 40px;
                padding: 0;
                justify-content: center;
            }
            .designer-option {
                position: relative;
            }
            .designer-option input {
                position: absolute;
                inset: auto 3px 3px auto;
                width: 12px;
                height: 12px;
            }
            .designer-body {
                padding: 12px;
            }
            .floating-element-toolbar button {
                width: 40px;
                height: 40px;
            }
            .designer-moveable .moveable-control,
            .designer-body .moveable-control {
                width: 14px !important;
                height: 14px !important;
                margin-top: -7px !important;
                margin-left: -7px !important;
            }
        }
    </style>
</head>
<body>
<script>
    window.BarcodeDesignerDefaults = @json($defaultLayout);
</script>

<div class="page">
    <header class="brand" aria-label="Application">
        <div class="mark" aria-hidden="true">
            <i data-lucide="ScanBarcode"></i>
        </div>
        <div>
            <h1>Generateur d'etiquettes</h1>
            <p class="subtitle">Code 128 vectoriel sur page A4 prete a imprimer.</p>
        </div>
    </header>

    <main>
        <section class="panel" aria-label="Parametres">
            <div class="panel-body">
                @foreach (['excel_file', 'excel_column', 'layout_json'] as $field)
                    @error($field)
                        <div class="alert" role="alert">
                            <i data-lucide="TriangleAlert"></i>
                            <div>{{ $message }}</div>
                        </div>
                    @enderror
                @endforeach

                @if (session('result'))
                    <section class="result" aria-label="PDF genere">
                        <div>
                            <p class="result-title">PDF genere avec succes</p>
                            <p class="result-metrics">{{ number_format(session('result.labels'), 0, ',', ' ') }} etiquettes &middot; {{ session('result.pages') }} pages A4</p>
                        </div>
                        <div class="actions">
                            <a class="action-primary" href="{{ route('labels.pdf', ['token' => session('result.token'), 'download' => 1]) }}">
                                <i data-lucide="Download"></i>
                                Telecharger
                            </a>
                            <a class="action-secondary" href="{{ route('labels.pdf', ['token' => session('result.token')]) }}" target="_blank" rel="noopener">
                                <i data-lucide="Printer"></i>
                                Imprimer
                            </a>
                        </div>
                    </section>
                @endif

                <form id="upload-form" action="{{ route('labels.generate') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <input id="layout_json" name="layout_json" type="hidden" value="{{ old('layout_json') }}">

                    <div class="section">
                        <h2>Fichier Excel</h2>
                        <label id="dropzone" class="dropzone" for="excel_file">
                            <input id="excel_file" class="file-input" name="excel_file" type="file" accept=".xlsx,.xls" required>
                            <span class="upload-state">
                                <span class="icon-disc" aria-hidden="true">
                                    <i data-lucide="FileSpreadsheet"></i>
                                </span>
                                <span class="drop-title">Glissez-deposez votre fichier</span>
                                <span id="choose-file-button" class="secondary-button" role="button" tabindex="0" aria-label="Choisir un fichier Excel">
                                    <i data-lucide="Upload"></i>
                                    Choisir un fichier
                                </span>
                                <span class="drop-meta">XLSX ou XLS</span>
                            </span>
                            <span class="selected-state">
                                <span id="file-name" class="file-name"></span>
                                <span class="ready">Fichier pret</span>
                                <span id="change-file-button" class="secondary-button" role="button" tabindex="0" aria-label="Modifier le fichier Excel">Modifier</span>
                            </span>
                        </label>

                        <div class="field">
                            <label for="excel_column">Nom de la colonne Excel</label>
                            <input id="excel_column" class="text-input" name="excel_column" type="text" value="{{ old('excel_column', 'Code Article') }}" required maxlength="120">
                        </div>
                    </div>

                    <div class="section">
                        <div class="section-head">
                            <h2>Disposition rapide</h2>
                            <button id="open-designer" class="ghost-button" type="button">
                                <i data-lucide="SquarePen"></i>
                                Personnaliser
                            </button>
                        </div>

                        <div class="grid-2">
                            <div class="field">
                                <label for="layout-columns">Colonnes</label>
                                <input id="layout-columns" class="number-input layout-field" type="number" min="1" max="50" step="1" value="3" data-key="columns">
                            </div>
                            <div class="field">
                                <label for="layout-rows">Lignes</label>
                                <input id="layout-rows" class="number-input layout-field" type="number" min="1" max="80" step="1" value="8" data-key="rows">
                            </div>
                            <div class="field">
                                <label for="layout-gap-x">Espacement horizontal</label>
                                <input id="layout-gap-x" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="gapXMm">
                            </div>
                            <div class="field">
                                <label for="layout-gap-y">Espacement vertical</label>
                                <input id="layout-gap-y" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="gapYMm">
                            </div>
                        </div>

                        <details class="advanced">
                        <summary><i data-lucide="Settings"></i> Marges avancees</summary>
                            <div class="advanced-body grid-2">
                                <div class="field">
                                    <label for="layout-margin-top">Haut</label>
                                    <input id="layout-margin-top" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="marginTopMm">
                                </div>
                                <div class="field">
                                    <label for="layout-margin-right">Droite</label>
                                    <input id="layout-margin-right" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="marginRightMm">
                                </div>
                                <div class="field">
                                    <label for="layout-margin-bottom">Bas</label>
                                    <input id="layout-margin-bottom" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="marginBottomMm">
                                </div>
                                <div class="field">
                                    <label for="layout-margin-left">Gauche</label>
                                    <input id="layout-margin-left" class="number-input layout-field" type="number" min="0" step="0.1" value="0" data-key="marginLeftMm">
                                </div>
                            </div>
                        </details>

                        <div class="metrics" aria-label="Calculs">
                            <div class="metric">Etiquettes / page <strong id="metric-slots">24</strong></div>
                            <div class="metric">Zone etiquette <strong id="metric-size">70 x 37.125 mm</strong></div>
                        </div>
                        <div id="layout-error" class="layout-error" role="status"></div>
                    </div>

                    <button id="submit-button" class="primary-button" type="submit">
                        <span class="spinner" aria-hidden="true"></span>
                        <i class="button-icon" data-lucide="Barcode"></i>
                        <span id="submit-label">Generer le PDF</span>
                    </button>
                </form>
            </div>

            <div class="info" aria-label="Parametres d'impression">
                <ul>
                    <li>Echelle : 100 %</li>
                    <li>Taille reelle / Actual Size</li>
                    <li>Desactiver Ajuster a la page</li>
                </ul>
            </div>
        </section>

        <section class="preview-panel" aria-label="Apercu A4">
            <div class="preview-head">
                <h2>Apercu A4</h2>
                <span id="preview-mode" class="field-help">Disposition rapide</span>
            </div>
            <div class="paper-wrap">
                <div id="a4-preview" class="a4-board" aria-label="Apercu de la page A4"></div>
            </div>
        </section>
    </main>
</div>

<div id="designer-modal" class="modal" aria-hidden="true">
    <div class="designer-shell" role="dialog" aria-modal="true" aria-labelledby="designer-title">
        <aside class="designer-sidebar" aria-label="Outils du designer">
            <button class="ghost-button" type="button" data-close-designer title="Retour" aria-label="Retour">
                <i data-lucide="ArrowLeft"></i>
                <span class="designer-button-label">Retour</span>
            </button>

            <h2 id="designer-title" class="designer-title">Designer d'etiquettes</h2>

            <div class="designer-group">
                <button id="designer-add" class="secondary-button" type="button" title="Ajouter" aria-label="Ajouter">
                    <i data-lucide="Plus"></i>
                    <span class="designer-button-label">Ajouter</span>
                </button>
                <button id="designer-duplicate" class="secondary-button" type="button" title="Dupliquer" aria-label="Dupliquer">
                    <i data-lucide="Copy"></i>
                    <span class="designer-button-label">Dupliquer</span>
                </button>
                <button id="designer-delete" class="secondary-button" type="button" title="Supprimer" aria-label="Supprimer">
                    <i data-lucide="Trash2"></i>
                    <span class="designer-button-label">Supprimer</span>
                </button>
            </div>

            <div class="designer-group">
                <label class="designer-option" for="designer-snap" title="Grille" aria-label="Grille">
                    <i data-lucide="Grid3X3"></i>
                    <input id="designer-snap" type="checkbox" checked>
                    <span class="designer-button-label">Grille</span>
                </label>
                <label class="designer-option" for="designer-align" title="Alignement" aria-label="Alignement">
                    <i data-lucide="Magnet"></i>
                    <input id="designer-align" type="checkbox" checked>
                    <span class="designer-button-label">Alignement</span>
                </label>
            </div>

            <div class="designer-group">
                <button id="designer-fullscreen" class="secondary-button" type="button" title="Plein ecran" aria-label="Plein ecran">
                    <i data-lucide="Maximize2"></i>
                    <span id="designer-fullscreen-label" class="designer-button-label">Plein ecran</span>
                </button>
            </div>

            <div id="designer-error" class="layout-error" role="status"></div>

            <div class="designer-actions-bottom">
                <button class="action-secondary" type="button" data-close-designer title="Annuler" aria-label="Annuler">
                    <i data-lucide="X"></i>
                    <span class="designer-button-label">Annuler</span>
                </button>
                <button id="designer-save" class="action-primary" type="button" title="Enregistrer" aria-label="Enregistrer">
                    <i data-lucide="Save"></i>
                    <span class="designer-button-label">Enregistrer</span>
                </button>
            </div>
        </aside>

        <div id="designer-workspace" class="designer-body" aria-label="Espace de travail">
            <div id="designer-board" class="a4-board designer-board" aria-label="Designer A4"></div>
        </div>
    </div>
</div>
</body>
</html>
