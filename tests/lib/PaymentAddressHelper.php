<?php

use App\Models\LedgerEntry;
use App\Models\User;
use App\Providers\Accounts\Facade\AccountHandler;
use App\Repositories\LedgerEntryRepository;
use App\Repositories\PaymentAddressRepository;
use Tokenly\CurrencyLib\CurrencyUtil;

/**
*  PaymentAddressHelper
*/
class PaymentAddressHelper
{

    function __construct(PaymentAddressRepository $payment_address_repository, LedgerEntryRepository $ledger_entry_repository, SampleTXOHelper $txo_helper) {
        $this->payment_address_repository = $payment_address_repository;
        $this->ledger_entry_repository    = $ledger_entry_repository;
        $this->txo_helper                 = $txo_helper;
    }


    public function createSamplePaymentAddressWithoutDefaultAccount($user=null, $override_vars=[]) {
        if ($user === null) { $user = app('UserHelper')->getSampleUser(); }
        $new_address = $this->payment_address_repository->createWithUser($user, $this->sampleVars($override_vars));
        return $new_address;
    }

    public function createSamplePaymentAddressWithoutInitialBalances($user=null, $override_vars=[]) {
        return $this->createSamplePaymentAddress($user, $override_vars, false);
    }

    public function createSamplePaymentAddress($user=null, $override_vars=[], $initial_balances=null) {
        $new_address = $this->createSamplePaymentAddressWithoutDefaultAccount($user, $override_vars);

        // also create a default account for this address
        AccountHandler::createDefaultAccount($new_address);

        // add initial balances
        if ($initial_balances === null) { $initial_balances = ['TOKENLY' => 100, 'BTC' => 1]; }
        if ($initial_balances) {
            $this->addBalancesToPaymentAddressAccount($initial_balances, $new_address);
        }

        return $new_address;
    }

    public function addBalancesToPaymentAddressAccountWithoutUTXOs($balances, $payment_address, $account_name='default', $txid='SAMPLE01') {
        return $this->addBalancesToPaymentAddressAccount($balances, $payment_address, false, $account_name, $txid);
    }

    public function addBalancesToPaymentAddressAccount($balances, $payment_address, $with_utxos=true, $account_name='default', $txid='SAMPLE01') {
        if (!$balances) { return; }

        $account = AccountHandler::getAccount($payment_address, $account_name);
        foreach($balances as $asset => $quantity) {
            $this->ledger_entry_repository->addCredit($quantity, $asset, $account, LedgerEntry::CONFIRMED, LedgerEntry::DIRECTION_OTHER, $txid);
        }

        if ($with_utxos) {
            $float_btc_balance = isset($balances['BTC']) ? $balances['BTC'] : 0;
            return $this->addUTXOToPaymentAddress($float_btc_balance, $payment_address, $account_name, $txid);
        }
        return [];
    }

    public function addUTXOToPaymentAddress($float_btc_balance, $payment_address, $account_name='default', $txid='SAMPLE01') {
        // add UTXOs for BTC balance
        $sample_txos = [];
        if ($float_btc_balance) {
            $txid = $this->txo_helper->nextTXID();
            $sample_txos[] = $this->txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => CurrencyUtil::valueToSatoshis($float_btc_balance),  'n' => 0]);
        }

        return $sample_txos;
    }

    public function sampleVars($override_vars=[]) {
        return array_merge([
            'address'           => '17YdDTY9pjcrAKSZ2AnGS5reXSLhKhxfbh',
            'private_key_token' => 'ASAMPLEKEYTOKEN',
            'user_id'           => 1,
        ], $override_vars);
    }


}