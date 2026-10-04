<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $guarded = [];
    public function scopeLiving(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('member_status', '!=', 'Deceased')->whereNull('date_of_death');
    }

    public function isDeceased(): bool
    {
        return $this->member_status === 'Deceased' || $this->date_of_death !== null;
    }
    public function getCouncilAttribute(?string $value): ?string
    {
        return self::normalizeCouncil($value);
    }

    public function setCouncilAttribute(?string $value): void
    {
        $this->attributes['council'] = self::normalizeCouncil($value);
    }

    public static function normalizeCouncil(?string $value): ?string
    {
        if ($value !== null && preg_match('/^\s*(\d+)\s*-?\s*[a-z]*\s*$/i', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    protected function casts(): array { return ['birth_date'=>'date','registration_date'=>'date','date_of_death'=>'date','service_date'=>'date','claim_received_date'=>'date','is_fourth_degree'=>'boolean']; }
    public function startCycle(): BelongsTo { return $this->belongsTo(CollectionCycle::class, 'start_cycle_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function registrationPayments(): HasMany { return $this->payments()->where('amount', '>', 0)->whereHas('collectionCycle', fn ($query) => $query->where('type', 'Registration Fee')); }
    public function getFullNameAttribute(): string { return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' '); }
}
