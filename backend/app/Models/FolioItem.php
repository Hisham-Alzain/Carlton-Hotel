<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = ['folio_id', 'description', 'amount_usd', 'source_type', 'source_id'];

    protected $casts = ['amount_usd' => 'decimal:2'];

    public function folio(): BelongsTo { return $this->belongsTo(Folio::class); }
}
