<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'decimal:2','disbursement_date'=>'date']; }
}
