<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";

class Clients extends Common {
    public static function set_amount_owing_per_client_per_store(): void {
        try {
            $db = get_db_instance();
            $db -> beginTransaction();
            $clients = [];
            $stores = array_keys(StoreDetails::STORE_DETAILS);
            foreach($stores as $store) {
                $summary = CustomerAgedSummary::fetch_customer_aged_summary($store, null, Utils::get_business_date($store), 0);
                
                foreach($summary as $s) {
                    $client_id = $s['client_id'];
                    if(!isset($clients[$client_id])) $clients[$client_id] = [];

                    if(!isset($clients[$client_id][$store])) $clients[$client_id][$store] = 0;
                    $clients[$client_id][$store] += $s['total'];
                }
            }

            // Round
            $new_clients = [];
            $client_keys = array_keys($clients);
            foreach($client_keys as $client_key) {
                $store_details = array_keys($clients[$client_key]);
                foreach($store_details as $s) {
                    if(!isset($new_clients[$client_key])) $new_clients[$client_key] = [];
                    if(!isset($new_clients[$client_key][$s])) $new_clients[$client_key][$s] = 0;

                    $new_clients[$client_key][$s] = Utils::round($clients[$client_key][$s], 2);
                }
            }
            
            $statement = $db -> prepare('UPDATE clients SET amount_owing = :amount_owing WHERE id = :id;');
            
            foreach($client_keys as $client_key) {
                $amount_owing = json_encode($new_clients[$client_key], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $is_successful = $statement -> execute([':id' => $client_key, ':amount_owing' => $amount_owing]);

                if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Update for Client: '. $client_key);
            }

            assert_success();
            $db -> commit();
        }
        catch(Exception $e) {
            $db -> rollBack();
            print_r($e -> getMessage());
        }
    }

    private static function format(array $old): array {
        $new = [];
        
        $new['id'] = $old['id'];
        $new['name'] = $old['_name'];
        $new['contact_name'] = $old['contact'];
        $new['street1'] = $old['street1'];
        $new['street2'] = $old['street2'];
        $new['city'] = $old['city'];
        $new['province'] = $old['province'];
        $new['postal_code'] = $old['postal_code'];
        $new['country'] = $old['country'];
        $new['phone_number_1'] = $old['phone1'];
        $new['phone_number_2'] = $old['phone2'];
        $new['fax'] = $old['fax'];
        $new['email_id'] = $old['email_id'];
        $new['additional_email_addresses'] = $old['additional_email_addresses'] ?? '';
        $new['client_since'] = is_null($old['since']) ? '1970-01-01' : $old['since'];
        $new['disable_credit_transactions'] = $old['disable_credit_txn'];
        $new['is_default_shipping_address'] = $old['is_default_shipping_address'];
        $new['default_receipt_payment_method'] = $old['receipt'];
        $new['default_payment_method'] = $old['default_payment_method'];

        // Standard Discount
        $old_standard_discount = json_decode($old['standard_discount'], true, self::JSON_FLAG);
        $standard_discount = [];
        if(is_array($old_standard_discount) === false) {
            foreach(self::STORE_KEYS as $store) {
                $standard_discount[$store] = $old_standard_discount;
            }
        }
        else $standard_discount = $old_standard_discount;
        $new['standard_discount'] = json_encode($standard_discount, self::JSON_FLAG);


        $old_standard_profit_margin = json_decode($old['standard_profit_margins'], self::JSON_FLAG);
        $standard_profit_margin = [];
        $keys = array_keys($old_standard_profit_margin);

        foreach($keys as $k) {

            if(SYSTEM_INIT_MODE === WASH) {
                $standard_profit_margin[$k] = [];
            }
            else {
                $standard_profit_margin[$k] = [
                    'STP' => $old_standard_profit_margin[$k]['stp'], 
                    'DEFAULT' => $old_standard_profit_margin[$k]['non_stp']
                ];
            }
        }
        $new['standard_profit_margins'] = json_encode($standard_profit_margin, self::JSON_FLAG);

        // Early Payment Discount
        $old_early_payment_discount = json_decode($old['early_payment_discount'], true, self::JSON_FLAG);
        $early_payment_discount = [];
        if(is_array($old_early_payment_discount) === false) {
            foreach(self::STORE_KEYS as $store) {
                $early_payment_discount[$store] = 0;
            }
        }
        else {
            $keys = array_keys($old_early_payment_discount);
            foreach($keys as $store) {
                $early_payment_discount[$store] = $old_early_payment_discount[$store];
            }
        }
        $new['early_payment_discount'] = json_encode($early_payment_discount, self::JSON_FLAG);

        $early_payment_paid_within_days = [];
        foreach(self::STORE_KEYS as $store) {
            $early_payment_paid_within_days[$store] = $old['early_payment_paid_within_days'];
        }
        $new['early_payment_paid_within_days'] = json_encode($early_payment_paid_within_days, self::JSON_FLAG);

        $net_amount_due_within_days = [];
        foreach(self::STORE_KEYS as $store) {
            $net_amount_due_within_days[$store] = $old['net_amount_due_within_days'];
        }
        $new['net_amount_due_within_days'] = json_encode($net_amount_due_within_days, self::JSON_FLAG);
        $new['produce_statement_for_client'] = 1;
        $new['memo'] = $old['memo'] ?? '';
        $new['additional_information'] = $old['additional_information'] ?? '';

        $is_inactive = [];
        foreach(self::STORE_KEYS as $store) {
            $is_inactive[$store] = $old['is_active'] ^ 1;
        }
        $new['is_inactive'] = json_encode($is_inactive, self::JSON_FLAG);
        $new['category'] = $old['category'];

        $credit_limit = [];
        foreach(self::STORE_KEYS as $store) {
            $credit_limit[$store] = $old['credit_limit'];
        }
        $new['credit_limit'] = json_encode($credit_limit, self::JSON_FLAG);
        $new['amount_owing'] = '{}';

        $old_shipping_address = json_decode($old['shipping_addresses'], true, self::JSON_FLAG);
        $new_shipping_address = Common::prepare_shipping_address($old_shipping_address, $new['name'], $new['contact_name']);
        $new['shipping_addresses'] = json_encode($new_shipping_address, self::JSON_FLAG);
        $new['name_history'] = '[]';
        $new['custom_selling_price_for_items'] = '[]';

        $gst_tax = [];
        foreach(self::STORE_KEYS as $store) {
            $gst_tax[$store] = $old['credit_limit'];
        }

        $disable_federal_taxes = [];
        foreach(self::STORE_KEYS as $store) {
            $disable_federal_taxes[$store] = $old['disable_gst_hst_tax'];
        }

        $new['disable_federal_taxes'] = json_encode($disable_federal_taxes, self::JSON_FLAG);

        $disable_provincial_taxes = [];
        foreach(self::STORE_KEYS as $store) {
            $disable_provincial_taxes[$store] = $old['has_pst_number'];
        }
        $new['disable_provincial_taxes'] = json_encode($disable_provincial_taxes, self::JSON_FLAG);
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    public static function read(int $from, ?int $till=null): array {
        $db = get_old_db_instance();

        $query = 'SELECT * FROM clients WHERE id >= :_from '. (is_numeric($till) ? ' AND id <= :_till': '').';';
        $statement = $db -> prepare($query);
        $values = [':_from' => $from];
        if(is_numeric($till)) $values[':_till'] = $till;
        $statement -> execute($values);

        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $clients = [];
        foreach($records as $c) {
            $clients[]= self::format($c);
        }
        return $clients;
    }

    public static function write(array $clients) : void {
        $db = get_db_instance();

        $query = <<<'EOS'
        INSERT INTO clients 
        (
            `id`,
            `name`,
            `contact_name`,
            `street1`,
            `street2`,
            `city`,
            `province`,
            `postal_code`,
            `country`,
            `phone_number_1`,
            `phone_number_2`,
            `fax`,
            `email_id`,
            `additional_email_addresses`,
            `client_since`,
            `disable_credit_transactions`,
            `is_default_shipping_address`,
            `default_receipt_payment_method`,
            `default_payment_method`,
            `standard_discount`,
            `standard_profit_margins`,
            `early_payment_discount`,
            `early_payment_paid_within_days`,
            `net_amount_due_within_days`,
            `produce_statement_for_client`,
            `memo`,
            `additional_information`,
            `is_inactive`,
            `category`,
            `credit_limit`,
            `amount_owing`,
            `shipping_addresses`,
            `name_history`,
            `custom_selling_price_for_items`,
            `disable_federal_taxes`,
            `disable_provincial_taxes`,
            `created`,
            `modified`
        )
        VALUES
        (
            :id,
            :name,
            :contact_name,
            :street1,
            :street2,
            :city,
            :province,
            :postal_code,
            :country,
            :phone_number_1,
            :phone_number_2,
            :fax,
            :email_id,
            :additional_email_addresses,
            :client_since,
            :disable_credit_transactions,
            :is_default_shipping_address,
            :default_receipt_payment_method,
            :default_payment_method,
            :standard_discount,
            :standard_profit_margins,
            :early_payment_discount,
            :early_payment_paid_within_days,
            :net_amount_due_within_days,
            :produce_statement_for_client,
            :memo,
            :additional_information,
            :is_inactive,
            :category,
            :credit_limit,
            :amount_owing,
            :shipping_addresses,
            :name_history,
            :custom_selling_price_for_items,
            :disable_federal_taxes,
            :disable_provincial_taxes,
            :created,
            :modified
        );
        EOS;

        try {
            $db -> beginTransaction();

            foreach($clients as $client) {
                $statement = $db -> prepare($query);
                $values = self::add_colon_before_keyname($client);
                $statement -> execute($values);
                if($db -> lastInsertId() === false) throw new Exception('UNABLE TO INSERT');
            }
            assert_success();
            $db -> commit();
        }
        catch(Exception $e) {
            $db -> rollBack();
            print_r($e -> getMessage());
        }
    }
}
?>
