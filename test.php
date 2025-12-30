<?php 

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_statement.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory_v2.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/suppressions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/flyer.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/user_management.php";

function generate_list(int $store_id, bool $do_print=true) {
    $db = get_db_instance();
    $query = <<<'EOS'
    SELECT 
        i.`identifier`,
        i.`prices`,
        inv.`quantity`
    FROM 
        items AS i
    LEFT JOIN 
        inventory AS inv
    ON 
        i.id = inv.item_id
    WHERE 
        inv.`store_id` = :store_id;
    EOS;

    $statement = $db -> prepare($query);
    $statement -> execute([':store_id' => $store_id]);
    $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $store_name = StoreDetails::STORE_DETAILS[$store_id]['name'];
    $current_date = date('d M, Y');
    $code = <<<EOS
    <html>
    <head></head>
    <body>
    <style>
    table {
        border-spacing: 20px 0;
    }
    </style>
    <h1>List for $store_name as on $current_date </h1>
    <table cellspacing="pixels">
    <thead>
    <th align="left">Item Identifier</th>
    <th align="center">Quantity</th>
    <th > Price / Item</th>
    <th align="right">Inventory Value</th>
    </thead>
    EOS;
    $total_value = 0;
    foreach($result as $r) {
        $identifier = $r['identifier'];
        $quantity = $r['quantity'];
        $prices = json_decode($r['prices'], true, flags: JSON_NUMERIC_CHECK| JSON_THROW_ON_ERROR);
        $price = $prices[$store_id]['buyingCost'] ?? 0;
        if($quantity < 1 || (($price > 0) === false)) continue;
        $value = $price * $quantity;
        $total_value += $value;
        $value_txt = Utils::number_format($value, 2);

        $price_txn = Utils::number_format($price, 2);
        $code .= <<<EOS
        <tr>
        <td align="left">$identifier</td>
        <td align="center">$quantity</td>
        <td ><label style="letter-spacing: 2px;">\$ $price_txn</label></td>
        <td align="right"><label style="letter-spacing: 2px;">\$ $value_txt</label></td>
        </tr>
        EOS;
    }

    
    if($do_print) {
        $total_value = Utils::number_format($total_value, 2);
        $code .= "</table><br><br>Total Inventory Value: &nbsp;&nbsp;&nbsp;&nbsp;<label style='letter-spacing: 2px;font-weight:bold;'>\$ $total_value</label>";
        echo $code;
    }
    else return $total_value;
}

function fix_inventory_value(int $store_id): void {
    $db =  get_db_instance();
    try {
        $db -> beginTransaction();

        $statement = $db -> prepare('SELECT id, `statement` FROM balance_sheet WHERE store_id = :store_id ORDER BY id DESC LIMIT 1;');
        $statement -> execute([':store_id' => $store_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $balance_sheet = json_decode($result[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $balance_sheet[AccountsConfig::INVENTORY_A] = generate_list($store_id, false);
        $balance_sheet = json_encode($balance_sheet, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        $statement = $db -> prepare(<<<'EOS'
        UPDATE 
            balance_sheet
        SET 
            `statement` = :statement
        WHERE 
            id = :id;
        EOS);
        $is_successful = $statement -> execute([':id' => $result[0]['id'], ':statement' => $balance_sheet]);
        
        if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Update Balance Sheet.');
        $db -> commit();
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}
$store_id = StoreDetails::SASKATOON;
// fix_inventory_value($store_id);
// generate_list($store_id, true);
// die;

// $code = 'AF';
// $details = Inventory::fetch_item_quantity_sold_by_prefix($code, StoreDetails::CALGARY, '2025-01-01', '2025-12-31');
// Inventory::generate_quantity_report_of_item_sold($code, $details);

// ********** FROM HERE **************

function f_record(): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        
        $statement = $db -> prepare('SELECT * FROM income_statement WHERE store_id = 2 AND `date` = "2025-11-12";');
        $statement -> execute();
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $income_statement = json_decode($records[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Sales Revenue
        $income_statement[4020] -= 45.62;

        // Inventory
        $income_statement[1520] += 36.18;
 
        // Update Records
        $statement = $db -> prepare('UPDATE income_statement SET `statement` = :_statement WHERE store_id = 2 AND `date` = "2025-11-12";');
        $is_successful = $statement -> execute([':_statement' => json_encode($income_statement, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR)]);
        if($is_successful && $statement -> rowCount() < 1) throw new Exception('Unable to update Income Statement');

        // Update Balance Sheet
        $bs = AccountsConfig::ACCOUNTS;

        BalanceSheetActions::update_account_value($bs, AccountsConfig::ACCOUNTS_RECEIVABLE, -47.90);
        BalanceSheetActions::update_account_value($bs, AccountsConfig::INVENTORY_A, 36.18);
        BalanceSheetActions::update_from($bs, '2025-11-12', 2, $db);

        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

function __find_receipt_for_transaction(int $store_id, int $client_id, int $transaction_id, float $credit_amount, PDO &$db, &$set_transaction_to_zero, $transaction_type) {
    $statement = $db -> prepare('SELECT id, `details` FROM receipt WHERE store_id = :store_id AND client_id = :client_id AND `details` LIKE :txn_id;');
    $txn_tag = $transaction_type === SALES_INVOICE ? 'IN': 'SR';
    $statement -> execute([
        ':store_id' => $store_id,
        ':client_id' => $client_id,
        ':txn_id' => "%\"txnId\":\"$txn_tag-$transaction_id\",%",
    ]);

    $receipts = $statement -> fetchAll(PDO::FETCH_ASSOC);
    if(count($receipts)) {
        $amount_owing = null;
        $amount_received = 0;
        $discount_given = 0;
        foreach($receipts as $r) {
            $txn_details = json_decode($r['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($txn_details as $txn) {
                if($txn['type'] === $transaction_type && $txn['id'] === $transaction_id) {

                    // Assign this one.
                    if(is_null($amount_owing)) $amount_owing = $txn['amountOwing'];
                    $amount_received += $txn['amountReceived'];
                    $discount_given += $txn['discountGiven'];
                }
            }
        }

        // Total Amount Received
        $total_amount_received = $amount_received + $discount_given;

        // Amount to be adjusted
        $amount_to_be_adjusted = Utils::round($total_amount_received - $amount_owing, 4);
        
        if(strval($amount_owing) == strval($total_amount_received)) {
            $is_successful = $set_transaction_to_zero -> execute([':id' => $transaction_id, ':credit_amount' => $credit_amount]);
            if($is_successful !== true || $set_transaction_to_zero -> rowCount() < 1) {
                throw new Exception('Unable to Update Transaction: '. $transaction_id);
            }
        }
        else echo "$transaction_id $amount_owing $total_amount_received $amount_to_be_adjusted<br>";
    }
}

function __fetch_transactions_by_receipt(int $store_id, string $date, int $transaction_type, PDO &$db): array {
    $statement = $db -> prepare('SELECT id, `details` FROM receipt WHERE store_id = :store_id AND `date` >= :_date;');
    $statement -> execute([':store_id' => $store_id, ':_date' => $date ]);
    $receipts = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $transactions_ids = [];
    foreach($receipts as $receipt) {
        $details = json_decode($receipt['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        foreach($details as $d) {
            if($d['type'] != $transaction_type) continue;
            $transactions_ids []= $d['id'];
        }
    }
    return $transactions_ids;
}

function fix_transactions_credit_amount(bool $is_test, int $store_id, string $date): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        $transaction_type = SALES_INVOICE;

        // Select Table name
        $table_name = ($transaction_type === SALES_INVOICE ? 'sales_invoice': 'sales_return');

        // Fetch Transaction IDS
        $transaction_ids = __fetch_transactions_by_receipt($store_id, $date, $transaction_type, $db);

        // Fetch IDS
        $query = 'SELECT id, client_id, credit_amount FROM '. $table_name. ' WHERE id IN (:placeholder);';
        $result = Utils::mysql_in_placeholder_pdo_substitute($transaction_ids, $query);
        
        // Fetch Transactions
        $statement = $db -> prepare($result['query']);
        $statement -> execute($result['values']);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Prepare Statement 
        $set_transaction_to_zero = $db -> prepare('UPDATE '. $table_name.' SET credit_amount = credit_amount - :credit_amount WHERE id = :id;');

        foreach($results as $result) {
            $transaction_id = $result['id'];
            $client_id = $result['client_id'];
            $credit_amount = $result['credit_amount'];
            $credit_amount_str = strval($credit_amount);

            if(Utils::round($credit_amount, 2) == 0.0 && $credit_amount_str != '0.0000') {

                // Print Transactions
                if($is_test) echo "$transaction_id,";
                else {
                    // Fetch Receipt for this transactions
                    __find_receipt_for_transaction($store_id, $client_id, $transaction_id, $credit_amount, $db, $set_transaction_to_zero, $transaction_type);
                }
            }
        }

        // Generate Exception on test
        if($is_test) throw new Exception('<br><br>TEST EXCEPTION');

        assert_success();
        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}

function fix_balance_sheet_amount_receivables(int $store_id) {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        
        // Fetch Accounts Receivables for Store.
        $customer_aged_summary = CustomerAgedSummary::generate(
            $store_id,
            '1970-01-01',
            '2025-12-31',
            0,
            0,
            1,
            0,
            0,
            true,
        );

        // Calculate Sum Total
        $amount_receivables = 0;
        foreach($customer_aged_summary as $r) {
            $amount_receivables += $r['total'];
        }

        // Round off.
        $amount_receivables = Utils::round($amount_receivables, 2);

        // Update Balance Sheet
        $statement = $db -> prepare('SELECT id, `statement`, modified FROM balance_sheet WHERE store_id = :store_id ORDER by `date` DESC LIMIT 1;');
        $statement -> execute([':store_id' => $store_id]);
        $balance_sheet = $statement -> fetchAll(PDO::FETCH_ASSOC)[0];
        $bs_id = $balance_sheet['id'];
        $bs_modified = $balance_sheet['modified'];
        $bs_statement = json_decode($balance_sheet['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        $bs_statement[AccountsConfig::ACCOUNTS_RECEIVABLE] = $amount_receivables;

        // Update 
        $statement = $db -> prepare('UPDATE balance_sheet SET `statement` = :_statement WHERE id = :id AND modified = :modified;');
        $is_successful = $statement -> execute([
            ':id' => $bs_id, 
            ':_statement' => json_encode($bs_statement, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ':modified' => $bs_modified
        ]);
        if($is_successful !== true && $statement -> rowCount() < 1) {
            throw new Exception('Unable to Update Balance Sheet.');
        }
        assert_success();
        $db -> commit();
        echo 'Fixed';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}

function fix_amount_owing(int $store_id) {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        // Fetch All Clients
        $clients = array_keys(Client::fetch_clients_of_store($store_id));

        // Prepare Statement
        $statement_fetch_amount_owing = $db -> prepare('SELECT amount_owing FROM clients WHERE id = :id;');
        $statement_update_amount_owing = $db -> prepare('UPDATE clients SET amount_owing = :amount_owing WHERE id = :id;');

        // Fetch Customer Statement 
        foreach($clients as $client_id) {
            $customer_statement = CustomerStatement::fetch_customer_statement(
                $client_id,
                $store_id,
                null,
                '2025-12-31',
            );
            
            if($customer_statement['status'] === false) continue;
            else {
                // Fetch Amount Owing
                $statement_fetch_amount_owing -> execute([':id' => $client_id]);
                $amount_owing = $statement_fetch_amount_owing -> fetchAll(PDO::FETCH_ASSOC)[0]['amount_owing'];
                
                $amount_owing = json_decode($amount_owing, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(isset($amount_owing[$store_id])) {
                    $amount_owing[$store_id] = Utils::round($customer_statement['data']['aged_summary']['total'], 4);
                }

                // Update Amount owing
                $is_successful = $statement_update_amount_owing -> execute([
                    ':amount_owing' => json_encode($amount_owing, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                    ':id' => $client_id,
                ]);
                
                if($is_successful !== true && $statement_update_amount_owing -> rowCount() < 1) {
                    throw new Exception('Unable to update amount owing for client: '. $client_id);
                }
            }
        }

        echo 'Done';
        assert_success();
        $db -> commit();
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

$store_id = StoreDetails::EDMONTON;
// f_record($store_id);
// fix_transactions_credit_amount(is_test: false, store_id: $store_id, date: '2025-12-01');
// fix_balance_sheet_amount_receivables($store_id);
// fix_amount_owing($store_id);
?>  