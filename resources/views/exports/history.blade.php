<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Historique {{ $header['Référence'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #64748b; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; vertical-align: top; text-align: left; }
        .header th { width: 25%; background: #f1f5f9; }
        .log thead th { background: #e8eef8; }
        .log thead { display: table-header-group; }
        .log tr { page-break-inside: avoid; }
        .details { color: #475569; font-size: 8px; }
    </style>
</head>
<body>
    <h1>Historique du dossier {{ $header['Référence'] }}</h1>
    <div class="muted">SOGEDIPROMA — édité le {{ $generatedAt->format('d/m/Y à H:i') }}</div>

    <table class="header">
        @foreach ($header as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <table class="log">
        <thead>
            <tr><th style="width: 12%">Date</th><th style="width: 7%">Heure</th><th style="width: 18%">Utilisateur</th><th style="width: 33%">Action</th><th>Commentaire</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['time'] }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td><strong>{{ $row['action'] }}</strong>@if ($row['details'] !== '')<div class="details">{{ $row['details'] }}</div>@endif</td>
                    <td>{{ $row['comment'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
