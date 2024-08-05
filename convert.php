<?php 
die('DISABLED');
/** CLIENTS */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/clients.php";
// Issues
/* ClientId: 18480, 14156 */
// $clients = Clients::read(0, null);Clients::write($clients);
// Clients::set_amount_owing_per_client_per_store();

/* ITEMS & INVENTORY */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/items.php";
// $items = Items::read(0, null);Items::write($items);
// $inventory = InventoryT::read(0, null); InventoryT::write($inventory);

/* USERS */ 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/users.php";
// $users = Users::read(0, null);Users::write($users);

/* Balance Sheet && Income Statement */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/reports.php";
// $bs = Report::read(0, null, 'balance_sheet'); Report::write($bs, 'balance_sheet');
// $is = Report::read(0, null, 'income_statements'); Report::write($is, 'income_statement');

/* Sales Invoice */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/sales_invoice.php";
$begin = 10000;$end = $begin + 10;
// $sales_invoice = SalesInvoiceFmt::read($begin, $end);SalesInvoiceFmt::write($sales_invoice);
// SalesInvoiceFmt::validate(10000, null);

/* Sales Return */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/sales_return.php";
// $sales_return = SalesReturnTransfer::read(10000, null);SalesReturnTransfer::write($sales_return);
// SalesReturnTransfer::validate(10000, null);

/* Receipts */ 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/receipt.php";
// $receipts = ReceiptConvert::read(10000, null);ReceiptConvert::write($receipts);

/* Credit Note */ 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/credit_debit_note.php";
// $notes = CreditDebitNoteTransfer::read(0, null, 'credit_note');CreditDebitNoteTransfer::write($notes, 'credit_note');
// CreditDebitNoteTransfer::validate('credit_note', 10000, null);

/* Debit Note */
// $notes = CreditDebitNoteTransfer::read(0, null, 'debit_note');CreditDebitNoteTransfer::write($notes, 'debit_note');
// CreditDebitNoteTransfer::validate('debit_note', 10000, null);

/* Quotation */ 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/quotations.php";
// $quotations = QuotationsConvert::read(10000, null);QuotationsConvert::write($quotations);
// QuotationsConvert::validate(10000, null);

/* Global Setting */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/global_settings.php";
// $settings = GlobalSettingsTransfer::read();GlobalSettingsTransfer::write($settings);
?>