<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionCycle extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['expected_amount'=>'decimal:2','start_date'=>'date','due_date'=>'date','active'=>'boolean']; }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
