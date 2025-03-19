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


class Vendor {

    public const ADD = 'vendor_add';
    public const UPDATE = 'vendor_update';
    public const FETCH = 'vendor_fetch';

    /**
     * This method will validate details.
     * @param details
     */
    private static function validate_details(array &$details): void {
        if(isset($details['name']) === false || Validate::is_name($details['name']) == false) {
            throw new Exception('Vendor name is Invalid.');
        }
    }

    /**
     * This method will format details.
     * @param details
     */
    private static function format_details(array &$details): void {
        $details['name'] = trim(ucwords(strtolower($details['name'])));
    }

    /**
     * This method will fetch vendor details. 
     * @param filters
     * @return array
     */
    public static function fetch(array $filters=[]): array {
        $db = get_db_instance();
        
        $query = <<<'EOS'
        SELECT 
            *
        FROM
            vendors
        WHERE
            1 
        EOS;

        $params = [];

        // ID 
        if(isset($filters['id'])) {
            $query .= ' AND id = :id ';
            $params[':id'] = $filters['id'];
        }

        // Search by name
        else if(isset($filters['term'])) {
            $query .= ' AND `name` LIKE :term ';
            $params[':term'] = '%'.$filters['name'].'%';
        }

        $query .= ';';
        $statement = $db -> prepare($query);
        $statement -> execute($params);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Store Id
        $store_id = intval($_SESSION['store_id']);
        $vendor_records = [];
        foreach($records as $record) {
            $is_inactive = json_decode($record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $is_inactive = $is_inactive[$store_id] ?? 0;
            $vendor_records[]= [
                'id' => $record['id'],
                'name' => $record['name'],
                'isInactive' => $is_inactive,
            ];
        }
        return $vendor_records;
    }

    /**
     * This method will create vendor.
     * @param details
     * @return array
     */
    public static function create(array $details): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Validate Details
            self::validate_details($details);

            // Format Details
            self::format_details($details);

            // Store ID
            $store_id = intval($_SESSION['store_id']);

            $query = <<<'EOS'
            INSERT INTO vendors
            (
                `name`,
                `is_inactive`
            )
            VALUES
            (
                :name,
                :is_inactive
            );
            EOS;
            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute([
                ':name' => $details['name'],
                ':is_inactive' => json_encode([$store_id => false], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ]);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Add Vendor.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will update vendor name.
     * @param details
     * @return array
     */
    public static function update(array $details) : array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Validate 
            self::validate_details($details);

            // Format
            self::format_details($details);

            $query = <<<'EOS'
            UPDATE
                vendors
            SET 
                `name` = :name,
                `is_inactive` = :is_inactive,
                `modified` = CURRENT_TIMESTAMP
            WHERE 
                `id` = :id;
            EOS;
            $statement = $db -> prepare($query);
            $statement -> execute([
                ':name' => $details['name'],
                ':is_inactive' => [],
            ]);

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }
}