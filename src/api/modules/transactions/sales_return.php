<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";

class SalesReturn {

    // Items Keys in JSON
    private const ITEM_KEYS = [
        'itemId',
        'amountPerItem',
        'basePrice',
        'discountRate',
        'pricePerItem',
        'quantity',
        'gstHSTTaxRate',
        'pstTaxRate',
        'category',
        'returnQuantity',
        'restockingRate',
    ];

    /* Max Restrocking Rate */
    public const MAX_RESTOCKING_RATE = 20;

    // Flag
    private static $is_self_client = false;

    /**
     * This method will validate the items details for validity.
     * @param items
     * @param disable_federal_taxes
     * @param disable_provincial_taxes
     * @return array
     */
    public static function validate_items_details(array $items, int $disable_federal_taxes, int $disable_provincial_taxes) : array {

        // Validate Item Count
        if(count($items) < 1) return ['status' => false, 'message' => 'Invalid Items Count.'];

        // Store Id
        $store_id = $_SESSION['store_id'];

        // Validate Item fields
        foreach($items as $item) {

            // Identifier
            $identifier = $item['identifier'];

            // Set Default Return Quantity
            if(isset($item['returnQuantity']) === false) $item['returnQuantity'] = 0;

            // Process Only Valid Items
            if(($item['returnQuantity'] ?? 0) < 0) return ['status' => false, 'message' => 'Invalid Return Quantity for: '. $identifier];

            // Check for Return Quantity when Item is Back Order.
            if($item['returnQuantity'] > 0 && $item['isBackOrder'] === 1) return ['status' => false, 'message' => 'Cannot Return Back Order Item.'];

            // Restocking Rate
            if(is_numeric($item['restockingRate'] ?? null) === false) $item['restockingRate'] = 0;

            // Uninitialized Item Found
            if(isset($item['itemId']) === false || is_null($item['itemId'])) return ['status' => false, 'message' => 'Uninitialized Item.'];
            foreach(self::ITEM_KEYS as $key) {
                if(!isset($item[$key])) return ['status' => false, 'message' => "$key not set for $identifier."];
                if(!is_numeric($item[$key])) return ['status' => false, 'message' => "$key not numeric for $identifier."];
                if(floatval($item[$key]) < 0) return ['status' => false, 'message' => "$key less than 0 for $identifier."];
            }

            $keys = ['basePrice', 'amountPerItem'];
            if(SYSTEM_INIT_MODE === PARTS) $keys []= 'buyingCost';
            foreach($keys as $key) if(floatval($item[$key]) < 0) return ['status' => false, 'message' => "$key less than or equal to 0 for $identifier."];

            // Check for GST/HST Tax Rate
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false && $disable_federal_taxes === 0 && floatval($item['gstHSTTaxRate']) !== FEDERAL_TAX_RATE) return ['status' => false, 'message' => "GSTHSTTaxRate less than or equal to 0 for $identifier."];

            // Check for PST if applicable
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false && $disable_provincial_taxes === 0 && (StoreDetails::STORE_DETAILS[$store_id]['pst_tax_rate'] > 0) && floatval($item['pstTaxRate']) !== StoreDetails::STORE_DETAILS[$store_id]['pst_tax_rate']) {
                return ['status' => false, 'message' => 'PST Tax Invalid.'];
            }

            // Check for Valid Selling Price
            // Check for Price > than Base Price
            if($item['pricePerItem'] < $item['buyingCost']) {
                throw new Exception('Selling price Cannot be less than the Base Price for: '. $identifier);
            }
        }
        return ['status' => true];
    }

    /**
     * This method will calculate amount for Invoice.
     * @param items
     * @param disable_federal_taxes
     * @param disable_provincial_tax
     * @return array
     */
    public static function calculate_amount(array $items, int $disable_federal_taxes, int $disable_provincial_taxes) : array {

        // Calculate Amounts 
        $sub_total = 0;
        $gst_hst_tax = 0;
        $pst_tax = 0;
        $txn_discount = 0;
        $cogr = 0;
        $total_restocking_fees = 0;

        // Select Tax Rate
        $federal_tax_rate = $disable_federal_taxes ? 0 : GST_HST_TAX_RATE;
        $provincial_tax_rate = $disable_provincial_taxes ? 0 : PROVINCIAL_TAX_RATE;
        
        foreach($items as $item) { 

            // Process only valid items
            if(($item['returnQuantity'] ?? 0) <= 0) continue;

            // Amount Per item
            $amount_per_item = $item['amountPerItem'];

            // Restocking Rate
            $restocking_rate = is_numeric($item['restockingRate'] ?? null) ? floatval($item['restockingRate']) : 0;
            if(SYSTEM_INIT_MODE === WASH && $restocking_rate != 0) throw new Exception('Restocking Rate Invalid for Wash Mode.');
            else if($restocking_rate < 0) throw new Exception('Restocking Rate cannot be negative.');
            else if($restocking_rate > self::MAX_RESTOCKING_RATE) throw new Exception('Restocking Rate cannot be more than '. self::MAX_RESTOCKING_RATE.'%');

            // Adjust for Restocking fees
            $restocking_fees = $restocking_rate > 0 ? ($amount_per_item * $restocking_rate) / 100: 0;

            // Add to total restocking fees
            $total_restocking_fees += $restocking_fees;
            
            $sub_total += $amount_per_item;

            // Add Taxes
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false) {
                $pst_tax += (($item['amountPerItem'] * $provincial_tax_rate) / 100);
                $gst_hst_tax += (($item['amountPerItem'] * $federal_tax_rate) / 100);
            }
            $base_price = $item['basePrice'];
            $return_quantity = $item['returnQuantity'];
            $txn_discount += ((($base_price * $return_quantity) * $item['discountRate']) / 100);
            $cogr += ($item['buyingCost'] * $return_quantity);
        }

        // Add Taxes to Sub total 
        $sum_total = $sub_total + $gst_hst_tax + $pst_tax;

        return [
            'sumTotal' => Utils::round($sum_total),
            'subTotal' => Utils::round($sub_total),
            'pstTax' => Utils::round($pst_tax),
            'gstHSTTax' => Utils::round($gst_hst_tax),
            'txnDiscount' => Utils::round($txn_discount),
            'cogr' => Utils::round($cogr),
            'restockingFees' => Utils::round($total_restocking_fees),
        ];
    }

    /**
     * This method will fetch sales invoice details.
     * @param sales_invoice_id
     * @param db
     * @return array
     */
    private static function fetch_sales_invoice(int $sales_invoice_id, PDO &$db): array {
        $statement = $db -> prepare('SELECT `date`, client_id, payment_method, details, early_payment_discount, early_payment_paid_within_days, net_amount_due_within_days, modified FROM sales_invoice WHERE id = :id;');
        $statement -> execute([':id' => $sales_invoice_id]);
        $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(!isset($record[0])) throw new Exception('Unable to Fetch Sales Invoice.');

        // Check Details
        return [
            'date' => $record[0]['date'],
            'client_id' => intval($record[0]['client_id']),
            'payment_method' => intval($record[0]['payment_method']),
            'details' => json_decode($record[0]['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            'early_payment_discount' => floatval($record[0]['early_payment_discount']),
            'early_payment_paid_within_days' => intval($record[0]['early_payment_paid_within_days']),
            'net_amount_due_within_days' => intval($record[0]['net_amount_due_within_days']),
            'modified' => $record[0]['modified'],
        ];
    }
    
    /**
     * This method will get sales invoice items frequency.
     * @param details
     * @return array
     */
    private static function get_sales_invoice_items_frequency(array $details): array {
        $items = [];
        $item_identifiers = [];
        foreach($details as $detail) {
            $items []= $detail['quantity'];
            $item_identifiers[]= $detail['identifier'];
        }
        return [$items, $item_identifiers];
    }

    /**
     * This method will check sales invoice details.
     * @param sales_invoice_details
     * @param details
     * @param txn_date
     */
    private static function check_sales_invoice_details(array $sales_invoice_details, array $details, string $txn_date): void {
        // Check for Valid Client
        if($sales_invoice_details['client_id'] !== $details['clientDetails']['id']) throw new Exception('Invoice Does not Belong to this client.');

        // Check for Last Modified Timestamp for Sales Invoice.
        if($sales_invoice_details['modified'] !== $details['selectedSalesInvoiceLastModifiedTimestamp']) throw new Exception('Cannot Proceed with Stale Copy of Sales Invoice.');

        // Check for Valid Payment Method
        $sales_invoice_payment_method = $sales_invoice_details['payment_method'];
        $sales_return_payment_method = $details['paymentMethod'];

        if($sales_invoice_payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later'] && $sales_return_payment_method !== PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {
            throw new Exception('Payment Method can only be Pay Later for this transaction.');
        }

        // Check for Other Payment Method
        if ($sales_invoice_payment_method !== PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {

            // Valid Payment Methods
            $valid_payment_methods = [PaymentMethod::MODES_OF_PAYMENT['Pay Later'], $sales_invoice_payment_method];
            $valid_payment_methods_string = [
                PaymentMethod::MODES_OF_PAYMENT[PaymentMethod::MODES_OF_PAYMENT['Pay Later']],
                PaymentMethod::MODES_OF_PAYMENT[$sales_invoice_payment_method],
            ];

            if(!in_array($sales_return_payment_method, $valid_payment_methods)) {
                $temp = '';
                $delimiter = ', ';
                foreach($valid_payment_methods_string as $pm) $temp .= ($pm. $delimiter);
                throw new Exception('Only these payment methods are valid: '. trim($temp, $delimiter).'.');
            }
        }

        /* Check for Date */
        if($txn_date < $sales_invoice_details['date']) throw new Exception('Sales Return Date Cannot be before the Sales Invoice\'s date.');
    }

    /**
     * This method will check for valid details.
     * @param sales_invoice_id
     * @param details
     * @param tnx_date
     * @param db
     * @param sales_return_id_to_skip
     * @return int 
     */
    private static function check_valid_details_for_sales_returns(int $sales_invoice_id, array $details, string $txn_date, PDO &$db, ?int $sales_return_id_to_skip=null): int {
        $returned_items_quantity = self::get_existing_sales_returns_items_quantity(
            $sales_invoice_id, 
            $details['details'], 
            $db, 
            $sales_return_id_to_skip
        );

        // Fetch Sales Invoice Details
        $sales_invoice_details = self::fetch_sales_invoice($sales_invoice_id, $db);

        // Check whether the sales invoice belongs to the same client.
        if(intval($details['clientDetails']['id']) !== $sales_invoice_details['client_id']) throw new Exception('Sales Invoice Does not belong to the client.');

        // Check Sales Invoice Details
        self::check_sales_invoice_details($sales_invoice_details, $details, $txn_date);

        // Fetch Sales Invoice Frequency
        $ret = self::get_sales_invoice_items_frequency($sales_invoice_details['details']);
        $sales_invoice_quantity = $ret[0];
        $sales_invoice_items_identifier = $ret[1];

        // Check for Invalid Quantity
        $count = count($returned_items_quantity);
        for($i = 0 ; $i < $count; ++$i) {
            if($returned_items_quantity[$i] > $sales_invoice_quantity[$i]) {
                throw new Exception('Returning Quantity more than purchased for item: '. $sales_invoice_items_identifier[$i]);
            }
        }
        return $sales_invoice_details['payment_method'];  
    }

     /**
     * This method will fetch sales returns by sales invoice id.
     * @param sales_invoice_id
     * @param db
     * @return array
     */
    public static function fetch_sales_returns_by_sales_invoice_id(int $sales_invoice_id, PDO &$db): array {
        $statement = $db -> prepare('SELECT id, details FROM sales_return WHERE sales_invoice_id = :sales_invoice_id;');
        $statement -> execute([':sales_invoice_id' => $sales_invoice_id]);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $sales_returns = [];
        foreach($records as $record) $sales_returns[$record['id']] = $record;
        return $sales_returns;
    }

    /**
     * This method will fetch all sales return quantity.
     * @param invoice_id
     * @param new_details
     * @param db
     * @param sales_return_id_to_skip
     * @return array
     */
    private static function get_existing_sales_returns_items_quantity(int $sales_invoice_id, array $new_details, PDO &$db, ?int $sales_return_id_to_skip=null): array {
        $sales_returns = self::fetch_sales_returns_by_sales_invoice_id($sales_invoice_id, $db);

        // Existing Sales Return Quantity
        $returned_quantity = [];

        // Add Latest Items Returned.
        foreach($new_details as $item) $returned_quantity[]= ($item['returnQuantity'] ?? 0);

        if(count($sales_returns) > 0) {
            
            // Now Add Quantities in All Sales Returns
            foreach($sales_returns as $sr) {
                if(is_numeric($sales_return_id_to_skip) && $sales_return_id_to_skip === $sr['id']) continue;
                $details = json_decode($sr['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $index = 0;
                foreach($details as $item) $returned_quantity[$index++] += ($item['returnQuantity'] ?? 0);
            }
        }
        return $returned_quantity;
    }

    /**
     * This method will validate details. If valid validated details will be returned, else error message will be returned.
     * @param data
     * @return array
     */
    private static function validate_details(array $data): array {
        // Client Id 
        $client_id = $data['clientDetails']['id'] ?? null;

        // Check for valid id 
        if(!Validate::is_numeric($client_id)) {
            throw new Exception('Cannot Process Sales Return for Invalid Customer.');
        }

        // Sales Rep Id
        if($data['salesRepId'] === 0) throw new Exception('Please select Sales Representative.');

        // Check for Valid Payment Method 
        $payment_method = Shared::check_for_valid_payment_method_for_sales_invoice_and_return(
            $data['paymentMethod']
        );

        // Is Pay Later
        $is_pay_later = $payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later'];
        
        // Check for Invalid Payment method
        if($client_id === ONE_TIME_CUSTOMER_ID && $is_pay_later) {
            throw new Exception('Invalid Payment Method for One time customer.');
        } 

        // Validate Store
        $store_id = intval($_SESSION['store_id']);
        if(key_exists($store_id, StoreDetails::STORE_DETAILS) === false) throw new Exception('Store is Invalid.');
        if($store_id !== intval($data['storeId'])) throw new Exception('Store does not match with current session.');

        // Transaction Date
        $transaction_date = Utils::get_YYYY_mm_dd(
            Utils::convert_utc_str_timestamp_to_localtime($data['txnDate'], $store_id),
        );
        if($transaction_date === null) throw new Exception('Invalid Date.');

        // Validate New Date(if any)
        Shared::validate_new_date_of_transaction($data, $transaction_date);

        // Check for transaction date
        /* Make an Exception for J.LOEWEN MECHANICAL LTD */
        if($client_id !== 14376) {
            if(isset($data['initial']['txnDate'])) Shared::check_transaction_older_than_2_days(
                $data['initial']['txnDate'], 
                $store_id,
            );
        }

        // Disable Federal Taxes
        $disable_federal_taxes = $data['disableFederalTaxes'] ?? null;
        $disable_provincial_taxes = $data['disableProvincialTaxes'] ?? null;

        // Check for Disabled Taxes
        if($disable_federal_taxes !== $data['clientDetails']['disableFederalTaxes']) {
            throw new Exception('Federal Tax Status cannot be changed for this sales return as it is different from the associated sales invoice.');
        }

        if($disable_provincial_taxes !== $data['clientDetails']['disableProvincialTaxes']) {
            throw new Exception('Provincial Tax Status cannot be changed for this sales return as it is different from the associated sales invoice.');
        }

        // Validate Items Information
        $valid_ret_value = self::validate_items_details(
            $data['details'],
            $disable_federal_taxes,
            $disable_provincial_taxes,
        );
        if($valid_ret_value['status'] === false) throw new Exception($valid_ret_value['message']);
        
        // Calculate Amounts
        $calculated_amount = self::calculate_amount(
            $data['details'], 
            $disable_federal_taxes,
            $disable_provincial_taxes
        );
        $sum_total = $calculated_amount['sumTotal'];
        $sub_total = $calculated_amount['subTotal'];
        $pst_tax = $calculated_amount['pstTax'];
        $gst_hst_tax = $calculated_amount['gstHSTTax'];
        $txn_discount = $calculated_amount['txnDiscount'];
        $cogr = $calculated_amount['cogr'];
        $restocking_fees = $calculated_amount['restockingFees'];

        // Restocking Fees
        if(is_numeric($restocking_fees) === false) throw new Exception('Invalid Restocking Fees.');
        if($restocking_fees < 0) throw new Exception('Restocking Fees cannot be negative.');

        // Sum Total
        if(!is_numeric($sum_total)) throw new Exception('Sum Total should be numeric.');
        if($sum_total <= 0) throw new Exception('Sum Total cannot be zero or negative.');

        // Sub Total
        if(!is_numeric($sub_total)) throw new Exception('Sub Total should be numeric.');
        if($sub_total <= 0) throw new Exception('Sub Total cannot be zero or negative.');

        // PST Taxes 
        if(!is_numeric($pst_tax)) throw new Exception('PST Tax should be numeric.');
        if($data['clientDetails']['disableProvincialTaxes'] === 0 && PROVINCIAL_TAX_RATE > 0 && $pst_tax <= 0) throw new Exception('PST Tax cannot be zero or negative.');

        // GST/HST Taxes
        if(!is_numeric($gst_hst_tax)) throw new Exception('GST/HST Tax should be numeric.');
        if($data['clientDetails']['disableFederalTaxes'] === 0 && $gst_hst_tax <= 0) throw new Exception('GST/HST Tax cannot be zero or negative.');

        // Total Discount
        if(!is_numeric($txn_discount)) throw new Exception('Total Discount should be numeric.');
        if($txn_discount < 0) throw new Exception('Total Discount cannot be zero or negative.');

        // Notes 
        $notes = isset($data['notes']) ? trim(ucfirst($data['notes'])) : '';

        // Return data
        return [
            'client_id' => $client_id,
            'payment_method' => $payment_method,
            'is_pay_later' => $is_pay_later,
            'store_id' => $store_id,
            'txn_date' => $transaction_date,
            'sum_total' => $sum_total,
            'sub_total' => $sub_total,
            'pst_tax' => $pst_tax,
            'gst_hst_tax' => $gst_hst_tax,
            'txn_discount' => $txn_discount,
            'cogr' => $cogr,
            'po' => trim($data['po'] ?? ''),
            'unit_no' => trim($data['unitNo'] ?? ''),
            'vin' => trim($data['vin'] ?? ''),
            'notes' => $notes,
            'restocking_fees' => $restocking_fees,
        ];
    }

    /**
     * Adjust Inventory
     * 
     * @param details
     * @param items_information
     * @param statement_adjust_inventory
     * @param store_id
     * @param op
     * @param affected_accounts
     * @throws Exception
     * @return void
     */
    private static function adjust_inventory(array $details, PDOStatement &$statement_adjust_inventory, int $store_id, string $op, array &$affected_accounts) : void {

        $details_count = count($details);
        for($index = 0; $index < $details_count; ++$index) {
            
            // Item Id
            $item_id = $details[$index]['itemId'];

            // Revenue
            $revenue = $details[$index]['amountPerItem'];
            $account_revenue = $details[$index]['account']['revenue'];

            // Inventory
            if(intval($details[$index]['category']) === CATEGORY_INVENTORY) {

                // Quantity
                $quantity = $details[$index]['returnQuantity'] ?? null;

                // Nothing to return.
                if(is_null($quantity)) continue;

                // Get our buying cost
                $our_buying_cost = $details[$index]['buyingCost'];

                // Cost of Goods Sold
                $cogs = Utils::round($our_buying_cost * $quantity);

                // Deduct From Inventory when Creating/Updating Fresh Items in Sales Return.
                if($op === 'deduct') {
                    $cogs = -$cogs;
                    $quantity = -$quantity;
                }

                // Adjust Inventory
                $is_successful = $statement_adjust_inventory -> execute([
                    ':quantity' => $quantity,
                    ':item_id' => $item_id,
                    ':store_id' => $store_id,
                ]);
                if($is_successful !== true && $statement_adjust_inventory -> rowCount() < 1) {
                    throw new Exception('Cannot Adjust from inventory while updating Sales Return.');
                }

                // Assets Account
                $account_asset = $details[$index]['account']['assets'];

                // Check for Key
                if(!array_key_exists($account_asset, $affected_accounts)) $affected_accounts[$account_asset] = 0;
                $affected_accounts[$account_asset] += $cogs;
            }

            // Update Revenue Account
            if(!array_key_exists($account_revenue, $affected_accounts)) $affected_accounts[$account_revenue] = 0;
            if($op === 'add') $revenue = -$revenue;
            $affected_accounts[$account_revenue] += $revenue;
        }
    }

    /**
     * This method will return the account number from payment method.
     * @param sales_return_payment_method
     * @return int 
     */
    private static function get_account_number_from_payment_method(int $sales_return_payment_method): int {
        if($sales_return_payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) return AccountsConfig::ACCOUNTS_RECEIVABLE;
        else {
            $payment_method_account = AccountsConfig::get_account_code_by_payment_method($sales_return_payment_method);
            if($payment_method_account !== null) return $payment_method_account;
            else throw new Exception('Invalid Payment Method Account.');
        }
    }

    /**
     * This method will create sales return.
     * @param data
     * @return array
     */
    private static function create_sales_return(array $data) : array {
        $db = get_db_instance();
        try {
            // Begin Transaction
            $db -> beginTransaction();
            
            // Validate Details.
            $validated_details = self::validate_details($data);

            // Client Id 
            $client_id = $validated_details['client_id'];

            // Self Client
            self::$is_self_client = Client::is_self_client($client_id);

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Store ID
            $store_id = $validated_details['store_id'];

            // Save Last Statement
            CustomerAgedSummary::save_last_statement($store_id, $db);

            // Txn date
            $date = $validated_details['txn_date'];

            // Amounts
            $sum_total = $validated_details['sum_total'];
            $sub_total = $validated_details['sub_total'];
            $pst_tax = $validated_details['pst_tax'];
            $gst_hst_tax = $validated_details['gst_hst_tax'];
            $txn_discount = $validated_details['txn_discount'];
            $cogr = $validated_details['cogr'];
            $restocking_fees = $validated_details['restocking_fees'];

            // Payment details
            $is_pay_later = $validated_details['is_pay_later'];
            $payment_method = $validated_details['payment_method'] ?? null;

            // Sales Invoice Id
            $sales_invoice_id = $data['selectedSalesInvoice'] ?? null;
            if(!is_numeric($sales_invoice_id)) throw new Exception('Invalid Sales Invoice Id.');

            // Validate Sales Returns Details
            $sales_invoice_payment_method = self::check_valid_details_for_sales_returns(
                $sales_invoice_id, 
                $data, 
                $date,
                $db
            );

            // Txn Details
            $details = $data['details'];

            // Amount Eligible For Receipt Discount
            if($sales_invoice_payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {
                $amount_eligible_for_receipt_discount = Shared::calculate_amount_eligible_for_receipt_discount($details, is_sales_return: true);
            }
            else $amount_eligible_for_receipt_discount = 0;
            
            // Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Prepared Statements
            $statement_adjust_inventory = $db -> prepare(Shared::ADJUST_INVENTORY_QUANTITY_AND_VALUE);

            // Affected Accounts
            $affected_accounts = [];

            // Adjust Inventory
            self::adjust_inventory(
                $details,
                $statement_adjust_inventory,
                $store_id,
                'add',
                $affected_accounts,
            );
            
            // Adjust Inventory And Revenue Accounts
            $accounts = array_keys($affected_accounts);
            foreach($accounts as $account) {
                BalanceSheetActions::update_account_value(
                    $bs_affected_accounts,
                    $account,
                    $affected_accounts[$account],
                );

                if($account !== AccountsConfig::SALES_INVENTORY_A) {
                    if(self::$is_self_client === false) {
                        IncomeStatementActions::update_account_values(
                            $is_affected_accounts,
                            $account,
                            $affected_accounts[$account],
                        );
                    } 
                }
            }

            /* ADD TO PAYMENT METHOD ACCOUNT */
            $payment_method_account = self::get_account_number_from_payment_method($payment_method);
            $temp = -$sum_total;

            // Adjust Payment Method Account
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $payment_method_account,
                $temp,
            );
            $offset_amounts[$payment_method_account] = $temp;

            /* Add Restocking Fees */
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $payment_method_account,
                $restocking_fees,
            );
            
            /* UPDATE PST TAX ACCOUNT */ 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                -$pst_tax
            );

            /* UPDATE GST/HST TAX ACCOUNT */ 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                -$gst_hst_tax
            );

            /* ADJUST DISCOUNT ACCOUNT */
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    AccountsConfig::TOTAL_DISCOUNT,
                    -$txn_discount
                );
            }

            /* Adjust Sales Return Account */
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts, 
                    AccountsConfig::SALES_RETURN,
                    $sub_total,
                );
            }
            

            /* COMMIT UPDATES TO BALANCE SHEET */ 
            BalanceSheetActions::update_from(
                $bs_affected_accounts,
                $date,
                $store_id,
                $db,
            );

            /* COMMIT UPDATES TO INCOME STATEMENT */
            IncomeStatementActions::update(
                $is_affected_accounts,
                $date,
                $store_id,
                $db,
            );

            /* Update Subtotal and Total by deducting restocking fees */
            $sub_total -= $restocking_fees;
            $sum_total -= $restocking_fees;

            // Update Client's amount owing if payment method is Pay Later
            if($is_pay_later) Client::update_amount_owing_of_client($client_id, -$sum_total, $db);

            // Insert into Database 
            $query = <<<'EOS'
            INSERT INTO sales_return 
            (
                sales_invoice_id,
                client_id,
                `date`,
                shipping_address,
                credit_amount,
                sum_total,
                sub_total,
                pst_tax,
                gst_hst_tax,
                txn_discount,
                cogr,
                payment_method,
                sales_invoice_payment_method,
                details,
                store_id,
                notes,
                sales_rep_id,
                amount_eligible_for_receipt_discount,
                disable_federal_taxes,
                disable_provincial_taxes,
                early_payment_discount,
                early_payment_paid_within_days,
                net_amount_due_within_days,
                po,
                unit_no,
                vin,
                `restocking_fees`
            )
            VALUES
            (
                :sales_invoice_id,
                :client_id,
                :date,
                :shipping_address,
                :credit_amount,
                :sum_total,
                :sub_total,
                :pst_tax,
                :gst_hst_tax,
                :txn_discount,
                :cogr,
                :payment_method,
                :sales_invoice_payment_method,
                :details,
                :store_id,
                :notes,
                :sales_rep_id,
                :amount_eligible_for_receipt_discount,
                :disable_federal_taxes,
                :disable_provincial_taxes,
                :early_payment_discount,
                :early_payment_paid_within_days,
                :net_amount_due_within_days,
                :po,
                :unit_no,
                :vin,
                :restocking_fees
            );
            EOS;

            // Remove Item Tag
            Shared::remove_item_tag_from_txn_details($details);

            // Values to be inserted into DB
            $values = [
                ':sales_invoice_id' => $sales_invoice_id,
                ':client_id' => $client_id,
                ':date' => $date,
                ':shipping_address' => json_encode($data['clientDetails']['shippingAddresses'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':credit_amount' => $is_pay_later ? $sum_total : 0,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':cogr' => $cogr,
                ':payment_method' => $payment_method,
                ':sales_invoice_payment_method' => $sales_invoice_payment_method,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':store_id' => $store_id,
                ':notes' => $validated_details['notes'],
                ':sales_rep_id' => $data['salesRepId'],
                ':amount_eligible_for_receipt_discount' => $amount_eligible_for_receipt_discount,
                ':disable_federal_taxes' => $data['clientDetails']['disableFederalTaxes'] ?? 0,
                ':disable_provincial_taxes' => $data['clientDetails']['disableProvincialTaxes'] ?? 0,
                ':early_payment_discount' => $data['earlyPaymentDiscount'],
                ':early_payment_paid_within_days' => $data['earlyPaymentPaidWithinDays'],
                ':net_amount_due_within_days' => $data['netAmountDueWithinDays'],
                ':po' => $validated_details['po'],
                ':unit_no' => $validated_details['unit_no'],
                ':vin' => $validated_details['vin'],
                ':restocking_fees' => $validated_details['restocking_fees'],
            ];

            /* CHECK FOR ANY ERROR */
            assert_success();

            // Insert into DB
            $statement = $db -> prepare($query);
            $statement -> execute($values);

            // Get Sales Return ID
            $sales_return_id = $db -> lastInsertId();
            if($sales_return_id === false) throw new Exception('Unable to create Sales Return.');

            // Commit
            if($db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $sales_return_id];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will revert old transaction.
     * @param bs_affected_accounts
     * @param is_affected_accounts 
     * @param details
     * @param statement_adjust_inventory
     * @param store_id
     */
    private static function revert_old_transaction(
        array &$bs_affected_accounts, 
        array &$is_affected_accounts, 
        array $details, 
        PDOStatement &$statement_adjust_inventory, 
        int $store_id,
    ) : void {

        // Offsets
        $affected_accounts = [];

        // Adjust Inventory
        self::adjust_inventory(
            $details['initial']['details'],
            $statement_adjust_inventory,
            $store_id,
            'deduct',
            $affected_accounts,
        );

        $accounts = array_keys($affected_accounts);
        foreach($accounts as $account) {

            // Adjust COGS in Balance Sheet
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $account,
                $affected_accounts[$account]
            );

            if($account !== AccountsConfig::SALES_INVENTORY_A) {
                // Adjust COGS From Income Statement
                if(self::$is_self_client === false) {
                    IncomeStatementActions::update_account_values(
                        $is_affected_accounts,
                        $account,
                        $affected_accounts[$account]
                    );
                }
            }
        }

        /* !! Sales Return */
        if(self::$is_self_client === false) {
            IncomeStatementActions::update_account_values(
                $is_affected_accounts, 
                AccountsConfig::SALES_RETURN,
                -($details['initial']['subTotal'] + $details['initial']['restockingFees']),
            );
        }

        /* !! Discount */
        // Update Income Statement
        if(self::$is_self_client === false) {
            IncomeStatementActions::update_account_values(
                $is_affected_accounts, 
                AccountsConfig::TOTAL_DISCOUNT,
                $details['initial']['txnDiscount']
            );
        }
        
        /* !! GST/HST TAX */ 
        /* Adjust Balance Sheet */
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::GST_HST_CHARGED_ON_SALE,
            $details['initial']['gstHSTTax']
        );

        /* !! PST TAX */ 
        /* Adjust Balance Sheet */
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::PST_CHARGED_ON_SALE,
            $details['initial']['pstTax']
        );

        /* !! Payment Method */
        if(!array_key_exists($details['initial']['paymentMethod'] ?? null, PaymentMethod::MODES_OF_PAYMENT)) throw new Exception('Invalid Old Payment Method.');
        $old_payment_method_account = self::get_account_number_from_payment_method(
            $details['initial']['paymentMethod']
        );

        // Verify Payment Method
        if($old_payment_method_account === null) throw new Exception('Invalid Old Payment Method Account.');
        else {
            $old_sum_total = $details['initial']['sumTotal'] ?? null;
            if(!is_numeric($old_sum_total) || $old_sum_total <= 0) throw new Exception('Invalid Old Sum Total.');

            // Update Balance Sheet
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $old_payment_method_account,
                ($old_sum_total + $details['initial']['restockingFees'])
            );

            // Deduct Restocking Fees
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $old_payment_method_account,
                -$details['initial']['restockingFees']
            );
        }
    }

    /**
     * This method will update Sales Return.
     * @param data
     * @return array
     */
    private static function update_sales_return(array $data) : array {
        $db = get_db_instance();
        try {
            // Begin Transaction
            $db -> beginTransaction();

            // Sales Return ID
            $sales_return_id = $data['id'] ?? null;
            if(!is_numeric($sales_return_id)) throw new Exception('Invalid Sales Return Id.');

            // Fetch Intial Transaction
            $initial_details = Shared::fetch_initial_details_of_txn(
                SALES_RETURN, 
                $sales_return_id,
                $db,
            );

            // Set Initial Details
            Shared::set_initial_client_details($data['initial'], $initial_details);
            
            // Validate Details.
            $validated_details = self::validate_details($data);

            // Client Id
            $client_id = $validated_details['client_id'];

            // Is Self Client 
            self::$is_self_client = Client::is_self_client($client_id);

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Store ID
            $store_id = $validated_details['store_id'];

            // Txn date
            $date = $validated_details['txn_date'];

            // Amounts
            $sum_total = $validated_details['sum_total'];
            $sub_total = $validated_details['sub_total'];
            $pst_tax = $validated_details['pst_tax'];
            $gst_hst_tax = $validated_details['gst_hst_tax'];
            $txn_discount = $validated_details['txn_discount'];
            $cogr = $validated_details['cogr'];
            $restocking_fees = $validated_details['restocking_fees'];

            // Payment details
            $is_pay_later = $validated_details['is_pay_later'];
            $payment_method = $validated_details['payment_method'] ?? null;

            // Check for Latest Copy of the Sales Return.
            $versions = Shared::fetch_latest_required_details_for_transaction($sales_return_id, SALES_RETURN, $data, $db);

            // Sales Invoice Id
            $sales_invoice_id = $data['selectedSalesInvoice'] ?? null;
            if(!is_numeric($sales_invoice_id)) throw new Exception('Invalid Sales Invoice Id.');

            // Validate Sales Returns Details
            $sales_invoice_payment_method = self::check_valid_details_for_sales_returns(
                $sales_invoice_id, 
                $data, 
                $date,
                $db, 
                $sales_return_id
            );

            // Txn Details
            $details = $data['details'];

            // Amount Eligible For Receipt Discount
            if($sales_invoice_payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {
                $amount_eligible_for_receipt_discount = Shared::calculate_amount_eligible_for_receipt_discount($details, is_sales_return: true);
            }
            else $amount_eligible_for_receipt_discount = 0;

            // Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Prepared Statements
            $statement_adjust_inventory = $db -> prepare(Shared::ADJUST_INVENTORY_QUANTITY_AND_VALUE);

            // Revert Old Transaction
            self::revert_old_transaction(
                $bs_affected_accounts,
                $is_affected_accounts,
                $data,
                $statement_adjust_inventory,
                $store_id,
            );

            // Update Balance Sheet
            BalanceSheetActions::update_from(
                $bs_affected_accounts,
                $data['initial']['txnDate'],
                $store_id,
                $db,
            );

            // Update Income Statement
            IncomeStatementActions::update(
                $is_affected_accounts,
                $data['initial']['txnDate'],
                $store_id,
                $db,
            );

            // Now Process New Changes
            // Affected Accounts.
            $affected_accounts = [];

            // Reset Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Now Process Inventory
            self::adjust_inventory(
                details: $details, 
                statement_adjust_inventory: $statement_adjust_inventory,
                store_id: $store_id,
                op: 'add',
                affected_accounts: $affected_accounts,
            );
            
            // Adjust Inventory And Revenue Accounts
            $accounts = array_keys($affected_accounts);
            foreach($accounts as $account) {
                BalanceSheetActions::update_account_value(
                    $bs_affected_accounts,
                    $account,
                    $affected_accounts[$account]
                );
                
                if($account !== AccountsConfig::SALES_INVENTORY_A) {
                    if(self::$is_self_client === false) {
                        IncomeStatementActions::update_account_values(
                            $is_affected_accounts,
                            $account,
                            $affected_accounts[$account]
                        );
                    }
                }
            }

            // ADD TO PAYMENT METHOD ACCOUNT
            $payment_method_account = self::get_account_number_from_payment_method($payment_method);
            $temp = -$sum_total;

            // Adjust Payment Method Account
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $payment_method_account,
                $temp,
            );

            /* Adjust Sales Return Account */
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts, 
                    AccountsConfig::SALES_RETURN,
                    $sub_total,
                );
            }

            // Add Restocking Fees
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $payment_method_account,
                $restocking_fees,
            );

            // UPDATE GST/HST TAX ACCOUNT 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                -$gst_hst_tax,
            );

            // UPDATE PST TAX ACCOUNT
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                -$pst_tax,
            );
            
            // ADJUST DISCOUNT ACCOUNT
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    AccountsConfig::TOTAL_DISCOUNT,
                    -$txn_discount
                );
            }

            // COMMIT UPDATES TO BALANCE SHEET
            BalanceSheetActions::update_from(
                $bs_affected_accounts,
                $date,
                $store_id,
                $db,
            );

            // COMMIT UPDATES TO INCOME STATEMENT
            IncomeStatementActions::update(
                $is_affected_accounts,
                $date,
                $store_id,
                $db,
            );

            // Remove Item Tag
            Shared::remove_item_tag_from_txn_details($details);

            // Check for Changes in Details.
            $initial_details_json = hash('sha256', json_encode($data['initial']['details']), JSON_THROW_ON_ERROR);
            $new_details_json = hash('sha256', json_encode($details), JSON_THROW_ON_ERROR);

            // Check for Any Changes in Details. If yes, add to versions
            if($initial_details_json !== $new_details_json) {
                if(is_null($versions)) $versions = [];
                $versions[Utils::get_utc_unix_timestamp_from_utc_str_timestamp($data['lastModifiedTimestamp'])] = $data['initial']['details'];
            }

            // Update Sales Return
            $query = <<<'EOS'
            UPDATE 
                sales_return 
            SET 
                date = :date,
                shipping_address = :shipping_address,
                credit_amount = :credit_amount,
                sum_total = :sum_total,
                sub_total = :sub_total,
                pst_tax = :pst_tax,
                gst_hst_tax = :gst_hst_tax,
                txn_discount = :txn_discount,
                cogr = :cogr,
                payment_method = :payment_method,
                details = :details,
                notes = :notes,
                amount_eligible_for_receipt_discount = :amount_eligible_for_receipt_discount,
                early_payment_discount = :early_payment_discount,
                early_payment_paid_within_days = :early_payment_paid_within_days,
                net_amount_due_within_days = :net_amount_due_within_days,
                restocking_fees = :restocking_fees,
                versions = :versions,
                modified = CURRENT_TIMESTAMP 
            WHERE
                id = :id;
            EOS;

            // Reverse Initial Amount Owing
            if($data['initial']['paymentMethod'] === PaymentMethod::PAY_LATER) {
                Client::update_amount_owing_of_client(
                    $data['clientDetails']['id'], 
                    $data['initial']['sumTotal'],
                    $db
                );
            }

            // Deduct Restocking Fees from Sub and Sum total
            $sub_total -= $restocking_fees;
            $sum_total -= $restocking_fees;

            // Update Client's amount owing if payment method is Pay Later
            if($is_pay_later) Client::update_amount_owing_of_client($data['clientDetails']['id'], -$sum_total, $db);

            $params = [
                ':date' => $date,
                ':shipping_address' => json_encode($data['clientDetails']['shippingAddresses'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':credit_amount' => $is_pay_later ? $sum_total : 0,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':cogr' => $cogr,
                ':payment_method' => $payment_method,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':notes' => $validated_details['notes'],
                ':amount_eligible_for_receipt_discount' => $amount_eligible_for_receipt_discount,
                ':early_payment_discount' => $data['earlyPaymentDiscount'],
                ':early_payment_paid_within_days' => $data['earlyPaymentPaidWithinDays'],
                ':net_amount_due_within_days' => $data['netAmountDueWithinDays'],
                ':restocking_fees' => $restocking_fees,
                ':versions' => is_array($versions) ? json_encode($versions, JSON_THROW_ON_ERROR) : null,
                ':id' => $sales_return_id,
            ];

            // CHECK FOR ANY ERROR
            assert_success();

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);

            // Check for Successful Update
            if($is_successful !== true || $statement -> rowCount () < 1) throw new Exception('Unable to Update Sales Return.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will fetch sales returns.
     * @param transaction_id
     * @return array
     */
    private static function fetch_transaction_by_id(int $transaction_id): array {
        $db = get_db_instance();

        // Store Id 
        $store_id = intval($_SESSION['store_id'] ?? 0);

        $statement = $db -> prepare('SELECT * FROM sales_return WHERE id = :id AND store_id = :store_id;');
        $statement -> execute([':id' => $transaction_id, ':store_id' => $store_id]);
        $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($record[0])) $record = $record[0];

        // Fetch Previous and Next Transaction ID
        $adjacent_records = Shared::fetch_previous_and_next_transaction_id($store_id, $record['client_id'], SALES_RETURN, $transaction_id, $db);

        // Format Record
        $formatted_record = Shared::format_transaction_record($record, SALES_RETURN);

        // Add Adjacent Records
        $formatted_record['previousTxnId'] = $adjacent_records['previousTxnId'];
        $formatted_record['nextTxnId'] = $adjacent_records['nextTxnId'];

        // Add Current State for Item 
        $cache = [];
        foreach($formatted_record['details'] as $item) {
            $item_id = $item['itemId'];
            if(!isset($cache[$item_id])) {
                $result = Inventory::fetch(['id' => $item_id], $store_id, db: $db);
                if($result['status']) {
                    $cache[$item_id] = $result['data'][0];
                }
            }
        }

        // Add Sales Invoice Details
        $sales_invoice_details = self::fetch_sales_invoice($record['sales_invoice_id'], $db);
        $formatted_record['selectedSalesInvoice'] = $record['sales_invoice_id'];
        $formatted_record['selectedSalesInvoiceLastModifiedTimestamp'] = $sales_invoice_details['modified'];

        // Initial Details
        $formatted_record['initial']['details'] = $formatted_record['details'];

        // Add to response
        $formatted_record['itemDetailsForTransactions'] = $cache;

        return ['status' => true, 'data' => $formatted_record];
    }

    public static function process(array $data): array {
        try {
            // Result
            $result = [];

            switch($data['action']) {
                case 'create_txn': $result = self::create_sales_return($data); break;
                case 'update_txn': $result = self::update_sales_return($data); break;
                case 'fetch_transaction_by_id': $result = self::fetch_transaction_by_id($data['transaction_id']); break;
                case 'txn_search': $result = Shared::search($data); break;
                case 'print': $result = Shared::generate_pdf($data['txn_queue']); break;
                case 'txn_email': $result = Shared::email_si_sr_cn_dn_qt($data['txn_queue'][0]['id'], $data['txn_queue'][0]['type']); break;
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