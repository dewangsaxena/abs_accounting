<?php
/**
 * This class defines modules to view Balance Sheet.
 * @author Dewang Saxena, <dewang2610@gmail.com>
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/accounts.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";


class BalanceSheetActions {

    // Queries
    // These are shared
    private const CREATE_BALANCE_SHEET = <<<'EOS'
    INSERT INTO balance_sheet
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

    // Fetch
    private const FETCH_BALANCE_SHEET_AS_PER_STORE_AND_DATE = <<<'EOS'
    SELECT 
        * 
    FROM 
        balance_sheet 
    WHERE 
        store_id = :store_id
    AND
        `date` LIKE :date 
    ORDER BY 
        `date` DESC
    LIMIT 1;
    EOS;

    // Update
    private const UPDATE_BALANCE_SHEET = <<<'EOS'
    UPDATE 
        balance_sheet 
    SET 
        `statement` = :statement,
        modified = CURRENT_TIMESTAMP
    WHERE 
        `date` = :date
    AND
        store_id = :store_id
    AND 
        modified = :modified;
    EOS;

    /**
     * This method will fetch balance sheet for store.
     * @param store_id
     * @param month
     * @param year
     * @param fetch_latest
     * @param send_blank
     * @param search_prior
     * @return array
     */
    public static function fetch_for_store(int $store_id, int $month, int $year, bool $fetch_latest=false, bool $send_blank=false, bool $search_prior=false): array {

        // Send Blank
        if($send_blank) return ['statement' => AccountsConfig::ACCOUNTS];

        // Month
        $month = intval($month);
        if($month < 10) $month = "0$month";

        // Params
        $params = [':store_id' => $store_id];

        if($fetch_latest) {
            $query = <<<'EOS'
            SELECT 
                `date`,
                `statement`
            FROM 
                balance_sheet 
            WHERE 
                store_id = :store_id 
            ORDER BY 
                `date` DESC
            LIMIT 1;
            EOS;
        }
        else if($search_prior) {
            $query = <<<'EOS'
            SELECT 
                `date`,
                `statement`
            FROM 
                balance_sheet 
            WHERE 
                store_id = :store_id 
            AND 
                `date` <= :date
            ORDER BY 
                `date` DESC
            LIMIT 1;
            EOS;
            $params[':date'] = "$year-$month-01";
        }
        else {
            $query = <<<'EOS'
            SELECT 
                `date`,
                `statement`
            FROM 
                balance_sheet 
            WHERE 
                store_id = :store_id 
            AND
                `date` LIKE :date 
            ORDER BY 
                `date` DESC
            LIMIT 1;
            EOS;
            $params[':date'] = "$year-$month-__";
        }

        $db = get_db_instance();
        $statement = $db -> prepare($query);
        $statement -> execute($params);
        $balance_sheet = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Response
        $response = [];
        if(isset($balance_sheet[0]['statement'])) {
            $date = explode('-', $balance_sheet[0]['date']);
            $response['month'] = intval($date[1]);
            $response['year'] = intval($date[0]);
            $response['statement'] = json_decode($balance_sheet[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        }
        else $response['statement'] = [];
        return $response;
    }

    /**
     * Calculate Total Current Assets
     * @param record
     */
    private static function calculate_total_current_assets(array &$record): void {
        // Total Cash 
        $record[1075] = 0;
        $total_cash_accounts = [1020, 1030, 1050, 1055, 1060, 1067, 10001,];
        foreach($total_cash_accounts as $account) $record[1075] += $record[$account];

        // Credit Cards
        $record[1090] = 0;
        $credit_cards_accounts = [1080, 1083, 1087, 1089];
        foreach($credit_cards_accounts as $account) {
            $record[1090] += $record[$account];
        }

        // Accounts Receivables
        $record[1230] = 0;
        $accounts_receivables_accounts = [1200, 1205,];
        foreach($accounts_receivables_accounts as $account) {
            $record[1230] += $record[$account];
        }

        // Total Current Assets
        $standalone_total_current_assets = [1100, 1300, 1320];
        $total_current_assets_accounts = array_merge(
            $standalone_total_current_assets,
            [1075, 1090, 1230]
        );
        $record[1400] = 0;
        foreach($total_current_assets_accounts as $account) {
            $record[1400] += $record[$account];
        }
    }

    /**
     * Calculate Total Inventory Assets.
     * @param record
     */
    private static function calculate_total_inventory_assets(array &$record): void {
        $inventory_accounts = [1520, 1530, 1540];
        $record[1590] = 0;
        foreach($inventory_accounts as $account) {
            $record[1590] += $record[$account];
        }
    }

    public static function calculate_total_capital_assets(array &$record): void {
        $record[1830] = 0;
        $acc_furniture_accounts = [1820, 1825];
        foreach($acc_furniture_accounts as $account) {
            $record[1830] += $record[$account];
        }

        $record[1870] = 0;
        $building_accounts = [1860, 1865];
        foreach($building_accounts as $account) {
            $record[1870] += $record[$account];
        }

        $individual_accounts = [1810, 1840, 1880];
        $all_accounts = array_merge($individual_accounts, [1830, 1870, 1880]);
        $record[1890] = 0;
        foreach($all_accounts as $account) {
            $record[1890] += $record[$account]; 
        }
    }

    private static function total_other_non_current_assets(array &$record): void {
        $accounts = [1910, 1920, 1930];
        $record[1950] = 0;
        foreach($accounts as $account) {
            $record[1950] += $record[$account];
        }
    }

    private static function total_assets(array &$record): void {

        self::calculate_total_current_assets($record);
        self::calculate_total_inventory_assets($record);
        self::calculate_total_capital_assets($record);
        self::total_other_non_current_assets($record);

        $accounts = [1400, 1590, 1890, 1950];
        $record['total_asset'] = 0;
        foreach($accounts as $account) {
            $record['total_asset'] += $record[$account];
        }
    }

    private static function current_liabilities(array &$record): void {
        $credit_card_payables_account = [2133, 2134, 2135, 2140];
        $record[2145] = 0;
        foreach($credit_card_payables_account as $account) {
            $record[2145] += $record[$account];
        }

        $total_receiver_general_accounts = [2160, 2170, 2180, 2185, 2190,];
        $record[2195] = 0;
        foreach($total_receiver_general_accounts as $account) {
            $record[2195] += $record[$account];
        }
        
        $group_accounts = [2145, 2195];
        $standalone_accounts = [
            2100, 2115, 2120, 2130, 2230,
            2234, 2235, 2236, 2237, 2238, 
            2240, 2250, 2260, 2270, 2280,
            2310, 2312, 2315, 2320, 2325,
            2330, 10002, 2460, 2335, 2340
        ];

        $total_accounts = array_merge($group_accounts, $standalone_accounts);
        $record[2500] = 0;
        foreach($total_accounts as $account) {
            $record[2500] += ($record[$account] ?? 0);
        }
    }

    private static function long_term_liabilities(array &$record): void {
        $accounts = [2620, 2630, 2640];
        $record[2700] = 0;
        foreach($accounts as $account) {
            $record[2700] += $record[$account];
        }
    }

    private static function total_liabilities(array &$record): void {
        self::current_liabilities($record);
        self::long_term_liabilities($record);

        $accounts = [2500, 2700];
        $record['total_liability'] = 0;
        foreach($accounts as $account) {
            $record['total_liability'] += $record[$account];
        }
    }

    private static function total_retained_earnings(array &$record): void {
        $accounts = [3560, 3600];
        $record['total_retained_earnings'] = 0;
        foreach($accounts as $account) {
            $record['total_retained_earnings'] += $record[$account];
        }
    }

    private static function total_equity(array &$record): void {
        self::total_retained_earnings($record);
        $record['total_equity'] = $record['total_retained_earnings'];
    }

    private static function liabilities_and_equity(array &$record): void {
        $record['liabilities_and_equity'] = ($record['total_asset'] - $record['total_liability']) + $record['total_equity'];
    }

    /**
     * This method will generate balance sheet.
     * @param store_id
     * @param month
     * @param year
     */
    public static function generate(int $store_id, int $month, int $year): void {

        // Current Month and Year
        $current_month = intval(date('m'));
        $current_year = intval(date('Y'));

        // Check for Valid Date
        if($year > $current_year || ($month > $current_month && $year >= $current_year)) die('Invalid Date.');
        
        // Fetch Record for the exact year and month
        $details = self::fetch_for_store($store_id, $month, $year);
        if(count($details['statement']) > 0) $record = $details['statement'];
        else {
            // Check for Any Record Prior to the requested date.
            if($month <= $current_month && $year <= $current_year) {
                $new_details = self::fetch_for_store($store_id, $month, $year, search_prior: true);
                if(count($new_details)) $record = self::fetch_for_store($store_id, $month, $year, false, send_blank: true)['statement'];
            }
            else if($details['month'] <= $current_month && $details['year'] === $current_year) {
                $record = self::fetch_for_store($store_id, $month, $year, fetch_latest: true)['statement'];
            }
            else $record = self::fetch_for_store($store_id, $month, $year, false, send_blank: true)['statement'];
        }

        // Send Blank
        if(count($record) === 0) $record = AccountsConfig::ACCOUNTS;

        self::total_assets($record);
        self::total_liabilities($record);
        self::total_equity($record);
        self::liabilities_and_equity($record);

        $date = date_format(date_create("$year-$month-01"), 'F, Y');
        $details = [
            'date_txt' => $date,
            'accounts' => $record,
            'store_name' => StoreDetails::STORE_DETAILS[$store_id]['name'],
        ];
        GeneratePDF::balance_sheet($details);
    }

    /**
     * This method will fetch balance sheet by location.
     * @param begin_time The beginning time
     * @param end_time The date end range time.
     * @param location The locations
     * @return array
     */
    public static function find_by_location(string $begin_time, string $end_time, string $location): array {
        try {
            // Format For Store 
            $location = rtrim($location, ',');
            $result = Utils::mysql_in_placeholder($location, <<<'EOS'
            SELECT 
                id,
                store_id,
                `statement`
            FROM 
                balance_sheet 
            WHERE 
                `date` >= ?
            AND
                `date` <= ?
            AND 
                store_id IN (:placeholder);
            EOS);
            
            // Set Values
            $values = [$begin_time, $end_time];

            // Add Store Locations
            $store_locations = $result['value'];
            foreach($store_locations as $val) array_push($values, $val);

            // Prepare
            $db = get_db_instance();
            $statement = $db -> prepare($result['query']);
            
            // Execute
            $statement -> execute($values);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            assert_success();
            return ['status' => true, 'data' => $records];
        }
        catch (Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will update the affected accounts.
     * @param accounts All accounts 
     * @param account The account to affect. 
     * @param value The value to adjust with.
     */
    public static function update_account_value(array &$accounts, int $account, float $value): void {
        if(!isset($accounts[$account])) $accounts[$account] = 0.0;
        $accounts[$account] += Utils::round($value);
    }

    /**
     * This method will fetch the accounts information to prepare balance sheet.
     * @param store_id The store ID 
     * @param month The month is in XX format. 
     * @param year The year is in XXXX format.
     * @return array 
     */
    public static function fetch_accounts_information(int $store_id, string $month, int $year): array {
        try {
            // Get DB instance
            $db = get_db_instance();

            // Fetch All Accounts Equity 
            $statement = $db -> prepare(self::FETCH_BALANCE_SHEET_AS_PER_STORE_AND_DATE);
            $statement -> execute([
                ':store_id' => $store_id,
                ':date' => "$year-$month-%"
            ]);
            $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Account keys 
            $account_keys = array_keys(AccountsConfig::ACCOUNT_NAMES);
            
            // No. of accounts
            $no_of_accounts = count($account_keys);

            // Accounts details.
            $account_details = [];

            // Build Template
            for($i = 0; $i < $no_of_accounts; ++$i) {
                $account_details[$account_keys[$i]] = [
                    'equity' => 0,
                    'name' => AccountsConfig::ACCOUNT_NAMES[$account_keys[$i]],
                    'value' => '',
                ];
            }

            foreach($results as $result) {
                $accounts = json_decode($result['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                for($i = 0; $i < $no_of_accounts; ++$i) {
                    // Skip Wash Specific Accounts.
                    if(!isset($accounts[$account_keys[$i]])) continue;
                    $account_details[$account_keys[$i]]['equity'] += $accounts[$account_keys[$i]];
                }
            }

            // Generate Value 
            for($i = 0; $i < $no_of_accounts; ++$i) {
                $account_details[$account_keys[$i]]['value'] = number_format(
                    $account_details[$account_keys[$i]]['equity'],
                    2
                );
            }

            assert_success();
            return ['status' => true, 'data' => $account_details];
        }
        catch(Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will fetch accounts information till exact date. This is useful for Debugging.
     * @param store_id
     * @param date
     */
    public static function fetch_accounts_information_till_exact_date(int $store_id, string $date): array {
        try {
            // Get DB instance
            $db = get_db_instance();

            // Fetch All Accounts Equity 
            $statement = $db -> prepare(self::FETCH_BALANCE_SHEET_AS_PER_STORE_AND_DATE);
            $statement -> execute([
                ':store_id' => $store_id,
                ':date' => $date
            ]);
            $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Account keys 
            $account_keys = array_keys(AccountsConfig::ACCOUNT_NAMES);
            
            // No. of accounts
            $no_of_accounts = count($account_keys);

            // Accounts details.
            $account_details = [];

            // Build Template
            for($i = 0; $i < $no_of_accounts; ++$i) {
                $account_details[$account_keys[$i]] = [
                    'equity' => 0,
                    'name' => AccountsConfig::ACCOUNT_NAMES[$account_keys[$i]],
                    'value' => '',
                ];
            }

            foreach($results as $result) {
                $accounts = json_decode($result['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                for($i = 0; $i < $no_of_accounts; ++$i) {
                    // Skip Wash Specific Accounts.
                    if(!isset($accounts[$account_keys[$i]])) continue;
                    $account_details[$account_keys[$i]]['equity'] += $accounts[$account_keys[$i]];
                }
            }

            // Generate Value 
            for($i = 0; $i < $no_of_accounts; ++$i) {
                $account_details[$account_keys[$i]]['value'] = number_format(
                    $account_details[$account_keys[$i]]['equity'],
                    2
                );
            }

            assert_success();
            return ['status' => true, 'data' => $account_details];
        }
        catch(Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * The method will process the offset amounts for adjustment in balance sheet.
     * @param offset_amounts_old
     * @param offset_amounts_new
     * @return array
     */
    public static function process_for_final_adjusted_amount(array $offset_amounts_old, array $offset_amounts_new): array {
        
        // Account keys
        $old_account_keys = array_keys($offset_amounts_old);
        $new_account_keys = array_keys($offset_amounts_new);

        // Common keys
        $common_keys = [];

        // Unique keys
        $unique_keys = [];

        // Combined and Unique Keys
        $keys = array_unique(array_merge($old_account_keys, $new_account_keys));

        // Find common and unique keys
        foreach($keys as $key) {
            // Check whether the following key exists in both the arrays
            $is_key_present_on_both = array_key_exists($key, $offset_amounts_old) && array_key_exists($key, $offset_amounts_new);

            if($is_key_present_on_both) $common_keys []= $key;
            else $unique_keys []= $key;
        }

        // Final offset amounts
        $final_offset_amounts = [];

        // Process common keys
        foreach($common_keys as $key) {

            // Add key if not exists
            if(!array_key_exists($key, $final_offset_amounts)) $final_offset_amounts[$key] = 0;

            // Find difference
            $diff_amount = $offset_amounts_new[$key] + $offset_amounts_old[$key];
            
            // Set final amount to be adjusted
            $final_offset_amounts[$key] = $diff_amount;
        }

        // Process unique keys
        foreach($unique_keys as $key) {
            if(array_key_exists($key, $offset_amounts_old)) $amount = $offset_amounts_old[$key];
            else $amount = $offset_amounts_new[$key];
            $final_offset_amounts[$key] = $amount;
        }

        return $final_offset_amounts;
    }

    /**
     * Fetch Balance Sheet By Unique ID from date.
     */
    private const FETCH_BALANCE_SHEET_FROM_DATE = <<<'EOS'
    SELECT 
        * 
    FROM 
        balance_sheet 
    WHERE 
        store_id = :store_id
    AND
        `date` >= :date
    ORDER BY 
        `date` ASC;
    EOS;

    /**
     * Fetch Balance Sheet by Unique id before timestamp.
     */
    private const FETCH_BALANCE_SHEET_BY_DATE_BEFORE_TIMESTAMP = <<<'EOS'
    SELECT 
        * 
    FROM 
        balance_sheet 
    WHERE 
        store_id = :store_id 
    AND 
        `date` < :date
    ORDER BY 
        `date` DESC
    LIMIT 1;
    EOS;

    /**
     * This method will carry forward detail if new year.
     * @param p_statement Previous Statement
     * @return array
     */
    private static function carry_forward_relevant_details_if_new_year(array $p_statement): array {
        $statement = AccountsConfig::ACCOUNTS;
        $statement[AccountsConfig::ACCOUNTS_RECEIVABLE] = $p_statement[AccountsConfig::ACCOUNTS_RECEIVABLE];
        $statement[AccountsConfig::ACCOUNTS_PAYABLE] = $p_statement[AccountsConfig::ACCOUNTS_PAYABLE];
        $statement[AccountsConfig::INVENTORY_A] = $p_statement[AccountsConfig::INVENTORY_A];
        return $statement;
    }

    /**
     * This method will update the balance sheet from the given date.
     * @param account_details
     * @param date 
     * @param store_id
     * @param db PDO Instance
     * @throws Exception
     * @return void
     */
    public static function update_from(array $account_details, string $date, int $store_id, PDO &$db) {

        // Fetch the Balance sheet from the given unique date by store.
        $statement = $db -> prepare(self::FETCH_BALANCE_SHEET_FROM_DATE);
        $statement -> execute([':date' => $date, ':store_id' => $store_id]);
        $balance_sheet_statements = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $record_count = count($balance_sheet_statements);

        // Flags 
        // By Default this will be true
        $create_new_record = true;
        $is_record_matching_unique_id = false;

        $debug = '';
        if($record_count > 0) {
            $debug .= '0';
            // Check whether balance sheet exists for the current date.
            if($balance_sheet_statements[0]['date'] === $date) {
                $is_record_matching_unique_id = true;
                $create_new_record = false;
            }
        }

        // Base Record 
        // Check for unassigned base record 
        // Assign it a default value
        $base_balance_sheet = null;
        if($create_new_record) {
            // Fetch Balance Sheet Before the Date
            $statement = $db -> prepare(self::FETCH_BALANCE_SHEET_BY_DATE_BEFORE_TIMESTAMP);
            $statement -> execute([':store_id' => $store_id, ':date' => $date]);
            $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            if(count($records) > 0) {
                $base_balance_sheet = $records[0]['statement'];
                $statement_date = $records[0]['date'];

                $date_year = intval(explode('-', $date)[0]);
                $statement_year = intval(explode('-', $statement_date)[0]);

                if($date_year > $statement_year) {
                    // Carry forward
                    $base_balance_sheet = self::carry_forward_relevant_details_if_new_year(
                        json_decode($base_balance_sheet, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                    );

                    // Convert back into json 
                    $base_balance_sheet = json_encode($base_balance_sheet, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                }
            }
            else {
                $base_balance_sheet = json_encode(AccountsConfig::ACCOUNTS, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            }

            // Insert Balance Sheet in Database
            $statement_insert = $db -> prepare(self::CREATE_BALANCE_SHEET);
            $statement_insert -> execute([
                ':statement' => $base_balance_sheet,
                ':date' => $date,
                ':store_id' => $store_id,
            ]);

            // Pkey 
            $pkey = $db -> lastInsertId();
            if($pkey === false) {
                throw new Exception('Cannot create New/Carry forward Balance sheet statement.');
            }

            // Convert it back into an array 
            $base_balance_sheet = json_decode($base_balance_sheet, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        }

        // Check for Record matching unique id 
        else if($is_record_matching_unique_id) {
            $base_balance_sheet = json_decode($balance_sheet_statements[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        }

        // Fetch Balance Sheet Records Again
        $statement_fetch = $db -> prepare(self::FETCH_BALANCE_SHEET_FROM_DATE);
        $statement_fetch -> execute([':date' => $date, ':store_id' => $store_id]);
        $balance_sheet_statements = $statement_fetch -> fetchAll(PDO::FETCH_ASSOC);

        // Account Keys
        $account_keys = array_keys($account_details);   

        // Prepared Statement
        $statement_update = $db -> prepare(self::UPDATE_BALANCE_SHEET);
        
        // Update
        foreach($balance_sheet_statements as $balance_sheet) {
            $statement = json_decode($balance_sheet['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

            // Apply Changes
            foreach($account_keys as $key) {
            
                // Add key if not exists
                if(!array_key_exists($key, $statement)) $statement[$key] = 0.0;

                // Adjust key
                $statement[$key] += $account_details[$key];
            }

            // Update 
            $is_successful = $statement_update -> execute([
                ':statement' => json_encode($statement, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':date' => $balance_sheet['date'],
                ':store_id' => $balance_sheet['store_id'],
                ':modified' => $balance_sheet['modified'],
            ]);

            if($is_successful !== true && $statement_update -> rowCount() < 1) {
                throw new Exception('Cannot Update Balance Sheet.');
            }
        }
    }
}
?>