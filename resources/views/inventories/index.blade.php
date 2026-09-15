<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventaires</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        .app { max-width: 1100px; margin: auto; padding: 20px; }
        h1 { margin: 0; font-size: 23px; letter-spacing: -.025em; }
        h2 { font-size: 16px; margin: 0 0 14px; }
        .subtitle { color: #607080; margin: 6px 0 22px; }
        .layout { display: grid; grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); gap: 22px; align-items: start; }
        section { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; padding: 20px; }
        label { display: block; font-size: 13px; font-weight: 700; margin: 12px 0 6px; }
        input { width: 100%; padding: 11px; border: 1px solid #b8c2cc; border-radius: 6px; font: inherit; }
        button, .button { border: 0; border-radius: 6px; padding: 11px 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .button.secondary { background: #edf1f4; color: #16202a; }
        form > button { width: 100%; margin-top: 16px; }
        .actions { display: flex; gap: 7px; flex-wrap: wrap; }
        .status { font-size: 12px; font-weight: 700; }
        .status.in_progress { color: #1769aa; }
        .status.completed { color: #28733f; }
        .table-wrap { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 12px 8px; border-bottom: 1px solid #e5e9ed; text-align: left; vertical-align: middle; }
        th { color: #607080; font-size: 12px; }
        .empty { color: #607080; padding: 18px 0; }
        .alert { padding: 10px; background: #fff4e9; color: #8b3e12; margin-bottom: 14px; font-size: 13px; }
        .field-error { color: #8b3e12; font-size: 12px; margin: 6px 0 0; }
        .reference-upload { margin-top: 14px; padding: 14px; border: 1px solid #dce2e7; border-radius: 8px; background: #f8fafb; }
        .reference-upload h3 { margin: 0 0 5px; font-size: 14px; }
        .reference-upload p { margin: 0; color: #607080; font-size: 12px; line-height: 1.45; }
        .upload-row { display: flex; align-items: center; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        .upload-trigger { border: 1px solid #b8c2cc; border-radius: 6px; padding: 10px 12px; background: #fff; color: #16202a; font-weight: 700; cursor: pointer; display: inline-block; }
        .upload-trigger.disabled { background: #edf1f4; color: #7a8794; cursor: not-allowed; }
        .file-state { display: none; color: #28733f; font-size: 12px; font-weight: 700; }
        .file-state.ready { display: inline-block; }
        .file-name { color: #16202a; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        @media (max-width: 760px) { .app { padding: 14px; } .layout { grid-template-columns: 1fr; } table { min-width: 650px; } }
    </style>
</head>
<body><div class="app">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link active" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link" href="{{ route('article-references.index') }}">Referentiel articles</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <h1>Inventaires</h1><p class="subtitle">Comptez les articles localement et exportez le resultat quand vous avez termine.</p>
    <div class="layout">
        <section><h2>Creer un inventaire</h2>@if ($errors->any())<div class="alert">Corrigez les champs indiques avant de continuer.</div>@endif<form method="post" action="{{ route('inventories.store') }}" enctype="multipart/form-data" id="inventory-create-form">@csrf<label for="name">Nom</label><input id="name" name="name" required maxlength="120" value="{{ old('name') }}">@error('name')<p class="field-error">{{ $message }}</p>@enderror<label for="zone">Zone <span style="font-weight:400">(optionnel)</span></label><input id="zone" name="zone" maxlength="120" value="{{ old('zone') }}">@error('zone')<p class="field-error">{{ $message }}</p>@enderror<div class="reference-upload" data-reference-upload><h3>Fichier de reference de la zone (optionnel)</h3><p data-upload-helper>Renseignez d abord une zone pour activer l import du fichier.</p><div class="upload-row"><label class="upload-trigger disabled" for="article_reference_file" data-upload-trigger>Choisir un fichier Excel</label><input class="sr-only" id="article_reference_file" name="article_reference_file" type="file" accept=".xlsx,.xls" disabled data-upload-input><span class="file-state" data-file-state><span class="file-name" data-file-name></span> - Fichier pret a etre importe avec cet inventaire.</span></div>@error('article_reference_file')<p class="field-error">{{ $message }}</p>@enderror</div><button type="submit">Commencer l'inventaire</button></form></section>
        <section><h2>Inventaires existants</h2>@if ($inventories->isEmpty())<p class="empty">Aucun inventaire pour le moment.</p>@else<div class="table-wrap"><table><thead><tr><th>Nom</th><th>Zone</th><th>Statut</th><th>References</th><th>Total</th><th>Date</th><th></th></tr></thead><tbody>@foreach ($inventories as $inventory)<tr><td><strong>{{ $inventory->name }}</strong></td><td>{{ $inventory->zone ?: '-' }}</td><td><span class="status {{ $inventory->status }}">{{ $inventory->isCompleted() ? 'Termine' : 'En cours' }}</span></td><td>{{ $inventory->items_count }}</td><td>{{ $inventory->items_sum_quantity ?? 0 }}</td><td>{{ $inventory->started_at->format('d/m/Y') }}</td><td><div class="actions"><a class="button secondary" href="{{ route('inventories.show', $inventory->uuid) }}">{{ $inventory->isCompleted() ? 'Consulter' : 'Continuer' }}</a><a class="button secondary" href="{{ route('inventories.export', $inventory->uuid) }}">Excel</a></div></td></tr>@endforeach</tbody></table></div>@endif</section>
    </div>
    <script>
        (() => {
            const zone = document.querySelector('#zone');
            const input = document.querySelector('[data-upload-input]');
            const trigger = document.querySelector('[data-upload-trigger]');
            const helper = document.querySelector('[data-upload-helper]');
            const fileState = document.querySelector('[data-file-state]');
            const fileName = document.querySelector('[data-file-name]');

            if (!zone || !input || !trigger || !helper || !fileState || !fileName) return;

            const disabledText = 'Renseignez d abord une zone pour activer l import du fichier.';
            const enabledText = 'Importez le fichier Excel correspondant a cette zone pour enrichir automatiquement les articles avec leur designation et leur emplacement.';

            function syncUploadState() {
                const enabled = zone.value.trim() !== '';

                input.disabled = !enabled;
                trigger.classList.toggle('disabled', !enabled);
                helper.textContent = enabled ? enabledText : disabledText;

                if (!enabled) {
                    input.value = '';
                    fileName.textContent = '';
                    fileState.classList.remove('ready');
                }
            }

            zone.addEventListener('input', syncUploadState);
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];

                fileName.textContent = file ? file.name : '';
                fileState.classList.toggle('ready', Boolean(file));
            });

            syncUploadState();
        })();
    </script>
</div></body></html>
