<x-site.layout :site="$site" title="Our operations" current="site.operations" :description="'How '.$site['name'].' runs livestock production, breeding, feed, processing and farm management.'">
    <x-site.page-head eyebrow="Our operations" title="From breeding pen to finished product" intro="Every stage of the pig's life is managed on the farm, which is how we keep quality consistent." :crumbs="['Home' => route('site.home')]" />

    <x-site.section id="ops" :heading="true">
        <div class="grid g3">@foreach (config('website.operations') as $op)<x-site.operation-card :op="$op" />@endforeach</div>
    </x-site.section>

    <x-site.section id="technology" tone="dark" :heading="true">
        <div class="split">
            <x-site.heading for="technology" eyebrow="Modern agriculture" title="Technology behind better farming" intro="We use modern tools to see what is happening on the farm and act on it early." />
            <ul class="tech-list reveal">@foreach (config('website.technology') as $item)<li><x-site.icon :name="$item['icon']" /><span>{{ $item['text'] }}</span></li>@endforeach</ul>
        </div>
    </x-site.section>

    <x-site.cta />
</x-site.layout>
