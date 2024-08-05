<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class ReceiptConvert extends Common {

    private const TYPE_INDEX = [
        'IN' => SALES_INVOICE,
        'SR' => SALES_RETURN,
        'CN' => CREDIT_NOTE,
        'DN' => DEBIT_NOTE,
    ];

    private const TABLE_NAME = [
        SALES_INVOICE => 'sales_invoice',
        SALES_RETURN => 'sales_return',
        CREDIT_NOTE => 'credit_note',
        DEBIT_NOTE => 'debit_note',
    ];

    private static function fetch_payment_method_and_sales_invoice_id_of_sales_invoice_of_sr(int $sales_return_id, PDOStatement &$statement_fetch_sr, PDOStatement &$statement_fetch_si) : array {
        $statement_fetch_sr -> execute([':id' => $sales_return_id]);
        $sales_invoice_id = $statement_fetch_sr -> fetchAll(PDO::FETCH_ASSOC)[0]['invoice_id'];

        $statement_fetch_si -> execute([':id' => $sales_invoice_id]);
        $payment_method = $statement_fetch_si -> fetchAll(PDO::FETCH_ASSOC)[0]['payment_method'];

        return ['invoice_id' => $sales_invoice_id, 'payment_method' => $payment_method];
    }

    /**
     * Fetch Created Timestamp of Txn.
     * @param tnx_id
     * @param type
     * @param db
     * @return string
     */
    private static function fetch_created_timestamp_of_txn(int $txn_id, int $type, PDO &$db) : string {
        $table_name = self::TABLE_NAME[$type];
        $query = "SELECT `created`, `modified` FROM $table_name WHERE id = :id;";
        $statement = $db -> prepare($query);
        $statement -> execute([':id' => $txn_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        return $result[0]['created'];
    }

    /**
     * Format Details
     */
    private static function format_details(array $old, PDO &$db, PDOStatement &$statement_fetch_sr, PDOStatement &$statement_fetch_si): array {
        $new = [];
        $new['id'] = $old['id'];
        $new['txnId'] = $old['type'].'-'. $old['id'];
        $type = $old['type'];
        $type_id = self::TYPE_INDEX[$type];
        $new['type'] = $type_id;
        $new['date'] = $old['date'];
        $new['originalAmount'] = $old['original_amount'];
        $new['amountOwing'] = $old['amount_owing'];
        $new['discountAvailable'] = $old['discount_available'];
        $new['discountGiven'] = $old['discount_taken'];
        $new['amountReceived'] = $old['amount_received'];

        if($type_id === SALES_RETURN) {
            $sales_invoice_details = self::fetch_payment_method_and_sales_invoice_id_of_sales_invoice_of_sr($old['id'], $statement_fetch_sr, $statement_fetch_si);
            $new['salesInvoiceId'] = $sales_invoice_details['invoice_id'];
            $new['salesInvoicePaymentMethod'] = $sales_invoice_details['payment_method'];
        }
        $new['modified'] = self::fetch_created_timestamp_of_txn($new['id'], $type_id, $db);
        return $new;
    }

    private static function format(array $old, PDO &$db, PDOStatement &$statement_fetch_sr, PDOStatement &$statement_fetch_si) : array {
        $new = [];
        $new['id'] = $old['id'];
        $new['client_id'] = $old['client_id'];
        $new['date'] = $old['receipt_date'];
        $old_details = json_decode($old['receipts'], true, self::JSON_FLAG);
        $new_details = [];
        foreach($old_details as $old_detail) {
            $new_details[]= self::format_details($old_detail, $db, $statement_fetch_sr, $statement_fetch_si);
        }

        $new['details'] = json_encode($new_details, self::JSON_FLAG);
        $new['sum_total'] = $old['total_amount'];
        $new['total_discount'] = $old['total_discount'];
        $new['payment_method'] = $old['payment_method'];
        $new['cheque_number'] = $old['cheque_no'];
        $new['comment'] = $old['comment'];
        $new['store_id'] = $old['store_id'];
        $new['do_conceal'] = $old['do_conceal'];
        $new['sales_rep_id'] = $old['sales_rep_id'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till): array {
        $db = get_old_db_instance();
        $query = "SELECT * FROM receipts WHERE id >= :_from ";
        $values = [':_from' => $from];
        if(is_numeric($till)) {
            $query .= ' AND id <= :_till ';
            $values[':_till'] = $till;
        }

        $statement_fetch_sr = $db -> prepare('SELECT * FROM sales_return WHERE id = :id;');
        $statement_fetch_si = $db -> prepare('SELECT * FROM sales_invoice WHERE id = :id;');
        $txn = [];
        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        foreach($records as $record) {
            $txn[]= self::format($record, $db, $statement_fetch_sr, $statement_fetch_si);
        }
        return $txn;
    }

    public static function write(array $records): void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            $query = <<<EOS
            INSERT INTO receipt
            (
                `id`,
                `client_id`,
                `date`,
                `details`,
                `sum_total`,
                `total_discount`,
                `payment_method`,
                `cheque_number`,
                `comment`,
                `store_id`,
                `do_conceal`,
                `sales_rep_id`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :client_id,
                :date,
                :details,
                :sum_total,
                :total_discount,
                :payment_method,
                :cheque_number,
                :comment,
                :store_id,
                :do_conceal,
                :sales_rep_id,
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

    public static function verify(int $from, ?int $till=null): void {
        
    }
}

?>