<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";

class Stats {

    // Sum by Payment Method
    const REVENUE_BY_PAYMENT_METHODS = [
        PaymentMethod::PAY_LATER => 0,
        PaymentMethod::CASH => 0,
        PaymentMethod::CHEQUE => 0,
        PaymentMethod::AMERICAN_EXPRESS => 0,
        PaymentMethod::MASTERCARD => 0,
        PaymentMethod::VISA => 0,
        PaymentMethod::DEBIT => 0,
    ];

    /**
     * This method will fetch stats. 
     * @return array
     */
    public static function stats(int $store_id): array {
        try {
            $db = get_db_instance();
            $data = [
                'currentMonth' => null,
                'currentDate' => null,
            ];

            $data['currentMonth'] = self::fetch_details_for_month_by_store($store_id, $db);
            $data['currentDate'] = self::fetch_details_by_store($store_id, $db);
    
            return ['status' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * This method will fetch details for month by store.
     * @param store_id
     * @param db
     * @return array
     */
    private static function fetch_details_for_month_by_store(int $store_id, PDO &$db): array {

        // Revenue 
        $revenue_by_payment_methods = self::REVENUE_BY_PAYMENT_METHODS;

        // Current Month 
        $month = date('m');
        $current_month = date('Y').'-'. $month.'-__';

        $query = <<<"EOS"
        SELECT 
            sub_total, 
            txn_discount, 
            cogs,
            payment_method
        FROM 
            sales_invoice
        WHERE 
            `date` LIKE :_date
        AND 
            store_id = :store_id
        AND
            client_id NOT IN (:placeholder)
        EOS;

        // Params
        $self_clients = array_keys(Client::SELF_CLIENT_WHITELIST);

        if (count($self_clients) == 0) $self_clients = [-1];
        $result = Utils::mysql_in_placeholder_pdo_substitute($self_clients, $query);
        $params = array_merge(
            [':_date' => $current_month, ':store_id' => $store_id],
            $result['values'],
        );
        $statement = $db->prepare($result['query']);
        $statement->execute($params);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $r) {
            $payment_method = $r['payment_method'];
            $revenue_by_payment_methods[$payment_method] += $r['sub_total'];
        }

        $result = Utils::mysql_in_placeholder_pdo_substitute(
            $self_clients,
            <<<'EOS'
            SELECT 
                sub_total, 
                txn_discount, 
                cogr, 
                restocking_fees,
                payment_method
            FROM 
                sales_return 
            WHERE 
                `date` LIKE :_date
            AND
                store_id = :store_id 
            AND 
                client_id NOT IN (:placeholder);
        EOS);
            
        $statement = $db->prepare($result['query']);
        $statement->execute($params);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $r) {
            $payment_method = $r['payment_method'];
            $revenue_by_payment_methods[$payment_method] -= ($r['sub_total'] + $r['restocking_fees']);
        }

        return $revenue_by_payment_methods;
    }

    /**
     * Fetch Details by Store
     * @return array
     */
    private static function fetch_details_by_store(int $store_id, PDO &$db): array {

        // Current Date
        $current_date = Utils::get_business_date($store_id);

        // Stats
        $revenue_by_payment_method_current_date = self::REVENUE_BY_PAYMENT_METHODS;

        $revenue = 0;
        $cogs = 0;
        $discount = 0;
        $sales_return = 0;
        $receipt_payment = 0;
        $receipt_discount = 0;

        // Params
        $self_clients = array_keys(Client::SELF_CLIENT_WHITELIST);

        $query = <<<'EOS'
        SELECT 
            sub_total, 
            txn_discount, 
            cogs,
            payment_method
        FROM 
            sales_invoice
        WHERE 
            `date` = :date
        AND 
            store_id = :store_id
        AND
            client_id NOT IN (:placeholder);
        EOS;

        if (count($self_clients) == 0) $self_clients = [-1];
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
            $payment_method = $r['payment_method'];
            $revenue_by_payment_method_current_date[$payment_method] += $r['sub_total'];
        }

        $result = Utils::mysql_in_placeholder_pdo_substitute(
            $self_clients,
            <<<'EOS'
            SELECT 
                sub_total, 
                txn_discount, 
                cogr, 
                restocking_fees,
                payment_method
            FROM 
                sales_return 
            WHERE 
                `date` = :date 
            AND 
                store_id = :store_id
            AND 
                client_id NOT IN (:placeholder);
        EOS);
            
        $statement = $db->prepare($result['query']);
        $statement->execute($params);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $r) {
            $sales_return += ($r['sub_total'] + $r['restocking_fees']);
            $cogs -= $r['cogr'];
            $discount -= $r['txn_discount'];
            $payment_method = $r['payment_method'];
            $revenue_by_payment_method_current_date[$payment_method] -= $r['sub_total'];
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

        return [
            'totalRevenue' => $revenue,
            'cogs' => $cogs,
            'discount' => $discount,
            'salesReturn' => $sales_return,
            'netIncome' => $net_income,
            'profitMargin' => $profit_margin,
            'cogsMargin' => $cogs_margin,
            'receiptPayments' => $receipt_payment,
            'receiptDiscount' => $receipt_discount,
            'revenueByPaymentMethod' => $revenue_by_payment_method_current_date,
        ];
    }
}
