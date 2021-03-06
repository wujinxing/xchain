<?php

use App\Blockchain\Sender\TXOChooser;
use App\Models\TXO;
use App\Providers\Accounts\Facade\AccountHandler;
use App\Providers\TXO\Facade\TXOHandler;
use Tokenly\CurrencyLib\CurrencyUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

class TXOChooserTest extends TestCase {

    const SATOSHI = 100000000;

    protected $useDatabase = true;

    public function testChooseTXOs_1()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        // samples [1000, 2000, 2000, 3000, 4000, 50000]
        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs();

        // exact
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(900), $float(100), 0);
        $this->assertFound([0], $sample_txos, $chosen_txos);
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(1900), $float(100), 0);
        $this->assertFound([2], $sample_txos, $chosen_txos);
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(2900), $float(100), 0);
        $this->assertFound([3], $sample_txos, $chosen_txos);

        // low
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(4900), $float(100), 0);
        $this->assertFound([4,0], $sample_txos, $chosen_txos);
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(7900), $float(100), 0);
        $this->assertFound([5,0], $sample_txos, $chosen_txos);
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(7899), $float(100), 0);
        $this->assertFound([5,0], $sample_txos, $chosen_txos);
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(11900), $float(100), 0);
        $this->assertFound([5,4,0], $sample_txos, $chosen_txos);

        // high
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(19000), $float(100), 0);
        $this->assertFound([6], $sample_txos, $chosen_txos);

        // choose high or low
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(18000), $float(100), 0);
        $this->assertFound([6], $sample_txos, $chosen_txos);

        // very high
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(63000), $float(0), 0);
        $this->assertFound([6,5,4,2], $sample_txos, $chosen_txos);

    }
/*
    6: 50000
    5:  7000
    4:  4000
    3:  3000
    2:  2000
    1:  2010
    0:  1000
*/


    public function testPrioritizeGreenAndUnconfirmedTXOs()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        // samples [1000, 2000, 3000, 50000]
        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_2();

        // 1 confirmed TXO
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(900), $float(100), 0);
        $this->assertFound([0], $sample_txos, $chosen_txos);

        // Prioritize confirmed over unconfirmed
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(5900), $float(100), 0);
        $this->assertFound([3], $sample_txos, $chosen_txos);

        // Prioritize green over red
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(52900), $float(100), 0);
        $this->assertFound([3,1,0], $sample_txos, $chosen_txos);

    }
/*
3: 50000   CONFIRMED, red
2:  3000 UNCONFIRMED, red
1:  2000 UNCONFIRMED, green 
0:  1000   CONFIRMED, green
*/


    public function testChoosePrimeTXOs()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs();

        // priming size without exclusion
        $chosen_txos = $txo_chooser->chooseUTXOsForPriming($payment_address, $float(6000), $float(100), $float(5430), $float(1001));
        $this->assertFound([5,4,0], $sample_txos, $chosen_txos);

        // priming size with exclusion (does not choose the 1000 satoshi UTXO)
        $chosen_txos = $txo_chooser->chooseUTXOsForPriming($payment_address, $float(6000), $float(100), $float(5430), $float(1000));
        $this->assertFound([5,3,2], $sample_txos, $chosen_txos);
    }
    /*
        6: 50000
        5:  7000
        4:  4000
        3:  3000
        2:  2000
        1:  2010
        0:  1000
    */



    public function testChooseTXOsWithMinChange()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs();

        // 1 confirmed TXO
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(4899), $float(100), $float(5430));
        $this->assertFound([5,4], $sample_txos, $chosen_txos);

    }
/*
    6: 50000
    5:  7000
    4:  4000
    3:  3000
    2:  2000
    1:  2010
    0:  1000
*/

    public function testChooseTXOsWithNoChange()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs();

        // No change needed
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(10900), $float(100), $float(5430));
        $this->assertFound([5,4], $sample_txos, $chosen_txos);

        // sweep all UTXOs
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(10900), $float(100), $float(5430));
        $this->assertFound([5,4], $sample_txos, $chosen_txos);

    }
/*
    6: 50000
    5:  7000
    4:  4000
    3:  3000
    2:  2000
    1:  2010
    0:  1000
*/

    public function testChooseTXOsThree()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_3();

        // choose from a bunch
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, $float(5430), $float(10000), $float(5430));
        $chosen_txos = $this->sortById($chosen_txos);
        // $this->assertFound([8,0,5,4], $sample_txos, $chosen_txos);
        // fall back to all
        $expected_found_offsets = [];
        $max = 4; for ($i=0; $i < $max; $i++) { $expected_found_offsets[] = $i; }
        $this->assertFound($expected_found_offsets, $sample_txos, $chosen_txos);

    }

    public function testChooseTXOsFour()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_4();

        // choose exact change from a bunch
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, 0.0005, 0.0001, $float(5430));
        // $this->assertFound([2,1,3,4], $sample_txos, $chosen_txos);
        PHPUnit::assertNotEmpty($chosen_txos);

    }

    public function testChooseTXOsFive()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_5();

        // choose TXOs
        // public function chooseUTXOs(PaymentAddress $payment_address, $float_quantity, $float_fee, $float_minimum_change_size=null, $strategy=null, $priming_size=null) {
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, 0.16, 0.0001, 0);
        PHPUnit::assertNotEmpty($chosen_txos);

    }

    public function testChooseLargeNumberOfTXOsOne()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_6();

        // choose TXOs
        // public function chooseUTXOs(PaymentAddress $payment_address, $float_quantity, $float_fee, $float_minimum_change_size=null, $strategy=null, $priming_size=null) {
        //   needs a minimum of 0.00051 + 0.0001 + 0.0000543 = 0.0006643
        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, 0.00051, 0.0001);
        PHPUnit::assertNotEmpty($chosen_txos);
        // 13 UTXOs at 5430 satoshis each is correct (0.00070590).
        //   12 UTXOS would not meet the minimum change size requirement (0.00065160)
        PHPUnit::assertCount(13, $chosen_txos);
    }

    public function testChooseLargeNumberOfTXOsTwo()
    {
        // receiving a transaction adds TXOs
        $txo_repository = app('App\Repositories\TXORepository');
        $txo_chooser    = app('App\Blockchain\Sender\TXOChooser');

        $float = function($i) { return CurrencyUtil::satoshisToValue($i); };

        list($payment_address, $sample_txos) = $this->makeAddressAndSampleTXOs_7();

        $chosen_txos = $txo_chooser->chooseUTXOs($payment_address, 0.008, 0.0001);
        PHPUnit::assertNotEmpty($chosen_txos);
        PHPUnit::assertCount(9, $chosen_txos);
    }

    // ------------------------------------------------------------------------
    
    protected function TXOHelper() {
        if (!isset($this->txo_helper)) { $this->txo_helper = app('SampleTXOHelper'); }
        return $this->txo_helper;
    }

    protected function makeAddressAndSampleTXOs() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        $txid = $txo_helper->nextTXID();
        $sample_txos[0] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 1000,  'n' => 0]);
        $sample_txos[1] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 2010,  'n' => 1]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[2] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 2000,  'n' => 0]);
        $sample_txos[3] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 3000,  'n' => 1]);
        $sample_txos[4] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 4000,  'n' => 2]);
        $sample_txos[5] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 7000,  'n' => 3]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[6] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 50000, 'n' => 2]);

        return [$payment_address, $sample_txos];
    }

    protected function assertFound($expected_offsets, $sample_txos, $chosen_txos) {
        $expected_txo_arrays = [];
        $expected_amounts = [];
        $expected_ids = [];
        foreach($expected_offsets as $expected_offset) {
            $expected_txo_arrays[] = $sample_txos[$expected_offset]->toArray();
            $expected_amounts[] = $sample_txos[$expected_offset]['amount'];
            $expected_ids[] = $sample_txos[$expected_offset]['id'];
        }

        $actual_amounts = [];
        $actual_ids = [];
        $chosen_txo_arrays = [];
        foreach($chosen_txos as $chosen_txo) {
            $chosen_txo_arrays[] = ($chosen_txo ? $chosen_txo->toArray() : null);
            $actual_amounts[] = $chosen_txo['amount'];
            $actual_ids[] = $chosen_txo['id'];
        }

        $explanation = "Did not find the expected offsets of ".json_encode($expected_offsets).'. Expected amounts were '.json_encode($expected_amounts).'. Actual amounts were '.json_encode($actual_amounts)."  Expected ids were ".json_encode($expected_ids).". Actual ids were ".json_encode($actual_ids);
        PHPUnit::assertEquals($expected_txo_arrays, $chosen_txo_arrays, $explanation);
    }



    protected function makeAddressAndSampleTXOs_2() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        $txid = $txo_helper->nextTXID();
        $sample_txos[0] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 1000,  'n' => 0, 'type' => TXO::CONFIRMED,   'green' => 1]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[1] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 2000,  'n' => 0, 'type' => TXO::UNCONFIRMED, 'green' => 1]);
        $sample_txos[2] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 3000,  'n' => 1, 'type' => TXO::UNCONFIRMED, 'green' => 0]);
        $txid = $txo_helper->nextTXID();
        $sample_txos[3] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txid, 'amount' => 50000, 'n' => 2, 'type' => TXO::CONFIRMED,   'green' => 0]);

        return [$payment_address, $sample_txos];
    }


    protected function makeAddressAndSampleTXOs_3() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        for ($i=0; $i < 6; $i++) { 
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5430,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }
        for ($i=0; $i < 28; $i++) { 
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5470,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }

        return [$payment_address, $sample_txos];
    }

    protected function makeAddressAndSampleTXOs_4() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        for ($i=0; $i < 10; $i++) { 
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5430,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }
        for ($i=0; $i < 10; $i++) { 
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5470,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }

        return [$payment_address, $sample_txos];
    }

    protected function makeAddressAndSampleTXOs_5() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);

        $values = [
            [0.00005470, false],
            [0.00005470, false],
            [0.00005470, false],
            [0.00005470, false],
            [0.00005470, false],
            [0.00006370, true ],
            [0.00006410, true ],
            [0.00005430, false],
            [0.00005470, false],
            [0.00005470, false],
            [0.00005920, true ],
            [0.08000000, false],
            [0.00005470, false],
            [0.07943710, true ],
        ];

        $sum = 0;
        $sample_txos = [];
        foreach($values as $value_pair) {
            list($value, $is_green) = $value_pair;
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => $value * self::SATOSHI,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => ($is_green ? 1 : 0)]);
            $sum += CurrencyUtil::valueToSatoshis($value);
        }
        // echo "\$sum: ".json_encode($sum, 192)."\n";

        return [$payment_address, $sample_txos];
    }

    protected function makeAddressAndSampleTXOs_6() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        for ($i=0; $i < 150; $i++) {
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5430,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }
        for ($i=0; $i < 50; $i++) {
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 10000,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }

        return [$payment_address, $sample_txos];
    }

    protected function makeAddressAndSampleTXOs_7() {
        $payment_address_helper = app('PaymentAddressHelper');

        $txo_helper = $this->TXOHelper();
        $payment_address = $payment_address_helper->createSamplePaymentAddressWithoutInitialBalances(null, ['address' => '1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD']);
        $sample_txos = [];

        for ($i=0; $i < 150; $i++) {
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 5430,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }
        for ($i=0; $i < 50; $i++) {
            $sample_txos[] = $txo_helper->createSampleTXO($payment_address, ['txid' => $txo_helper->nextTXID(), 'amount' => 100000,  'n' => 0, 'type' => TXO::CONFIRMED, 'green' => 0]);
        }

        return [$payment_address, $sample_txos];
    }

    protected function sortById($txos) {
        uasort($txos, function($txo_a, $txo_b) {
            return $txo_a['id'] > $txo_b['id'];
        });
        return $txos;
    }

}
