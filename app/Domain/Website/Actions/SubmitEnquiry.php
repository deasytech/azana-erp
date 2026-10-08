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

        $kind = EnquiryKind::tryFrom((string) ($data['kind'] ?? '')) ?? throw new DomainException('Choose what your enquiry is about.', 'enquiry_kind');
        $name = trim((string) ($data['name'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        $phone = filled($data['phone'] ?? null) ? trim($data['phone']) : null;

        $name !== '' || throw new DomainException('Tell us your name.', 'enquiry_name');
        mb_strlen($message) >= 10 || throw new DomainException('Tell us a little more about what you need.', 'enquiry_message');
        ($email || $phone) || throw new DomainException('Give a phone number or an e-mail address so we can reply.', 'enquiry_contact');
        ! $email || filter_var($email, FILTER_VALIDATE_EMAIL) || throw new DomainException('That e-mail address is not valid.', 'enquiry_email');

        $listing = filled($data['listing_id'] ?? null)
            ? Listing::where('is_published', true)->find($data['listing_id']) ?? throw new DomainException('That product is no longer listed.', 'enquiry_listing')
            : null;

        $enquiry = DB::transaction(function () use ($data, $kind, $name, $message, $email, $phone, $listing) {
            // The same person sending the same words twice in a few minutes (a double click, a retry) is one enquiry.
            $existing = Enquiry::where('message', $message)->where('name', $name)
                ->when($email, fn ($q) => $q->where('email', $email), fn ($q) => $q->whereNull('email'))
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            return [Enquiry::create([
                'number' => sprintf('ENQ-%06d', ($this->nextNumber)('enquiry')),
                'kind' => $kind,
                'listing_id' => $listing?->id,
                'name' => mb_substr($name, 0, 150),
                'organisation' => filled($data['organisation'] ?? null) ? mb_substr(trim($data['organisation']), 0, 150) : null,
                'email' => $email,
                'phone' => $phone ? mb_substr($phone, 0, 40) : null,
                'quantity' => filled($data['quantity'] ?? null) ? mb_substr(trim($data['quantity']), 0, 100) : null,
                'message' => mb_substr($message, 0, 2000),
                'source_ip_hash' => filled($data['source'] ?? null) ? hash('sha256', $data['source'].config('app.key')) : null,
            ]), true];
        });

        [$enquiry, $isNew] = $enquiry;

        if ($isNew) {
            $this->tellStaff($enquiry);
        }

        return $enquiry;
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
