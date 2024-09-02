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
            $params = [':date' => $current_date, ':store_id' => $store_id];

            $statement = $db->prepare('SELECT sub_total, sub_total, txn_discount, cogs FROM sales_invoice WHERE `date` = :date AND store_id = :store_id;');
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $revenue += $r['sub_total'];
                $cogs += $r['cogs'];
                $discount += $r['txn_discount'];
            }

            $statement = $db->prepare('SELECT sub_total, sub_total, txn_discount, cogr FROM sales_return WHERE `date` = :date AND store_id = :store_id;');
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $sales_return += $r['sub_total'];
                $cogs -= $r['cogr'];
                $discount -= $r['txn_discount'];
            }

            $statement = $db->prepare('SELECT sum_total, total_discount FROM receipt WHERE `date` = :date AND store_id = :store_id;');
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $r) {
                $receipt_payment += $r['sum_total'];
                $receipt_discount += $r['total_discount'];
            }

            $net_income = $revenue - $sales_return - $cogs - $receipt_discount;
            $net_revenue = $revenue - $sales_return - $receipt_discount;
            $profit_margin = Utils::calculateProfitMargin($net_revenue, $cogs);
            $cogs_margin = Utils::calculateCOGSMargin($net_income, $cogs);

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
