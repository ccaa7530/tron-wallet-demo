<?php
namespace App\Services;

use IEXBase\TronAPI\Exception\TronException;
use IEXBase\TronAPI\TransactionBuilder as OriginalTransactionBuilder;

class TransactionBuilder extends OriginalTransactionBuilder
{
    /**
     * Create an TransactionBuilder object
     *
     * @param Tron $tron
     */
    public function __construct(Tron $tron)
    {
        parent::__construct($tron);
    }

    /**
     * Override 因為第二個參數$amount轉float導致精度問題
     * Creates a transaction of transfer.
     * If the recipient address does not exist, a corresponding account will be created on the blockchain.
     *
     * @param string $to
     * @param float $amount
     * @param string|null $from
     * @param string|null $message
     * @return array
     * @throws TronException
     */
    public function sendTrx(string $to, $amount, string $from = null, string $message = null)
    {
        if ($amount < 0) {
            throw new TronException('Invalid amount provided');
        }

        if(is_null($from)) {
            $from = $this->tron->address['hex'];
        }

        $to = $this->tron->address2HexString($to);
        $from = $this->tron->address2HexString($from);

        if ($from === $to) {
            throw new TronException('Cannot transfer TRX to the same account');
        }
        $options = [
            'to_address' => $to,
            'owner_address' => $from,
            'amount' => $this->tron->toTron($amount),
        ];

        if(!is_null($message)) {
            $params['extra_data'] = $this->tron->stringUtf8toHex($message);
        }

        return $this->tron->getManager()->request('wallet/createtransaction', $options);
    }
}
