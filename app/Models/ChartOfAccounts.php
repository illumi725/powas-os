<?php

namespace App\Models;

use App\Http\Traits\ChartOfAccountsTraits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccounts extends Model
{
    use HasFactory;
    use ChartOfAccountsTraits;

    protected $primaryKey = 'account_number';
    public $incrementing = false;

    protected $fillable = [
        'account_number',
        'account_name',
        'alias',
        'description',
        'account_type',
        'normal_balance',
    ];

    public function transanctions(): HasMany
    {
        return $this->hasMany(Transanctions::class, 'account_number', 'account_number');
    }
}
