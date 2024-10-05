<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory_v2.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";

// Inventory::generate_inventory_list(StoreDetails::EDMONTON);

// Inventory::fetch_low_stock(StoreDetails::EDMONTON);
function generate_list(int $store_id) {
    $db = get_db_instance();
    $query = <<<'EOS'
    SELECT 
        i.`identifier`,
        i.`prices`,
        inv.`quantity`
    FROM 
        items AS i
    LEFT JOIN 
        inventory AS inv
    ON 
        i.id = inv.item_id
    WHERE 
        inv.`store_id` = :store_id;
    EOS;

    $statement = $db -> prepare($query);
    $statement -> execute([':store_id' => $store_id]);
    $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $store_name = StoreDetails::STORE_DETAILS[$store_id]['name'];
    $code = <<<EOS
    <html>
    <head></head>
    <body>
    <style>
    table {
        border-spacing: 20px 0;
    }
    </style>
    <h1>List for $store_name</h1>
    <table cellspacing="pixels">
    <thead>
    <th align="left">Item Identifier</th>
    <th align="center">Quantity</th>
    <th > Price / Item</th>
    <th align="right">Inventory Value</th>
    </thead>
    EOS;
    $total_value = 0;
    foreach($result as $r) {
        $identifier = $r['identifier'];
        $quantity = $r['quantity'];
        $prices = json_decode($r['prices'], true, flags: JSON_NUMERIC_CHECK| JSON_THROW_ON_ERROR);
        $price = $prices[$store_id]['buyingCost'] ?? 0;
        if($quantity < 1 || (($price > 0) === false)) continue;
        $value = $price * $quantity;
        $total_value += $value;
        $value_txt = Utils::number_format($value, 2);

        $price_txn = Utils::number_format($price, 2);
        $code .= <<<EOS
        <tr>
        <td align="left">$identifier</td>
        <td align="center">$quantity</td>
        <td ><label style="letter-spacing: 2px;">\$ $price_txn</label></td>
        <td align="right"><label style="letter-spacing: 2px;">\$ $value_txt</label></td>
        </tr>
        EOS;
    }

    $total_value = Utils::number_format($total_value, 200);
    $code .= "</table><br><br>Total Inventory Value: &nbsp;&nbsp;&nbsp;&nbsp;<label style='letter-spacing: 2px;font-weight:bold;'>\$ $total_value</label>";

    echo $code;
}

function fetch_inventory(int $store_id): void {
    $db = get_db_instance();
    $query = <<<'EOS'
    SELECT 
        i.id,
        i.`identifier`,
        i.`prices`,
        inv.`quantity`
    FROM 
        items AS i
    LEFT JOIN 
        inventory AS inv
    ON 
        i.id = inv.item_id
    WHERE 
        inv.`store_id` = :store_id;
    EOS;

    $statement = $db -> prepare($query);
    $statement -> execute([':store_id' => $store_id]);
    $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $store_name = StoreDetails::STORE_DETAILS[$store_id]['name'];
    $code = <<<EOS
    <html>
    <head></head>
    <body>
    <style>
    table {
        border-spacing: 20px 0;
    }
    </style>
    <h1>List for $store_name</h1>
    <table cellspacing="pixels">
    <thead>
    <th align="left">Item Identifier</th>
    <th align="center">Quantity</th>
    <th > Price / Item</th>
    <th align="right">Inventory Value</th>
    </thead>
    EOS;

    $total_value = 0;
    $parts = [];
    foreach($result as $r) {
        $id = $r['id'];
        $identifier = $r['identifier'];
        $quantity = $r['quantity'];
        $prices = json_decode($r['prices'], true, flags: JSON_NUMERIC_CHECK| JSON_THROW_ON_ERROR);
        $price = $prices[$store_id]['buyingCost'] ?? 0;
        if($quantity < 1 || (($price > 0) === false)) continue;
        $value = $price * $quantity;
        $total_value += $value;
        $value_txt = Utils::number_format($value, 2);
        $price_txn = Utils::number_format($price, 2);

        if(!isset($parts[$id])) {
            $parts[$id] = [
                'identifier' => $identifier,
                'quantity' => $quantity,
                'price' => Utils::round($price, 2),
            ];
        }
    }

    $no_of_items = count($parts);
    $items = array_keys($parts);

    // Deduct 
    $amount_to_deduct = 303538.76;
    while($amount_to_deduct > 50) {
        $index = rand(0, $no_of_items - 1);

        $selected_item = $items[$index];

        $quantity = $parts[$selected_item]['quantity'];
        if($quantity > 1) {
            $price = $parts[$selected_item]['price'];
            if($price > 0 && ($amount_to_deduct - $price > 0)) {
                $parts[$selected_item]['quantity'] -= 1;
                $amount_to_deduct -= $price;
            }
        }
    }

    $total_value = 0;

    foreach($parts as $part) {
        $identifier = $part['identifier'];
        $quantity = $part['quantity'];
        $price_txn = Utils::number_format($part['price'], 2);
        $value_txt = Utils::number_format($part['price'] * $part['quantity'], 2);
        $code .= <<<EOS
        <tr>
        <td align="left">$identifier</td>
        <td align="center">$quantity</td>
        <td ><label style="letter-spacing: 2px;">\$ $price_txn</label></td>
        <td align="right"><label style="letter-spacing: 2px;">\$ $value_txt</label></td>
        </tr>
        EOS;

        $total_value += Utils::round($part['price'] * $part['quantity'], 2);
    }

    $total_value = Utils::number_format($total_value, 2);
    $code .= "</table><br><br>Total Inventory Value: &nbsp;&nbsp;&nbsp;&nbsp;<label style='letter-spacing: 2px;font-weight:bold;'>\$ $total_value</label>";
    if($amount_to_deduct < 20) echo $code;
}

if(SYSTEM_INIT_MODE === PARTS) {
    $store_id = StoreDetails::REGINA;
    // generate_list($store_id);
    // fetch_inventory($store_id);die;
    // die('REGINA : '. (Correct_IS_BS_InventoryV2::correct(StoreDetails::REGINA) ? 'T' : 'F'));
}

$items = [14942,
14512, 
14528, 
14529, 
14512,
14514,
14528,
14529,
14530,
14531,
14532,
14533,
14534,
14535,
14536,
14537,
14538,
14539,
14540,
14541,
14542,
14543,
14545,
14546,
14547,
14548,
14549,
14550,
14551,
14552,
14553,
14555,
14556,
14557,
14558,
14559,
14560,
14561,
14563,
14565,
14566,
14568,
14570,
14571,
14572,
14942
];

function fetch_quantity_sold_of_items(int $item_id, PDO &$db, int $store_id): int {
    $query = <<<EOS
    SELECT `details` FROM sales_invoice WHERE store_id = $store_id AND `details` LIKE '%"itemId":$item_id,%';
    EOS;

    $quantity = 0;
    $statement = $db -> prepare($query);
    $statement -> execute();
    $details = $statement -> fetchAll(PDO::FETCH_ASSOC);
    foreach($details as $d) {
        $d = json_decode($d['details'], true, flags: JSON_NUMERIC_CHECK);
        foreach($d as $item) {
            if($item['itemId'] === $item_id) $quantity += $item['quantity'];
        }
    }

   return $quantity;
}

// $store_id = StoreDetails::EDMONTON;
// $db = get_db_instance();
// $quantity_table = [];
// foreach($items as $item_id) {
//     $quantity = fetch_quantity_sold_of_items($item_id, $db, $store_id);
//     if(isset($quantity_table[$item_id]) === false)  $quantity_table[$item_id] = 0;
//     $quantity_table[$item_id] += $quantity;
// }

function generate_table(array $quantity_table, PDO &$db, int $store_id): void {
    $query = 'SELECT id, identifier FROM items WHERE id IN (:placeholder);';
    $ret = Utils::mysql_in_placeholder_pdo_substitute(array_keys($quantity_table), $query);
    $query = $ret['query'];
    $values = $ret['values'];

    $statement = $db -> prepare($query);
    $statement -> execute($values);
    $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    foreach($result as $r) {
        $items[$r['id']] = [
            'identifier' => $r['identifier'],
            'quantity' => $quantity_table[$r['id']],
        ];
    }

    $store_name = StoreDetails::STORE_DETAILS[$store_id]['name'];
    echo "<html><body><h1>$store_name</h1><br><br><table><thead><tr><th>Identifier</th><th>Quantity</th></tr></thead><tbody>";
    foreach($items as $item) {
        echo <<<EOS
        <tr>
        <td>{$item['identifier']}</td>
        <td>{$item['quantity']}</td>
        </tr>
        EOS;
    }

    echo <<<EOS
    </tbody></table>
    EOS;
}

function format_data(array $data) : array {
    $new_data = [];
    $count = count($data);
    for($i = 1; $i < $count; ++$i) {
        $r = $data[$i];
        $t = [trim($r[0]), trim($r[1]), trim($r[2]), trim($r[3]), trim($r[4]), trim($r[5])];
        $new_data []= $t;
    }
    return $new_data;
}

function check_for_item(string &$identifier, PDOStatement &$statement_check_item) : int|null {
    $statement_check_item -> execute([':identifier' => $identifier]);
    $result = $statement_check_item -> fetchAll(PDO::FETCH_ASSOC);
    return isset($result[0]) ? $result[0]['id'] : null;
}

function update_inventory(array &$items, PDO &$db, array &$bs): void {
    $statement_check = $db -> prepare(<<<'EOS'
    SELECT 
        id
    FROM
        inventory 
    WHERE 
        id = :id
    AND
        store_id = :store_id;
    EOS);

    $statement_insert = $db -> prepare(<<<'EOS'
    INSERT INTO inventory
    (
        item_id,
        quantity,
        store_id,
        `aisle`,
        `shelf`,
        `column`
    )
    VALUES
    (
        :item_id,
        :quantity,
        :store_id,
        :aisle,
        :shelf,
        :column
    );
    EOS);

    $statement = $db -> prepare(<<<'EOS'
    UPDATE 
        inventory 
    SET 
        `quantity` = :quantity,
        `aisle` = :aisle,
        `shelf` = :shelf,
        `column` = :column
    WHERE 
        store_id = :store_id
    AND 
        `item_id` = :item_id;
    EOS);

    $total_value = 0;

    foreach($items as $item) {
        $id = $item[0];
        $identifier = $item[1];
        $quantity = is_numeric($item[2]) ? floatval($item[2]) : 0;
        $cost = is_numeric($item[3]) ? floatval($item[3]) : 0;
        $aisle = $item[4];
        $shelf =  $item[5];
        $column =  $item[6];
        
        if(is_numeric($quantity) === false) throw new Exception('Invalid Quantity for Item: '. $identifier);
        if($quantity < 0) throw new Exception('Quantity cannot be Negative for: '. $identifier);
        if(is_numeric($cost) === false) throw new Exception('Invalid Cost for Item: '. $identifier);
        if($cost < 0) throw new Exception('Cost cannot be zero for: '. $identifier);

        // Check ID 
        $statement_check -> execute([':id' => $id, ':store_id' => StoreDetails::REGINA]);
        $result = $statement_check -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($result[0]['id']) === false) {
            // Insert
            $statement_insert -> execute([
                ':item_id' => $id,
                ':quantity' => $quantity,
                ':store_id' => StoreDetails::REGINA,
                ':aisle' => $aisle,
                ':shelf' => $shelf,
                ':column' => $column,
            ]);

            $lid = $db -> lastInsertId();
            if(is_numeric($lid) === false) throw new Exception('Unable to Add Item: '. $identifier);
        } 
        else {
            $is_successful = $statement -> execute([
                ':quantity' => $quantity,
                ':aisle' => $aisle,
                ':shelf' => $shelf,
                ':column' => $column,
                ':store_id' => StoreDetails::REGINA,
                ':item_id' => $id,
            ]);

            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Update Item: '. $item[1]);
        }
        
        // Add to Total Value
        $total_value += ($quantity * $cost);
    }

    // Update Account Value
    BalanceSheetActions::update_account_value($bs, AccountsConfig::INVENTORY_A, $total_value);
}

function add_item(array &$items, PDO &$db): void {
    $statement = $db -> prepare(<<<'EOS'
    INSERT INTO items
    (
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
        `additional_informatation`,
        `reorder_quantity`,
        `images`
    )
    VALUES
    (
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
        :images
    );
    EOS);

    foreach($items as $item) {
        $params = [
            ':code' => $item[0]. '~',
            ':identifier' => $item[0],
            ':description' => '~',
            ':oem' => '',
            ':category' => CATEGORY_INVENTORY,
            ':unit' => 'Each',
            ':prices' => '{"8":0}',
            ':account_assets' => 1520,
            ':account_revenue' => 4020,
            ':account_cogs' => 5020,
            ':account_variance' => 5100,
            ':account_expense' => 5020,
            ':is_inactive' => '{"8":0}',
            ':is_core' => 0,
            ':memo' => '',
            ':aditional_information' => '',
            ':reorder_quantity' => '{"8":0}',
            ':images' => '{}',
        ];

        $statement -> execute($params);
        $p_key = $db -> lastInsertId();
        if(is_numeric($p_key) === false) throw new Exception('Unable to Add item: '. $item[0]);
    }
}

function import_data(string $filename) : void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        $data = format_data(Utils::read_csv_file(TEMP_DIR. $filename));
        $statement_check_item = $db -> prepare('SELECT `id` FROM items WHERE `identifier` = :identifier;');
        $existing_item = [];
        $new_items = [];
        foreach($data as $d) {
            $result = check_for_item($d[0], $statement_check_item);
            if(is_numeric($result)) $existing_item[]= [$result,...$d];
            else $new_items []= $d;
        }

        // Balance Sheet
        $bs = AccountsConfig::ACCOUNTS;

        // Update Inventory
        update_inventory($existing_item, $db, $bs);

        assert_success();
        
        $db -> commit();
        echo 'Successfully Imported';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();        
    }
}

import_data('file_2.csv');
?>