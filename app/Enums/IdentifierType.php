<?php

namespace App\Enums;

enum IdentifierType: string
{
    case EarTag = 'ear_tag';
    case Rfid = 'rfid';
    case Qr = 'qr';
    case Barcode = 'barcode';
    case Tattoo = 'tattoo';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::EarTag => 'Ear tag',
            self::Rfid => 'RFID',
            self::Qr => 'QR code',
            default => ucfirst($this->value),
        };
    }
}
