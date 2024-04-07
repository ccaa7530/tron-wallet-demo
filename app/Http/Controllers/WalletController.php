<?php

namespace App\Http\Controllers;

use App\Console\Commands\WatchWallet;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\TronService;
use Exception;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function list() {
      try {
        $users = User::with('wallet')->get();
        $tronService = new TronService();

        foreach ($users as $user) {
          if (!$user->wallet) continue;

          $wallet_info = $tronService->getAddressFromGrid($user->wallet->address_base58);
          $user['wallet_info'] = $wallet_info;
        }

        $balance = $tronService->getAdminAccountBalance();

        // get admin account balancce
        $admin_account = [
          'address' => TronService::ACTIVE_ACCOUNT['address_base58'],
          'balance' => $balance
        ];

        return view('wallet', [
          'users' => $users,
          'admin_account' => $admin_account
        ]);
      } catch (Exception $e) {
        return redirect()->route('welcome')->withErrors([
          'exception' => "非預期錯誤，Error: {$e->getMessage()}"
        ]);
      }
    }

    public function detail(Request $request, $address) {
      try {
        $userWallet = UserWallet::where('address_base58', $address)->with('user')->first();
        if (!$userWallet) {
          return redirect()->back()->withErrors([
            'not_found' => "查無此錢包"
          ]);
        }
        $filters = [];
        if ($request->has('fingerprint')) {
          $filters['fingerprint'] = $request->get('fingerprint');
        }
        $tronService = new TronService();
        $transactions = $tronService->getTransactions($userWallet, $filters);

        return view('walletDetail', [
          'userWallet' => $userWallet,
          'transactions' => $transactions['data'],
          'transactionMeta' => $transactions['meta']
        ]);
      } catch (Exception $e) {
        return redirect()->route('wallet.list')->withErrors([
          'exception' => "非預期錯誤，Error: {$e->getMessage()}"
        ]);
      }
    }

    public function createWallet(Request $request)
    {
      try {
        $userId = $request->input('user_id');
        $user = User::find($userId);
        if (!$user) throw new Exception('User not found');

        $tronService = new TronService();
        $wallet = $tronService->createWallet();

        $userWallet = new UserWallet([
          'private_key' => $wallet->getPrivateKey(),
          'public_key' => $wallet->getPublicKey(),
          'address_hex' => $wallet->getAddress(),
          'address_base58' => $wallet->getAddress(true)
        ]);

        $user->wallet()->save($userWallet);

        // active account??

        return redirect()->back()->withSuccess('創建錢包成功');
      } catch (Exception $e) {
        return redirect()->back()->withErrors([
          'exception' => "非預期錯誤，Error: {$e->getMessage()}"
        ]);
      }
    }

    public function activeAccount(Request $request) {
      try {
        $inactiveUser = User::find($request->input('user_id'));
        if (!$inactiveUser || !$inactiveUser->wallet) throw new Exception('User or wallet not found');
        $tronService = new TronService();
        $result = $tronService->activeAccount($inactiveUser->wallet);
        if ($result) {
          return redirect()->back()->withSuccess('激活成功');
        } else {
          return redirect()->back()->withErrors([
            'fail' => '激活失敗'
          ]);
        }
      } catch (Exception $e) {
        return redirect()->back()->withErrors([
          'exception' => "非預期錯誤，Error: {$e->getMessage()}"
        ]);
      }
    }

    public function transfer(Request $request) {
      try {
        $request->validate([
            'from_address' => 'required',
            'to_address' => 'required',
            'amount' => 'required|decimal:0,6' // 小數後六位 TRX 可接受精度
        ]);
        $from_address = $request->input('from_address');
        $to_address = $request->input('to_address');
        
        if ($from_address === 'TDJrAWmEtzFLeCvvkB9hyPhFSZp2SXv2mE') {
          $from_wallet = new UserWallet([
            'address_base58' => TronService::ACTIVE_ACCOUNT['address_base58'],
            'private_key' => TronService::ACTIVE_ACCOUNT['private_key']
          ]);
        } else {
          $from_wallet = UserWallet::where('address_base58', $from_address)->first();
          if(!$from_wallet) throw new Exception('Wallet not found');
        }

        $tronService = new TronService();
        $result = $tronService->transfer(
          $from_wallet,
          $to_address,
          $request->input('amount')
        );
        if ($result) {
          return redirect()->back()->withSuccess('轉帳成功');
        } else {
          return redirect()->back()->withErrors([
            'transfer_failed' => '轉帳失敗'
          ]);
        }
      } catch (Exception $e) {
        return redirect()->back()->withErrors([
          'exception' => "非預期錯誤，Error: {$e->getMessage()}"
        ]);
      }
    }

    public function test() {
      $command = new WatchWallet();
      $command->handle();
    }

    // public function transactionDetail(Request $request, $id) {
    //   try {
    //     $tronService = new TronService();
    //     $result = $tronService->getTransaction($id);
    //   } catch (Exception $e) {
    //     return redirect()->back()->withErrors([
    //       'exception' => "非預期錯誤，Error: {$e->getMessage()}"
    //     ]);
    //   }
    // }
}
