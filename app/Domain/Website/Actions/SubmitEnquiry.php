<?php

namespace App\Domain\Website\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Notifications\Notifier;
use App\Domain\Website\Models\Enquiry;
use App\Domain\Website\Models\Listing;
use App\Domain\Website\Notifications\EnquiryReceived;
use App\Enums\EnquiryKind;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Records what a visitor asked for and tells staff. It reserves no stock, quotes no price and creates no order: staff answer the visitor
 * and, if it goes ahead, raise the order through the sales screens, where the credit, discount and stock rules apply.
 */
class SubmitEnquiry
{
    private const DUPLICATE_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly NextNumber $nextNumber,
        private readonly ResolveSettings $settings,
        private readonly Notifier $notifier,
    ) {}

    /** @param array{kind: string, name: string, message: string, email?: ?string, phone?: ?string, organisation?: ?string, quantity?: ?string, listing_id?: ?int, source?: ?string} $data */
    public function __invoke(array $data): Enquiry
    {
        $this->settings->get('website.enquiries_enabled') || throw new DomainException('Enquiries are not being taken on the website right now. Please call or e-mail us.', 'enquiries_closed');

        $fields = $this->validated($data);
        [$enquiry, $isNew] = DB::transaction(fn () => $this->findOrCreate($fields));

        if ($isNew) {
            $this->tellStaff($enquiry);
        }

        return $enquiry;
    }

    /** @return array{kind: EnquiryKind, name: string, message: string, email: ?string, phone: ?string, listing: ?Listing, data: array<string, mixed>} */
    private function validated(array $data): array
    {
        $kind = EnquiryKind::tryFrom((string) ($data['kind'] ?? '')) ?? throw new DomainException('Choose what your enquiry is about.', 'enquiry_kind');
        $name = trim((string) ($data['name'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $email = $this->text($data['email'] ?? null);
        $email = $email === null ? null : strtolower($email);
        $phone = $this->text($data['phone'] ?? null);

        $name !== '' || throw new DomainException('Tell us your name.', 'enquiry_name');
        mb_strlen($message) >= 10 || throw new DomainException('Tell us a little more about what you need.', 'enquiry_message');
        ($email || $phone) || throw new DomainException('Give a phone number or an e-mail address so we can reply.', 'enquiry_contact');
        ! $email || filter_var($email, FILTER_VALIDATE_EMAIL) || throw new DomainException('That e-mail address is not valid.', 'enquiry_email');

        return ['kind' => $kind, 'name' => $name, 'message' => $message, 'email' => $email, 'phone' => $phone, 'listing' => $this->listing($data['listing_id'] ?? null), 'data' => $data];
    }

    private function listing(mixed $id): ?Listing
    {
        return filled($id) ? Listing::where('is_published', true)->find($id) ?? throw new DomainException('That product is no longer listed.', 'enquiry_listing') : null;
    }

    /** @return array{0: Enquiry, 1: bool} the enquiry and whether it was just made */
    private function findOrCreate(array $fields): array
    {
        // The same person sending the same words twice in a few minutes (a double click, a retry) is one enquiry.
        $existing = Enquiry::where('message', $fields['message'])->where('name', $fields['name'])
            ->when($fields['email'], fn ($q, $email) => $q->where('email', $email), fn ($q) => $q->whereNull('email')->where('phone', $this->text($fields['phone'], 40)))
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $data = $fields['data'];
        $source = $this->text($data['source'] ?? null);

        return [Enquiry::create([
            'number' => sprintf('ENQ-%06d', ($this->nextNumber)('enquiry')),
            'kind' => $fields['kind'],
            'listing_id' => $fields['listing']?->id,
            'name' => mb_substr($fields['name'], 0, 150),
            'organisation' => $this->text($data['organisation'] ?? null, 150),
            'email' => $fields['email'],
            'phone' => $this->text($fields['phone'], 40),
            'quantity' => $this->text($data['quantity'] ?? null, 100),
            'message' => mb_substr($fields['message'], 0, 2000),
            'source_ip_hash' => $source ? hash('sha256', $source.config('app.key')) : null,
        ]), true];
    }

    /** Trimmed text, cut to $max characters, or null when there is none. */
    private function text(mixed $value, ?int $max = null): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $max ? mb_substr($value, 0, $max) : $value;
    }

    private function tellStaff(Enquiry $enquiry): void
    {
        $notification = new EnquiryReceived($enquiry);

        User::permission('website.edit')->where('is_active', true)->get()
            ->each(fn (User $user) => $this->notifier->send($user, $notification));

        $extra = trim((string) $this->settings->get('website.enquiry_notify_email'));

        if ($extra !== '' && filter_var($extra, FILTER_VALIDATE_EMAIL)) {
            try {
                Notification::route('mail', $extra)->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
