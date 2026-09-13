<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generateur d'etiquettes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }
        .app { max-width: 1280px; margin: 0 auto; padding: 28px; }
        header { margin-bottom: 22px; } h1 { margin: 0; font-size: 23px; letter-spacing: -.025em; } .subtitle, .field-help { color: #607080; }
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
<div class="app">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link active" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <header><h1>Generateur d'etiquettes</h1><p class="subtitle">Code 128 vectoriel sur page A4 prete a imprimer.</p></header>
    <div id="barcode-labels-app"></div>
</div>
@php
    $barcodeLabelsPage = [
        'presets' => $presets,
        'headersUrl' => route('labels.headers'),
        'generateUrl' => route('labels.generate'),
        'csrfToken' => csrf_token(),
        'oldExcelColumn' => old('excel_column', ''),
        'errors' => collect(['excel_file', 'excel_column', 'preset_id'])
            ->map(fn ($field) => $errors->first($field))
            ->filter()
            ->values()
            ->all(),
        'result' => session('result') ? [
            'labels' => session('result.labels'),
            'labelsFormatted' => number_format(session('result.labels'), 0, ',', ' '),
            'pages' => session('result.pages'),
            'downloadUrl' => route('labels.pdf', ['token' => session('result.token'), 'download' => 1]),
            'printUrl' => route('labels.pdf', ['token' => session('result.token')]),
        ] : null,
    ];
@endphp
<script>
    window.BarcodeLabelsPage = {{ Illuminate\Support\Js::from($barcodeLabelsPage) }};
</script>
</body>
</html>
