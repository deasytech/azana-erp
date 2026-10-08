@php
    $about = $site['about'] ?: [
        $site['name'].' is a livestock farming business built around the whole journey of the pig: genetics and breeding, feed, animal health, growing and quality pork.',
        'We bring these stages together on one management system, so every decision rests on clear records and every product can be traced.',
    ];
@endphp
<x-site.layout :site="$site" title="About us" current="site.about" :description="'About '.$site['name'].': integrated livestock farming built on quality, traceability and responsible management.'">
    <x-site.page-head eyebrow="About us" title="Integrated agriculture, run with discipline" :intro="$site['tagline']" :crumbs="['Home' => route('site.home')]" />

    <x-site.section id="story" :heading="true">
        <div class="split">
            <div class="frame reveal"><x-site.image name="about" alt="Farm workers caring for pigs" ratio="r45" /></div>
            <div>
                <x-site.heading for="story" eyebrow="Who we are" title="One farm, every stage in view" />
                <div class="prose reveal">@foreach ($about as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
            </div>
        </div>
    </x-site.section>

    <x-site.section id="why" tone="cream" :heading="true">
        <x-site.heading for="why" eyebrow="What we stand for" title="Our principles" :center="true" />
        <div class="grid g3">@foreach (config('website.values') as $item)<x-site.feature-card :item="$item" />@endforeach</div>
    </x-site.section>

    <x-site.cta />
</x-site.layout>
