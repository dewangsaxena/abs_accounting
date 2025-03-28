<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory_v2.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/suppressions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/flyer.php";

// Inventory::generate_inventory_list(StoreDetails::CALGARY);die;

// Inventory::fetch_low_stock(StoreDetails::EDMONTON);
function generate_list(int $store_id, bool $do_print=true) : float {
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

    
    if($do_print) {
        $total_value = Utils::number_format($total_value, 2);
        $code .= "</table><br><br>Total Inventory Value: &nbsp;&nbsp;&nbsp;&nbsp;<label style='letter-spacing: 2px;font-weight:bold;'>\$ $total_value</label>";
        echo $code;
    }

    return $total_value;
}

// echo generate_list(StoreDetails::SLAVE_LAKE, false);die;

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
    $store_id = StoreDetails::EDMONTON;
    // generate_list($store_id);die;
    // fetch_inventory($store_id);die;
    // die(StoreDetails::STORE_DETAILS[$store_id]['name'].': '. (Correct_IS_BS_InventoryV2::correct($store_id) ? 'T' : 'F'));
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
    $statement_check_quantity = $db -> prepare(<<<'EOS'
    SELECT 
        `quantity`
    FROM
        inventory 
    WHERE 
        item_id = :item_id
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

    $statement_update = $db -> prepare(<<<'EOS'
    UPDATE 
        inventory 
    SET 
        `quantity` = `quantity` + :quantity,
        `aisle` = :aisle,
        `shelf` = :shelf,
        `column` = :column
    WHERE 
        store_id = :store_id
    AND 
        `item_id` = :item_id;
    EOS);

    $statement_fetch_prices = $db -> prepare('SELECT prices FROM items WHERE id = :id;');
    $statement_update_prices = $db -> prepare('UPDATE items SET prices = :prices, modified = CURRENT_TIMESTAMP WHERE id = :id');

    $total_value = 0;

    foreach($items as $item) {
        $id = $item[0];
        $identifier = $item[1];
        $quantity = is_numeric($item[2]) ? floatval($item[2]) : 0;
        $cost = is_numeric($item[3]) ? floatval($item[3]) : 0;
        $aisle = $item[4];
        $shelf =  $item[5];
        $column = $item[6];

        if(is_numeric($quantity) === false) throw new Exception('Invalid Quantity for Item: '. $identifier);
        if($quantity < 0) throw new Exception('Quantity cannot be Negative for: '. $identifier);
        if(is_numeric($cost) === false) throw new Exception('Invalid Cost for Item: '. $identifier);
        if($cost < 0) throw new Exception('Cost cannot be zero for: '. $identifier);

        // Check whether inventory entry exists
        $statement_check_quantity -> execute([':item_id' => $id, ':store_id' => StoreDetails::REGINA]);
        $result = $statement_check_quantity -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($result[0]['quantity']) === false) {
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

            $existing_quantity = 0 ;
        } 
        else {
            $is_successful = $statement_update -> execute([
                ':quantity' => $quantity,
                ':aisle' => $aisle,
                ':shelf' => $shelf,
                ':column' => $column,
                ':store_id' => StoreDetails::REGINA,
                ':item_id' => $id,
            ]);

            if($is_successful !== true) throw new Exception('Unable to Update Item: '. $item[1]);

            // Existing Quantity
            $existing_quantity = $result[0]['quantity']; 
        }
        
        // Value of Items 
        $new_value = $cost * $quantity;

        // Add to Total Value
        $total_value += $new_value;

        // Fetch Existing Prices
        $statement_fetch_prices -> execute([':id' => $id]);
        $prices = $statement_fetch_prices -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($prices[0])) {
            $prices = json_decode($prices[0]['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if(isset($prices[StoreDetails::REGINA]) === false) {
                $prices[StoreDetails::REGINA] = [
                    'storeId' => StoreDetails::REGINA, 
                    'buyingCost' => 0, 
                    'sellingPrice' => 0, 
                    'preferredPrice' => 0
                ];
            }
        }
        else $prices = [
            StoreDetails::REGINA => [
                'storeId' => StoreDetails::REGINA, 
                'buyingCost' => 0, 
                'sellingPrice' => 0, 
                'preferredPrice' => 0
            ]
        ];

        // Existing Quantity
        $existing_cost = $prices[StoreDetails::REGINA]['buyingCost'];
        $existing_value = $existing_quantity * $existing_cost;
        
        // Total Value of combined items
        $combined_value = $existing_value + $new_value;
        $combined_quantity = $existing_quantity + $quantity;

        if($combined_quantity <= 0) throw new Exception('Invalid Combined Quantity for: '. $identifier);

        $new_cost = Utils::round($combined_value / $combined_quantity);

        // Update Prices
        $prices[StoreDetails::REGINA]['buyingCost'] = $new_cost;

        // Prices
        $prices = json_encode($prices, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Update Prices
        $is_successful = $statement_update_prices -> execute([':prices' => $prices, ':id' => $id]);
        if($is_successful !== true) throw new Exception('Unable to Update Prices for item: '. $identifier);
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
        `additional_information`,
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

    $unique_items = [];
    $duplicate_items = [];

    foreach($items as $item) {
        if(in_array($item[0], $unique_items) === false) $unique_items[]= $item[0];
        else $duplicate_items[]= $item[0];
    }

    if(count($duplicate_items) > 0) {
        echo 'Duplicate Items Found:<br><br>';
        foreach($duplicate_items as $item) {
            echo $item.'<br>';
        }
        throw new Exception('');
    }

    foreach($items as $item) {
        $params = [
            ':code' => "{$item[0]} ~",
            ':identifier' => $item[0],
            ':description' => '~',
            ':oem' => '',
            ':category' => CATEGORY_INVENTORY,
            ':unit' => 'Each',
            ':prices' => '{"8":{"storeId":8,"sellingPrice":0,"buyingCost":0,"preferredPrice":0}}',
            ':account_assets' => 1520,
            ':account_revenue' => 4020,
            ':account_cogs' => 5020,
            ':account_variance' => 5100,
            ':account_expense' => 5020,
            ':is_inactive' => '{"8":0}',
            ':is_core' => 0,
            ':memo' => '',
            ':additional_information' => '',
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

        // Add Item
        add_item($new_items, $db);

        // Combine Items 
        $combined_items = [];
        foreach($data as $d) {
            $result = check_for_item($d[0], $statement_check_item);
            $combined_items[]= [$result,...$d];
        }

        // Update Inventory
        update_inventory($combined_items, $db, $bs);

        // Update Balance Sheet
        BalanceSheetActions::update_from($bs, Utils::get_business_date(StoreDetails::REGINA), StoreDetails::REGINA, $db);

        assert_success();
        
        $db -> commit();
        echo 'Successfully Imported';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();        
    }
}

function extract_report(int $store_id) : void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        $parts = [
            'DON' => [],
            'GRO' => [],
            'SHE' => [],
            'GAT' => [],
            'HOL' => [],
            'SKF' => [],
            'TSE' => [],
            'STM' => [],
            'MID' => [],
        ];

        // Substring
        $keys = array_keys($parts);

        $all_identifiers_by_id = [];

        // Fetch all items with SUBSTRINGS
        $item_ids = [];
        $statement_fetch_items = $db -> prepare('SELECT id, `identifier` FROM items WHERE `identifier` LIKE :substring;');
        foreach($keys as $key) {
            $statement_fetch_items -> execute([':substring' => "$key%"]);
            $items = $statement_fetch_items -> fetchAll(PDO::FETCH_ASSOC);
            foreach($items as $item) {
                $item_ids []= $item['id'];
                $all_identifiers_by_id[$item['id']] = strtoupper($item['identifier']);
            }
        }

        // Sales Invoice
        $statement = $db -> prepare('SELECT id, `details` FROM sales_invoice WHERE store_id = :store_id AND `date` >= "2024-01-01;"');
        $statement -> execute([':store_id' => $store_id]);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
        foreach($results as $result) {
            $items = json_decode($result['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($items as $item) {
                $item_id = $item['itemId'];
                if(in_array($item_id, $item_ids) === false) continue;
                $quantity = $item['quantity'];

                // Latest identifier
                $identifier = $all_identifiers_by_id[$item_id];
                foreach($keys as $key) {
                    if(str_starts_with($identifier, $key)) {
                        if(isset($parts[$key][$identifier]) === false) {
                            $parts[$key][$identifier] = ['sold' => 0, 'inventory' => 0];
                        }
                        $parts[$key][$identifier]['sold'] += $quantity;
                    }
                }
            }
        }

        // Adjust for Sales Return
        $statement = $db -> prepare('SELECT `details` FROM sales_return WHERE store_id = :store_id AND `date` >= "2024-01-01;"');
        $statement -> execute([':store_id' => $store_id]);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);
        foreach($results as $result) {
            $items = json_decode($result['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($items as $item) {
                $item_id = $item['itemId'];
                if(in_array($item_id, $item_ids) === false) continue;

                if(isset($item['returnQuantity']) === false) continue;
                $quantity = $item['returnQuantity'];

                // Latest identifier
                $identifier = $all_identifiers_by_id[$item_id];
                foreach($keys as $key) {
                    if(str_starts_with($identifier, $key)) {
                        if(isset($parts[$key][$identifier]) === false) {
                            $parts[$key][$identifier] = ['sold' => 0, 'inventory' => 0];
                        }
                        $parts[$key][$identifier]['sold'] -= $quantity;
                    }
                }
            }
        }

        // Fetch Quantities of items
        $query = <<<EOS
        SELECT 
            i.`identifier`,
            inv.`quantity`
        FROM 
            items AS i
        LEFT JOIN 
            inventory AS inv
        ON 
            i.id = inv.item_id
        WHERE 
            i.id IN (:placeholder)
        AND 
            inv.store_id = $store_id;
        EOS;
        $result = Utils::mysql_in_placeholder_pdo_substitute($item_ids, $query);
        $query = $result['query'];
        $values = $result['values'];
        $statement_fetch_quantity = $db -> prepare($query);
        $statement_fetch_quantity -> execute($values);
        $results = $statement_fetch_quantity -> fetchAll(PDO::FETCH_ASSOC);
        foreach($results as $result) {
            $identifier = strtoupper($result['identifier']);
            $substr = substr($identifier, 0, 3);
            $quantity = $result['quantity'];
            if(isset($parts[$substr][$identifier]) === false) {
                $parts[$substr][$identifier] = ['sold' => 0, 'inventory' => 0];
            }
            $parts[$substr][$identifier]['inventory'] += $quantity;
        }

        $handle = fopen('delta.csv', 'w');
        fputcsv($handle, ['Identifier', 'Sold', 'Inventory Stock']);
        foreach($keys as $key) {
            $identifiers = array_keys($parts[$key]);
            foreach($identifiers as $identifier) {
                fputcsv($handle, [$identifier, $parts[$key][$identifier]['sold'], $parts[$key][$identifier]['inventory']]);
            };
        }

        fclose($handle);
    }
    catch(Exception $e) {
        echo $e -> getMessage();
        $db -> rollBack();
    }
}
// extract_report(StoreDetails::DELTA);

function wash_report(string $start_date, string $end_date): void {
    $db = get_db_instance();

    // Fetch Transactions
    $statement = $db -> prepare(<<<'EOS'
    SELECT * FROM (
        SELECT 
            1 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            sales_invoice
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            payment_method = 0
        AND 
            store_id = :store_id
        UNION ALL
        SELECT 
            2 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            sales_return
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            payment_method = 0
        AND 
            store_id = :store_id
        UNION ALL
        SELECT 
            3 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            credit_note
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            store_id = :store_id
        UNION ALL
        SELECT 
            4 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            debit_note
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            store_id = :store_id
        UNION ALL 
        SELECT 
            6 AS `type`,
            id,
            `date`,
            client_id,
            sum_total
        FROM 
            receipt
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            store_id = :store_id
        AND 
            do_conceal = 0
    ) AS _tmp 
    ORDER BY 
        `date` ASC,
        `type` ASC;
    EOS);
    $statement -> execute([':start_date' => $start_date, ':end_date' => $end_date, ':store_id' => StoreDetails::NISKU]);
    $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

    // Clients 
    $clients = [];

    $transactions = [];
    foreach($records as $r) {
        $txn_date = $r['date'];
        $client_id = $r['client_id'];
        $type = $r['type'];
        $sum_total = $r['sum_total'];
        $debits = 0;
        $credits = 0;
        $type_abbr = TRANSACTION_NAMES_ABBR[$type];

        // Check for Presence of client 
        if(isset($clients[$client_id]) === false) $clients[$client_id] = null;

        if($type === SALES_INVOICE || $type === DEBIT_NOTE) $debits = $sum_total;
        else if($type === SALES_RETURN || $type === CREDIT_NOTE || $type === RECEIPT) $credits = $sum_total;

        // Transaction Date
        $transactions[] = [
            'date' => $txn_date,
            'client_id' => $client_id,
            'source' => "$type_abbr-{$r['id']}",
            'trans_no' => '',
            'debits' => $debits,
            'credits' => $credits,
        ];
    }

    // Fetch Client Name
    $query = <<<'EOS'
    SELECT 
        id,
        `name`
    FROM 
        clients
    WHERE 
        id IN (:placeholder);
    EOS;
    $temp = Utils::mysql_in_placeholder_pdo_substitute(array_keys($clients), $query);
    $query = $temp['query'];
    $client_ids = $temp['values'];
    $statement = $db -> prepare($query);
    $statement -> execute($client_ids);
    $client_details = $statement -> fetchAll(PDO::FETCH_ASSOC);
    foreach($client_details as $client_detail) {
        $clients[$client_detail['id']] = $client_detail['name'];
    }

    // Initial Balance
    $accounts_receivables = 0;

    // Write to CSV
    $report_handle = fopen('report.csv', 'w+');

    // Put Header
    fputcsv($report_handle, ['Date', 'Client Name', 'Source', 'Debits', 'Credits', 'Balance']);
    fputcsv($report_handle, ['~', 'Accounts Receivables', '~', '~', '~', $accounts_receivables]);

    foreach($transactions as $transaction) {

        // Adjust Balance
        if($transaction['debits'] > 0) $accounts_receivables += $transaction['debits'];
        else if($transaction['credits'] > 0) $accounts_receivables -= $transaction['credits'];

        // Round off
        $accounts_receivables = Utils::round($accounts_receivables);

        // Prepare Data
        $data = [
            date_format(date_create($transaction['date']), 'd M, Y'), 
            $clients[$transaction['client_id']],
            $transaction['source'],
            $transaction['debits'],
            $transaction['credits'],
            $accounts_receivables,
        ];

        // Write to Disk
        fputcsv($report_handle, $data);
    }

    // Close handle
    fclose($report_handle);

    echo 'Created Report.';
}

// wash_report('2022-01-01', '2024-10-31');

function wash_report_2(string $start_date, string $end_date, array $payment_method): void {
    $db = get_db_instance();

    // Fetch Transactions
    $query = <<<'EOS'
    SELECT * FROM (
        SELECT 
            1 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            sales_invoice
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            payment_method IN (:placeholder)
        AND 
            store_id = :store_id
        UNION ALL
        SELECT 
            2 AS `type`,
            id,
            `date`, 
            client_id, 
            sum_total 
        FROM 
            sales_return
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            payment_method IN (:placeholder)
        AND 
            store_id = :store_id
        UNION ALL
        SELECT 
            6 AS `type`,
            id,
            `date`,
            client_id,
            sum_total
        FROM 
            receipt
        WHERE 
            `date` >= :start_date 
        AND 
            `date` <= :end_date
        AND 
            store_id = :store_id
        AND 
            do_conceal = 0
        AND 
            payment_method IN (:placeholder)
    ) AS _tmp 
    ORDER BY 
        `date` ASC,
        `type` ASC;
    EOS;
    $result = Utils::mysql_in_placeholder_pdo_substitute($payment_method, $query);
    $query = $result['query'];
    $values = $result['values'];
    $statement = $db -> prepare($query);
    $statement -> execute([
        ':start_date' => $start_date, 
        ':end_date' => $end_date, 
        ':store_id' => StoreDetails::NISKU,
        ...$values
    ]);
    $records = $statement -> fetchAll(PDO::FETCH_ASSOC);

    // Clients 
    $clients = [];

    $transactions = [];
    foreach($records as $r) {
        $txn_date = $r['date'];
        $client_id = $r['client_id'];
        $type = $r['type'];
        $sum_total = $r['sum_total'];
        $debits = 0;
        $credits = 0;
        $type_abbr = TRANSACTION_NAMES_ABBR[$type];

        // Check for Presence of client 
        if(isset($clients[$client_id]) === false) $clients[$client_id] = null;

        if($type === SALES_INVOICE) {
            $debits = $sum_total;
            $credits = 0;
        }
        else if($type === SALES_RETURN) {
            $debits = 0;
            $credits = -$sum_total;
        }
        else if($type === RECEIPT) {
            $debits = $sum_total;
            $credits = 0;
        }

        // Transaction
        $transactions[] = [
            'date' => $txn_date,
            'client_id' => $client_id,
            'source' => "$type_abbr-{$r['id']}",
            'trans_no' => '',
            'debits' => $debits,
            'credits' => $credits,
        ];

        if($type === SALES_INVOICE) {
            $debits = 0;
            $credits = $sum_total;
        }
        else if($type === SALES_RETURN) {
            $debits = -$sum_total;
            $credits = 0;
        }
        else if($type === RECEIPT) {
            $debits = 0;
            $credits = $sum_total;
        }

        $transactions[] = [
            'date' => $txn_date,
            'client_id' => $client_id,
            'source' => "$type_abbr-{$r['id']}",
            'trans_no' => '',
            'debits' => $debits,
            'credits' => $credits,
        ];
    }

    // Fetch Client Name
    $query = <<<'EOS'
    SELECT 
        id,
        `name`
    FROM 
        clients
    WHERE 
        id IN (:placeholder);
    EOS;
    $temp = Utils::mysql_in_placeholder_pdo_substitute(array_keys($clients), $query);
    $query = $temp['query'];
    $client_ids = $temp['values'];
    $statement = $db -> prepare($query);
    $statement -> execute($client_ids);
    $client_details = $statement -> fetchAll(PDO::FETCH_ASSOC);
    foreach($client_details as $client_detail) {
        $clients[$client_detail['id']] = $client_detail['name'];
    }

    // Balance
    $balance = 0;

    // Write to CSV
    $report_handle = fopen('debit.csv', 'w+');

    // Put Header
    fputcsv($report_handle, ['Date', 'Client Name', 'Source', 'Debits', 'Credits', 'Balance']);
    fputcsv($report_handle, ['~', 'Payments', '~', '~', '~', $balance]);

    foreach($transactions as $transaction) {

        // Adjust Balance
        if($transaction['debits'] > 0) $balance += $transaction['debits'];
        else if($transaction['credits'] > 0) $balance -= $transaction['credits'];
        else if($transaction['debits'] < 0) $balance -= $transaction['debits'];
        else if($transaction['credits'] < 0) $balance += $transaction['credits'];

        // Round off
        $accounts_receivables = Utils::round($balance);

        // Prepare Data
        $data = [
            date_format(date_create($transaction['date']), 'd M, Y'), 
            $clients[$transaction['client_id']],
            $transaction['source'],
            $transaction['debits'],
            $transaction['credits'],
            $accounts_receivables,
        ];

        // Write to Disk
        fputcsv($report_handle, $data);
    }

    // Close handle
    fclose($report_handle);

    echo 'Created Report.';
}

// wash_report_2('2022-01-01', '2024-10-31', [PaymentMethod::DEBIT, PaymentMethod::CASH]);
// hello

function generate_line_code_file(int $store_id): void {
    $db = get_db_instance();

    $query = <<<'EOS'
    SELECT
        i.`identifier`,
        i.`description`,
        inv.`aisle`,
        inv.`shelf`,
        inv.`column`
    FROM items AS i
    LEFT JOIN 
        inventory AS inv
    ON 
        i.id = inv.item_id 
    WHERE 
        inv.`store_id` = :store_id
    AND
        inv.`quantity` > 0; 
    EOS;

    $statement = $db -> prepare($query);
    $statement -> execute([':store_id' => $store_id]);
    $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

    $store_name = str_replace(' ', '_', strtolower(StoreDetails::STORE_DETAILS[$store_id]['name'].'.csv'));
    $file_handle = fopen($store_name, 'w+');

    fputcsv($file_handle, ['Item', 'Description', 'Line Code', 'Aisle', 'Shelf', 'Column']);

    foreach($results as $r) {
        fputcsv($file_handle, [
            $r['identifier'], 
            $r['description'],
            '',
            $r['aisle'],
            $r['shelf'],
            $r['column'],
        ]);    
    }

    fclose($file_handle);
}

// generate_line_code_file(StoreDetails::CALGARY);

function merge_csv_file(): array {
    $filenames = ['calgary.csv', 'delta.csv', 'nisku.csv', 'regina.csv'];

    $combined_data = [];

    // Read 
    foreach($filenames as $file) {
        $data = Utils::read_csv_file($file);
        $combined_data = array_merge($combined_data, $data);
    }

    return $combined_data;
}

function format_data1 (array $data): array {
    $items = [];
    foreach($data as $item) {
        $identifier = $item[0];

        // Line Code
        if(isset($item[2])) $line_code = trim($item[2]);
        else $line_code = trim($item[1]);
        
        // Check for Valid Line Code
        if(strlen($line_code) === 0) continue;

        // Check for Presence 
        if(isset($items[$identifier]) === false) $items[$identifier] = [];

        // Check for presence of same line code 
        if(count($items[$identifier]) > 0 && $items[$identifier][0] === $line_code) continue;

        /* First time adding or descripancy in line code detected */
        else $items[$identifier] []= $line_code;
    }

    return $items;
}

function validate_data(array $data, array &$identifiers): void {
    $multiple_codes_for_items = [];
    foreach($identifiers as $k) {
        $d = $data[$k];
        if(count($d) > 1) {
            if(isset($multiple_codes_for_items[$k]) === false) $multiple_codes_for_items[$k] = [];
            $multiple_codes_for_items[$k]= $d;
        }
    }

    if(count($multiple_codes_for_items) > 0) {
        $error_file_handle = fopen('error_identifiers.csv', 'w+');
        $keys = array_keys($multiple_codes_for_items);
        foreach($keys as $k) {
            $error_items = implode(', ', $multiple_codes_for_items[$k]);
            echo "$k: ~~> $error_items<br>";
            fputcsv($error_file_handle, [$k, ...$multiple_codes_for_items[$k]]);
        }
        fclose($error_file_handle);
        throw new Exception('<br><br>VALIDATION FAILED.');
    }
}

function update_item_identifier(string $identifier, string $line_code, PDOStatement &$statement_update, PDOStatement &$statement_fetch): void {

    // Trim
    $identifier = trim($identifier);

    // Find Description
    $statement_fetch -> execute([':identifier' => $identifier]);
    $result = $statement_fetch -> fetchAll(PDO::FETCH_ASSOC);
    if(isset($result[0]['description']) === false) throw new Exception('Unable to Find Identifier: '. $identifier);

    // Description
    $description = $result[0]['description'];

    $new_identifier = trim("$line_code$identifier");
    $is_successful = $statement_update -> execute([
        ':new_code' => "$new_identifier $description",
        ':new_identifier' => $new_identifier,
        ':old_identifier' => $identifier,
    ]);

    if($is_successful !== true || $statement_update -> rowCount() < 1) throw new Exception('Unable to Update Identifier: '. $identifier);
}

function update_with_fixed_items(array &$data): void {

    // Filename
    $filename = 'fixed.csv';

    // Read file
    if(file_exists($filename)) {
        $fixed_data = Utils::read_csv_file($filename, 'r');
        $formatted_data = format_data1($fixed_data);
        $keys = array_keys($formatted_data);
        foreach($keys as $f) {
            $data[$f] = [$formatted_data[$f][0]];
        }
    }
}

function process_line_codes(): void {
    $db = get_db_instance();
    try {
        $data = format_data1(merge_csv_file());
        $identifiers = array_keys($data);

        // Perform fix
        update_with_fixed_items($data);

        // Validate 
        validate_data($data, $identifiers);

        // Update DB
        $db -> beginTransaction();

        // Statement Fetch
        $statement_fetch = $db -> prepare('SELECT `description` FROM items WHERE `identifier` = :identifier LIMIT 1;');

        // Prepare Statement
        $statement_update = $db -> prepare(<<<'EOS'
        UPDATE 
            `items`
        SET 
            `code` = :new_code,
            `identifier` = :new_identifier
        WHERE
            `identifier` = :old_identifier;
        EOS);

        foreach($identifiers as $identifier) {
            update_item_identifier($identifier, $data[$identifier][0], $statement_update, $statement_fetch);
        }
        if($db -> inTransaction()) $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        if($db -> inTransaction()) $db -> rollBack();
        print_r($e -> getMessage());
    }
}

function fetch_item_details_by_code(string $code, int $store_id): void {
    $db = get_db_instance();
    $query = <<<'EOS'
    SELECT 
        it.id, 
        it.identifier,
        it.description,
        it.prices,
        inv.quantity
    FROM 
        items AS it
    LEFT JOIN 
        inventory AS inv 
    ON 
        it.id = inv.item_id
    WHERE 
        it.identifier LIKE :item_code
    AND
        inv.quantity > 0
    AND 
        inv.store_id = :store_id;
    EOS;

    $statement = $db -> prepare($query);
    $statement -> execute([
        ':item_code' => "$code%",
        ':store_id' => $store_id,
    ]);

    $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

    echo <<<'EOS'
    <html>
    <body>
        <table>
    <tr>
        <th>Identifier</th>
        <th>Description</th>
        <th>Quantity</th>
        <th colspan="2">Price</th>
        <th></th>
        <th colspan="2">Value</th>
        <th></th>
    </tr>
    EOS;
    $total_value = 0;
    foreach($results as $r) {
        $prices = json_decode($r['prices'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $buying_cost = Utils::round($prices[$store_id]['buyingCost'] ?? 0, 2);
        $quantity = $r['quantity'];
        $value = $buying_cost * $quantity;
        $total_value += $value;

        $identifier = $r['identifier'];
        $description = $r['description'];
        

        echo <<<EOS
        <tr>
            <td>$identifier</td>
            <td>$description</td>
            <td>$quantity</td>
            <td colspan="2">$buying_cost</td>
            <td></td>
            <td colspan="2">$value</td>
            <td></td>
        </tr>
        EOS;
    }

    $total_value = Utils::number_format($total_value);
    echo <<<EOS
    </table>
    <br><br>
    Total Value: &nbsp;&nbsp;$ $total_value
    </body>
    </html>
    EOS;
}

function fetch_item_details_by_identifiers(int $store_id): void {

    $db = get_db_instance();
    $data = Utils::read_csv_file('items2.csv');
    $identifiers = [];
    foreach($data as $d) $identifiers[]= $d[0];

    $query = <<<'EOS'
    SELECT 
        it.identifier, 
        it.description,
        it.prices, 
        inv.quantity
    FROM 
        items AS it
    LEFT JOIN 
        inventory AS inv
    ON 
        it.id = inv.item_id
    WHERE
        it.identifier IN (:placeholder)
    AND
        inv.store_id = :store_id;
    EOS;

    $results = Utils::mysql_in_placeholder_pdo_substitute($identifiers, $query);

    $query = $results['query'];
    $statement = $db -> prepare($query);
    $statement -> execute([...$results['values'], ':store_id' => $store_id]);
    $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
    
    echo <<<'EOS'
    <html>
    <body>
        <table>
    <tr>
        <th>Identifier</th>
        <th>Description</th>
        <th>Quantity</th>
        <th colspan="2">Price</th>
        <th></th>
        <th colspan="2">Value</th>
        <th></th>
    </tr>
    EOS;

    $table = [];
    foreach($identifiers as $i) $table[$i] = $i;

    $total_value = 0;
    foreach($records as $r) {
        $identifier = $r['identifier'];

        if(isset($table[$identifier]) === true) unset($table[$identifier]);

        $description = $r['description'];
        $prices = json_decode($r['prices'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR)[$store_id];
        $buying_cost = $prices['buyingCost'] ?? 0;
        $quantity = $r['quantity'];
        $value = $buying_cost * $quantity;
        $total_value += $value;

        echo <<<EOS
        <tr>
            <td>$identifier</td>
            <td>$description</td>
            <td>$quantity</td>
            <td colspan="2">$buying_cost</td>
            <td></td>
            <td colspan="2">$value</td>
            <td></td>
        </tr>
        EOS;
    }

    $total_value = Utils::number_format($total_value);
    echo <<<EOS
    </table>
    <br><br>
    Total Value: &nbsp;&nbsp;$ $total_value
    EOS;

    if(count($table) > 0) {
        echo '<br><br>Following items were not found:<br><br>';
        foreach($table as $i) echo "$i<br>";
    }

    echo "</body></html>";
}

// fetch_item_details_by_identifiers(StoreDetails::CALGARY);

// Flyer Send
// FlyerManagement::send_flyer(
//     store_id: StoreDetails::EDMONTON,
//     subject: 'Promotional Email From ABS Truck & Trailer Parts Ltd.',
//     content: <<<'EOS'
//     Dear Client,
//     <br><br>
//     We at ABS Truck and Trailer Parts Ltd. are happy to announce our monthly promotion <b>till end of March</b> on items specified in the flyer attached.
//     We expect to do business with you soon.
//     <br><br>
//     Thanks and Regards,<br>
//     ABS Truck and Trailer Parts Ltd.<br>
//     Edmonton
// EOS,
//     path_to_attachment: "{$_SERVER['DOCUMENT_ROOT']}/tmp/flyer.jpg",
//     file_name: 'Flyer_Edmonton_March_2025',
// );

function fix_balance_sheet(): void {
    try {
        $db = get_db_instance();
        $db -> beginTransaction();
        $amount = 45281.2500 + 69746.2500;

        $accounts = AccountsConfig::ACCOUNTS;
        $accounts[AccountsConfig::CHEQUE_RECEIVABLES] = -$amount;
        BalanceSheetActions::update_from($accounts, '2025-01-01', StoreDetails::EDMONTON, $db);
        $db -> commit();
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}

function fix_inventory_value(int $store_id): void {
    $db =  get_db_instance();
    try {
        $db -> beginTransaction();

        $statement = $db -> prepare('SELECT id, `statement` FROM balance_sheet WHERE store_id = :store_id ORDER BY id DESC LIMIT 1;');
        $statement -> execute([':store_id' => $store_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $balance_sheet = json_decode($result[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $balance_sheet[AccountsConfig::INVENTORY_A] = generate_list($store_id, false);
        $balance_sheet = json_encode($balance_sheet, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        $statement = $db -> prepare(<<<'EOS'
        UPDATE 
            balance_sheet
        SET 
            `statement` = :statement
        WHERE 
            id = :id;
        EOS);
        $is_successful = $statement -> execute([':id' => $result[0]['id'], ':statement' => $balance_sheet]);
        
        if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Update Balance Sheet.');
        $db -> commit();
        echo 'Successfully Updated Balance Sheet';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}
// fix_balance_sheet();
// fix_inventory_value(StoreDetails::SLAVE_LAKE);

function update_last_sold_for_items(int $store_id): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        $statement = $db -> prepare('SELECT `date`, `details` FROM sales_invoice WHERE store_id = :store_id;');
        $statement -> execute([':store_id' => $store_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $last_sold_dates = [];
        foreach($result as $r) {
            $date = $r['date'];
            $details = json_decode($r['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            foreach($details as $item) {
                if(isset($last_sold_dates[$item['itemId']]) === false) $last_sold_dates[$item['itemId']] = '';
                if($date > $last_sold_dates[$item['itemId']]) $last_sold_dates[$item['itemId']] = $date;
            }
        }

        $statement_fetch = $db -> prepare('SELECT id, last_sold FROM items WHERE id = :id;');
        $statement_update = $db -> prepare(<<<'EOS'
        UPDATE 
            items
        SET
            last_sold = :last_sold
        WHERE 
            id = :id;
        EOS);

        $item_ids = array_keys($last_sold_dates);
        foreach($item_ids as $item_id) {
            $statement_fetch -> execute([':id' => $item_id]);
            $last_sold = $statement_fetch -> fetchAll(PDO::FETCH_ASSOC)[0]['last_sold'];
            $last_sold = json_decode($last_sold, true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $last_sold[$store_id] = $last_sold_dates[$item_id];

            // Update 
            $last_sold = json_encode($last_sold, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $is_successful = $statement_update -> execute([':last_sold' => $last_sold, ':id' => $item_id]);
            if($is_successful !== true || $statement_update -> rowCount() < 1) throw new Exception(
                'Cannot update item: '. $item_id
            );
        }

        $db -> commit();

        echo 'Updated Last Sold for Items.';
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}

// update_last_sold_for_items(StoreDetails::EDMONTON);
// print_r(Inventory::get_dead_inventory(StoreDetails::EDMONTON));
// Inventory::generate_dead_inventory(StoreDetails::EDMONTON, 3);
// Inventory::fetch_quantity_sold_for_all_items(StoreDetails::EDMONTON, 2024, 1);


function transfer_account(int $store_id): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        // Fetch Balance Sheets
        $statement = $db -> prepare('SELECT * FROM balance_sheet WHERE store_id = :store_id AND `date` >= "2025-01-01";');
        $statement -> execute([':store_id' => $store_id]);
        $balance_sheet = $statement -> fetchAll(PDO::FETCH_ASSOC);

        // Update Balance sheet
        $statement = $db -> prepare(<<<'EOS'
        UPDATE 
            balance_sheet
        SET 
            statement = :statement 
        WHERE 
            id = :id;
        EOS);

        foreach($balance_sheet as $bs) {
            $id = $bs['id'];
            $accounts = json_decode($bs['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $accounts[AccountsConfig::CHEQUING_BANK_ACCOUNT] += $accounts[AccountsConfig::CASH_TO_BE_DEPOSITED];
            $accounts[AccountsConfig::CASH_TO_BE_DEPOSITED] = 0;

            $accounts = json_encode($accounts, flags: JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);
            $is_successful = $statement -> execute([':id' => $id, ':statement' => $accounts]);

            if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Update Balance Sheet # '. $id);
        }
        if($db -> inTransaction()) $db -> commit();

        echo 'Transferred';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}
// transfer_account(StoreDetails::EDMONTON);

// print_r(Shared::get_txn_age(
//     '2025-01-01',
//     '2025-03-01',
//     100,
//     StoreDetails::EDMONTON,
// ));

function process_transaction(array &$transactions, array &$data, int $txn_type): void {
    foreach($transactions as $txn) {
        $client_id = $txn['client_id'];
        if(isset($data[$client_id]) === false) $data[$client_id] = [
            SALES_INVOICE => [],
            SALES_RETURN => [],
            CREDIT_NOTE => [],
            DEBIT_NOTE => [],
            RECEIPT => [],
        ];

        $is_credit_txn = $txn_type === CREDIT_NOTE || $txn_type === SALES_RETURN;

        $temp = [
            'txn_id' => $txn['id'],
            'date' => $txn['date'],
            'txn_type' => TRANSACTION_NAMES[$txn_type],
            'sum_total' => $is_credit_txn ? -$txn['sum_total'] : $txn['sum_total'],
            'txn_type_id' => $txn_type,
        ];
        if(isset($txn['credit_amount'])) $temp['credit_amount'] = $is_credit_txn ? -$txn['credit_amount']: $txn['credit_amount'];
        $data[$client_id][$txn_type][$txn['id']] = $temp;
    }
}

function reverse_receipts(array &$receipts, array &$data): void {
    foreach($receipts as $receipt) {
        $client_id = $receipt['client_id'];
        $details = json_decode($receipt['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        foreach($details as $d) {
            $txn_type = $d['type'];
            $txn_id = $d['id'];
            if(isset($data[$client_id][$txn_type][$txn_id]) === false) continue;
            $data[$client_id][$txn_type][$txn_id]['credit_amount'] += $d['amountReceived'];
        }
    }
}

function add_receipt_payments(array &$receipts, array &$data): void {
    foreach($receipts as $receipt) {
        $receipt_id = $receipt['id'];
        $client_id = $receipt['client_id'];
        $details = json_decode($receipt['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        foreach($details as $d) {
            $txn_type = $d['type'];
            $txn_id = $d['id'];

            // Check transaction exists
            if(isset($data[$client_id][$txn_type][$txn_id]) === false) continue;

            if(isset($data[$client_id][$txn_type][$txn_id]['receipt_payments']) === false) {
                $data[$client_id][$txn_type][$txn_id]['receipt_payments'] = [];
            }

            $data[$client_id][$txn_type][$txn_id]['receipt_payments'][$receipt_id] = [
                'txn_id' => $receipt_id,
                'payment_method' => $receipt['payment_method'],
                'date' => $receipt['date'],
                'txn_type' => 'Receipt Payment',
                'sum_total' => ($d['amountReceived'] + $d['discountGiven']),
            ];
        }
    }
}

function display_txn(array &$data) {
    $txn_types = [SALES_INVOICE, SALES_RETURN, CREDIT_NOTE, DEBIT_NOTE, RECEIPT];
    foreach($data as $d) {
        foreach($txn_types as $txn_type) {
            foreach($d[$txn_type] as $x) {
                print_r($x);
                echo '<br>';
            }
        }   
    }
}

function get_row_code(array $txn, string $txn_date, string $report_date, int $store_id): string {
    $diff = Shared::get_txn_age($txn_date, $report_date, $txn['credit_amount'], $store_id);
    $code = '';
    $code .= '<td>'.$txn['txn_id'].'</td>';
    $code .= '<td>'.$txn['date'].'</td>';
    $code .= '<td>'.$txn['txn_type'].'</td>';
    $code .= '<td>'.Utils::number_format($diff['total'], 2).'</td>';
    $code .= '<td>'.Utils::number_format($diff['current'], 2).'</td>';
    $code .= '<td>'.Utils::number_format($diff['31-60'], 2).'</td>';
    $code .= '<td>'.Utils::number_format($diff['61-90'], 2).'</td>';
    $code .= '<td>'.Utils::number_format($diff['91+'], 2).'</td>';
    return $code;
}


function generate_report(array &$data, PDO $db, string $report_date, int $store_id, array $client_data, bool $show_error_list = false): void {
    $client_list = array_keys($data);

    $results = Utils::mysql_in_placeholder_pdo_substitute(
        $client_list,
        'SELECT id, `name` FROM clients WHERE id IN (:placeholder);',
    );

    $query = $results['query'];
    $values = $results['values'];

    $statement = $db -> prepare($query);
    $statement -> execute($values);
    $temp = $statement -> fetchAll(PDO::FETCH_ASSOC);

    // Format Client Details
    $client_details = [];
    foreach($temp as $t) {
        $client_details[$t['id']] = $t['name'];
    }

    $error_list = [];
    
    $code = <<<'EOS'
    <html>
    <body>
    <table>
    <thead>
        <tr>
            <th>Source</th>
            <th>Date</th>
            <th>thansaction Type</th>
            <th>Total</th>
            <th>Current</th>
            <th>31 - 60</th>
            <th>61 - 90</th>
            <th>91+</th>
        </tr>
    </thead>
    <tbody>
    EOS;
    $total_outstanding = 0;
    foreach($client_list as $client_id) {
        
        $temp_code = '';
        $total_outstanding_per_client = 0;
        $client_name = strtoupper($client_details[$client_id]);
        $temp_code .= '<tr><td colspan="7" style="letter-spacing:2px;"><b>'.$client_name.'</b></td></tr>';

        // List All Transactions
        $client_transactions_types = $data[$client_id];
        foreach($client_transactions_types as $txn_records) {
            $temp_code .= '<tr>';
            foreach($txn_records as $txn) {
                
                $total_outstanding_per_client += $txn['credit_amount'];
                
                $temp_code .= get_row_code($txn, $txn['date'], $report_date, $store_id);
                $temp_code .= '</tr>';
                if(isset($txn['receipt_payments'])) {
                    $receipt_payments = $txn['receipt_payments'];
    
                    // Show Receipt Payments
                    foreach($receipt_payments as $rp) {
                        if($rp['date'] > $report_date) continue;
                        $temp_code .= '<tr>';
                        $total_outstanding_per_client -= $rp['sum_total'];
                        $rp['sum_total'] = -$rp['sum_total'];
                        $temp_code .= get_row_code($rp, $rp['date'], $report_date, $store_id);
                        $temp_code .= '</tr>';
                    }
                }
            }
        }
    
        if($total_outstanding_per_client != 0) {
            $total_outstanding += $total_outstanding_per_client;
            $total_outstanding_per_client_formatted = Utils::number_format($total_outstanding_per_client);
            $temp_code .= "<tr><td colspan='7'><b>Total Outstanding: $total_outstanding_per_client_formatted</b></td></tr>";
            
            if(isset($client_data[$client_name]) === false) {
                $error_list[]= $client_name;
            }
            else {
                $total_outstanding_per_client = strval(Utils::round($total_outstanding_per_client, 2));
                $str_client_amount = strval($client_data[$client_name]);
                if($total_outstanding_per_client != $str_client_amount) {
                    $error_list []= $client_name;
                }
            }

            $code .= $temp_code;
        }
    }

    $total_outstanding = Utils::number_format($total_outstanding);
    $code .= <<<EOS
    <p><b>Total Receivables: $ $total_outstanding<b/></p>
    </tbody>
    </table>
    </body>
    </html>
    EOS;

    echo $code;

    if($show_error_list) {
        echo '<br><br>';
        foreach($error_list as $e) echo $e.'<br>';
    }
}

function eliminate_paid_transactions(array &$data) : void {
    $client_ids = array_keys($data);
    foreach($client_ids as $client_id) {
        $transaction_by_types = $data[$client_id];
        foreach($transaction_by_types as $txn_by_type) {
            foreach($txn_by_type as $txn) {
                if($txn['txn_type_id'] === RECEIPT) continue;
                if($txn['credit_amount'] == 0) {
                    
                    // Delete record
                    unset($data[$client_id][$txn['txn_type_id']][$txn['txn_id']]);
                }
            }
        }
    }
}

function generate_client_aged_detail(int $store_id, string $receipt_exclude_date, array $client_list): void {
    $db = get_db_instance();

    $params_txn = [':store_id' => $store_id, ':till_date' => $receipt_exclude_date];
    $params_receipt = [':store_id' => $store_id, ':exclude_from' => $receipt_exclude_date];
    $data = [];

    // Select Invoices
    $statement_invoices = $db -> prepare('SELECT id, sum_total, credit_amount, `date`, client_id FROM sales_invoice WHERE store_id = :store_id AND payment_method = 0 AND `date` < :till_date;');
    $statement_invoices -> execute($params_txn);
    $sales_invoices = $statement_invoices -> fetchAll(PDO::FETCH_ASSOC);

    // Sales Returns
    $statement_sales_return = $db -> prepare('SELECT id, sum_total, credit_amount, `date`, client_id FROM sales_return WHERE store_id = :store_id AND payment_method = 0 AND `date` < :till_date;');
    $statement_sales_return -> execute($params_txn);
    $sales_returns = $statement_sales_return -> fetchAll(PDO::FETCH_ASSOC);

    // Credit Note
    $statement_credit_note = $db -> prepare('SELECT id, sum_total, credit_amount, `date`, client_id  FROM credit_note WHERE store_id = :store_id AND `date` < :till_date;');
    $statement_credit_note -> execute($params_txn);
    $credit_notes = $statement_credit_note -> fetchAll(PDO::FETCH_ASSOC);

    // Debit Note
    $statement_debit_note = $db -> prepare('SELECT id, sum_total, credit_amount, `date`, client_id  FROM debit_note WHERE store_id = :store_id AND `date` < :till_date;');
    $statement_debit_note -> execute($params_txn);
    $debit_notes = $statement_debit_note -> fetchAll(PDO::FETCH_ASSOC);

    // Receipts
    $statement_receipt = $db -> prepare('SELECT id, sum_total, `date`, `details`, client_id, payment_method FROM receipt WHERE store_id = :store_id AND do_conceal = 0 AND `date` >= :exclude_from;');
    $statement_receipt -> execute($params_receipt);
    $receipts = $statement_receipt -> fetchAll(PDO::FETCH_ASSOC);

    // Reverse Receipts
    process_transaction($sales_invoices, $data, SALES_INVOICE);
    process_transaction($sales_returns, $data, SALES_RETURN);
    process_transaction($credit_notes, $data, CREDIT_NOTE);
    process_transaction($debit_notes, $data, DEBIT_NOTE);

    reverse_receipts($receipts, $data);
    add_receipt_payments($receipts, $data);
    eliminate_paid_transactions($data);

    generate_report($data, $db, '2025-02-28', $store_id, $client_list, false);
}

$file = Utils::read_csv_file("{$_SERVER['DOCUMENT_ROOT']}/tmp/nisku_wash.csv");

function format_client_name(array $data): array {
    $clients = [];
    foreach($data as $d) {
        $clients[strtoupper($d[0])] = $d[2];
    }
    return $clients;
}
// $client_list = format_client_name($file);
// generate_client_aged_detail(StoreDetails::NISKU, '2025-03-01', $client_list);

$db = get_db_instance();
// CustomerAgedSummary::create_statement('2025-01-08', StoreDetails::EDMONTON, $db);
?>  