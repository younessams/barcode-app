<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catalogue QR</title>

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color: #16202a;
            background: #f4f6f8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-width: 320px;
        }

        .app {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px;
        }

        header {
            margin-bottom: 22px;
        }

        h1 {
            margin: 0;
            font-size: 23px;
            letter-spacing: -.025em;
        }

        .subtitle,
        .field-help {
            color: #607080;
        }

        main {
            display: grid;
            grid-template-columns: minmax(300px, 390px) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .panel,
        .preview-panel {
            background: #fff;
            border: 1px solid #dce2e7;
            border-radius: 10px;
        }

        .panel-body,
        .preview-panel {
            padding: 22px;
        }

        h2 {
            margin: 0 0 12px;
            font-size: 16px;
        }

        .dropzone {
            display: block;
            border: 1px dashed #9eabb7;
            border-radius: 9px;
            padding: 24px 18px;
            text-align: center;
            cursor: pointer;
            transition: .15s ease;
        }

        .dropzone.is-dragging {
            border-color: #1769aa;
            background: #eef7ff;
        }

        .file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .upload-state,
        .selected-state {
            display: grid;
            gap: 9px;
            justify-items: center;
        }

        .selected-state {
            display: none;
        }

        .dropzone.has-file .upload-state {
            display: none;
        }

        .dropzone.has-file .selected-state {
            display: grid;
        }

        .button,
        .secondary-button {
            border: 0;
            border-radius: 7px;
            padding: 11px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .button {
            display: block;
            width: 100%;
            margin-top: 18px;
            color: #fff;
            background: #1769aa;
        }

        .secondary-button {
            display: inline-block;
            color: #16202a;
            background: #edf1f4;
        }

        .requirements {
            margin-top: 18px;
            padding: 14px;
            border-radius: 8px;
            background: #f7f9fa;
            border: 1px solid #e1e6ea;
        }

        .requirements p {
            margin: 0 0 9px;
            font-weight: 700;
            font-size: 13px;
        }

        .requirements code {
            display: block;
            padding: 4px 0;
            color: #52616d;
            font-size: 12px;
        }

        .alert {
            padding: 11px;
            margin-bottom: 14px;
            color: #8b3e12;
            background: #fff4e9;
            border: 1px solid #f0c89d;
            border-radius: 7px;
            font-size: 13px;
        }

        .result {
            padding: 14px;
            margin-bottom: 18px;
            background: #eef8f0;
            border: 1px solid #b8dbbf;
            border-radius: 8px;
        }

        .result-title {
            margin: 0;
            font-weight: 800;
        }

        .result-info {
            margin: 5px 0 12px;
            color: #41624a;
            font-size: 13px;
        }

        .result-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .result-actions a {
            margin: 0;
            width: auto;
        }

        .preview-title {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .a4 {
            width: min(100%, 560px);
            aspect-ratio: 210 / 297;
            margin: 0 auto;
            padding: 3.2%;
            background: #fff;
            border: 1px solid #e4e8eb;
            box-shadow: 0 3px 16px #1824311c;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(6, 1fr);
            gap: 1.5%;
        }

        .mock-card {
            min-width: 0;
            padding: 7%;
            border: 1px solid #d6dde2;
            border-radius: 5px;
            display: grid;
            grid-template-columns: 38% 1fr;
            gap: 7%;
            align-items: center;
        }

        .mock-qr {
            aspect-ratio: 1;
            background:
                linear-gradient(90deg, #111 15%, transparent 15% 28%, #111 28% 42%, transparent 42% 57%, #111 57% 72%, transparent 72%),
                linear-gradient(#111 15%, transparent 15% 28%, #111 28% 42%, transparent 42% 57%, #111 57% 72%, transparent 72%);
            background-size: 9px 9px;
        }

        .mock-info {
            min-width: 0;
            text-align: center;
        }

        .mock-code {
            height: 5px;
            width: 85%;
            margin: 0 auto 8px;
            border-radius: 3px;
            background: #273440;
        }

        .mock-text {
            height: 4px;
            width: 100%;
            margin: 4px auto;
            border-radius: 3px;
            background: #c5cdd3;
        }

        .mock-location {
            width: 70%;
            height: 10px;
            margin: 9px auto 0;
            border-radius: 3px;
            background: #edf2f5;
            border: 1px solid #dce3e8;
        }

        @media (max-width: 820px) {
            .app {
                padding: 16px;
            }

            main {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="app">

    <nav class="app-nav" aria-label="Navigation principale">
        <a class="app-nav-link" href="{{ route('labels.index') }}">
            Etiquettes
        </a>

        <a class="app-nav-link" href="{{ route('inventories.index') }}">
            Inventaire
        </a>

        <a class="app-nav-link active" href="{{ route('catalogue.index') }}">
            Catalogue QR
        </a>
    </nav>

    <header>
        <h1>Catalogue QR</h1>

        <p class="subtitle">
            Generez une fiche A4 2x6 avec QR Code, designation et emplacement.
        </p>
    </header>

    <main>

        <section class="panel">
            <div class="panel-body">

                @error('excel_file')
                    <div class="alert" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                @if (session('result'))
                    <section class="result">

                        <p class="result-title">
                            PDF genere avec succes
                        </p>

                        <p class="result-info">
                            {{ session('result.items') }} articles
                            &middot;
                            {{ session('result.pages') }} page(s) A4
                        </p>

                        <div class="result-actions">

                            <a
                                class="button"
                                href="{{ route('catalogue.pdf', [
                                    'token' => session('result.token'),
                                    'download' => 1
                                ]) }}"
                            >
                                Telecharger
                            </a>

                            <a
                                class="secondary-button"
                                href="{{ route('catalogue.pdf', [
                                    'token' => session('result.token')
                                ]) }}"
                                target="_blank"
                                rel="noopener"
                            >
                                Ouvrir / Imprimer
                            </a>

                        </div>

                    </section>
                @endif

                <form
                    method="post"
                    action="{{ route('catalogue.generate') }}"
                    enctype="multipart/form-data"
                >
                    @csrf

                    <h2>Fichier Excel</h2>

                    <label
                        id="dropzone"
                        class="dropzone"
                        for="excel_file"
                    >

                        <input
                            id="excel_file"
                            class="file-input"
                            type="file"
                            name="excel_file"
                            accept=".xlsx,.xls"
                            required
                        >

                        <span class="upload-state">
                            <strong>Choisir un fichier Excel</strong>

                            <span class="secondary-button">
                                Parcourir
                            </span>

                            <span class="field-help">
                                XLSX ou XLS
                            </span>
                        </span>

                        <span class="selected-state">
                            <strong id="file-name"></strong>

                            <span class="field-help">
                                Fichier pret
                            </span>

                            <span class="secondary-button">
                                Modifier
                            </span>
                        </span>

                    </label>

                    <div class="requirements">
                        <p>Colonnes requises</p>

                        <code>Code Article</code>
                        <code>Designation / Désignation</code>
                        <code>Emplacement</code>
                    </div>

                    <button class="button" type="submit">
                        Generer le catalogue QR
                    </button>

                </form>

            </div>
        </section>

        <section class="preview-panel">

            <div class="preview-title">
                <h2>Apercu A4</h2>

                <span class="field-help">
                    2 x 6 · 12 articles / page
                </span>
            </div>

            <div class="a4">
                @for ($i = 0; $i < 12; $i++)
                    <div class="mock-card">
                        <div class="mock-qr"></div>

                        <div class="mock-info">
                            <div class="mock-code"></div>
                            <div class="mock-text"></div>
                            <div class="mock-text"></div>
                            <div class="mock-location"></div>
                        </div>
                    </div>
                @endfor
            </div>

        </section>

    </main>
</div>

<script>
(() => {
    const input = document.querySelector('#excel_file');
    const dropzone = document.querySelector('#dropzone');
    const filename = document.querySelector('#file-name');

    if (!input || !dropzone || !filename) {
        return;
    }

    const showFile = (file) => {
        if (!file) {
            return;
        }

        filename.textContent = file.name;
        dropzone.classList.add('has-file');
    };

    input.addEventListener('change', () => {
        showFile(input.files?.[0]);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const file = event.dataTransfer?.files?.[0];

        if (!file || !/\.(xlsx|xls)$/i.test(file.name)) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;

        showFile(file);
    });
})();
</script>

</body>
</html>
