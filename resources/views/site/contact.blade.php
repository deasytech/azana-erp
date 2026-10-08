<x-site.layout :site="$site" :livewire="true" title="Contact us" current="site.contact" :description="'Contact '.$site['name'].' about pigs, boar semen and pork.'" :schema="[
    '@context' => 'https://schema.org', '@type' => 'ContactPage', 'name' => 'Contact '.$site['name'], 'url' => route('site.contact'),
]">
    <x-site.page-head eyebrow="Contact" title="Talk to us" intro="Whether you are buying pigs, planning a breeding programme or looking for fresh pork, we would like to hear from you." :crumbs="['Home' => route('site.home')]" />

    <x-site.section id="enquiry" :heading="true">
        <h2 id="enquiry-h" class="sr">Contact details and enquiry form</h2>
        <div class="contact-grid">
            <x-site.contact-details :site="$site" />
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
