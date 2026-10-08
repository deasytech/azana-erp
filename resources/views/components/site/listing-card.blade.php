@props(['row'])
@php $listing = $row['listing']; @endphp
<a class="card reveal" href="{{ route('site.product', $listing->slug) }}">
    <div class="card-body">
        <h3>{{ $listing->title }}</h3>
        <p>{{ $listing->summary }}</p>
        <p class="meta">
            @if ($row['price'])<span class="price">{{ $row['price'] }}</span>@else<span>Price on request</span>@endif
            @if ($row['available'] === true)<span class="badge badge-ok">Available</span>@elseif ($row['available'] === false)<span class="badge">Ask about next batch</span>@endif
        </p>
        <span class="more">View details <x-site.icon name="arrow" /></span>
    </div>
</a>
