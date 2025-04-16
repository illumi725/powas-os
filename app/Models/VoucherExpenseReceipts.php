<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherExpenseReceipts extends Model
{
    use HasFactory;
    protected $fillable = [
        'voucher_id',
        'receipt_path'
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Vouchers::class, 'voucher_id', 'voucher_id');
    }
}
