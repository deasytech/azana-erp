@php($listing = $row['listing'])
<x-site.layout :site="$site" :livewire="true" :title="$listing->title" :description="$listing->summary" :schema="[
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $listing->title, 'description' => $listing->summary,
    'brand' => ['@type' => 'Brand', 'name' => $site['name']],
]">
    <header class="page-head">
        <div class="wrap narrow">
            <nav aria-label="Breadcrumb" class="crumbs"><a href="{{ route('site.home') }}">Home</a> / <a href="{{ route('site.products') }}">Products</a> / <span aria-current="page">{{ $listing->title }}</span></nav>
            <div class="hero-in">
                <h1>{{ $listing->title }}</h1>
                <p class="intro">{{ $listing->summary }}</p>
                <p class="meta">
                    @if ($row['price'])<span class="price">{{ $row['price'] }}</span>@else<span>Price on request</span>@endif
                    @if ($row['available'] === true)<span class="badge badge-ok">Available</span>@elseif ($row['available'] === false)<span class="badge">Ask about next batch</span>@endif
                </p>
            </div>
        </div>
    </header>

    <x-site.section id="detail" :heading="true">
        <div class="narrow">
            @if ($listing->description)
                <div class="prose">@foreach (preg_split('/\R{2,}/', trim($listing->description)) as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
            @endif
            @if ($site['enquiries_enabled'])
                <div class="panel" id="enquiry" style="margin-top:2.5rem">
                    <h2 id="detail-h" style="font-size:1.6rem;margin-bottom:1.25rem">Enquire about this</h2>
                    @livewire(\App\Livewire\Public\EnquiryForm::class, ['listingId' => $listing->id])
                </div>
            @else
                <h2 id="detail-h" class="sr">Contact</h2>
                <p>To enquire, use the contact details on our <a href="{{ route('site.contact') }}">contact page</a>.</p>
            @endif
        </div>
    </x-site.section>
</x-site.layout>
