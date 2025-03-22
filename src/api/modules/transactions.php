<?php 
/**
 * This module will implements method to manage inventory.
 * @author Dewang Saxena, <dewang2610@gmail.com>
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_invoice.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_return.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/quotation.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/credit_note.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/debit_note.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/receipt.php";

/**
 * This method will handle transactions.
 * @param data
 * @return array
 */
function handle_transactions(array $data) {
    $response = [];
    $transaction_type = $data['transactionType'] ?? null;
    switch($transaction_type) {
        case SALES_INVOICE: $response = SalesInvoice::process($data); break;
        case QUOTATION: $response = Quotations::process($data); break;
        case CREDIT_NOTE: $response = CreditNote::process($data); break;
        case DEBIT_NOTE: $response = DebitNote::process($data); break;
        case SALES_RETURN: $response = SalesReturn::process($data); break;
        case RECEIPT: $response = Receipt::process($data); break;
        case PURCHASE_INVOICE: $response = PurchaseInvoice::process($data); break;
        default: $response = ['status' => false, 'message' => 'Invalid Transaction Type.'];
    }
    return $response;
}
?> 