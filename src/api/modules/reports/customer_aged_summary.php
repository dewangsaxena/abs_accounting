<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/special_exceptions.php";

/**
 * This module will implement class to handle customer summary.
 */
class CustomerAgedSummary {

    // Till Date
    public static string|DateTime $till_date = '';

    /**
     * Create Customer Summary 
     */
    private const CREATE_SUMMARY = <<<'EOS'
    INSERT INTO customer_aged_summary
    (
        `summary`,
        `date`,
        `store_id`
    )
    VALUES
    (
        :summary,
        :date,
        :store_id
    );
    EOS;

    // Fetch
    private const FETCH_CUSTOMER_SUMMARY_AS_PER_STORE_AND_DATE = <<<'EOS'
    SELECT 
        * 
    FROM 
        customer_aged_summary 
    WHERE 
        store_id = :store_id
    AND
        `date` = :date 
    ORDER BY 
        `date` DESC
    LIMIT 1;
    EOS;

    // Fetch 
    private const FETCH_QUERY = <<<'EOS'
    SELECT 
        *,
        c.`name` AS client_name, 
        c.phone_number_1 AS phone_1,
        c.phone_number_2 AS phone_2
    FROM (
            SELECT
                1 AS txn_type,
                txn.id AS sales_invoice_id,
                txn.`date` AS txn_date,
                txn.credit_amount AS amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                sales_invoice AS txn
            WHERE 
                txn.store_id = :store_id
            AND 
                txn.`date` >= :from_date 
            AND 
                txn.`date` <= :till_date
            AND
                txn.credit_amount > 0.0000 
            __CLIENT_SELECT__
            UNION 
            SELECT 
                2 AS txn_type,
                txn.sales_invoice_id AS sales_invoice_id,
                txn.`date` AS txn_date,
                -txn.credit_amount AS amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM    
                sales_return AS txn 
            WHERE
                txn.store_id = :store_id
            AND
                txn.`date` >= :from_date
            AND
                txn.`date` <= :till_date
            AND
                ABS(txn.credit_amount) > 0.0000
            __CLIENT_SELECT__
            UNION
            SELECT 
                3 AS txn_type,
                NULL AS sales_invoice_id,
                txn.`date` AS txn_date,
                -txn.credit_amount AS amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                credit_note AS txn
            WHERE 
                txn.store_id = :store_id
            AND 
                txn.`date` >= :from_date
            AND
                txn.`date` <= :till_date
            AND 
                ABS(txn.credit_amount) > 0.0000 
            __CLIENT_SELECT__
            UNION
            SELECT 
                4 AS txn_type,
                NULL AS sales_invoice_id,
                txn.`date` AS txn_date,
                txn.credit_amount AS amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                debit_note AS txn
            WHERE 
                txn.store_id = :store_id
            AND
                txn.`date` >= :from_date
            AND 
                txn.`date` <= :till_date 
            AND
                txn.credit_amount > 0.0000
            __CLIENT_SELECT__
        ) AS _tmp
    LEFT JOIN 
        clients AS c
    ON 
        client_id = c.id
    ORDER BY
        txn_type ASC 
    EOS;
        
    /**
     * This method will check whether statement exists for a particular date for a store.
     * @param store_id
     * @param date
     * @param db
     * @return bool
     */
    private static function check_statement_exists(int $store_id, string $date, PDO &$db=null): bool {
        $statement = $db -> prepare(self::FETCH_CUSTOMER_SUMMARY_AS_PER_STORE_AND_DATE);
        $statement -> execute([
            ':store_id' => $store_id,
            ':date' => $date,
        ]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        return isset($result[0]);
    }

    /**
     * This method will group by date different records.
     * @param source_type
     * @param record
     * @param summary 
     * @param store_id
     */
    private static function group_by_date(array $record, array &$summary, int $store_id) : void {

        // Actual amount Owing 
        $actual_amount_owing = $record['amount'];

        // Skip processing
        if($actual_amount_owing == 0.0000) return;

        // Cache Client ID
        $client_id = $record['client_id'];

        // Check whether the key exists 
        // If no, then add one.
        if(!array_key_exists($client_id, $summary)) {
            $summary[$client_id] = [
                'client_id' => $client_id,
                'client_name' => $record['client_name'],
                'phone_number' => 
                Utils::format_phone_number(
                    $record['phone_1'] ?? $record['phone_2'] ?? ''
                ),
                'total' =>      0.00,
                'current' =>    0.00,
                '31-60' =>      0.00,
                '61-90' =>      0.00,
                '91+' =>        0.00
            ];
        }

        $aged_summary = Shared::get_txn_age($record['txn_date'], self::$till_date, $actual_amount_owing, $store_id);
        $summary[$client_id]['current'] += $aged_summary['current'];
        $summary[$client_id]['31-60'] += $aged_summary['31-60'];
        $summary[$client_id]['61-90'] += $aged_summary['61-90'];
        $summary[$client_id]['91+'] += $aged_summary['91+'];
        $summary[$client_id]['total'] += $actual_amount_owing;
    }

    /**
     * This method will remove records with even balance.
     * @param summary
     * @return array
     */
    private static function remove_records_with_even_balance(array $summary): array {
        $new_summary = [];
        foreach($summary as $sr) if($sr['total'] != 0.00) $new_summary[]= $sr;
        return $new_summary;
    }

    /**
     * This method will fetch customer aged summary for client.
     * @param client_id
     * @param store_id
     * @param till_date
     * @return array
     */
    public static function fetch_customer_aged_summary_of_client(int $client_id, int $store_id, string $till_date): array {
        $record = self::fetch_customer_aged_summary(
            $store_id,
            '0000-00-00',
            $till_date,
            0,
            $client_id
        );
        if(isset($record[0])) return $record[0];
        else return [];
    }

    /**
     * This method will exclude self companies.
     * @param summary
     * @return array
     */
    private static function exclude_self_companies(array &$summary): array {
        $new_summary = [];
        foreach($summary as $s) {
            if(Client::is_self_client($s['client_id']) === false) $new_summary[]= $s;
        }
        return $new_summary;
    }

    /**
     * Exclude clients with credit transactions.
     * @param summary
     */
    private static function exclude_credit_transactions(array &$summary): array {
        $new_summary = [];
        foreach($summary as $s) {
            if($s['total'] > 0) $new_summary []= $s;
        }
        return $new_summary;
    }

    /**
     * This method will exclude clients in exclusion list.
     * @param summary
     * @return array
     */
    private static function exclude_clients(array &$summary): array {
        $new_summary = [];
        foreach($summary as $s) {
            if(isset(SpecialExceptions::CUSTOMER_AGED_SUMMARY_CLIENT_EXCLUSIONS[$s['client_id']]) === false) $new_summary[]= $s;
        }
        return $new_summary;
    }

    /**
     * This method will fetch the customer aged summary.
     * @param store_id
     * @param from_date
     * @param till_date
     * @param sort_ascending
     * @param client_id
     * @param exclude_self
     * @param exclude_clients
     * @param omit_credit_records
     * @return array
     */
    public static function fetch_customer_aged_summary(int $store_id, ?string $from_date, string $till_date, int $sort_ascending, int $client_id=null, int $exclude_self=0, int $exclude_clients=0, int $omit_credit_records=0): array {

        // Till Date
        self::$till_date = $till_date;

        $params = [ 
            ':store_id' => $store_id,
            ':till_date' => $till_date
        ];

        if(isset($from_date)) $params[':from_date'] = $from_date;
        else $params[':from_date'] = '0000-00-00';
        
        // Query
        $query = self::FETCH_QUERY. ', amount '. ($sort_ascending === 1 ? 'ASC' : 'DESC').', client_id ASC, txn_date ASC, sales_invoice_id ASC;';

        // Check for Client
        $client_sql_statement = '';
        if(is_numeric($client_id)) {
            $client_sql_statement = 'AND txn.client_id = :client_id ';
            $params[':client_id'] = $client_id;
        }

        // Replace Client Select Placeholder
        $query = str_replace('__CLIENT_SELECT__', $client_sql_statement, $query);
        $db = get_db_instance();
        $statement = $db -> prepare($query);
        $statement -> execute($params);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Prepare summary 
        $summary = [];

        // Convert to DateTime Object
        self::$till_date = date_create(self::$till_date);

        // Group Invoice/Sales Return/Credit Note/Debit Note Records
        foreach($records as $record) {
            self::group_by_date($record, $summary, $store_id);
        }

        // Remove records with even balance.
        $summary = self::remove_records_with_even_balance($summary);

        // Exclude Self
        if($exclude_self) $summary = self::exclude_self_companies($summary);

        // Exclude Clients
        if($exclude_clients) $summary = self::exclude_clients($summary);

        // Omit Credit transactions
        if($omit_credit_records) $summary = self::exclude_credit_transactions($summary);
            
        if($sort_ascending === 1) {
            // Sort the list ASCENDING
            usort($summary, function($x, $y){
                return $x['total'] <=> $y['total'];
            });
        }
        else {
            // Sort the list DESCENDING
            usort($summary, function($x, $y){
                return $y['total'] <=> $x['total'];
            });
        }

        return $summary;
    }

    /**
     * This method will generate CSV.
     * @param details
     * @return string
     */
    private static function generate_csv(array $details): string {
        $path_to_file = TEMP_DIR. Utils::generate_token(16).'.csv';
        $handle = fopen($path_to_file, 'w');
        $summary = $details['summary'];
        fputcsv($handle, ['Client Name', 'Phone Number', 'Total', 'Current', '31-60', '61-90', '91+']);

        $footer = ['**** TOTAL AMOUNTS ****', '', 0, 0, 0, 0, 0];
        foreach($summary as $r) {
            fputcsv($handle, [
                $r['client_name'],
                $r['phone_number'],
                Utils::round($r['total'], 2),
                Utils::round($r['current'], 2),
                Utils::round($r['31-60'], 2),
                Utils::round($r['61-90'], 2),
                Utils::round($r['91+'], 2),
            ]);

            $footer[2] += $r['total'];
            $footer[3] += $r['current'];
            $footer[4] += $r['31-60'];
            $footer[5] += $r['61-90'];
            $footer[6] += $r['91+'];
        }

        // Round off
        $footer[2] = Utils::round($footer[2], 2);
        $footer[3] = Utils::round($footer[3], 2);
        $footer[4] = Utils::round($footer[4], 2);
        $footer[5] = Utils::round($footer[5], 2);
        $footer[6] = Utils::round($footer[6], 2);

        fputcsv($handle, $footer);

        fclose($handle);
        return $path_to_file;
    }

    /**
     * This method will generate pdf.
     * @param store_id
     * @param from_date
     * @param till_date
     * @param sort
     * @param is_csv
     * @param exclude_self
     * @param exclude_clients
     * @param omit_credit_records
     */
    public static function generate(int $store_id, ?string $from_date, string $till_date, int $sort, int $is_csv = 0, int $exclude_self=0, int $exclude_clients=0, int $omit_credit_records=0): void {
        $customer_aged_summary = self::fetch_customer_aged_summary(
            $store_id, 
            $from_date, 
            $till_date, 
            $sort, 
            exclude_self: $exclude_self, 
            exclude_clients: $exclude_clients,
            omit_credit_records: $omit_credit_records,
        );
        $details = [
            'summary' => $customer_aged_summary,
            'date' => date_format(date_create($till_date), 'd M, Y'),
            'store_name' => StoreDetails::STORE_DETAILS[$store_id]['name'],
            'store_id' => $store_id,
        ];

        if($is_csv === 1) {
            // Force User to Dowload File
            Utils::user_download(self::generate_csv($details));
        }
        else GeneratePDF::customer_aged_summary($details);
    }

    /**
     * This method will delete keys.
     * @param statement
     * @return void 
     */
    private static function delete_keys(array &$statement): void {
        $count = count($statement);
        for($i = 0; $i < $count; ++$i) {
            unset($statement[$i]['client_name']);
            unset($statement[$i]['phone_number']);
        }
    }

    /**
     * This method will fetch historical summary.
     * @param store_id
     * @param for_date
     * @param is_csv
     * @param exclude_self Exclude self clients
     * @return void
     */
    public static function fetch_historical_summary(int $store_id, string $for_date, int $is_csv=0, int $exclude_self=0): void {

        // Send Latest Record if Current Day is Requested
        if($for_date === Utils::get_business_date($store_id)) {
            self::generate($store_id, '0000-00-00', $for_date, 0);
        }
        else {
            $db = get_db_instance();
            $statement = $db -> prepare(self::FETCH_CUSTOMER_SUMMARY_AS_PER_STORE_AND_DATE);
            $statement -> execute([
                ':store_id' => $store_id,
                ':date' => $for_date,
            ]);
            $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(isset($record[0]) === false) $summary = [];
            else {
                // Summary
                $summary = json_decode($record[0]['summary'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Fetch Client List
                $client_ids = [];
                foreach($summary as $s) {
                    $client_ids[]= $s['client_id'];
                }

                // Fetch Client Details
                $client_details = Client::fetch_client_name_and_contact_details($client_ids, $db);

                // Insert Data in Summary 
                $count = count($summary);
                for($i = 0; $i < $count; ++$i) {
                    $client_id = $summary[$i]['client_id'];
                    $summary[$i]['client_name'] = $client_details[$client_id]['name'];
                    $summary[$i]['phone_number'] = $client_details[$client_id]['phoneNumber1'];
                }
            }

            // Exclude self.
            if($exclude_self) $summary = self::exclude_self_companies($summary);

            // Details
            $details = [
                'summary' => $summary,
                'date' => date_format(date_create($for_date), 'd M, Y'),
                'store_name' => StoreDetails::STORE_DETAILS[$store_id]['name'],
                'store_id' => $store_id,
            ];

            if($is_csv === 1) {
                // Force User to Dowload File
                Utils::user_download(self::generate_csv($details));
            }
            else GeneratePDF::customer_aged_summary($details);
        }
    }

    /**
     * This method will save last statement.
     * @param store_id
     * @param db
     * @return void
     */
    public static function save_last_statement(int $store_id, PDO &$db=null): void {

        // Current Date
        $current_date = Utils::get_business_date($store_id);

        // Create Date from TimeStamp
        $for_date = date_create($current_date);
        
        // Subtract Date
        date_sub($for_date, date_interval_create_from_date_string('1 days'));

        // Convert to Format
        $for_date = date_format($for_date, 'Y-m-d');

        // Check for Presence of Statement.
        if(self::check_statement_exists($store_id, $for_date, $db) === false) {

            // Fetch Statement
            $statement = self::fetch_customer_aged_summary($store_id, '0000-00-00', $for_date, 0);

            // Delete Keys such as Client Name, and Phone number.
            // These details will be set when data is fetched to ensure latest details are provided.
            self::delete_keys($statement);

            // Add Statement to Database
            $values = [
                ':summary' => json_encode($statement, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':date' => $for_date, 
                ':store_id' => $store_id,
            ];

            // Insert into DB
            $statement = $db -> prepare(self::CREATE_SUMMARY);
            $is_successful = $statement -> execute($values);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Save Last Customer Aged Statement.');
        }
    }
}
?>