<?php

namespace App\Enums;

/** What a stock movement is. Every stock change is one of these, recorded in the inventory ledger. */
enum InventoryTransactionType: string
{
    case Opening = 'opening';
    case Purchase = 'purchase';
    case Receipt = 'receipt';
    case Production = 'production';
    case Consumption = 'consumption';
    case Sale = 'sale';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Return = 'return';
    case Wastage = 'wastage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    /** Types that can only add stock. */
    public function adds(): bool
    {
        return in_array($this, [self::Opening, self::Purchase, self::Receipt, self::Production, self::TransferIn], true);
    }

    /** Types that can only remove stock. */
    public function removes(): bool
    {
        return in_array($this, [self::Consumption, self::Sale, self::TransferOut, self::Wastage], true);
    }

    /** Stock leaving for use or sale must not be past its expiry; write-offs, returns and corrections may be. */
    public function blocksExpiredStock(): bool
    {
        return in_array($this, [self::Consumption, self::Sale, self::TransferOut], true);
    }
}
