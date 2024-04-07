<?php
namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TronService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\UserWallet;
use Exception;
use IEXBase\TronAPI\Exception\TronException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WatchTronBlock extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = "app:watch-block";

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "監控Tron區塊轉入轉出交易記錄，更新用戶餘額";

  protected $logger;

  protected $tronService;

  protected $processed_transactions = [];

  public function __construct()
  {
    parent::__construct();
    $this->logger = Log::channel("watchblock");
    $this->tronService = new TronService();
  }

  /**
   * Execute the console command.
   */
  public function handle()
  {
    try {
      $blockNumber = 0;
      $startNumber = 0;
      while (true) {
        // 獲取最新區塊
        if ($blockNumber === 0) {
          $blockResponse = $this->tronService->getLatestBlock();
        } else {
          try {
            $blockNumber++;
            $blockResponse = $this->tronService->getBlockByNumber($blockNumber);
          } catch (TronException $e) {
            if ($e->getMessage() === "Block not found") {
              $blockNumber = $startNumber;
              $this->log("Block($blockNumber) 找不到，到底了重頭掃描...");
              $this->wait(3);
              continue;
            }
          }
        }
        if (!isset($blockResponse["blockID"]) || !isset($blockResponse["block_header"])) {
          continue;
        }

        $blockNumber = Arr::get($blockResponse, "block_header.raw_data.number");
        if ($startNumber === 0) $startNumber = $blockNumber;
        $blockID = Arr::get($blockResponse, "blockID");
        $blockTransactions = Arr::get($blockResponse, "transactions", []);
  
        $this->log("區塊高度 $blockNumber , 區塊哈希 $blockID, 交易數量" . count($blockTransactions));
  
        // 沒有交易
        if (count($blockTransactions) === 0) {
          continue;
        }
  
        // 遍歷交易
        foreach ($blockTransactions as $transaction) {
          $result = $this->handleTransaction($transaction, $blockNumber);
          if (!$result) {
            continue;
          } else {
            // $this->log("這筆完成" . $transaction["txID"]);
          }
        }
        // 等待2秒，3秒出一區塊
        $this->wait(2); // 自己控制
      }
    } catch (Exception $e) {
      $this->log('Error' . $e->getMessage());
    }
  }

  private function handleTransaction($transaction, $blockNumber)
  {
    $contractRet = Arr::get($transaction, "ret.0.contractRet", null);
    // 不成功跳過
    if (!$contractRet || $contractRet !== "SUCCESS") {
      // $this->log("Transaction" . $transaction["txID"] . " 不成功跳過");
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
      // $this->log("Transaction" . $transaction["txID"] . " 沒parameter資料跳過");
      return false;
    }
    $value = Arr::get($parameter, "value", null);
    // 沒資料跳過
    if (!$value) {
      // $this->log("Transaction" . $transaction["txID"] . " 沒value資料跳過");
      return false;
    }
    switch ($type) {
      case "TransferContract":
        // 先檢查 from 或 to 認不認識
        $from = $value["owner_address"];
        $to = $value["to_address"];
        $exists = UserWallet::whereIn('address_hex', [ $from, $to ])->exists();
        if (!$exists) {
          $this->log("遇到不認識的人 From ($from), To ($to) ");
          return false;
        }

        // 查找transaction
        $transactionInTable = Transaction::where('tx_id', $transaction['txID'])->first();

        if ($transactionInTable && $transactionInTable['status'] === 'confirmed') {
          $this->log($transactionInTable["txID"] . "這筆處理過了");
          return false;
        }

        // 金額
        $amount = $value["amount"];
        $fee = Arr::get($transaction, "net_fee", 0);
        $this->handleUserBalance(
          $from,
          $to,
          $transaction,
          $blockNumber,
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
    $blockNumber,
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
        "block_number" => $blockNumber,
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
