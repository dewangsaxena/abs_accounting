<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";

/**
 * This class implements methods to generate sales per store per client.
 */
class CustomerSales {

    /**
     * This method will generate report.
     * @param store_id
     * @param from_date 
     * @param till_date
     */
    public static function generate_report(int $store_id, string $from_date, string $till_date): void {
        $db = get_db_instance();

        $query = 'SELECT client_id, SUM(sum_total) AS sum_total, YEAR(`date`) as __year FROM __TABLE_NAME__ WHERE store_id = :store_id AND `date` >= :_start_date AND `date` <= :end_date GROUP BY client_id, __year ORDER BY __year ASC;';

        $statement_sales_invoices = $db -> prepare(str_replace('__TABLE_NAME__', 'sales_invoice', $query));
        $params = [':store_id' => $store_id, ':_start_date' => $from_date, ':end_date' => $till_date];
        $statement_sales_invoices -> execute($params);
        $sales_invoices = $statement_sales_invoices -> fetchAll(PDO::FETCH_ASSOC);
        $records = [];

        foreach($sales_invoices as $r) {
            $client_id = $r['client_id'];
            if(isset($records[$client_id]) === false) {
                $records[$client_id] = [
                    'name' => '',
                    '2024' => 0,
                    '2025' => 0,
                ];
            }
            $records[$client_id][$r['__year']] += $r['sum_total'];
        }

        $statement_sales_returns = $db -> prepare(str_replace('__TABLE_NAME__', 'sales_return', $query));
        $statement_sales_returns -> execute($params);
        $sales_returns = $statement_sales_returns -> fetchAll(PDO::FETCH_ASSOC);
        foreach($sales_returns as $r) {
            $client_id = $r['client_id'];
            if(isset($records[$client_id]) === false) {
                $records[$client_id] = [
                    'name' => '',
                    '2024' => 0,
                    '2025' => 0,
                ];
            }
            $records[$client_id][$r['__year']] -= $r['sum_total'];
        }

        // Fetch Clients
        $client_ids = array_keys($records);
        $query = 'SELECT id, `name` FROM clients WHERE id IN (:placeholder);';
        $results = Utils::mysql_in_placeholder_pdo_substitute($client_ids, $query);
        $statement_client_details = $db -> prepare($results['query']);
        $statement_client_details -> execute($results['values']);
        $client_details_records = $statement_client_details -> fetchAll(PDO::FETCH_ASSOC);
        foreach($client_details_records as $r) {
            $records[$r['id']]['name'] = $r['name'];
        }

        self::format($store_id, $records);
    }

    /**
     * This methid will format the details for printing.
     * @param details
     */
    private static function format(int $store_id, array &$details): void {

        $store_name = strtoupper(StoreDetails::STORE_DETAILS[$store_id]['name']);
        echo <<<EOS
        <html>
        <body>
            <h3>DETAILS FOR <b>$store_name</b></h3><br>
        EOS;
        foreach($details as $detail) {
            echo $detail['name'].'<br>';
            $_2024 = Utils::round($detail['2024']);
            $_2025 = Utils::round($detail['2025']);
            echo <<<EOS
<ul>
    <li>2024: $ $_2024</li>
    <li>2025: $ $_2025</li>
</ul>
EOS;    
        }

        echo <<<EOS
        </body>
        </html>
        EOS;

    }
}