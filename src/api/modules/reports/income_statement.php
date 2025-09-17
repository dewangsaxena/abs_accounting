<?php 
/**
 * This module will define template for Storing Income Statement.
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/accounts.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/chart.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

class IncomeStatementActions {

    /**
     * This method will fetch the data points for reconstructing graph based on income statement.
     * @param begin_time The beginning time
     * @param end_time The date end range time.
     * @param location The location of the income statement
     * @return array 
     */
    public static function fetch_graph_data_points(string $begin_time, string $end_time, array $locations): array {
        // Locations Selected
        $locations_selected = '';
        foreach($locations as $location) $locations_selected .= $location. ',';
        $locations_selected = trim($locations_selected, ',');

        // Convert Date
        $begin_time = Utils::get_YYYY_mm_dd(Utils::convert_utc_str_timestamp_to_localtime($begin_time, StoreDetails::EDMONTON));
        $end_time = Utils::get_YYYY_mm_dd(Utils::convert_utc_str_timestamp_to_localtime($end_time, StoreDetails::EDMONTON));

        // Fetch Income Statement
        $data = self::find_by_location($begin_time, $end_time, $locations_selected);
        if($data['status'] === false) return [];
        else $data = $data['data'];

        // Grouped Statements
        $grouped_statements = self::group_statements_by_store($data);

        // Summed Statements
        $summed_statements = self::sum_all_statements($grouped_statements);

        // Total Summary of all Stores.
        $summary_of_all_stores = [
            'sales_inventory' => 0,
            'sales_return' => 0,
            'early_payment_sales_discounts' => 0,
            'total_revenue' => 0,
            'cogs' => 0,
            'net_income' => 0,
            'profit_margin' => 0,
            'cogs_margin' => 0,

            /* Wash Specific Accounts */
            'part_sales' => 0,
            'merchandise_sales' => 0,
            'labour_revenue' => 0,
            'sales' => 0,
            'full_service' => 0,
            'self_wash' => 0,
            'oil_grease' => 0,
            'misc_revenue' => 0,
        ];
 
        $chart_data = Charts::income_statement($data);
        if($chart_data['status'] === false) return [];

        foreach($summed_statements as $statement) {
            $summary_of_all_stores['sales_inventory'] += $statement[4020];
            $summary_of_all_stores['sales_returns'] += $statement[4220];
            $summary_of_all_stores['early_payment_sales_discounts'] += $statement[4240];
            $summary_of_all_stores['cogs'] += $statement[1520];

            if(SYSTEM_INIT_MODE === WASH) {
                $summary_of_all_stores['part_sales'] += $statement[4150];
                $summary_of_all_stores['merchandise_sales'] += $statement[4170];
                $summary_of_all_stores['labour_revenue'] += $statement[4175];
                $summary_of_all_stores['sales'] += $statement[4200];
                $summary_of_all_stores['full_service'] += $statement[4205];
                $summary_of_all_stores['self_wash'] += $statement[4210];
                $summary_of_all_stores['oil_grease'] += $statement[4215];
                $summary_of_all_stores['misc_revenue'] += $statement[4460];
            }
        }

        // // Calculate Total Revenue
        $summary_of_all_stores['total_revenue'] = 
        $summary_of_all_stores['sales_inventory'] - 
        $summary_of_all_stores['sales_returns'] -
        $summary_of_all_stores['early_payment_sales_discounts'];

        // Add Wash Specific Revenue
        if(SYSTEM_INIT_MODE === WASH) {
            $keys = [
                'part_sales',
                'merchandise_sales',
                'labour_revenue',
                'sales',
                'full_service',
                'self_wash',
                'oil_grease',
                'misc_revenue',
            ];

            foreach($keys as $key) {
                $summary_of_all_stores['total_revenue'] += $summary_of_all_stores[$key];
            }
        }
        
        // Net Income
        $summary_of_all_stores['net_income'] = $summary_of_all_stores['total_revenue'] - abs($summary_of_all_stores['cogs']);
        
        // Profit Margin
        if (SYSTEM_INIT_MODE === PARTS) {
            $summary_of_all_stores['profit_margin'] = Utils::calculateProfitMargin($summary_of_all_stores['total_revenue'], $summary_of_all_stores['cogs']);
            $summary_of_all_stores['cogs_margin'] = Utils::calculateCOGSMargin($summary_of_all_stores['total_revenue'], $summary_of_all_stores['cogs']);
        }
        
        // Negate 
        $summary_of_all_stores['sales_returns'] = -$summary_of_all_stores['sales_returns'];
        $summary_of_all_stores['early_payment_sales_discounts'] = -$summary_of_all_stores['early_payment_sales_discounts'];
        
        // Round off
        $keys = array_keys($summary_of_all_stores);
        foreach($keys as $key) $summary_of_all_stores[$key] = Utils::round($summary_of_all_stores[$key], 2);
        $data = [
            'chartData' => $chart_data['data'],
            'statement' => $summed_statements,
            'summaryOfAllStores' => $summary_of_all_stores,
        ];
        return ['status' => true, 'data' => $data];
    }

    /**
     * This method will group statements by store.
     * @param statements
     * @return array
     */
    private static function group_statements_by_store(array $statements): array {
        $statements_by_store = [];
        foreach($statements as $statement) {
            $store_id = $statement['store_id'];
            if(!isset($statements_by_store[$store_id])) $statements_by_store[$store_id] = [];
            $statements_by_store[$store_id][]= $statement;
        }
        return $statements_by_store;
    }

    /**
     * This method will sum all statements.
     * @param statements_by_stores
     * @return array
     */
    private static function sum_all_statements(array $statements_by_stores): array {
        $summed_statement_by_store = [];
        foreach($statements_by_stores as $statement_objects) {
            foreach($statement_objects as $details) {
                $store_id = $details['store_id'];

                if(!isset($summed_statement_by_store[$store_id])) $summed_statement_by_store[$store_id] = [];
                $statement = json_decode($details['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $account_keys = array_keys($statement);
                foreach($account_keys as $account) {
                    
                    if(!isset($summed_statement_by_store[$store_id][$account])) $summed_statement_by_store[$store_id][$account] = 0;
                    $summed_statement_by_store[$store_id][$account] += $statement[$account];
                }
            }
        }
        return $summed_statement_by_store;
    }

    /**
     * This method will fetch income statement by location.
     * @param begin_time The beginning time
     * @param end_time The date end range time.
     * @param location The locations
     * @return array
     */
    public static function find_by_location(string $begin_time, string $end_time, string $location): array {
        try {
            $db = get_db_instance();

            // Format For Store 
            $location = rtrim($location, ',');
            $result = Utils::mysql_in_placeholder($location, <<<'EOS'
            SELECT 
                id,
                `date`,
                store_id,
                statement
            FROM 
                income_statement
            WHERE 
                `date` >= ?
            AND
                `date` <= ?
            AND 
                store_id IN (:placeholder)
            ORDER BY `date` ASC;
            EOS);
            
            // Set Values
            $values = [$begin_time, $end_time];

            // Add Store Locations
            foreach($result['values'] as $val) array_push($values, $val);

            // Prepare
            $statement = $db -> prepare($result['query']);
            
            // Execute
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Flag
            $is_present_for = [];

            foreach($records as $record) {
                if(!in_array($record['store_id'], $is_present_for)) {
                    $is_present_for []= $record['store_id'];
                }
            }

            // Add Default Values for
            $add_default_values_for = [];

            // Locations
            $locations = explode(',', $location);
            foreach($locations as $location) {
                if(!in_array($location, $is_present_for)) $add_default_values_for[]= $location;
            }

            if(count($add_default_values_for) > 0) {
                foreach($add_default_values_for as $location) {
                    $records []= [
                        'id' => 0,
                        'date' => Utils::get_business_date($location),
                        'store_id' => $location,
                        'statement' => json_encode(AccountsConfig::ACCOUNTS, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                    ];
                }
            }
            assert_success();
            return ['status' => true, 'data' => $records];
        }
        catch (Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will fetch the last two income statements.
     * @param location The location for which to fetch the income statement.
     * @return array
     */
    public static function fetch_last_two_income_statements(int $location) : array {
        $db = get_db_instance();
        $statement = $db -> prepare(<<<'EOS'
        SELECT 
        * 
        FROM 
            income_statements 
        WHERE 
            store_id = :store_id
        LIMIT 
            2;
        EOS);
        $statement -> execute([':store_id' => $location]);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        return ['status' => true, 'data' => $records];
    }
    
    // Queries
    // These are shared
    private const CREATE_INCOME_STATEMENT = <<<'EOS'
    INSERT INTO income_statement
    (
        `statement`,
        `date`,
        store_id
    )
    VALUES
    (
        :statement,
        :date,
        :store_id
    );
    EOS;

    /**
     * Update Income Statement
     */
    private const UPDATE_INCOME_STATEMENT = <<<'EOS'
    UPDATE 
        income_statement 
    SET 
        `statement` = :statement,
        modified = CURRENT_TIMESTAMP
    WHERE 
        `date` = :date
    AND
        store_id = :store_id;
    EOS;

    /**
     * Fetch Income Statement by Unique Id.
     */
    private const FETCH_INCOME_STATEMENT_BY_DATE = <<<'EOS'
    SELECT 
    * 
    FROM 
        income_statement
    WHERE 
        `date` = :date
    AND 
        store_id = :store_id;
    EOS;

    /**
     * This method will add(if not present) and update the income statement.
     * @param account_detail The account details to alter.
     * @param date The account statement date. The date should be in the following format: YYYY-MM-DD, and should be as per the Business Timezone.
     * @param store_id The Store location.
     * @param db PDO Db connection
     * @throws Exception
     * @return void 
     */
    public static function update(array $account_details, int $store_id, ?string $date=null, ?PDO &$db=null): void {
        // NOTE: Ensure the server is using UTC timezone.
        if($date === null) $date = Utils::get_business_date($store_id);

        // Convert to specific format
        $date = Utils::get_YYYY_mm_dd($date);

        // Fetch the income statement for that date
        $statement = $db -> prepare(self::FETCH_INCOME_STATEMENT_BY_DATE);
        $statement -> execute([':date' => $date, ':store_id' => $store_id]);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $record_count = count($records);
        
        // Flag
        $create_new_record = $record_count > 0 ? false : true;

        // Record PKey
        $pkey = null;

        // Base record
        // Check for unassigned base record
        // Assign it a default value.
        $base_income_statement = null;
        if($create_new_record) $base_income_statement = json_encode(AccountsConfig::ACCOUNTS, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
    
        // This will store the base record to begin recording with.
        else {
            $last_record = $records[0];

            // Set Last Record Details to begin with
            $base_income_statement = $last_record['statement'];
            $pkey = $last_record['id'];
        }

        // Insert a new record
        if($create_new_record) {
            $statement = $db -> prepare(self::CREATE_INCOME_STATEMENT);
            $statement -> execute([
                ':statement' => $base_income_statement,
                ':date' => $date,
                ':store_id' => $store_id,
            ]);
        
            // Pkey 
            $pkey = $db -> lastInsertId();
            if($pkey === false) {
                throw new Exception('Cannot create Income Statement Record.');
            }

            // Convert it back to an array
            $base_income_statement = json_decode($base_income_statement, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        }
        else $base_income_statement = json_decode($base_income_statement, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Set Account Details
        $record = [
            'id' => $pkey,
            'statement' => $base_income_statement,
        ];

        // Income Statement
        $income_statement = $record['statement'];

        // Account Keys
        $account_keys = array_keys($account_details);

        // Apply Changes
        foreach($account_keys as $key) {
            
            // Add key if not exists
            if(!array_key_exists($key, $income_statement)) $income_statement[$key] = 0.0;

            // Adjust key
            $income_statement[$key] += $account_details[$key];
        }
        
        // Prepare Statement.
        $statement = $db -> prepare(self::UPDATE_INCOME_STATEMENT);
        $is_successful = $statement -> execute([
            ':statement' => json_encode($income_statement, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ':date' => $date,
            ':store_id' => $store_id,
        ]);
        
        if($is_successful !== true && $statement -> rowCount() < 1) {
            throw new Exception('Cannot Update Income Statement.');
        }
    }

    /**
     * This method will update the affected accounts.
     * @param accounts All accounts 
     * @param account The account to affect. 
     * @param value The value to adjust with.
     * @return void
     */
    public static function update_account_values(array &$accounts, int $account, float $value) : void {
        if(!isset($accounts[$account])) $accounts[$account] = 0.0;
        $accounts[$account] += Utils::round($value);
    }

    /**
     * This method will generate Income Statement.
     * @param data
     * @return void
     */
    public static function generate(array $data): void {
        if(isset($data['selectedStores'])) {
            $selected_stores = explode(',', $data['selectedStores']);
            if(is_numeric($selected_stores[0] ?? null) === false) die('Invalid Store.');
            if(count($selected_stores) === 0) die('No Store Selected.');
            else $selected_store = $selected_stores[0];
        }
        $dates = json_decode($data['dates'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $response = self::fetch_graph_data_points(
            $dates['startDate'],
            $dates['endDate'],
            [$selected_store]
        );

        if($response['status'] === false) die($response['message']);
        $statement = $response['data']['statement'][$selected_store];

        $details = [
            'statement' => $statement,
            'store_name' => StoreDetails::STORE_DETAILS[$selected_store]['name'],
            'start_date' => Utils::get_YYYY_mm_dd($dates['startDate']),
            'end_date' => Utils::get_YYYY_mm_dd($dates['endDate']),
        ];

        // Generate Income Statement
        GeneratePDF::income_statement($details);
    }
}
?>