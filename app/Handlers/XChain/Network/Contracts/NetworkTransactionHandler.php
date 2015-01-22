<?php 

namespace App\Handlers\XChain\Network\Contracts;

/**
 * Invoked when a new transaction is received
 */
interface NetworkTransactionHandler {

    public function storeParsedTransaction($parsed_tx);

    public function sendNotifications($parsed_tx, $confirmations, $block_seq, $block_confirmation_time);
    
}