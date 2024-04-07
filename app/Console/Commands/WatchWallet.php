<?php
namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TronService;
use Illuminate\Console\Command;
use App\Models\UserWallet;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WatchWallet extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = "app:watch-wallet";

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "監控錢包地址轉入轉出交易記錄，更新用戶餘額";

  protected $logger;

  protected $tronService;

  public function __construct()
  {
    parent::__construct();
    $this->logger = Log::channel("watchwallet");
    $this->tronService = new TronService();
  }

  public function handle()
  {
    $times = 0;
    while (true) {
      $times++;
      $a=microtime(true);
      $this->main();
      $b=microtime(true);
      $time_spending = ($b - $a) * 1000;
      $this->log("執行第 $times 次,花費 $time_spending 毫秒");
      $this->wait(3); // 自己控制
    }
  }

  private function main()
  {
    // 監控用戶地址交易列表
    // 只撈取有開錢包的用戶
    $userWallets = UserWallet::get();
    // $userWallets = UserWallet::where('id', 1)->get();
    foreach ($userWallets as $userWallet) {
      $fingerprint = null;
      do {
        $payload = [
          "only_confirmed" => true,
          "order_by" => "block_timestamp,asc", // 從最舊的開始抓
        ];
        if ($fingerprint) {
          $payload = array_merge($payload, [
            "fingerprint" => $fingerprint,
          ]);
        }
        // if ($userWallet->last_block_time) {
        //   $payload = array_merge($payload, [
        //     "min_timestamp" => $userWallet->last_block_time + 1000
        //   ]);
        // }
        // dump($payload);
        $response = $this->tronService->getTransactionsV2($userWallet, $payload);
        if (!$response || $response["success"] !== true) {
          $this->log("Response有問題 $response");
          break;
        }
        $transactions = $response["data"];
        $fingerprint = Arr::get($response, "meta.fingerprint", null);
        for ($i = 0; $i < count($transactions); $i++) {
          // $this->log($transactions[$i]);
          $transaction = $transactions[$i];
          $result = $this->handleTransaction($transaction);
          if (!$result) {
            continue;
          } else {
            // $this->log("這筆完成" . $transaction["txID"]);
          }
        }
        // 表示最後一頁
        if ($fingerprint === null && count($transactions) > 0) {
          // 記錄最後一個blockNumber和txID
          $last = $transactions[count($transactions) - 1];
          // $this->log('記錄最後一個blockNumber和txID' . "(". $last['txID'] . ")");
          $userWallet->update([
            "last_block_number" => $last["blockNumber"],
            "last_transaction_id" => $last["txID"],
            "last_block_time" => $last["block_timestamp"],
          ]);
        }
      } while ($fingerprint !== null);
    }
  }

  private function handleTransaction(array $transaction)
  {
    $contractRet = Arr::get($transaction, "ret.0.contractRet", null);
    // 不成功跳過
    if (!$contractRet || $contractRet !== "SUCCESS") {
      $this->log("Transaction" . $transaction["txID"] . " 不成功跳過");
      return false;
    }

    $contract = Arr::get($transaction, "raw_data.contract.0", null);

    if (!$contract) {
      return false;
    }

    $type = Arr::get($contract, "type", null);
    $parameter = Arr::get($contract, "parameter", null);
    // 沒資料跳過
    if (!$parameter) {
      $this->log("Transaction" . $transaction["txID"] . " 沒parameter資料跳過");
      return false;
    }
    $value = Arr::get($parameter, "value", null);
    // 沒資料跳過
    if (!$value) {
      $this->log("Transaction" . $transaction["txID"] . " 沒value資料跳過");
      return false;
    }
    switch ($type) {
      case "TransferContract":
        // 檢查transaction表是否存在
        $transactionInTable = Transaction::where("tx_id", $transaction["txID"])->first();

        if ($transactionInTable && $transactionInTable["status"] === "confirmed") {
          // $this->log($transactionInTable["txID"] . "這筆處理過了");
          return false;
        }
        $from = $value["owner_address"];
        $to = $value["to_address"];
        $amount = $value["amount"];
        $fee = Arr::get($transaction, "net_fee", 0);
        $this->handleUserBalance(
          $from,
          $to,
          $transaction,
          $amount,
          $fee,
        );
        break;
      case "TriggerSmartContract": // 智能合約?? USDT??
        $this->log("TriggerSmartContract 先不處理");
        break;
      case "AccountCreateContract": // 激活帳號
        $this->log("AccountCreateContract 先不處理");
        break;
      default:
        $this->log("收到其他交易類型 $type");
        break;
    }
    return true;
  }

  // 帶進來的值都是sun單位，進db要除10的6次方
  private function handleUserBalance(
    string $from,
    string $to,
    $transaction,
    $amount = 0,
    $net_fee = 0,
  ) {
    try {
      DB::beginTransaction();
      // $this->log("處理用戶餘額事務啟動");
      // TRX 轉出地址
      $from_base58_address = $this->tronService->getAddressFromHex($from);
      // TRX 轉入地址
      $to_base58_address = $this->tronService->getAddressFromHex($to);

      $from_wallet = UserWallet::where("address_hex", $from)->lockForUpdate()->first();
      $to_wallet = UserWallet::where("address_hex", $to)->lockForUpdate()->first();

      if ($from_wallet) {
        // 手續費 net_fee 跟 轉出金額 amount
        $deduction_amount = bcadd($amount, $net_fee, UserWallet::TRX_DECIMAL);
        // 帶進來的值都是sun單位，進db要除10的6次方
        $trx_deduction_amount = bcdiv($deduction_amount, 10 ** UserWallet::TRX_DECIMAL, UserWallet::TRX_DECIMAL);
        $remain_amount = bcsub($from_wallet->trx_balance, $trx_deduction_amount, UserWallet::TRX_DECIMAL);
        $this->log(
          "($from_base58_address)轉出 Current Balance: $from_wallet->trx_balance, Transfer: $trx_deduction_amount, Account Remain: $remain_amount",
        );
        $from_wallet->update(["trx_balance" => $remain_amount]);
      }
      if ($to_wallet) {
        // 帶進來的值都是sun單位，進db要除10的6次方
        $transfer_amount = bcdiv($amount, 10 ** UserWallet::TRX_DECIMAL, UserWallet::TRX_DECIMAL);
        $remain_amount = bcadd($to_wallet->trx_balance, $transfer_amount, UserWallet::TRX_DECIMAL);
        $this->log(
          "($to_base58_address)轉入 Current Balance: $to_wallet->trx_balance, Transfer: $transfer_amount, Account Remain: $remain_amount",
        );
        $to_wallet->update(["trx_balance" => $remain_amount]);
      }
      Transaction::updateOrCreate([
        "tx_id" => $transaction["txID"],
      ], [
        "from_address" => $from,
        "to_address" => $to,
        "type" => 'TransferContract',
        "amount" => $amount,
        "fee" => $net_fee,
        "status" => "confirmed",
        "block_number" => $transaction['blockNumber'],
        "tx_time" => Arr::get($transaction, "raw_data.timestamp", 0),
        "raw_data" => json_encode($transaction),
      ]);
      DB::commit();
    } catch (Exception $e) {
      $this->log("處理用戶餘額發生錯誤(" . $e->getMessage() . ")");
      DB::rollBack();
    }
  }

  private function log($msg) {
    $this->logger->info($msg);
    if (php_sapi_name() === 'cli')  {
      $this->info(is_array($msg) ? json_encode($msg) : $msg);
    } else {
      dump($msg);
    }
  }

  private function wait($seconds) {
    $this->log("休息 $seconds 秒....");
    sleep($seconds);
  }
}
