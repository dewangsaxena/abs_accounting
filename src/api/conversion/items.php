<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/conversion/common.php";

class Items extends Common {

    public static function format(array $old) {
        $new = [];

        $new['id'] = $old['id'];
        $new['code'] = $old['code'];
        $new['identifier'] = $old['item_identifier'];
        $new['description'] = $old['_description'];
        $new['oem'] = $old['_oem'];
        $new['category'] = $old['category'];
        $new['unit'] = 'Each';

        // Prices
        $regular_prices = json_decode($old['regular_price'], true, self::JSON_FLAG);
        $preferred_price = json_decode($old['preferred_price'], true, self::JSON_FLAG);
        $web_price = json_decode($old['web_price'], true, self::JSON_FLAG);
        $prices = [];

        foreach(self::STORE_KEYS as $store) {
            if(isset($regular_prices[$store])) {
                $prices[$store] = [
                    'storeId' => $store,
                    'sellingPrice' => $regular_prices[$store],
                    'preferredPrice' => $preferred_price[$store],
                    'buyingCost' => $web_price[$store],
                ];
            }
        }
        $new['prices'] = json_encode($prices, self::JSON_FLAG);

        $new['account_assets'] = $old['acc_assets'];
        $new['account_revenue'] = $old['acc_revenue'];
        $new['account_cogs'] = $old['acc_cogs'];
        $new['account_variance'] = $old['acc_variance'];
        $new['account_expense'] = $old['acc_expense'];
        $old_inactive = json_decode($old['is_inactive'], true, self::JSON_FLAG);
        $new_inactive = [];
        $keys = array_keys($old_inactive);
        foreach($keys as $key) $new_inactive[$key] = $old_inactive[$key] ^ 1;
        $new['is_inactive'] = json_encode($new_inactive, self::JSON_FLAG);
        $new['is_core'] = $old['is_core'];
        $new['memo'] = $old['memo'] ?? '';
        $new['additional_information'] = $old['additional_information'] ?? '';

        // Reorder Quantity
        $reorder_quantity = json_decode($old['reorder_quantity'], true, self::JSON_FLAG);
        $keys = array_keys($reorder_quantity);
        foreach($keys as $key) {
            if(is_numeric($reorder_quantity[$key]) === false) $reorder_quantity[$key] = 0;
        }

        $new['images'] = '{}';
        $new['reorder_quantity'] = json_encode($reorder_quantity, self::JSON_FLAG);
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];

        return $new;
    }
    public static function read(int $from, ?int $till=null): array {
        $db = get_old_db_instance();

        $query = 'SELECT * FROM items WHERE id >= :_from '. (is_numeric($till) ? ' AND id <= :_till': '').';';
        $statement = $db -> prepare($query);
        $values = [':_from' => $from];
        if(is_numeric($till)) $values[':_till'] = $till;
        $statement -> execute($values);

        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach($records as $r) {
            $items[]= self::format($r);
        }
        return $items;
    }

    public static function write(array $records) : void {
        $db = get_db_instance();

        $query = <<<'EOS'
        INSERT INTO items 
        (
            `id`,
            `code`,
            `identifier`,
            `description`,
            `oem`,
            `category`,
            `unit`,
            `prices`,
            `account_assets`,
            `account_revenue`,
            `account_cogs`,
            `account_variance`,
            `account_expense`,
            `is_inactive`,
            `is_core`,
            `memo`,
            `additional_information`,
            `reorder_quantity`,
            `images`,
            `created`,
            `modified`
        )
        VALUES
        (
            :id,
            :code,
            :identifier,
            :description,
            :oem,
            :category,
            :unit,
            :prices,
            :account_assets,
            :account_revenue,
            :account_cogs,
            :account_variance,
            :account_expense,
            :is_inactive,
            :is_core,
            :memo,
            :additional_information,
            :reorder_quantity,
            :images,
            :created,
            :modified
        );
        EOS;

        try {
            $db -> beginTransaction();

            foreach($records as $record) {
                $statement = $db -> prepare($query);
                $values = self::add_colon_before_keyname($record);
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

class InventoryT extends Common {

    private static function format(array $old): array {
        $new = [];
        $new['id'] = $old['id'];
        $new['item_id'] = $old['item_id'];
        $new['quantity'] = $old['qty_on_hand'];
        $new['store_id'] = $old['_location'];
        $new['aisle'] = $old['aisle'];
        $new['shelf'] = $old['shelf'];
        $new['column'] = $old['_column'];
        $new['created'] = $old['created'];
        $new['modified'] = $old['modified'];
        return $new;
    }

    /**
     * Read
     */
    public static function read(int $from, ?int $till) : array {
        $db = get_old_db_instance();
        $query = 'SELECT * FROM inventory WHERE id >= :_from ';
        $values = [':_from' => $from];
        if(is_numeric($till)) {
            $query .= ' AND id <= :_till;';
            $values[':_till'] = $till;
        }

        $statement = $db -> prepare($query);
        $statement -> execute($values);
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $inventory = [];
        foreach($records as $record) {
            $inventory[]= self::format($record);
        }

        return $inventory;
    }

    public static function write(array $records): void {

        $db = get_db_instance();

        $query = <<<'EOS'
        INSERT INTO inventory 
        (
            `id`,
            `item_id`,
            `quantity`,
            `store_id`,
            `aisle`,
            `shelf`,
            `column`,
            `created`,
            `modified`
        )
        VALUES
        (
            :id,
            :item_id,
            :quantity,
            :store_id,
            :aisle,
            :shelf,
            :column,
            :created,
            :modified
        );
        EOS;
        try {
            $db -> beginTransaction();
            $statement = $db -> prepare($query);
            foreach($records as $record) {
                $statement -> execute(self::add_colon_before_keyname($record));
                if($db -> lastInsertId() === false) throw new Exception('UNABLE TO INSERT');
            }
            assert_success();
            $db -> commit();
        }
        catch(Exception $e) {
            print_r($e -> getMessage());
            $db -> rollBack();
        }
    }
}

?>