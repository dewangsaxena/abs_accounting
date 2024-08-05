<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class CreditDebitNoteTransfer extends Common {

    /**
     * Format Details
     */
    private static function format_details(array $old): array {
        $new = [];
        $new['itemId'] = $old['id'];
        $new['amountPerItem'] = $old['amount'];
        $new['basePrice'] = $old['base_price'];
        $new['identifier'] = $old['item_identifier'];
        $new['description'] = $old['description'];
        $new['discountRate'] = $old['discount'];
        $new['pricePerItem'] = $old['price'];
        $new['quantity'] = $old['quantity'];
        $new['gstHSTTaxRate'] = 5;
        $new['pstTaxRate'] = $old['tax'] === 12 ? 7 : 0;
        $new['buyingCost'] = 0;
        $new['originalSellingPrice'] = 0;
        $new['unit'] = 'Each';
        // $new['account'] = [
        //     'assets' => 1520, 
        //     'revenue' => 4020,
        //     'cogs' => 5020,
        //     'variance' => 5100,
        //     'expense' => 5020,
        //     'payable' => $old['account'],
        // ];
        $new['isBackOrder'] = 0;
        return $new;
    }

    private static function format(array $old, string $table_name): array {
        $new = [];

        $new['id'] = $old['id'];
        $new['client_id'] = $old['client_id'];
        $new['date'] = $old["$table_name".'_date'];
        $new['credit_amount'] = $old[$table_name === 'credit_note' ? 'credit_amount' : 'debit_amount'];
        $old_details = json_decode($old[$table_name === 'credit_note' ? 'credit_notes' : 'debit_notes'], true, self::JSON_FLAG);
        $new_details = [];
        foreach($old_details as $old_detail) {
            $new_details[]= self::format_details($old_detail);
        }
        $new['details'] = json_encode($new_details, self::JSON_FLAG);

        $new['store_id'] = $old['store_id'];
        $new['notes'] = $old['notes'];
        $new['sales_rep_id'] = $old['sales_rep_id'];
        $new['disable_federal_taxes'] = $old['disable_gst_hst_tax_for_txn'];
        if($old['store_id'] === StoreDetails::VANCOUVER || $old['store_id'] === StoreDetails::DELTA) {
            $disable_pst_tax = $old['total_pst_tax'] == 0 ? 1 : 0;
        }
        else $disable_pst_tax = 0;
        $new['disable_provincial_taxes'] = $disable_pst_tax;

        // Calculate Amount
        $amounts = Shared::calculate_amount($new_details, $new['disable_federal_taxes'], $new['disable_provincial_taxes']);
        // $new['sum_total'] = $amounts['sumTotal'];
        // $new['sub_total'] = $amounts['subTotal'];
        // $new['gst_hst_tax'] = $amounts['gstHSTTax'];
        // $new['pst_tax'] = $amounts['pstTax'];
        // $new['txn_discount'] = $amounts['txnDiscount'];   

        $new['sum_total'] = $old['total_amount'];
        $new['sub_total'] = Utils::round($old['total_amount'] - $old['total_gst_hst_tax'] - $old['total_pst_tax'], 2);
        $new['gst_hst_tax'] = $old['total_gst_hst_tax'];
        $new['pst_tax'] = $old['total_pst_tax'];
        $new['txn_discount'] = $old['total_discount'];   

        $new['versions'] = null;
        $new['__lock_counter'] = $old['_lock'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till, string $table_name): array {
        $db = get_old_db_instance();
        $query = "SELECT * FROM $table_name WHERE id >= :_from ";
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
            $txn[]= self::format($record, $table_name);
        }
        return $txn;
    }

    public static function write(array $records, string $table_name): void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            $query = <<<EOS
            INSERT INTO $table_name 
            (
                `id`,
                `client_id`,
                `date`,
                `credit_amount`,
                `sum_total`,
                `sub_total`,
                `gst_hst_tax`,
                `pst_tax`,
                `txn_discount`,
                `details`,
                `store_id`,
                `notes`,
                `sales_rep_id`,
                `disable_federal_taxes`,
                `disable_provincial_taxes`,
                `versions`,
                `__lock_counter`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :client_id,
                :date,
                :credit_amount,
                :sum_total,
                :sub_total,
                :gst_hst_tax,
                :pst_tax,
                :txn_discount,
                :details,
                :store_id,
                :notes,
                :sales_rep_id,
                :disable_federal_taxes,
                :disable_provincial_taxes,
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
            echo $e -> getMessage();
            if($db -> inTransaction()) $db -> rollBack();
        }
    }

    public static function validate(string $table_name, int $from, ?int $till=null): void {
        $db_old = get_old_db_instance();
        $db = get_db_instance();
        $values = [];
        $values[':_from'] = $from;

        $query = "SELECT * FROM $table_name where id >= :_from ";

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
            'client_id', 
            'total_amount', 
            $table_name === 'credit_note' ? 'credit_amount' : 'debit_amount', 
            'total_gst_hst_tax', 
            'total_discount',
            'notes',
            'store_id',
            $table_name === 'credit_note' ? 'credit_note_date' : 'debit_note_date',
            'sales_rep_id',
            '_lock',
            'created',
            'modified',
            'total_pst_tax',
        ];

        $new_keys = [
            'client_id',
            'sum_total',
            'credit_amount',
            'gst_hst_tax',
            'txn_discount',
            'notes',
            'store_id',
            'date',
            'sales_rep_id',
            '__lock_counter',
            'created',
            'modified',
            'pst_tax',
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