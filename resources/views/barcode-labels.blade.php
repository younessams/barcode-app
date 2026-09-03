<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generateur d'etiquettes</title>
    @vite(['resources/js/app.js'])
    <style>
        :root { font-family: Inter, system-ui, sans-serif; color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }
        .app { max-width: 1280px; margin: 0 auto; padding: 28px; }
        header { margin-bottom: 22px; } h1 { margin: 0; font-size: 25px; } .subtitle, .field-help { color: #607080; }
        .top-nav { display: inline-flex; gap: 3px; margin-bottom: 20px; padding: 3px; background: #e9eef2; border-radius: 8px; } .top-nav a { color: #52616d; text-decoration: none; font-size: 13px; font-weight: 700; padding: 7px 12px; border-radius: 6px; } .top-nav a.active { color: #16202a; background: #fff; box-shadow: 0 1px 3px #1824311c; }
        main { display: grid; grid-template-columns: minmax(300px, 390px) minmax(0, 1fr); gap: 24px; align-items: start; }
        .panel, .preview-panel { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; }
        .panel-body, .preview-panel { padding: 22px; } .section { border-top: 1px solid #e5e9ed; padding-top: 18px; margin-top: 18px; }
        h2 { font-size: 16px; margin: 0 0 12px; } label { display: block; font-weight: 650; font-size: 13px; margin-bottom: 6px; }
        .dropzone { border: 1px dashed #9eabb7; border-radius: 8px; padding: 18px; text-align: center; cursor: pointer; }
        .dropzone.is-dragging { border-color: #1769aa; background: #eef7ff; } .file-input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .upload-state, .selected-state { display: grid; gap: 8px; justify-items: center; } .selected-state { display: none; }
        .dropzone.has-file .upload-state { display: none; } .dropzone.has-file .selected-state { display: grid; }
        .icon-disc { color: #1769aa; } .secondary-button, .primary-button, .action-primary, .action-secondary { border: 0; border-radius: 6px; padding: 10px 13px; display: inline-flex; gap: 7px; align-items: center; justify-content: center; font-weight: 650; cursor: pointer; text-decoration: none; }
        .secondary-button, .action-secondary { background: #edf1f4; color: #16202a; } .primary-button, .action-primary { width: 100%; background: #1769aa; color: #fff; margin-top: 20px; }
        .text-input, select { width: 100%; min-width: 0; padding: 10px; border: 1px solid #b8c2cc; border-radius: 6px; background: #fff; font: inherit; }
        .field { margin-top: 14px; } .field-help { font-size: 12px; font-weight: 400; } .preset-help { margin: -4px 0 10px; font-size: 12px; color: #607080; }
        .metrics { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; font-size: 12px; color: #607080; } .metric { border: 1px solid #e0e5e9; padding: 8px; border-radius: 5px; } .metric strong { color: #16202a; }
        .alert { display: flex; gap: 8px; padding: 11px; color: #8b3e12; background: #fff4e9; border: 1px solid #f0c89d; margin-bottom: 12px; font-size: 13px; }
        .result { display: grid; gap: 12px; padding: 14px; background: #eef8f0; border: 1px solid #b8dbbf; border-radius: 6px; margin-bottom: 18px; } .result p { margin: 0; } .result-title { font-weight: 700; } .result-metrics { font-size: 13px; color: #41624a; } .actions { display: flex; gap: 8px; flex-wrap: wrap; } .actions a { width: auto; }
        .preview-head { display: flex; justify-content: space-between; gap: 12px; align-items: baseline; margin-bottom: 14px; } .paper-wrap { overflow: auto; padding: 4px; }
        .a4-board { width: min(100%, 720px); aspect-ratio: 210 / 297; margin: 0 auto; background: #fff; box-shadow: 0 2px 12px #18243122; position: relative; overflow: visible; }
        .guide { position: absolute; border: 1px solid #d4dce3; background: #fafbfc; } .placeholder { position: absolute; background: repeating-linear-gradient(90deg, #263746 0 2px, transparent 2px 4px); } .placeholder-text { position: absolute; font-size: 7px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .warning { color: #8b3e12; font-size: 12px; margin-top: 10px; }
        .code-types { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .code-type { position: relative; } .code-type input { position: absolute; opacity: 0; } .code-type label { margin: 0; padding: 11px; border: 1px solid #b8c2cc; border-radius: 6px; cursor: pointer; } .code-type input:checked + label { border-color: #1769aa; box-shadow: 0 0 0 2px #1769aa22; background: #f1f8fe; } .code-type-title { display: block; } .code-type-help { display: block; font-size: 11px; color: #607080; font-weight: 400; margin-top: 3px; }
        .qr-preview { display: grid; grid-template-columns: repeat(29, 1fr); gap: 1px; background: #fff; padding: 7%; } .qr-module { aspect-ratio: 1; background: #fff; } .qr-module.on { background: #111; }
        @media (max-width: 820px) { .app { padding: 16px; } main { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app" data-headers-url="{{ route('labels.headers') }}">
    <nav class="top-nav"><a class="active" href="{{ route('labels.index') }}">Etiquettes</a><a href="{{ route('inventories.index') }}">Inventaire</a></nav>
    <header><h1>Generateur d'etiquettes</h1><p class="subtitle">Code 128 vectoriel sur page A4 prete a imprimer.</p></header>
    <main>
        <section class="panel"><div class="panel-body">
            @foreach (['excel_file', 'excel_column', 'preset_id'] as $field)
                @error($field)<div class="alert" role="alert"><i data-lucide="TriangleAlert"></i><div>{{ $message }}</div></div>@enderror
            @endforeach
            @if (session('result'))
                <section class="result"><div><p class="result-title">PDF genere avec succes</p><p class="result-metrics">{{ number_format(session('result.labels'), 0, ',', ' ') }} etiquettes &middot; {{ session('result.pages') }} pages A4</p></div><div class="actions"><a class="action-primary" href="{{ route('labels.pdf', ['token' => session('result.token'), 'download' => 1]) }}"><i data-lucide="Download"></i>Telecharger</a><a class="action-secondary" href="{{ route('labels.pdf', ['token' => session('result.token')]) }}" target="_blank" rel="noopener"><i data-lucide="Printer"></i>Imprimer</a></div></section>
            @endif
            <form id="upload-form" action="{{ route('labels.generate') }}" method="post" enctype="multipart/form-data">@csrf
                <div class="section" style="border-top:0;padding-top:0;margin-top:0"><h2>Fichier Excel</h2><label id="dropzone" class="dropzone" for="excel_file"><input id="excel_file" class="file-input" name="excel_file" type="file" accept=".xlsx,.xls" required><span class="upload-state"><span class="icon-disc"><i data-lucide="FileSpreadsheet"></i></span><span>Glissez-deposez votre fichier</span><span id="choose-file-button" class="secondary-button" role="button" tabindex="0"> <i data-lucide="Upload"></i>Choisir un fichier</span><span class="field-help">XLSX ou XLS</span></span><span class="selected-state"><strong id="file-name"></strong><span>Fichier pret</span><span id="change-file-button" class="secondary-button" role="button" tabindex="0">Modifier</span></span></label>
                    <div class="field"><label for="excel_column">Colonne a convertir en code-barres</label><select id="excel_column" name="excel_column" required disabled data-old-value="{{ old('excel_column') }}"><option value="">Choisissez un fichier Excel</option></select><div class="field-help">SKU, Reference, Code article, N serie, N commande, Tracking...</div></div></div>
                <div class="section"><h2>Format d'etiquette</h2><p class="preset-help">Les formats disponibles sont adaptes aux planches A4 pre-decoupees.</p><select id="preset-selector" name="preset_id" required>@foreach ($presets as $preset)<option value="{{ $preset['id'] }}" @selected($preset['default'])>{{ $preset['displayWidthMm'] }} x {{ $preset['displayHeightMm'] }} mm &middot; {{ $preset['labelsPerSheet'] }} / A4 &middot; {{ $preset['columns'] }}x{{ $preset['rows'] }}@if ($preset['recommended']) &middot; Recommande @endif</option>@endforeach</select><div class="metrics"><div class="metric">Etiquettes / page <strong id="metric-slots">24</strong></div><div class="metric">Zone etiquette <strong id="metric-size">70 x 37 mm</strong></div></div><p id="preset-warning" class="warning" hidden>Les valeurs longues peuvent etre plus compactes sur ce petit format.</p></div>
                <div class="section"><h2>Type de code</h2><div class="code-types"><div class="code-type"><input id="code-type-code128" type="radio" name="code_type" value="code128" checked><label for="code-type-code128"><span class="code-type-title">Code-barres</span><span class="code-type-help">Code 128 · Lecteurs classiques</span></label></div><div class="code-type"><input id="code-type-qr" type="radio" name="code_type" value="qr"><label for="code-type-qr"><span class="code-type-title">QR Code</span><span class="code-type-help">Lecture rapide avec smartphone</span></label></div></div></div>
                <button id="submit-button" class="primary-button" type="submit"><i data-lucide="Barcode"></i>Generer le PDF</button>
            </form>
        </div></section>
        <section class="preview-panel"><div class="preview-head"><h2>Apercu A4</h2><span id="preview-mode" class="field-help">70 x 37 mm</span></div><div class="paper-wrap"><div id="a4-preview" class="a4-board" aria-label="Apercu de la page A4"></div></div></section>
    </main>
</div>
<script>window.BarcodePresets = @json($presets);</script>
</body>
</html>
