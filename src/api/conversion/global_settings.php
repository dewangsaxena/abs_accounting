<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class GlobalSettingsTransfer extends Common {

    private static function format(array $old) : array {
        return [
            'STP' => $old['stp'],
            'DEFAULT' => $old['non_stp'],
            'GRO' => $old['gro'],
            'PHI' => $old['phi'],
            'STM' => $old['stm'],
            'DON' => $old['don'],
        ];
    }

    public static function read(): array {
        $db = get_old_db_instance();

        $query = 'SELECT _value FROM global_settings';
        $statement = $db -> prepare($query);
        $statement -> execute();
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $new_details = [];
        $margins = json_decode($result[0]['_value'], true, flags: self::JSON_FLAG);
        $stores = array_keys($margins);
        foreach($stores as $store) {
            $details = $margins[$store];
            $new_details[$store] = self::format($details);
        }

        return $new_details;
    }

    public static function write(array $details): void {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();
            $query = <<<'EOS'
            UPDATE 
                store_details 
            SET
                profit_margins = :profit_margins 
            WHERE 
                id = :id;
            EOS;

            $statement = $db -> prepare($query);
            $stores = array_keys($details);
            foreach($stores as $store) {
                $statement -> execute([
                    ':profit_margins' => json_encode($details[$store], self::JSON_FLAG),
                    ':id' => $store
                ]);

                if($statement -> rowCount() < 1) throw new Exception('UNABLE TO UPDATE');
            }
            $db -> commit();
        }
        catch(Exception $e) {
            $db -> rollBack();
            echo $e -> getMessage();
        }
    }
}


?>