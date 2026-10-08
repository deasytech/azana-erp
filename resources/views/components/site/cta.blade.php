@props(['title' => 'Let us talk about what you need', 'text' => 'Tell us about the pigs, genetics or pork you are looking for and we will come back to you.'])
<section class="cta" aria-labelledby="cta-h">
    <div class="wrap reveal">
        <h2 id="cta-h">{{ $title }}</h2>
        <p>{{ $text }}</p>
        <div class="actions">
            <x-site.button :href="route('site.contact').'#enquiry'">Get in Touch</x-site.button>
            <x-site.button :href="route('site.products')" variant="outline">See Products</x-site.button>
        </div>
    </div>
</section>
