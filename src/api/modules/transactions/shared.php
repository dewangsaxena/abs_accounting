<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/prepare_pdf_details.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";

/**
 * This class contains shared queries/configurations.
 */
class Shared {

    /* Operation Tags */ 
    public const CREATE_TXN = 'create_txn';
    public const UPDATE_TXN = 'update_txn';
    public const TRANSFER_INVOICE = 'transfer_invoice';
    public const CONVERT_QUOTE_TO_INVOICE = 'convert_quote_to_invoice';

    /* Minimum Profit Margin Required */ 
    private const MINIMUM_PROFIT_MARGIN_REQUIRED = 25;

    /* Adjust Inventory */ 
    public const ADJUST_INVENTORY_QUANTITY_AND_VALUE = <<<'EOS'
    UPDATE 
        inventory
    SET
        `quantity` = `quantity` + :quantity,
        modified = CURRENT_TIMESTAMP
    WHERE 
        item_id = :item_id
    AND 
        store_id = :store_id;
    EOS;

    // Table Name Index
    private const TABLE_NAME_INDEX = [
        SALES_INVOICE => ' sales_invoice ',
        SALES_RETURN => ' sales_return ',
        CREDIT_NOTE => ' credit_note ',
        DEBIT_NOTE => ' debit_note ',
        QUOTATION => ' quotation ',
        RECEIPT => ' receipt ',
    ];

    // Keys to Remove
    public const KEYS_TO_REMOVE_SI_CN_DN_QT = ['returnQuantity', /*'invoiceAmount'*/];

    // No. of Records to fetch Per Request
    private const NO_OF_RECORDS_PER_REQUEST = 200;

    /**
     * Remove Keys
     * @param details
     * @param keys
     * @return void
     */
    public static function remove_keys(array &$details, array $keys): void {
        $count = count($details);

        // Remove Return Quantity
        for($i = 0; $i < $count; ++$i) {
            foreach($keys as $key) unset($details[$i][$key]);
        }
    }

    /**
     * This method will format VIN.
     * @param vin
     * @return string
     */
    private static function format_vin(string $vin) : string {
        return 
            substr($vin, 0, 4). '-'.
            substr($vin, 4, 4). '-'.
            substr($vin, 8, 4). '-'.
            substr($vin, 12);
    }

    /**
     * This method will get version timestamps from old details. 
     * @param timestamps
     * @param store_id
     * @return array
     */
    private static function get_version_timestamps_from_versions(array $timestamps, int $store_id): array {
        $temp = [];
        foreach($timestamps as $timestamp) {
            $temp[$timestamp] = Utils::convert_to_local_timestamp_from_utc_unix_timestamp($timestamp, $store_id);
        }
        return $temp;
    }

    /**
     * This method will format transaction record.
     * @param record
     * @param transaction_type
     * @return ?array
     */
    public static function format_transaction_record(array $record, int $transaction_type): ?array {

        // Client Details
        $ret_value = Client::fetch(['id' => $record['client_id']]);
        if($ret_value['status'] === false) return null;

        // Details
        $details = json_decode($record['details'], true, flags: JSON_THROW_ON_ERROR);

        // Add Item Tag
        self::add_item_tag_to_txn_details($details);

        // Versions
        if(is_null($record['versions']) === false) {
            $versions = json_decode($record['versions'], true, flags: JSON_THROW_ON_ERROR);

            $version_keys = array_keys($versions);
            foreach($version_keys as $version) {
                self::add_item_tag_to_txn_details($versions[$version]);
            }
            $version_keys = self::get_version_timestamps_from_versions(array_keys($versions), $record['store_id']);
        }
        else {
            $versions = null;
            $version_keys = null;
        }

        if(isset($record['shipping_addresses'])) {
            $shipping_addresses = json_decode($record['shipping_addresses'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if(count($shipping_addresses) > 0) {
                $ret_value['data'][0]['shippingAddresses'] = $shipping_addresses;
            }
        }

        // $details_count = count($details);
        // for($i = 0; $i < $details_count; ++$i) $details[$i]['isExisting'] = 1;
        $response = [
            'id' => $record['id'],
            'transactionType' => $transaction_type,
            'clientDetails' => $ret_value['data'][0],
            'txnDate' => Utils::format_date_for_front_end($record['date']),
            'details' => $details,
            'subTotal' => $record['sub_total'],
            'txnDiscount' => $record['txn_discount'],
            'cogs' => $record['cogs'] ?? 0,
            'gstHSTTax' => $record['gst_hst_tax'],
            'pstTax' => $record['pst_tax'],
            'creditAmount' => $record['credit_amount'],
            'sumTotal' => $record['sum_total'],
            'salesRepId' => $record['sales_rep_id'],
            'storeId' => $record['store_id'],
            'notes' => $record['notes'],
            'disableFederalTaxes' => $record['disable_federal_taxes'],
            'disableProvincialTaxes' => $record['disable_provincial_taxes'],
            'lastModifiedTimestamp' => $record['modified'],
            'paymentMethod' => $record['payment_method'] ?? PaymentMethod::PAY_LATER,
            'earlyPaymentDiscount' => $record['early_payment_discount'] ?? 0,
            'earlyPaymentPaidWithinDays' => $record['early_payment_paid_within_days'] ?? 0,
            'netAmountDueWithinDays' => $record['net_amount_due_within_days'] ?? 0,
            'isInvoiceTransferred' => $record['is_invoice_transferred'] ?? 1,
            'accountNumber' => $record['account_number'] ?? '',
            'purchasedBy' => $record['purchased_by'] ?? '',
            'versionKeys' => $version_keys,
            '__lockCounter' => $record['__lock_counter'] ?? 0,
            'initial' => [
                'clientDetails' => $ret_value['data'][0],
                'txnDate' => $record['date'],
                'paymentMethod' => $record['payment_method'],
                'sumTotal' => $record['sum_total'],
                'subTotal' => $record['sub_total'],
                'gstHSTTax' => $record['gst_hst_tax'],
                'pstTax' => $record['pst_tax'],
                'txnDiscount' => $record['txn_discount'],
            ],
        ];

        // Add Transaction Specific Details
        if($transaction_type === SALES_INVOICE) {
            $response = array_merge($response, [
                'po' => $record['po'] ?? '',
                'unitNo' => $record['unit_no'] ?? '',
                'vin' => strlen($record['vin']) === 17 ? self::format_vin($record['vin']) : '',
                'driverName' => $record['driver_name'] ?? '',
                'odometerReading' => $record['odometer_reading'] ?? '',
                'trailerNumber' => $record['trailer_number'] ?? '',
            ]);
        }

        return $response;
    }

    /**
     * This method will fetch previous and next transaction id.
     * @param store_id
     * @param client_id
     * @param transaction_type
     * @param transaction_id
     * @param db
     * @return array
     */
    public static function fetch_previous_and_next_transaction_id(int $store_id, int $client_id, int $transaction_type, int $transaction_id, PDO $db=null): array {
        if($db === null) $db = get_db_instance();

        // Select Table
        switch($transaction_type) {
            case SALES_INVOICE: $table_name = 'sales_invoice'; break;
            case SALES_RETURN: $table_name = 'sales_return'; break;
            case CREDIT_NOTE: $table_name = 'credit_note'; break;
            case DEBIT_NOTE: $table_name = 'debit_note'; break;
            case QUOTATION: $table_name = 'quotations'; break;
            default: $table_name = '';
        }

        $query = <<<EOS
        SELECT * FROM (
            SELECT id, "previous_txn_id" AS `key` FROM $table_name WHERE id < :transaction_id AND store_id = :store_id AND client_id = :client_id ORDER BY date DESC, id DESC LIMIT 1 
        ) AS previous_txn_id
        UNION ALL
        SELECT * FROM (
            SELECT id, "next_txn_id" AS `key` FROM $table_name WHERE id > :transaction_id AND store_id = :store_id AND client_id = :client_id ORDER BY date ASC, id ASC LIMIT 1 
        ) AS next_txn_id
        EOS;

        // Default IDS
        $ids = ['previousTxnId' => null, 'nextTxnId' => null];

        try {
            $statement = $db -> prepare($query);
            $statement -> execute([':transaction_id' => $transaction_id, ':store_id' => $store_id, ':client_id' => $client_id]);
            $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
            
            // Return IDS
            if(count($results) > 0) {
                foreach($results as $result) {
                    if($result['key'] === 'previous_txn_id') $ids['previousTxnId'] = $result['id'];
                    else if($result['key'] === 'next_txn_id') $ids['nextTxnId'] = $result['id'];
                }
                return $ids;
            }
            else return $ids;
        }
        catch(Exception $e) {
            return $ids;
        }
    }

    /**
     * Calculate Profit Margin
     * @param base_price
     * @param selling_price
     * @return float
     */
    public static function calculate_profit_margin($base_price, $selling_price) {
        return (($selling_price / $base_price) - 1) * 100;
    }

    /**
     * This method will calculate the total discount available on receipt.
     * @param items
     * @param is_sales_return
     * @return float
     */
    public static function calculate_amount_eligible_for_receipt_discount(array $items, bool $is_sales_return=false): float {
        $total_discount_available = 0;
        $property_name = $is_sales_return === true ? 'returnQuantity': 'quantity';
        foreach($items as $item) {
            // Process for PARTS Only, skip Service.
            if($item['category'] == CATEGORY_INVENTORY) {
                if(isset($item[$property_name]) && $item[$property_name] > 0) {
                    $profit_margin_per_item = self::calculate_profit_margin($item['buyingCost'], $item['pricePerItem']);
                    if($profit_margin_per_item > self::MINIMUM_PROFIT_MARGIN_REQUIRED && $item['pricePerItem'] >= $item['originalSellingPrice'] && $item['discountRate'] == 0) {
                        $total_tax = $item['gstHSTTaxRate'] + $item['pstTaxRate'];
                        $tax_on_amount = ($item['pricePerItem'] * $total_tax) / 100;
                        $price_per_item_after_tax = ($item['pricePerItem'] + $tax_on_amount);
                        $total_discount_available += ($price_per_item_after_tax * $item[$property_name]);
                    }
                }
            }
        }
        return Utils::round($total_discount_available);
    }

    /**
     * This method will validate the items details for validity.
     * @param items
     * @param disable_federal_taxes
     * @param disable_provincial_taxes
     * @return array
     */
    public static function validate_items_details_for_credit_and_debit_note(array $items, int $disable_federal_taxes, int $disable_provincial_taxes) : array {

        // Item Keys
        $item_keys = [
            'itemId',
            'amountPerItem',
            'basePrice',
            'discountRate',
            'pricePerItem',
            'quantity',
            'gstHSTTaxRate',
            'pstTaxRate',
        ];

        // Validate Item Count
        if(count($items) < 1) return ['status' => false, 'message' => 'Invalid Items Count.'];

        // Validate Item fields
        foreach($items as $item) {

            // Identifier
            $identifier = $item['identifier'];

            // Uninitialized Item Found
            if(isset($item['itemId']) === false || is_null($item['itemId'])) return ['status' => false, 'message' => 'Uninitialized Item.'];
            foreach($item_keys as $key) {
                if(!isset($item[$key])) return ['status' => false, 'message' => "$key not set for $identifier."];
                if(!is_numeric($item[$key])) return ['status' => false, 'message' => "$key not numeric for $identifier."];
                if(floatval($item[$key]) < 0) return ['status' => false, 'message' => "$key less than 0 for $identifier."];
            }

            $keys = ['quantity', 'basePrice', 'amountPerItem'];
            foreach($keys as $key) if(floatval($item[$key]) <= 0) return ['status' => false, 'message' => "$key less than or equal to 0 for $identifier."];

            // Check for GST/HST Tax Rate
            if($disable_federal_taxes === 0 && $item['gstHSTTaxRate'] <= 0) return ['status' => false, 'message' => "GSTHSTTaxRate less than or equal to 0 for $identifier."];

            // Check for PST if applicable
            if($disable_provincial_taxes === 0 && (StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['pst_tax_rate'] > 0) && floatval($item['pstTaxRate']) < 0) {
                return ['status' => false, 'message' => 'PST Tax Invalid.'];
            }
        }
        return ['status' => true];
    }

    /**
     * This method will calculate amounts by transaction type.
     * @param transaction_type
     * @param items
     * @param disable_federal_taxes
     * @param disable_provincial_taxes
     * @return array
     */
    public static function calculate_amount_by_transaction_type(int $transaction_type, array $items, int $disable_federal_taxes, int $disable_provincial_taxes): array {
        switch($transaction_type) {
            case CREDIT_NOTE: return self::calculate_amount($items, $disable_federal_taxes, $disable_provincial_taxes);
            case DEBIT_NOTE: return self::calculate_amount($items, $disable_federal_taxes, $disable_provincial_taxes);
            case SALES_INVOICE: return SalesInvoice::calculate_amount($items, $disable_federal_taxes, $disable_provincial_taxes);
            case SALES_RETURN: return SalesReturn::calculate_amount($items, $disable_federal_taxes, $disable_provincial_taxes);
            case QUOTATION: return Quotations::calculate_amount($items, $disable_federal_taxes, $disable_provincial_taxes);
        }
    }

    /**
     * This method will calculate amount.
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

        // Select Tax Rate
        $federal_tax_rate = $disable_federal_taxes ? 0 : GST_HST_TAX_RATE;
        $provincial_tax_rate = $disable_provincial_taxes ? 0 : PROVINCIAL_TAX_RATE;
        foreach($items as $item) { 
            $total += $item['amountPerItem'];
            $pst_tax += (($item['amountPerItem'] * $provincial_tax_rate) / 100);
            $gst_hst_tax += (($item['amountPerItem'] * $federal_tax_rate) / 100);
            $base_price = $item['basePrice'];
            $quantity = $item['quantity'];
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
        ];
    }

    /**
     * This method will validate details. If valid validated details will be returned, else error message will be returned.
     * @param data
     * @param txn_type
     * @return array
     */
    public static function validate_details_for_credit_and_debit_note(array $data, string $txn_type=null): array {
        // Client Id 
        $client_id = $data['clientDetails']['id'] ?? null;

        // Check for valid id 
        if(!Validate::is_numeric($client_id)) {
            throw new Exception('Cannot Process Txn for Invalid Customer.');
        }

        // Check for Credit Eligible Transaction
        Client::is_credit_txn_eligible_for_client($data['clientDetails']['primaryDetails']);

        // Validate Store
        $store_id = intval($_SESSION['store_id']);
        if(key_exists($store_id, StoreDetails::STORE_DETAILS) === false) throw new Exception('Store is Invalid.');
        if($store_id !== intval($data['storeId'])) throw new Exception('Store does not match with current session.');

        // Sales Rep Id
        if($data['salesRepId'] === 0) throw new Exception('Please select Sales Representative.');

        // Check whether credit transactions are disabled for this client.
        if($txn_type === 'debit_note') {
            if(!Validate::is_numeric(strval($data['clientDetails']['disableCreditTransactions']))) throw new Exception('Invalid Disable Credit Transaction Value.');
            $disable_credit_txn = intval($data['clientDetails']['disableCreditTransactions'] ?? 1);
            if($disable_credit_txn === 1) throw new Exception('Credit Transactions are disabled for this customer due to Non-payment in the past.');
        }
    
        // Transaction Date
        $transaction_date = Utils::get_YYYY_mm_dd(
            Utils::convert_utc_str_timestamp_to_localtime($data['txnDate'], $store_id),
            $store_id
        );
        if($transaction_date === null) throw new Exception('Invalid Date.');

        // Disable Federal Taxes
        $disable_federal_taxes = $data['disableFederalTaxes'] ?? null;
        $disable_provincial_taxes = $data['disableProvincialTaxes'] ?? null;

        // Check for Disabled Taxes
        if($disable_federal_taxes !== $data['clientDetails']['disableFederalTaxes']) {
            throw new Exception('Federal Tax Status cannot be changed for this Txn.');
        }

        if($disable_provincial_taxes !== $data['clientDetails']['disableProvincialTaxes']) {
            throw new Exception('Provincial Tax Status cannot be changed for this Txn.');
        }

        // Validate Items Information
        $valid_ret_value = self::validate_items_details_for_credit_and_debit_note(
            $data['details'],
            $disable_federal_taxes,
            $disable_provincial_taxes,
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

        // Transaction Discount
        if(!is_numeric($txn_discount)) throw new Exception('Total Discount should be numeric.');
        if($txn_discount < 0) throw new Exception('Total Discount cannot be zero or negative.');

        // Notes
        $notes = isset($data['notes']) ? trim(ucfirst($data['notes'])) : '';

        // Return data
        return [
            'client_id' => $client_id,
            'store_id' => $store_id,
            'txn_date' => $transaction_date,
            'sum_total' => $sum_total,
            'sub_total' => $sub_total,
            'pst_tax' => $pst_tax,
            'gst_hst_tax' => $gst_hst_tax,
            'txn_discount' => $txn_discount,
            'notes' => $notes,
        ];
    }

    /**
     * This method will update client amount owing for credit and debit note transactions.
     * @param data 
     * @param sum_total
     * @param db
     * @param mode
     * @throws Exception
     */
    public static function update_client_amount_owing_for_credit_and_debit_note(array $data, float $sum_total, PDO &$db, string $mode): void {

        // Is Credit
        $is_credit = $mode === 'credit_note';

        // Check for Client Change
        if($data['clientDetails']['id'] != $data['initial']['clientDetails']['id']) {
                
            // Revert Amount owing for old client.
            Client::update_amount_owing_of_client(
                $data['initial']['clientDetails']['id'], 
                $is_credit ? $data['initial']['sumTotal']: -$data['initial']['sumTotal'], 
                $db
            );

            // Add Old Amount Owing to Current Client
            // This will be adjusted in below statements.
            Client::update_amount_owing_of_client(
                $data['clientDetails']['id'], 
                $is_credit ? -$data['initial']['sumTotal']: $data['initial']['sumTotal'], 
                $db
            );
        }

        // Reverse Client Amount Owing.
        Client::update_amount_owing_of_client(
            $data['clientDetails']['id'], 
            $is_credit ? $data['initial']['sumTotal']: -$data['initial']['sumTotal'], 
            $db
        );

        // Now Update Client with new Amount Owing
        Client::update_amount_owing_of_client(
            $data['clientDetails']['id'], 
            $is_credit ? -$sum_total: $sum_total, 
            $db
        );
    }

    /**
     * This method will fetch credit or debit transaction by id.
     * @param transaction_id
     * @param transaction_type
     * @return array
     */
    public static function fetch_credit_or_debit_transaction_by_id(int $transaction_id, int $transaction_type): array {
        try {
            $db = get_db_instance();
            $table_name = '';
            switch($transaction_type) {
                case CREDIT_NOTE: $table_name = 'credit_note'; break;
                case DEBIT_NOTE: $table_name = 'debit_note'; break;
                default: throw new Exception('Invalid Transaction Type.');
            }

            // Store Id 
            $store_id = intval($_SESSION['store_id'] ?? null);

            $statement = $db -> prepare("SELECT * FROM $table_name WHERE id = :id AND store_id = :store_id;");
            $statement -> execute([':id' => $transaction_id, ':store_id' => $store_id]);
            $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(isset($record[0])) $record = $record[0];  

            // Fetch Previous and Next Transaction ID
            $adjacent_records = Shared::fetch_previous_and_next_transaction_id(
                $store_id, 
                $record['client_id'],
                $transaction_type, 
                $transaction_id, 
                $db
            );

            // Format Txn Record
            $formatted_record = Shared::format_transaction_record($record, $transaction_type);

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

            return ['status' => true, 'data' => $formatted_record];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will limit records.
     * @param records
     * @param offset
     * @return array
     */
    private static function limit_records(array &$records, int $offset): array {
        return array_slice($records, $offset, self::NO_OF_RECORDS_PER_REQUEST);
    }

    /**
     * This method will search for transactions.
     * @param data
     * @return array
     */
    public static function search(array $data) : array {
        try {
            // TXN TYPE
            $transaction_type = isset($data['transactionType']) ? intval($data['transactionType']): null;
            if(!is_numeric($transaction_type)) throw new Exception('Invalid Transaction Type.');

            // Table Name
            $table_name = self::TABLE_NAME_INDEX[$transaction_type];

            // Common Column
            $common_column = '';
            if($transaction_type !== RECEIPT) {
                $common_column = ' txn_tb.sub_total AS subTotal,';
                if($transaction_type !== QUOTATION) $common_column .= 'txn_tb.credit_amount AS creditAmount,';
            }

            // Sales Invoice
            if($transaction_type === SALES_INVOICE) $common_column .= ' txn_tb.cogs,';

            // Send Sales Invoice ID when txn is Sales Return
            if($transaction_type === SALES_RETURN) $common_column .= ' txn_tb.sales_invoice_id AS salesInvoiceId,';

            // Store Id
            $store_id = isset($_SESSION['store_id']) ? intval($_SESSION['store_id']) : null;

            // Values
            $values = [];

            // Query
            $query = <<<EOS
            SELECT 
                txn_tb.id,
                txn_tb.`date`,
                txn_tb.sum_total AS sumTotal,
                $common_column
                c.`name` AS clientName
            FROM 
                $table_name AS txn_tb
            LEFT JOIN 
                clients AS c 
            ON 
                txn_tb.client_id = c.id
            WHERE 
                txn_tb.store_id = :store_id 
            EOS;

            // Fetch Only Non-Concealed Receipts.
            if($transaction_type === RECEIPT) $query .= ' AND txn_tb.do_conceal = 0 ';

            // Add Filters
            $values[':store_id'] = $store_id;

            // Flag
            $is_any_filter_selected = false;

            // Transaction ID
            if(is_numeric($data['transactionId'] ?? null)) {
                $query .= ' AND txn_tb.id = :id ';
                $values[':id'] = $data['transactionId'];
                $is_any_filter_selected = true;
            }

            // Client Id
            if(is_numeric($data['clientId'] ?? null)) {
                $query .= ' AND client_id = :clientId ';
                $values[':clientId'] = $data['clientId'];
                $is_any_filter_selected = true;
            }

            // Txn Start Date
            if(isset($data['txnStartDate'][0])) {
                $query .= ' AND txn_tb.`date` >= :txnStartDate ';
                $values[':txnStartDate'] = Utils::get_YYYY_mm_dd(
                    Utils::convert_utc_str_timestamp_to_localtime($data['txnStartDate'], $store_id), 
                    $store_id
                );
                $is_any_filter_selected = true;
            }

            // Txn End Date
            if(isset($data['txnEndDate'][0])) {
                $query .= ' AND txn_tb.`date` <= :txnEndDate ';
                $values[':txnEndDate'] = Utils::get_YYYY_mm_dd(
                    Utils::convert_utc_str_timestamp_to_localtime($data['txnEndDate'], $store_id), 
                    $store_id
                );
                $is_any_filter_selected = true;
            }

            // Month
            $month = $data['month'] ?? null;
            if(is_numeric($month) && $month > 0) {
                $query .= ' AND txn_tb.`date` LIKE :__month ';

                // Prepend 0 
                if($data['month'] < 10) $data['month'] = '0'. $data['month'];

                // Add Year to narrow search to current year, if year is not given.
                $year = null;
                if(is_numeric($data['year'] ?? null)) $year = $data['year'];
                else $year = date('Y');
                $values[':__month'] = "$year-".$data['month'].'-__';

                $is_any_filter_selected = true;
            }

            // Year
            if(is_numeric($data['year'] ?? null)) {
                $query .= ' AND txn_tb.`date` LIKE :__year ';
                $values[':__year'] = $data['year'].'-__-__';
                $is_any_filter_selected = true;
            }

            // Transaction Amount
            if(is_numeric($data['transactionAmount'] ?? null)) {
                $query .= ' AND txn_tb.`sum_total` = :sumTotal ';
                $values[':sumTotal'] = $data['transactionAmount'];
                $is_any_filter_selected = true;
            }

            // PO Number
            if(isset($data['poNumber'])) {
                $query .= ' AND txn_tb.`po` LIKE :poNumber ';
                $values[':poNumber'] = '%'. $data['poNumber']. '%';
                $is_any_filter_selected = true;
            }

            // Unit Number
            if(isset($data['unitNumber'])) {
                $query .= ' AND txn_tb.`unit_no` LIKE :unitNumber ';
                $values[':unitNumber'] = '%'. $data['unitNumber']. '%';
                $is_any_filter_selected = true;
            }

            // VIN 
            if(isset($data['vinNumber'])) {
                $query .= ' AND txn_tb.vin LIKE :vinNumber ';
                $values[':vinNumber'] = '%'. $data['vinNumber']. '%';
                $is_any_filter_selected = true;
            }

            // Item Identifier
            if(is_numeric($data['itemIdentifier'] ?? null)) {
                $query .= ' AND txn_tb.`details` LIKE :itemIdentifier ';
                $values[':itemIdentifier'] = '%"itemId":'. $data['itemIdentifier'].',%';
                $is_any_filter_selected = true;
            }

            // Find By Core
            // if(isset($data['itemIdentifier'])) {
            //     $query .= ' AND `details` LIKE :itemIdentifier ';
            //     $values[':itemIdentifier'] = '%"itemId":'. $data['itemIdentifier'].',%';
            // }

            // Sales Rep Id.
            if(isset($data['salesRepId']) && is_numeric($data['salesRepId']) && intval($data['salesRepId']) !== 0) {
                $query .= ' AND txn_tb.sales_rep_id = :sales_rep_id ';
                $values[':sales_rep_id'] = $data['salesRepId'];
                $is_any_filter_selected = true;
            }

            // Find Unpaid
            if(($data['findUnpaidTransactions'] ?? 0) === 1) {
                $query .= ' AND txn_tb.`credit_amount` > 0 ';
                $is_any_filter_selected = true;
            }

            // Find Paid
            if(($data['findPaidTransactions'] ?? 0) === 1) {
                $query .= ' AND txn_tb.`credit_amount` = 0 ';
                $is_any_filter_selected = true;
            }

            // Find BackOrder
            if(($data['findByBackOrder'] ?? 0) === 1) {
                $query .= ' AND txn_tb.details LIKE \'%"isBackOrder":1%\'';
                $is_any_filter_selected = true;
            }

            // Search by Sales Invoice Id
            if(is_numeric($data['salesInvoiceId'])) {
                $query .= ' AND txn_tb.sales_invoice_id = :salesInvoiceId ';
                $values[':salesInvoiceId'] = intval($data['salesInvoiceId']);
                $is_any_filter_selected = true;
            }

            // Address
            if(isset($data['address'][0])) {
                $query .= ' AND txn_tb.`shipping_addresses` LIKE :addresses ';
                $values[':addresses'] = '%'. $data['address'] .'%';
                $is_any_filter_selected = true;
            }

            // Payment Method 
            $__payment_method = intval($data['paymentMethod'] ?? -1);
            if($__payment_method !== -1) {
                $query .= ' AND txn_tb.`payment_method` = :paymentMethod ';
                $values[':paymentMethod'] = $data['paymentMethod'];
                $is_any_filter_selected = true;
            }

            // Skip Forgiven Receipts unless explicitly selected.
            if ($transaction_type === RECEIPT) {
                // Payment Method not selected
                if($__payment_method === -1) {
                    $query .= ' AND txn_tb.`payment_method` != :forgiven ';
                    $values[':forgiven'] = PaymentMethod::FORGIVEN;
                }
            }

            // Find Transaction By Type and ID
            if(isset(TRANSACTION_NAMES[intval($data['transactionTypeForReceipt'] ?? -1)]) === true && is_numeric($data['transactionNumberForReceipt'] ?? null)) {
                $query .= ' AND txn_tb.`details` LIKE :txn_fingerprint ';
                $fingerprint = TRANSACTION_NAMES_ABBR[intval($data['transactionTypeForReceipt'])].'-'. intval($data['transactionNumberForReceipt']);
                $values[':txn_fingerprint'] = '%"txnId":"'. $fingerprint.'",%';
                $is_any_filter_selected = true;
            }

            $query .= <<<'EOS'
            ORDER BY 
                txn_tb.`date` DESC,
                txn_tb.id DESC
            EOS;

            // Set Limit on Results
            //$query .= ' LIMIT '. ($is_any_filter_selected === false ? '200': '1000'). ';';

            $db = get_db_instance();
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Offset
            $offset = 0;

            // Flag
            $direction = intval($data['__direction'] ?? 0);

            // Limit Records
            if(($data['__offset'] ?? 0) > 0) {
                $__offset = abs(intval($data['__offset'] ?? 0));
                
                if($direction === -1) {
                    $__offset -= (self::NO_OF_RECORDS_PER_REQUEST * 2);
                    $offset = $__offset;
                }
                else {
                    $offset = $__offset;
                }
            }

            // Limit Records
            $records = self::limit_records($records, $offset);

            // Reset 
            if(count($records) === 0) {
                $direction = 0;
                $offset = 0;
            }

            $response = [
                'records' => $records,
                '__offset' => $offset += ($direction === -1 ? 0 : self::NO_OF_RECORDS_PER_REQUEST),
            ];
            return ['status' => true, 'data' => $response];
        }
        catch(Exception $e) {
            return ['data' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will fetch transaction detail for PDF.
     * @param transaction_id
     * @param transaction_type
     * @param version
     * @return array
     */
    public static function fetch_transaction_for_pdf(int $transaction_id, int $transaction_type, ?int $version=null): array {
        try {
            $db = get_db_instance();

            // Table Name
            $table_name = self::TABLE_NAME_INDEX[$transaction_type];

            // Fetch Transaction
            $query = <<<EOS
            SELECT 
                txn.*,
                c.`name` AS client_name,
                c.street1,
                c.street2,
                c.city,
                c.province,
                c.postal_code AS postalCode,
                c.country,
                c.phone_number_1 AS phoneNumber1
            FROM 
                $table_name AS txn
            LEFT JOIN 
                clients AS c
            ON 
                txn.client_id = c.id
            WHERE 
                txn.id = :txn_id;
            EOS;
            $statement = $db -> prepare($query);
            $statement -> execute([':txn_id' => $transaction_id]);
            $transaction = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(isset($transaction[0])) $transaction = $transaction[0];

            // Check Any version requested
            if(is_numeric($version)) {
                $existing_versions = json_decode($transaction['versions'], true, flags: JSON_THROW_ON_ERROR);
                if(isset($existing_versions[$version])) $transaction['details'] = json_encode($existing_versions[$version], JSON_THROW_ON_ERROR);
                else die('Invalid Version!');

                // Update Amounts.
                $amounts = self::calculate_amount_by_transaction_type(
                    $transaction_type,
                    $existing_versions[$version],
                    $transaction['disable_federal_taxes'],
                    $transaction['disable_provincial_taxes'],
                );

                // Update Amounts.
                $transaction['sum_total'] = $amounts['sumTotal'];
                $transaction['sub_total'] = $amounts['subTotal'];
                $transaction['gst_hst_tax'] = $amounts['gstHSTTax'];
                $transaction['pst_tax'] = $amounts['pstTax'];
                $transaction['txn_discount'] = $amounts['txnDiscount'];
                $transaction['credit_amount'] = $amounts['sumTotal'];

                // Update Modified Timestamp
                $transaction['modified'] = Utils::get_utc_str_timestamp_from_utc_unix_timestamp($version);
            }
            return $transaction;
        }
        catch(Exception $e) {
            die($e -> getMessage());
        }
    }

    /**
     * This method will generate pdf.
     * @param txn_queue 
     * @param dump_file
     * @return array
     */
    public static function generate_pdf(array $txn_queue, bool $dump_file=false): array {

        // Filenames
        $filenames = [];

        // Process each transaction
        foreach($txn_queue as $txn) {

            $output_filename = trim(self::TABLE_NAME_INDEX[$txn['type']]);
            $filename = "{$output_filename}_{$txn['id']}.pdf";

            // Transaction Details
            $transaction_details = self::fetch_transaction_for_pdf($txn['id'], $txn['type'], $txn['version'] ?? null);

            // Add Transaction Flag
            if(isset($txn['version'])) $transaction_details['is_old_version'] = true;

            // Prepare Transaction
            $prepared_details = PrepareDetails_SI_SR_CN_DN_QT::prepare_details($txn['type'], $transaction_details);

            // Generate Transaction
            GeneratePDF::transaction($prepared_details, $filename, $dump_file);

            // Add to path
            $filenames []= $filename;
        }
        return ['status' => true, 'data' => $filenames];
    }

    /**
     * This method will fetch client details for email.
     * @param transaction_id 
     * @param transaction_type
     * @return array
     */
    public static function fetch_client_details_for_email(int $transaction_id, int $transaction_type): array {
        $db = get_db_instance();
        try {
            $table_name = self::TABLE_NAME_INDEX[$transaction_type];
            $query = <<<EOS
            SELECT 
                txn.*,
                c.`name`,
                c.email_id,
                c.additional_email_addresses
            FROM
                $table_name AS txn
            LEFT JOIN 
                clients AS c
            ON 
                txn.client_id = c.id
            WHERE
                txn.id = :txn_id;
            EOS;

            $statement = $db -> prepare($query);
            $statement -> execute([':txn_id' => $transaction_id]);
            $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(isset($record[0])) $record = $record[0];
            return ['status' => true, 'data' => $record];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will send email.
     * @param transaction_id
     * @param transaction_type
     * @return array
     */
    public static function email_si_sr_cn_dn_qt(int $transaction_id, int $transaction_type) : array {
        try {
            // Fetch Client Details
            $record = self::fetch_client_details_for_email($transaction_id, $transaction_type);

            // Validate
            if($record['status'] === true) $record = $record['data'];
            else throw new Exception($record['message']);

            // Validate Email Id
            if(Validate::is_email_id($record['email_id'] ?? '') === false) throw new Exception('Invalid Email Id.');

            // Send Email
            self::__send_email_si_sr_cn_dn_qt(
                $record['store_id'],
                $transaction_id,
                $transaction_type,
                $record['email_id'],
                $record['name'],
                $record['sum_total'],
                $record['po'] ?? null,
                $record['unit_no'] ?? null,
                $record['additional_email_addresses'],
            );
            return ['status' => true];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will get the store signature.
     * @param store_id
     * @return string 
     */
    public static function get_store_signature(int $store_id): string {
        return '<b>'.StoreDetails::STORE_DETAILS[$store_id]['name']. '</b>'.
                '<br>'.
                StoreDetails::STORE_DETAILS[$store_id]['address']['tel'].
                '<br>'.
                StoreDetails::STORE_DETAILS[$store_id]['address']['street1'].'<br>'. 
                StoreDetails::STORE_DETAILS[$store_id]['address']['city'].', '. 
                StoreDetails::STORE_DETAILS[$store_id]['address']['province']. ' '. StoreDetails::STORE_DETAILS[$store_id]['address']['postal_code'];
    }

    /**
     * This method will send the transaction in email.
     * @param store_id
     * @param transaction_id The id of the transaction.
     * @param transaction_type Transaction Type.
     * @param recipient_email The email of recipient
     * @param recipient_name The name of recipient
     * @param total_amount The total amount
     * @param po
     * @param unit_number 
     * @param additional_email_addresses
     * @return bool 
     */
    private static function __send_email_si_sr_cn_dn_qt(
        int $store_id,
        int $transaction_id, 
        int $transaction_type, 
        string $recipient_email, 
        string $recipient_name, 
        string $amount, 
        ?string $po, 
        ?string $unit_number, 
        string $additional_email_addresses=''
    ) : array {
        $exception_message = '';
        try {
            // Generate PDF 
            $filenames = Shared::generate_pdf([['id' => $transaction_id, 'type' => $transaction_type]], dump_file: true);
            if($filenames['status'] === false) throw new Exception('Error in Generating Transaction Details.');
            $filename = $filenames['data'][0];
            
            // Path to file
            $path_to_file = TEMP_DIR. "$filename";

            // Set Flag
            $is_email_sent = false;

            // Check whether file exists 
            if(file_exists($path_to_file) !== true) throw new Exception('Unable to save document on disk.');
            else {
                // Transaction Name
                $transaction_name = TRANSACTION_NAMES[$transaction_type];

                // Round of Amount to 2 decimal Places
                $amount = Utils::round($amount, 2);
                
                // Content Details
                $content_details = <<<EOS
                Please find herewith attached $transaction_name amounting to a total of $$amount.
                EOS;

                // Send invoice in email.
                require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/email.php";

                // Subject 
                $subject = "$transaction_name #$transaction_id ";

                // Add PO/Unit Number 
                if($transaction_type === SALES_INVOICE) {
                    if(strlen($po) > 0) $subject .= " w/ PO# $po";
                    else if(strlen($unit_number) > 0) $subject .= " w/ UNIT NO# $unit_number";
                }
                
                // Add Business Name
                $subject .= ' from '. StoreDetails::STORE_DETAILS[$store_id]['email']['from_name'][SYSTEM_INIT_MODE];

                // Signature 
                $signature = self::get_store_signature($store_id);

                // Content
                $content = <<<EOS
                Please notify us immediately if you are unable to see the attachment(s).$content_details
                <br><br><br><br>
                $signature
                EOS;

                // Send email
                $is_email_sent = Email::send(
                    subject: $subject,
                    recipient_email: $recipient_email,
                    recipient_name: $recipient_name,
                    content: $content,
                    path_to_attachment: $path_to_file,
                    file_name: $filename,
                    store_id: $store_id,
                    additional_email_addresses: $additional_email_addresses,
                    is_html: true,
                );

                if($is_email_sent['status'] === false) throw new Exception($is_email_sent['message']);
                else $is_email_sent = $is_email_sent['status'];
            }
        }
        catch (Throwable $th) {
            $is_email_sent = false;
            $exception_message = $th -> getMessage();
        }
        finally {
            // Delete File from disk.
            if(file_exists($path_to_file)) register_shutdown_function('unlink', $path_to_file);

            // Status
            return ['status' => $is_email_sent, 'message' => $exception_message];
        }
    }

    /**
     * This method will check for balance due for client.
     * @param client_id
     * @param store_id
     * @throws Exception
     */
    public static function allow_balance_due_check_for_client(int $client_id, int $store_id): void {
        if(SpecialExceptions::allow_balance_due_check_for_client($client_id, $store_id)) {
            Shared::check_balance_due_over_60_plus_days($client_id, $store_id);
        }
    }

    /**
     * This method will check balance due over 60+ days for client.
     * @param client_id
     * @param store_id
     * @throws Exception
     */
    public static function check_balance_due_over_60_plus_days(int $client_id, int $store_id): void {

        // Dont Check for Self Client.
        if(isset(Client::SELF_CLIENT_WHITELIST[$client_id])) return;

        $customer_aged_summary = CustomerAgedSummary::fetch_customer_aged_summary_of_client(
            $client_id, 
            $store_id,
            Utils::get_business_date($store_id),
        );
        if(isset($customer_aged_summary['client_id'])) {
            if($customer_aged_summary['61-90'] > 0 || $customer_aged_summary['91+'] > 0) {
                throw new Exception('Client has Balance Due above 60+ days. Cannot proceed with Credit Transaction.');
            }
        }
    }

    /**
     * This method will get the txn age. 
     * @param txn_date
     * @param till_date
     * @param amount
     * @param store_id
     */
    public static function get_txn_age(string $txn_date, DateTime $till_date, float $amount, int $store_id): array {

        // Aged Amounts.
        $aged_amounts = [
            'total' =>      0.00,
            'current' =>    0.00,
            '31-60' =>      0.00,
            '61-90' =>      0.00,
            '91+' =>        0.00
        ];

        // Calculate the date difference 
        // The date is as per Business Timezone.
        // The time picked up from the browser.
        $difference = Utils::get_difference_from_current_date(
            $txn_date,
            $till_date,
            $store_id,
        );

        // Year
        if($difference['y'] > 0) {
            $aged_amounts['91+'] += $amount;
        }

        // Current Month(days)
        else if($difference['m'] === 0 && $difference['d'] >= 0) {
            $aged_amounts['current'] += $amount;
        }

        // Prior Month(s)
        else if($difference['m'] > 0) {
            if($difference['m'] === 1) {
                $aged_amounts['31-60'] += $amount;
            }
            else if($difference['m'] === 2) {
                $aged_amounts['61-90'] += $amount;
            }
            else if($difference['m'] > 2) {
                $aged_amounts['91+'] += $amount;
            }
        }

        // Add to total 
        $aged_amounts['total'] += $amount;

        return $aged_amounts;
    }

    /**
     * This method will fetch latest required details for transaction.
     * @param transaction_id
     * @param transaction_type
     * @param db
     * @return ?array 
     * @throws Exception
     */
    public static function fetch_latest_required_details_for_transaction(int $transaction_id, int $transaction_type, array $data, PDO &$db): ?array {
        $table_name = self::TABLE_NAME_INDEX[$transaction_type];
        $lock_counter_column_name = $transaction_type === QUOTATION ? '': '__lock_counter,';
        $statement = $db -> prepare("SELECT versions, $lock_counter_column_name modified FROM $table_name WHERE id = :id;");
        $statement -> execute([':id' => $transaction_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Versions
        $versions = null;
        if(isset($result[0])) {

            // Check for Any Receipt made for this Transaction
            if(intval($result[0]['__lock_counter'] ?? 0) > 0) throw new Exception('A receipt has been made for this '. TRANSACTION_NAMES[$transaction_type].'.');

            // Check for Fresh Copy of this Transaction.
            if($data['lastModifiedTimestamp'] !== $result[0]['modified']) {
                throw new Exception('Cannot Update Stale Copy of '.TRANSACTION_NAMES[$transaction_type].'. Please reload and try again.');
            }

            // Set Versions
            $versions = $result[0]['versions'];
        }
        else throw new Exception('Unable to fetch Transaction Record for '. TRANSACTION_NAMES[$transaction_type].'.');

        // Process
        if(is_null($versions)) return null;
        return json_decode($versions, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
    }

    /**
     * This method will remove item tag from txn details.
     * @param details
     */
    public static function remove_item_tag_from_txn_details(array &$details): void {
        $count = count($details);
        for($i = 0; $i < $count; ++$i) {
            $details[$i]['identifier'] = self::remove_item_tag_from_string($details[$i]['identifier']);
            $details[$i]['description'] = self::remove_item_tag_from_string($details[$i]['description']);
        }
    }

    /**
     * This method will add item tag to txn details.
     * @param details
     */
    public static function add_item_tag_to_txn_details(array &$details): void {
        $count = count($details);
        for($i = 0; $i < $count; ++$i) {
            $details[$i]['identifier'] = Inventory::ITEM_DETAILS_TAG . $details[$i]['identifier'];
            $details[$i]['description'] = Inventory::ITEM_DETAILS_TAG. $details[$i]['description'];
        }
    }

    /**
     * This method will remove item tag from string.
     * @param text 
     * @return string 
     */
    public static function remove_item_tag_from_string(string $text): string {
        return str_replace(Inventory::ITEM_DETAILS_TAG, '', $text);
    }

    /**
     * This method will fetch initial details of txn.
     * @param txn_type
     * @param txn_id
     * @param db
     * @return array
     */
    public static function fetch_initial_details_of_txn(int $txn_type, int $txn_id, PDO &$db): array {
        $table_name = '';
        switch($txn_type) {
            case SALES_INVOICE: $table_name = 'sales_invoice'; break;
            case SALES_RETURN: $table_name = 'sales_return'; break;
            case CREDIT_NOTE: $table_name = 'credit_note'; break;
            case DEBIT_NOTE: $table_name = 'debit_note'; break;
            case QUOTATION: $table_name = 'quotation'; break;
            default: throw new Exception('Invalid Transaction Type');
        }
        $statement = $db -> prepare("SELECT * FROM $table_name WHERE id = :id;");
        $statement -> execute([':id' => $txn_id]);
        $txn_details = $statement -> fetchAll(PDO::FETCH_ASSOC)[0];
        $client_id = $txn_details['client_id'];

        // Client Details
        $client_details = Client::fetch(['id' => $client_id]);
        if($client_details['status'] === false) throw new Exception('Unable to Fetch Initial Client Details.');
        else $client_details = $client_details['data'][0];

        return [
            'txn' => $txn_details,
            'client' => $client_details,
        ];
    }
    
    /**
     * This method will set the initial details of transaction.
     * @param details
     * @param initial_details
     */
    public static function set_initial_client_details(array &$details, array $initial_details): void {
        $details['clientDetails'] = $initial_details['client'];
        $details['details'] = json_decode($initial_details['txn']['details'], true, flags: JSON_THROW_ON_ERROR);
        $details['gstHSTTax'] = $initial_details['txn']['gst_hst_tax'];
        $details['paymentMethod'] = $initial_details['txn']['payment_method'] ?? null;
        $details['pstTax'] = $initial_details['txn']['pst_tax'];
        $details['subTotal'] = $initial_details['txn']['sub_total'];
        $details['sumTotal'] = $initial_details['txn']['sum_total'];
        $details['txnDate'] = $initial_details['txn']['date'];
        $details['txnDiscount'] = $initial_details['txn']['txn_discount'];
    }

}