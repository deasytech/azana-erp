@php
    $about = $site['about'] ?: [
        $site['name'].' is a livestock farming business built around the whole journey of the pig: genetics and breeding, feed, animal health, growing and quality pork.',
        'We bring these stages together on one management system, so every decision rests on clear records and every product can be traced.',
    ];
@endphp
<x-site.layout :site="$site" :livewire="true" :title="$site['name']" current="site.home"
    :description="$site['tagline'] ?: 'Modern livestock farming, quality pigs, boar semen and fresh pork.'" :schema="[
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $site['name'], 'legalName' => $site['legal_name'], 'url' => route('site.home'),
        'logo' => asset('images/branding/logo.jpeg'), 'telephone' => $site['phone'], 'email' => $site['email'], 'address' => $site['address'],
    ]">
    <section class="hero" aria-labelledby="hero-h">
        <div class="hero-media"><x-site.image name="hero" alt="" ratio="" :eager="true" /></div>
        <div class="wrap hero-in">
            <p class="eyebrow">Integrated Princess Azana Farms</p>
            <h1 id="hero-h">Modern farming. <em>Quality livestock.</em> Sustainable growth.</h1>
            <p class="lead">Pigs raised with care, genetics bred with purpose and fresh pork handled with pride, all run on one connected system.</p>
            <div class="actions">
                <x-site.button href="#operations">Explore Our Farm</x-site.button>
                <x-site.button :href="route('site.contact').'#enquiry'" variant="ghost">Contact Us</x-site.button>
            </div>
        </div>
    </section>

    <x-site.section id="about" :heading="true">
        <div class="split">
            <div class="frame reveal"><x-site.image name="about" alt="Farm workers caring for pigs" ratio="r45" /></div>
            <div>
                <x-site.heading id="about" eyebrow="About us" title="Integrated agriculture, run with discipline" />
                <div class="prose reveal">
                    @foreach ($about as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                </div>
                <ul class="ticks reveal">
                    <li><x-site.icon name="check" />Livestock production and breeding</li>
                    <li><x-site.icon name="check" />Quality control from feed to finished product</li>
                    <li><x-site.icon name="check" />Responsible management and modern operations</li>
                </ul>
                <p class="reveal" style="margin-top:2rem"><x-site.button :href="route('site.about')" variant="outline">More About Us</x-site.button></p>
            </div>
        </div>
    </x-site.section>

    <x-site.section id="operations" tone="cream" :heading="true">
        <x-site.heading id="operations" eyebrow="Our operations" title="From breeding pen to finished product" intro="Every stage of the pig's life is managed on the farm, which is how we keep quality consistent." />
        <div class="grid g3">
            @foreach (config('website.operations') as $op)<x-site.operation-card :op="$op" />@endforeach
        </div>
    </x-site.section>

    <x-site.section id="technology" tone="dark" :heading="true">
        <div class="split">
            <x-site.heading id="technology" eyebrow="Modern agriculture" title="Technology behind better farming" intro="We use modern tools to see what is happening on the farm and act on it early. It is how we keep animals healthy, feed efficient and records dependable." />
            <ul class="tech-list reveal">
                @foreach (config('website.technology') as $item)<li><x-site.icon :name="$item['icon']" /><span>{{ $item['text'] }}</span></li>@endforeach
            </ul>
        </div>
    </x-site.section>

    <x-site.section id="why" :heading="true">
        <x-site.heading id="why" eyebrow="Why Azana Farms" title="What you can expect from us" :center="true" />
        <div class="grid g3">
            @foreach (config('website.values') as $item)<x-site.feature-card :item="$item" />@endforeach
        </div>
    </x-site.section>

    <x-site.section id="products" tone="cream" :heading="true">
        <x-site.heading id="products" eyebrow="Products &amp; services" title="What we offer" intro="Tell us what you need and we will confirm what is ready and when." />
        <div class="grid g3">
            @foreach (config('website.sections') as $kind => $section)<x-site.product-card :kind="$kind" :section="$section" :image="['pigs' => 'livestock', 'semen' => 'breeding', 'meat' => 'meat'][$kind] ?? 'livestock'" />@endforeach
        </div>
    </x-site.section>

    <x-site.section id="sustainability" :heading="true">
        <div class="split flip">
            <div class="frame reveal"><x-site.image name="sustainability" alt="The farm and its surroundings" /></div>
            <div>
                <x-site.heading id="sustainability" eyebrow="Sustainability" title="Responsible farming, built to last" intro="Looking after animals, feed and resources carefully is good for the land and good for business." />
                <ul class="ticks reveal">
                    @foreach (config('website.sustainability') as $item)<li><x-site.icon name="check" /><span><strong>{{ $item['title'] }}.</strong> {{ $item['text'] }}</span></li>@endforeach
                </ul>
            </div>
        </div>
    </x-site.section>

    <x-site.cta />

    <x-site.section id="enquiry" tone="cream" :heading="true">
        <div class="contact-grid">
            <div>
                <x-site.heading id="enquiry" eyebrow="Contact" title="Talk to us" intro="Whether you are buying pigs, planning a breeding programme or looking for fresh pork, we would like to hear from you." />
                <x-site.contact-details :site="$site" />
            </div>
            <div class="panel">
                @if ($site['enquiries_enabled'])
                    @livewire(\App\Livewire\Public\EnquiryForm::class)
                @else
                    <p>We are not taking enquiries through the website right now. Please use the contact details shown.</p>
                @endif
            </div>
        </div>
    </x-site.section>
</x-site.layout>
