<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventaires</title>
    <style>
        :root { font-family: Inter, system-ui, sans-serif; color: #16202a; background: #f4f6f8; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        .app { max-width: 1100px; margin: auto; padding: 20px; }
        nav { display: inline-flex; gap: 3px; margin-bottom: 20px; padding: 3px; background: #e9eef2; border-radius: 8px; }
        nav a { color: #52616d; text-decoration: none; font-size: 13px; font-weight: 700; padding: 7px 12px; border-radius: 6px; }
        nav a.active { color: #16202a; background: #fff; box-shadow: 0 1px 3px #1824311c; }
        h1 { margin: 0; font-size: 25px; }
        h2 { font-size: 17px; margin: 0 0 14px; }
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
        @media (max-width: 760px) { .app { padding: 14px; } .layout { grid-template-columns: 1fr; } table { min-width: 650px; } }
    </style>
</head>
<body><div class="app">
    <nav><a href="{{ route('labels.index') }}">Etiquettes</a><a class="active" href="{{ route('inventories.index') }}">Inventaire</a></nav>
    <h1>Inventaires</h1><p class="subtitle">Comptez les articles localement et exportez le resultat quand vous avez termine.</p>
    <div class="layout">
        <section><h2>Creer un inventaire</h2>@error('name')<div class="alert">{{ $message }}</div>@enderror<form method="post" action="{{ route('inventories.store') }}">@csrf<label for="name">Nom</label><input id="name" name="name" required maxlength="120" value="{{ old('name') }}"><label for="zone">Zone <span style="font-weight:400">(optionnel)</span></label><input id="zone" name="zone" maxlength="120" value="{{ old('zone') }}"><button type="submit">Commencer l'inventaire</button></form></section>
        <section><h2>Inventaires existants</h2>@if ($inventories->isEmpty())<p class="empty">Aucun inventaire pour le moment.</p>@else<div class="table-wrap"><table><thead><tr><th>Nom</th><th>Zone</th><th>Statut</th><th>References</th><th>Total</th><th>Date</th><th></th></tr></thead><tbody>@foreach ($inventories as $inventory)<tr><td><strong>{{ $inventory->name }}</strong></td><td>{{ $inventory->zone ?: '-' }}</td><td><span class="status {{ $inventory->status }}">{{ $inventory->isCompleted() ? 'Termine' : 'En cours' }}</span></td><td>{{ $inventory->items_count }}</td><td>{{ $inventory->items_sum_quantity ?? 0 }}</td><td>{{ $inventory->started_at->format('d/m/Y') }}</td><td><div class="actions"><a class="button secondary" href="{{ route('inventories.show', $inventory->uuid) }}">{{ $inventory->isCompleted() ? 'Consulter' : 'Continuer' }}</a><a class="button secondary" href="{{ route('inventories.export', $inventory->uuid) }}">Excel</a></div></td></tr>@endforeach</tbody></table></div>@endif</section>
    </div>
</div></body></html>
