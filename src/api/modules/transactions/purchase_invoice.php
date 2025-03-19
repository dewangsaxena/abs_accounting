<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class PurchaseInvoice {

    // Create
    public const CREATE = 'vendor_create';

    /**
     * This method will create purchase invoice.
     * @param details
     * @return array
     */
    private static function create(array $details): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();



            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    public static function process(array $details): array {
        return [];
    }
}

?>