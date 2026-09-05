<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'decimal:2','date_paid'=>'date']; }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function collectionCycle(): BelongsTo { return $this->belongsTo(CollectionCycle::class); }
}
