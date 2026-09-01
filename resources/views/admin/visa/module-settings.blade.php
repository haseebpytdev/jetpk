<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visa module settings</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #111; }
        table { border-collapse: collapse; width: min(720px, 100%); }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid #ddd; }
        .warn { color: #9a3412; }
    </style>
</head>
<body>
    <h1>Visa module control</h1>
    <p class="warn">Live Saudi MOFA remains blocked until written policy approval is recorded.</p>
    <table>
        @foreach ($status as $key => $value)
            @if (!is_array($value))
                <tr>
                    <th>{{ $key }}</th>
                    <td>{{ is_bool($value) ? ($value ? 'YES' : 'NO') : $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
    <h2>Notes</h2>
    <ul>
        @foreach ($status['notes'] ?? [] as $note)
            <li>{{ $note }}</li>
        @endforeach
    </ul>
</body>
</html>
