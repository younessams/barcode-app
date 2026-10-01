<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventaires</title>
    @vite(['resources/css/app.css', 'resources/js/inventory-index.js'])
    <style>
        :root { color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        .app { max-width: 1100px; margin: auto; padding: 20px; }
        h1 { margin: 0; font-size: 23px; letter-spacing: -.025em; }
        h2 { font-size: 16px; margin: 0 0 14px; }
        .subtitle, .empty { color: #607080; }
        .subtitle { margin: 6px 0 22px; }
        .layout { display: grid; grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); gap: 22px; align-items: start; }
        section { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; padding: 20px; }
        label { display: block; font-size: 13px; font-weight: 700; margin: 12px 0 6px; }
        input { width: 100%; padding: 11px; border: 1px solid #b8c2cc; border-radius: 6px; font: inherit; }
        button, .button { border: 0; border-radius: 6px; padding: 11px 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        form > button { width: 100%; margin-top: 16px; }
        .inventory-list { display: grid; gap: 10px; }
        .inventory-row { display: grid; grid-template-columns: minmax(150px, 1fr) auto; gap: 12px; align-items: center; padding: 13px 0; border-bottom: 1px solid #e5e9ed; }
        .inventory-row:last-child { border-bottom: 0; }
        .inventory-meta { margin: 5px 0 0; color: #607080; font-size: 12px; }
        .alert { padding: 10px; color: #8b3e12; background: #fff4e9; border: 1px solid #f0c89d; margin: 0 0 14px; font-size: 13px; }
        @media (max-width: 760px) { .app { padding: 14px; } .layout, .inventory-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app" data-show-url-template="{{ route('inventories.show', ['uuid' => '__INVENTORY_UUID__']) }}">
    <nav class="app-nav" aria-label="Navigation principale"><a class="app-nav-link" href="{{ route('labels.index') }}">Etiquettes</a><a class="app-nav-link active" href="{{ route('inventories.index') }}">Inventaire</a><a class="app-nav-link" href="{{ route('catalogue.index') }}">Catalogue QR</a></nav>
    <h1>Inventaires</h1><p class="subtitle">Comptez les articles localement dans ce navigateur.</p>
    <div class="layout">
        <section><h2>Creer un inventaire</h2><form id="inventory-create-form" novalidate><div id="create-error" class="alert" hidden></div><label for="name">Nom</label><input id="name" name="name" required maxlength="120"><label for="zone">Zone <span style="font-weight:400">(optionnel)</span></label><input id="zone" name="zone" maxlength="120"><button type="submit">Commencer l'inventaire</button></form></section>
        <section><h2>Inventaires existants</h2><div id="inventory-list" class="inventory-list"></div><p id="empty-inventories" class="empty">Aucun inventaire pour le moment.</p></section>
    </div>
</div>
</body>
</html>
