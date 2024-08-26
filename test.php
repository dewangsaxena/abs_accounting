<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory.php";
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

if(SYSTEM_INIT_MODE === PARTS) {
    // generate_list(StoreDetails::CALGARY);
    // echo 'CALGARY : '. (Correct_IS_BS_Inventory::correct(StoreDetails::CALGARY) ? 'T' : 'F');
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

$store_id = StoreDetails::VANCOUVER;
$db = get_db_instance();
$quantity_table = [];
foreach($items as $item_id) {
    $quantity = fetch_quantity_sold_of_items($item_id, $db, $store_id);
    if(isset($quantity_table[$item_id]) === false)  $quantity_table[$item_id] = 0;
    $quantity_table[$item_id] += $quantity;
}

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

// generate_table($quantity_table, $db, $store_id);


function disable_pay_later(int $store_id) {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();

        $client_query = 'SELECT id, last_purchase_date, modified FROM clients;';
        $statement = $db -> prepare($client_query);
        $statement -> execute();
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $clients_to_disable = [];

        foreach($results as $client) {
            $last_purchase_date = json_decode($client['last_purchase_date'], true, flags: JSON_THROW_ON_ERROR);
            if(isset($last_purchase_date[$store_id])) {
                $last_purchase_date = $last_purchase_date[$store_id];

                if($last_purchase_date < '2024-01-01') {
                    $clients_to_disable[$client['id']] = $client['modified'];
                }
            }
        }

        // Update Clients
        $client_query = 'UPDATE clients SET disable_credit_transactions = 1 WHERE id = :id AND modified = :modified;';
        $statement = $db -> prepare($client_query);
        $client_ids = array_keys($clients_to_disable);
        foreach($client_ids as $client_id) {
            $is_successful = $statement -> execute([
                ':id' => $client_id,
                ':modified' => $clients_to_disable[$client_id],
            ]);

            if($is_successful !== true && $statement -> rowCount() < 1) throw new Exception('Unable to Update for client: '. $client_id);
        }
        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        echo $e -> getMessage();
        $db -> rollBack();
    }
}
?>