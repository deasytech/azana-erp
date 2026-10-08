<?php

namespace App\Domain\Website\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Farm;

/** What the public site says about the business: the farm's own record plus the website settings. Nothing is typed twice. */
class GetSiteProfile
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return array{name: string, legal_name: ?string, tagline: string, about: list<string>, address: ?string, phone: ?string, email: ?string, whatsapp: ?string, hours: string, enquiries_enabled: bool} */
    public function __invoke(): array
    {
        $farm = Farm::where('is_active', true)->orderBy('id')->first();
        $whatsapp = preg_replace('/\D+/', '', (string) $this->settings->get('website.whatsapp_number'));

        return [
            'name' => $farm?->name ?? config('app.name'),
            'legal_name' => $farm?->legal_name,
            'tagline' => (string) $this->settings->get('website.tagline'),
            'about' => array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $this->settings->get('website.about')) ?: []))),
            'address' => $farm?->address,
            'phone' => $farm?->phone,
            'email' => $farm?->email,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'hours' => (string) $this->settings->get('website.opening_hours'),
            'enquiries_enabled' => (bool) $this->settings->get('website.enquiries_enabled'),
        ];
    }
}
