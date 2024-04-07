<?php

namespace App\Action;

use App\Models\Test;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\TronService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * deprecated
 * 按Transaction表的資料檢查是否confirmed,執行用戶餘額更新
 */
class SyncTronTransaction
{
    public function __invoke($x)
    {
        try {
            Log::debug('Sync start');
            $tronService = new TronService();

            $unConfirmedTransactions = Transaction::where('status', 'unconfirmed')
                ->where('processing', 0)
                // ->orderBy('created_at')
                ->take(20)
                ->get();
            $ids = $unConfirmedTransactions->map(function ($transaction) {
                return $transaction->id;
            });
            // 避免其他排程撈到一樣的
            Transaction::whereIn('id', $ids)->update([
                'processing' => 1
            ]);
            Log::debug("Fetch IDs $ids");

            foreach ($unConfirmedTransactions as $transaction) {
                Log::debug("process start $transaction->id");
                // check status
                $hasInBlock = $tronService->isTxConfirmed($transaction->tx_id);
                // 模擬處理太久
                sleep(2);
                if (!$hasInBlock) continue;
                $wallet = UserWallet::where('address_hex', $transaction->to_address)->first();
                if (!$wallet) continue;

                try {
                    DB::transaction(function () use ($wallet, $transaction) {
                        Log::debug("process db transaction $transaction->id");
                        // lock for update by id indexing
                        // $lockUser = User::where('id', $user->id)->lockForUpdate()->first();

                        $lockTransaction = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

                        $newAmount = bcadd($wallet->trx_balance, $lockTransaction->amount, UserWallet::TRX_DECIMAL);

                        // update user trx_balance
                        $wallet->update([
                            'trx_balance' => $newAmount
                        ]);
                        // 假設出錯
                        if ($transaction->id === 15) throw new Exception('id5出錯了');

                        // update transaction status to confirmed
                        $lockTransaction->update([
                            'status' => 'confirmed',
                            'processing' => 0
                        ]);
                        Log::debug("process db transaction done");
                    }, 3);
                } catch (Exception $e) {
                    // 發生exception要把transaction processing改回0
                    Log::error('transaction error' . $e->getMessage());
                    Transaction::where('id', $transaction->id)->update([
                        'processing' => 0,
                        'process_error' => $e->getMessage()
                    ]);
                }
            }
            Log::debug('Sync done');
        } catch (Exception $e) {
            Log::error('Sync error' . $e->getMessage());
        }
    }
}