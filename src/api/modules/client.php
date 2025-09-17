<?php 
/**
 * This file will implement different actions related to the client functionality in the application.
 * 
 * @author Dewang Saxena, <dewang2610@gmail.com>
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/csrf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";

class Client {

    // Client Operation Tags
    public const ADD = 'client_add';
    public const UPDATE = 'client_update';
    public const FETCH = 'client_fetch';

    /**
     * Self Client WhiteList
     * Client Id : Store ID
     */
    public const SELF_CLIENT_WHITELIST = [
        PARTS => [
            /* ABS Truck Wash And Lube */
            13735 => StoreDetails::EDMONTON,

            /* ABS Trucking Ltd. */
            14506 => StoreDetails::EDMONTON,

            /* ABS Truck & Trailer Parts Ltd. */ 
            15260 => StoreDetails::EDMONTON,

            /* ABS TRUCK PARTS NISKU */ 
            16047 => StoreDetails::NISKU,

            /* ABS Truck Wash and Lube Nisku */
            15356 => StoreDetails::NISKU,

            /* ABS Truck & Trailer Parts Slave Lake */ 
            17671 => StoreDetails::SLAVE_LAKE,

            /* ABS Truck & Trailer Parts Calgary */ 
            18636 => StoreDetails::CALGARY,

            /* Traction Delta */ 
            17773 => StoreDetails::DELTA,
            
            /* 1721534 AB LTD */
            16630 => StoreDetails::EDMONTON,

            /* ABS TRUCK AND TRAILER PARTS SK LTD. */
            20432 => StoreDetails::REGINA,
        ],
        WASH => [],
    ];

    /**
     * Self Client Exceptions.
     */
    public const SELF_CLIENT_EXCEPTIONS = [
        PARTS => [
            /* ABS Trucking Ltd. */
            14506 => StoreDetails::EDMONTON,

            /* 1721534 AB LTD */
            16630 => StoreDetails::EDMONTON,

            /* ABS Truck Wash And Lube */
            13735 => StoreDetails::EDMONTON,
        ],
        WASH => [],
    ];

    /**
     * Self Client to be included in customer aged summary report.
     */
    public const SELF_CLIENT_INCLUDED_IN_CUSTOMER_AGED_SUMMARY_REPORTING = [
        PARTS => [
            /* ABS Truck & Trailer Parts Slave Lake */ 
            17671 => StoreDetails::SLAVE_LAKE,
        ],
        WASH => [],
    ];

    /**
     * Inter stores.
     */
    private const INTER_STORES = [
        PARTS => [
            /* ABS Truck & Trailer Parts Ltds. */ 
            15260 => StoreDetails::EDMONTON,

            /* ABS TRUCK PARTS NISKU */ 
            16047 => StoreDetails::NISKU,

            /* ABS Truck Wash and Lube Nisku */
            15356 => StoreDetails::NISKU,

            /* ABS Truck & Trailer Parts Slave Lake */ 
            17671 => StoreDetails::SLAVE_LAKE,

            /* ABS Truck & Trailer Parts Calgary */ 
            18636 => StoreDetails::CALGARY,

            /* Traction Delta */ 
            17773 => StoreDetails::DELTA,

            /* ABS TRUCK AND TRAILER PARTS SK LTD. */
            20432 => StoreDetails::REGINA,
        ],
    ];

    /**
     * Stores with Restricted Access
     */
    private const STORES_WITH_RESTRICTED_ACCESS = [
        PARTS => [
            StoreDetails::EDMONTON,
            StoreDetails::SLAVE_LAKE,
        ],
        WASH => [],
    ];

    /**
     * Client Catgeory
     */
    public const CATEGORY_ALL = 0;
    public const CATEGORY_OTHER = 1;
    public const CATEGORY_LOGISTIC = 2;
    public const CATEGORY_REPAIR_SHOP = 3;
    public const CATEGORY_OWNER_DRIVE = 4;
    public const CATEGORY_DRIVER = 5;
    public const CATEGORY_TRANSPORT = 6;
    public const CATEGORY_MOBILE_REPAIR_VAN = 7;
    public const CATEGORY_TRANSPORTER_FLEET = 8;

    // Client Category Index
    private const CLIENT_CATEGORY_INDEX = [
        self::CATEGORY_ALL => 'All',
        self::CATEGORY_OTHER => 'Other/No Category',
        self::CATEGORY_LOGISTIC => 'Logistic',
        self::CATEGORY_REPAIR_SHOP => 'Repair Shop',
        self::CATEGORY_OWNER_DRIVE => 'Owner Driver',
        self::CATEGORY_DRIVER => 'Driver',
        self::CATEGORY_TRANSPORT => 'Transport',
        self::CATEGORY_MOBILE_REPAIR_VAN => 'Mobile Repair Van',
        self::CATEGORY_TRANSPORTER_FLEET => 'Transporter/Fleet',
    ];

    /**
     * This method will validate phone number or fax.
     */
    private static function validate_phone_or_fax_number(string|null $phone_number) : bool {
        if(isset($phone_number[0])) {
            // Extract Numbers
            $phone_number = Utils::extract_numbers($phone_number, true);

            if(!is_numeric($phone_number)) return false;

            // Must be atleast 10 digits
            else if(!isset($phone_number[9])) return false;
        } 
        return true;
    }

    /**
     * This method will validate address and sanitize null/empty fields.
     * @param detail
     * @return array
     */
    private static function validate_and_sanitize_address(array &$detail): array {
        $response = ['status' => true];
        if(!isset($detail['name']) || !Validate::is_name($detail['name'])) {
            $response['message'] = 'Invalid Name';
            $response['status'] = false;
        }
        else if(!isset($detail['contactName']) || !Validate::is_name($detail['contactName'])) {
            $response['message'] = 'Invalid Contact Name';
            $response['status'] = false;
        }
        
        if(isset($detail['phoneNumber1']) && self::validate_phone_or_fax_number($detail['phoneNumber1']) === false) {
            $response['message'] = 'Invalid Phone Number 1';
            $response['status'] = false;
        }
        else $details['phoneNumber1'] = '';

        if(isset($detail['phoneNumber2']) && self::validate_phone_or_fax_number($detail['phoneNumber2']) === false) {
            $response['message'] = 'Invalid Phone Number 2';
            $response['status'] = false;
        }
        else $details['phoneNumber2'] = '';

        if(isset($detail['fax']) && self::validate_phone_or_fax_number($detail['fax']) === false) {
            $response['message'] = 'Invalid Fax Number';
            $response['status'] = false;
        }
        else $details['fax'] = '';
        return $response;
    } 

    /**
     * This method will check whether Pay Later is Available for the client.
     * @param primary_details
     */
    public static function is_credit_txn_eligible_for_client(array $primary_details) : void {
        $is_pay_later_available = true;
        if(isset($primary_details['phoneNumber1']) === false || strlen($primary_details['phoneNumber1']) < 10) $is_pay_later_available = false;

        // Check for Pay later available
        if($is_pay_later_available === false) throw new Exception('Pay Later is Not Available for this Client Due to Invalid Phone Number.');
    }

    /**
     * This method will validate details. 
     * @param details
     * @return array 
     */
    private static function validate_details(array &$details) : array {

        // Store Id
        $store_id = isset($_SESSION['store_id']) ? intval($_SESSION['store_id']) : null;

        // Check for Valid Store 
        if($store_id === null) return ['status' => false, 'message' => 'Store Id is Null.'];

        // Validtate Primary Address
        $ret = self::validate_and_sanitize_address($details['primaryDetails']);
        if($ret['status'] === false) return $ret;

        // Validate Ship to address
        if(intval($details['isDefaultShippingAddress']) !== 1) {
            $ret = self::validate_and_sanitize_address($details['shippingAddresses']);
            if($ret['status'] === false) return $ret;
        }
        
        // Validate Email ID
        if(isset($details['emailId'][0]) && Validate::is_email_id($details['emailId']) === false) {
            return ['status' => false, 'message' => 'Invalid Email ID provided.'];
        }
        
        // Standard Discount
        $details['standardDiscount'] = isset($details['standardDiscount'][0]) && is_numeric($details['standardDiscount']) ? floatval($details['standardDiscount']) : 0;
        if($details['standardDiscount'] < 0.00) return ['message' => 'Standard Discount cannot be negative.', 'status' => false];
        if($details['standardDiscount'] > 100.00) return ['message' => 'Standard Discount cannot be more than 100%.', 'status' => false];

        // Handle Standard Profit Margin per store
        $standard_profit_margins = $details['standardProfitMargins'] ?? [];

        // Check for Default Standard Profit Margin.
        if(isset($standard_profit_margins[DEFAULT_PROFIT_MARGIN_KEY]) === false) throw new Exception('"'. DEFAULT_PROFIT_MARGIN_KEY.'" Profit Margin Required.');
        
        // Check whether store exists in standard profit margins
        $item_prefixes = array_keys($standard_profit_margins);
        foreach($item_prefixes as $prefix) {
            if(is_numeric($standard_profit_margins[$prefix]) === false) {
                unset($standard_profit_margins[$prefix]);
                continue;
            }
            else {
                $standard_profit_margins[$prefix] = floatval($standard_profit_margins[$prefix]);

                // Validate 
                if($standard_profit_margins[$prefix] < 0) return ['status' => false, 'message' => 'Standard Profit Margin for '. $prefix. ' cannot be negative.'];
                else if($standard_profit_margins[$prefix] > 100) return ['status' => false, 'message' => 'Standard Profit Margin for '. $prefix. ' cannot be more than 100.'];
            }
        }

        // Encode Standard Profit Margins
        $details['standardProfitMargins'] = $standard_profit_margins;

        // Credit Limit
        $details['creditLimit'] = floatval(is_numeric($details['creditLimit']) ? $details['creditLimit'] : 0);
        if($details['creditLimit'] < 0.00) return ['message' => 'Invalid Credit Limit.', 'status' => false];

        // Early Payment Discount
        $details['earlyPaymentDiscount'] = floatval(is_numeric($details['earlyPaymentDiscount']) ? $details['earlyPaymentDiscount'] : 0);
        if($details['earlyPaymentDiscount'] < 0.00) return ['message' => 'Early Payment Discount cannot be negative.', 'status' => false];
        if($details['earlyPaymentDiscount'] > 100) return ['message' => 'Early Payment Discount cannot be more than 100%.', 'status' => false];

        // Early Payment Paid Within Days
        $details['earlyPaymentPaidWithinDays'] = intval(is_numeric($details['earlyPaymentPaidWithinDays']) ? $details['earlyPaymentPaidWithinDays'] : 0);
        if($details['earlyPaymentPaidWithinDays'] < 0) return ['message' => 'Invalid Early Payment Paid Within Days', 'status' => false];

        // Net Amount Due Within Days
        $details['netAmountDueWithinDays'] = intval(is_numeric($details['netAmountDueWithinDays']) ? $details['netAmountDueWithinDays'] : 0);
        if($details['netAmountDueWithinDays'] < 0) return ['message' => 'Invalid Net Amount Due Within Days.', 'status' => false];

        // Default Payment Method
        $default_payment_method = $details['defaultPaymentMethod'] ?? null;
        if(!is_numeric($default_payment_method) || !in_array($default_payment_method, PaymentMethod::MODES_OF_PAYMENT)) return ['message' => 'Invalid Default Payment Method', 'status' => false];

        // Default Receipt Payment Method
        $default_receipt_payment_method = $details['defaultReceiptPaymentMethod'] ?? null;
        if(!is_numeric($default_receipt_payment_method) || !in_array($default_receipt_payment_method, PaymentMethod::MODES_OF_PAYMENT)) return ['message' => 'Invalid Default Receipt Payment Method', 'status' => false];

        // Pay Later cannot be used for receipt.
        if(intval($default_receipt_payment_method) === PaymentMethod::MODES_OF_PAYMENT['Pay Later']) return ['message' => 'Receipt Payment Method cannot be Pay Later.', 'status' => false];

        // Validate Additional Email Addresses
        $additional_email_ids = explode(',', $details['additionalEmailAddresses']);
        foreach($additional_email_ids as $email_id) if(isset($email_id[0]) && Validate::is_email_id($email_id) === false) return ['message' => 'Invalid Additional Email Id.', 'status' => false];

        // Validate Custom Pricing 
        $custom_pricing = $details['customSellingPriceForItems'] ?? [];
        if(isset($custom_pricing[$store_id])) {
            $items = $custom_pricing[$store_id];
            foreach($items as $item) {
                $item_identifier = $item['identifier'];
                if(is_numeric($item['sellingPrice']) === false) throw new Exception('Invalid Selling Price for: '. $item_identifier);
                else if($item['sellingPrice'] < $item['buyingCost']) throw new Exception('Selling Price cannot be less than buying price for: '. $item_identifier);
            }
        }
        return ['status' => true];
    }

    /**
     * This method will add client.
     * @param db
     * @param values
     * @return bool
     */
    private static function add(PDO &$db, array $values) : bool {

        // Insert into table
        $statement = $db -> prepare(<<<'EOS'
        INSERT INTO 
        clients
        (
            `name`,
            contact_name,
            street1,
            street2,
            city,
            province,
            postal_code,
            country,
            phone_number_1,
            phone_number_2,
            fax,
            email_id,
            additional_email_addresses,
            client_since,
            disable_credit_transactions,
            is_default_shipping_address,
            default_payment_method,
            default_receipt_payment_method,
            standard_discount,
            standard_profit_margins,
            early_payment_discount,
            early_payment_paid_within_days,
            net_amount_due_within_days,
            produce_statement_for_client,
            disable_federal_taxes,
            disable_provincial_taxes,
            memo,
            additional_information,
            category,
            credit_limit,
            shipping_addresses,
            custom_selling_price_for_items,
            last_purchase_date,
            send_quotations_to_additional_email_addresses
        )
        VALUES(
            :name,
            :contact_name,
            :street1,
            :street2,
            :city,
            :province,
            :postal_code,
            :country,
            :phone_number_1,
            :phone_number_2,
            :fax,
            :email_id,
            :additional_email_addresses,
            :client_since,
            :disable_credit_transactions,
            :is_default_shipping_address,
            :default_payment_method,
            :default_receipt_payment_method,
            :standard_discount,
            :standard_profit_margins,
            :early_payment_discount,
            :early_payment_paid_within_days,
            :net_amount_due_within_days,
            :produce_statement_for_client,
            :disable_federal_taxes,
            :disable_provincial_taxes,
            :memo,
            :additional_information,
            :category,
            :credit_limit,
            :shipping_addresses,
            :custom_selling_price_for_items,
            :last_purchase_date,
            :send_quotations_to_additional_email_addresses
        );
        EOS);

        $statement -> execute($values);
        return $db -> lastInsertId() !== false;
    }

    /**
     * This method will update client.
     * @param db
     * @param values
     * @param dynamic_values
     * @return array
     */
    private static function update(PDO &$db, array $values, array &$dynamic_values): array { 

        // Check for Latest Copy of Client
        $statement = $db -> prepare('SELECT * FROM clients WHERE id = :id;');
        $statement -> execute([':id' => $values[':id']]);
        $existing_record = $statement -> fetchAll(PDO::FETCH_ASSOC);
        
        // Assign first record
        $existing_record = $existing_record[0];

        // Check for Latest Copy
        if($existing_record['modified'] !== $values[':last_modified_timestamp']) return [
            'status' => false,
            'message' => 'Cannot Update Stale Copy of Client. Reload client and try again!.',
        ];

        // Store Id
        $store_id = $dynamic_values['store_id'];

        // Standard Discount
        $standard_discount = json_decode($existing_record['standard_discount'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $standard_discount[$store_id] = $dynamic_values['standard_discount'];

        // Standard Profit Margins
        $standard_profit_margins = json_decode($existing_record['standard_profit_margins'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $standard_profit_margins[$store_id] = $dynamic_values['standard_profit_margins'];

        //  Early Payment Discount
        $early_payment_discount = json_decode($existing_record['early_payment_discount'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $early_payment_discount[$store_id] = $dynamic_values['early_payment_discount'];

        //  Early Payment Paid Within Days
        $early_payment_paid_within_days = json_decode($existing_record['early_payment_paid_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $early_payment_paid_within_days[$store_id] = $dynamic_values['early_payment_paid_within_days'];

        //  Net Amount Due Within Days
        $net_amount_due_within_days = json_decode($existing_record['net_amount_due_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $net_amount_due_within_days[$store_id] = $dynamic_values['net_amount_due_within_days'];

        // Is Inactive
        $is_inactive = json_decode($existing_record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $is_inactive[$store_id] = $dynamic_values['is_inactive'];

        // Credit Limit
        $credit_limit = json_decode($existing_record['credit_limit'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $credit_limit[$store_id] = $dynamic_values['credit_limit'];

        // Disable Federal Taxes
        $disable_federal_taxes = json_decode($existing_record['disable_federal_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $disable_federal_taxes[$store_id] = $dynamic_values['disable_federal_taxes'];

        // Disable provincial Taxes
        $disable_provincial_taxes = json_decode($existing_record['disable_provincial_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $disable_provincial_taxes[$store_id] = $dynamic_values['disable_provincial_taxes'];

        // Send Quotations to Additional Email Addresses
        $send_quotations_to_additional_email_addresses = json_decode($existing_record['send_quotations_to_additional_email_addresses'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $send_quotations_to_additional_email_addresses[$store_id] = $dynamic_values['send_quotations_to_additional_email_addresses'];

        // Convert to JSON
        $values[':standard_discount'] = json_encode($standard_discount, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':standard_profit_margins'] = json_encode($standard_profit_margins, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':early_payment_discount'] = json_encode($early_payment_discount, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':early_payment_paid_within_days'] = json_encode($early_payment_paid_within_days, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':net_amount_due_within_days'] = json_encode($net_amount_due_within_days, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':is_inactive'] = json_encode($is_inactive, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':credit_limit'] = json_encode($credit_limit, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':disable_federal_taxes'] = json_encode($disable_federal_taxes, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':disable_provincial_taxes'] = json_encode($disable_provincial_taxes, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $values[':send_quotations_to_additional_email_addresses'] = json_encode($send_quotations_to_additional_email_addresses, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Insert into table
        $statement = $db -> prepare(<<<'EOS'
        UPDATE
            clients
        SET 
            `name` = :name,
            contact_name = :contact_name,
            street1 = :street1,
            street2 = :street2,
            city = :city,
            province = :province,
            postal_code = :postal_code,
            country = :country,
            phone_number_1 = :phone_number_1,
            phone_number_2 = :phone_number_2,
            fax = :fax,
            email_id = :email_id,
            additional_email_addresses = :additional_email_addresses,
            client_since = :client_since,
            disable_credit_transactions = :disable_credit_transactions,
            is_default_shipping_address = :is_default_shipping_address,
            default_payment_method = :default_payment_method,
            default_receipt_payment_method = :default_receipt_payment_method,
            standard_discount = :standard_discount,
            standard_profit_margins = :standard_profit_margins,
            early_payment_discount = :early_payment_discount,
            early_payment_paid_within_days = :early_payment_paid_within_days,
            net_amount_due_within_days = :net_amount_due_within_days,
            produce_statement_for_client = :produce_statement_for_client,
            disable_federal_taxes = :disable_federal_taxes,
            disable_provincial_taxes = :disable_provincial_taxes,
            memo = :memo,
            additional_information = :additional_information,
            category = :category,
            credit_limit = :credit_limit,
            shipping_addresses = :shipping_addresses,
            name_history = :name_history,
            is_inactive = :is_inactive,
            custom_selling_price_for_items = :custom_selling_price_for_items,
            send_quotations_to_additional_email_addresses = :send_quotations_to_additional_email_addresses,
            modified = CURRENT_TIMESTAMP
        WHERE
            id = :id
        AND
            modified = :last_modified_timestamp;
        );
        EOS);

        $is_successful = $statement -> execute($values);
        return ['status' => $is_successful === true && $statement -> rowCount () > 0];
    }

    /**
     * This method will check whether any detail has been changed and update name history if exists.
     * @param new 
     * @param old
     * @param name_history
     * @return array
     */
    private static function is_any_detail_changed(array $new, array $old, array $name_history): array {

        // Any Details Changed
        $any_details_changed = hash('sha256', json_encode($new, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR)) !== hash('sha256', json_encode($old, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR));

        if($any_details_changed) {
            
            // This is UTC Timestamp
            $old['modified'] = date('Y-m-d H:m:s');

            // Name of the user who changed it 
            $old['changedBy'] = $_SESSION['name'] ?? 'DEWANG';

            // Add Old Contact Details to Name History
            $name_history[]= $old;
        }
        return $name_history;
    }

    /**
     * This method will format address. 
     * @param address
     * @return array
     */
    private static function format_address(array $address): array {
        $address['name'] = ucwords($address['name']);
        $address['contactName'] = ucwords($address['contactName']);
        $address['street1'] = ucwords(strtolower($address['street1'] ?? ''));
        $address['street2'] = ucwords(strtolower($address['street2'] ?? ''));
        $address['city'] = ucwords(strtolower($address['city'] ?? ''));
        $address['province'] = ucwords(strtolower($address['province'] ?? ''));
        $address['postalCode'] = strtoupper(strtolower($address['postalCode'] ?? ''));
        return $address;
    }
    
    /**
     * This method will remove redundant item details for custom pricing for client.
     * @param custom_selling_price_for_items
     * @param store_id
     * @return array
     */
    public static function remove_redundant_details_for_custom_pricing(array $custom_selling_price_for_items, int $store_id) : array {

        // Custom Selling Price for Items.
        if(isset($custom_selling_price_for_items[$store_id]) === false) return $custom_selling_price_for_items;

        // Remove Redundant Details
        $temp = $custom_selling_price_for_items[$store_id];

        // Item Keys 
        $item_keys = array_keys($temp);

        // Selling Price
        $selling_prices = [];

        $no_of_items = count($item_keys);
        for($i = 0; $i < $no_of_items; ++$i) {
            $item_id = $item_keys[$i];
            $selling_prices[$item_id] = $temp[$item_id]['sellingPrice'];
        }

        // Set Details for Store 
        $custom_selling_price_for_items[$store_id] = $selling_prices;

        return $custom_selling_price_for_items;
    }

    /**
     * This method will pack item details for custom pricing.
     * @param custom_selling_price_for_items
     * @param store_id
     * @param db
     * @return array
     */
    public static function pack_item_details_for_custom_pricing(array $custom_selling_price_for_items, int $store_id, PDO &$db): array {

        // Check for Presence of store.
        if(count($custom_selling_price_for_items[$store_id]) === 0) return $custom_selling_price_for_items;

        // Add Keys 
        $temp = [];
        $items_keys = array_keys($custom_selling_price_for_items[$store_id]);
        foreach($items_keys as $item_key) {
            if(!isset($temp[$item_key])) $temp[$item_key] = [];
            $temp[$item_key]['sellingPrice'] = $custom_selling_price_for_items[$store_id][$item_key];
        }

        // Re-Assign
        $custom_selling_price_for_items[$store_id] = $temp;

        // Item Keys 
        $item_keys = array_keys($temp);

        // Fetch Item Details
        $item_details = Inventory::fetch(['item_ids' => $item_keys], $store_id, db: $db);
        if($item_details['status'] === false) throw new Exception('Unable to Fetch Item Details.');
        else $item_details = $item_details['data'];

        // Extract Item Details
        $extracted_item_details = [];
        foreach($item_details as $item_detail) {
            $item_id = $item_detail['id'];
            $extracted_item_details[$item_id] = [
                'identifier' => $item_detail['identifier'],
                'description' => $item_detail['description'],
                'storeId' => $store_id,
                'buyingCost' => $item_detail['prices'][$store_id]['buyingCost'],
                'preferredPrice' => $item_detail['prices'][$store_id]['preferredPrice'],
            ];
        }
        
        // Pack Details
        $item_count = count($item_keys);
        for($i = 0 ; $i < $item_count; ++$i) {
            $item_id = $item_keys[$i];
            $temp[$item_id]['identifier'] = $extracted_item_details[$item_id]['identifier'];
            $temp[$item_id]['description'] = $extracted_item_details[$item_id]['description'];
            $temp[$item_id]['storeId'] = $extracted_item_details[$item_id]['storeId'];
            $temp[$item_id]['buyingCost'] = $extracted_item_details[$item_id]['buyingCost'];
            $temp[$item_id]['preferredPrice'] = $extracted_item_details[$item_id]['preferredPrice'];
        }

        // Set Details for Store 
        $custom_selling_price_for_items[$store_id] = $temp;

        return $custom_selling_price_for_items;
    }

    /**
     * This method will add a new client.
     * @param data 
     * @return array
     */
    public static function process(array $data): array {
        try {
            $db = get_db_instance();

            // Insert into DB 
            $db -> beginTransaction();
            
            // Sanitize Values 
            $data = Utils::sanitize_values($data);
            $data['primaryDetails'] = Utils::sanitize_values($data['primaryDetails']);

            // Format Primary Details/Address
            $data['primaryDetails'] = self::format_address($data['primaryDetails']);

            // Check for Default shipping Address
            if(intval($data['isDefaultShippingAddress']) === 1) $data['shippingAddresses'] = $data['primaryDetails'];
            else $data['shippingAddresses'] = self::format_address(Utils::sanitize_values($data['shippingAddresses']));
            
            // Validate
            $ret = self::validate_details($data);
            if($ret['status'] === false) throw new Exception($ret['message']);

            // Process Client Since Date
            $clientSince = date('Y-m-d', strtotime($data['clientSince']));

            // Store Id
            $store_id = intval($_SESSION['store_id']);

            // Custom Selling Price
            $custom_selling_price_for_items = self::remove_redundant_details_for_custom_pricing($data['customSellingPriceForItems'] ?? [], $store_id);

            // User Id 
            $user_id = intval($_SESSION['user_id']);

            // Disable Credit Transactions
            $disable_credit_transactions = $data['disableCreditTransactions'];

            // Check for Special Exception for Few Stores.
            if($data['action'] === 'client_update' && $user_id !== UserManagement::ROOT_USER_ID) {
                if(SYSTEM_INIT_MODE === PARTS && in_array($store_id, self::STORES_WITH_RESTRICTED_ACCESS[SYSTEM_INIT_MODE])) {
                    if(in_array($_SESSION['user_id'], SpecialExceptions::USERS_WITH_SPECIAL_ACCESS[$store_id]) === false) {
                        $disable_credit_transactions = $data['initial']['disableCreditTransactions'];
                    }
                }
            }
            
            // Values
            $values = [
                ':name' => $data['primaryDetails']['name'],
                ':contact_name' => $data['primaryDetails']['contactName'],
                ':street1' => $data['primaryDetails']['street1'],
                ':street2' => $data['primaryDetails']['street2'],
                ':city' => $data['primaryDetails']['city'],
                ':province' => $data['primaryDetails']['province'],
                ':postal_code' => $data['primaryDetails']['postalCode'],
                ':country' => $data['primaryDetails']['country'],
                ':phone_number_1' => Utils::extract_numbers($data['primaryDetails']['phoneNumber1'] ?? '', true),
                ':phone_number_2' => Utils::extract_numbers($data['primaryDetails']['phoneNumber2'] ?? '', true),
                ':fax' => Utils::extract_numbers($data['primaryDetails']['fax'] ?? '', true),
                ':email_id' => strtolower($data['primaryDetails']['emailId'] ?? ''),
                ':additional_email_addresses' => strtolower($data['additionalEmailAddresses'] ?? ''),
                ':client_since' => $clientSince,
                ':disable_credit_transactions' => $disable_credit_transactions,
                ':is_default_shipping_address' => $data['isDefaultShippingAddress'],
                ':default_payment_method' => $data['defaultPaymentMethod'],
                ':default_receipt_payment_method' => $data['defaultReceiptPaymentMethod'],
                ':produce_statement_for_client' => $data['produceStatementForClient'],
                ':memo' => ucfirst($data['memo'] ?? ''),
                ':additional_information' => ucfirst($data['additionalInformation'] ?? ''),
                ':category' => $data['category'],
                ':custom_selling_price_for_items' => json_encode($custom_selling_price_for_items, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                // Store shipping address as an array.
                // We might store multiple addresses later on.
                ':shipping_addresses' => json_encode([$data['shippingAddresses']], JSON_THROW_ON_ERROR),
            ];

            // Select Action
            if($data['action'] === 'client_add') {

                // Standard Discount
                $values[':standard_discount'] = json_encode([$store_id => Utils::round($data['standardDiscount'])], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Standard Profit Margin
                $values[':standard_profit_margins'] = json_encode([$store_id => $data['standardProfitMargins']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Early payment Discount
                $values[':early_payment_discount'] = json_encode([$store_id => Utils::round($data['earlyPaymentDiscount'])], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Early Payment Paid Within Days
                $values[':early_payment_paid_within_days'] = json_encode([$store_id => $data['earlyPaymentPaidWithinDays']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Early Payment Paid Within Days
                $values[':net_amount_due_within_days'] = json_encode([$store_id => $data['netAmountDueWithinDays']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Credit Limit
                $values[':credit_limit'] = json_encode([$store_id => Utils::round($data['creditLimit'])], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Disable Federal Taxes
                $values[':disable_federal_taxes'] = json_encode([$store_id => $data['disableFederalTaxes']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Disable Provincial Taxes
                $values[':disable_provincial_taxes'] = json_encode([$store_id => $data['disableProvincialTaxes']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Last Purchase Date
                $values[':last_purchase_date'] = '{}';

                // Send Quotation to Additional Email Addresses
                $values[':send_quotations_to_additional_email_addresses'] = json_encode([$store_id => $data['sendQuotationsToAdditionalEmailAddresses']], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                $success_status = self::add($db, $values);
            }
            else if($data['action'] === 'client_update') {
                $values[':id'] = intval($data['id']);
                $values[':is_inactive'] = $data['isInactive'];
                $values[':last_modified_timestamp'] = $data['lastModifiedTimestamp'];

                // Check for One time Customer
                if($values[':id'] === ONE_TIME_CUSTOMER_ID) throw new Exception('Cannot update One time customer.');    
                
                // Handle Name History
                if(isset($data['primaryDetailsHistory'])) {
                    $values[':name_history'] = self::is_any_detail_changed(
                        $data['primaryDetails'], 
                        $data['primaryDetailsHistory'], 
                        $data['nameHistory'] ?? [],
                    );
                }
                else $values[':name_history'] = [];

                // JSON Encode Name History
                $values[':name_history'] = json_encode($values[':name_history'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Standard Discount And Early Payment Discount
                $dynamic_values = [
                    'store_id' => $store_id,
                    'standard_discount' => Utils::round($data['standardDiscount']),
                    'standard_profit_margins' => $data['standardProfitMargins'],
                    'early_payment_discount' => Utils::round($data['earlyPaymentDiscount']),
                    'early_payment_paid_within_days' => $data['earlyPaymentPaidWithinDays'],
                    'net_amount_due_within_days' => $data['netAmountDueWithinDays'],
                    'is_inactive' => $data['isInactive'],
                    'credit_limit' => Utils::round($data['creditLimit']),
                    'disable_federal_taxes' => $data['disableFederalTaxes'],
                    'disable_provincial_taxes' => $data['disableProvincialTaxes'],
                    'send_quotations_to_additional_email_addresses' => $data['sendQuotationsToAdditionalEmailAddresses'],
                ];

                $ret_value = self::update($db, $values, $dynamic_values);
                if($ret_value['status'] === false) throw new Exception($ret_value['message']);

                // Set Flag
                $success_status = $ret_value['status'];
            }
            else throw new Exception('Invalid Operation.');

            // Throw Exception on Error
            assert_success();

            if($success_status) {
                if($db -> inTransaction()) $db -> commit();
                return ['status' => $success_status];
            }
            else throw new Exception('Unable to Add/Update client.');
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will build shipping addresses.
     * @param _addresses 
     * @return array
     */
    private static function build_shipping_addresses(string $_addresses): array {
        $addresses = json_decode($_addresses, true, flags: JSON_THROW_ON_ERROR);
        $formatted_addresses = [];
        foreach($addresses as $address) $formatted_addresses[]= $address;

        /* FOR NOW WE WILL ONLY BE SENDING 1 SHIPPING ADDRESS */
        return $formatted_addresses[0];
    }

    /**
     * This method will check whether the client is self client or not.
     * @param client_id
     * @return bool
     */
    public static function is_self_client(int $client_id): bool {
        return isset(self::SELF_CLIENT_WHITELIST[SYSTEM_INIT_MODE][$client_id]);
    }

    /**
     * This method will check whether the client is on self client exceptions list.
     * @param client_id
     * @return bool
     */
    public static function is_exception_made_for_self_client(int $client_id): bool {
        return isset(self::SELF_CLIENT_EXCEPTIONS[SYSTEM_INIT_MODE][$client_id]);
    }

    /**
     * This method will include self client in customer aged summary reports.
     * @param client_id
     * @return bool
     */
    public static function include_self_client_in_customer_aged_summary_report(int $client_id): bool {
        return isset(self::SELF_CLIENT_INCLUDED_IN_CUSTOMER_AGED_SUMMARY_REPORTING[SYSTEM_INIT_MODE][$client_id]);
    }

    /**
     * This method will check whether the client is inter store.
     * @param client_id
     */
    public static function is_inter_store_client($client_id): bool {
        return isset(self::INTER_STORES[SYSTEM_INIT_MODE][$client_id]);
    }

    /**
     * This method will fetch client name and contact details.
     * @param client_ids
     * @param db
     * @return array
     */
    public static function fetch_client_name_and_contact_details(array $client_ids, PDO &$db): array {
        $query = 'SELECT `id`, `name`, `phone_number_1` FROM clients WHERE id IN (:placeholder);';
        $ret_value = Utils::mysql_in_placeholder($client_ids, $query);
        $query = $ret_value['query'];
        $values = $ret_value['values'];

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $details = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $client_details = [];
        foreach($details as $d) {
            $client_details[$d['id']] = [
                'name' => $d['name'],
                'phoneNumber1' => $d['phone_number_1'],
            ];
        }
        return $client_details;
    }

    /**
     * This method will pack primary details.
     * @param record
     * @return array
     */
    public static function pack_primary_details(array $record) : array {
        return [
            'name' => $record['name'],
            'contactName' => $record['contact_name'],
            'street1' => $record['street1'],
            'street2' => $record['street2'],
            'city' => $record['city'],
            'postalCode' => $record['postal_code'],
            'province' => $record['province'],
            'phoneNumber1' => $record['phone_number_1'],
            'phoneNumber2' => $record['phone_number_2'],
            'fax' => $record['fax'],
            'emailId' => $record['email_id'],
            'country' => $record['country'],
        ];
    }

    /**
     * This method will fetch the client detail.
     * @param params
     * @param db
     * @return array 
     */
    public static function fetch(array|null $params=[], PDO | null &$db=null) : array {
        try {
            if(is_null($db)) $db = get_db_instance();

            // Query Params
            $values = [];

            // Check for Client ID 
            if(isset($params['id'])) {
                $query = <<<'EOS'
                SELECT 
                    * 
                FROM
                    clients 
                WHERE 
                    id = :id; 
                EOS;
                $values[':id'] = $params['id'];
            }
            else if($params === null) $query = 'SELECT * FROM clients;';
            else {
                $query = <<<'EOS'
                SELECT 
                    *
                FROM 
                    clients 
                WHERE 
                    (`name` LIKE :term
                    OR contact_name LIKE :term 
                    OR phone_number_1 LIKE :term
                    OR phone_number_2 LIKE :term
                    OR email_id LIKE :term)
                EOS;
                $values[':term'] = '%'. $params['term']. '%';

                // Add Limit 
                $query .= ' LIMIT 200;';
            }

            // Current Store 
            $store_id = intval($_SESSION['store_id'] ?? -1);

            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $formatted_records = [];
            foreach($records as $record) {

                // Check for Inactive 
                if(isset($params['exclude_inactive']) && $params['exclude_inactive'] == 1) {
                    $is_inactive = json_decode($record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                    if($is_inactive[$store_id] === 1) continue;
                }
                
                // Add Default Value for Current store.
                $standard_profit_margins = json_decode($record['standard_profit_margins'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $standard_profit_margins) === false) $standard_profit_margins = [DEFAULT_PROFIT_MARGIN_KEY => 0];
                else {
                    $standard_profit_margins = $standard_profit_margins[$store_id];

                    // Set Default Profit Margin key
                    if(key_exists(DEFAULT_PROFIT_MARGIN_KEY, $standard_profit_margins) === false) $standard_profit_margins[DEFAULT_PROFIT_MARGIN_KEY] = 0;
                }

                // Standard Discount
                $standard_discount = json_decode($record['standard_discount'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $standard_discount) === false) $standard_discount = 0;
                else $standard_discount = $standard_discount[$store_id];

                // Early Payment Discount
                $early_payment_discount = json_decode($record['early_payment_discount'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $early_payment_discount) === false) $early_payment_discount = 0;
                else $early_payment_discount = $early_payment_discount[$store_id];

                // Early Payment Paid Within Days
                $early_payment_paid_within_days = json_decode($record['early_payment_paid_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $early_payment_paid_within_days) === false) $early_payment_paid_within_days = 0;
                else $early_payment_paid_within_days = $early_payment_paid_within_days[$store_id];

                // Early Payment Due Within Days
                $net_amount_due_within_days = json_decode($record['net_amount_due_within_days'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $net_amount_due_within_days) === false) $net_amount_due_within_days = 0;
                else $net_amount_due_within_days = $net_amount_due_within_days[$store_id];

                // Is Inactive
                $is_inactive = json_decode($record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $is_inactive) === false) $is_inactive = 0;
                else $is_inactive = $is_inactive[$store_id];

                // Credit Limit
                $credit_limit = json_decode($record['credit_limit'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $credit_limit) === false) $credit_limit = 0;
                else $credit_limit = $credit_limit[$store_id];

                // Amount Owing
                $amount_owing = json_decode($record['amount_owing'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $amount_owing) === false) $amount_owing = 0;
                else $amount_owing = $amount_owing[$store_id];

                // Disable Federal Taxes
                $disable_federal_taxes = json_decode($record['disable_federal_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $disable_federal_taxes) === false) $disable_federal_taxes = 0;
                else $disable_federal_taxes = $disable_federal_taxes[$store_id];

                // Disable Provincial Taxes
                $disable_provincial_taxes = json_decode($record['disable_provincial_taxes'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(key_exists($store_id, $disable_provincial_taxes) === false) $disable_provincial_taxes = 0;
                else $disable_provincial_taxes = $disable_provincial_taxes[$store_id];

                // Custom Selling Price
                $custom_selling_price_for_items = json_decode($record['custom_selling_price_for_items'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(isset($custom_selling_price_for_items[$store_id]) === false) {
                    $custom_selling_price_for_items[$store_id] = [];
                }

                // Pack Item Details for Custom Selling Price
                $custom_selling_price_for_items = Client::pack_item_details_for_custom_pricing($custom_selling_price_for_items, $store_id, $db);

                // Client Since
                $client_since = str_replace('-', '/', $record['client_since']);

                // Last Purchase Date 
                $last_purchase_date = json_decode($record['last_purchase_date'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $last_purchase_date = $last_purchase_date[$store_id] ?? null;

                // Send Quotations to Secondary Emails
                $send_quotations_to_additional_email_addresses = json_decode($record['send_quotations_to_additional_email_addresses'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $send_quotations_to_additional_email_addresses = $send_quotations_to_additional_email_addresses[$store_id] ?? 1;

                // Set EPOCH time
                if($client_since === '0000/00/00') $client_since = '1970/01/01';
                $data = [
                    'id' => $record['id'],
                    'primaryDetails' => self::pack_primary_details($record),
                    'additionalInformation' => $record['additional_information'],
                    'clientSince' => $client_since,
                    'disableCreditTransactions' => $record['disable_credit_transactions'],
                    'initial' => [
                        'disableCreditTransactions' => $record['disable_credit_transactions'],
                    ],
                    'isDefaultShippingAddress' => $record['is_default_shipping_address'],
                    'defaultReceiptPaymentMethod' => $record['default_receipt_payment_method'],
                    'defaultPaymentMethod' => $record['default_payment_method'],
                    'standardDiscount' => $standard_discount,
                    'standardProfitMargins' => $standard_profit_margins,
                    'amountOwing' => $amount_owing,
                    'earlyPaymentDiscount' => $early_payment_discount,
                    'earlyPaymentPaidWithinDays' => $early_payment_paid_within_days,
                    'netAmountDueWithinDays' => $net_amount_due_within_days,
                    'produceStatementForClient' => $record['produce_statement_for_client'],
                    'disableFederalTaxes' => $disable_federal_taxes,
                    'disableProvincialTaxes' => $disable_provincial_taxes,
                    'memo' => $record['memo'],
                    'isInactive' => $is_inactive,
                    'category' => $record['category'],
                    'creditLimit' => $credit_limit,
                    'shippingAddresses' => self::build_shipping_addresses($record['shipping_addresses']),
                    'nameHistory' => json_decode($record['name_history'], true, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                    'additionalEmailAddresses' => $record['additional_email_addresses'],
                    'lastModifiedTimestamp' => $record['modified'],
                    'isSelfClient' => self::is_self_client($record['id']) ? 1 : 0,
                    'customSellingPriceForItems' => $custom_selling_price_for_items,
                    'lastPurchaseDate' => is_string($last_purchase_date) ? Utils::format_to_human_readable_date($last_purchase_date) : 'N/A',
                    'enforceSelfClientPriceLock' => $store_id === StoreDetails::CALGARY ? 0 : 1,
                    'sendQuotationsToAdditionalEmailAddresses' => $send_quotations_to_additional_email_addresses,
                ];
                $formatted_records []= $data;
            }
            return ['status' => true, 'data' => $formatted_records];
        }
        catch(Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will check for fresh copy of the client.
     * @param client_id
     * @param last_modified_timestamp
     * @throws Exception
     */
    public static function check_fresh_copy_of_client(int $client_id, string $last_modified_timestamp, PDO &$db): void {
        $statement = $db -> prepare('SELECT modified FROM clients WHERE id = :id;');
        $statement -> execute([':id' => $client_id]);
        $modified = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(!isset($modified[0])) throw new Exception('Unable to Fetch Client\'s Last Modified Timestamp.');

        // Compare
        if($modified[0]['modified'] !== $last_modified_timestamp) throw new Exception('Cannot Proceed with stale client details. Reload and try again.');
    }

    /**
     * This method will update amount owing for client.
     * @param client_id
     * @param credit_amount
     * @param db
     * @return array
     */
    public static function update_amount_owing_of_client(int $client_id, float $credit_amount, PDO &$db): void {

        // Fetch Amount Owing
        $statement = $db -> prepare('SELECT amount_owing, modified FROM clients WHERE id = :id;');
        $statement -> execute([':id' => $client_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(!isset($result[0])) throw new Exception('Unable to Fetch Amount Owing.');
        else $amount_owing = json_decode($result[0]['amount_owing'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Modified
        $modified = $result[0]['modified'];

        // Store Id 
        $store_id = intval($_SESSION['store_id']);

        // Amount Owing
        if(!isset($amount_owing[$store_id])) $amount_owing[$store_id] = 0;

        // Update Amount Owing
        $amount_owing[$store_id] = Utils::round($amount_owing[$store_id] + $credit_amount);

        // Convert Back into JSON
        $amount_owing = json_encode($amount_owing, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Query
        $query = <<<'EOS'
        UPDATE 
            clients
        SET 
            amount_owing = :amount_owing 
        WHERE
            id = :client_id
        AND
            modified = :modified;
        EOS;
        
        $statement = $db -> prepare($query);
        $is_successful = $statement -> execute([
            ':amount_owing' => $amount_owing, 
            ':client_id' => $client_id,
            ':modified' => $modified,
        ]);
        if($is_successful !== true && $statement -> rowCount () < 1) throw new Exception('Unable to update amount owing for client.');
        assert_success();
    }

    /**
     * This method will update last purchase date.
     * @param client_id
     * @param txn_id
     * @param txn_date
     * @param db
     * @param store_id
     */
    public static function update_last_purchase_date(int $client_id, int|null $txn_id, string $txn_date, PDO &$db, int $store_id) : void {
        $statement = $db -> prepare('SELECT last_purchase_date FROM clients WHERE id = :id;');
        $values = [':id' => $client_id];
        $statement -> execute($values);
        $__last_purchase_dates = json_decode(
            $statement -> fetchAll(PDO::FETCH_ASSOC)[0]['last_purchase_date'],
            true, 
            flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR
        );
        if(isset($__last_purchase_dates[$store_id]) === false) $last_purchase_date = '';
        else $last_purchase_date = $__last_purchase_dates[$store_id];

        // Do Update
        $do_update = false;

        // Fetch Last Transaction of Client
        if(is_numeric($txn_id)) {
            $last_sales_invoice = SalesInvoice::fetch_last_transaction_of_client($client_id, $db);
            $__last_purchase_dates[$store_id] = $last_sales_invoice;
            $do_update = true;
        }

        else {
            // It could be that client is creating invoice on prior date for some reason.
            // Only update it when presented with a later date than the existing last purchase date.
            if($last_purchase_date < $txn_date) {
                $do_update = true;
                $__last_purchase_dates[$store_id] = $txn_date;
            }
        }

        if($do_update) {
            $statement = $db -> prepare('UPDATE clients SET last_purchase_date = :last_purchase_date WHERE id = :id;');
            $values['last_purchase_date'] = json_encode($__last_purchase_dates, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $is_successful = $statement -> execute($values);
            if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Cannot Update Last Purchase Date for Client.');
        }
    }

    /**
     * This method will fetch clients by last purchase date.
     * @param last_purchase_date
     * @param store_id
     * @return void
     */
    public static function fetch_clients_by_last_purchase_date(string $last_purchase_date, int $store_id): void {
        try {
            $query = <<<'EOS'
            SELECT DISTINCT
                `name`,
                `contact_name`,
                `phone_number_1`,
                `last_purchase_date`,
                `category`
            FROM
                clients AS c
            LEFT JOIN 
                sales_invoice AS si
            ON 
                c.id = si.client_id
            WHERE 
                si.store_id = :store_id;
            EOS;
            if(isset($last_purchase_date[0]) === false) throw new Exception('Invalid Last Purchase Date.');

            $db = get_db_instance();
            $statement = $db -> prepare($query);
            $statement -> execute([':store_id' => $store_id]);
            $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $clients = [];
            foreach($result as $client) {
                $last_purchase_date_per_client = json_decode($client['last_purchase_date'], true, flags: JSON_THROW_ON_ERROR);
                if(isset($last_purchase_date_per_client[$store_id][0]) && $last_purchase_date_per_client[$store_id] <= $last_purchase_date) {
                    $clients[]= [
                        'name' => $client['name'],
                        'contact_name' => $client['contact_name'],
                        'phone_number_1' => isset($client['phone_number_1'][0]) ? Utils::format_phone_number($client['phone_number_1']): 'N/A',
                        'last_purchase_date' => Utils::format_to_human_readable_date($last_purchase_date_per_client[$store_id]),
                        'category' => self::CLIENT_CATEGORY_INDEX[$client['category']],
                    ];
                }
            }

            // Generate PDF 
            GeneratePDF::last_purchase_date([
                'till_date' => Utils::format_to_human_readable_date($last_purchase_date), 
                'clients' => $clients]
            );
        }
        catch(Exception $e) {
            echo $e -> getMessage();
        }
    }

    /**
     * This method will generate item sold for client within a date range.
     * 
     * @param store_ids
     * @param client_id
     * @param start_date
     * @param end_date
     * @param is_csv
     * @return void
     */    
    public static function generate_item_sold_reports(array $store_ids, int $client_id, string $start_date, string $end_date, bool $is_csv = false): void {
        $db = get_db_instance();

        $query = <<<'EOS'
        SELECT 
            *
        FROM
            sales_invoice 
        WHERE 
            client_id = :client_id
        AND
            store_id IN (:placeholder)
        AND
            `date` >= :start_date
        AND
            `date` <= :end_date;
        EOS;
        $results = Utils::mysql_in_placeholder_pdo_substitute($store_ids, $query);
        $statement = $db -> prepare($results['query']);
        $statement -> execute([
            ...$results['values'],
            ':client_id' => $client_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date,
        ]);

        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $items_sold = [];
        foreach($result as $r) {
            $details = json_decode($r['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($details as $d) {
                $item_id = $d['itemId'];
                if(isset($items_sold[$item_id]) === false) {
                    $items_sold[$item_id] = [
                        'identifier' => $d['identifier'],
                        'description' => $d['description'],
                        'quantity' => 0,
                    ];
                }

                $items_sold[$item_id]['quantity'] += $d['quantity'];
            }
        }

        if($is_csv) {
            $fopen = fopen('dbi.csv', 'w');
            fputcsv($fopen, ['Identifier', 'Description', 'Quantity']);
            foreach($items_sold as $item) {
                fputcsv($fopen, [$item['identifier'], $item['description'], $item['quantity']]);
            }
        }
        else {
            $heading = '<h1>Items Sold in ';
            foreach($store_ids as $store_id) {
                $heading .= StoreDetails::STORE_DETAILS[$store_id]['name'].', ';
            } 

            // Heading
            $heading = rtrim($heading, ', ');
            $heading .= " between $start_date and $end_date.</h1><br><br>";


            $code = <<<EOS
            <html>
            <body>
            $heading
            <table>
            EOS;

            $keys = array_keys($items_sold);
            $c = count($keys);
            $break_outer = false;
            for($i = 0; $break_outer === false && $i < $c; ) {
                $code .= '<tr>';
                for ($j = 0; $j < 4; ++$j) {
                    if(isset($keys[$i]) === false) {
                        $break_outer = true;
                        break;
                    }
                    $key = $keys[$i];
                    $identifier = $items_sold[$key]['identifier'];
                    $quantity = $items_sold[$key]['quantity'];
                    $i += 1;
                    $code .= "<td><b>$identifier</b></td>";
                    $code .= "<td>$quantity</td>";
                }
                $code .= '</tr>';
            }

            echo $code;
            $code = <<<EOS
            $code
            </table>
            </body>
            </html>
            EOS;
        }
    }
}
