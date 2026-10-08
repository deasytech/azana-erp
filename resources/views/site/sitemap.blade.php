{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $u)
    <url><loc>{{ $u['url'] }}</loc>@if ($u['at'])<lastmod>{{ $u['at']->toAtomString() }}</lastmod>@endif</url>
@endforeach
</urlset>
