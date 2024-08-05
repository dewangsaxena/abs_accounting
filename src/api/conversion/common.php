<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";

class Common {
    const STORES = [
        StoreDetails::EDMONTON => 0,
        StoreDetails::CALGARY => 0,
        StoreDetails::NISKU => 0,
        StoreDetails::SLAVE_LAKE => 0,
        StoreDetails::DELTA => 0,
        StoreDetails::VANCOUVER => 0,
    ];
    const STORE_KEYS = [
        StoreDetails::EDMONTON, 
        StoreDetails::CALGARY, 
        StoreDetails::NISKU, 
        StoreDetails::SLAVE_LAKE, 
        StoreDetails::DELTA, 
        StoreDetails::VANCOUVER
    ];
    const JSON_FLAG = JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR;

    protected static function add_colon_before_keyname(array $data) : array {
        $new_data = [];
        $keys = array_keys($data);
        foreach($keys as $key) {
            $new_data[":$key"] = $data[$key];
        }
        return $new_data;
    }

    protected static function prepare_shipping_address(array|null $old_shipping_address, string $name, string $contact_name): array {
        if(is_array($old_shipping_address)) {
            $old_shipping_address = $old_shipping_address[0];
            $new_shipping_address = [[
                'name' => $old_shipping_address['name'] ?? '',
                'contactName' => $old_shipping_address['contact'] ?? '',
                'street1' => $old_shipping_address['street1'] ?? '',
                'street2' => $old_shipping_address['street2'] ?? '',
                'city' => $old_shipping_address['city'] ?? '',
                'postalCode' => $old_shipping_address['postal_code'] ?? '',
                'province' => $old_shipping_address['province'] ?? '',
                'phoneNumber1' => $old_shipping_address['phone_1'] ?? '',
                'country' => $old_shipping_address['country'] ?? '',
            ]];
        }
        else $new_shipping_address = [[
            'name' => $name,
            'contactName' => $contact_name,
            'street1' => '',
            'street2' => '',
            'city' =>  '',
            'postalCode' =>  '',
            'province' =>  '',
            'phoneNumber1' =>  '',
            'country' =>  '',
        ]];
        return $new_shipping_address;
    }

    protected static function set_id_as_index(array $_txn): array {
        $txns = [];
        foreach($_txn as $txn) {
            $txns[$txn['id']] = $txn;
        }
        return $txns;
    }
}

?>