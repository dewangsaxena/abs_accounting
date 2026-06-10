<?php 

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_sales.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_statement.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/correct_is_bs_inventory_v2.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/suppressions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/flyer.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/user_management.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/stats.php";

$items_details = [];
function read_line_code_from_calgary($filename) {
    global $items_details;
    $data = Utils::read_csv_file($filename);
    foreach($data as $d) {
        if(isset($items_details[$d[0]]) === false) {
            $items_details[$d[0]] = [
                'identifier' => $d[0],
                'line_code' => $d[2], 
                'quantity' => 0, 
                'description' => $d[1],
                'buyingCost' => 0,
                'min' => 0,
                'max' => 0,
                'value' => 0,
            ];
        }
    }
}

function read_line_code_from_delta($filename) {
    global $items_details;
    $data = Utils::read_csv_file($filename);
    foreach($data as $d) {
        if(isset($items_details[$d[0]]) === false) {
            $items_details[$d[0]] = [
                'identifier' => $d[0],
                'line_code' => 'N/A', 
                'quantity' => 0, 
                'description' => $d[1],
                'buyingCost' => 0,
                'min' => 0,
                'max' => 0,
                'value' => 0,
            ];
        }
    }
    return $items_details;
}

// read_line_code_from_calgary("{$_SERVER['DOCUMENT_ROOT']}/calgary_line_code.csv");
// $items_details = read_line_code_from_delta("{$_SERVER['DOCUMENT_ROOT']}/delta_line_code.csv");
// print_r($items_details);

function generate_new_store_inventory_file(int $store_id) {
    global $items_details;
    $db = get_db_instance();

    $statement = $db -> prepare(<<<'EOS'
    SELECT 
        i.`code`,
        i.oem,
        i.is_inactive,
        i.is_core,
        i.memo,
        i.additional_information,
        i.is_discount_disabled,
        i.`identifier`,
        i.`description`,
        i.`reorder_quantity`,
        i.`prices`,
        inv.`quantity`
    FROM
        inventory as inv
    LEFT JOIN
        items AS i
    ON 
        i.id = inv.item_id
    WHERE 
        inv.store_id = :store_id;
    EOS
    );
    $statement -> execute([':store_id' => $store_id]);

    $items_quantity = $statement -> fetchAll(PDO::FETCH_ASSOC);
    foreach($items_quantity as $i) {
        $identifier = $i['identifier'];
        $prices = json_decode($i['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $reorder_quantity = json_decode($i['reorder_quantity'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        if(isset($items_details[$identifier]) === false) {
            $items_details[$identifier] = [
                'identifier' => $identifier,
                'line_code' => 'N/A', 
                'quantity' => 0, 
                'description' => $i['description'],
                'buyingCost' => 0,
                'min' => 0,
                'max' => 0,
                'value' => 0,
            ];
        }
        $items_details[$identifier]['quantity'] = $i['quantity'] ?? 0;
        if(isset($reorder_quantity[$store_id])) $items_details[$identifier]['min'] = $reorder_quantity[$store_id];
        if(isset($prices[$store_id])) $items_details[$identifier]['buyingCost'] = Utils::round($prices[$store_id]['buyingCost'], 2);
        $items_details[$identifier]['value'] = Utils::round($items_details[$identifier]['buyingCost'] * $items_details[$identifier]['quantity'], 2);

        $items_details[$identifier]['code'] = $i['code'];
        $items_details[$identifier]['oem'] = $i['oem'];
        $is_inactive = json_decode($i['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        if(isset($is_inactive[$store_id])) $is_inactive = $is_inactive[$store_id];
        else $is_inactive = 0;
        $items_details[$identifier]['is_inactive'] = $is_inactive;
        $items_details[$identifier]['is_core'] = $i['is_core'];
        $items_details[$identifier]['memo'] = $i['memo'];
        $items_details[$identifier]['additional_information'] = $i['additional_information'];

        $is_discount_disabled = json_decode($i['is_discount_disabled'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        if(isset($is_discount_disabled[$store_id])) $is_discount_disabled = $is_discount_disabled[$store_id];
        else $is_discount_disabled = 0;
        $items_details[$identifier]['is_discount_disabled'] = $is_discount_disabled;
    }

    $file_handle = fopen('delta_inventory.csv', 'w');

    fputcsv($file_handle, [
                'Line Abbreviation',
                'Line Abbreviation Description',
                'Part Number Identifier',
                'Part Number Description',
                'Supplier',
                'Store Cost',
                'Unit of measure',
                'Quantity on Hand',
                'Current Minium Stocked (MIN)',
                'Current maximum Stocked (MAX)',
                'Code',
                'OEM',
                'Is Inactive',
                'Is Core',
                'Memo',
                'Additional Information',
                'Is Discount Disabled',
            ]);

    foreach($items_details as $i) {
            fputcsv($file_handle, [
                $i['line_code'],
                'N/A',
                $i['identifier'],
                $i['description'],
                '',
                $i['buyingCost'],
                'Each',
                $i['quantity'],
                $i['min'],
                $i['max'],
                $i['code'] ?? ($i['identifier']. ' '. $i['description']),
                $i['oem'] ?? '',
                $i['is_inactive'] ?? 0,
                $i['is_core'] ?? 0,
                $i['memo'] ?? '',
                $i['additional_information'] ?? '',
                $i['is_discount_disabled'] ?? 0,
            ]);
    }

    fclose($file_handle);
}

// generate_new_delta_inventory_file(StoreDetails::DELTA);

function extract_transaction_records_of_clients(int $store_id, string $table_name) {
    $db = get_db_instance();
    $clients = Client::fetch_clients_of_store($store_id);

    if($table_name == 'credit_note' || $table_name == 'debit_note') {
        $is_credit_or_debit = true;
        $query = <<<EOS
        SELECT 
            t.* ,
            c.`contact_name`
        FROM 
            $table_name AS t
        LEFT JOIN 
            clients AS c
        ON 
            t.client_id = c.id
        WHERE 
            t.client_id IN (:placeholder) 
        AND 
            t.store_id = :store_id 
        AND 
            t.`date` >= '2023-01-01' 
        ORDER BY `id`, `date` ASC;
        EOS;
    }
    else {
        $is_credit_or_debit = false;
        $query = "SELECT * FROM $table_name WHERE client_id IN (:placeholder) AND store_id = :store_id AND `date` >= '2023-01-01' ORDER BY `id`, `date` ASC;";
    }

    $results = Utils::mysql_in_placeholder_pdo_substitute(array_keys($clients), $query);
    $query = $results['query'];
    $values = $results['values'];
    $values[':store_id'] = $store_id;
    $statement = $db -> prepare($query);
    $is_successful = $statement -> execute($values);
    if($is_successful !== true) throw new Exception('Unable to execute query');

    $transaction_records = $statement -> fetchAll(PDO::FETCH_ASSOC);

    $file_handle = fopen("edmonton_$table_name.csv", 'w');
    fputcsv($file_handle, [
                'BranchCode',
                'Customer',
                'CompanyName',
                'InvoiceNumber',
                'InvoiceDate',
                'Supplier',
                'PartNumber',
                'Quantity',
                'Price',
                'Cost',
    ]);

    foreach($transaction_records as $txn) {
        
        if($is_credit_or_debit === false) {
            $contact_details = json_decode($txn['shipping_address'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $customer = $contact_details['name'];
        }
        else $customer = $txn['contact_name'];
       
        $all_records = [];
        $base_record = [
            $store_id,
            $txn['client_id'],
            $customer,
            $txn['id'],
            $txn['date'],
            'N/A', /* Supplier */
            null, /* [6]: Part Number */ 
            null, /* [7]: Quantity */
            null, /* [8]: Price */
            null, /* [9]: Cost */
        ];

        $item_details = json_decode($txn['details'], true, flags: JSON_THROW_ON_ERROR);
        foreach($item_details as $item) {
            $column_name = $table_name === 'sales_return' ? 'returnQuantity': 'quantity';
            $quantity = $item[$column_name] ?? 0;
            if($quantity == 0) continue;
            $tmp_record = $base_record;
            $tmp_record[6] = $item['identifier'];
            $tmp_record[7] = $item[$column_name];
            $tmp_record[8] = Utils::round($item['pricePerItem'], 2);
            $tmp_record[9] = Utils::round($item['buyingCost'], 2);
            $all_records[]= $tmp_record;
        }

        foreach($all_records as $r) fputcsv($file_handle, $r);
    }

    fclose($file_handle);

}

// extract_transaction_records_of_clients(StoreDetails::EDMONTON, 'debit_note');die;

function extract_client_details(int $store_id) {
    $fields = [
        'CustomerNumber',
        'FusionCustomerNumber',
        'Company',
        'BaseBranch',
        'ControlBranch',
        'InActive',
        'PrimaryContactSalutation',
        'PrimaryContactFirstName',
        'PrimaryContactMiddleInitial',
        'PrimaryContactLastName',
        'PrimaryContactTitle',
        'Street1',
        'Street2',
        'City',
        'Region',
        'PostalCode',
        'Country',
        'BillToTaxBody',
        'ShipTo_Street1',
        'ShipTo_Street2',
        'ShipTo_City',
        'ShipTo_Region',
        'ShipTo_PostalCode',
        'ShipTo_Country',
        'ShipToTaxBody',
        'LanguageName',
        'Territory',
        'BusinessTaxNumber',
        'QuebecTaxNumber',
        'CanadianTaxNumber',
        'CreationDate',
        'PrimaryContactHomePhone',
        'PrimaryContactWorkPhone',
        'PrimaryContactCellPhone',
        'PrimaryContactFax',
        'InvoiceEmailAddress',
        'StatementEmailAddress',
        'TaxStatusDescription',
        'AccountStatus',
        'PaymentTerms',
        'SalesManagementTaxStatus',
        'CreditLimit',
        'IsPORequired',
        'SubjectToFinanceCharge',
        'BlanketPONumber',
        'ParentCustomerBranch',
        'ParentCustomerNumber',
        'isSeparateStatement',
        'DefaultPaymentMethod',
        'SubjectToDelinquency',
        'SubjectToPastDue',
        'PerformCreditCheck',
        'AllowCharge',
        'Comment1',
        'Comment2',
        'AllowMVP',
        'AllowMVPCardNumber',
        'MVPCardNumber',
        'MVPCardNumberType',
        'MVPExpire',
        'MVPCreditLimit',
        'AllowIBS',
        'IBSNumber',
        'IBSMessage',
        'AllowFleetCharge',
        'InternationalFleetChargeAccountNumber',
        'InternationalFleetChargePCardNumber',
        'AllowFreightliner',
        'FreightlinerInvoiceCopyRequired',
        'AllowCorcentric',
        'CorecentricAccountNumber',
        'AllowServiceUnitOwnership',
        'IsLPORequired',
        'ProspectFlag',
        'LastInvoiceDate',
        'LastPaymentDate',
        'CityCode',
        'FordCustomerType',
        'IndustryType',
        'SeparateCoreInvoice',
    ];

    $file_handle = fopen('client_details.csv', 'w+');

    fputcsv($file_handle, $fields);

    $clients = Client::fetch_clients_of_store($store_id);

    foreach($clients as $client) {
        $shipping_address = json_decode($client['shipping_addresses'], true, flags: JSON_NUMERIC_CHECK);
        if(count($shipping_address) > 0) $shipping_address = $shipping_address[0];
        else $shipping_address = null;

        // Tax 
        $disable_federal_tax = json_decode($client['disable_federal_taxes'], true, flags: JSON_NUMERIC_CHECK)[$store_id] ?? 0;
        $disable_provincial_tax = json_decode($client['disable_provincial_taxes'], true, flags: JSON_NUMERIC_CHECK)[$store_id] ?? 0;

        // early_payment_paid_within_days
        $early_payment_paid_within_days = json_decode($client['early_payment_paid_within_days'], true, flags: JSON_NUMERIC_CHECK)[$store_id] ?? 0;
        $net_amount_due_within_days = json_decode($client['net_amount_due_within_days'], true, flags: JSON_NUMERIC_CHECK)[$store_id] ?? 0;
        $payment_terms = '';
        if($early_payment_paid_within_days > 0) {
            $payment_terms .= $early_payment_paid_within_days . ' - ';
        }
        if($net_amount_due_within_days > 0) $payment_terms .= 'NET '. $net_amount_due_within_days. ' DAYS FROM INV.';

        // Last Purchase Date
        $last_purchase_date = json_decode($client['last_purchase_date'], true, flags: JSON_NUMERIC_CHECK)[$store_id];
        $record = [
            $client['id'], // CustomerNumber
            $client['id'], // FusionCustomerNumber
            $client['name'], // Company
            7, // BaseBranch
            7, // ControlBranch
            $client['is_inactive'], // InActive
            '', // PrimaryContactSalutation
            $client['contact_name'], // PrimaryContactFirstName
            '', // PrimaryContactMiddleInitial
            '', // PrimaryContactLastName
            '', // PrimaryContactTitle
            $client['street_1'],
            $client['street_2'],
            $client['city'],
            'BC', // Region
            $client['postal_code'],
            'Canada', // Country
            'BC', // BillToTaxBody
            $shipping_address['street1'] ?? '',
            $shipping_address['street2'] ?? '',
            $shipping_address['city'] ?? '',
            $shipping_address['province'] ?? '',
            $shipping_address['postalCode'] ?? '',
            'Canada', // ShipTo_Country
            $shipping_address['province'] ?? '',
            'English', // Language
            '', // Territory
            '', // BusinessTaxNumber
            '', // QuebecTaxNumber
            '', // CanadianTaxNumber
            '', // Creation Date
            '', // PrimaryContactHomePhone
            $client['phone_number_1'] ?? '', // PrimaryContactWorkPhone
            $client['phone_number_2'] ?? '', // PrimaryContactCellPhone
            $client['fax'] ?? '', // PrimaryContactFax
            $client['email_id'],  // InvoiceEmailAddress
            $client['additional_email_addresses'], // StatementEmailAddress
            $disable_federal_tax && $disable_provincial_tax ? 'Exempt': 'Taxable',  // TaxStatusDescription
            'Open', // AccountStatus
            $payment_terms,
            '', // SalesManagementTaxStatus
            json_decode($client['credit_limit'], true, flags: JSON_NUMERIC_CHECK)[$store_id] ?? 0,
            '', // IsPORequired
            true, // SubjectToFinanceCharge
            '', // BlanketPONumber
            '', // ParentCustomerBranch
            '', // ParentCustomerNumber
            '', // isSeparateStatement
            'Charge', // DefaultPaymentMethod
            '', // SubjectToDelinquency
            '', // SubjectToPastDue
            '', // PerformCreditCheck
            true, // AllowCharge
            '', // Comment1
            '', // Comment2
            '', // AllowMVP
            '', // AllowMVPCardNumber
            '', // MVPCardNumber
            '', // MVPCardNumberType
            '', // MVPExpire
            '', // MVPCreditLimit
            '', // AllowIBS
            '', // IBSNumber
            '', // IBSMessage
            '', // AllowFleetCharge
            '', // InternationalFleetChargeAccountNumber
            '', // InternationalFleetChargePCardNumber
            '', // AllowFreightliner
            '', // FreightlinerInvoiceCopyRequired
            '', // AllowCorcentric
            '', // CorecentricAccountNumber
            '', // AllowServiceUnitOwnership
            '', // IsLPORequired
            '', // ProspectFlag
            $last_purchase_date, // LastInvoiceDate
            '', // LastPaymentDate
            '', // CityCode
            '', // FordCustomerType
            '', // IndustryType
            '', // SeparateCoreInvoice
        ];
        fputcsv($file_handle, $record);
    }

    fclose($file_handle);
}

// extract_client_details(StoreDetails::DELTA);

function extract_accounts_receivables_file_for_store(int $store_id) : void {
    $db = get_db_instance();

    
}