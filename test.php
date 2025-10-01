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
fix_inventory_value($store_id);
echo generate_list($store_id, true);
die;

function check_tax(int $store_id): void {
    $db = get_db_instance();
    $statement = $db -> prepare('SELECT * FROM sales_invoice WHERE store_id = :store_id;');
    $statement -> execute([':store_id' => $store_id]);
    $records = $statement -> fetchAll(PDO::FETCH_ASSOC);
    $gst_tax_rate = null;
    $pst_tax_rate = null;
    $counter = 0;
    foreach($records as $record) {
        $gst_tax_rate = null;
        $pst_tax_rate = null;
        $details = json_decode($record['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        foreach($details as $detail) {
            if(is_null($gst_tax_rate) || is_null($pst_tax_rate)) {
                $gst_tax_rate = $detail['gstHSTTaxRate'];
                $pst_tax_rate = $detail['pstTaxRate'];
            }
            
            if(in_array($detail['itemId'], Inventory::EHC_ITEMS) === false && ($gst_tax_rate != $detail['gstHSTTaxRate'] || $pst_tax_rate != $detail['pstTaxRate'])) {
                $disable_gst = $record['disable_federal_taxes'];
                $disable_pst = $record['disable_provincial_taxes'];
                echo $detail['identifier'].'<br>';
                echo "$disable_gst | $disable_pst<br>";
                echo 'Payment Method: '. $record['payment_method'].'<br><br>';
                echo('Record# '.$record['id']).'<br>';
                echo "$gst_tax_rate : ". $detail['gstHSTTaxRate']. ' | '. "$pst_tax_rate | ". $detail['pstTaxRate'].'<br><br><br>';
                $counter += 1;
            }
        }
    }

    // echo $counter;
}

function spread_amount() : void {
    $amount = 291560.96;

    $data = Utils::read_csv_file('DEWANG.csv');
    $clients = [];
    foreach($data as $d) {
        if(isset($d[0])) $clients[]= [$d[0], 0];
    }

    $total_amount_adjusted = 0;
    $client_index = 0;
    while($amount > 10) {
        $random_amount = rand(100, 1100);
        $decimal = rand(0, 100);
        $amount_to_adjust = $random_amount + ($decimal/ 100);
        $clients[$client_index][1] += $amount_to_adjust;
        $amount -= $amount_to_adjust;
        $total_amount_adjusted += $amount_to_adjust;
        $client_index += 1;
        if($client_index == 265 && $amount > 10) $client_index = 0;
    }

    echo "$amount : $total_amount_adjusted";
    
    $fp = fopen('new_clients_list.csv', 'w');
    foreach($clients as $c) {
        fputcsv($fp, $c);
    }

}

spread_amount();

?>  