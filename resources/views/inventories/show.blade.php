<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $inventory->name }}</title>
    @vite(['resources/js/inventory.js'])
    <style>
        :root { font-family: Inter, system-ui, sans-serif; color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        .app { max-width: 920px; margin: auto; padding: 20px; }
        nav { display: inline-flex; gap: 3px; margin-bottom: 16px; padding: 3px; background: #e9eef2; border-radius: 8px; }
        nav a { color: #52616d; text-decoration: none; font-size: 13px; font-weight: 700; padding: 7px 12px; border-radius: 6px; }
        nav a.active { color: #16202a; background: #fff; box-shadow: 0 1px 3px #1824311c; }
        .top { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 24px; }
        h2 { margin: 0; font-size: 17px; }
        .meta { color: #607080; margin: 6px 0 18px; }
        .toolbar, .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .button, button { border: 0; border-radius: 6px; padding: 11px 14px; background: #1769aa; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .button.secondary, button.secondary { background: #edf1f4; color: #16202a; }
        .button:disabled, button:disabled { opacity: .55; cursor: not-allowed; }
        .scanner, .items { background: #fff; border: 1px solid #dce2e7; border-radius: 8px; margin-top: 16px; }
        .scanner { padding: 14px; max-width: 760px; }
        .camera-frame { position: relative; background: #101820; border-radius: 6px; overflow: hidden; aspect-ratio: 16 / 10; max-height: 42vh; margin-top: 10px; }
        #camera-video { display: block; width: 100%; height: 100%; object-fit: cover; }
        .scan-guide { position: absolute; inset: 20% 12%; border: 2px solid #8fd3ff; border-radius: 8px; pointer-events: none; }
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
        #quantity, #detected-quantity { font-size: 16px; max-width: 180px; }
        .detected-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
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
            .detected-actions .button, .detected-actions button { flex: 1 1 140px; }
            input[type=search] { max-width: none; }
        }
    </style>
</head>
<body>
<div class="app" data-inventory="{{ $inventory->uuid }}" data-item-url="{{ route('inventories.items.store', $inventory->uuid) }}" data-completed="{{ $inventory->isCompleted() ? '1' : '0' }}">
    <nav><a href="{{ route('labels.index') }}">Etiquettes</a><a class="active" href="{{ route('inventories.index') }}">Inventaire</a></nav>
    <div class="top">
        <div><h1>{{ $inventory->name }}</h1><p class="meta">{{ $inventory->zone ?: 'Zone non renseignee' }} · <span id="status">{{ $inventory->isCompleted() ? 'Termine' : 'En cours' }}</span></p></div>
        <div class="toolbar">
            <a class="button secondary" href="{{ route('inventories.export', $inventory->uuid) }}">Exporter Excel</a>
            @if ($inventory->isCompleted())
                <form method="post" action="{{ route('inventories.reopen', $inventory->uuid) }}">@csrf<button class="secondary" type="submit">Rouvrir</button></form>
            @endif
        </div>
    </div>

    @if (!$inventory->isCompleted())
        <section class="scanner" aria-labelledby="scanner-title">
            <h2 id="scanner-title">Scanner un article</h2>
            <div class="camera-frame"><video id="camera-video" playsinline muted aria-label="Apercu de la camera"></video><div class="scan-guide"></div><p id="camera-status" class="camera-status">Placez le QR code ou le code-barres devant la camera.</p></div>
            <div class="action-area"><div class="camera-actions"><button id="start-camera" class="icon-button" type="button" aria-label="Demarrer la camera" title="Demarrer la camera"><i data-lucide="Camera"></i></button><button id="retry-camera" class="icon-button secondary" type="button" aria-label="Reessayer la camera" title="Reessayer" hidden><i data-lucide="RefreshCw"></i></button><button id="manual-toggle" class="manual-link" type="button" aria-expanded="false"><i data-lucide="Keyboard"></i><span>Saisir le code article manuellement</span></button></div><div id="idle-quantity" class="quantity-bar" aria-label="Quantite par defaut"><button class="icon-button" type="button" data-quantity-step="-1" aria-label="Diminuer la quantite" title="Diminuer"><i data-lucide="Minus"></i></button><input id="quantity" name="quantity" type="number" min="0" step="1" value="1" aria-label="Quantite"><button class="icon-button" type="button" data-quantity-step="1" aria-label="Augmenter la quantite" title="Augmenter"><i data-lucide="Plus"></i></button><button id="manual-save" class="icon-button save-button" form="item-form" type="submit" aria-label="Enregistrer l article" title="Enregistrer" hidden><i data-lucide="Save"></i></button></div></div>
            <div id="manual-entry" hidden><form id="item-form"><label class="quantity-label" for="code_article">Code Article</label><input id="code_article" name="code_article" autocomplete="off"></form></div>
            <div id="detected-panel" class="detected" hidden><div class="detected-header"><strong>Article detecte :</strong><span id="detected-code" class="detected-code"></span></div><div class="action-area"><div class="quantity-bar"><button class="icon-button" type="button" data-detected-step="-1" aria-label="Diminuer la quantite" title="Diminuer"><i data-lucide="Minus"></i></button><input id="detected-quantity" type="number" min="0" step="1" value="1" aria-label="Quantite"><button class="icon-button" type="button" data-detected-step="1" aria-label="Augmenter la quantite" title="Augmenter"><i data-lucide="Plus"></i></button><button id="save-detected" class="icon-button save-button" type="button" aria-label="Enregistrer l article" title="Enregistrer"><i data-lucide="Save"></i></button></div><button id="cancel-detected" class="icon-button secondary" type="button" aria-label="Annuler la detection" title="Annuler"><i data-lucide="X"></i></button></div></div>
            <div id="duplicate-panel" class="duplicate" hidden></div>
            <p id="message" class="message" role="status"></p>
        </section>
    @else
        <section class="scanner"><h2>Inventaire termine</h2><p class="meta">La camera et la modification des articles sont desactivees. Vous pouvez rouvrir cet inventaire pour continuer.</p></section>
    @endif

    <div class="summary"><div class="stat"><small>References</small><strong id="items-count">{{ $inventory->items->count() }}</strong></div><div class="stat"><small>Quantite totale</small><strong id="total-quantity">{{ $inventory->items->sum('quantity') }}</strong></div></div>
    @if (!$inventory->isCompleted())
        <form id="complete-form" method="post" action="{{ route('inventories.complete', $inventory->uuid) }}">@csrf<button class="close-action" type="submit"><i data-lucide="CircleStop"></i>Cloturer l'inventaire</button></form>
    @endif

    <details id="items-section" class="items"><summary><i data-lucide="ChevronDown"></i>Voir les articles comptes ({{ $inventory->items->count() }})</summary><div class="items-toolbar"><h2>Articles comptes</h2><input id="search" type="search" placeholder="Rechercher un code" aria-label="Rechercher un code"></div><div class="table-wrap"><table><thead><tr><th>Code Article</th><th>Quantite</th><th>QR</th>@if (!$inventory->isCompleted())<th>Actions</th>@endif</tr></thead><tbody id="items-body">@foreach ($inventory->items as $item)<tr data-code="{{ strtolower($item->code_article) }}" data-item="{{ $item->uuid }}"><td>{{ $item->code_article }}</td><td class="quantity">{{ $item->quantity }}</td><td>Disponible a l'export</td>@if (!$inventory->isCompleted())<td><div class="actions"><button class="button secondary edit-item" type="button">Modifier</button><button class="button secondary delete-item" type="button">Supprimer</button></div></td>@endif</tr>@endforeach</tbody></table></div><p id="empty-items" class="empty" @if ($inventory->items->isNotEmpty()) hidden @endif>Aucun article compte.</p></details>
</div>
</body>
</html>
