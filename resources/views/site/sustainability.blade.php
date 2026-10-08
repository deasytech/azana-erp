<x-site.layout :site="$site" title="Sustainability" current="site.sustainability" :description="$site['name'].' approach to responsible livestock management, efficient production and less waste.'">
    <x-site.page-head eyebrow="Sustainability" title="Responsible farming, built to last" intro="Looking after animals, feed and resources carefully is good for the land and good for business." :crumbs="['Home' => route('site.home')]" />

    <x-site.section id="approach" :heading="true">
        <div class="split flip">
            <div class="frame reveal"><x-site.image name="sustainability" alt="The farm and its surroundings" /></div>
            <div>
                <x-site.heading for="approach" eyebrow="Our approach" title="How we farm responsibly" />
                <ul class="ticks reveal">
                    @foreach (config('website.sustainability') as $item)<li><x-site.icon name="check" /><span><strong>{{ $item['title'] }}.</strong> {{ $item['text'] }}</span></li>@endforeach
                </ul>
            </div>
        </div>
    </x-site.section>

    <x-site.cta />
</x-site.layout>
