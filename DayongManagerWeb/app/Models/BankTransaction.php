<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'decimal:2','transaction_date'=>'date']; }
}
