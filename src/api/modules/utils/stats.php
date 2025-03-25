<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";

class Stats
{

    /**
     * This method will fetch stats. 
     * @return array
     */
    public static function stats(): array
    {
        try {
            $db = get_db_instance();
            $data = [];

            // Store Id
            $store_id = intval($_SESSION['store_id']);

            // Current Date
            $current_date = Utils::get_business_date($store_id);

            // Stats
            $revenue = 0;
            $cogs = 0;
            $discount = 0;
            $sales_return = 0;
            $receipt_payment = 0;
            $receipt_discount = 0;

            // Params
            $self_clients = array_keys(Client::SELF_CLIENT_WHITELIST[SYSTEM_INIT_MODE]);

            $query = <<<'EOS'
            SELECT 
                sub_total, 
                sub_total, 
                txn_discount, 
                cogs 
            FROM 
                sales_invoice
            WHERE 
                `date` = :date
            AND 
                store_id = :store_id
            AND
                client_id NOT IN (:placeholder);
            EOS;
            $result = Utils::mysql_in_placeholder_pdo_substitute($self_clients, $query);
            $params = array_merge(
                [':date' => $current_date, ':store_id' => $store_id],
                $result['values'],
            );
            $statement = $db->prepare($result['query']);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $revenue += $r['sub_total'];
                $cogs += $r['cogs'];
                $discount += $r['txn_discount'];
            }

            $result = Utils::mysql_in_placeholder_pdo_substitute(
                $self_clients,
                <<<'EOS'
                SELECT 
                    sub_total, 
                    sub_total, 
                    txn_discount, 
                    cogr, 
                    restocking_fees 
                FROM 
                    sales_return 
                WHERE 
                    `date` = :date 
                AND 
                    store_id = :store_id
                AND 
                    client_id NOT IN (:placeholder);
                EOS
            );
            
            $statement = $db->prepare($result['query']);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $sales_return += ($r['sub_total'] + $r['restocking_fees']);
                $cogs -= $r['cogr'];
                $discount -= $r['txn_discount'];
            }

            // Receipt
            $params_receipt = [':date' => $current_date, ':store_id' => $store_id];
            $statement = $db->prepare('SELECT sum_total, total_discount FROM receipt WHERE `date` = :date AND store_id = :store_id AND do_conceal = 0;');
            $statement->execute($params_receipt);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $receipt_payment += $r['sum_total'];
                $receipt_discount += $r['total_discount'];
            }

            // Calculate Net Amounts
            $net_income = $revenue - $sales_return - $cogs - $receipt_discount;
            $net_revenue = $revenue - $sales_return - $receipt_discount;

            // Calculate Margins
            $profit_margin = $net_revenue & $cogs ? Utils::calculateProfitMargin($net_revenue, $cogs) : 0;
            $cogs_margin = $net_income & $cogs ? Utils::calculateCOGSMargin($net_income, $cogs) : 0;

            $data = [
                'totalRevenue' => $revenue,
                'cogs' => $cogs,
                'discount' => $discount,
                'salesReturn' => $sales_return,
                'netIncome' => $net_income,
                'profitMargin' => $profit_margin,
                'cogsMargin' => $cogs_margin,
                'receiptPayments' => $receipt_payment,
                'receiptDiscount' => $receipt_discount,
            ];

            return ['status' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
