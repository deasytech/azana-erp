<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0; }
        .sub { color: #6b7280; margin: 2px 0 14px; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f3f4f6; }
        th, td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>Integrated Princess Azana Farms - {{ $document->title }}</h1>
    <p class="sub">{{ $document->subtitle }}</p>
    @foreach ($document->sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead><tr>@foreach ($section['headings'] as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($section['headings']) }}">Nothing to show.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
