<?php 

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
    public static function write_to_db(PDO &$db, int $store_id): void {
        $statement = $db -> prepare('INSERT INTO debug(store_id, details) VALUES (:store_id, :details);');
        $is_successful = $statement -> execute([
            ':store_id' => $store_id,
            ':details' => json_encode(self::$data, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
        ]);
        if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Write to Debug Table.');
    }
}