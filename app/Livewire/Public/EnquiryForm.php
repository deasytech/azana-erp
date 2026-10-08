<?php

namespace App\Livewire\Public;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Actions\SubmitEnquiry;
use App\Domain\Website\Models\Listing;
use App\Enums\EnquiryKind;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** The enquiry form. It checks the shape of the input and slows abuse down; what an enquiry means is decided by SubmitEnquiry. */
class EnquiryForm extends Component
{
    public string $kind = 'general';

    public ?int $listingId = null;

    public string $name = '';

    public string $organisation = '';

    public string $email = '';

    public string $phone = '';

    public string $quantity = '';

    public string $message = '';

    /** A honeypot: people never see this field, so anything in it came from a script. */
    public string $website = '';

    public ?string $sent = null;

    public function mount(?string $kind = null, ?int $listingId = null): void
    {
        $listing = $listingId ? Listing::where('is_published', true)->find($listingId) : null;

        $this->listingId = $listing?->id;
        $this->kind = $listing && EnquiryKind::tryFrom($listing->kind->value) ? $listing->kind->value : (EnquiryKind::tryFrom((string) $kind)?->value ?? 'general');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(EnquiryKind::class)],
            'name' => ['required', 'string', 'max:150'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]{6,40}$/', 'required_without:email'],
            'quantity' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'email.required_without' => 'Give an e-mail address or a phone number so we can reply.',
            'phone.required_without' => 'Give a phone number or an e-mail address so we can reply.',
            'phone.regex' => 'Use digits, spaces, + ( ) and - only.',
            'message.min' => 'Tell us a little more about what you need.',
        ];
    }

    public function submit(SubmitEnquiry $submit): void
    {
        $this->validate();

        $ip = (string) request()->ip();

        if (RateLimiter::tooManyAttempts("enquiry-minute:{$ip}", 3) || RateLimiter::tooManyAttempts("enquiry-day:{$ip}", 30)) {
            $this->addError('message', 'You have sent several enquiries already. Please wait a few minutes, or call us.');

            return;
        }

        RateLimiter::hit("enquiry-minute:{$ip}", 60);
        RateLimiter::hit("enquiry-day:{$ip}", 86400);

        // A script filled in the hidden field: look as if it worked and keep nothing.
        if ($this->website !== '') {
            $this->sent = 'ENQ';

            return;
        }

        try {
            $enquiry = $submit([
                'kind' => $this->kind, 'listing_id' => $this->listingId, 'name' => $this->name, 'organisation' => $this->organisation,
                'email' => $this->email, 'phone' => $this->phone, 'quantity' => $this->quantity, 'message' => $this->message, 'source' => $ip,
            ]);
        } catch (DomainException $e) {
            $this->addError('message', $e->getMessage());

            return;
        }

        $this->sent = $enquiry->number;
        $this->reset('name', 'organisation', 'email', 'phone', 'quantity', 'message');
    }

    public function render(): View
    {
        return view('livewire.public.enquiry-form', ['kinds' => EnquiryKind::cases()]);
    }
}
