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
?>