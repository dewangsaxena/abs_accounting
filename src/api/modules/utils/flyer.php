<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/email.php";


class FlyerManagement {

    /**
     * This method will fetch client details of store.
     * @param store_id
     * @return array
     */
    public static function fetch_client_detail_of_store(int $store_id): array {
        try {
            $db = get_db_instance();
            $query = <<<'EOS'
            SELECT DISTINCT 
                client_id
            FROM
                sales_invoice 
            WHERE 
                store_id = :store_id;
            EOS;

            $statement = $db -> prepare($query);
            $statement -> execute([':store_id' => $store_id]);
            $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $client_ids = [];
            foreach($results as $result) {
                $client_ids []= $result['client_id'];
            }
            
            // Fetch CLient Details
            $query = <<<'EOS'
            SELECT 
                `id`,
                `name`,
                `email_id`
            FROM
                clients 
            WHERE 
                id IN (:placeholder);
            EOS;

            $results = Utils::mysql_in_placeholder_pdo_substitute($client_ids, $query);
            $query = $results['query'];
            $values = $results['values'];
            
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $client_details = $statement -> fetchAll(PDO::FETCH_ASSOC);

            $valid_clients = [];
            foreach($client_details as $c) {
                if(isset($c['email_id'][0])) $valid_clients[]= [
                    'name' => $c['name'],
                    'email_id' => $c['email_id'],
                ];
            }
            return $valid_clients;
        }
        catch(Exception $e) {
            echo $e -> getMessage();
        }
    }

    /**
     * This method will send flyer to clients of the given store.
     * @param store_id
     * @param subject
     * @param content
     * @param path_to_attachment
     * @param file_name
     */
    public static function send_flyer(int $store_id, string $subject, string $content, string $path_to_attachment, string $file_name): void {

        // Fetch client details
        // $client_details = self::fetch_client_detail_of_store($store_id);

        // Test
        $client_details = [['name' => 'Dewang Saxena', 'email_id' => 'dewang2610@gmail.com']];
        foreach($client_details as $client) {
            Email::send(
                subject: $subject,
                recipient_email: $client['email_id'],
                recipient_name: $client['name'],
                content: $content,
                path_to_attachment: $path_to_attachment,
                file_name: $file_name,
                store_id: $store_id,
                additional_email_addresses: null,
                is_html: true,
                add_cc: false,
            );
        }
    }
}

?>