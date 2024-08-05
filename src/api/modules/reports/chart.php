<?php 
/**
 * This module will show the data for charts.
 * @author Dewang Saxena, <dewang2610@gmail.com>
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/accounts.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";

class Charts {

    public const OVERVIEW_LABELS = [  
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];

    /**
     * This method will fetch the income statement for the given date range.
     * @param records
     * @return array Containing the data points.
     */
    public static function income_statement(array $records): array {
        try {
            // Format Data 
            $data_points = [];

            foreach($records as $record) {

                // Current store 
                $current_store = $record['store_id'];

                // Temp
                $temp = [];

                // Add data point per location
                if(!array_key_exists($current_store, $data_points)) {
                    $data_points[$current_store] = [];
                }

                // Decode back into Array
                $statement = json_decode($record['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                
                // Inventory A 
                $inventory_A = abs($statement[AccountsConfig::INVENTORY_A]);
                
                // Profit
                $temp['profit'] = Utils::round($statement[AccountsConfig::SALES_INVENTORY_A] - $inventory_A, 2);

                // Sales 
                $temp['sales'] = Utils::round($statement[AccountsConfig::SALES_INVENTORY_A], 2);

                // Inventory 
                $temp['inventory'] = Utils::round($statement[AccountsConfig::INVENTORY_A], 2);

                // Date
                $temp['name'] = $record['date'];

                // Add to Array
                array_push($data_points[$current_store], $temp);
            }
            return ['status' => true, 'data' => $data_points];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    // /**
    //  * This method will return the data points for generating Revenue YTD chart.
    //  * @return array Containing the datapoints.
    //  */
    // public static function revenue_ytd(): array {

    //     // DB instance
    //     $db = get_db_instance();

    //     try {
    //         // Beginning of the year 
    //         $year_start = date('Y'). '-01-01';

    //         // Get Current Date in Business Time
    //         $current_date = Utils::get_business_date();

    //         // Fetch Income Statements and Balance Sheet
    //         $statement = $db -> prepare(Queries::FETCH_INCOME_STATEMENTS_AND_BALANCE_SHEET_YTD);
    //         $statement -> execute([
    //             ':year_begin' => $year_start,
    //             ':ytd' => $current_date,
    //         ]);
            
    //         // Fetch records
    //         $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

    //         // Format Data 
    //         $data_points = [
    //             'sales' => [],
    //             'profit' => [],
    //             'inventory' => [], 
    //             'discount' => [],
    //         ];

    //         foreach($records as $record) {
                
    //             // Decode back into Array
    //             $statement = json_decode($record['statement'], true, flags: JSON_NUMERIC_CHECK);
                
    //             // Inventory A 
    //             $inventory_A = abs($statement[AccountsConfig::INVENTORY_A]);

    //             /* Data points from Income Statement */ 
    //             if($record['type'] === 'is') {
    //                 /* Profit */ 
    //                 array_push($data_points['profit'], 
    //                     $statement[AccountsConfig::SALES_INVENTORY_A]
    //                     -
    //                     $inventory_A
    //                 );

    //                 /* Sales */ 
    //                 array_push($data_points['sales'], $statement[AccountsConfig::SALES_INVENTORY_A]);
    //             }

    //             /* Data Points from Balance Sheet */ 
    //             if($record['type'] === 'bs') {
    //                 /* Inventory */ 
    //                 array_push($data_points['inventory'], $inventory_A);

    //                 /* Discount */ 
    //                 array_push($data_points['discount'], $statement[AccountsConfig::TOTAL_DISCOUNT]);
    //             }
    //         }

    //         return $data_points;
    //     }
    //     catch(Exception $e) {
    //         return [];
    //     }
    // }
}
?>