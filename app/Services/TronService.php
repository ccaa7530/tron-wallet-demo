<?php
namespace App\Services;

use App\Models\Transaction;
use App\Support\TronTransaction;
use App\Services\Tron;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Exception\TronException;
use App\Models\UserWallet;
use Exception;
use Illuminate\Support\Arr;

class TronService
{
    // 已激活帳戶，單純用來激活新帳號
    const ACTIVE_ACCOUNT = [
      'address_base58' => 'TDJrAWmEtzFLeCvvkB9hyPhFSZp2SXv2mE',
      'address_hex' => '41249f4d80fc15de9847624f0ee211aa6a85cc654d',
      'private_key' => '840378214fa96229838ee0d1a9773d8c55b3a485622e62e47452129d20b738f9',
      'public_key' => '041378dfbb21aa687181f5d6479bb9564666ed78277c8d229205e6de4194215608a1b3804928c1b3591f5dc092ba8c1d349efdffae02aaa8a87b2fa8c73140ea3b'
    ];
    private $url = 'https://api.shasta.trongrid.io';
    private $api = null;

    public function __construct() {
      // 创建一个Tron实例
      $fullNode = new HttpProvider($this->url); // Tron 全节点 URL
      $solidityNode = new HttpProvider($this->url); // Tron Solidity 节点 URL
      $eventServer = new HttpProvider($this->url); // Tron 事件服务器 URL
      $signServer = new HttpProvider($this->url); // Tron 事件服务器 URL
      $this->api = new Tron($fullNode, $solidityNode, $eventServer, $signServer, null);
    }

    public function getAdminAccountBalance()
    {
      try {
        return $this->api->getBalanceFromFullNode(static::ACTIVE_ACCOUNT['address_base58'], true);
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function createWallet()
    {
      try {
        // 生成一个新的账户
        $attempts = 0;
        $validAddress = false;
        do {
  
          if ($attempts++ === 5) {
            throw new TronException('Could not generate valid key');
          }
  
          $account = $this->api->generateAddress();
  
          // We cant use hex2bin unless the string length is even.
          if (strlen($account->getPublicKey()) % 2 !== 0) {
            continue;
          }
  
          // check private key exists
          $hasExistsPrivateKey = UserWallet::where('private_key', $account->getPrivateKey())->first();
          if ($hasExistsPrivateKey) {
            continue;
          }
  
          $result = $this->api->validateAddress($account->getAddress());
          $validAddress = $result['result'] === true;
  
        } while (!$validAddress);
  
        return $account;
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function getTransactions(UserWallet $wallet, $options = []) {
      try {
        // fetch transaction list
        $result = $this->api->getAccountTransactions($wallet->address_base58, $options);

        if ($result['success'] === true) {

          $mapped = Arr::map($result['data'], function ($item, $index) {
            return new TronTransaction($item);
          });

          return [
            'data' => $mapped,
            'meta' => $result['meta']
          ];
        } else {
          throw new Exception('Get Account Transaction failed.');
        }
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function getTransactionsV2(UserWallet $wallet, $options = []) {
      try {
        // fetch transaction list
        $result = $this->api->getAccountTransactions($wallet->address_base58, $options);

        return $result;
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function transfer(UserWallet $fromWallet, $to, $amount) {
      try {
        // 创建一个Tron实例
        $fullNode = new HttpProvider($this->url); // Tron 全节点 URL
        $solidityNode = new HttpProvider($this->url); // Tron Solidity 节点 URL
        $eventServer = new HttpProvider($this->url); // Tron 事件服务器 URL
        $signServer = new HttpProvider($this->url); // Tron 事件服务器 URL
        $tronApi = new Tron($fullNode, $solidityNode, $eventServer, $signServer, null, $fromWallet->private_key);
        $result = $tronApi->sendTransaction($to, $amount, $fromWallet->address_base58, 'DevTest');
        $params = Arr::get($result, 'raw_data.contract.0.parameter.value', []);
        $type = Arr::get($result, 'raw_data.contract.0.type', 'Undefined');
        // save transaction
        Transaction::create([
          'tx_id' => Arr::get($result, 'txID', null),
          'from_address' => Arr::get($params, 'owner_address', null),
          'to_address' => Arr::get($params, 'to_address', null),
          'amount' => Arr::get($params, 'amount', null),
          'type' => $type,
          'status' => 'unconfirmed',
          'tx_time' => Arr::get($result, 'raw_data.timestamp', 0),
          'block_hash' => Arr::get($result, 'raw_data.ref_block_hash', ''),
          'raw_data' => json_encode($result),
        ]);
        return $result;
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function getAccountByAddress(UserWallet $wallet) {
      try {
        $result = $this->api->getAccount($wallet->address_base58);
        if (!isset($result['address'])) return null;

        $floatBalance = $this->api->fromTron($result['balance']);
        $result['float_balance'] = $floatBalance;
        return $result;
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function activeAccount(UserWallet $wallet) {
      try {
        // 创建一个Tron实例
        $fullNode = new HttpProvider($this->url); // Tron 全节点 URL
        $solidityNode = new HttpProvider($this->url); // Tron Solidity 节点 URL
        $eventServer = new HttpProvider($this->url); // Tron 事件服务器 URL
        $signServer = new HttpProvider($this->url); // Tron 事件服务器 URL
        $tronApi = new Tron($fullNode, $solidityNode, $eventServer, $signServer, null, static::ACTIVE_ACCOUNT['private_key']);

        $transaction = $tronApi->registerAccount(static::ACTIVE_ACCOUNT['address_base58'], $wallet->address_base58);
        $signedTransaction = $tronApi->signTransaction($transaction);
        $response = $tronApi->sendRawTransaction($signedTransaction);

        if ($response['result'] === true) {
          $wallet->update([
            'activate_tx_id' => $response['txid']
          ]);
          return true;
        } else {
          return false;
        }
      } catch (Exception $e) {
        throw $e;
      }
    }

    public function isTxConfirmed(string $tx_id) {
      $result = $this->api->getTransactionInfo($tx_id); // walletsolidity 如果資料存在就表示已經confirmed
      return isset($result['id']);
    }

    public function getLatestBlock() {
      return $this->api->getCurrentBlock();
    }

    public function getBlockByNumber($id) {
      return $this->api->getBlockByNumber($id);
    }

    public function getAddressFromHex($hex_string) {
      return $this->api->hexString2Address($hex_string);
    }

    public function getAddressFromGrid(string $address) {
      return $this->api->getAccountFromGrid($address);
    }
}

