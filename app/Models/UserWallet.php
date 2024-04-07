<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserWallet extends Model
{

    public const TRX_DECIMAL = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'private_key',
        'public_key',
        'address_hex',
        'address_base58',
        'trx_balance',
        'activate_tx_id',
        'last_transaction_id',
        'last_block_number',
        'last_block_time',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'private_key',
        'public_key',
    ];

    /**
     * Get the user that owns the phone.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    public function getTrxFormat($number_format = false) {
        $format_amount = ($number_format) ? number_format($this->trx_balance, static::TRX_DECIMAL) : $this->trx_balance;
        return rtrim(rtrim($format_amount, '0'), '.');
    }
}
