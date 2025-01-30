<?php 
/**
 * This module will correct the Income Statements, Balance Sheet, Inventory.
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/accounts.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";


class Correct_IS_BS_InventoryV2 {

    /* Store Id */ 
    private static int $store_id = 0;

    /* DB */
    private static ?PDO $db = null;

    /**
     * This will correct the Income Statement/Balance Sheet/Inventory.
     * @param store_id
     * @param from_date 
     * @param add_keys
     * @return bool
     */
    public static function correct(int $store_id, string $from_date=null, bool $add_keys=false): bool {

        // Get DB instance 
        self::$db = get_db_instance();

        try {
            self::$db -> beginTransaction();

            // Set Store Id 
            self::$store_id = $store_id;

            // Fetch Distinct Dates
            $dates = self::fetch_distinct_dates($from_date);

            // Add Current Date
            if(!isset($dates[0])) $dates []= Utils::get_business_date($store_id);
            
            // Add keys to All Invoice and Sales Return 
            if($add_keys) {
                $are_keys_added_successfully = self::_1_add_keys_to_sales_invoice_and_sales_return();
                if($are_keys_added_successfully === false) return false;
            }

            // Execute only for Parts
            if (SYSTEM_INIT_MODE === PARTS) {
                // Get Current inventory Value.
                $ret_value = self::_2_extract_current_value_of_inventory();
                $items_information = $ret_value[0];
                $total_inventory_value = Utils::round($ret_value[1]);
            }

            // Wash
            else {
                $items_information = [];
                $total_inventory_value = 0.0;
            }

            // Adjust inventory for all existing invoices    
            $invoices = [];
            self::_3_adjust_inventory_for_all_existing_invoices($items_information, $invoices, $total_inventory_value);

            // ADDED
            $sales_returns = [];
            self::_3_adjust_inventory_for_all_existing_sales_return($items_information, $sales_returns, $total_inventory_value);
            // ADDED
            
            // Update Income Statement and Balance Sheet
            $ret_value = self::_4_update_income_statements_and_bs(
                $dates, $items_information, $invoices, $total_inventory_value
            );
            
            // // Commit to DB 
            $ret_value = self::_5_set_db($ret_value['is'], $ret_value['bs'], $items_information);

            assert_success();

            // Commit
            if(self::$db -> inTransaction()) self::$db -> commit();
            return $ret_value;
        }
        catch(Exception $e) {
            if(self::$db -> inTransaction()) self::$db -> rollBack();
            print_r($e -> getMessage());
            return false;
        }
    }

    /**
     * This method will fetch distinct dates from the database and return them.
     * @param from_date
     * @return array
     */
    private static function fetch_distinct_dates(string|null $from_date): array {

        // From a certain date.
        if(isset($from_date)) {
            $query = <<<'EOS'
            SELECT DISTINCT `date` FROM(
                (
                    SELECT DISTINCT `date` FROM sales_invoice WHERE `date` >= :from_date
                    UNION 
                    SELECT DISTINCT `date` FROM sales_return WHERE `date` >= :from_date
                    UNION 
                    SELECT DISTINCT `date` FROM receipt WHERE `date` >= :from_date
                    UNION
                    SELECT DISTINCT `date` FROM credit_note WHERE `date` >= :from_date
                    UNION
                    SELECT DISTINCT `date` FROM debit_note WHERE `date` >= :from_date
                ) AS _temp_table
            );
            EOS;
            $params = [':from_date' => $from_date];
        }
        else {
            $query = <<<'EOS'
                SELECT DISTINCT `date` FROM(
                    (
                        SELECT DISTINCT `date` FROM sales_invoice
                        UNION 
                        SELECT DISTINCT `date` FROM sales_return
                        UNION 
                        SELECT DISTINCT `date` FROM receipt
                        UNION
                        SELECT DISTINCT `date` FROM credit_note
                        UNION
                        SELECT DISTINCT `date` FROM debit_note
                    ) AS _temp_table
                ) ORDER BY `date` ASC;
            EOS;
            $params = [];
        }

        // Fetch Dates
        $statement = self::$db -> prepare($query);
        $statement -> execute($params);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Restructure Dates
        $dates = [];
        foreach($results as $date) $dates[]= $date['date'];
        return $dates;
    }

    /**
     * Adjust for Invoice.
     * 
     * @param unique_id 
     * @param invoice 
     * @param is 
     * @param bs
     * @param item_information
     */
    private static function adjust_for_invoice(string &$unique_id, array &$invoice, array &$is, array &$bs, array &$items_information) : void {

        // Get Payment Method 
        $account_code_by_payment_method = AccountsConfig::get_account_code_by_payment_method(
            $invoice['payment_method']
        );  

        // Costs
        $our_cost = 0.0;
        $sales = 0.0;

        // Items sold 
        $items_sold = json_decode($invoice['details'], true, flags: JSON_NUMERIC_CHECK);
        
        foreach($items_sold as $item) {
            $item_id = $item['itemId'];

            // Check for existence
            if(SYSTEM_INIT_MODE === PARTS) {
                if(!isset($items_information[$item_id]['quantity'])) continue;
                
                $items_information[$item_id]['quantity'] -= $item['quantity'];
                $our_cost += ($item['buyingCost'] * $item['quantity']);
            }

            // Sales Amount
            $sales += $item['amountPerItem'];

            // Special case for wash.
            if (SYSTEM_INIT_MODE === WASH) {

                // Account
                $account_id = intval($item['account']['revenue']);
                
                if($account_id != 1520) {

                    // Check for key exists
                    if(!array_key_exists($account_id, $is[$unique_id])) $is[$unique_id][$account_id] = 0;
                    if(!array_key_exists($account_id, $bs[$unique_id])) $bs[$unique_id][$account_id] = 0;

                    // Adjust the account associated 
                    $is[$unique_id][$account_id] += $item['amountPerItem'];
                    $bs[$unique_id][$account_id] += $item['amountPerItem'];
                }
            }
        }

        $total_discount = $invoice['txn_discount'];
        $total_gst_hst_tax = $invoice['gst_hst_tax'];
        $total_payable = $invoice['sum_total'];
        $total_pst_tax = $invoice['pst_tax'];

        // Adjust Income Statement and Balance Sheet
        // Inventory
        $is[$unique_id][1520] -= $our_cost;
        $bs[$unique_id][1520] -= $our_cost;

        // Sales Revenue 
        if(SYSTEM_INIT_MODE === PARTS) $is[$unique_id][4020] += $sales;

        // Add to Account associated with Payment Method
        $is[$unique_id][$account_code_by_payment_method] += $total_payable;
        $bs[$unique_id][$account_code_by_payment_method] += $total_payable;

        // Add to GST/HST tax 
        $is[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] += $total_gst_hst_tax;
        $bs[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] += $total_gst_hst_tax;

        // Add to tax 
        $is[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] += $total_pst_tax;
        $bs[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] += $total_pst_tax;

        // Add to Discount
        $is[$unique_id][AccountsConfig::TOTAL_DISCOUNT] += $total_discount;
        $bs[$unique_id][AccountsConfig::TOTAL_DISCOUNT] += $total_discount;
    }

    /**
     * Adjust for Sales Return.
     * @param unique_id 
     * @param sales_return 
     * @param is
     * @param bs
     * @param item_information
     */
    private static function adjust_for_sales_return(string &$unique_id, array &$sales_return, array &$is, array &$bs, array $items_information) : void {

        // Payment method
        $payment_method = intval($sales_return['payment_method']);

        // Sales Invoice Payment Method 
        $sales_invoice_payment_method = intval($sales_return['si_pm']);

        // Account Code
        $account_code_by_payment_method = AccountsConfig::get_account_code_by_payment_method(
            $payment_method
        );
        
        // Items
        $items = json_decode($sales_return['details'], true);

        // Calculate total inventory value to be adjusted
        $adjusted_inventory_value = 0;
        
        foreach($items as $item) {
            if(($item['returnQuantity'] ?? 0) > 0) {
                
                // Only for Parts
                if (SYSTEM_INIT_MODE === PARTS) {

                    // Check for item's existence.
                    if(!isset($items_information[$item['itemId']]['quantity'])) continue;

                    // Adjusted Inventory Value 
                    $adjusted_inventory_value = ($item['buyingCost'] * $item['returnQuantity']);
                    
                    // Add to inventory 
                    $items_information[$item['itemId']]['quantity'] += $item['returnQuantity'];

                    // Add to bs
                    $bs[$unique_id][1520] += $adjusted_inventory_value;

                    // Add to inventory To Income Statement
                    $is[$unique_id][1520] += $adjusted_inventory_value;
                }
    
                // Deduct Discount
                $total_discount = (($item['basePrice'] * $item['discountRate'])/100);
                $is[$unique_id][AccountsConfig::TOTAL_DISCOUNT] -= $total_discount;
                $bs[$unique_id][AccountsConfig::TOTAL_DISCOUNT] -= $total_discount;
                
                // Add to sales return 
                $is[$unique_id][AccountsConfig::SALES_RETURN] += ($item['amountPerItem']);
            }
        }

        // Adjust Account associated with payment method
        if($payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {

            // Just deduct the amount from Account Receivables
            $is[$unique_id][AccountsConfig::ACCOUNTS_RECEIVABLE] -= $sales_return['sum_total'];
            $bs[$unique_id][AccountsConfig::ACCOUNTS_RECEIVABLE] -= $sales_return['sum_total'];
        }
        // If this block gets executed, it means that the Sales Invoice was not made in Pay Later.
        else {
            $is[$unique_id][$account_code_by_payment_method] -= $sales_return['sum_total'];
            $bs[$unique_id][$account_code_by_payment_method] -= $sales_return['sum_total'];
        }
    
        // Deduct from GST/HST tax 
        $is[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] -= $sales_return['gst_hst_tax'];
        $bs[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] -= $sales_return['gst_hst_tax'];

        // Deduct from PST tax 
        $is[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] -= $sales_return['pst_tax'];
        $bs[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] -= $sales_return['pst_tax'];
    }

    /**
     * This method will adjust for receipt.
     * @param unique_id
     * @param receipt
     * @param is
     * @param bs
     */
    private static function adjust_for_receipt(string &$unique_id, array &$receipt, array &$is, array &$bs) : void {

        // Deduct from Pay Later 
        $total_amount = ($receipt['sum_total'] + $receipt['total_discount']);
        $is[$unique_id][1200] -= $total_amount;
        $bs[$unique_id][1200] -= $total_amount;

        // Add to discount
        $is[$unique_id][AccountsConfig::TOTAL_DISCOUNT] += $receipt['total_discount'];
        $bs[$unique_id][AccountsConfig::TOTAL_DISCOUNT] += $receipt['total_discount'];

        // Early Payment Discount
        $is[$unique_id][4240] += $receipt['total_discount'];
        $bs[$unique_id][4240] += $receipt['total_discount'];
    
        // Add to payment account
        $receipt_payment_account = AccountsConfig::get_account_code_by_payment_method(
            $receipt['payment_method']
        );
        $bs[$unique_id][$receipt_payment_account] += $receipt['sum_total'];
    }


    /**
     * This method will adjust for credit note.
     * @param unique_id
     * @param credit_note
     * @param bs
     */
    private static function adjust_for_credit_note(string &$unique_id, array &$credit_note, array &$bs) : void {
        $bs[$unique_id][AccountsConfig::ACCOUNTS_RECEIVABLE] -= $credit_note['sum_total'];
        $bs[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] -= $credit_note['gst_hst_tax'];
        $bs[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] -= $credit_note['pst_tax'];
        $bs[$unique_id][AccountsConfig::TOTAL_DISCOUNT] -= $credit_note['txn_discount'];
    }
    
    /**
     * This method will adjust for debit note.
     * @param unique_id
     * @param debit_note
     * @param bs
     */
    private static function adjust_for_debit_note(string &$unique_id, array &$debit_note, array &$bs) : void {
        $bs[$unique_id][AccountsConfig::ACCOUNTS_RECEIVABLE] += $debit_note['sum_total'];
        $bs[$unique_id][AccountsConfig::GST_HST_CHARGED_ON_SALE] += $debit_note['gst_hst_tax'];
        $bs[$unique_id][AccountsConfig::PST_CHARGED_ON_SALE] += $debit_note['pst_tax'];
        $bs[$unique_id][AccountsConfig::TOTAL_DISCOUNT] += $debit_note['txn_discount'];
    }     

    /**
     * This method will add the Base Price Key in the items JSON.
     * @return bool
     */
    private static function _1_add_keys_to_sales_invoice_and_sales_return(): bool {

        // DB instance
        $db = get_db_instance();
        try {
            $tables = ['sales_invoice', 'sales_return'];
            $db -> beginTransaction();
            foreach($tables as $table_name) {

                // Fetch all invoices
                $statement_fetch_all_invoices = $db -> prepare("SELECT * FROM $table_name WHERE store_id=:store_id;");
                $statement_fetch_all_invoices -> execute([':store_id' => self::$store_id]);
                $sales_invoices = $statement_fetch_all_invoices -> fetchAll(PDO::FETCH_ASSOC);

                // Save Invoice items
                $statement_save_all_items = $db -> prepare("UPDATE $table_name SET items = :items WHERE id=:id;");
                foreach($sales_invoices as $invoices) {
                    $items = json_decode($invoices['items'], true);
                    $items_count = count($items);
                    for($i = 0; $i < $items_count ; ++$i) {
                        if(!array_key_exists('our_buying_price', $items[$i])) {
                            $items[$i]['our_buying_price'] = $items[$i]['base_price'];
                        }
                    }
                
                    // Update Invoice items
                    $is_successful = $statement_save_all_items -> execute([
                        ':id' => $invoices['id'],
                        ':items' => json_encode($items)
                    ]);

                    if(!$is_successful  && $statement_save_all_items -> rowCount() === 0) {
                        throw new Exception('Correct IS BS Inventory > add_keys_to_sales_invoice_and_sales_return: Unable to Update '. $table_name);
                    }
                }
            }
            $db -> commit();
            return true;
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            print_r($e -> getMessage());
            return false;
        }
    } 

    /**
     * Extract current inventory information.
     * @return array 
     */
    private static function _2_extract_current_value_of_inventory(): array {
        $db = get_db_instance();

        $query = <<<EOS
        SELECT 
            it.id as item_id,
            inv.`quantity` as quantity,
            it.prices
        FROM 
            inventory as inv
        LEFT JOIN 
            items as it
        ON 
            it.id = inv.item_id
        WHERE 
            inv.store_id = :_location;
        EOS;
        $statement = $db -> prepare($query);
        $statement -> execute([
            ':_location' => self::$store_id
        ]);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $total_inventory_value = 0;
        foreach($records as $record) {
            $prices = json_decode($record['prices'], true, flags: JSON_NUMERIC_CHECK)[self::$store_id];
            
            $price_per_item = $prices['buyingCost'];;
            // if($web_price > 0.0000) $price_per_item = $web_price;
            // else $price_per_item = $regular_price;
            // $price_per_item = $web_price;

            if(!array_key_exists($record['item_id'], $items)) $items[$record['item_id']] = [];
            $items[$record['item_id']]['value'] = $record['quantity'] * $price_per_item;
            $items[$record['item_id']]['price_per_item'] = $price_per_item;
            $items[$record['item_id']]['quantity'] = $record['quantity'];

            $total_inventory_value += $items[$record['item_id']]['value'];
        }
        return [$items, $total_inventory_value];
    }

    /**
     * This method will adjust inventory for all existing invoices.
     * @param items_information
     * @param invoices
     * @param total_inventory_value
     * @return array
     */
    private static function _3_adjust_inventory_for_all_existing_invoices(array &$items_information, array &$invoices, float &$total_inventory_value) {
        $db = get_db_instance();
        $query = <<<EOS
        SELECT 
            *
        FROM 
            sales_invoice
        WHERE 
            store_id = :store_id
        ORDER BY 
            `date` ASC;
        EOS;

        $statement = $db -> prepare($query);
        $statement -> execute([':store_id' => self::$store_id]);
        $invoices = $statement -> fetchAll(PDO::FETCH_ASSOC);
        
        foreach($invoices as $invoice) {
            $items = json_decode($invoice['details'], true, flags: JSON_NUMERIC_CHECK);
            
            foreach($items as $item) {
                $item_id = $item['itemId'];
                $quantity = 0;
                $quantity = $item['quantity'];
                
                // Check whether the item exists 
                if(!isset($items_information[$item_id]['price_per_item'])) continue;

                // Add to total inventory value
                $temp = (
                    $items_information[$item_id]['price_per_item']
                    *
                    $quantity
                );
                $total_inventory_value += $temp;
                $items_information[$item_id]['quantity'] += $quantity;
                $items_information[$item_id]['value'] += $temp;
            }
        }
    }

    /**
     * This method will adjust inventory for all existing sales_return.
     * @param items_information
     * @param invoices
     * @param total_inventory_value
     * @return array
     */
    private static function _3_adjust_inventory_for_all_existing_sales_return(array &$items_information, array &$sales_return, float &$total_inventory_value) {
        $query = <<<EOS
        SELECT 
            *
        FROM 
            sales_return
        WHERE 
            store_id = :store_id
        ORDER BY 
            `date` ASC;
        EOS;

        $statement = self::$db -> prepare($query);
        $statement -> execute([':store_id' => self::$store_id]);
        $invoices = $statement -> fetchAll(PDO::FETCH_ASSOC);
        
        foreach($invoices as $invoice) {
            $items = json_decode($invoice['details'], true);
            
            foreach($items as $item) {
                $item_id = $item['itemId'];
                $quantity = $item['returnQuantity'] ?? 0;

                if($quantity == 0) continue;

                // Check whether the item exists 
                if(!isset($items_information[$item_id]['price_per_item'])) continue;

                // Deduct from total inventory value
                $temp = (
                    $items_information[$item_id]['price_per_item']
                    *
                    $quantity
                );
                $total_inventory_value -= $temp;
                $items_information[$item_id]['quantity'] -= $quantity;
                $items_information[$item_id]['value'] -= $temp;
            }
        }
    }

    /**
     * This will update income statements and Balance Sheet.
     * @param dates
     * @param items_information
     * @return array 
     */
    private static function _4_update_income_statements_and_bs(array &$dates, array &$items_information, array &$invoices, float &$total_inventory_value) {
        
        // Dates count
        $date_count = count($dates);
        
        // Income statement and balance sheet
        $is = [];
        $bs = [];
        try {
            for($i = 0; $i < $date_count; ++$i) {
                // $unique_id = Utils::get_unique_id_from_date(self::$store_id, $dates[$i]);
                $unique_id = $dates[$i];
                
                // Income Statement
                if(!array_key_exists($unique_id, $is)) {
                    $is[$unique_id] = AccountsConfig::ACCOUNTS;
                }
        
                // Balance Sheet
                if(!array_key_exists($unique_id, $bs)) {
                    if($i === 0) {
                        $bs[$unique_id] = AccountsConfig::ACCOUNTS;
                        $bs[$unique_id][1520] = $total_inventory_value;
                    }
                    else {
                        $previous_date_unique_id = $dates[$i-1];
                        $bs[$unique_id] = $bs[$previous_date_unique_id];
                    }
                }
                
                // ***** INVOICES ******
                $statement = self::$db -> prepare('SELECT * FROM sales_invoice WHERE `date`= :invoice_date AND store_id = :store_id;');
                $statement -> execute([':invoice_date' => $dates[$i], ':store_id' => self::$store_id]);
                $invoices = $statement -> fetchAll(PDO::FETCH_ASSOC);
                
                foreach($invoices as $invoice) {
                    // Handle Invoice.
                    self::adjust_for_invoice(
                        $unique_id,
                        $invoice,
                        $is, 
                        $bs,
                        $items_information
                    );
                }
        
                // ***** SALES RETURN *******
                $statement = self::$db -> prepare('SELECT sr.*, si.payment_method AS si_pm FROM sales_return AS sr LEFT JOIN sales_invoice AS si ON sr.sales_invoice_id = si.id WHERE sr.`date` = :return_date AND sr.store_id = :store_id;');
                $statement -> execute([':return_date' => $dates[$i], ':store_id' => self::$store_id]);
                $sales_returns = $statement -> fetchAll(PDO::FETCH_ASSOC);
                foreach($sales_returns as $sales_return) {
        
                    // Adjust for Sales Return
                    self::adjust_for_sales_return(
                        $unique_id, 
                        $sales_return, 
                        $is, 
                        $bs, 
                        $items_information
                    );
                }
                
                // ****** RECEIPTS *****
                $statement = self::$db -> prepare('SELECT * FROM receipt WHERE `date`=:receipt_date AND store_id=:store_id AND do_conceal = 0;');
                $statement -> execute([':receipt_date' => $dates[$i], ':store_id' => self::$store_id]);
                $receipts = $statement -> fetchAll(PDO::FETCH_ASSOC);
                foreach($receipts as $receipt) {
                    
                    // Adjust for receipt
                    self::adjust_for_receipt(
                        $unique_id,
                        $receipt,
                        $is,
                        $bs,
                        $items_information
                    );
                }

                // ****** CREDIT NOTE ******
                $statement = self::$db -> prepare('SELECT * FROM credit_note WHERE `date`=:credit_note_date AND store_id=:store_id;');
                $statement -> execute([':credit_note_date' => $dates[$i], ':store_id' => self::$store_id]);
                $credit_notes = $statement -> fetchAll(PDO::FETCH_ASSOC);
                foreach($credit_notes as $credit_note) {
                    
                    // Adjust for credit note
                    self::adjust_for_credit_note(
                        $unique_id,
                        $credit_note,
                        $bs
                    );
                }
                
                // ****** DEBIT NOTE ******
                $statement = self::$db -> prepare('SELECT * FROM debit_note WHERE `date`=:debit_note_date AND store_id=:store_id;');
                $statement -> execute([':debit_note_date' => $dates[$i], ':store_id' => self::$store_id]);
                $debit_notes = $statement -> fetchAll(PDO::FETCH_ASSOC);
                foreach($debit_notes as $debit_note) {
                    
                    // Adjust for debit note
                    self::adjust_for_debit_note(
                        $unique_id,
                        $debit_note,
                        $bs
                    );
                }
            }
        
            // Update Inventory
            $keys = array_keys($items_information);
            $keys_count = count($keys);
            $total_value_of_inventory = 0;
            for($i = 0 ; $i < $keys_count ; ++$i) {
                $items_information[$keys[$i]]['value'] = 
                    $items_information[$keys[$i]]['quantity']
                    * 
                    $items_information[$keys[$i]]['price_per_item'];
        
                    $total_value_of_inventory += 
                    $items_information[$keys[$i]]['quantity']
                    * 
                    $items_information[$keys[$i]]['price_per_item'];
            }
            return ['is'=> $is, 'bs' => $bs];
        }
        catch(Exception $e) {
            print_r($e -> getMessage());
            throw new Exception($e -> getMessage());
        }
    }

    /**
     * This method will update the Income Statement/Balance Sheet in the DB.
     * @param is 
     * @param bs 
     * @param items_information
     */
    private static function _5_set_db(&$is, &$bs, &$items_information) {
    
        $is_keys = array_keys($is);
        $bs_keys = array_keys($bs);
        $item_keys = array_keys($items_information);

        // Base Statement
        $base_statement = json_encode(AccountsConfig::ACCOUNTS);

        try {
            
            // Check for Statement existence
            $statement_check_is = self::$db -> prepare(<<<'EOS'
            SELECT 
                id 
            FROM 
                income_statement
            WHERE 
                `date` = :date
            AND 
                store_id = :store_id;
            EOS);

            // Income Statement
            $statement_is = self::$db -> prepare(<<<'EOS'
            UPDATE 
                income_statement
            SET 
                `statement` = :statement 
            WHERE 
                `date` = :date
            AND 
                store_id = :store_id;
            EOS);

            foreach($is_keys as $is_key) {

                // Check
                $statement_check_is -> execute([':date' => $is_key, ':store_id' => self::$store_id]); 
                $records = $statement_check_is -> fetchAll(PDO::FETCH_ASSOC);
                if(!isset($records[0])) {
                    // Insert new Record
                    $statement = self::$db -> prepare(<<<'EOS'
                    INSERT INTO income_statement
                    (
                        `statement`,
                        `date`,
                        store_id
                    )
                    VALUES
                    (
                        :statement,
                        :date,
                        :store_id
                    );
                    EOS);
                    $statement -> execute([
                        ':statement' => $base_statement,
                        ':date' => $is_key,
                        ':store_id' => self::$store_id,
                    ]);

                    if(self::$db -> lastInsertId() === false) {
                        throw new Exception('Unable to create Income Statement.');
                    }
                }

                $is_successful = $statement_is -> execute([
                    ':statement' => json_encode($is[$is_key]),
                    ':date' => $is_key,
                    ':store_id' => self::$store_id,
                ]);
    
                if(!$is_successful && $statement_is -> rowCount() === 0) {
                    throw new Exception('Unable to update income statement.');
                }
            }
            
            // Check for Statement existence
            $statement_check_bs = self::$db -> prepare(<<<'EOS'
            SELECT 
                id 
            FROM 
                balance_sheet 
            WHERE 
                `date` = :date
            AND
                store_id = :store_id; 
            EOS);

            // Balance Sheet
            $statement_bs = self::$db -> prepare(<<<'EOS'
            UPDATE 
                balance_sheet 
            SET
                `statement` = :statement 
            WHERE 
                `date` = :date
            AND
                store_id = :store_id;
            EOS);
            
            foreach($bs_keys as $bs_key) {

                // Check
                $statement_check_bs -> execute([':date' => $bs_key, ':store_id' => self::$store_id]); 
                $records = $statement_check_bs -> fetchAll(PDO::FETCH_ASSOC);
                if(!isset($records[0])) {
                    // Insert new Record
                    $statement = self::$db -> prepare(<<<'EOS'
                    INSERT INTO balance_sheet
                    (
                        `statement`,
                        `date`,
                        store_id
                    )
                    VALUES
                    (
                        :statement,
                        :date,
                        :store_id
                    );
                    EOS);
                    $statement -> execute([
                        ':statement' => $base_statement,
                        ':date' => $bs_key,
                        ':store_id' => self::$store_id,
                    ]);

                    if(self::$db -> lastInsertId() === false) {
                        throw new Exception('Unable to create Balance Sheet');
                    }
                }

                $params = [
                    ':statement' => json_encode($bs[$bs_key]),
                    ':date' => $bs_key,
                    ':store_id' => self::$store_id,
                ];
                $is_successful = $statement_bs -> execute($params);
                if(!$is_successful && $statement_bs -> rowCount() === 0) {
                    throw new Exception('Unable to update balance sheet.');
                }
            }
    
            // Inventory
            // $statement_it = self::$db -> prepare(<<<EOS
            // UPDATE 
            //     inventory 
            // SET 
            //     `quantity` = :quantity
            // WHERE 
            //     item_id = :item_id
            // AND
            //     store_id = :store_id;
            // EOS);
    
            // foreach($item_keys as $item_key) {
            //     $is_successful = $statement_it -> execute([
            //         ':quantity' => $items_information[$item_key]['quantity'],
            //         ':item_id' => $item_key,
            //         ':store_id' => self::$store_id,
            //     ]);
    
            //     if(!$is_successful && $statement_it -> rowCount() === 0) {
            //         throw new Exception('Unable to update inventory value.');
            //     }
            // }
            return true;
        }
        catch(Exception $e) {
            throw new Exception($e -> getMessage());
        }
    }
}
?>