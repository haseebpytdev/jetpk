<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visa PDF copy</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { width: 34%; color: #333; }
        .footer { margin-top: 24px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>Visa document copy</h1>
    <p class="meta">Source: {{ $source }}</p>
    <table>
        @foreach ($fields as $key => $value)
            @if ($value !== null && $value !== '')
                <tr>
                    <th>{{ str_replace('_', ' ', ucfirst($key)) }}</th>
                    <td>{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
    <p class="footer">{{ $attribution }}</p>
</body>
</html>
