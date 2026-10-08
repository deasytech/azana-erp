@props(['site'])
<ul class="details">
    @if ($site['address'])<li><x-site.icon name="pin" /><div><strong>Address</strong>{!! nl2br(e($site['address'])) !!}</div></li>@endif
    @if ($site['phone'])<li><x-site.icon name="phone" /><div><strong>Phone</strong><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['phone']) }}">{{ $site['phone'] }}</a></div></li>@endif
    @if ($site['email'])<li><x-site.icon name="mail" /><div><strong>E-mail</strong><a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></div></li>@endif
    @if ($site['whatsapp'])<li><x-site.icon name="message" /><div><strong>WhatsApp</strong><a href="https://wa.me/{{ $site['whatsapp'] }}" rel="noopener">Message us on WhatsApp</a></div></li>@endif
    @if ($site['hours'])<li><x-site.icon name="clock" /><div><strong>Hours</strong>{{ $site['hours'] }}</div></li>@endif
</ul>
