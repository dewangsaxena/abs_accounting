<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";

class Debug {
    /**
     * <key>: <value>
     */
    public static array $data = [];

    /**
     * This method will write data to db for debugging.
     * @param db
     * @param store_id
     * @throws Exception
     */
    public static function  write_to_db(PDO &$db, int $store_id): void {
        if(SYSTEM_INIT_HOST !== __PARTS_V2__) return;
        $statement = $db -> prepare('INSERT INTO debug(store_id, details) VALUES (:store_id, :details);');
        $is_successful = $statement -> execute([
            ':store_id' => $store_id,
            ':details' => json_encode(self::$data, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
        ]);
        if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Write to Debug Table.');
    }

    /**
     * This method will set current inventory value.
     * @param key
     * @param db
     * @param store_id
     * @return void
     */
    public static function set_current_inventory_value(string $key, PDO &$db, int $store_id): void {
        $debug_fetch_db_statement = $db -> prepare('SELECT `statement` FROM balance_sheet WHERE store_id = :store_id ORDER BY `date` DESC LIMIT 1;');
        $debug_fetch_db_statement -> execute([':store_id' => $store_id]);
        $debug_fetch_result = $debug_fetch_db_statement -> fetchAll(PDO::FETCH_ASSOC);
        if(count($debug_fetch_result)) {
            $debug_bs_statement = json_decode($debug_fetch_result[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            Debug::$data[$key] = $debug_bs_statement[1520];
        }
    }
}