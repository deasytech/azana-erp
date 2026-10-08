<?php

namespace App\Domain\Website\Actions;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Models\Enquiry;
use App\Enums\EnquiryStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moves an enquiry along: new, contacted, quoted, then closed (or spam). A finished enquiry can be reopened. "Converted" is reached only by
 * turning the enquiry into a customer (ConvertEnquiryToCustomer) and is final.
 */
class UpdateEnquiryStatus
{
    public function __invoke(Enquiry $enquiry, EnquiryStatus $to, ?string $note = null, ?User $actor = null): Enquiry
    {
        return DB::transaction(function () use ($enquiry, $to, $note, $actor) {
            $enquiry = Enquiry::lockForUpdate()->findOrFail($enquiry->id);

            $enquiry->status !== EnquiryStatus::Converted || throw new DomainException("{$enquiry->number} was turned into a customer and cannot be changed.", 'enquiry_converted');
            $to !== EnquiryStatus::Converted || throw new DomainException('Use "Make customer" to convert an enquiry.', 'enquiry_convert');

            $enquiry->update([
                'status' => $to,
                'handled_by' => ($actor ?? Auth::user())?->getKey(),
                'handled_at' => now(),
                'staff_note' => filled($note) ? trim($note) : $enquiry->staff_note,
            ]);

            return $enquiry;
        });
    }
}
