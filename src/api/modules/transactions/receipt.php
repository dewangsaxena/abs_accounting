<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class Receipt {

    /**
     * This method will remove attributes.
     * @param transactions
     * @return void
     */
    private static function remove_attributes(array &$transactions): void {
        $count = count($transactions);
        for($i = 0; $i < $count; ++$i) {
            // Delete Attribute(s)
            if(isset($transactions[$i]['isChecked'])) unset($transactions[$i]['isChecked']);
        }
    }

    /**
     * This method will return valid transactions and calculate total amounts such as Amount Received and Total Discount.
     * @param data
     * @return array 
     */
    private static function get_valid_transactions_and_amount(array $data): array {
        $total_amount_received = 0;
        $total_discount = 0;
        $valid_transactions = [];
        foreach($data as $txn) {
            // Transaction Type
            $type = $txn['type'];

            // Amount Received
            $amount_received = $txn['amountReceived'];

            // Check for Valid Amount Received
            if(!is_numeric($amount_received)) throw new Exception('Invalid Amount Received for Txn: '. $txn['txnId']);

            // Ignore Transaction
            else if($amount_received == 0) continue;

            // Check for Valid Transactions.
            else if(($type === SALES_INVOICE || $type === DEBIT_NOTE) && $amount_received < 0) {
                throw new Exception('Amount Received cannot be negative for Debit Type Transaction: '. $txn['txnId']);
            }
            else if(($type === SALES_RETURN || $type === CREDIT_NOTE) && $amount_received > 0) {
                throw new Exception('Amount Received cannot be positive for Credit Type Transaction: '. $txn['txnId']);
            }

            // Txn Amounts
            $amount_owing = $txn['amountOwing'];
            $discount_available = $txn['discountAvailable'];
            $discount_given = $txn['discountGiven'];

            // Check for Valid Discount Given
            if(!is_numeric($discount_given)) throw new Exception('Invalid Discount Given for Txn: '. $txn['txnId']);

            // Check for Valid Amount Owing
            if(abs($amount_received) > abs($amount_owing)) throw new Exception('Amount Received cannot be greater than amount owing for Txn: '. $txn['txnId']);
            
            if($discount_given !== 0) {

                // Check for Valid Discount
                if(abs($discount_given) > abs($discount_available)) throw new Exception('Discount Given cannot be more than Discount Available for: '. $txn['txnId']);

                if($type === SALES_INVOICE) {
                    if($discount_given < 0) throw new Exception('Discount Given cannot be less than 0 for: '. $txn['txnId']);
                    else if(Utils::round($discount_given + $amount_received) > $amount_owing) throw new Exception('Discount Given + Amount Received cannot be greater tha Amount Owing: '. $txn['txnId']);
                }
                if($type === SALES_RETURN) {
                    if($discount_given > 0) throw new Exception('Discount Given cannot be more than 0 for: '. $txn['txnId']);
                    else if(Utils::round($discount_given + $amount_received) < $amount_owing) throw new Exception('Discount Given + Amount Received cannot be greater tha Amount Owing for: '. $txn['txnId']);
                }
                else if($type === CREDIT_NOTE || $type === DEBIT_NOTE) {
                    if($discount_given !== 0) throw new Exception('Invalid Discount Given for Credit/Debit Note for: '. $txn['txnId']);
                }
            }

            // Total Amount Received
            $total_amount_received += ($amount_received + $discount_given);
            $total_discount += $discount_given; 

            // Add to Valid Transactions
            $valid_transactions []= $txn;
        }

        return [
            'sum_total' => Utils::round($total_amount_received - $total_discount),
            'total_discount' => Utils::round($total_discount),
            'transactions' => $valid_transactions,
        ];
    }

    /**
     * This method will validate details. 
     * @param data
     * @return array
     */
    private static function validated_details(array &$data): array {

        // Client ID
        if(!is_numeric($data['clientId'])) throw new Exception('Invalid Client Id.');

        // Check for One Time Customer
        if(intval($data['clientId']) === ONE_TIME_CUSTOMER_ID) throw new Exception('Cannot Create Receipt for One Time Customer.');

        // Check for Payment Method
        else if(!in_array($data['paymentMethod'], PaymentMethod::RECEIPT_PAYMENT_METHODS)) throw new Exception('Invalid Payment Method');

        // Get Validated Transactions
        $validated_transactions = self::get_valid_transactions_and_amount($data['transactions']);

        // Check for Cheque No.
        if($data['paymentMethod'] === PaymentMethod::CHEQUE) {
            $cheque_no = trim($data['chequeNumber'] ?? '');
            if(!isset($cheque_no[0])) throw new Exception('Invalid Cheque Number.');
        }
        return $validated_transactions;
    }

    /**
     * This method will process transactions.
     * @param data
     * @param db
     * @param bs
     * @param undo
     */
    private static function process_transactions(array $data, PDO &$db, array &$bs, bool $undo = false): void {
        
        // Template Query
        $template_query = <<<'EOS'
        SET 
            credit_amount = credit_amount + :credit_amount, 
            __receipt_discount__
            __lock_counter = __lock_counter + :__lock_counter,
            modified = CURRENT_TIMESTAMP
        WHERE 
            id = :id 
        AND 
            modified = :modified;
        EOS;

        $shared_db_query_si_sr = str_replace(
            '__receipt_discount__',
            'receipt_discount = receipt_discount + :receipt_discount,',
            $template_query
        );

        $shared_db_query_dn_cn = str_replace('__receipt_discount__', '', $template_query);

        $sales_invoice = $db -> prepare('UPDATE sales_invoice '. $shared_db_query_si_sr);
        $sales_return  = $db -> prepare('UPDATE sales_return '. $shared_db_query_si_sr);
        $credit_note   = $db -> prepare('UPDATE credit_note '. $shared_db_query_dn_cn);
        $debit_note    = $db -> prepare('UPDATE debit_note '. $shared_db_query_dn_cn);

        // Lock Counter
        $__lock_counter = $undo ? -1 : 1;

        // Accounts Receivables/Payables Amount
        $accounts_receivables_amount = 0;
        
        foreach($data as $txn) {
            // Transaction Type
            $type = $txn['type'];

            // Amount Received
            $amount_received = Utils::round($txn['amountReceived'] + $txn['discountGiven']);
            
            // Discount Given
            $discount_given = $txn['discountGiven'];

            // Params
            $params = [
                ':id' => $txn['id'],
                ':__lock_counter' => $__lock_counter,
                ':modified' => $txn['modified'],
            ];

            // Amount Received Abs
            $amount_received_abs = abs($amount_received);

            if($type === SALES_INVOICE) {

                // Add Details
                $params[':credit_amount'] = $undo ? $amount_received : -$amount_received;
                $params[':receipt_discount'] = $undo ? -$discount_given : $discount_given;
                $is_successful = $sales_invoice -> execute($params);
                if($is_successful !== true || $sales_invoice -> rowCount() < 1) throw new Exception('Unable to Update Sales Invoice: '. $txn['id']);
                $accounts_receivables_amount += $amount_received_abs;
            }
            else if($type === SALES_RETURN) {
                $discount_given = abs($discount_given);

                // Check for Sales Invoices Receipt Discount
                $sales_invoice_receipt_discount = self::fetch_receipt_discount_of_sales_invoice(
                    $txn['salesInvoiceId'] ?? null, 
                    $db
                );

                // Check for Validity of Receipt Discount
                if($discount_given > $sales_invoice_receipt_discount) throw new Exception(
                    'Receipt Discount Given against Sales Return #'. $txn['id'].
                    ' exceeds receipt discount given against associated Sales Invoice #'. $txn['salesInvoiceId']
                );

                // Undo
                if($undo) $discount_given = -$discount_given;

                // Add Details
                $params[':credit_amount'] = $undo ? -$amount_received: $amount_received;
                $params[':receipt_discount'] = $undo ? $discount_given: $discount_given;

                $is_successful = $sales_return -> execute($params);
                if($is_successful !== true || $sales_return -> rowCount() < 1) throw new Exception('Unable to Update Sales Return: '. $txn['id']);

                // Adjust 
                if($undo) $accounts_receivables_amount += $amount_received_abs;
                else $accounts_receivables_amount -= $amount_received_abs;
            }
            else if($type === DEBIT_NOTE) {

                // Add Details
                $params[':credit_amount'] = $undo ? $amount_received : -$amount_received;
                $is_successful = $debit_note -> execute($params);
                if($is_successful !== true || $debit_note -> rowCount() < 1) throw new Exception('Unable to Update Debit Note: '. $txn['id']);
                if($undo) $accounts_receivables_amount -= $amount_received_abs;
                else $accounts_receivables_amount += $amount_received_abs;
            }
            else if($type === CREDIT_NOTE) {
                
                // Add Details
                $params[':credit_amount'] = $undo ? -$amount_received: $amount_received;
                $is_successful = $credit_note -> execute($params);
                if($is_successful !== true || $credit_note -> rowCount() < 1) throw new Exception('Unable to Update Credit Note: '. $txn['id']);
                if($undo) $accounts_receivables_amount += $amount_received_abs;
                else $accounts_receivables_amount -= $amount_received_abs;
            }
        }

        // Update Balance Sheet Amounts.
        $bs[AccountsConfig::ACCOUNTS_RECEIVABLE] -= $accounts_receivables_amount;
    }

    /**
     * This method will fetch receipt discount of sales invoice.
     * @param sales_invoice_id
     * @param db
     * @return float Receipt Discount 
     */
    private static function fetch_receipt_discount_of_sales_invoice(int $sales_invoice_id, PDO &$db): float {
        $statement = $db -> prepare('SELECT receipt_discount FROM sales_invoice WHERE id = :id;');
        $statement -> execute([':id' => $sales_invoice_id]);
        return $statement -> fetchAll(PDO::FETCH_ASSOC)[0]['receipt_discount'];
    }

    /**
     * This method will create Receipt.
     * @param data
     * @return array
     */
    private static function create_receipt(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Validated Details
            $validated_details = self::validated_details($data);
            $details = $validated_details['transactions'];
            $sum_total = $validated_details['sum_total'];
            $total_discount = $validated_details['total_discount'];
            $payment_method = intval($data['paymentMethod']);
            $store_id = intval($_SESSION['store_id']);
            $sales_rep_id = intval($_SESSION['user_id']);
            $client_id = intval($data['clientId']);
            $client_last_modified_timestamp = $data['clientLastModifiedTimestamp'];
            $date = Utils::get_business_date($store_id);

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $client_last_modified_timestamp, $db);

            // Affected Accounts 
            $bs_accounts_affected = AccountsConfig::ACCOUNTS;

            // Process
            self::process_transactions($details, $db, $bs_accounts_affected, undo: false);

            // Forgiven 
            if($payment_method === PaymentMethod::FORGIVEN) {
                throw new Exception('Invalid Payment Method');
                
                // Deduct from Income Statement
                IncomeStatementActions::update(
                    [AccountsConfig::SALES_INVENTORY_A => 0],
                    $date,
                    $store_id,
                    $db
                );
            }
            else {
                // Add Total Amount to Receipt Payment Method
                $payment_method_account = AccountsConfig::get_account_code_by_payment_method($payment_method);
                $bs_accounts_affected[$payment_method_account] += $sum_total;
                
                // Update Balance Sheet
                BalanceSheetActions::update_from($bs_accounts_affected, $date, $store_id, $db);

                // Update Income Statement
                IncomeStatementActions::update(
                    [AccountsConfig::EARLY_PAYMENT_SALES_DISCOUNT => $total_discount],
                    $date,
                    $store_id,
                    $db
                );
            }

            // Update Client Amount Owing
            Client::update_amount_owing_of_client(
                $client_id, 
                -($sum_total + $total_discount), 
                $db
            );

            // Remove Attributes 
            self::remove_attributes($details);

            // Create Receipt.
            $query = <<<'EOS'
            INSERT INTO receipt
            (
                client_id,
                `date`,
                details,
                sum_total,
                total_discount,
                payment_method,
                cheque_number,
                `comment`,
                store_id,
                sales_rep_id
            ) 
            VALUES
            (
                :client_id,
                :date,
                :details,
                :sum_total,
                :total_discount,
                :payment_method,
                :cheque_number,
                :comment,
                :store_id,
                :sales_rep_id
            );
            EOS;

            $params = [
                ':client_id' => $client_id,
                ':date' => $date,
                ':details' => json_encode($details, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':sum_total' => $sum_total,
                ':total_discount' => $total_discount,
                ':payment_method' => $payment_method,
                ':cheque_number' => trim($data['chequeNumber'] ?? ''),
                ':comment' => trim($data['comment'] ?? ''),
                ':store_id' => $store_id,
                ':sales_rep_id' => $sales_rep_id,
            ];

            // Check for Exception
            assert_success();

            $statement = $db -> prepare($query);
            $statement -> execute($params);

            $receipt_id = $db -> lastInsertId();
            if($receipt_id === false) throw new Exception('Unable to Create Receipt.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $receipt_id];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will verify last modified timestamp.
     * @param receipt_id
     * @param timestamp
     * @param db
     * @throws Exception
     */
    private static function verify_last_modified_timestamp(int $receipt_id, string $timestamp, PDO &$db): void {
        $statement = $db -> prepare('SELECT modified FROM receipt WHERE id = :id;');
        $statement -> execute([':id' => $receipt_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(!isset($result[0]['modified'])) throw new Exception('Unable to Fetch Receipt.');

        // Check for Fresh Copy.
        if($result[0]['modified'] !== $timestamp) throw new Exception('Cannot Update Stale Copy of Receipt. Please reload and retry.');
    }
    
    /**
     * This method will update Receipt.
     * @param data
     * @return array
     */
    private static function update_receipt(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Receipt Id
            $receipt_id = $data['id'];

            // Last Modified timestamp
            $last_modified_timestamp = $data['modified'];

            // Verify Last Modified Timestamp
            self::verify_last_modified_timestamp($receipt_id, $last_modified_timestamp, $db);

            // Validated Details
            $validated_details = self::validated_details($data);
            $sum_total = $validated_details['sum_total'];
            $payment_method = intval($data['paymentMethod']);
            $initial_payment_method = intval($data['initial']['paymentMethod']);
            $store_id = intval($_SESSION['store_id']);
            $date = Utils::get_YYYY_mm_dd(
                Utils::convert_utc_str_timestamp_to_localtime($data['date'], $store_id)
            );
            
            // Change Payment Method
            if($payment_method !== $initial_payment_method) {
                // Affected Accounts 
                $bs_accounts_affected = AccountsConfig::ACCOUNTS;

                // Deduct Total Amount From Receipt Payment Method
                $initial_payment_method_account = AccountsConfig::get_account_code_by_payment_method($initial_payment_method);
                if($sum_total < 0) $temp = abs($sum_total);
                else $temp = -$sum_total;
                $bs_accounts_affected[$initial_payment_method_account] += $temp;

                // Add Total Amount to New Receipt Payment Method
                $new_payment_method_account = AccountsConfig::get_account_code_by_payment_method($payment_method);
                $bs_accounts_affected[$new_payment_method_account] += $sum_total;

                // Update Balance Sheet
                BalanceSheetActions::update_from($bs_accounts_affected, $date, $store_id, $db);
            }

            // Update Receipt Payment Method 
            $query = <<<'EOS'
            UPDATE 
                receipt 
            SET 
                payment_method = :payment_method,
                comment = :comment,
                cheque_number = :cheque_number,
                modified = CURRENT_TIMESTAMP 
            WHERE 
                id = :id
            AND
                do_conceal = 0
            AND 
                modified = :last_modified_timestamp;
            EOS;
            
            $params = [
                ':payment_method' => $payment_method,
                ':comment' => trim($data['comment'] ?? ''),
                ':cheque_number' => trim($data['chequeNumber'] ?? ''),
                ':id' => $receipt_id,
                ':last_modified_timestamp' => $last_modified_timestamp,
            ];

            // Assert Success
            assert_success();

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Update Receipt. Reload the receipt and try again.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $data['id']];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will check whether the receipt is fresh and not concealed.
     * @param receipt_id
     * @param modified
     * @param db
     * @throws Exception
     */
    private static function validate_receipt_before_deletion(int $receipt_id, string $modified, PDO &$db): void {
        $statement = $db -> prepare('SELECT do_conceal, `modified` FROM receipt WHERE id = :id;');
        $statement -> execute([':id' => $receipt_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(count($result) > 0) $result = $result[0];
        else throw new Exception('Unable to Find Receipt By ID: '. $receipt_id);

        // Check for Modified Timestamp
        if($result['modified'] !== $modified) throw new Exception('Cannot Delete a Stale Receipt. Please reload and try again.');

        // Check for Concealment 
        if($result['do_conceal'] === 1) throw new Exception('This receipt is already concealed.');
    }

    /**
     * This method will delete receipt transactions.
     * @param data
     * @return array
     */
    private static function delete_receipt(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Check for Already Concealed Receipt and "fresh" status
            self::validate_receipt_before_deletion($data['id'], $data['modified'], $db);

            // Fetch the Copy of Transactions.
            $receipt_details = self::fetch_by_id($data['id'], $db);
            if($receipt_details['status'] === false) throw new Exception('Receipt with ID # '. $data['id']);
            else $receipt_details = $receipt_details['data'];

            // Client Id 
            $client_id = $receipt_details['clientId'];

            // Client Last Modified Timestamp
            $client_last_modified_timestamp = $receipt_details['clientLastModifiedTimestamp'];

            // Check for fresh copy of client.
            Client::check_fresh_copy_of_client($client_id, $client_last_modified_timestamp, $db);

            // Total Amount Received
            $total_amount_received = $receipt_details['totalAmountReceived'];

            // Total Discount
            $total_discount = $receipt_details['totalDiscount'];

            // Payment Method
            $payment_method = $receipt_details['paymentMethod'];

            // Store Id 
            $store_id = $receipt_details['storeId'];

            // Date 
            $date = $receipt_details['date'];

            // Txn. Details
            $transactions = $receipt_details['transactions'];

            // Update Transactions Modified Timestamps
            self::update_modified_timestamps_for_transactions($transactions, $db);

            // Affected Accounts 
            $bs_accounts_affected = AccountsConfig::ACCOUNTS;
            
            // Reverse the transaction.
            self::process_transactions($transactions, $db, $bs_accounts_affected, undo: true);

            // Reverse Sign to adjust
            if($total_amount_received < 0) $temp_total_amount_received = abs($total_amount_received);
            else $temp_total_amount_received = -$total_amount_received;

            // Adjust Total Amount to Receipt Payment Method
            $payment_method_account = AccountsConfig::get_account_code_by_payment_method($payment_method);
            $bs_accounts_affected[$payment_method_account] += $temp_total_amount_received;

            // Update Balance Sheet
            BalanceSheetActions::update_from($bs_accounts_affected, $date, $store_id, $db);

            // Update Income Statement
            IncomeStatementActions::update(
                [AccountsConfig::EARLY_PAYMENT_SALES_DISCOUNT => -abs($total_discount)],
                $date,
                $store_id,
                $db
            );

            // Total Amount To Adjust 
            $total_amount_to_adjust = abs($total_amount_received) + abs($total_discount);

            // Change Sign if Receipt Was Overall a credit txn.
            if($total_amount_received < 0) $total_amount_to_adjust = -$total_amount_to_adjust;

            // Update Client Amount Owing
            Client::update_amount_owing_of_client(
                $client_id, 
                $total_amount_to_adjust, 
                $db
            );

            // Hide Receipt.
            $query = <<<'EOS'
            UPDATE 
                receipt
            SET 
                do_conceal = 1,
                modified = CURRENT_TIMESTAMP 
            WHERE 
                id = :id
            AND 
                do_conceal = 0
            AND 
                modified = :modified;
            EOS;

            $params = [
                ':id' => $data['id'],
                ':modified' => $data['modified'],
            ];

            /* Assert Success */
            assert_success();

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Delete Receipt.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will calculate discount available.
     * @param date
     * @param amount_eligible_for_receipt_discount
     * @param early_payment_discount
     * @param early_payment_paid_within_days
     * @param store_id
     * @return float
     */
    private static function calculate_discount_available($date, $amount_eligible_for_receipt_discount, $early_payment_discount, $early_payment_paid_within_days, $store_id) : float {
        $date_diff = Utils::get_difference_from_current_date($date, Utils::get_business_date($store_id), $store_id);
        $diff = ($date_diff['y'] * 365) + ($date_diff['m'] * 30) + $date_diff['d'];
        $discount_available = 0;
        if($diff < $early_payment_paid_within_days) {
            $discount_available = Utils::round(($amount_eligible_for_receipt_discount * $early_payment_discount) / 100);
        }
        return $discount_available;
    }

    /**
     * This method will fetch outstanding transactions of client.
     * @param client_id
     * @return array
     */
    private static function fetch_outstanding_transactions_of_client(int $client_id): array {
        try {
            $query = <<<'EOS'
            SELECT * FROM (
                SELECT 
                    1 AS type,
                    id,
                    `date`,
                    credit_amount,
                    sum_total,
                    amount_eligible_for_receipt_discount,
                    receipt_discount,
                    NULL AS sales_invoice_id,
                    early_payment_discount,
                    early_payment_paid_within_days,
                    net_amount_due_within_days,
                    NULL AS sales_invoice_payment_method,
                    modified
                FROM 
                    sales_invoice 
                WHERE 
                    credit_amount > 0 
                AND 
                    client_id = :client_id 
                AND
                    store_id = :store_id
                UNION ALL
                SELECT 
                    2 AS type,
                    id,
                    `date`,
                    credit_amount,
                    sum_total,
                    amount_eligible_for_receipt_discount,
                    receipt_discount,
                    sales_invoice_id,
                    early_payment_discount,
                    early_payment_paid_within_days,
                    net_amount_due_within_days,
                    sales_invoice_payment_method,
                    modified
                FROM 
                    sales_return 
                WHERE 
                    credit_amount > 0 
                AND 
                    client_id = :client_id 
                AND
                    store_id = :store_id
                UNION ALL
                SELECT 
                    3 AS type,
                    id,
                    `date`,
                    credit_amount,
                    sum_total,
                    0 AS amount_eligible_for_receipt_discount,
                    0 AS receipt_discount,
                    NULL AS sales_invoice_id,
                    NULL AS early_payment_discount,
                    NULL AS early_payment_paid_within_days,
                    NULL AS net_amount_due_within_days,
                    NULL AS sales_invoice_payment_method,
                    modified
                FROM 
                    credit_note 
                WHERE 
                    credit_amount > 0 
                AND 
                    client_id = :client_id 
                AND
                    store_id = :store_id
                UNION ALL
                SELECT 
                    4 AS type,
                    id,
                    `date`,
                    credit_amount,
                    sum_total,
                    0 AS amount_eligible_for_receipt_discount,
                    0 AS receipt_discount,
                    NULL AS sales_invoice_id,
                    NULL AS early_payment_discount,
                    NULL AS early_payment_paid_within_days,
                    NULL AS net_amount_due_within_days,
                    NULL AS sales_invoice_payment_method,
                    modified
                FROM 
                    debit_note 
                WHERE 
                    credit_amount > 0 
                AND 
                    client_id = :client_id 
                AND
                    store_id = :store_id
            ) AS _tmp 
            ORDER BY 
                `date` ASC,
                `type` ASC,
                id ASC;
            EOS;
            
            // Store Id 
            $store_id = intval($_SESSION['store_id']);

            // Fetch from DB
            $db = get_db_instance();
            $statement = $db -> prepare($query);
            $statement -> execute([':store_id' => $store_id, ':client_id' => $client_id]);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $response = [];
            
            $transactions_abbr = [
                SALES_INVOICE => 'IN',
                SALES_RETURN => 'SR',
                CREDIT_NOTE => 'CN',
                DEBIT_NOTE => 'DN',
            ];
            foreach($records as $record) {
                if($record['type'] === SALES_INVOICE || $record['type'] === SALES_RETURN) {
                    $amount_eligible_for_receipt_discount = self::calculate_discount_available(
                        $record['date'], 
                        $record['amount_eligible_for_receipt_discount'],
                        $record['early_payment_discount'],
                        $record['early_payment_paid_within_days'],
                        $store_id
                    );
                }
                else $amount_eligible_for_receipt_discount = 0;

                // Check for Credit Record.
                $is_credit_record = $record['type'] === SALES_RETURN || $record['type'] === CREDIT_NOTE;

                // Disable Any Further Discount, If already taken on Transaction
                if($record['receipt_discount'] != 0) $amount_eligible_for_receipt_discount = 0;

                $response[]= [
                    'id' => $record['id'],
                    'txnId' => $transactions_abbr[$record['type']].'-'. $record['id'],
                    'type' => $record['type'],
                    'date' => $record['date'],
                    'originalAmount' => $is_credit_record ? -$record['sum_total'] : $record['sum_total'],
                    'amountOwing' => $is_credit_record ? -$record['credit_amount']: $record['credit_amount'],
                    'discountAvailable' => $is_credit_record ? -$amount_eligible_for_receipt_discount: $amount_eligible_for_receipt_discount,
                    'discountGiven' => 0,
                    'amountReceived' => 0,
                    'salesInvoiceId' => $record['sales_invoice_id'],
                    'salesInvoicePaymentMethod' => $record['sales_invoice_payment_method'],
                    'isChecked' => 0,
                    'modified' => $record['modified'],
                ];
            }
            return ['status' => true, 'data' => $response];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will update modified timestamps for transactions.
     * @param transactions 
     * @param db
     */
    private static function update_modified_timestamps_for_transactions(array &$transactions, PDO &$db): void {

        // Ids
        $sales_invoice_ids = [];
        $sales_return_ids = [];
        $credit_note_ids = [];
        $debit_note_ids = [];

        foreach($transactions as $txn) {
            $type = $txn['type'];

            // Add ids.
            if($type === SALES_INVOICE) $sales_invoice_ids[]= $txn['id'];
            else if($type === SALES_RETURN) $sales_return_ids[]= $txn['id'];
            else if($type === CREDIT_NOTE) $credit_note_ids[]= $txn['id'];
            else if($type === DEBIT_NOTE) $debit_note_ids[]= $txn['id'];
        }

        // Fetch Modified Timestamps.
        $sales_invoice_timestamps = [];
        $sales_return_timestamps = [];
        $credit_note_timestamps = [];
        $debit_note_timestamps = [];

        if(count($sales_invoice_ids) > 0) {
            $query = 'SELECT id, `modified` FROM sales_invoice WHERE id IN (:placeholder);';
            $result = Utils::mysql_in_placeholder($sales_invoice_ids, $query);
            $query = $result['query'];
            $values = $result['values'];
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            foreach($records as $record) {
                $sales_invoice_timestamps[$record['id']] = $record['modified'];
            }
        }

        if(count($sales_return_ids) > 0) {
            $query = 'SELECT id, `modified` FROM sales_return WHERE id IN (:placeholder);';
            $result = Utils::mysql_in_placeholder($sales_return_ids, $query);
            $query = $result['query'];
            $values = $result['values'];
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            foreach($records as $record) {
                $sales_return_timestamps[$record['id']] = $record['modified'];
            }
        }

        if(count($credit_note_ids) > 0) {
            $query = 'SELECT id, `modified` FROM credit_note WHERE id IN (:placeholder);';
            $result = Utils::mysql_in_placeholder($credit_note_ids, $query);
            $query = $result['query'];
            $values = $result['values'];
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            foreach($records as $record) {
                $credit_note_timestamps[$record['id']] = $record['modified'];
            }
        }

        if(count($debit_note_ids) > 0) {
            $query = 'SELECT id, `modified` FROM debit_note WHERE id IN (:placeholder);';
            $result = Utils::mysql_in_placeholder($debit_note_ids, $query);
            $query = $result['query'];
            $values = $result['values'];
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            foreach($records as $record) {
                $debit_note_timestamps[$record['id']] = $record['modified'];
            }
        }

        // Update Timestamps
        $count = count($transactions);
        for($i = 0; $i < $count; ++$i) {
            $type = $transactions[$i]['type'];
            $txn_id = $transactions[$i]['id'];
            if($type === SALES_INVOICE) $transactions[$i]['modified'] = $sales_invoice_timestamps[$txn_id];
            else if($type === SALES_RETURN) $transactions[$i]['modified'] = $sales_return_timestamps[$txn_id];
            else if($type === CREDIT_NOTE) $transactions[$i]['modified'] = $credit_note_timestamps[$txn_id];
            else if($type === DEBIT_NOTE) $transactions[$i]['modified'] = $debit_note_timestamps[$txn_id];
        }
    }

    /**
     * This method will fetch the transaction by id.
     * @param receipt_id
     * @param db
     * @return array
     */
    private static function fetch_by_id(int $receipt_id, PDO &$db=null): array {
        try {
            if($db === null) $db = get_db_instance();
            
            $query = <<<'EOS'
            SELECT 
                r.*,
                c.`name` AS clientName
            FROM 
                receipt AS r
            LEFT JOIN 
                clients AS c
            ON 
                r.client_id = c.id
            WHERE 
                r.id = :receipt_id
            AND 
                r.do_conceal = 0;
            EOS;

            $statement = $db -> prepare($query);
            $statement -> execute([
                ':receipt_id' => $receipt_id,
            ]);
            $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $formatted_result = [];
            if(isset($result[0])) {
                $result = $result[0];
                $transactions = json_decode($result['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $count = count($transactions);

                // Add Attribute
                for($i = 0; $i < $count; ++$i) $transactions[$i]['isChecked'] = 0;

                // Fetch Client Last Modified Timestamp
                $client_details = Client::fetch(['id' => $result['client_id']], $db);
                if($client_details['status'] === false) throw new Exception($client_details['message']);
                else $client_details = $client_details['data'][0];
                
                $formatted_result = [
                    'id' => $result['id'],
                    'clientId' => $result['client_id'],
                    'clientLastModifiedTimestamp' => $client_details['lastModifiedTimestamp'],
                    'clientName' => $result['clientName'],
                    'date' => $result['date'],
                    'paymentMethod' => $result['payment_method'],
                    'transactions' => $transactions,
                    'totalAmountReceived' => $result['sum_total'],
                    'totalDiscount' => $result['total_discount'],
                    'comment' => $result['comment'],
                    'chequeNumber' => $result['cheque_number'] ?? '',
                    'storeId' => $result['store_id'],
                    'modified' => $result['modified'],
                    'initial' => [
                        'paymentMethod' => $result['payment_method'],
                    ],
                ];
            }

            $has_transaction_records = count($formatted_result) > 0 ? true : false;
            return [
                'status' => $has_transaction_records, 
                'data' => $formatted_result,
                'message' => $has_transaction_records === false ? 'No Transaction Found.': '',
            ];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will generate pdf.
     * @param receipt_id
     * @param transactions
     * @param for
     * @param dump_file
     */
    private static function generate_pdf(int $receipt_id, string $for, array $transactions=[], bool $dump_file=false): array {
        try {
            $path_to_output_file = null;
            $err_message = '';
            $random_token = Utils::generate_token(4);

            // By Default, Receipt Filename will be included.
            $filenames = ["receipt_$receipt_id.pdf"];
            $attach_transactions = count($transactions) > 0;
            if($attach_transactions) {
                $result = Shared::generate_pdf($transactions, dump_file: true);
                if($result['status'] === false) throw new Exception($result['message']);
                $filenames = array_merge($filenames, $result['data']);

                // Change Dump File Status 
                $dump_file = true;
            }

            // Receipt Details
            $receipt_details = PrepareDetails_Receipts::fetch_record_by_id($receipt_id);

            // Generate
            GeneratePDF::receipt($receipt_details, $filenames[0], $dump_file);

            // Merge PDF
            $merge_object = Utils::merge_pdfs($filenames);

            // Delete Residual Files
            if($for === 'print' && $dump_file) Utils::delete_files($filenames);

            // This is the output filename
            $receipt_filename = "receipt-$receipt_id-$random_token.pdf";

            // Output 
            $merge_object -> output($for === 'email' && $dump_file ? TEMP_DIR. $receipt_filename : null);

            // Set Path To Output File
            $path_to_output_file = TEMP_DIR. $receipt_filename;
        }
        catch(Exception $e) {
            $path_to_output_file = null;
            $err_message = $e -> getMessage();
        }
        finally {
            // Delete residual files
            Utils::delete_files($filenames);
            return ['status' => $path_to_output_file !== null, 'data' => $path_to_output_file, 'message' => $err_message];
        }
    }

    /**
     * This method will send email to client.
     * @param receipt_id
     * @param transactions
     * @return array
     */
    private static function email(int $receipt_id, array $transactions=[]): array {
        $exception_message = '';
        $is_email_sent = false;
        try {
            $details = Shared::fetch_client_details_for_email($receipt_id, RECEIPT);
            if($details['status'] === false) throw new Exception('Unable to Fetch Client Details.');
            else $details = $details['data'];

            // Format For Transactions
            if(count($transactions) > 0) $transactions = Utils::format_for_transaction_by_type($transactions);

            // Generate PDF
            $ret = self::generate_pdf($receipt_id, 'email', $transactions, dump_file: true);
            if($ret['status'] === false) throw new Exception($ret['message']);
            $receipt_path_to_file = $ret['data'];

            // Check for Valid File Path
            if(file_exists($receipt_path_to_file) === false) throw new Exception('Unable to Save Receipt File on Disk.');

            // Store id
            $store_id = $details['store_id'];

            // Sum Total
            // Format Number to 2 decimal places
            $sum_total = Utils::number_format($details['sum_total']);

            // Signature 
            $signature = Shared::get_store_signature($store_id);
            
            // Content Details
            $content_details = <<<EOS
            Please find herewith attached receipt amounting to a total of $$sum_total.
            <br><br><br><br>
            $signature
            EOS;

            // Send receipt in email.
            require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/email.php";

            // Send email
            $is_email_sent = Email::send(
                subject: "Receipt #$receipt_id from ". StoreDetails::STORE_DETAILS[$store_id]['email']['from_name'][SYSTEM_INIT_MODE],
                recipient_email: $details['email_id'],
                recipient_name: $details['name'],
                content: "Please notify us immediately if you are unable to see the attachment(s).$content_details",
                path_to_attachment: $receipt_path_to_file,
                file_name: "receipt_$receipt_id.pdf",
                store_id: $store_id,
                additional_email_addresses: $details['additional_email_addresses'],
                is_html: true,
            );

            if($is_email_sent['status'] === false) throw new Exception($is_email_sent['message']);
            else $is_email_sent = $is_email_sent['status'];
        }
        catch(Exception $e) {
            $is_email_sent = false;
            $exception_message = $e -> getMessage();
        }
        finally {
            if(file_exists($receipt_path_to_file)) register_shutdown_function('unlink', $receipt_path_to_file);
            return ['status' => $is_email_sent, 'message' => $exception_message];
        }
    }

    /**
     * This method will process Credit Note.
     * @param data
     * @return array
     */
    public static function process(array $data): array {
        try {
            // Result
            $result = [];

            switch($data['action']) {
                case 'create_txn': $result = self::create_receipt($data); break;
                case 'update_txn': $result = self::update_receipt($data); break;
                case 'delete_txn': $result = self::delete_receipt($data); break;
                case 'fetch_transaction_by_id': $result = self::fetch_by_id($data['transaction_id']); break;
                case 'txn_fetch_outstanding_txn_for_receipt': $result = self::fetch_outstanding_transactions_of_client($data['clientId'] ?? null); break;
                case 'txn_search': $result = Shared::search($data); break;
                case 'print': $result = self::generate_pdf($data['id'], 'print', $data['transactions']); break;
                case 'receipt_email': $result = self::email($data['id'], $data['transactions']); break;
                default: throw new Exception('Invalid Operation.');
            }
            return $result;
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

}

?>