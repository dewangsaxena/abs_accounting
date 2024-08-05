<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";

function set_last_purchase_date() : void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        $query = <<<'EOS'
        select client_id, store_id, `date` from sales_invoice ORDER BY `date` desc;
        EOS;

        $statement = $db -> prepare($query);
        $statement -> execute();
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $clients = [];
        foreach($result as $r) {
            $client_id = $r['client_id'];
            if(isset($clients[$client_id]) === false) {
                $clients[$client_id] = [];
            }

            if(isset($clients[$client_id][$r['store_id']]) === false) {
                $clients[$client_id][$r['store_id']] = $r['date'];
            }
        }

        $query = <<<'EOS'
        UPDATE 
            clients 
        SET 
            last_purchase_date = :last_purchase_date
        WHERE 
            id = :client_id;
        EOS;

        $statement = $db -> prepare($query);

        $client_ids = array_keys($clients);
        foreach($client_ids as $client_id) {
            $is_successful = $statement -> execute([
                ':client_id' => $client_id,
                ':last_purchase_date' => json_encode($clients[$client_id], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ]);

            if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to update '. $client_id);
        }

        assert_success();

        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

set_last_purchase_date();

?>