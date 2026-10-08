<x-site.layout :site="$site" :livewire="true" :title="$section['title']" :description="$section['intro']">
    <x-site.page-head eyebrow="Products &amp; services" :title="$section['title']" :intro="$section['intro']" :crumbs="['Home' => route('site.home'), 'Products' => route('site.products')]" />

    <x-site.section id="list" :heading="true">
        @if ($rows->isNotEmpty())
            <div class="grid g3">@foreach ($rows as $row)<x-site.listing-card :row="$row" />@endforeach</div>
        @else
            <p class="prose">Nothing is listed here at the moment. Ask us what is coming up.</p>
        @endif
    </x-site.section>

    @if ($site['enquiries_enabled'])
        <x-site.section id="enquiry" tone="cream" :heading="true">
            <div class="narrow">
                <x-site.heading id="enquiry" eyebrow="Enquiry" :title="'Ask about '.strtolower($section['title'])" />
                <div class="panel">@livewire(\App\Livewire\Public\EnquiryForm::class, ['kind' => $kind->value])</div>
            </div>
        </x-site.section>
    @endif
</x-site.layout>
