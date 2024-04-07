<?php

namespace App\Support;

use App\Models\UserWallet;
use Carbon\Carbon;
use IEXBase\TronAPI\TronAwareTrait;
use Illuminate\Support\Arr;

class TronTransaction {

  use TronAwareTrait;

  public $tx_id;
  public $block_number;
  public $type;
  public $amount; // sun unit
  public $trx; // 怎麼判斷這個transaction是trx或usdt
  public $time;
  public $datetime;

  public function __construct(array $data) {
    $this->tx_id = $data['txID'];
    $this->time = Arr::get($data, 'raw_data.timestamp', null);
    $this->datetime = ($this->time)
      ? Carbon::createFromTimestampMs($this->time, 'Asia/Taipei')->toDateTimeString()
      : null;
    $this->type = Arr::get($data, 'raw_data.contract.0.type', 'Undefined');
    $this->amount = Arr::get($data, 'raw_data.contract.0.parameter.value.amount', 0);
    $this->trx = rtrim(rtrim(bcdiv((string)$this->amount, bcpow('10', UserWallet::TRX_DECIMAL), 8), '0'), '.');
    $this->block_number = Arr::get($data, 'blockNumber');
  }
}