<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class Quotations {

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

            $keys = ['quantity', 'basePrice', 'amountPerItem'];
            if(SYSTEM_INIT_MODE === PARTS) $keys []= 'buyingCost';
            foreach($keys as $key) if(floatval($item[$key]) <= 0) return ['status' => false, 'message' => "$key less than or equal to 0 for $identifier."];

            // Check for GST/HST Tax Rate
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false && $disable_federal_taxes === 0 && $item['gstHSTTaxRate'] <= 0) return ['status' => false, 'message' => "GSTHSTTaxRate less than or equal to 0 for $identifier."];

            // Check for PST if applicable
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false && $disable_provincial_taxes === 0 && (StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['pst_tax_rate'] > 0) && floatval($item['pstTaxRate']) < 0) {
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
     * This method will calculate amount for Quotations.
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

            // Add Taxes
            if(in_array($item['itemId'], Inventory::EHC_ITEMS) === false) {
                $pst_tax += (($item['amountPerItem'] * $provincial_tax_rate) / 100);
                $gst_hst_tax += (($item['amountPerItem'] * $federal_tax_rate) / 100);
            }
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
     * @return array
     */
    private static function validate_details(array $data): array {
        // Client Id 
        $client_id = $data['clientDetails']['id'] ?? null;

        // Check for valid id 
        if(!Validate::is_numeric($client_id)) {
            throw new Exception('Cannot Process Quotations for Invalid Customer.');
        }

        // Validate Store
        $store_id = intval($_SESSION['store_id']);
        if(key_exists($store_id, StoreDetails::STORE_DETAILS) === false) throw new Exception('Store is Invalid.');
        if($store_id !== intval($data['storeId'])) throw new Exception('Store does not match with current session.');

        // Sales Rep Id
        if($data['salesRepId'] === 0) throw new Exception('Please select Sales Representative.');

        // Transaction Date
        $transaction_date = Utils::get_YYYY_mm_dd(
            Utils::convert_utc_str_timestamp_to_localtime($data['txnDate'], $store_id)
        );
        if($transaction_date === null) throw new Exception('Invalid Date.');

        // Disable Federal Taxes
        $disable_federal_taxes = $data['disableFederalTaxes'] ?? null;
        $disable_provincial_taxes = $data['disableProvincialTaxes'] ?? null;

        // Check for Disabled Taxes
        if($disable_federal_taxes !== $data['clientDetails']['disableFederalTaxes']) {
            throw new Exception('Federal Tax Status cannot be changed for this Quotation.');
        }

        if($disable_provincial_taxes !== $data['clientDetails']['disableProvincialTaxes']) {
            throw new Exception('Provincial Tax Status cannot be changed for this Quotation.');
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

        // Account Number
        $account_number = ucwords(trim($data['accountNumber'] ?? ''));

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
            'account_number' => $account_number,
        ];
    }

    /**
     * This method will create quotations.
     * @param data
     * @return array
     */
    private static function create_quotations(array $data): array {
        $db = get_db_instance();
        try {
            // Begin Transaction
            $db -> beginTransaction();

            // Validate Details.
            $validated_details = self::validate_details($data);

            // Client Id 
            $client_id = $validated_details['client_id'];

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

            // Txn Details
            $details = $data['details'];

            // Remove Keys
            Shared::remove_keys($details, Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // Insert into Database 
            $query = <<<'EOS'
            INSERT INTO quotation
            (
                client_id,
                `date`,
                sum_total,
                sub_total,
                pst_tax,
                gst_hst_tax,
                txn_discount,
                details,
                store_id,
                notes,
                account_number,
                sales_rep_id,
                disable_federal_taxes,
                disable_provincial_taxes
            )
            VALUES
            (
                :client_id,
                :date,
                :sum_total,
                :sub_total,
                :pst_tax,
                :gst_hst_tax,
                :txn_discount,
                :details,
                :store_id,
                :notes,
                :account_number,
                :sales_rep_id,
                :disable_federal_taxes,
                :disable_provincial_taxes
            );
            EOS;

            // Remove Item Tag
            Shared::remove_item_tag_from_txn_details($details);

            // Values to be inserted into DB
            $values = [
                ':client_id' => $client_id,
                ':date' => $date,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':store_id' => $store_id,
                ':account_number' => $validated_details['account_number'],
                ':notes' => $validated_details['notes'],
                ':sales_rep_id' => $data['salesRepId'],
                ':disable_federal_taxes' => $data['clientDetails']['disableFederalTaxes'] ?? 0,
                ':disable_provincial_taxes' => $data['clientDetails']['disableProvincialTaxes'] ?? 0,
            ];

            /* CHECK FOR ANY ERROR */
            assert_success();

            // Insert into DB
            $statement = $db -> prepare($query);
            $statement -> execute($values);

            // Get Quotation ID
            $quotation_id = $db -> lastInsertId();
            if($quotation_id === false) throw new Exception('Unable to create Quotation.');

            /* COMMIT */
            if($db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $quotation_id];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will update quotations.
     * @param data
     * @return array
     */
    private static function update_quotations(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Quotation Id
            $txn_id = is_numeric($data['id'] ?? null) ? intval($data['id']) : null;
            if(!is_numeric($txn_id)) throw new Exception('Invalid Quotation Id.');

            // Fetch Intial Transaction
            $initial_details = Shared::fetch_initial_details_of_txn(
                QUOTATION, 
                $txn_id,
                $db,
            );

            // Set Initial Details
            Shared::set_initial_client_details($data['initial'], $initial_details);

            // Validate Details.
            $validated_details = self::validate_details($data);

            // Client Id 
            $client_id = $validated_details['client_id'];

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Versions
            $versions = Shared::fetch_latest_required_details_for_transaction($txn_id, QUOTATION, $data, $db);

            // Details
            $details = $data['details'];

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

            // Remove Keys
            Shared::remove_keys($details, Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // Update Sales Invoice
            $query = <<<'EOS'
            UPDATE 
                quotation
            SET 
                client_id = :client_id,
                date = :date,
                sum_total = :sum_total,
                sub_total = :sub_total,
                pst_tax = :pst_tax,
                gst_hst_tax = :gst_hst_tax,
                txn_discount = :txn_discount,
                details = :details,
                account_number = :account_number,
                notes = :notes,
                versions = :versions,
                modified = CURRENT_TIMESTAMP 
            WHERE
                id = :id;
            EOS;

            $params = [
                ':client_id' => $client_id,
                ':date' => $validated_details['txn_date'],
                ':sum_total' => $validated_details['sum_total'],
                ':sub_total' => $validated_details['sub_total'],
                ':pst_tax' => $validated_details['pst_tax'],
                ':gst_hst_tax' => $validated_details['gst_hst_tax'],
                ':txn_discount' => $validated_details['txn_discount'],
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':account_number' => $validated_details['account_number'],
                ':notes' => $validated_details['notes'],
                ':versions' => is_array($versions) ? json_encode($versions, JSON_THROW_ON_ERROR) : null,
                ':id' => $txn_id,
            ];

            // CHECK FOR ANY ERROR
            assert_success();

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);

            // Check for Successful Update
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Update Quotations.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will fetch latest item details by id.
     * @param details
     * @param store_id
     * @param db
     * @return array
     */
    private static function fetch_latest_item_details_by_id(array $details, int $store_id, PDO &$db): array {
        $ids = [];
        foreach($details as $item) {
            $item_id = $item['itemId'];
            if(!in_array($item_id, $ids)) $ids[]= $item_id;
        }
        $query = "SELECT id, code, prices FROM items WHERE id IN (:placeholder);";
        $ret = Utils::mysql_in_placeholder($ids, $query);
        $query = $ret['query'];
        $values = $ret['values'];
        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $item_details = [];
        foreach($result as $r) {
            $item_id = $r['id'];
            $prices = json_decode($r['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if(!isset($prices[$store_id])) throw new Exception('Prices Does not Exists for: '. $r['code']);
            $item_details[$item_id] = $prices[$store_id];
        }

        // Set Latest Buying Cost
        $count = count($details);
        for($i = 0; $i < $count; ++$i) {
            $item_id = $details[$i]['itemId'];
            $details[$i]['buyingCost'] = $item_details[$item_id]['buyingCost'];
        }
        return $details;
    }

    /**
     * This method will fetch quotations by id for conversions.
     * @param quotations_ids
     * @return array
     */
    public static function fetch_quotations_by_id_for_conversion(array $quotations_id): array {
        $db = get_db_instance();
        try {
            $store_id = isset($_SESSION['store_id']) ? intval($_SESSION['store_id'])  : null;
            if(!is_numeric($store_id)) throw new Exception('Invalid Store ID.');

            // Query
            $query = <<<EOS
            SELECT 
                c.early_payment_discount,
                c.early_payment_paid_within_days,
                c.net_amount_due_within_days,
                c.shipping_addresses,
                c.disable_federal_taxes,
                c.disable_provincial_taxes,
                c.disable_credit_transactions,
                c.modified as client_last_modified_timestamp,
                c.name,
                c.contact_name,
                c.street1,
                c.street2,
                c.city,
                c.postal_code,
                c.province,
                c.phone_number_1,
                c.phone_number_2,
                c.fax,
                c.email_id,
                c.country,
                qt.*
            FROM 
                quotation AS qt
            LEFT JOIN 
                clients AS c 
            ON 
                qt.client_id = c.id
            WHERE 
                qt.id IN (:placeholder) 
            AND 
                qt.store_id = $store_id;
            EOS;

            // Convert 
            $ret = Utils::mysql_in_placeholder($quotations_id, $query);
            $query = $ret['query'];
            $values = $ret['values'];

            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $formatted_record = [];
            foreach($records as $record) {
                // Details
                $record['details'] = json_decode($record['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Get Fresh Item Details such as Selling and Buying Price.
                $record['details'] = self::fetch_latest_item_details_by_id($record['details'], $store_id, $db);

                // Early Payment Discount Details
                $early_payment_discount = json_decode($record['early_payment_discount'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $early_payment_paid_within_days = json_decode($record['early_payment_paid_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $net_amount_due_within_days = json_decode($record['net_amount_due_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $record['early_payment_discount'] = $early_payment_discount[$store_id] ?? 0;
                $record['early_payment_paid_within_days'] = $early_payment_paid_within_days[$store_id] ?? 0;
                $record['net_amount_due_within_days'] = $net_amount_due_within_days[$store_id] ?? 0;

                // Disable Federal Taxes
                $disable_federal_taxes = json_decode($record['disable_federal_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $record['disable_federal_taxes'] = $disable_federal_taxes[$store_id] ?? 0;

                // Disable Provincial Taxes
                $disable_provincial_taxes = json_decode($record['disable_provincial_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $record['disable_provincial_taxes'] = $disable_provincial_taxes[$store_id] ?? 0;

                // Add Primary Details
                $record['primary_details'] = Client::pack_primary_details($record);

                // Shipping Address
                $shipping_addresses = json_decode($record['shipping_addresses'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(isset($shipping_addresses[0])) $record['shipping_addresses'] = $shipping_addresses[0];
                else $record['shipping_addresses'] = '{}';

                // Is Self Client
                $record['is_self_client'] = Client::is_self_client($record['client_id']);

                // ID
                $formatted_record[$record['id']] = $record;
            }
            return ['status' => true, 'data' => $formatted_record];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will fetch transaction by id.
     * @param transaction_id
     * @return array
     */
    private static function fetch_transaction_by_id(int $transaction_id): array {
        try {
            $db = get_db_instance();

            // Store Id 
            $store_id = intval($_SESSION['store_id'] ?? 0);

            $statement = $db -> prepare('SELECT * FROM quotation WHERE id = :id AND store_id = :store_id;');
            $statement -> execute([':id' => $transaction_id, ':store_id' => $store_id]);
            $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(isset($record[0])) $record = $record[0];

            // Fetch Previous and Next Transaction ID
            $adjacent_records = Shared::fetch_previous_and_next_transaction_id($store_id, $record['client_id'], QUOTATION, $transaction_id, $db);

            // Format Invoice Record
            $formatted_record = Shared::format_transaction_record($record, QUOTATION);

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

            return ['status' => true, 'data' => $formatted_record];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will process quotations.
     * @param data
     * @return array
     */
    public static function process(array $data) : array {
        try {
            // Result
            $result = [];

            switch($data['action']) {
                case 'create_txn': $result = self::create_quotations($data); break;
                case 'update_txn': $result = self::update_quotations($data); break;
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