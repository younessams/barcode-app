<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Referentiel articles</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }
        .app { max-width: 980px; margin: 0 auto; padding: 28px; }
        header { margin-bottom: 22px; }
        h1 { margin: 0; font-size: 23px; letter-spacing: -.025em; }
        h2 { margin: 0 0 12px; font-size: 16px; }
        .subtitle, .muted { color: #607080; }
        .layout { display: grid; grid-template-columns: minmax(300px, 420px) minmax(0, 1fr); gap: 22px; align-items: start; }
        .panel { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; padding: 22px; }
        label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 7px; }
        input { width: 100%; padding: 11px; border: 1px solid #b8c2cc; border-radius: 6px; font: inherit; background: #fff; }
        button { width: 100%; border: 0; border-radius: 6px; padding: 11px 14px; margin-top: 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .requirements { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
        code { padding: 6px 8px; border: 1px solid #dce2e7; border-radius: 5px; background: #f7f9fa; font-size: 12px; }
        .alert { padding: 11px; color: #8b3e12; background: #fff4e9; border: 1px solid #f0c89d; margin-bottom: 12px; font-size: 13px; }
        .result { padding: 14px; background: #eef8f0; border: 1px solid #b8dbbf; border-radius: 6px; margin-bottom: 16px; }
        .result p { margin: 0 0 8px; }
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .stat { border: 1px solid #e0e5e9; border-radius: 6px; padding: 10px; }
        .stat small { display: block; color: #607080; font-size: 12px; }
        .stat strong { display: block; margin-top: 4px; font-size: 22px; }
        @media (max-width: 760px) { .app { padding: 16px; } .layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link active" href="{{ route('article-references.index') }}">Referentiel articles</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <header>
        <h1>Referentiel articles</h1>
        <p class="subtitle">Importez le fichier Excel qui associe chaque Code Article a sa designation et son emplacement.</p>
    </header>
    <main class="layout">
        <section class="panel">
            @error('excel_file')
                <div class="alert" role="alert">{{ $message }}</div>
            @enderror

            @if (session('result'))
                @php($result = session('result'))
                <section class="result">
                    <p><strong>Import termine avec succes</strong></p>
                    <p>{{ number_format($result->total, 0, ',', ' ') }} references uniques traitees.</p>
                    <p class="muted">
                        {{ number_format($result->inserted, 0, ',', ' ') }} creees
                        &middot;
                        {{ number_format($result->updated, 0, ',', ' ') }} mises a jour
                        &middot;
                        {{ number_format($result->duplicateRows, 0, ',', ' ') }} doublons resolus
                        &middot;
                        {{ number_format($result->skippedBlankRows, 0, ',', ' ') }} lignes vides ignorees
                    </p>
                </section>
            @endif

            <h2>Importer un fichier Excel</h2>
            <form method="post" action="{{ route('article-references.import') }}" enctype="multipart/form-data">
                @csrf
                <label for="excel_file">Fichier Excel</label>
                <input id="excel_file" name="excel_file" type="file" accept=".xlsx,.xls" required>
                <div class="requirements" aria-label="Colonnes attendues">
                    <code>Code Article</code>
                    <code>Designation</code>
                    <code>Emplacement</code>
                </div>
                <button type="submit">Importer le referentiel</button>
            </form>
        </section>

        <section class="panel">
            <h2>Etat actuel</h2>
            <div class="stats">
                <div class="stat">
                    <small>References stockees</small>
                    <strong>{{ number_format($referenceCount, 0, ',', ' ') }}</strong>
                </div>
                <div class="stat">
                    <small>Derniere mise a jour</small>
                    <strong>{{ $lastImportAt ? $lastImportAt->format('d/m/Y H:i') : '-' }}</strong>
                </div>
            </div>
            <p class="muted">Les imports mettent a jour les codes existants et ajoutent les nouveaux. Les references absentes du fichier ne sont pas supprimees.</p>
        </section>
    </main>
</div>
</body>
</html>
