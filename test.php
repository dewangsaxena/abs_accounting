<?php 

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_sales.php";
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
    else return Utils::round($total_value);
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

        assert_success();
        $db -> commit();
        echo 'f_record: Done<br>';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

function __find_receipt_for_transaction(int $store_id, int $client_id, int $transaction_id, float $credit_amount, PDO &$db, &$deduct_transaction_credit_amount, $transaction_type, &$client_table) {
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
        
        // This will execute when there is only 1 receipt and the entire amount is settled in it.
        if(strval($amount_owing) == strval($total_amount_received)) {
            $is_successful = $deduct_transaction_credit_amount -> execute([':id' => $transaction_id, ':credit_amount' => $credit_amount]);
            if($is_successful !== true || $deduct_transaction_credit_amount -> rowCount() < 1) {
                throw new Exception('Unable to Update Transaction: '. $transaction_id);
            }
        }

        // This will execute when the transaction is in multiple receipts.
        else {
            // $transaction_type_str = $transaction_type === SALES_INVOICE ? 'Sales_Invoice' : 'Sales_Return';
            $transaction_type_str = $transaction_type;
            if(isset($client_table[$client_id]) === false) $client_table[$client_id] = [];
            if(isset($client_table[$client_id][$transaction_type_str][$transaction_id]) === false) {
                $client_table[$client_id][$transaction_type_str][$transaction_id] = 0;
            }
            $client_table[$client_id][$transaction_type_str][$transaction_id] += $amount_to_be_adjusted;
            // echo "$transaction_id $amount_owing $total_amount_received $amount_to_be_adjusted<br>";
        }
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

function fix_transactions_credit_amount(bool $is_test, int $store_id, string $date, int $transaction_type, array &$client_table): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

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
        $deduct_transaction_credit_amount = $db -> prepare('UPDATE '. $table_name.' SET credit_amount = credit_amount - :credit_amount WHERE id = :id;');
        $set_transaction_credit_amount_to_zero = $db -> prepare('UPDATE '. $table_name.' SET credit_amount = 0 WHERE id = :id;');

        foreach($results as $result) {
            $transaction_id = $result['id'];
            $client_id = $result['client_id'];
            $credit_amount = $result['credit_amount'];
            $credit_amount_str = strval($credit_amount);

            // Fix Negative Balance Issue
            $credit_amount_abs = abs($credit_amount);

            if($credit_amount_abs > 0 and $credit_amount_abs <= 0.1) {
                echo "$transaction_id | $credit_amount_str<br>";
                $is_successful = $set_transaction_credit_amount_to_zero -> execute([':id' => $transaction_id]);
                if($is_successful !== true && $set_transaction_credit_amount_to_zero -> rowCount() < 1) {
                    throw new Exception('Cannot Set Transaction to Zero: '. $transaction_id);
                }
            }
            
            // if(Utils::round($credit_amount, 4) == 0.0 && $credit_amount_str != '0.0000') {
            //     // Print Transactions
            //     if($is_test) echo "$transaction_id,";
            //     else {
            //         // Fetch Receipt for this transactions
            //         __find_receipt_for_transaction($store_id, $client_id, $transaction_id, $credit_amount, $db, $deduct_transaction_credit_amount, $transaction_type, $client_table);
            //     }
            // }
        }

        // Generate Exception on test
        if($is_test) throw new Exception('<br><br>TEST EXCEPTION');

        assert_success();
        $db -> commit();
        echo 'fix_transactions_credit_amount: Done<br>';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}

function update_credit_amount_of_all_transaction($client_table) {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        // Prepare Statement 
        $set_si_transaction_to_zero = $db -> prepare('UPDATE sales_invoice SET credit_amount = 0 WHERE id = :id;');
        $set_sr_transaction_to_zero = $db -> prepare('UPDATE sales_return  SET credit_amount = 0 WHERE id = :id;');

        foreach($client_table as $txn) {
            $sales_invoices = $txn[SALES_INVOICE] ?? [];
            $sales_returns = $txn[SALES_RETURN] ?? [];

            foreach($sales_invoices as $txn => $credit_amount) {
                $is_successful = $set_si_transaction_to_zero -> execute([
                    ':id' => $txn,
                ]);

                if($is_successful !== true && $set_si_transaction_to_zero -> rowCount() < 1) {
                    throw new Exception('Unable to Update Transaction: SI-'. $txn);
                }
            }

            foreach($sales_returns as $txn => $credit_amount) {
                $is_successful = $set_sr_transaction_to_zero -> execute([
                    ':id' => $txn,
                ]);

                if($is_successful !== true && $set_sr_transaction_to_zero -> rowCount() < 1) {
                    throw new Exception('Unable to Update Transaction: SR-'. $txn);
                }
            }
        }
        assert_success();
        $db -> commit();
        echo 'update_credit_amount_of_all_transaction: Done<br>';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
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
            '2026-12-31',
            0,
            0,
            exclude_self:1,
            exclude_clients:0,
            omit_credit_records: 0,
            do_return: true,
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
        echo 'fix_balance_sheet_amount_receivables: Done<br>';
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

        $current_date = Utils::get_business_date($store_id);

        // Fetch Customer Statement 
        foreach($clients as $client_id) {

            $customer_statement = CustomerStatement::fetch_customer_statement(
                $client_id,
                $store_id,
                null,
                $current_date,
            );

            // Set to zero.
            $set_to_zero = $customer_statement['status'] === false;
            
            // Fetch Amount Owing
            $statement_fetch_amount_owing -> execute([':id' => $client_id]);
            $amount_owing = $statement_fetch_amount_owing -> fetchAll(PDO::FETCH_ASSOC)[0]['amount_owing'];
  
            $amount_owing = json_decode($amount_owing, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if(isset($amount_owing[$store_id])) {
                if($set_to_zero) $amount_owing[$store_id] = 0;
                else $amount_owing[$store_id] = Utils::round($customer_statement['data']['aged_summary']['total'], 4);
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

        echo 'fix_amount_owing: Done<br>';
        assert_success();
        $db -> commit();
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

function print_client_details($client_table): void {
    $db = get_db_instance();
    $clients = array_keys($client_table);
    if (count($clients)) {
        $query = 'SELECT id, `name` FROM clients WHERE id IN (:placeholder);';
        $results = Utils::mysql_in_placeholder_pdo_substitute($clients, $query);
        $query = $results['query'];
        $values = $results['values'];

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $temp = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $client_details = [];
        foreach($temp as $client_detail) {
            $client_details[$client_detail['name']] = $client_table[$client_detail['id']];
        }

        $clients = array_keys($client_details);
        $temp = [];
        foreach($clients as $client) {
            $temp[$client] = [
                'credit_amount' => 0,
                'debit_amount' => 0,
                'txn' => $client_details[$client],
            ];

            $si = $client_details[$client][1] ?? [];
            $sr = $client_details[$client][2] ?? [];
            foreach($si as $t) {
                $temp[$client]['credit_amount'] += abs($t);
            }

            foreach($sr as $t) {
                $temp[$client]['debit_amount'] += abs($t);
            }
            
        }
        $clients = array_keys($temp);
        foreach($clients as $client) {
            echo $client.'<br>&nbsp;&nbsp;&nbsp;&nbsp;->&nbsp;&nbsp;';
            print_r($temp[$client]);
            echo '<br><br>';
        }
        echo '<br>';
    }
}

// SET UTILS::ROUND to 4 Decimal Places before proceeding.
$store_id = StoreDetails::EDMONTON;
// echo generate_list($store_id, false);die;
// $client_table = [];
// fix_transactions_credit_amount(is_test: false, store_id: $store_id, date: '2025-12-01', transaction_type: SALES_INVOICE, client_table: $client_table);
// fix_transactions_credit_amount(is_test: false, store_id: $store_id, date: '2025-12-01', transaction_type: SALES_RETURN, client_table: $client_table);
// print_client_details($client_table);
// // update_credit_amount_of_all_transaction($client_table);
// // DISABLE FEDERAL AND PROVINCIAL TAXES FOR CLIENTS.
// fix_balance_sheet_amount_receivables($store_id);
// fix_amount_owing($store_id);
// // SET UTILS::ROUND to 2 Decimal Places AFTER COMPLETING ALL STORES.
// // ENABLE FEDERAL AND PROVINCIAL TAXES FOR CLIENTS.

function calculate_cogs_from_details(array $details) {
    $cogs = 0;
    foreach($details as $i) {
        $cogs += ($i['buyingCost'] * $i['quantity']);
    }
    return $cogs;
}

function find_issue(int $store_id): void {
    $db = get_db_instance();

    // Fetch Debug Data 
    $statement = $db -> prepare('SELECT * FROM debug WHERE store_id = :store_id;');
    $statement -> execute([':store_id' => $store_id]);
    $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

    // Fetch Sales Invoice Details
    $statement_fetch_si = $db -> prepare('SELECT * FROM sales_invoice WHERE id = :_id;');
    $statement_fetch_inventory = $db -> prepare('SELECT * FROM inventory_history WHERE store_id = :store_id AND created = :_created_timestamp;');

    foreach($results as $result) {
        $id = $result['id'];
        $details = json_decode($result['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $amount = 0;

        $inventory_old = Utils::round($details['old_inventory_value']);
        $inventory_new = Utils::round($details['new_inventory_value']);
        $inventory_diff = abs(Utils::round($inventory_new - $inventory_old));

        $execute = false;
        $debug_created_time = $result['created'];
        if(isset($details['adjust_inventory'])) {
            $execute = false;

            // Fetch Inventory details
            $statement_fetch_inventory -> execute([':store_id' => $store_id, ':_created_timestamp' => $debug_created_time]);
            $inventory = json_decode($statement_fetch_inventory -> fetchAll(PDO::FETCH_ASSOC)[0]['details'], true, flags: JSON_NUMERIC_CHECK);
            
            // Get Inventory Value
            foreach($inventory as $i) {
                $amount += ($i['quantity'] * $i['buyingCost']);
            }
        }
        else if(isset($details['sales_invoice_id'])) {
            $execute = false;
            $statement_fetch_si -> execute([':_id' => $details['sales_invoice_id']]);
            $si = $statement_fetch_si -> fetchAll(PDO::FETCH_ASSOC)[0];
            $amount = $si['cogs'];

            // Check for Edits
            if(is_null($si['versions']) === false) {
                $versions = json_decode($si['versions'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $version_keys = array_keys($versions);
                $local_time_from_debug = Utils::convert_utc_str_timestamp_to_localtime($debug_created_time, $store_id);
                $selected_version = null;
                foreach($version_keys as $version) {
                    $local_time = Utils::convert_to_local_timestamp_from_utc_unix_timestamp($version, $store_id);
                    if($local_time_from_debug == $local_time) {
                        $selected_version = $version;
                        break;
                    }
                }

                $amount = calculate_cogs_from_details($versions[$selected_version]);                
            } 
        }
        else if(isset($details['sales_invoice_id (Update)'])) {
            $execute = true;
            $statement_fetch_si -> execute([':_id' => $details['sales_invoice_id (Update)']]);
            $si = $statement_fetch_si -> fetchAll(PDO::FETCH_ASSOC)[0];
            $amount = $si['cogs'];
            $si_modified = $si['modified'];
            $latest_cogs = $si['cogs'];

            // Check for Edits
            if(is_null($si['versions']) === false) {
                $versions = json_decode($si['versions'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $version_keys = array_keys($versions);
                $local_time_from_debug = Utils::convert_utc_str_timestamp_to_localtime($debug_created_time, $store_id);
                $selected_version = null;

                foreach($version_keys as $version) {
                    $local_time = Utils::convert_to_local_timestamp_from_utc_unix_timestamp($version, $store_id);

                    if($local_time_from_debug == $local_time) {
                        $selected_version = $version;
                        break;
                    }

                    $cogs_of_version = calculate_cogs_from_details($versions[$version]);
                    $amount = Utils::round(abs($latest_cogs - $cogs_of_version), 2);
                    if($amount == $inventory_diff) {
                        $selected_version = $version;
                        break;
                    }
                }

                // if(is_null($selected_version) === false) $amount = calculate_cogs_from_details($versions[$selected_version]);                
            } 

            // No Changed have been made
            // Just invoice was edited
            else {
                $amount = 0;
            }

            if(is_null($selected_version)) {
                // The Latest Version
                $amount = $latest_cogs ;
            }
        }

        // Check for Difference
        $amount = abs(Utils::round($amount, 2));
        if($execute && $inventory_diff != $amount) {
            echo ("$id : $inventory_diff : $amount<br>");
        }
    }
}

function round_bs_inventory(int $store_id): void {
    $db = get_db_instance();
    $db -> beginTransaction();
    try {

        // Fetch Balance Sheet
        $statement = $db -> prepare('SELECT * FROM balance_sheet WHERE store_id = :store_id ORDER BY id DESC LIMIT 1;');
        $statement -> execute([':store_id' => $store_id]);
        $balance_sheet = $statement -> fetchAll(PDO::FETCH_ASSOC)[0];
        $id = $balance_sheet['id'];
        $bs_statement = json_decode($balance_sheet['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        

        $bs_statement[1520] = Utils::round($bs_statement[1520], 2);

        // Update Balance Sheet
        $statement = $db -> prepare('UPDATE balance_sheet SET `statement` = :_statement WHERE id = :id;');
        $is_successful = $statement -> execute([
            ':_statement' => json_encode($bs_statement, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ':id' => $id,
        ]);
        
        if($is_successful !== true && $statement -> rowCount() < 1) {
            throw new Exception('Unable to update Balance Sheet');
        }

        assert_success();
        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        print_r($e -> getMessage());
        $db -> rollBack();
    }
}
fix_inventory_value($store_id);
round_bs_inventory($store_id);
?>  