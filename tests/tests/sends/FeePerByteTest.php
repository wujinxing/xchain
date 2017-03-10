<?php

use App\Blockchain\Sender\PaymentAddressSender;
use App\Models\TXO;
use App\Repositories\TXORepository;
use Tokenly\CurrencyLib\CurrencyUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

class FeePerByteTest extends TestCase {

    protected $useDatabase = true;

    public function testFeeCoinSelection_1() {
        $mock_calls = app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);

        $user = $this->app->make('\UserHelper')->createSampleUser();
        list($payment_address, $input_utxos) = $this->makeAddressAndSampleTXOs($user);

        $sender = app(PaymentAddressSender::class);
        $fee_per_byte = 50;
        $change_address_collection = null;
        $unsigned_transaction = $sender->composeUnsignedTransaction($payment_address, '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', 100, 'TOKENLY', $change_address_collection, $float_fee=null, $fee_per_byte);

        // print "\nTXOS:\n";
        // print $this->debugDumpUTXOs($unsigned_transaction->getInputUtxos())."\n";

        PHPUnit::assertEquals(7900, $unsigned_transaction->feeSatoshis());
        PHPUnit::assertEquals(50, $unsigned_transaction->getSatoshisPerByte());
        PHPUnit::assertEquals(1, count($unsigned_transaction->getInputUtxos()));
    }

    public function testFeeCoinSelection_2() {
        $mock_calls = app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);

        $user = $this->app->make('\UserHelper')->createSampleUser();
        list($payment_address, $input_utxos) = $this->makeAddressAndSampleTXOs($user);

        $sender = app(PaymentAddressSender::class);
        $fee_per_byte = 50;
        $change_address_collection = null;
        $unsigned_transaction = $sender->composeUnsignedTransaction($payment_address, '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', CurrencyUtil::satoshisToValue(1000), 'BTC', $change_address_collection, $float_fee=null, $fee_per_byte);

        // print "\nTXOS:\n";
        // print $this->debugDumpUTXOs($unsigned_transaction->getInputUtxos())."\n";

        PHPUnit::assertEquals(8000, $unsigned_transaction->feeSatoshis());
        PHPUnit::assertEquals(64, $unsigned_transaction->getSatoshisPerByte());
        PHPUnit::assertEquals(2, count($unsigned_transaction->getInputUtxos()));
    }


    // test when a little higher fee ratio would be better
    public function testFeeCoinSelection_3() {
        $mock_calls = app('CounterpartySenderMockBuilder')->installMockCounterpartySenderDependencies($this->app, $this);

        $user = $this->app->make('\UserHelper')->createSampleUser();

        $payment_address_helper = app('PaymentAddressHelper');
        $txo_helper             = app('SampleTXOHelper');

        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances($user, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);

        // make all TXOs (with roughly 0.5 BTC)
        $sample_txos = [];
        $txid = $txo_helper->nextTXID();
        $sample_txos[0] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 0]);
        $sample_txos[1] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 1]);
        $sample_txos[2] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 2]);
        $sample_txos[3] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 3]);
        $sample_txos[4] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 4]);
        $sample_txos[5] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 999,  'n' => 5]);

        $sender = app(PaymentAddressSender::class);
        $fee_per_byte = 10;
        $change_address_collection = null;
        $unsigned_transaction = $sender->composeUnsignedTransaction($payment_address, '1AAAA2222xxxxxxxxxxxxxxxxxxy4pQ3tU', CurrencyUtil::satoshisToValue(1000), 'BTC', $change_address_collection, $float_fee=null, $fee_per_byte);

        // print "\nTXOS:\n";
        // print $this->debugDumpUTXOs($unsigned_transaction->getInputUtxos())."\n";
        // echo "\$unsigned_transaction->getSatoshisPerByte(): ".json_encode($unsigned_transaction->getSatoshisPerByte(), 192)."\n";
        // echo "\$unsigned_transaction->feeSatoshis(): ".json_encode($unsigned_transaction->feeSatoshis(), 192)."\n";

        PHPUnit::assertEquals(1997, $unsigned_transaction->feeSatoshis());
        PHPUnit::assertEquals(12, $unsigned_transaction->getSatoshisPerByte());
        PHPUnit::assertEquals(3, count($unsigned_transaction->getInputUtxos()));
    }


    // ------------------------------------------------------------------------

    // total is 50019001 satoshis
    protected function makeAddressAndSampleTXOs($user=null) {
        $payment_address_helper = app('PaymentAddressHelper');
        $txo_helper             = app('SampleTXOHelper');

        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances($user, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);

        // make all TXOs (with roughly 0.5 BTC)
        $sample_txos = [];
        $txid = $txo_helper->nextTXID();
        $sample_txos[0] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 1000,  'n' => 0]);
        $sample_txos[1] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 2001,  'n' => 1]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[2] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 2000,  'n' => 0]);
        $sample_txos[3] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 3000,  'n' => 1]);
        $sample_txos[4] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 4000,  'n' => 2]);
        $sample_txos[5] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 7000,  'n' => 3]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[6] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 50000000, 'n' => 2]);

        return [$payment_address, $sample_txos];
    }

    // total is 100000000 satoshis
    protected function makeAddressAndSimpleSampleTXOs($user=null) {
        $payment_address_helper = app('PaymentAddressHelper');
        $txo_helper             = app('SampleTXOHelper');

        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances($user, ['address' => '1AAAA1111xxxxxxxxxxxxxxxxxxy43CZ9j']);

        // make all TXOs (with roughly 0.5 BTC)
        $sample_txos = [];
        $txid = $txo_helper->nextTXID();
        $sample_txos[0] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 100000000,  'n' => 0]);

        return [$payment_address, $sample_txos];
    }


    protected function debugDumpUTXOs($utxos) {
        $out = '';
        $out .= 'total utxos: '.count($utxos)."\n";
        foreach($utxos as $utxo) {
            $out .= $utxo['amount']."\n";
        }
        return rtrim($out);
    }


}
