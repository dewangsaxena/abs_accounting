<?php 

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory_v2.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/suppressions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/flyer.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/user_management.php";

function generate_list(int $store_id, bool $do_print=true) {
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
    $current_date = date('d M, Y');
    $code = <<<EOS
    <html>
    <head></head>
    <body>
    <style>
    table {
        border-spacing: 20px 0;
    }
    </style>
    <h1>List for $store_name as on $current_date </h1>
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
    else return $total_value;
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
    }
    catch(Exception $e) {
        $db -> rollBack();
        echo $e -> getMessage();
    }
}
$store_id = StoreDetails::SASKATOON;
// fix_inventory_value($store_id);
// generate_list($store_id, true);
// die;

// $code = 'AF';
// $details = Inventory::fetch_item_quantity_sold_by_prefix($code, StoreDetails::CALGARY, '2025-01-01', '2025-12-31');
// Inventory::generate_quantity_report_of_item_sold($code, $details);

function f_record(): void {
    $db = get_db_instance();
    try {
        $db -> beginTransaction();
        
        $statement = $db -> prepare('SELECT * FROM income_statement WHERE store_id = 2 AND `date` = "2025-11-12";');
        $statement -> execute();
        $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
        $income_statement = json_decode($records[0]['statement'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Sales Revenue
        $income_statement[4020] -= 45.62;

        // Inventory
        $income_statement[1520] += 36.18;
 
        // Update Records
        $statement = $db -> prepare('UPDATE income_statement SET `statement` = :_statement WHERE store_id = 2 AND `date` = "2025-11-12";');
        $is_successful = $statement -> execute([':_statement' => json_encode($income_statement, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR)]);
        if($is_successful && $statement -> rowCount() < 1) throw new Exception('Unable to update Income Statement');

        // Update Balance Sheet
        $bs = AccountsConfig::ACCOUNTS;

        BalanceSheetActions::update_account_value($bs, AccountsConfig::ACCOUNTS_RECEIVABLE, -47.90);
        BalanceSheetActions::update_account_value($bs, AccountsConfig::INVENTORY_A, 36.18);
        BalanceSheetActions::update_from($bs, '2025-11-12', 2, $db);

        $db -> commit();
        echo 'Done';
    }
    catch(Exception $e) {
        $db -> rollBack();
        print_r($e -> getMessage());
    }
}

// f_record(StoreDetails::EDMONTON);
$client_ids = Client::fetch_clients_of_store(StoreDetails::NISKU);

$index = 0;
$limit = $index + 25;
for (; $index < $limit; ++$index) {
    Email::send(
        'Merry Christmas and Happy New Year!',
        $client_ids[$index]['email_id'],
        $client_ids[$index]['name'],
        <<<'EOS'
        We at ABS Truck & Trailer Parts Nisku wish you a Merry Christmas and a Happy New Year! Have a Jolly holidays.<br><br>
        Our hours vary during this time, please see the attachment below to learn more.<br>
        <br>
        <br>
        Regards,<br>
        ABS Truck & Trailer Parts Nisku
        EOS,
        StoreDetails::NISKU,
        "{$_SERVER['DOCUMENT_ROOT']}/nisku_holiday_schedule.png",
        'Holiday_schedule.png'
    );
}

?>  