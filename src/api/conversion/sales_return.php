<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";


class SalesReturnTransfer extends Common {

    /**
     * Calculate Receipt Discount
     */
    private static function calculate_receipt_discount(int $txn_id, PDO &$db): float {
        $receipt_discount = 0;
        $query = 'SELECT receipts FROM receipts WHERE receipts LIKE :txn_tag;';
        $tag = <<<EOS
        %SR-$txn_id%
        EOS;
        $values = ['txn_tag' => $tag];
        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

        foreach($records as $r) {
            $details = json_decode($r['receipts'], true, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($details as $d) {
                if($d['id'] == $txn_id && $d['type'] == 'SR') {
                    $receipt_discount += $d['discount_taken'];
                }
            }
        }
        return $receipt_discount;
    }

    private static function calculate_amount(array $items, int $disable_federal_taxes, int $disable_provincial_taxes) : array {

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

            // Process only valid items
            if(($item['returnQuantity'] ?? 0) < 1) continue;

            $total += $item['amountPerItem'];
            $pst_tax += (($item['amountPerItem'] * $provincial_tax_rate) / 100);
            $gst_hst_tax += (($item['amountPerItem'] * $federal_tax_rate) / 100);
            $base_price = $item['basePrice'];
            $return_quantity = $item['returnQuantity'];
            $txn_discount += ((($base_price * $return_quantity) * $item['discountRate']) / 100);
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

    private static function fetch_item_identifier(array $details, PDO &$db): array {

        $ids = [];
        foreach($details as $d) $ids[]= $d['id'];

        $query = 'SELECT id, item_identifier FROM items WHERE id IN (:placeholder);';

        $ret_value = Utils::mysql_in_placeholder($ids, $query);
        $query = $ret_value['query'];
        $values = $ret_value['values'];

        $statement = $db -> prepare($query);
        $statement -> execute($values);

        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach($result as $r) {
            $items[$r['id']] = $r['item_identifier'];
        }
        return $items;
    }

    /**
     * Format Details
     */
    private static function format_details(array $old, array $item_identifiers): array {
        $new = [];
        $new['itemId'] = $old['id'];
        $new['category'] = $old['category'];
        $new['amountPerItem'] = $old['amount'];
        $new['basePrice'] = $old['base_price'];
        $new['identifier'] = $item_identifiers[$old['id']];
        $new['description'] = $old['description'];
        $new['discountRate'] = $old['discount'];
        $new['pricePerItem'] = $old['price'];
        $new['quantity'] = $old['invoice_quantity'];
        $new['returnQuantity'] = $old['quantity'];
        $new['gstHSTTaxRate'] = 5;
        $new['pstTaxRate'] = $old['tax'] === 12 ? 7 : 0;
        $new['buyingCost'] = $old['our_buying_price'];
        $new['originalSellingPrice'] = $old['original_selling_price'];
        $new['unit'] = $old['unit'];
        $new['account'] = [
            'cogs' => 5020,
            'variance' => 5100,
            'expense' => 5020,
        ];
        if(SYSTEM_INIT_MODE === WASH) {
            $new['account']['assets'] = $old['account'];
            $new['account']['revenue'] = $old['revenue_account'];
        }
        else {
            $new['account']['assets'] = 1520;
            $new['account']['revenue'] = 4020;
        }
        $new['isBackOrder'] = 0;
        return $new;
    }

    private static function fetch_sales_invoice_payment_method(int $sales_invoice_id, PDO &$db) : int {
        $statement = $db -> prepare('SELECT payment_method FROM sales_invoice WHERE id = :id;');
        $statement -> execute([':id' => $sales_invoice_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        return $result[0]['payment_method'];
    }

    private static function format(array $old, PDO &$db): array {
        $new = [];
        $new['id'] = $old['id'];
        $new['client_id'] = $old['client_id'];
        $new['date'] = $old['return_date'];
        $new['sales_invoice_id'] = $old['invoice_id'];
        $new['sales_invoice_payment_method'] = self::fetch_sales_invoice_payment_method($new['sales_invoice_id'], $db);
        
        // Shipping Address
        $old_shipping_address = json_decode($old['shipping_address'], true, self::JSON_FLAG);
        if(is_array($old_shipping_address)) {
            $name = $old_shipping_address[0]['name'] ?? '';
            $contact_name = $old_shipping_address[0]['contact'] ?? '';
        }
        else {
            $name = '';
            $contact_name = '';
        }
        $new['shipping_address'] = json_encode(
            Common::prepare_shipping_address($old_shipping_address, $name, $contact_name)[0], 
            self::JSON_FLAG
        );

        $new['credit_amount'] = $old['amount_due'];
        $new['payment_method'] = $old['payment_method'];

        // Details
        $details = json_decode($old['items'], true, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Fetch Item Identifiers
        $item_identifiers = self::fetch_item_identifier($details, $db);
        $count = count($details);
        $new_details = [];
        for($i = 0; $i < $count; ++$i) {
            $temp = self::format_details($details[$i], $item_identifiers);
            $new_details[]= $temp;
        }

        $new['disable_federal_taxes'] = $old['disable_gst_hst_tax_for_txn'];
        if($old['store_id'] === StoreDetails::VANCOUVER || $old['store_id'] === StoreDetails::DELTA) {
            $disable_pst_tax = $old['total_pst_tax'] == 0 ? 1 : 0;
        }
        else $disable_pst_tax = 0;
        $new['disable_provincial_taxes'] = $disable_pst_tax;

        // Amount Details
        $amounts = self::calculate_amount($new_details, $new['disable_federal_taxes'], $disable_pst_tax);

        $new['details'] = json_encode($new_details, self::JSON_FLAG);

        // $new['sum_total'] = $amounts['sumTotal'];
        // $new['sub_total'] = $amounts['subTotal'];
        // $new['gst_hst_tax'] = $amounts['gstHSTTax'];
        // $new['pst_tax'] = $amounts['pstTax'];
        // $new['txn_discount'] = $amounts['txnDiscount'];

        $new['sum_total'] = $old['credit_amount'];
        $new['sub_total'] = Utils::round($old['credit_amount'] - $old['total_gst_hst_tax'] - $old['total_pst_tax'], 2);
        $new['gst_hst_tax'] = $old['total_gst_hst_tax'];
        $new['pst_tax'] = $old['total_pst_tax'];
        $new['txn_discount'] = $old['total_discount'];
        
        $new['store_id'] = $old['store_id'];
        $new['notes'] = $old['notes'];
        $new['sales_rep_id'] = $old['sales_rep_id'];
        $new['amount_eligible_for_receipt_discount'] = Shared::calculate_amount_eligible_for_receipt_discount($new_details);

        $new['receipt_discount'] = self::calculate_receipt_discount($old['id'], $db);
        
        $new['early_payment_discount'] = $old['early_payment_discount'];
        $new['early_payment_paid_within_days'] = $old['early_payment_paid_within_days'];
        $new['net_amount_due_within_days'] = $old['net_due_within_days'];
        $new['versions'] = null;
        $new['__lock_counter'] = $old['_lock'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till): array {
        $db = get_old_db_instance();
        $query = 'SELECT * FROM sales_return WHERE id >= :_from ';
        $values = [':_from' => $from];
        if(is_numeric($till)) {
            $query .= ' AND id <= :_till ';
            $values[':_till'] = $till;
        }

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $txn = [];
        foreach($records as $record) {
            $txn[]= self::format($record, $db);
        }
        return $txn;
    }

    public static function write(array $records): void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();
            $query = <<<'EOS'
            INSERT INTO sales_return
            (
                `id`,
                `sales_invoice_id`,
                `client_id`,
                `date`,
                `shipping_address`,
                `credit_amount`,
                `sum_total`,
                `sub_total`,
                `gst_hst_tax`,
                `pst_tax`,
                `txn_discount`,
                `receipt_discount`,
                `payment_method`,
                `details`,
                `store_id`,
                `notes`,
                `sales_rep_id`,
                `amount_eligible_for_receipt_discount`,
                `disable_federal_taxes`,
                `disable_provincial_taxes`,
                `early_payment_discount`,
                `early_payment_paid_within_days`,
                `net_amount_due_within_days`,
                `sales_invoice_payment_method`,
                `versions`,
                `__lock_counter`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :sales_invoice_id,
                :client_id,
                :date,
                :shipping_address,
                :credit_amount,
                :sum_total,
                :sub_total,
                :gst_hst_tax,
                :pst_tax,
                :txn_discount,
                :receipt_discount,
                :payment_method,
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
                :sales_invoice_payment_method,
                :versions,
                :__lock_counter,
                :created,
                :modified
            );
            EOS;

            $statement = $db -> prepare($query);
            foreach($records as $r) {
                $values = self::add_colon_before_keyname($r);
                $statement -> execute($values);
                if($db -> lastInsertId() === false) throw new Exception('UNABLE TO INSERT');
            }

            assert_success();
            $db -> commit();
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            echo $e -> getMessage();
        }
    } 

    public static function validate(int $from, ?int $till=null): void {
        $db_old = get_old_db_instance();
        $db = get_db_instance();
        $values = [];
        $values[':_from'] = $from;

        $query = 'SELECT * FROM sales_return where id >= :_from ';

        if(is_numeric($till)) {
            $values[':_till'] = $till;
            $query .= ' AND id <= :_till';
        }
        $query .= ';';
        $statement_fetch_old = $db_old -> prepare($query);
        $statement_fetch_new = $db -> prepare($query);
        $statement_fetch_old -> execute($values);
        $statement_fetch_new -> execute($values);

        $old_txns = self::set_id_as_index($statement_fetch_old -> fetchAll(PDO::FETCH_ASSOC));
        $new_txns = self::set_id_as_index($statement_fetch_new -> fetchAll(PDO::FETCH_ASSOC));

        $txn_keys = array_keys($old_txns);

        $old_keys = [
            'invoice_id',
            'client_id', 
            'payment_method', 
            'credit_amount', 
            'amount_due',
            'total_gst_hst_tax', 
            'return_date',
            'sales_rep_id',
            'store_id',
            'created',
            'modified',
            'notes',
            '_lock',
            'total_pst_tax',
            'total_discount',
            'early_payment_discount',
            'early_payment_paid_within_days',
            'net_due_within_days',
            'amount_eligible_for_receipt_discount',
            'disable_gst_hst_tax_for_txn',
        ];

        $new_keys = [
            'sales_invoice_id',
            'client_id',
            'payment_method',
            'sum_total',
            'credit_amount',
            'gst_hst_tax',
            'date',
            'sales_rep_id',
            'store_id',
            'created',
            'modified',
            'notes',
            '__lock_counter',
            'pst_tax',
            'txn_discount',
            'early_payment_discount',
            'early_payment_paid_within_days',
            'net_amount_due_within_days',
            'amount_eligible_for_receipt_discount',
            'disable_federal_taxes',
        ];
        if(count($old_keys) !== count($new_keys)) die('KEYS NOT SAME');
        $no_of_keys = count($old_keys);
        foreach($txn_keys as $invoice_id) {
            for($i = 0; $i < $no_of_keys; ++$i) {
                if($old_txns[$invoice_id][$old_keys[$i]] != $new_txns[$invoice_id][$new_keys[$i]] && $old_keys[$i] !== 'amount_eligible_for_receipt_discount') {
                    echo $invoice_id. ' | '. $old_keys[$i]. ' | '. $new_keys[$i]. ' | '. $old_txns[$invoice_id][$old_keys[$i]]. ' | '. $new_txns[$invoice_id][$new_keys[$i]].'<br>';
                }
            }
        }
    }   
}

?>