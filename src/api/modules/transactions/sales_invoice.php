<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/accounts.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/income_statement.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_return.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/prepare_pdf_details.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/quotation.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/special_exceptions.php";

/**
 * This class will handle processing of sales invoice.
 */
class SalesInvoice {

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
    ];

    // Flag
    private static $is_self_client = false;

    /**
     * This method will validate the items details for validity.
     * @param items
     * @param disable_federal_taxes
     * @param disable_provincial_taxes
     * @param is_self_client
     * @return array
     */
    private static function validate_items_details(array $items, int $disable_federal_taxes, int $disable_provincial_taxes, bool $is_self_client = false) : array {

        // Validate Item Count
        if(count($items) < 1) return ['status' => false, 'message' => 'Invalid Items Count.'];

        // Store Tax Rate
        $federal_tax_rate = $disable_federal_taxes === 1 ? 0.00 : GST_HST_TAX_RATE;
        $provincial_tax_rate = $disable_provincial_taxes === 1 ? 0.00 : StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['pst_tax_rate'];

        // Validate Item fields
        foreach($items as $item) {

            // Identifier
            $identifier = $item['identifier'];

            // Uninitialized Item Found
            if(isset($item['itemId']) === false || is_null($item['itemId'])) return ['status' => false, 'message' => 'Uninitialized Item.'];
            foreach(self::ITEM_KEYS as $key) {
                if(!isset($item[$key])) return ['status' => false, 'message' => "$key not set for $identifier."];
                if(!is_numeric($item[$key])) return ['status' => false, 'message' => "$key not numeric for $identifier."];
                if(floatval($item[$key]) < 0) return ['status' => false, 'message' => "$key less than 0 for $identifier."];
            }

            // Check for Same Base Price for Self Client.
            if($is_self_client && $item['pricePerItem'] !== $item['buyingCost']) throw new Exception('Selling Price Has to be the same as Buying Price for Self Client.');

            $keys = ['quantity', 'basePrice', 'amountPerItem'];
            if(SYSTEM_INIT_MODE === PARTS) {
                $keys []= 'buyingCost';

                // Only Check for Parts
                foreach($keys as $key) if(floatval($item[$key]) <= 0) return ['status' => false, 'message' => "$key less than or equal to 0 for $identifier."];
            }

            // Flags
            $federal_tax_status_invalid = false;
            $provincial_tax_status_invalid = false;

            // Tax rate of individual item.
            $gst_hst_tax_rate_of_item = floatval($item['gstHSTTaxRate']);
            $pst_tax_rate_of_item = floatval($item['pstTaxRate']);

            // Check for Valid Tax Rate
            $federal_tax_status_invalid = $federal_tax_rate !== $gst_hst_tax_rate_of_item;
            $provincial_tax_status_invalid = $provincial_tax_rate !== $pst_tax_rate_of_item;

            if (SYSTEM_INIT_HOST === PARTS_HOST) {
                if(in_array($item['itemId'], Inventory::EHC_ITEMS) === true) {
                    // Item is EHC 
                    $federal_tax_status_invalid = $provincial_tax_status_invalid = false;
                }
            }
            
            if($federal_tax_status_invalid) return ['status' => false, 'message' => "GST/HST Tax Rate invalid for $identifier."];
            else if($provincial_tax_status_invalid) return ['status' => false, 'message' => "PST Tax Rate invalid for $identifier."];

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
        $total = 0;
        $sub_total = 0;
        $gst_hst_tax = 0;
        $pst_tax = 0;
        $txn_discount = 0;
        $cogs = 0;

        // Select Tax Rate
        $federal_tax_rate = $disable_federal_taxes ? 0 : GST_HST_TAX_RATE;
        $provincial_tax_rate = $disable_provincial_taxes ? 0 : PROVINCIAL_TAX_RATE;
        foreach($items as $item) { 

            // Skip Back Order
            if($item['isBackOrder'] === 1) continue;
            $total += $item['amountPerItem'];

            // Add Taxes
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false) {
                $pst_tax += (($item['amountPerItem'] * $provincial_tax_rate) / 100);
                $gst_hst_tax += (($item['amountPerItem'] * $federal_tax_rate) / 100);
            }
            
            $base_price = $item['basePrice'];
            $quantity = $item['quantity'];
            $cogs += ($item['buyingCost'] * $quantity);
            $txn_discount += ((($base_price * $quantity) * $item['discountRate']) / 100);
        }

        // Set Sub total
        $sub_total = $total;

        // Add Taxes to Sub total 
        $sum_total = $sub_total + $gst_hst_tax + $pst_tax;

        return [
            'sumTotal' => Utils::round($sum_total),
            'subTotal' => Utils::round($sub_total),
            'pstTax' => Utils::round($pst_tax),
            'gstHSTTax' => Utils::round($gst_hst_tax),
            'txnDiscount' => Utils::round($txn_discount),
            'cogs' => Utils::round($cogs),
        ];
    }
    
    /**
     * This method will validate details. If valid validated details will be returned, else exception will be thrown.
     * @throws Exception
     * @param data
     * @return array
     */
    private static function validate_details(array $data): array {
        // Client Id 
        $client_id = $data['clientDetails']['id'] ?? null;

        // Check for valid id 
        if(!Validate::is_numeric($client_id)) {
            throw new Exception('Cannot Process Sales Invoice for Invalid Customer.');
        }

        // Disable Self Client 
        if(Client::is_inter_store_client($client_id)) throw new Exception('Transactions disabled for Self Client.');

        // Sales Rep Id
        if($data['salesRepId'] === 0) throw new Exception('Please select Sales Representative.');

        // Check for Already Transferred Invoice.
        if(isset($data['id']) && is_numeric($data['id']) && intval($data['isInvoiceTransferred']) === 1) {
            throw new Exception('This Invoice\'s items are already transferred to another store and cannot be updated. Please create a new Sales Invoice.');
        }

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

        // Check for Pay Later Available
        if($is_pay_later) Client::is_credit_txn_eligible_for_client($data['clientDetails']['primaryDetails']);

        // Check whether credit transactions are disabled for this client.
        if(!Validate::is_numeric(strval($data['clientDetails']['disableCreditTransactions']))) throw new Exception('Invalid Disable Credit Transaction Value.');
        $disable_credit_txn = intval($data['clientDetails']['disableCreditTransactions'] ?? 1);
        if($disable_credit_txn === 1 && $is_pay_later) throw new Exception('Credit Transactions are disabled for this customer due to Non-payment in the past.');

        // Validate Store
        $store_id = intval($_SESSION['store_id']);
        if(key_exists($store_id, StoreDetails::STORE_DETAILS) === false) throw new Exception('Store is Invalid.');
        if($store_id !== intval($data['storeId'])) throw new Exception('Store does not match with current session.');
        
        // Transaction Date
        $transaction_date = Utils::get_YYYY_mm_dd(
            Utils::convert_utc_str_timestamp_to_localtime($data['txnDate'], $store_id)
        );
        if($transaction_date === null) throw new Exception('Invalid Date.');

        // Assert Current Month of Transaction
        Shared::assert_current_month_of_transaction($transaction_date, $store_id);

        // Validate New Date(if any)
        Shared::validate_new_date_of_transaction($data, $transaction_date);

        // Change for Changed Transactions
        $is_transaction_detail_changed = false;
        if(isset($data['initial'])) {
            $is_transaction_detail_changed = Shared::is_transaction_detail_changed(
                $data['initial']['details'],
                $data['details'],
            );
        }

        // Is Update Transaction
        $is_update_txn = isset($data['id']);

        // Do validate date 
        $do_validate_date = $is_update_txn === false || $is_transaction_detail_changed === true;

        if($do_validate_date) {
            /* Make an Exception for J.LOEWEN MECHANICAL LTD, DriftPile */
            if(SYSTEM_INIT_MODE === PARTS && ($client_id !== 14376 && $client_id !== 18520) ) {
                if(isset($data['initial']['txnDate'])) Shared::check_transaction_older_than_2_days(
                    $data['initial']['txnDate'], 
                    $store_id,
                );
            }
        }
        
        // Disable Federal Taxes
        $disable_federal_taxes = $data['disableFederalTaxes'] ?? null;
        $disable_provincial_taxes = $data['disableProvincialTaxes'] ?? null;

        // Check for Disabled Taxes
        if($disable_federal_taxes !== $data['clientDetails']['disableFederalTaxes']) {
            throw new Exception('Federal Tax Status cannot be changed for this invoice.');
        }

        if($disable_provincial_taxes !== $data['clientDetails']['disableProvincialTaxes']) {
            throw new Exception('Provincial Tax Status cannot be changed for this invoice.');
        }

        // Validate Items Information
        $valid_ret_value = self::validate_items_details(
            $data['details'],
            $disable_federal_taxes,
            $disable_provincial_taxes,
            $data['clientDetails']['isSelfClient'],
        );
        if($valid_ret_value['status'] === false) throw new Exception($valid_ret_value['message']);

        // Calculate Amounts
        $calculated_amount = self::calculate_amount(
            $data['details'], 
            $disable_federal_taxes,
            $disable_provincial_taxes,
        );
        $sum_total = $calculated_amount['sumTotal'];
        $sub_total = $calculated_amount['subTotal'];
        $pst_tax = $calculated_amount['pstTax'];
        $gst_hst_tax = $calculated_amount['gstHSTTax'];
        $txn_discount = $calculated_amount['txnDiscount'];
        $cogs = $calculated_amount['cogs'];

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

        // Flag
        $is_unit_no_or_vin_or_po_given = false;

        // Validate Unit number
        $unit_no = strtoupper(($data['unitNo'] ?? ''));

        // Set Flag
        if(strlen($unit_no) > 0) $is_unit_no_or_vin_or_po_given = true;

        // Validate VIN(Vehicle Identification Number)
        $vin = isset($data['vin']) ? strtoupper(trim($data['vin'])) : '';
        if(strlen($vin) > 0) {

            // Strip  '-'
            $vin = str_replace('-', '', $vin);
            
            if(strlen($vin) != 17) throw new Exception('VIN Number must be 17 characters long.');
            else if(ctype_alnum($vin) === false) throw new Exception('VIN Number has Invalid Characters.');

            // Set Flag
            $is_unit_no_or_vin_or_po_given = true;
        }

        // P.O 
        $po = isset($data['po']) ? strtoupper(trim($data['po'])) : '';
        if(isset($po[0])) $is_unit_no_or_vin_or_po_given = true;

        // Check for Required Details.
        if($is_unit_no_or_vin_or_po_given === false) throw new Exception('Unit Number or VIN or PO is required.');

        // Driver Name
        $driver_name = isset($data['driverName']) ? ucwords(trim($data['driverName'])) : '';

        // Odometer Reading 
        $odometer_reading = isset($data['odometerReading']) ? trim($data['odometerReading']) : '';

        // Trailer Number
        $trailer_number = isset($data['trailerNumber']) ? trim($data['trailerNumber']) : '';
        
        // Account Number
        $account_number = ucwords(trim($data['accountNumber'] ?? ''));
        
        // Purchased By 
        $purchased_by = ucwords(trim($data['purchasedBy'] ?? ''));

        // Notes 
        $notes = isset($data['notes']) ? ucfirst(trim($data['notes'])): '';

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
            'cogs' => $cogs,
            'txn_discount' => $txn_discount,
            'vin' => $vin,
            'unit_no' => $unit_no,
            'po' => $po,
            'driver_name' => $driver_name,
            'odometer_reading' => $odometer_reading,
            'trailer_number' => $trailer_number,
            'account_number' => $account_number,
            'purchased_by' => $purchased_by,
            'notes' => $notes,
            'is_transaction_detail_changed' => $is_transaction_detail_changed,
        ];
    }

    /**
     * This method will check the quantity for all items.
     * @param items
     * @param db
     * @return array
     */
    private static function check_quantity_of_items(array $items, PDO | null &$db=null) : array {
        try {
            $ids = [];
            $details = [];
            foreach($items as $item) {
                if($item['category'] === CATEGORY_INVENTORY) {

                    // Skip BackOrder
                    if($item['isBackOrder'] === 1) continue;
                    $item_id = $item['itemId'];
                    if(!in_array($item['itemId'], $ids)) {
                        $details[$item_id] = ['quantity' => 0, 'identifier' => $item['identifier']];
                        $ids []= $item_id;
                    }

                    // Add to Quantity
                    $details[$item_id]['quantity'] += $item['quantity'];
                }
            }

            // Store ID
            $store_id = intval($_SESSION['store_id']);

            // Fetch Item Inventory Details By id.
            $item_quantities = Inventory::fetch_item_inventory_details_by_id($ids, $store_id, $db);

            // Check for Valid Execution
            if($item_quantities['status'] === false) throw new Exception($item_quantities['message']);

            // Set Data
            $item_quantities = $item_quantities['data'];

            foreach($ids as $id) {
                $inventory_quantity = $item_quantities[$id][$store_id]['quantity'] ?? 0;
                if(in_array($id, Inventory::EHC_ITEMS) === false && $details[$id]['quantity'] > $inventory_quantity) {
                    throw new Exception("{$details[$id]['identifier']} quantity is low in inventory.");
                }
            }
            return ['status' => true];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will create sales invoice.
     * @param data 
     * @param db
     * @return array
     */
    private static function create_sales_invoice(array $data, PDO | null &$db=null): array {
        if($db === null) {
            $db = get_db_instance();
            $is_new_connection = true;
        }
        else $is_new_connection = false;
        try {
            // Begin Transaction
            if($is_new_connection) $db -> beginTransaction();

            // Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Validate Details.
            $validated_details = self::validate_details($data);

            // Store ID
            $store_id = $validated_details['store_id'];

            // Client Id 
            $client_id = $validated_details['client_id'];

            // Is Self Client
            self::$is_self_client = Client::is_self_client($client_id);

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Save Last Statement
            // CustomerAgedSummary::save_last_statement($store_id, $db);

            // Payment details
            $is_pay_later = $validated_details['is_pay_later'];
            $payment_method = $validated_details['payment_method'] ?? null;

            // Fetch Customer Aged Summary of Client
            if($is_pay_later) Shared::allow_balance_due_check_for_client($client_id, $store_id);

            // Txn date
            $date = $validated_details['txn_date'];

            // Amounts
            $sum_total = $validated_details['sum_total'];
            $sub_total = $validated_details['sub_total'];
            $pst_tax = $validated_details['pst_tax'];
            $gst_hst_tax = $validated_details['gst_hst_tax'];
            $txn_discount = $validated_details['txn_discount'];
            $cogs = $validated_details['cogs'];

            // Txn Details
            $details = $data['details'];

            if(SYSTEM_INIT_MODE === PARTS) {
                // Verify Valid Quantities
                $ret_status = SalesInvoice::check_quantity_of_items($details, $db);
                if($ret_status['status'] === false) throw new Exception($ret_status['message']);
            }

            // Amount Eligible For Receipt Discount
            if($payment_method === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {
                $amount_eligible_for_receipt_discount = Shared::calculate_amount_eligible_for_receipt_discount($details);
            }
            else $amount_eligible_for_receipt_discount = 0;

            // Prepared Statements
            $statement_adjust_inventory = $db -> prepare(Shared::ADJUST_INVENTORY_QUANTITY_AND_VALUE);

            // Remove Keys
            Shared::remove_keys($details, Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // No of items
            $no_of_items = count($details);

            // Fetch Item Properties
            $ids = [];
            for($index = 0; $index < $no_of_items; ++$index) {
                if(!in_array($details[$index]['itemId'], $ids)) $ids[]= $details[$index]['itemId'];
            }

            // Fetch Item Information
            $items_information = Inventory::fetch(['item_ids' => $ids], $store_id, set_id_as_index: true, db: $db);
            if($items_information['status'] === false) throw new Exception($items_information['message']);
            else $items_information = $items_information['data'];

            // Affected Accounts
            $affected_accounts = [];

            // Adjust Inventory and get total COGS
            self::adjust_inventory(
                $details,
                $items_information,
                $statement_adjust_inventory,
                $store_id,
                'deduct',
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

                if(self::$is_self_client === false) {
                    IncomeStatementActions::update_account_values(
                        $is_affected_accounts,
                        $account,
                        $affected_accounts[$account],
                    );
                }
            }
            
            /* ADD TO PAYMENT METHOD ACCOUNT */
            $payment_method_account = AccountsConfig::get_account_code_by_payment_method($payment_method);
            if($payment_method_account !== null) {
                BalanceSheetActions::update_account_value(
                    $bs_affected_accounts,
                    $payment_method_account,
                    $sum_total,
                );

                if(self::$is_self_client === false) {
                    IncomeStatementActions::update_account_values(
                        $is_affected_accounts,
                        $payment_method_account,
                        $sum_total
                    );
                }
            }
            else throw new Exception('Invalid Payment Method Account.');

            /* UPDATE PST TAX ACCOUNT */ 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                $pst_tax
            );

            /* UPDATE GST/HST TAX ACCOUNT */ 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                $gst_hst_tax
            );

            /* ADJUST DISCOUNT ACCOUNT */
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    AccountsConfig::TOTAL_DISCOUNT,
                    $txn_discount
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

            // Update Client's amount owing if payment method is Pay Later
            if($is_pay_later) Client::update_amount_owing_of_client($client_id, $sum_total, $db);

            // Update Last Purchase Date
            Client::update_last_purchase_date($client_id, null, $date, $db, $store_id);

            // Insert into Database 
            $query = <<<'EOS'
            INSERT INTO sales_invoice 
            (
                client_id,
                `date`,
                shipping_address,
                credit_amount,
                sum_total,
                sub_total,
                pst_tax,
                gst_hst_tax,
                txn_discount,
                cogs,
                payment_method,
                details,
                po,
                unit_no,
                vin,
                store_id,
                notes,
                sales_rep_id,
                driver_name,
                odometer_reading,
                trailer_number,
                amount_eligible_for_receipt_discount,
                disable_federal_taxes,
                disable_provincial_taxes,
                early_payment_discount,
                early_payment_paid_within_days,
                net_amount_due_within_days,
                account_number,
                purchased_by
            )
            VALUES
            (
                :client_id,
                :date,
                :shipping_address,
                :credit_amount,
                :sum_total,
                :sub_total,
                :pst_tax,
                :gst_hst_tax,
                :txn_discount,
                :cogs,
                :payment_method,
                :details,
                :po,
                :unit_no,
                :vin,
                :store_id,
                :notes,
                :sales_rep_id,
                :driver_name,
                :odometer_reading,
                :trailer_number,
                :amount_eligible_for_receipt_discount,
                :disable_federal_taxes,
                :disable_provincial_taxes,
                :early_payment_discount,
                :early_payment_paid_within_days,
                :net_amount_due_within_days,
                :account_number,
                :purchased_by
            );
            EOS;

            // Remove Item Tag
            Shared::remove_item_tag_from_txn_details($details);

            // Values to be inserted into DB
            $values = [
                ':client_id' => $client_id,
                ':date' => $date,
                ':shipping_address' => json_encode($data['clientDetails']['shippingAddresses'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':credit_amount' => $is_pay_later ? $sum_total : 0,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':cogs' => $cogs,
                ':payment_method' => $payment_method,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':po' => $validated_details['po'],
                ':unit_no' => $validated_details['unit_no'],
                ':vin' => $validated_details['vin'],
                ':store_id' => $store_id,
                ':notes' => $validated_details['notes'],
                ':sales_rep_id' => $data['salesRepId'],
                ':driver_name' => $validated_details['driver_name'],
                ':odometer_reading' => $validated_details['odometer_reading'],
                ':trailer_number' => $validated_details['trailer_number'],
                ':amount_eligible_for_receipt_discount' => $amount_eligible_for_receipt_discount,
                ':disable_federal_taxes' => $data['clientDetails']['disableFederalTaxes'] ?? 0,
                ':disable_provincial_taxes' => $data['clientDetails']['disableProvincialTaxes'] ?? 0,
                ':early_payment_discount' => $data['earlyPaymentDiscount'],
                ':early_payment_paid_within_days' => $data['earlyPaymentPaidWithinDays'],
                ':net_amount_due_within_days' => $data['netAmountDueWithinDays'],
                ':account_number' => $validated_details['account_number'],
                ':purchased_by' => $validated_details['purchased_by'],
            ];

            // Update Last Sold Date
            Inventory::update_last_sold_for_items($details, $date, $store_id, $db);

            // if($payment_method === PaymentMethod::PAY_LATER) {
            //     // Update Customer Aged Summary
            //     CustomerAgedSummary::update(
            //         $client_id,
            //         $date,
            //         $sum_total,
            //         $store_id,
            //         $db,
            //     );
            // }

            /* CHECK FOR ANY ERROR */
            assert_success();

            // Insert into DB
            $statement = $db -> prepare($query);
            $statement -> execute($values);

            // Get Sales Invoice ID
            $sales_invoice_id = $db -> lastInsertId();
            if($sales_invoice_id === false) throw new Exception('Unable to create Sales Invoice.');

            /* COMMIT */
            if($is_new_connection && $db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $sales_invoice_id];
        }
        catch(Exception $e) {
            if($is_new_connection && $db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will update sales invoice.
     * @param data 
     * @return array
     */
    private static function update_sales_invoice(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Invoice Id
            $invoice_id = is_numeric($data['id'] ?? null) ? intval($data['id']) : null;
            if(!is_numeric($invoice_id)) throw new Exception('Invalid Invoice Id.');

            // Fetch Intial Transaction
            $initial_details = Shared::fetch_initial_details_of_txn(
                SALES_INVOICE, 
                $invoice_id,
                $db,
            );

            // Set Initial Details
            Shared::set_initial_client_details($data['initial'], $initial_details);

            // Remove Item Tag
            Shared::remove_item_tag_from_txn_details($data['details']);

            // Validate Details.
            $details = self::validate_details($data);
            
            // Check for changed transactions
            $is_transaction_detail_changed = $details['is_transaction_detail_changed'];

            // Check for Existing Sales Returns for This Invoice.
            $sales_returns = SalesReturn::fetch_sales_returns_by_sales_invoice_id($invoice_id, $db);
            if(count($sales_returns) > 0) throw new Exception('Sales Returns have been made for this invoice.');

            // Store Id 
            $store_id = $details['store_id'];

            // Client Id
            $client_id = $details['client_id'];

            // Is Self Client
            self::$is_self_client = Client::is_self_client($client_id);

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Is Pay Later
            $is_pay_later = $details['is_pay_later'];

            // Fetch Customer Aged Summary of Client
            if($is_pay_later) Shared::allow_balance_due_check_for_client($client_id, $store_id);

            // Versions
            $versions = Shared::fetch_latest_required_details_for_transaction($invoice_id, SALES_INVOICE, $data, $db);

            // Initial Payment Method 
            $initial_payment_method = $data['initial']['paymentMethod'];

            // Check for Client Change
            if($client_id != $data['initial']['clientDetails']['id'] && $initial_payment_method === PaymentMethod::PAY_LATER) {
                
                // Revert Amount owing for old client.
                Client::update_amount_owing_of_client(
                    $data['initial']['clientDetails']['id'], 
                    -$data['initial']['sumTotal'], 
                    $db
                );

                // Add Old Amount Owing to Current Client
                // This will be adjusted in below statements.
                Client::update_amount_owing_of_client(
                    $client_id, 
                    $data['initial']['sumTotal'], 
                    $db
                );
            }

            // Reverse Client Amount Owing.
            if($initial_payment_method === PaymentMethod::PAY_LATER) {
                Client::update_amount_owing_of_client(
                    $client_id, 
                    -$data['initial']['sumTotal'], 
                    $db
                );
            }

            // Update For Client.
            if($is_pay_later) {
                Client::update_amount_owing_of_client(
                    $client_id,
                    $details['sum_total'], 
                    $db
                );
            }

            // Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Prepared Statements
            $statement_adjust_inventory = $db -> prepare(Shared::ADJUST_INVENTORY_QUANTITY_AND_VALUE);

            // Revert Old Transaction Details
            self::revert_old_transaction(
                $bs_affected_accounts, 
                $is_affected_accounts, 
                $data, 
                $statement_adjust_inventory, 
                $store_id,
                $db,
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

            // ** NOW PROCESS NEW CHANGES

            // Verify Valid Quantities
            if(SYSTEM_INIT_MODE === PARTS) {
                $ret_status = SalesInvoice::check_quantity_of_items($data['details'], $db);
                if($ret_status['status'] === false) throw new Exception($ret_status['message']);
            }
            
            // Amount Eligible for Receipt Discount
            if($details['payment_method'] === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) {
                $amount_eligible_for_receipt_discount = Shared::calculate_amount_eligible_for_receipt_discount($data['details']);
            }
            else $amount_eligible_for_receipt_discount = 0;

            // Affected Accounts.
            $affected_accounts = [];

            // Reset Affected Accounts 
            $is_affected_accounts = AccountsConfig::ACCOUNTS;
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Check whether Disable Item Editing is Enabled or Not.
            if(($details['disableItemEditing'] ?? null) === 1) $data['details'] = $data['details']['initial'];

            // Remove Keys
            Shared::remove_keys($data['details'], Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // No of items
            $no_of_items = count($data['details']);

            // Fetch Item Properties
            $ids = [];
            for($index = 0; $index < $no_of_items; ++$index) {
                if(!in_array($data['details'][$index]['itemId'], $ids)) $ids[]= $data['details'][$index]['itemId'];
            }

            // Fetch Fresh Items Information
            $items_information = Inventory::fetch(['item_ids' => $ids], $store_id, set_id_as_index: true, db: $db);
            if($items_information['status'] === false) throw new Exception($items_information['message']);
            else $items_information = $items_information['data'];

            // Now Process Inventory
            self::adjust_inventory(
                details: $data['details'], 
                items_information: $items_information,
                statement_adjust_inventory: $statement_adjust_inventory,
                store_id: $store_id,
                op: 'deduct',
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
                
                if(self::$is_self_client === false) {
                    IncomeStatementActions::update_account_values(
                        $is_affected_accounts,
                        $account,
                        $affected_accounts[$account]
                    );
                }
            }

            // ADD TO PAYMENT METHOD ACCOUNT
            $payment_method_account = AccountsConfig::get_account_code_by_payment_method($details['payment_method']);
            if($payment_method_account !== null) {
                BalanceSheetActions::update_account_value(
                    $bs_affected_accounts,
                    $payment_method_account,
                    $details['sum_total'],
                );

                if(self::$is_self_client === false) {
                    IncomeStatementActions::update_account_values(
                        $is_affected_accounts,
                        $payment_method_account,
                        $details['sum_total'],
                    );
                }
            }
            else throw new Exception('Invalid Payment Method Account.');

            // UPDATE GST/HST TAX ACCOUNT 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                $details['gst_hst_tax'],
            );

            // UPDATE PST TAX ACCOUNT
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                $details['pst_tax']
            );
            
            // ADJUST DISCOUNT ACCOUNT
            if(self::$is_self_client === false) {
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    AccountsConfig::TOTAL_DISCOUNT,
                    $details['txn_discount']
                );
            }
            
            // COMMIT UPDATES TO BALANCE SHEET
            BalanceSheetActions::update_from(
                $bs_affected_accounts,
                $details['txn_date'],
                $store_id,
                $db,
            );

            // COMMIT UPDATES TO INCOME STATEMENT
            IncomeStatementActions::update(
                $is_affected_accounts,
                $details['txn_date'],
                $store_id,
                $db,
            );

            // Check for Any Changes in Details. If yes, add to versions
            if($is_transaction_detail_changed) {
                if(is_null($versions)) $versions = [];
                $versions[Utils::get_utc_unix_timestamp_from_utc_str_timestamp($data['lastModifiedTimestamp'])] = $data['initial']['details'];
            }

            // Update Sales Invoice
            $query = <<<'EOS'
            UPDATE 
                sales_invoice 
            SET 
                client_id = :client_id,
                date = :date,
                shipping_address = :shipping_address,
                credit_amount = :credit_amount,
                sum_total = :sum_total,
                sub_total = :sub_total,
                pst_tax = :pst_tax,
                gst_hst_tax = :gst_hst_tax,
                txn_discount = :txn_discount,
                cogs = :cogs,
                payment_method = :payment_method,
                details = :details,
                po = :po,
                unit_no = :unit_no,
                vin = :vin,
                notes = :notes,
                driver_name = :driver_name,
                odometer_reading = :odometer_reading,
                trailer_number = :trailer_number,
                amount_eligible_for_receipt_discount = :amount_eligible_for_receipt_discount,
                early_payment_discount = :early_payment_discount,
                early_payment_paid_within_days = :early_payment_paid_within_days,
                net_amount_due_within_days = :net_amount_due_within_days,
                account_number = :account_number,
                purchased_by = :purchased_by,
                versions = :versions,
                modified = CURRENT_TIMESTAMP 
            WHERE
                id = :id
            AND
                is_invoice_transferred = 0;
            EOS;

            $params = [
                ':client_id' => $client_id,
                ':date' => $details['txn_date'],
                ':shipping_address' => json_encode($data['clientDetails']['shippingAddresses'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':credit_amount' => $details['is_pay_later'] ? $details['sum_total'] : 0,
                ':sum_total' => $details['sum_total'],
                ':sub_total' => $details['sub_total'],
                ':pst_tax' => $details['pst_tax'],
                ':gst_hst_tax' => $details['gst_hst_tax'],
                ':txn_discount' => $details['txn_discount'],
                ':cogs' => $details['cogs'],
                ':payment_method' => $details['payment_method'],
                ':details' => json_encode($data['details'], JSON_THROW_ON_ERROR),
                ':po' => $details['po'],
                ':unit_no' => $details['unit_no'],
                ':vin' => $details['vin'],
                ':notes' => $details['notes'],
                ':driver_name' => $details['driver_name'],
                ':odometer_reading' => $details['odometer_reading'],
                ':trailer_number' => $details['trailer_number'],
                ':amount_eligible_for_receipt_discount' => $amount_eligible_for_receipt_discount,
                ':early_payment_discount' => $data['earlyPaymentDiscount'],
                ':early_payment_paid_within_days' => $data['earlyPaymentPaidWithinDays'],
                ':net_amount_due_within_days' => $data['netAmountDueWithinDays'],
                ':id' => $invoice_id,
                ':account_number' => $details['account_number'],
                ':purchased_by' => $details['purchased_by'],
                ':versions' => is_array($versions) ? json_encode($versions, JSON_THROW_ON_ERROR) : null,
            ];

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);

            // Update Last Purchase Date
            Client::update_last_purchase_date($client_id, $invoice_id, $details['txn_date'], $db, $store_id);

            // Update Last Sold Date for items
            Inventory::update_last_sold_for_items($data['details'], $details['txn_date'], $store_id, $db);

            // CHECK FOR ANY ERROR
            assert_success();

            // Check for Successful Update
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Update Sales Invoice.');
            if($db -> inTransaction()) $db -> commit();

            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
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
    private static function adjust_inventory(array $details, array $items_information, PDOStatement &$statement_adjust_inventory, int $store_id, string $op, array &$affected_accounts) : void {

        $details_count = count($details);
        for($index = 0; $index < $details_count; ++$index) {
            
            // Item Id
            $item_id = $details[$index]['itemId'];

            // Cache
            if($op === 'deduct') $item_information = $items_information[$item_id];

            // Revenue
            $revenue = $details[$index]['amountPerItem'];
            $account_revenue = $details[$index]['account']['revenue'];

            // Inventory
            if(intval($details[$index]['category']) === CATEGORY_INVENTORY) {

                // Skip Back Order.
                if($details[$index]['isBackOrder'] === 1) continue;

                // Our Buying Cost 
                // Take the Latest Buying Cost.
                // Update Buying cost
                if ($op === 'deduct') $our_buying_cost = $item_information['prices'][$store_id]['buyingCost'];
                else $our_buying_cost = $details[$index]['buyingCost'];

                // Cost of Goods Sold
                $cogs = Utils::round($our_buying_cost * $details[$index]['quantity']);

                // Quantity
                $quantity = $details[$index]['quantity'];

                // Deduct From Inventory when Creating/Updating Fresh Items in Sales Invoice.
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
                if ($is_successful !== true && $statement_adjust_inventory -> rowCount() < 1) {
                    throw new Exception('Cannot Adjust from inventory while updating Sales Invoice.');
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
     * This method will revert old transaction.
     * @param bs_affected_accounts
     * @param is_affected_account 
     * @param details
     * @param statement_adjust_inventory
     * @param store_id
     * @param db
     */
    private static function revert_old_transaction(array &$bs_affected_accounts, array &$is_affected_accounts, array $details, PDOStatement &$statement_adjust_inventory, int $store_id, PDO &$db) : void {
        
        // Offsets
        $affected_accounts = [];

        // Adjust Inventory
        self::adjust_inventory(
            $details['initial']['details'],
            [],
            $statement_adjust_inventory,
            $store_id,
            'add',
            $affected_accounts
        );

        $accounts = array_keys($affected_accounts);
        foreach($accounts as $account) {

            // Adjust COGS in Balance Sheet
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $account,
                $affected_accounts[$account]
            );

            if(self::$is_self_client === false) {
                // Adjust COGS From Income Statement
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    $account,
                    $affected_accounts[$account]
                );
            }
        }

        /* !! Discount */
        // Update Income Statement
        if(self::$is_self_client === false) {
            IncomeStatementActions::update_account_values(
                $is_affected_accounts, 
                AccountsConfig::TOTAL_DISCOUNT,
                -$details['initial']['txnDiscount']
            );
        }

        /* !! GST/HST TAX */ 
        /* Adjust Balance Sheet */
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::GST_HST_CHARGED_ON_SALE,
            -$details['initial']['gstHSTTax']
        );

        /* !! PST TAX */ 
        /* Adjust Balance Sheet */
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::PST_CHARGED_ON_SALE,
            -$details['initial']['pstTax']
        );

        /* !! Payment Method */
        if(!array_key_exists($details['initial']['paymentMethod'] ?? null, PaymentMethod::MODES_OF_PAYMENT)) throw new Exception('Invalid Old Payment Method.');
        $old_payment_method_account = AccountsConfig::get_account_code_by_payment_method($details['initial']['paymentMethod']);

        // Verify Payment Method
        if($old_payment_method_account === null) throw new Exception('Invalid Old Payment Method Account.');
        else {
            $old_sum_total = $details['initial']['sumTotal'] ?? null;
            if(!is_numeric($old_sum_total) || $old_sum_total <= 0) throw new Exception('Invalid Old Sum Total.');

            // Negate
            $old_sum_total = -$old_sum_total;

            // Update Balance Sheet
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                $old_payment_method_account,
                $old_sum_total
            );

            if(self::$is_self_client === false) {
                // Update Income Statement
                IncomeStatementActions::update_account_values(
                    $is_affected_accounts,
                    $old_payment_method_account,
                    $old_sum_total
                );
            }

            // if($details['initial']['paymentMethod'] === PaymentMethod::PAY_LATER) {

            //     // Reverse Customer Aged Summary.
            //     CustomerAgedSummary::update(
            //         $details['initial']['clientDetails']['id'],
            //         $details['initial']['txnDate'],
            //         $old_sum_total,
            //         $store_id,
            //         $db,
            //     );
            // }
        }
    }

    /**
     * This method will fetch transaction by id.
     * @param transaction_id
     * @return array
     */
    private static function fetch_transaction_by_id(int $transaction_id): array {
        $db = get_db_instance();

        // Store Id 
        $store_id = intval($_SESSION['store_id'] ?? 0);

        $statement = $db -> prepare('SELECT * FROM sales_invoice WHERE id = :id AND store_id = :store_id;');
        $statement -> execute([':id' => $transaction_id, ':store_id' => $store_id]);
        $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($record[0])) $record = $record[0];

        // Fetch Previous and Next Transaction ID
        $adjacent_records = Shared::fetch_previous_and_next_transaction_id($store_id, $record['client_id'], SALES_INVOICE, $transaction_id, $db);

        // Format Invoice Record
        $formatted_record = Shared::format_transaction_record($record, SALES_INVOICE);

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

        // Initial Details
        $formatted_record['initial']['details'] = $formatted_record['details'];

        // Add to response
        $formatted_record['itemDetailsForTransactions'] = $cache;

        // Check for Any Sales Return existing for this invoice.
        $statement = $db -> prepare('SELECT id FROM sales_return WHERE sales_invoice_id = :sales_invoice_id;');
        $statement -> execute([':sales_invoice_id' => $transaction_id]);
        $record = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Disable Item Editing if any sales return exists for this item.
        $formatted_record['disableItemEditing'] = isset($record[0]['id']) ? 1 : 0;

        return ['status' => true, 'data' => $formatted_record];
    }

    /**
     * This method will fetch sales invoices for client.
     * @param client_id
     * @param invoice_id
     * @param is_descending
     * @return array
     */
    public static function fetch_sales_invoices_for_client(int $client_id, ?string $invoice_id=null, bool $is_descending=false): array {
        $db = get_db_instance();
        try {
            $query = <<<'EOS'
            SELECT 
                `id`,
                `po`, 
                `unit_no`,
                `vin`,
                `early_payment_discount`,
                `early_payment_paid_within_days`,
                `net_amount_due_within_days`,
                `disable_federal_taxes`,
                `disable_provincial_taxes`,
                `details`,
                `modified`
            FROM 
                sales_invoice 
            WHERE 
                client_id = :client_id 
            AND 
                store_id = :store_id
            EOS;
            $values = [':client_id' => $client_id, ':store_id' => $_SESSION['store_id'] ?? null];
            if(is_numeric($invoice_id)) {
                $query .= ' AND id = :id ';
                $values[':id'] = $invoice_id;
            }

            // Order
            if($is_descending) $query .= ' ORDER BY id DESC ';

            // Limit no. of invoices.
            if(is_null($invoice_id)) $query .= ' LIMIT 25 ';
            $statement = $db -> prepare($query.';');
            $statement -> execute($values);
            $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $sales_invoices = [];

            foreach($result as $si) {

                // Decode
                $details = json_decode($si['details'], true, flags: JSON_THROW_ON_ERROR);

                // Add Item Tag
                Shared::add_item_tag_to_txn_details($details);
                $sales_invoices[$si['id']] = [
                    'po' => $si['po'],
                    'unitNo' => $si['unit_no'],
                    'vin' => $si['vin'],
                    'details' => $details,
                    'lastModifiedTimestamp' => $si['modified'],
                    'earlyPaymentDiscount' => $si['early_payment_discount'],
                    'earlyPaymentPaidWithinDays' => $si['early_payment_paid_within_days'],
                    'netAmountDueWithinDays' => $si['net_amount_due_within_days'],
                    'disableFederalTaxes' => $si['disable_federal_taxes'],
                    'disableProvincialTaxes' => $si['disable_provincial_taxes'],
                ];
            }
            return ['status' => true, 'data' => $sales_invoices];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will fetch invoices details for transfer.
     * @param sales_invoices_ids
     * @param store_id
     * @param db
     * @return array
     */
    private static function fetch_invoices_detail_for_transfer(array $sales_invoices_ids, int $store_id, PDO &$db): array {
        $query = "SELECT * FROM sales_invoice WHERE id IN (:placeholder) AND store_id = $store_id;";

        // Prepare Query
        $ret = Utils::mysql_in_placeholder($sales_invoices_ids, $query);
        $query = $ret['query'];
        $values = $ret['values'];

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $temp = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $sales_invoices_records = [];
        foreach($temp as $t) {
            $t['details'] = json_decode($t['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $sales_invoices_records[$t['id']] = $t;
        }
        return $sales_invoices_records;
    }

    /**
     * This method will transfer invoices items from one store to another.
     * @param data
     * @return array
     */
    private static function transfer_sales_invoice_items(array $data): array {
        $db = get_db_instance();
        try {       
            // Check for Invalid Mode
            if(SYSTEM_INIT_MODE === WASH) throw new Exception('Cannot Transfer Sales Invoice Items for WASH.');
            $db -> beginTransaction();

            // Store Id
            $store_id = isset($_SESSION['store_id']) ? intval($_SESSION['store_id']) : null;
            if(!is_numeric($store_id)) throw new Exception('Invalid Store ID.');

            // Transfer To Store
            $transfer_to = is_numeric($data['transfer_to'] ?? null) ? intval($data['transfer_to']) : null;
            if(!is_numeric($transfer_to)) throw new Exception('Invalid Transfer Store ID.');

            // Validate
            if($store_id === $transfer_to) throw new Exception('Invalid Transfer To Store.');
            
            $temp = explode(',', $data['sales_invoices']);
            $sales_invoices_ids = [];
            foreach($temp as $s) {
                if(!is_numeric($s)) throw new Exception('Invalid Sales Invoice ID: '. $s);
                else {
                    $sales_invoice_id = intval(trim($s));
                    if(!in_array($sales_invoice_id, $sales_invoices_ids)) $sales_invoices_ids[]= $sales_invoice_id;
                }
            }

            // Fetch Invoices Details for Transfer
            $sales_invoices_details = self::fetch_invoices_detail_for_transfer(
                $sales_invoices_ids,
                $store_id,
                $db,
            );

            // Check for Already transferred invoices.
            foreach($sales_invoices_details as $si) {
                if($si['is_invoice_transferred'] === 1) throw new Exception('Sales Invoice #'. $si['id']. ' has already been transferred.');
            }

            // Unknown Error; possible invoice not found.
            if(count($sales_invoices_details) !== count($sales_invoices_ids)) {
                throw new Exception('Please check for Valid Sales Invoices and try again.');
            }

            // Statement Flag
            $statement_flag = $db -> prepare('UPDATE sales_invoice SET is_invoice_transferred = 1 WHERE id = :id;');

            // Check for Valid Customers
            foreach($sales_invoices_details as $si) {

                // Check for White Listed Clients
                if(Client::is_self_client($si['client_id']) === false) {
                    throw new Exception('Invalid Client for Sales Invoice #: '. $si['id']);
                }

                // Check whether sales invoice belongs to valid store.
                if(Client::SELF_CLIENT_WHITELIST[SYSTEM_INIT_MODE][$si['client_id']] !== $transfer_to) throw new Exception('Sales Invoice #'. $si['id']. ' does not belong to the selected store.');

                // Update Flag
                $is_successful = $statement_flag -> execute([':id' => $si['id']]);
                if($is_successful !== true || $statement_flag -> rowCount() < 1) throw new Exception('Unable to Update Sales Invoice Flag for #'. $si['id']);
            }

            // Extract Items
            $item_ids = [];
            foreach($sales_invoices_details as $si) {
                $details = $si['details'];
                foreach($details as $d) $item_ids[]= $d['itemId'];
            }

            // Inventory Details of Transfer Store.
            $inventory_details = Inventory::fetch_item_inventory_details_by_id($item_ids, $transfer_to, $db);
            if($inventory_details['status'] === false) throw new Exception($inventory_details['message']);
            else $inventory_details = $inventory_details['data'];

            $payload = [];

            foreach($sales_invoices_details as $si) {
                $details = $si['details'];
                foreach($details as $item) {
                    $item_id = $item['itemId'];
                    if(!isset($payload[$item['itemId']])) $payload[$item_id] = [
                        'id' => $item_id,
                        'identifier' => $item['identifier'],
                        'description' => $item['description'],
                        'unit' => $item['unit'],
                        'quantity' => 0,
                        'buyingCost' => 0,
                        'aisle' => $inventory_details[$item_id][$store_id]['aisle'] ?? '',
                        'shelf' => $inventory_details[$item_id][$store_id]['shelf'] ?? '',
                        'column' => $inventory_details[$item_id][$store_id]['column'] ?? '',
                        'existingQuantity' => 0,
                    ];

                    $payload[$item_id]['quantity'] += $item['quantity'];
                    $payload[$item_id]['buyingCost'] += $item['amountPerItem'];
                }
            }

            // Average Out Buying Price
            $item_keys = array_keys($payload);
            $count = count($item_keys);
            for($i = 0; $i < $count; ++$i) {
                $item_id = $item_keys[$i];
                $payload[$item_id]['buyingCost'] = $payload[$item_id]['buyingCost'] / $payload[$item_id]['quantity'];
            }

            // Adjust Inventory
            $ret = Inventory::adjust_inventory($payload, $transfer_to, $db);
            if($ret['status'] === false) throw new Exception($ret['message']);

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will convert quotation to details required by sales invoice.
     * @param quotation
     * @return array
     */
    private static function convert_quotation_to_sales_invoice(array $quotation): array {
        $details = [];
        $details['transactionType'] = SALES_INVOICE;
        $details['txnDate'] = Utils::get_business_date($quotation['store_id']). ' 12:00:00';
        $details['unitNo'] = '.';
        $details['accountNumber'] = $quotation['account_number'];
        $details['paymentMethod'] = PaymentMethod::MODES_OF_PAYMENT['Pay Later'];
        $details['details'] = $quotation['details'];
        $details['salesRepId'] = $quotation['sales_rep_id'];
        $details['storeId'] = $quotation['store_id'];
        $details['disableFederalTaxes'] = $quotation['disable_federal_taxes'];
        $details['disableProvincialTaxes'] = $quotation['disable_provincial_taxes'];
        $details['earlyPaymentDiscount'] = $quotation['early_payment_discount'];
        $details['earlyPaymentPaidWithinDays'] = $quotation['early_payment_paid_within_days'];
        $details['netAmountDueWithinDays'] = $quotation['net_amount_due_within_days'];

        // Add Client Details
        $details['clientDetails'] = [
            'id' => $quotation['client_id'],

            /* Pass on the credit transaction status. */
            'disableCreditTransactions' => $quotation['disable_credit_transactions'],

            /* Taxes */ 
            'disableFederalTaxes' => $details['disableFederalTaxes'],
            'disableProvincialTaxes' => $details['disableProvincialTaxes'],

            /* Primary Details */ 
            'primaryDetails' => $quotation['primary_details'],

            /* Shipping Address */ 
            'shippingAddresses' => $quotation['shipping_addresses'],

            /* Is Self Client */
            'isSelfClient' => $quotation['is_self_client'],

            /* Last Modified Timestamp */ 
            'lastModifiedTimestamp' => $quotation['client_last_modified_timestamp'],
        ];
        return $details;
    }

    /**
     * This method will convert quotations to invoices.
     * @param data
     * @return array
     */
    private static function convert_quote_to_invoice(array $data): array {
        $db = get_db_instance();
        try {
            // Check for Invalid Mode
            if(SYSTEM_INIT_MODE === WASH) throw new Exception('Cannot Convert Quote for Wash.');
            $db -> beginTransaction();
            $temp = explode(',', $data['quotations'] ?? '');
            $quote_count = count($temp);

            // Quotations
            $quotation_ids = [];

            // Check for Valid Count
            if($quote_count > 1) throw new Exception('Only One Convert is Allowed at a time.');

            // Verify Quotations Ids
            for($i = 0; $i < $quote_count; ++$i) {
                $quote_id = trim($temp[$i]);
                if(!is_numeric($quote_id)) throw new Exception('Invalid Quotation ID: '. $quotation_ids[$i]);
                if(!in_array($quote_id, $quotation_ids)) $quotation_ids[]= $quote_id;
            }

            // Fetch Quotations
            $quotations = Quotations::fetch_quotations_by_id_for_conversion($quotation_ids);
            if($quotations['status'] === false) throw new Exception($quotations['message']);
            else $quotations = $quotations['data'];

            // Check for All Quotations Validity
            if(count($quotations) !== count($quotation_ids)) throw new Exception('Quotation Not Found.');

            foreach($quotations as $quotation) {

                // Check for Credit Transactions
                if($quotation['disable_credit_transactions'] === 1) {
                    throw new Exception('Cannot convert Quote #: '. $quotation['id']. ' because credit transactions are disabled. Create a Sales Invoice Instead.');
                }

                $converted_details = self::convert_quotation_to_sales_invoice($quotation);

                // Create A New Sales Invoice
                $status = self::create_sales_invoice($converted_details, $db);
                if($status['status'] === false) throw new Exception($status['message']);
            }

            assert_success();

            // Commit
            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will generate packaging slip.
     * @param invoice_id
     * @return void 
     */
    public static function generate_packaging_slip(int $invoice_id) : void {
        $txn = PrepareDetails_SI_SR_CN_DN_QT::prepare_details(
            SALES_INVOICE, 
            Shared::fetch_transaction_for_pdf($invoice_id, SALES_INVOICE)
        );
        GeneratePDF::packaging_slip($txn);
    }

    /**
     * This method will fetch last transaction date of client.
     * @param client_id
     * @param db
     * @return string
     */
    public static function fetch_last_transaction_of_client(int $client_id, PDO &$db): string {
        $statement = $db -> prepare('SELECT `date` FROM sales_invoice WHERE client_id = :client_id ORDER BY `date` DESC, id DESC LIMIT 1;');
        $statement -> execute([':client_id' => $client_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(count($result) > 0) return $result[0]['date'];
        return '';
    }

    /**
     * This method will process sales invoice.
     * @param data
     * @return array
     */
    public static function process(array $data): array {
        try {
            // Result
            $result = [];

            switch($data['action']) {
                case 'create_txn': $result = self::create_sales_invoice($data); break;
                case 'update_txn': $result = self::update_sales_invoice($data); break;
                case 'fetch_transaction_by_id': $result = self::fetch_transaction_by_id($data['transaction_id']); break;
                case 'fetch_sales_invoices_for_client': $result = self::fetch_sales_invoices_for_client($data['client_id'], $data['invoice_id']); break;
                case 'txn_search': $result = Shared::search($data); break;
                case 'print': $result = Shared::generate_pdf($data['txn_queue']); break;
                case 'txn_email': $result = Shared::email_si_sr_cn_dn_qt($data['txn_queue'][0]['id'], $data['txn_queue'][0]['type']); break;
                case 'packaging_slip': self::generate_packaging_slip($data['id']); break;
                case 'transfer_invoice': $result = self::transfer_sales_invoice_items($data); break;
                case 'convert_quote_to_invoice': $result = self::convert_quote_to_invoice($data); break;
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