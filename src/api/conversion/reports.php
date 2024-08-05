<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";

class Report extends Common {

    private static function format(array $old) : array {
        $new = [];

        $new['id'] = $old['id'];
        $new['statement'] = $old['_statement'];
        $new['date'] = $old['_date'];
        $new['store_id'] = $old['store_id'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];

        return $new;
    }

    public static function read(int $from, ?int $till, string $table_name) : array {
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
        $balance_sheets = [];
        foreach($records as $record) {
            $balance_sheets[]= self::format($record);
        }
        return $balance_sheets;
    }

    public static function write(array $records, string $table_name) : void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();
            $query = <<<EOS
            INSERT INTO $table_name
            (
                `id`,
                `statement`,
                `date`,
                `store_id`,
                `created`,
                `modified`
            )
            VALUES
            (
                :id,
                :statement,
                :date,
                :store_id,
                :created,
                :modified
            );
            EOS;

            foreach($records as $record) {
                $statement = $db -> prepare($query);
                $values = self::add_colon_before_keyname($record);
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
}
?>