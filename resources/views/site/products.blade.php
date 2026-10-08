<x-site.layout :site="$site" title="Products and services" current="site.products" :description="'Live pigs, boar semen and pork from '.$site['name'].'.'">
    <x-site.page-head eyebrow="Products &amp; services" title="What we offer" intro="Tell us what you need and we will confirm what is ready and when." :crumbs="['Home' => route('site.home')]" />

    <x-site.section id="offer" :heading="true">
        <div class="grid g3">
            @foreach (config('website.sections') as $kind => $section)<x-site.product-card :kind="$kind" :section="$section" :image="['pigs' => 'livestock', 'semen' => 'breeding', 'meat' => 'meat'][$kind] ?? 'livestock'" />@endforeach
        </div>
    </x-site.section>

    @foreach ($groups as $kind => $rows)
        <x-site.section :id="'g-'.$kind" tone="cream" :heading="true">
            <x-site.heading :id="'g-'.$kind" eyebrow="Current listings" :title="\App\Enums\ListingKind::from($kind)->label()" />
            <div class="grid g3">@foreach ($rows as $row)<x-site.listing-card :row="$row" />@endforeach</div>
        </x-site.section>
    @endforeach

    <x-site.cta />
</x-site.layout>
