<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['birth_date'=>'date','registration_date'=>'date','date_of_death'=>'date','service_date'=>'date','claim_received_date'=>'date','is_fourth_degree'=>'boolean']; }
    public function startCycle(): BelongsTo { return $this->belongsTo(CollectionCycle::class, 'start_cycle_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function getFullNameAttribute(): string { return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' '); }
}
