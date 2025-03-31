<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_invoice.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_return.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/credit_note.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/debit_note.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/quotation.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/receipt.php";

/**
 * This class will prepare details for Sales Invoice/Sales Returns/Credit Note/Debit Note/Quotations.
 */
class PrepareDetails_SI_SR_CN_DN_QT {

    /**
     * This method will build sold to address.
     * @param details
     * @return array
     */
    private static function build_address(array $details): array {

        // Build street 2 
        $street2 = '';
        if(isset($details['street2'][0])) $street2 .= $details['street2'];
        if(isset($details['city'][0])) $street2.= ", {$details['city']}";
        if(isset($details['province'][0])) $street2.= ", {$details['province']}";
        if(isset($details['postalCode'][0])) $street2.= ", {$details['postalCode']}";

        // Client Name
        $client_name = strtoupper(isset($details['client_name'][0]) ? $details['client_name'] : $details['name']);
        return [
            'client_name' => $client_name ?? '',
            'client_address_1' => strtoupper($details['street1'] ?? ''),
            'client_address_2' => strtoupper($street2 ?? ''),
            'client_address_3' => strtoupper(in_array($details['country'] ?? '', COUNTRY) ? COUNTRY[$details['country']] : ''),
        ];
    }

    /**
     * This method will build both sold to and ship to address. 
     * @param txn_details
     * @param skip_ship_to
     * @return array
     */
    private static function build_client_addresses(array $txn_details, bool $skip_ship_to=false) : array {

        // Sold to
        $sold_to = self::build_address($txn_details);

        if($skip_ship_to) return [
            'sold_to' => $sold_to, 
            'ship_to' => null,
        ];

        // Build Ship to address
        $shipping_address = json_decode($txn_details['shipping_address'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $ship_to = [];
        if(json_last_error() === JSON_ERROR_NONE) $ship_to = self::build_address($shipping_address);

        return [
            'sold_to' => $sold_to, 
            'ship_to' => $ship_to,
        ];
    }

    /**
     * This method will process details for sales return.
     * @param details
     * @return array
     */
    private static function process_details_for_sales_return(array $details): array {
        $new_details = [];
        foreach($details as $detail) {
            if(($detail['returnQuantity'] ?? 0) > 0) $new_details[]= $detail;
        }
        return $new_details;
    }

    /**
     * This method will prepare details for Printing PDF.
     * @param transaction_type
     * @param tx
     * @return array 
     */
    public static function prepare_details(int $transaction_type, array $txn): array {

        // Only process for Sales Invoice, Sales Returns and Quotations
        if($transaction_type === 1 || $transaction_type === 2 || $transaction_type === 5) $addresses = self::build_client_addresses($txn, skip_ship_to: $transaction_type === 5);
        else $addresses = null;

        // Store Id
        $store_id = intval($txn['store_id']);

        // Item Details
        $item_details = json_decode($txn['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Process for Sales Return
        if($transaction_type === SALES_RETURN) $item_details = self::process_details_for_sales_return($item_details);

        // Is Credit Transaction
        $is_credit_transaction = $transaction_type === SALES_RETURN || $transaction_type === CREDIT_NOTE;

        // GST/HST Tax
        $gst_hst_tax = $txn['gst_hst_tax'] ?? 0;

        // PST Tax
        $pst_tax = $txn['pst_tax'] ?? 0;

        // Amount Owing
        $amount_owing = $txn['credit_amount'] ?? 0;

        // Amount Paid
        $amount_paid = $txn['sum_total'] - ($txn['credit_amount'] ?? 0);

        // Transaction Discount
        $txn_discount = $txn['txn_discount'] ?? 0;

        $details = [
            'txn_type_id' => $transaction_type,
            'document_id' => $txn['id'],
            'date' => Utils::convert_date_to_human_readable($txn['date']),
            'document_type' => TRANSACTION_NAMES[$transaction_type],
            'po' => $txn['po'] ?? '',
            'unit_no' => $txn['unit_no'] ?? '',
            'vin' => $txn['vin'] ?? '',
            'config_mode' => SYSTEM_INIT_MODE,
            'driver_name' => $txn['driver_name'] ?? '',
            'odometer_reading' => $txn['odometer_reading'] ?? '',
            'trailer_number' => $txn['trailer_number'] ?? '',
            'purchased_by' => $txn['purchased_by'] ?? '',
            'client_details' => [
                'sold_to' => $addresses['sold_to'] ?? null,
                'ship_to' => $addresses['ship_to'] ?? null,
            ],
            'business_number' => StoreDetails::STORE_DETAILS[$txn['store_id']]['business_number'][SYSTEM_INIT_MODE],
            'details' => $item_details,
            'timestamp' => $txn['created'],
            'modified' => $txn['modified'],
            'txn_discount' => $is_credit_transaction ? -$txn_discount : $txn_discount,
            'gst_hst_tax'=> $is_credit_transaction ? -$gst_hst_tax: $gst_hst_tax,
            'pst_tax'=> $is_credit_transaction ? -$pst_tax: $pst_tax,
            'sum_total' => $is_credit_transaction ? -$txn['sum_total']: $txn['sum_total'],
            'sub_total' => $is_credit_transaction ? -$txn['sub_total'] : $txn['sub_total'],
            'amount_paid' => $is_credit_transaction ? -$amount_paid : $amount_paid,
            'amount_owing' => $is_credit_transaction ? -$amount_owing : $amount_owing,
            'pst_number' => StoreDetails::STORE_DETAILS[$store_id]['pst_number'][SYSTEM_INIT_MODE],
            'account_number' => $txn['account_number'] ?? '',
            'store_id' => $store_id,
            'is_old_version' => $txn['is_old_version'] ?? false,
        ];

        // Add Restocking Fees
        if(isset($txn['restocking_fees'])) $details['restocking_fees'] = $txn['restocking_fees'];

        // Combine Store details.
        return array_merge($details, Utils::build_store_address($store_id));
    }
}

/**
 * This class will prepare details for Receipts.
 */
class PrepareDetails_Receipts {

    /**
     * This method will prepare format receipt.
     * @param details
     * @param array
     */
    private static function prepare(array $details): array {
        $store_id = $details['store_id'];
        $receipt_items = [];
        $items = json_decode($details['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        foreach($items as $t) {
            $receipt_items []= [
                'id' => $t['txnId'],
                'discount_given' => $t['discountGiven'],
                'amount_received' => $t['amountReceived'],
            ];
        }
        $details = [
            'id' => $details['id'],
            'sum_total' => $details['sum_total'],
            'date' => $details['date'],
            'client_name' => $details['name'],
            'receipt_items' => $receipt_items, 
            'receipt_items_og' => $items,
        ];
        return array_merge($details, Utils::build_store_address($store_id));
    }

    /**
     * This method will fetch receipt by id.
     * @param receipt_id
     * @return array
     */
    public static function fetch_record_by_id(int $receipt_id) : array {
        $db = get_db_instance();
        $query = <<<'EOS'
        SELECT 
            c.`name`,
            r.*
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
        $statement -> execute([':receipt_id' => $receipt_id]);
        $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($record[0])) $record = $record[0];
        else throw new Exception('Unable to Fetch Receipt.');
        return self::prepare($record);
    }
}

/**
 * This class will prepare details for customer statement.
 */
class PrepareDetails_CustomerStatement {

    // Type index
    private const TYPE_INDEX = [
        1 => 'Sales Invoice',
        2 => 'Sales Return',
        3 => 'Credit Note',
        4 => 'Debit Note'
    ];

    // Type Abbr
    private const TYPE_ABBR = [
        1 => 'IN',
        2 => 'SR',
        3 => 'CN',
        4 => 'DN'
    ];

    // Store amounts per age
    private static $amount = [
        'current' => 0,
        '31-60' => 0,
        '60+' => 0
    ];

    // Total amount
    public static $total = 0;

    /**
     * This method will format transactions.
     * @param txn 
     * @return array 
     */
    private static function format_transactions(array $txn): array {
        $no_of_txn = count($txn);
        $amount_due = 0;

        // Current date
        // $current_date = date_create(Utils::get_business_date());
        $current_date = '';

        for($i = 0 ; $i < $no_of_txn; ++$i) {
            $txn_type = $txn[$i]['type'];
            $txn[$i]['transaction_id'] = $txn[$i]['id'];
            $txn[$i]['transaction_type_id'] = $txn[$i]['type'];
            $txn[$i]['transaction_type'] = self::TYPE_INDEX[$txn_type];
            $txn[$i]['transaction_no'] = self::TYPE_ABBR[$txn_type] . '-'. $txn[$i]['id'];

            // Round Amount 
            $txn[$i]['balance'] = Utils::round($txn[$i]['amount']);
            
            // Amount Due
            $amount_due += $txn[$i]['balance'];

            // Calculate Date difference
            $date_diff = Utils::get_difference_between_dates(
                $txn[$i]['txn_date'],
                $current_date,
                intval($_SESSION['store_id'])
            );
            
            // Add amount as per date

            // More than year
            if($date_diff['y'] > 0) self::$amount['60+'] += $txn[$i]['amount'];
            
            // Current Month(days)
            else if($date_diff['m'] === 0 && $date_diff['d'] >= 0) self::$amount['current'] += $txn[$i]['amount'];

            // Prior month(s)
            else if($date_diff['m'] > 0) {
                if($date_diff['m'] === 1)    self::$amount['31-60'] += $txn[$i]['amount'];
                else if($date_diff['m'] > 1) self::$amount['60+'] += $txn[$i]['amount'];
            }
            
            // Update total amount
            self::$total += $txn[$i]['balance'];

            // Convert Txn Date
            $txn[$i]['transaction_date'] = Utils::convert_date_to_human_readable($txn[$i]['txn_date']);

            // Format Amount
            $txn[$i]['balance'] = Utils::number_format($txn[$i]['balance']);
            $txn[$i]['amount_due'] = Utils::number_format($amount_due);

            if(isset($txn[$i]['invoice_id'])) $txn[$i]['invoice_no'] = 'IN-'.$txn[$i]['invoice_id'];
            else $txn[$i]['invoice_no'] = '-';
        }

        return $txn;
    }

    /**
     * This method will prepare customer statement.
     * @param customer_statements
     * @param client_details
     * @return array
     */
    public static function prepare(array $customer_statements, array $client_details): array {

        // Format transactions
        $txn = self::format_transactions($customer_statements);
        
        $details = [
            'date' => '',
            'txn' => $txn,
            'current' => Utils::number_format(self::$amount['current']),
            '31-60' => Utils::number_format(self::$amount['31-60']),
            '60+' => Utils::number_format(self::$amount['60+']),
            'total_amount' => Utils::number_format(self::$total),
        ];

        return array_merge($details, $client_details, Utils::build_store_address($_SESSION['store_id']));
    }
}

/**
 * Prepare Details Inventory
 */
class PrepareDetails_Inventory {

    /**
     * This method will generate Inventory List.
     * @param store_id
     */
    public static function generate_inventory_list(int $store_id): array {
        $db = get_db_instance();
        $statement = $db -> prepare(<<<'EOS'
        SELECT 
            i.`identifier`,
            i.`description`,
            i.`prices`,
            inv.`quantity`
        FROM 
            inventory AS inv 
        LEFT JOIN 
            items AS i
        ON 
            inv.`item_id` = i.`id`
        WHERE 
            inv.`store_id` = :store_id
        AND 
            inv.`quantity` > 0;
        EOS);
        $statement -> execute([':store_id' => $store_id]);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach($results as $result) {
            $prices = json_decode($result['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $buying_cost = isset($prices[$store_id]['buyingCost']) ? $prices[$store_id]['buyingCost'] : 0;
            $data[]= [
                'identifier' => $result['identifier'],
                'description' => $result['description'],
                'buying_cost' => $buying_cost,
                'quantity' => $result['quantity'],
                'value' => Utils::round($buying_cost * $result['quantity'], 2),
            ];
        }
        return $data;
    }
}
?>