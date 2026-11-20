<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['studio_id', 'row_label', 'number', 'grid_x', 'grid_y'])]
class Seat extends Model
{
    // The layout is static; timestamps would only add noise.
    public $timestamps = false;

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /** Human label shown on tickets and to screen readers, e.g. "C7". */
    public function label(): string
    {
        return $this->row_label.$this->number;
    }
}
