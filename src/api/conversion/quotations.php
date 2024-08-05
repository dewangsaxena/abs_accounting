<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class QuotationsConvert extends Common {

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
        $new['quantity'] = $old['quantity'];
        $new['gstHSTTaxRate'] = 5;
        $new['pstTaxRate'] = $old['tax'] === 12 ? 7 : 0;
        $new['buyingCost'] = $old['our_buying_price'];
        $new['originalSellingPrice'] = $old['base_price'];
        $new['unit'] = $old['unit'];
        $new['account'] = [
            'assets' => 1520, 
            'revenue' => 4020,
            'cogs' => 5020,
            'variance' => 5100,
            'expense' => 5020,
        ];
        $new['isBackOrder'] = 0;
        return $new;
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

    private static function format(array $old, PDO &$db): array {
        $new = [];
        $new['id'] = $old['id'];
        $new['client_id'] = $old['client_id'];
        $new['date'] = $old['quotation_date'];

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

        $new['sum_total'] = $old['total_payable'];
        $new['sub_total'] = Utils::round($old['total_payable'] - $old['total_gst_hst_tax'] - $old['total_pst_tax'], 2);
        $new['gst_hst_tax'] = $old['total_gst_hst_tax'];
        $new['pst_tax'] = $old['total_pst_tax'];
        $new['txn_discount'] = $old['total_discount'];
        
        $new['store_id'] = $old['store_id'];
        $new['notes'] = $old['notes'];
        $new['sales_rep_id'] = $old['sales_rep_id'];
        
        $new['account_number'] = $old['account_number'];
        $new['versions'] = null;
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till): array {
        $db = get_old_db_instance();
        $query = 'SELECT * FROM quotations WHERE id >= :_from ';
        $values = [':_from' => $from];
        if(is_numeric($till)) {
            $query .= ' AND id <= :_till ';
            $values[':_till'] = $till;
        }

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $sales_invoices = [];
        foreach($records as $record) {
            $sales_invoices[]= self::format($record, $db);
        }
        return $sales_invoices;
    }

    public static function write(array $records): void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();
            $query = <<<'EOS'
            INSERT INTO quotation
            (
                `id`,
                `client_id`,
                `date`,
                `sum_total`,
                `sub_total`,
                `gst_hst_tax`,
                `pst_tax`,
                `txn_discount`,
                `details`,
                `store_id`,
                `notes`,
                `sales_rep_id`,
                `account_number`,
                `disable_federal_taxes`,
                `disable_provincial_taxes`,
                `versions`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :client_id,
                :date,
                :sum_total,
                :sub_total,
                :gst_hst_tax,
                :pst_tax,
                :txn_discount,
                :details,
                :store_id,
                :notes,
                :sales_rep_id,
                :account_number,
                :disable_federal_taxes,
                :disable_provincial_taxes,
                :versions,
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

    /**
     * This method will validate sales invoice details.
     * @param from
     * @param till
     */
    public static function validate(int $from, ?int $till=null): void {
        $db_old = get_old_db_instance();
        $db = get_db_instance();
        $values = [];
        $values[':_from'] = $from;

        $query_old = 'SELECT * FROM quotations where id >= :_from ';
        $query_new = 'SELECT * FROM quotation where id >= :_from ';

        if(is_numeric($till)) {
            $values[':_till'] = $till;
            $query_old .= ' AND id < :_till';
            $query_new .= ' AND id < :_till';
        }
        $query_old .= ';';
        $query_new .= ';';
        $statement_fetch_old = $db_old -> prepare($query_old);
        $statement_fetch_new = $db -> prepare($query_new);
        $statement_fetch_old -> execute($values);
        $statement_fetch_new -> execute($values);

        $old_txns = self::set_id_as_index($statement_fetch_old -> fetchAll(PDO::FETCH_ASSOC));
        $new_txns = self::set_id_as_index($statement_fetch_new -> fetchAll(PDO::FETCH_ASSOC));

        $txn_keys = array_keys($old_txns);

        $old_keys = [
            'client_id', 
            'total_payable',
            'total_gst_hst_tax',
            'total_discount',
            'quotation_date',
            'store_id',
            'notes',
            'sales_rep_id',
            'created',
            'modified',
            'total_pst_tax',
        ];

        $new_keys = [
            'client_id',
            'sum_total',
            'gst_hst_tax',
            'txn_discount',
            'date',
            'store_id',
            'notes',
            'sales_rep_id',
            'created',
            'modified',
            'pst_tax',
        ];
        
        if(count($old_keys) !== count($new_keys)) die('KEYS NOT SAME');
        $no_of_keys = count($old_keys);
        foreach($txn_keys as $txn_id) {
            for($i = 0; $i < $no_of_keys; ++$i) {
                if($old_txns[$txn_id][$old_keys[$i]] != $new_txns[$txn_id][$new_keys[$i]] && $old_keys[$i] !== 'amount_eligible_for_receipt_discount') {
                    echo $txn_id. ' | '. $old_keys[$i]. ' | '. $new_keys[$i]. ' | '. $new_txns[$txn_id][$old_keys[$i]]. ' | '. $new_txns[$txn_id][$new_keys[$i]].'<br>';
                }
            }
        }
    }   
}

?>