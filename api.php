<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

// Start or resume session
if (isset($_POST['session_id'][0])) session_id($_POST['session_id']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/user_management.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_statement.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/utils/stats.php";

// Perform External Operation.
if (isset($_GET['action'])) {

    // Action
    $action = $_GET['action'];

    /* Balance Sheet */
    if ($action === 'balance_sheet') {
        BalanceSheetActions::generate(intval($_GET['storeId']), intval($_GET['month']), intval($_GET['year']));
    } else if ($action === 'customer_aged_summary') {
        if (isset($_GET['fetchHistoricalRecord']) && $_GET['fetchHistoricalRecord'] == 1) {
            CustomerAgedSummary::fetch_historical_summary(
                intval($_GET['storeId']),
                $_GET['tillDate'],
                intval($_GET['isCSV']),
                intval($_GET['es'] ?? '1'), /* Exclude Self */
                intval($_GET['ec'] ?? '1'), /* Exclude Client */
            );
        } else {
            CustomerAgedSummary::generate(
                intval($_GET['storeId']),
                null,
                $_GET['tillDate'],
                intval($_GET['sortAscending'] ?? 0),
                intval($_GET['isCSV'] ?? '0'),
                intval($_GET['es'] ?? '1'), /* Exclude Self */
                intval($_GET['ec'] ?? '1'), /* Exclude Client */
                /* This parameter is passed only for Generating Customer Statement */
                omit_credit_records: intval($_GET['omitCreditRecords'] ?? '0')
            );
        }
    } else if ($action === 'customer_statement') {
        $status = CustomerStatement::generate(
            $_GET['clientId'],
            $_GET['storeId'],
            $_GET['startDate'],
            $_GET['endDate'],
            intval($_GET['attachTransactions']) ? true : false,
            intval($_GET['generateRecordOfAllTransactions']) ? true : false,
        );
        if ($status === null) echo 'Unable to Generate Statement.';
    } else if ($action === 'income_statement') {
        IncomeStatementActions::generate($_GET);
    } else if ($action === 'packaging_slip' && is_numeric($_GET['i'] ?? null)) {
        SalesInvoice::generate_packaging_slip($_GET['i']);
    } else if ($action === 'low_stock') {
        $store_id = intval($_GET['store_id']);
        $message = '';
        if (isset(StoreDetails::STORE_DETAILS[$store_id]) === true) {
            Inventory::fetch_low_stock($store_id);
        } else $message = 'Invalid Store.';
        die($message);
    } else if ($action === 'last_purchase_before') {
        Client::fetch_clients_by_last_purchase_date($_GET['lastPurchaseBefore'], intval($_GET['storeId']));
        die;
    } else {
        require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/pdf/pdf.php";
        $transaction_type = intval($_GET['t'] ?? 0);
        $params = ['action' => $action, 'transactionType' => $transaction_type];
        if ($transaction_type === RECEIPT) {
            $params['id'] = $_GET['i'];
            if (isset($_GET['s'])) {
                $attached_transactions = json_decode(base64_decode($_GET['s']), true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Format Transactions
                $attached_transactions = Utils::format_for_transaction_by_type($attached_transactions);
            } else $attached_transactions = [];
            $params['transactions'] = $attached_transactions;
        } else $params['txn_queue'] = [['type' => $transaction_type, 'id' => $_GET['i'], 'version' => $_GET['version'] ?? null]];
        $response = handle_transactions($params);
        if ($response['status'] === false) die($response['message']);
    }

    /* Terminate Script */
    die;
}

/* Disable error reporting. */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if (isset($_POST['action'])) {

    // Convert Request into an array for processing
    $data = json_decode(json_encode($_POST, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR), true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
    if (json_last_error() !== JSON_ERROR_NONE || count($data) < 1) throw new Exception(json_encode(['status' => false, 'message' => 'Invalid Request.']));

    // Check for Valid App Version
    if (isset($data['app_version']) === false || $data['app_version'] !== CLIENT_APP_VERSION) {
        // die(json_encode(['status' => false, 'message' => 'Please update the Client to v'. CLIENT_APP_VERSION. ' by performing a hard refresh to proceed. To perform a hard refresh use the following shortcut: CTRL + SHIFT + R']));
    }

    if ($data['action'] !== 'um_authenticate') {

        // Validate Session Token
        if ($data['session_token'] !== SESSION_TOKEN) die(json_encode(['status' => false, 'message' => 'Invalid Session Token. Login again.']));

        // Validate CSRF token if not authenticating.
        if (CSRF::is_token_valid($data['csrf_token']) === false) die(json_encode(['status' => false, 'message' => 'Invalid CSRF Token. Please Login again.']));

        // DB Instance
        $db = get_db_instance();

        // Check for Valid User Access
        if (CHECK_USER_ACCESS_ON_REQUEST && UserManagement::check_access($_SESSION['user_id'], $db)['status'] === false) die(json_encode(['status' => false, 'message' => 'Access Revoked.']));

        // Check for Update Methods
        $response = SessionManagement::has_write_permission($_SESSION['user_id'], $data['action'], $db);
        if ($response['status'] === false) die(json_encode($response, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR));
    }

    // Response 
    $response = ['status' => false];
    switch ($data['action']) {

            /* Client */
        case Client::ADD:
            $response = Client::process($data);
            break;
        case Client::UPDATE:
            $response = Client::process($data);
            break;
        case Client::FETCH:
            $response = Client::fetch($data);
            break;

            /* User Management */
        case UserManagement::ADD:
            $response = UserManagement::add($data);
            break;
        case UserManagement::AUTHENTICATE:
            $response = UserManagement::authenticate($data);
            break;
        case UserManagement::UPDATE_PASSWORD:
            $response = UserManagement::change_password($data);
            break;
        case UserManagement::UPDATE_STATUS:
            $response = UserManagement::update_access_status($data);
            break;
        case UserManagement::CHANGE_USER_ACCESS_LEVEL:
            $response = UserManagement::change_user_access_level($data);
            break;
        case UserManagement::FETCH:
            $response = UserManagement::fetch($data);
            break;
        case UserManagement::LOGOUT:
            UserManagement::logout();
            break;

            /* Inventory */
        case Inventory::ADD:
            $response = Inventory::process_item($data);
            break;
        case Inventory::UPDATE:
            $response = Inventory::process_item($data);
            break;
        case Inventory::FETCH:
            $response = Inventory::fetch($data ?? null, $_SESSION['store_id']);
            break;
        case Inventory::FETCH_PROFIT_MARGINS:
            $response = Inventory::fetch_profit_margins(0);
            break;
        case Inventory::UPDATE_PROFIT_MARGINS:
            $response = Inventory::update_profit_margins($data);
            break;
        case Inventory::FETCH_ITEM_DETAILS_FOR_ADJUST_INVENTORY:
            $response = Inventory::fetch_item_details_for_adjust_inventory($data['search_term'], $_SESSION['store_id']);
            break;
        case Inventory::ADJUST_INVENTORY:
            $response = Inventory::adjust_inventory($data['details'] ?? null, intval($_SESSION['store_id']));
            break;
        case Inventory::ITEM_DETAILS_FOR_TRANSACTIONS:
            $response = Inventory::item_details_for_transactions($data['search_term']);
            break;

            /* Transactions */
        case Shared::CREATE_TXN:
            $response = handle_transactions($data);
            break;
        case Shared::UPDATE_TXN:
            $response = handle_transactions($data);
            break;
        case 'fetch_transaction_by_id':
            $response = handle_transactions($data);
            break;
        case 'fetch_sales_invoices_for_client':
            $response = handle_transactions([
                'action' => $data['action'],
                'transactionType' => SALES_INVOICE,
                'client_id' => $data['client_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
            ]);
            break;
        case 'txn_search':
            $response = handle_transactions($data);
            break;
        case 'txn_fetch_outstanding_txn_for_receipt':
            $response = handle_transactions($data);
            break;
        case Shared::TRANSFER_INVOICE:
            $response = handle_transactions($data);
            break;
        case Shared::CONVERT_QUOTE_TO_INVOICE:
            $response = handle_transactions($data);
            break;
        case 'txn_email':
            $params = [
                'action' => 'txn_email',
                'transactionType' => $data['transactionType'],
                'txn_queue' => [[
                    'type' => $data['transactionType'],
                    'id' => $data['transactionId']
                ]]
            ];
            $response = handle_transactions($params);
            break;
        case 'receipt_email':
            $attached_transactions = json_decode(base64_decode($data['selectedTxn']), true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            $response = handle_transactions([
                'action' => 'receipt_email',
                'transactionType' => RECEIPT,
                'id' => $data['id'],
                'transactions' => $attached_transactions,
            ]);
            break;
        case 'delete_txn':
            $response = handle_transactions($data);
            break;

            /* Customer Statement */
        case 'email_customer_statement':
            $response = CustomerStatement::email(
                $data['clientId'] ?? null,
                $data['storeId'],
                $data['startDate'],
                $data['endDate'],
                intval($data['attachTransactions']) === 1 ? true : false,
                intval($data['generateRecordOfAllTransactions']) === 1 ? true : false,
                $data['selectedClients'] ?? []
            );
            break;

            /* Income Statement */
        case 'fetch_income_statement':
            $response = IncomeStatementActions::fetch_graph_data_points(
                $data['startDate'],
                $data['endDate'],
                $data['selectedStores'],
            );
            break;
        default:
            break;

            /* Customer Aged Summary */
        case 'customer_aged_summary': 
            $response = [
                'status' => true, 
                'data' => CustomerAgedSummary::fetch_customer_aged_summary(
                    $data['storeId'], 
                    null, 
                    $data['tillDate'], 
                    $data['sortAscending'],
                    null,
                    exclude_self: 1,
                    exclude_clients: 1,
                    omit_credit_records: intval($data['omitCreditRecords'] ?? '0'),
                ),
            ];
            break;

            /* Customer Summary */
        case 'customer_summary':
            $response = CustomerSummary::fetch(
                $data,
                intval($_SESSION['store_id']),
            );
            break;

            /* Stats */
        case 'stats':
            $response = Stats::stats();
            break;

            /* Item Frequency */
        case 'item_frequency':
            $response = Inventory::frequency($data['partId'] ?? null, $data['startDate'] ?? null, $data['endDate'] ?? null);
            break;
    }

    // Send Response Back 
    echo Shared::remove_item_tag_from_string(json_encode($response, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR));
}
