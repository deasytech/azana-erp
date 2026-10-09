<?php

namespace App\Domain\Import\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'row_number' => 'integer'];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(DataImport::class, 'data_import_id');
    }
}
