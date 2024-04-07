<?php

namespace App\Services;

use App\Payload\GetTransactionOptions;
use IEXBase\TronAPI\Tron as OriginalTron;
use IEXBase\TronAPI\Provider\HttpProviderInterface;
use IEXBase\TronAPI\TronManager;
use App\Services\TransactionBuilder;

class Tron extends OriginalTron
{

  public function __construct(?HttpProviderInterface $fullNode = null,
                              ?HttpProviderInterface $solidityNode = null,
                              ?HttpProviderInterface $eventServer = null,
                              ?HttpProviderInterface $signServer = null,
                              ?HttpProviderInterface $explorer = null,
                              ?string $privateKey = null)
  {
    if(!is_null($privateKey)) {
      $this->setPrivateKey($privateKey);
    }

    $this->setManager(new TronManager($this, [
      'fullNode'      =>  $fullNode,
      'solidityNode'  =>  $solidityNode,
      'eventServer'   =>  $eventServer,
      'signServer'    =>  $signServer,
    ]));

    $this->transactionBuilder = new TransactionBuilder($this);
  }

  /**
   * Override 因為第二個參數$amount轉float導致精度問題
   * Send transaction to Blockchain
   *
   * @param string $to
   * @param float $amount
   * @param string|null $message
   * @param string|null $from
   *
   * @return array
   * @throws TronException
   */
  public function sendTransaction(string $to, $amount, string $from = null, string $message = null): array
  {
    if (is_null($from)) {
        $from = $this->address['hex'];
    }

    $transaction = $this->transactionBuilder->sendTrx($to, $amount, $from, $message);
    $signedTransaction = $this->signTransaction($transaction);


    $response = $this->sendRawTransaction($signedTransaction);
    return array_merge($response, $signedTransaction);
  }

  public function getAccountTransactions($address, $options = []) {
    $queryString = count($options) > 0
      ? '?' . http_build_query($options)
      : '';
    return $this->manager->request("v1/accounts/{$address}/transactions" . $queryString, [], 'get');
  }

  public function getAccountFromGrid(?string $address = null)
  {
    return $this->manager->request("wallet/getaccount", [
      'address' => $address,
      'visible' => true
    ], 'post');
  }

  public function getBalanceFromFullNode(string $address, $fromTron = false)
  {
    $account = $this->manager->request('wallet/getaccount', [
      'address' => $address,
      'visible' => true
    ]);

    if(!array_key_exists('balance', $account)) {
      return 0;
    }

    return $fromTron == true
      ? $this->fromTron($account['balance'])
      : $account['balance'];
  }
}
