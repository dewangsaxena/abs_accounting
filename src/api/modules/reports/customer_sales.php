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
    public static function generate_report(int $store_id, int $year): void {
        $db = get_db_instance();

        // Query
        $query = 'SELECT client_id, SUM(sum_total) AS sum_total FROM __TABLE_NAME__ WHERE store_id = :store_id AND `date` LIKE :_year GROUP BY client_id;';

        $statement_sales_invoices = $db -> prepare(str_replace('__TABLE_NAME__', 'sales_invoice', $query));
        $params = [':store_id' => $store_id, ':_year' => "$year-__-__"];
        $statement_sales_invoices -> execute($params);
        $sales_invoices = $statement_sales_invoices -> fetchAll(PDO::FETCH_ASSOC);
        $records = [];
        $total = 0;

        foreach($sales_invoices as $r) {
            $client_id = $r['client_id'];
            if(isset($records[$client_id]) === false) {
                $records[$client_id] = [
                    'name' => '',
                    'sum_total' => 0,
                ];
            }
            $records[$client_id]['sum_total'] += $r['sum_total'];
            $total += $r['sum_total'];
        }

        $statement_sales_returns = $db -> prepare(str_replace('__TABLE_NAME__', 'sales_return', $query));
        $statement_sales_returns -> execute($params);
        $sales_returns = $statement_sales_returns -> fetchAll(PDO::FETCH_ASSOC);
        foreach($sales_returns as $r) {
            $client_id = $r['client_id'];
            if(isset($records[$client_id]) === false) {
                $records[$client_id] = [
                    'name' => '',
                    'sum_total' => 0,
                ];
            }
            $records[$client_id]['sum_total'] -= $r['sum_total'];
            $total -= $r['sum_total'];
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
        self::format($store_id, $records, $total);
    }

    /**
     * This methid will format the details for printing.
     * @param store_id
     * @param details
     * @param total
     */
    private static function format(int $store_id, array &$details, float $total): void {

        $store_name = strtoupper(StoreDetails::STORE_DETAILS[$store_id]['name']);
        echo <<<EOS
        <html>
        <body>
            <h3>DETAILS FOR <b>$store_name</b></h3><br>
            <table>
            <tr>
                <th>Name</th>
                <th>Amount</th>
            </tr>
        EOS;
        foreach($details as $detail) {
            $name = $detail['name'];
            $sum_total = Utils::number_format($detail['sum_total']);

            echo <<<EOS
            <tr>
                <td>$name</td>
                <td>$ $sum_total</td>
            </tr>
            EOS;
        }

        $total = Utils::number_format($total);
        echo <<<EOS
        </table>
        <br>
        <span>Total: $$total</span>
        </body>
        </html>
        EOS;

    }
}