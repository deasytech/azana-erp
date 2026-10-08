@props(['kind', 'section', 'image'])
<a class="card reveal" href="{{ route('site.section', $kind) }}">
    <x-site.image :name="$image" :alt="$section['title']" />
    <div class="card-body">
        <h3>{{ $section['title'] }}</h3>
        <p>{{ $section['intro'] }}</p>
        <span class="more">Learn more<span class="sr"> about {{ strtolower($section['title']) }}</span> <x-site.icon name="arrow" /></span>
    </div>
</a>
