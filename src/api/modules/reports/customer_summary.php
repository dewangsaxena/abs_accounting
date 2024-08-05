<?php
/**
 * Customer Sales Summary
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";

class CustomerSummary {
    private const MAX_NO_OF_CLIENTS_ALLOWED = 10;

    // Month Data Categorization
    private const MONTH_DATA = [
        'sumTotal' => 0,
        'subTotal' => 0,
        'profitMargin' => 0,
        'cogsMargin' => 0,
        'amountReceived' => 0,
        'goodsCost' => 0,
    ];

    // No. of Records
    private const NO_OF_RECORDS = 20;

    /**
     * This method will group by date. 
     * @param results
     * @param data
     */
    private static function group_by_year_month(array &$results, array &$data): void {
        $current_year = intval(date('Y'));
        foreach($results as $result) {
            $client_id = $result['client_id'];
            $month = $result['month'];
            $year = $result['year'];
            $txn_type = $result['txn_type'];

            // Group By Client
            if(isset($data[$client_id]) === false) $data[$client_id] = [
                'name' => $result['name'],
                'category' => $result['category'],
                'lastPurchaseDate' => $result['last_purchase_date'],
                'summary' => [],
                'ytd' => [
                    'sumTotal' => 0,
                    'subTotal' => 0,
                    'sumReturned' => 0,
                    'subReturned' => 0,
                    'cogsMargin' => 0,
                    'profitMargin' => 0,
                ],
            ];

            // Group By Year
            if(isset($data[$client_id]['summary'][$year]) === false) $data[$client_id]['summary'][$year] = [
                'monthlyReport' => [
                    1 => self::MONTH_DATA, 2 => self::MONTH_DATA, 3 => self::MONTH_DATA, 4 => self::MONTH_DATA, 
                    5 => self::MONTH_DATA, 6 => self::MONTH_DATA, 7 => self::MONTH_DATA, 8 => self::MONTH_DATA, 
                    9 => self::MONTH_DATA, 10 => self::MONTH_DATA, 11 => self::MONTH_DATA, 12 => self::MONTH_DATA,
                ],
                'subTotal' => 0,
                'goodsCost' => 0,
            ];

            // Add to Month
            if($txn_type === SALES_INVOICE) {

                // YTD
                if($current_year === $year) {
                    $data[$client_id]['ytd']['sumTotal'] += $result['sum_total'];
                    $data[$client_id]['ytd']['subTotal'] += $result['sub_total'];
                }
                
                // Yearly Subtotal
                $data[$client_id]['summary'][$year]['subTotal'] += $result['sub_total'];

                // Yearly COGS
                $data[$client_id]['summary'][$year]['goodsCost'] += $result['goods_cost'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['sumTotal'] += $result['sum_total'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['subTotal'] += $result['sub_total'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'] += $result['goods_cost'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['amountReceived'] += ($result['credit_amount'] == 0 ? $result['sub_total'] : 0);
            }
            else if($txn_type === SALES_RETURN) {

                if($current_year === $year) {
                    $data[$client_id]['ytd']['sumReturned'] += $result['sum_total'];
                    $data[$client_id]['ytd']['subReturned'] += $result['sub_total'];
                }

                // Yearly Subtotal
                $data[$client_id]['summary'][$year]['subTotal'] -= $result['sub_total'];

                // Yearly Cogs
                $data[$client_id]['summary'][$year]['goodsCost'] -= $result['goods_cost'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['sumTotal'] -= $result['sum_total'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['subTotal'] -= $result['sub_total'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'] -= $result['goods_cost'];
                $data[$client_id]['summary'][$year]['monthlyReport'][$month]['amountReceived'] -= ($result['credit_amount'] == 0 ? $result['sub_total'] : 0);
            }
            else throw new Exception('Invalid Transaction Type');
        }
    }

    /**
     * Calculate COGS and Profit Margin
     * @param data
     */
    private static function calculate_cogs_and_profit_margins(array &$data): void {
        $client_keys = array_keys($data);
        $current_year = intval(date('Y'));
        foreach($client_keys as $client_id) {
            $total_sale_ytd = 0;
            $total_cogs_ytd = 0;
            $years = array_keys($data[$client_id]['summary']);
            foreach($years as $year) {
                $months = array_keys($data[$client_id]['summary'][$year]['monthlyReport']);
                foreach($months as $month) {
                    if($data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'] > 0) {

                        // COGS Margin
                        $data[$client_id]['summary'][$year]['monthlyReport'][$month]['cogsMargin'] = Utils::calculateCOGSMargin(
                            $data[$client_id]['summary'][$year]['monthlyReport'][$month]['subTotal'],
                            $data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'],
                        );

                        // Profit Margin
                        $data[$client_id]['summary'][$year]['monthlyReport'][$month]['profitMargin'] = Utils::calculateProfitMargin(
                            $data[$client_id]['summary'][$year]['monthlyReport'][$month]['subTotal'],
                            $data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'],
                        );

                        // YTD
                        if($current_year === $year) {
                            $total_sale_ytd += $data[$client_id]['summary'][$year]['monthlyReport'][$month]['subTotal'];
                            $total_cogs_ytd += $data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost'];
                        }
                    }
                    
                    // Delete Goods Cost
                    unset($data[$client_id]['summary'][$year]['monthlyReport'][$month]['goodsCost']);
                }
            }

            // Set Total COGS and Profit Margin
            if($total_cogs_ytd > 0) {
                $data[$client_id]['ytd']['cogsMargin'] = Utils::calculateCOGSMargin($total_sale_ytd, $total_cogs_ytd);
                $data[$client_id]['ytd']['profitMargin'] = Utils::calculateProfitMargin($total_sale_ytd, $total_cogs_ytd);
            }
        }
    }

    /**
     * This method will unpack client details.
     * @param store_id
     * @param record
     */
    private static function unpack_client_details(int $store_id, array &$record): void {
        $count = count($record);
        for($i = 0; $i < $count; ++$i) {
            // Amount Owing
            $amount_owing = json_decode($record[$i]['amount_owing'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $record[$i]['amount_owing'] = $amount_owing[$store_id] ?? 0;
            
            // Credit Limit
            $credit_limit = json_decode($record[$i]['credit_limit'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $record[$i]['credit_limit'] = $credit_limit[$store_id] ?? 0;

            // Last Purchase Date
            $last_purchase_date = json_decode($record[$i]['last_purchase_date'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $last_purchase_date = $last_purchase_date[$store_id] ?? null;

            if(is_string($last_purchase_date)) {
                $record[$i]['last_purchase_date'] = Utils::format_to_human_readable_date($last_purchase_date);
            }
            else $record[$i]['last_purchase_date'] = 'N/A';
        }
    }

    /**
     * This method will filter the records by amount. 
     * @param minimum_amount
     * @param maximum_amount
     * @param data
     */
    private static function filter_by_amount(float $minimum_amount, float $maximum_amount, &$data): void {
        if($minimum_amount == 0 && $maximum_amount == 0) return;

        $client_ids = array_keys($data);
        $no_of_keys = count($client_ids);
        for($i = 0; $i < $no_of_keys; ++$i) {
            if($minimum_amount > 0 && $maximum_amount > 0) {
                if(!($data[$client_ids[$i]]['ytd']['sumTotal'] >= $minimum_amount && $data[$client_ids[$i]]['yts']['sumTotal'] <= $maximum_amount)) {
                    unset($data[$client_ids[$i]]);
                }
            }
            else if($minimum_amount > 0) {
                if($data[$client_ids[$i]]['ytd']['sumTotal'] < $minimum_amount) unset($data[$client_ids[$i]]);
            }
            else if($maximum_amount > 0) {
                if($data[$client_ids[$i]]['ytd']['sumTotal'] > $maximum_amount) unset($data[$client_ids[$i]]); 
            }
        }
    }

    /**
     * This method will limit records.
     * @param data
     * @param offset
     * @return array
     */
    private static function limit_records(array &$data, int $offset): array {
        $selected_clients = array_slice(array_keys($data), $offset, self::NO_OF_RECORDS);
        $new_data = [];
        foreach($selected_clients as $client_id) {
            $new_data[$client_id] = $data[$client_id];
        }
        return $new_data;
    }

    /**
     * This method will fetch the customer sales summary. 
     * @param params
     * @param store_id
     * @return array
     */
    public static function fetch(array $params, int $store_id): array {
        try {
            $current_year = date('Y');
            
            // Query Values
            $values = [
                ':store_id' => $store_id,
                ':yearFrom' => $current_year,
                ':yearTill' => $current_year,
            ];

            // Query
            $query = <<<'EOS'
            SELECT 
                _tmp.`txn_type`,
                _tmp.`client_id`,
                _tmp.`month`,
                _tmp.`year`,
                _tmp.`sum_total`,
                _tmp.`sub_total`,
                _tmp.`credit_amount`,
                _tmp.`goods_cost`,
                c.`name`,
                c.`amount_owing`,
                c.`category`,
                c.`credit_limit`,
                c.`last_purchase_date`
            FROM (
                SELECT 
                    1 AS `txn_type`,
                    `client_id`,
                    MONTH(`date`) AS `month`,
                    YEAR(`date`) AS `year`,
                    `sum_total`,
                    `sub_total`,
                    `credit_amount`,
                    `cogs` AS `goods_cost`,
                    `store_id`
                FROM 
                    sales_invoice
                UNION ALL
                SELECT 
                    2 AS `txn_type`,
                    `client_id`,
                    MONTH(`date`) AS `month`,
                    YEAR(`date`) AS `year`,
                    `sum_total`,
                    `sub_total`,
                    `credit_amount`,
                    `cogr` AS `goods_cost`,
                    `store_id`
                FROM 
                    sales_return
                ) AS _tmp
            LEFT JOIN 
                clients AS c
            ON 
                c.id = _tmp.client_id 
            WHERE 
                _tmp.store_id = :store_id 
            AND 
                _tmp.`year` >= :yearFrom
            AND 
                _tmp.`year` <= :yearTill 
            EOS;

            // Selected Clients
            if(is_array($params['selectedClients'] ?? null)) {

                // Check for limit
                if(count($params['selectedClients']) > self::MAX_NO_OF_CLIENTS_ALLOWED) throw new Exception('Max of clients allowed: '. self::MAX_NO_OF_CLIENTS_ALLOWED);

                // Append Query
                $query .= ' AND c.id IN (:placeholder) ';

                // Substitute placeholder
                $ret_value = Utils::mysql_in_placeholder_pdo_substitute($params['selectedClients'], $query);
                $__values = $ret_value['values'];
                $query = $ret_value['query'];

                // Array merge
                $values = array_merge($values, $__values);
            } 

            if(is_numeric($params['yearFrom'] ?? null)) $values[':yearFrom'] = $params['yearFrom'];

            if(is_numeric($params['yearTill'] ?? null)) $values[':yearTill'] = $params['yearTill'];

            if(is_numeric($params['category'] ?? null) && $params['category'] !== Client::CATEGORY_ALL) {
                $values[':category'] = $params['category'];
                $query .= ' AND c.`category` = :category ';
            }

            // Add Order by clause
            $query .= <<<EOS
            ORDER BY 
                `client_id` ASC,
                `txn_type` ASC,
                `month` ASC,
                `year` ASC;
            EOS;

            $db = get_db_instance();
            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
            $data = [];

            self::unpack_client_details($store_id, $results);
            self::group_by_year_month($results, $data);
            self::calculate_cogs_and_profit_margins($data);
            self::filter_by_amount(
                $params['minimumAmount'] ?? 0,
                $params['maximumAmount'] ?? 0,
                $data,
            );

            // Offset 
            $offset = 0;

            // Add Offset
            if(($params['__offset'] ?? 0) > 0) $offset += intval($params['__offset']);

            $data = self::limit_records($data, $offset);
            
            $response = [
                'report' => $data,
                '__offset' => $offset + self::NO_OF_RECORDS,
            ];
            return ['status' => true, 'data' => $response];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }
}