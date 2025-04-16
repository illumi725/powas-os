<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLogs extends Model
{
    use HasFactory;

    protected $primaryKey = 'log_id';
    public $incrementing = false;

    protected $fillable = [
        'login_id',
        'login_result',
        'user_id',
    ];

    public function loginlogs(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
