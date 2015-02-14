<?php

namespace App\Handlers\XChain\Network\Bitcoin;

use App\Handlers\XChain\Network\Bitcoin\BitcoinTransactionEventBuilder;
use App\Handlers\XChain\Network\Contracts\NetworkTransactionStore;
use Tokenly\LaravelEventLog\Facade\EventLog;
use App\Repositories\TransactionRepository;
use Illuminate\Contracts\Logging\Log;
use Tokenly\Insight\Client;

class BitcoinTransactionStore implements NetworkTransactionStore {

    public function __construct(BitcoinTransactionEventBuilder $transaction_builder, TransactionRepository $transaction_repository, Client $insight_client, Log $log) {
        $this->log                    = $log;
        $this->insight_client         = $insight_client;
        $this->transaction_repository = $transaction_repository;
        $this->transaction_builder    = $transaction_builder;
    }

    public function getCachedTransaction($txid)
    {
        return $this->fetchTransactionFromRepository($txid);
    }

    public function getTransaction($txid)
    {
        $transaction = $this->fetchTransactionFromRepository($txid);
        if (!$transaction) {
            $transaction = $this->getParsedTransactionFromInsight($txid);
        }

        return $transaction;
    }

    public function getParsedTransactionFromInsight($txid, $block_seq=null) {
        // EventLog::increment('insight.loadtx');

        // load
        $insight_data = $this->fetchTransactionFromInsight($txid);

        // convert to a transaction model
        $parsed_transaction_data = $this->buildParsedTransactionDataFromInsightData($insight_data);

        // and cache
        $transaction = $this->saveNewTransactionFromParsedTransactionData($parsed_transaction_data, $block_seq);

        return $transaction;
    }

    public function storeParsedTransaction($parsed_tx, $block_seq=null) {
        $txid = $parsed_tx['txid'];
        $transaction = $this->fetchTransactionFromRepository($txid);
        if ($transaction) {
            // update
            $transaction = $this->transaction_repository->update($transaction, [
                'block_confirmed_hash' => isset($parsed_tx['bitcoinTx']['blockhash']) ? $parsed_tx['bitcoinTx']['blockhash'] : null,
                'is_mempool'           => isset($parsed_tx['bitcoinTx']['blockhash']) ? 0 : 1,
            ]);
        } else {
            $transaction = $this->saveNewTransactionFromParsedTransactionData($parsed_tx, $block_seq);
        }

        return $transaction;
    }


    protected function buildParsedTransactionDataFromInsightData($insight_data) {
        $xstalker_data = [
            'ts' => time() * 1000,
            'tx' => $insight_data,
        ];
        return $this->transaction_builder->buildParsedTransactionData($xstalker_data);
    }

    protected function fetchTransactionFromInsight($txid) {
        return $this->insight_client->getTransaction($txid);
    }

    protected function fetchTransactionFromRepository($txid) {
        return $this->transaction_repository->findByTXID($txid);
    }

    protected function saveNewTransactionFromParsedTransactionData($parsed_tx, $block_seq=null) {

        $block_confirmed_hash = isset($parsed_tx['bitcoinTx']['blockhash']) ? $parsed_tx['bitcoinTx']['blockhash'] : null;
        $is_mempool           = isset($parsed_tx['bitcoinTx']['blockhash']) ? 0 : 1;

        $transaction = $this->transaction_repository->create($parsed_tx, $block_confirmed_hash, $is_mempool, $block_seq);
        return $transaction;
    }


    protected function wlog($text) {
        $this->log->info($text);
    }

}
