<?php
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/balance_sheet.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/reports/customer_aged_summary.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/shared.php";

class CreditNote {

    /**
     * This method will create Credit Note.
     * @param data
     * @return array
     */
    private static function create_credit_note(array $data): array {
        $db = get_db_instance();
        try {
            // Begin Transaction
            $db -> beginTransaction();

            // Validate Details.
            $validated_details = Shared::validate_details_for_credit_and_debit_note($data, txn_type: 'credit_note');

            // Client Id 
            $client_id = $validated_details['client_id'];

            // Check for One time Customer 
            if($client_id === ONE_TIME_CUSTOMER_ID) throw new Exception('One time customer is Disabled.');

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Store ID
            $store_id = $validated_details['store_id'];

            // Check for Balance Due
            Shared::allow_balance_due_check_for_client($client_id, $store_id);

            // Save Last Statement
            CustomerAgedSummary::save_last_statement($store_id, $db);

            // Txn date
            $date = $validated_details['txn_date'];

            // Amounts
            $sum_total = $validated_details['sum_total'];
            $sub_total = $validated_details['sub_total'];
            $pst_tax = $validated_details['pst_tax'];
            $gst_hst_tax = $validated_details['gst_hst_tax'];
            $txn_discount = $validated_details['txn_discount'];

            // Txn Details
            $details = $data['details'];

            // Remove Keys
            Shared::remove_keys($details, Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // Update Amount Owing of Client
            Client::update_amount_owing_of_client($client_id, -$sum_total, $db);

            // Insert into Database 
            $query = <<<'EOS'
            INSERT INTO credit_note 
            (
                client_id,
                `date`,
                credit_amount,
                sum_total,
                sub_total,
                pst_tax,
                gst_hst_tax,
                txn_discount,
                details,
                store_id,
                notes,
                sales_rep_id,
                disable_federal_taxes,
                disable_provincial_taxes
            )
            VALUES
            (
                :client_id,
                :date,
                :credit_amount,
                :sum_total,
                :sub_total,
                :pst_tax,
                :gst_hst_tax,
                :txn_discount,
                :details,
                :store_id,
                :notes,
                :sales_rep_id,
                :disable_federal_taxes,
                :disable_provincial_taxes
            );
            EOS;

            // Values to be inserted into DB
            $values = [
                ':client_id' => $client_id,
                ':date' => $date,
                ':credit_amount' => $sum_total,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':store_id' => $store_id,
                ':notes' => $validated_details['notes'],
                ':sales_rep_id' => $data['salesRepId'],
                ':disable_federal_taxes' => $data['clientDetails']['disableFederalTaxes'] ?? 0,
                ':disable_provincial_taxes' => $data['clientDetails']['disableProvincialTaxes'] ?? 0,
            ];

            // Store ID
            $store_id = intval($_SESSION['store_id']);

            $bs_affected_accounts = AccountsConfig::ACCOUNTS;
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::ACCOUNTS_RECEIVABLE,
                -$sum_total,
            );

            // Adjust PST Tax
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                -$pst_tax,
            );

            // Adjust GST/HST Tax offset
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                -$gst_hst_tax,
            );

            // Adjust offset 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::TOTAL_DISCOUNT,
                -$txn_discount
            );

            // Update Balance Sheet
            BalanceSheetActions::update_from($bs_affected_accounts, $date, $store_id, $db);

            /* CHECK FOR ANY ERROR */
            assert_success();

            // Insert into DB
            $statement = $db -> prepare($query);
            $statement -> execute($values);

            // Get Credit Note ID
            $credit_note_id = $db -> lastInsertId();
            if($credit_note_id === false) throw new Exception('Unable to create Credit Note.');

            /* COMMIT */
            if($db -> inTransaction()) $db -> commit();
            return ['status' => true, 'data' => $credit_note_id];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will revert old transaction.
     * @param data Initial Data
     * @param bs_affected_accounts
     * @param db
     * @param data
     */
    private static function revert_old_transaction(array $data, array &$bs_affected_accounts, PDO &$db): void {
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::ACCOUNTS_RECEIVABLE,
            $data['sumTotal'],
        );

        // Adjust GST/HST Tax
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::GST_HST_CHARGED_ON_SALE,
            $data['gstHSTTax'],
        );

        // Adjust PST Tax
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::PST_CHARGED_ON_SALE,
            $data['pstTax'],
        );

        // Total Discount
        BalanceSheetActions::update_account_value(
            $bs_affected_accounts,
            AccountsConfig::TOTAL_DISCOUNT,
            $data['txnDiscount'],
        );
    }

    /**
     * This method will update credit note.
     * @param data
     * @return array
     */
    private static function update_credit_note(array $data): array {
        $db = get_db_instance();
        try {
            $db -> beginTransaction();

            // Txn Id
            $txn_id = is_numeric($data['id'] ?? null) ? intval($data['id']) : null;
            if(!is_numeric($txn_id)) throw new Exception('Invalid Credit Note Id.');

            // Fetch Intial Transaction
            $initial_details = Shared::fetch_initial_details_of_txn(
                CREDIT_NOTE, 
                $txn_id,
                $db,
            );

            // Set Initial Details
            Shared::set_initial_client_details($data['initial'], $initial_details);

            // Validate Details.
            $validated_details = Shared::validate_details_for_credit_and_debit_note($data, txn_type: 'credit_note');

            // Is Transaction Detail Changed
            $is_transaction_detail_changed = $validated_details['is_transaction_detail_changed'];

            // Client Id 
            $client_id = $validated_details['client_id'];

            // Check for One time Customer 
            if($client_id === ONE_TIME_CUSTOMER_ID) throw new Exception('One time customer is Disabled.');

            // Check for Fresh Copy of Client.
            Client::check_fresh_copy_of_client($client_id, $data['clientDetails']['lastModifiedTimestamp'], $db);

            // Store ID
            $store_id = $validated_details['store_id'];

            // Check for Balance Due
            Shared::allow_balance_due_check_for_client($client_id, $store_id);

            // Txn date
            $date = $validated_details['txn_date'];

            // Amounts
            $sum_total = $validated_details['sum_total'];
            $sub_total = $validated_details['sub_total'];
            $pst_tax = $validated_details['pst_tax'];
            $gst_hst_tax = $validated_details['gst_hst_tax'];
            $txn_discount = $validated_details['txn_discount'];

            // Txn Details
            $details = $data['details'];

            // Remove Keys
            Shared::remove_keys($details, Shared::KEYS_TO_REMOVE_SI_CN_DN_QT);

            // Versions
            $versions = Shared::fetch_latest_required_details_for_transaction($txn_id, CREDIT_NOTE, $data, $db);

            // Balance Sheet
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            // Revert Old Transaction
            self::revert_old_transaction($data['initial'], $bs_affected_accounts, $db);

            // Update Balance Sheet
            BalanceSheetActions::update_from($bs_affected_accounts, $data['initial']['txnDate'], $store_id, $db);

            // Update Client Amount Owing.
            Shared::update_client_amount_owing_for_credit_and_debit_note($data, $sum_total, $db, mode: 'credit_note');

            // Reset
            $bs_affected_accounts = AccountsConfig::ACCOUNTS;

            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::ACCOUNTS_RECEIVABLE,
                -$sum_total,
            );

            // Adjust PST Tax
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::PST_CHARGED_ON_SALE,
                -$pst_tax,
            );

            // Adjust GST/HST Tax offset
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::GST_HST_CHARGED_ON_SALE,
                -$gst_hst_tax,
            );

            // Adjust offset 
            BalanceSheetActions::update_account_value(
                $bs_affected_accounts,
                AccountsConfig::TOTAL_DISCOUNT,
                -$txn_discount
            );

            // Update Balance Sheet
            BalanceSheetActions::update_from($bs_affected_accounts, $date, $store_id, $db);

            // Check for Any Changes in Details. If yes, add to versions
            if($is_transaction_detail_changed) {
                if(is_null($versions)) $versions = [];
                $versions[Utils::get_utc_unix_timestamp_from_utc_str_timestamp($data['lastModifiedTimestamp'])] = $data['initial']['details'];
            }

            // Update Credit Note
            $query = <<<'EOS'
            UPDATE 
                credit_note 
            SET 
                client_id = :client_id,
                date = :date,
                credit_amount = :credit_amount,
                sum_total = :sum_total,
                sub_total = :sub_total,
                pst_tax = :pst_tax,
                gst_hst_tax = :gst_hst_tax,
                txn_discount = :txn_discount,
                details = :details,
                notes = :notes,
                versions = :versions,
                modified = CURRENT_TIMESTAMP 
            WHERE
                id = :id;
            EOS;

            $params = [
                ':client_id' => $client_id,
                ':date' => $date,
                ':credit_amount' => $sum_total,
                ':sum_total' => $sum_total,
                ':sub_total' => $sub_total,
                ':pst_tax' => $pst_tax,
                ':gst_hst_tax' => $gst_hst_tax,
                ':txn_discount' => $txn_discount,
                ':details' => json_encode($details, JSON_THROW_ON_ERROR),
                ':notes' => $validated_details['notes'],
                ':versions' => is_array($versions) ? json_encode($versions, JSON_THROW_ON_ERROR) : null,
                ':id' => $txn_id,
            ];

            // CHECK FOR ANY ERROR
            assert_success();

            $statement = $db -> prepare($query);
            $is_successful = $statement -> execute($params);

            // Check for Successful Update
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('Unable to Update Credit Note.');

            if($db -> inTransaction()) $db -> commit();
            return ['status' => true];
        }
        catch(Exception $e) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will process Credit Note.
     * @param data
     * @return array
     */
    public static function process(array $data): array {
        try {
            // Result
            $result = [];

            switch($data['action']) {
                case 'create_txn': $result = self::create_credit_note($data); break;
                case 'update_txn': $result = self::update_credit_note($data); break;
                case 'fetch_transaction_by_id': $result = Shared::fetch_credit_or_debit_transaction_by_id($data['transaction_id'], CREDIT_NOTE); break;
                case 'txn_search': $result = Shared::search($data); break;
                case 'print': $result = Shared::generate_pdf($data['txn_queue']); break;
                case 'txn_email': $result = Shared::email_si_sr_cn_dn_qt($data['txn_queue'][0]['id'], $data['txn_queue'][0]['type']); break;
                default: throw new Exception('Invalid Operation.');
            }
            return $result;
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }
}