<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; margin: 0; }
        .header { border-bottom: 3px solid #0EA5E9; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 18px; font-weight: bold; color: #0EA5E9; }
        .brand small { color: #64748b; font-weight: normal; font-size: 10px; }
        .title { font-size: 14px; font-weight: bold; margin-top: 6px; }
        .meta-info { font-size: 10px; color: #64748b; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #0EA5E9; color: #fff; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background: #f0f9ff; }
        .summary { margin-top: 14px; width: auto; }
        .summary td { border: none; padding: 3px 10px 3px 0; }
        .summary .label { color: #64748b; }
        .summary .value { font-weight: bold; }
        .empty { text-align: center; color: #94a3b8; padding: 20px; }
        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Tarakan Art Class <small>— Sistem administrasi</small></div>
        <div class="title">{{ $title }}</div>
        <div class="meta-info">
            Dibuat oleh {{ $generatedBy }} · {{ $generatedAt->translatedFormat('d F Y, H:i') }} · Total {{ count($rows) }} baris
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($headers) }}">Tidak ada data untuk diekspor.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($meta))
        <table class="summary">
            @foreach($meta as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="value">: {{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        &copy; {{ date('Y') }} Tarakan Art Class · Dokumen dibuat otomatis oleh sistem
    </div>
</body>
</html>
