<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/email.php";

/**
 * This module will implement class to handle Customer Statement.
 */
class CustomerStatement {

    // Till Date
    public static string|DateTime $till_date = '';

    // Fetch Query
    private const FETCH_QUERY = <<<'EOS'
    SELECT 
        *,
        c.`name` AS client_name, 
        c.contact_name
    FROM (
            SELECT
                1 AS txn_type,
                txn.id AS txn_id,
                txn.id AS sales_invoice_id,
                txn.`date` AS txn_date,
                txn.credit_amount AS amount,
                txn.sum_total AS total_amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                sales_invoice AS txn
            WHERE 
                txn.client_id = :client_id
            AND
                txn.store_id = :store_id
            __FROM_DATE__
            AND 
                txn.`date` <= :till_date
            __AMOUNT_OWING__
            UNION 
            SELECT 
                2 AS txn_type,
                txn.id AS txn_id,
                txn.sales_invoice_id AS sales_invoice_id,
                txn.`date` AS txn_date,
                -txn.credit_amount AS amount,
                -txn.sum_total AS total_amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM    
                sales_return AS txn 
            WHERE
                txn.client_id = :client_id
            AND
                txn.store_id = :store_id
            __FROM_DATE__
            AND
                txn.`date` <= :till_date
            __AMOUNT_OWING__
            UNION
            SELECT 
                3 AS txn_type,
                txn.id AS txn_id,
                NULL AS sales_invoice_id,
                txn.`date` AS txn_date,
                -txn.credit_amount AS amount,
                -txn.sum_total AS total_amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                credit_note AS txn
            WHERE 
                txn.client_id = :client_id
            AND
                txn.store_id = :store_id
            __FROM_DATE__
            AND
                txn.`date` <= :till_date
            __AMOUNT_OWING__
            UNION
            SELECT 
                4 AS txn_type,
                txn.id AS txn_id,
                NULL AS sales_invoice_id,
                txn.`date` AS txn_date,
                txn.credit_amount AS amount,
                txn.sum_total AS total_amount,
                txn.client_id AS client_id,
                txn.created AS created_date
            FROM 
                debit_note AS txn
            WHERE 
                txn.client_id = :client_id
            AND
                txn.store_id = :store_id
            __FROM_DATE__
            AND
                txn.`date` <= :till_date  
            __AMOUNT_OWING__
        ) AS _tmp
    LEFT JOIN 
        clients AS c
    ON 
        client_id = c.id
    ORDER BY
        txn_date ASC,
        txn_type ASC,
        txn_id ASC,
        sales_invoice_id ASC,
        amount ASC;
    EOS;

    /**
     * This method will fetch customer statement.
     * @param client_id
     * @param store_id
     * @param from_date
     * @param till_date
     * @param generate_record_of_all_txn
     * @return array
     */
    private static function fetch_customer_statement(int $client_id, int $store_id, ?string $from_date, string $till_date, bool $generate_record_of_all_txn=false): array {
        try {
            $db = get_db_instance();
            $amount_owing_sql_statement = '';
            $from_date_sql_statement = '';

            $params = [
                ':client_id' => $client_id,
                ':store_id' => $store_id,
                ':till_date' => $till_date,
            ];

            // Generate Record
            if($generate_record_of_all_txn === false) $amount_owing_sql_statement = ' AND ABS(txn.credit_amount) > 0.0000 ';

            // Query
            $query = str_replace('__AMOUNT_OWING__', $amount_owing_sql_statement, self::FETCH_QUERY);

            if(($generate_record_of_all_txn === true && is_null($from_date) === false) || isset($from_date)) {
                $from_date_sql_statement = ' AND txn.`date` >= :from_date ';
                $params[':from_date'] = $from_date;
            }

            // Query
            $query = str_replace('__FROM_DATE__', $from_date_sql_statement, $query);

            $statement = $db -> prepare($query);
            $statement -> execute($params);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Check for Valid Records.
            if(isset($records[0]) === false) throw new Exception('No Records found for Client.');
            
            $formatted_record = [];
            $total_amount = 0;

            // Aged Summary
            $aged_summary = [
                'current' => 0,
                '31-60' => 0,
                '61-90' => 0,
                '91+' => 0,
                'total' => 0,
            ];

            // Convert to Date Object
            $till_date_obj = date_create($till_date);

            foreach($records as $record) {
                $txn_amount = ($generate_record_of_all_txn === false ? $record['amount'] : $record['total_amount']);
                $__aged_summary = Shared::get_txn_age($record['txn_date'], $till_date_obj, $txn_amount, $store_id);

                // Add Aged Summary
                $aged_summary['current'] += $__aged_summary['current'];
                $aged_summary['31-60'] += $__aged_summary['31-60'];
                $aged_summary['61-90'] += $__aged_summary['61-90'];
                $aged_summary['91+'] += $__aged_summary['91+'];
                $aged_summary['total'] += $__aged_summary['total'];

                $total_amount += $txn_amount;
                $temp = [
                    'transaction_date' => Utils::convert_date_to_human_readable($record['txn_date']),
                    'transaction_id' => TRANSACTION_NAMES_ABBR[$record['txn_type']]. '-'. $record['txn_id'],
                    'transaction_type' => TRANSACTION_NAMES[$record['txn_type']],
                    'balance' => $txn_amount,
                    'sales_invoice_id' => is_numeric($record['sales_invoice_id']) ? TRANSACTION_NAMES_ABBR[SALES_INVOICE]. '-'. $record['sales_invoice_id']: '-',
                    'amount_total' => $total_amount,
                    'txn_id' => $record['txn_id'],
                    'txn_type' => $record['txn_type'],
                ];  
                $formatted_record[]= $temp;
            }

            $response = [
                'client_details' => [
                    'name' => $records[0]['client_name'],
                    'contact' => $records[0]['contact_name'],
                ],
                'transaction_records' => $formatted_record,
                'aged_summary' => $aged_summary,
            ];
            return ['status' => true, 'data' => $response];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will generate customer statement.
     * @param client_id
     * @param store_id
     * @param from_date
     * @param till_date
     * @param attach_transactions
     * @param generate_record_of_all_txn
     * @param dump_file
     */
    public static function generate(
            int $client_id, 
            int $store_id, 
            ?string $from_date, 
            string $till_date, 
            bool $attach_transactions=false, 
            bool $generate_record_of_all_txn=false,
            bool $dump_file=false
        ): string | null {
        
        $txn_filenames = [];
        $customer_statement_filename = null;
        try {
            // Fetch Customer Statement
            $response = self::fetch_customer_statement(
                $client_id, 
                $store_id,
                $from_date,
                $till_date, 
                $generate_record_of_all_txn
            );
            if($response['status'] === false) throw new Exception('Unable to Generate Customer Statement.');
            else $response = $response['data'];

            $transaction_records = $response['transaction_records'];
            $client_details = $response['client_details'];

            // Store Details
            $store_details = Utils::build_store_address($store_id);

            // Aged Summary Report 
            if($generate_record_of_all_txn === false) {
                $customer_aged_summary = CustomerAgedSummary::fetch_customer_aged_summary_of_client($client_id, $store_id, $till_date);
                $aged_summary = [];
                $aged_summary['current'] = $customer_aged_summary['current'];
                $aged_summary['31-60'] = $customer_aged_summary['31-60'];
                $aged_summary['61-90'] = $customer_aged_summary['61-90'];
                $aged_summary['91+'] = $customer_aged_summary['91+'];
                $aged_summary['total'] = $customer_aged_summary['total'];
            }
            else $aged_summary = $response['aged_summary'];
            
            $details = [
                'company_name' => $store_details['company_name'],
                'company_address_line_1' => $store_details['company_address_line_1'],
                'company_address_line_2' => $store_details['company_address_line_2'],
                'company_address_line_3' => $store_details['company_address_line_3'],
                'company_tel' => $store_details['company_tel'],
                'company_fax' => $store_details['company_fax'],
                'date' => strtoupper(Utils::convert_date_to_human_readable($till_date)),
                'store_id' => $store_id,
                'client_name' => $client_details['name'],
                'contact_name' => $client_details['contact'],
                'transaction_records' => $transaction_records,
                'current' => $aged_summary['current'],
                '31-60' => $aged_summary['31-60'],
                '60+' => $aged_summary['61-90'] + $aged_summary['91+'],
                'total_amount' => $aged_summary['total'],
            ];
            $customer_statement_filename = 'customer_statement_'. Utils::generate_token(8). '_'.strtolower(Utils::convert_date_to_human_readable($till_date, '_'));
            GeneratePDF::customer_statement(
                $details, 
                $customer_statement_filename, 
                generate_record: $generate_record_of_all_txn, 
                generate_file: $attach_transactions || $dump_file
            );

            $txn_records = [];
            foreach($transaction_records as $txn) {
                $txn_records[]= ['type' => $txn['txn_type'], 'id' => $txn['txn_id']];
            }

            $txn_filenames = Shared::generate_pdf($txn_records, true);
            if($txn_filenames['status'] === false) throw new Exception('Unable to Generate Transaction Files.');
            else $txn_filenames = $txn_filenames['data'];

            $txn_filenames = [$customer_statement_filename.'.pdf', ...$txn_filenames];

            // Merge PDF
            $merge_object = Utils::merge_pdfs($txn_filenames);

            // Delete Files
            Utils::delete_files($txn_filenames);

            // Output Single File
            // Generate a new Filename
            if($dump_file) $customer_statement_filename = 'customer_statement_'. Utils::generate_token(8). '_'.strtolower(Utils::convert_date_to_human_readable($till_date, '_')). '.pdf';
            $merge_object -> output($dump_file ? TEMP_DIR. $customer_statement_filename : null);

            return $customer_statement_filename;
        }
        catch(Exception $e) {
            return null;
        }
        finally {
            // Delete Files
            Utils::delete_files($txn_filenames);
            return $customer_statement_filename;
        }
    }

    /**
     * This method will generate customer statement and send it in email.
     * @param __client_id
     * @param store_id
     * @param from_date
     * @param till_date
     * @param attach_transactions
     * @param generate_record_of_all_txn
     * @param selected_client
     */
    public static function email(
        ?int $__client_id, 
        int $store_id, 
        ?string $from_date, 
        string $till_date, 
        bool $attach_transactions=false, 
        bool $generate_record_of_all_txn=false,
        array $selected_client = [],
    ) : array {
        $is_email_sent = false;
        $error_message = 'Unable to Generate Statement.';
        try {
            if(is_numeric($__client_id)) $selected_client []= $__client_id;

            // All Filenames
            $all_filenames = [];

            // Unsuccessful Clients
            $unsuccessful_clients = [];

            foreach($selected_client as $client_id) {
                // Fetch Client Details
                $client_details = Client::fetch(['id' => $client_id]);
                if($client_details['status'] === false) throw new Exception($client_details['message']);
                else $client_details = $client_details['data'][0];

                // Path To file.
                $filename = self::generate(
                    $client_id,
                    $store_id,
                    $from_date,
                    $till_date,
                    $attach_transactions,
                    $generate_record_of_all_txn,
                    true,
                );
                
                // Check for Valid Statement
                if(is_null($filename)) {
                    $unsuccessful_clients[]= $client_details['primaryDetails']['name'];
                    continue;
                }
                $path_to_file = TEMP_DIR. $filename;

                // Append
                $all_filenames[]= $filename;
                
                // Send email
                $is_email_sent = Email::send(
                    subject: 'Statement from '. StoreDetails::STORE_DETAILS[$store_id]['email']['from_name'][SYSTEM_INIT_MODE],
                    recipient_email: $client_details['primaryDetails']['emailId'],
                    recipient_name: $client_details['primaryDetails']['name'],
                    content: 'Please notify us immediately if you are unable to see the attachment(s). Please find herewith attached statement.',
                    path_to_attachment: $path_to_file,
                    file_name: $filename,
                    store_id: $store_id,
                    additional_email_addresses: $client_details['additionalEmailAddresses'],
                );

                if($is_email_sent['status'] === false) throw new Exception($is_email_sent['message']);
                else $is_email_sent = $is_email_sent['status'];

                if($is_email_sent !== true) {
                    $unsuccessful_clients[]= $client_details['primaryDetails']['name'];
                }
            }

            if(count($unsuccessful_clients) > 0) throw new Exception('Cannot Send Email to the following clients: '. implode(',', $unsuccessful_clients));
        }
        catch(Throwable $th) {
            $is_email_sent = false;
            $error_message = $th -> getMessage();
        }
        finally {
            // Delete file(s)
            Utils::delete_files($all_filenames);
            return ['status' => $is_email_sent, 'message' => $error_message];
        }
    }
}