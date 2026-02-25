<?php 
/**
 * This module defines user management capability.
 * @author Dewang Saxena
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/csrf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/client.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/user_management.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

/**
 * Session Management
 */
class SessionManagement {

    /* Write Operations */
    private const WRITE_OPERATIONS = [
        /* Client */
        Client::ADD,
        Client::UPDATE,

        /* User Management */
        UserManagement::ADD,
        UserManagement::UPDATE_PASSWORD,
        UserManagement::UPDATE_STATUS,
        UserManagement::CHANGE_USER_ACCESS_LEVEL,
        UserManagement::CHANGE_USER_STORE_ACCESS,

        /* Inventory */ 
        Inventory::ADD,
        Inventory::UPDATE,
        Inventory::UPDATE_PROFIT_MARGINS,
        Inventory::ADJUST_INVENTORY,

        /* Transactions */ 
        Shared::CREATE_TXN,
        Shared::UPDATE_TXN,
        Shared::TRANSFER_INVOICE,
        Shared::CONVERT_QUOTE_TO_INVOICE,
    ];

    /**
     * This method will check the user for write access.
     * @param user_id
     * @throws Exception
     */
    private static function has_write_access(int $user_id, PDO &$db): void {
        $statement = $db -> prepare('SELECT access_level FROM users WHERE id = :id;');
        $statement -> execute([':id' => $user_id]);
        $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
        if(isset($result[0])) {
            $result = $result[0];
            if($result['access_level'] === READ_ONLY) throw new Exception('Insufficient Permission to update.');
        }
        else throw new Exception('User Not Found > has_write_access');
    }

    /**
     * This method will check whether the user has write permission or not.
     * @param user_id
     * @param operation
     * @param db
     * @return array
     */
    public static function has_write_permission(int $user_id, string $operation, PDO &$db) : array {
        try {
            // Check for Access.
            if(in_array($operation, self::WRITE_OPERATIONS)) {

                // Disable Write Operations for Vancouver
                if($_SESSION['store_id'] == StoreDetails::VANCOUVER) throw new Exception('Store Disabled for Write Operations.');
                self::has_write_access($user_id, $db);
            }
            return ['status' => true];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }
}

class UserManagement {

    /* Operation Tags */
    public const ADD = 'um_add';
    public const AUTHENTICATE = 'um_authenticate';
    public const UPDATE_PASSWORD = 'um_update_password';
    public const UPDATE_STATUS = 'um_update_status';
    public const CHANGE_USER_ACCESS_LEVEL = 'um_change_user_access_level';
    public const CHANGE_USER_STORE_ACCESS = 'um_change_user_store_access';
    public const FETCH = 'um_fetch';
    public const LOGOUT = 'um_logout';

    /** Root User ID */
    public const ROOT_USER_ID = 8;

    /* Boss */ 
    private const BOSS_USER_ID = 10005;

    /* Password Hashing Options */ 
    private const HASH_OPTIONS = ['cost' => 10,];

    /**
     * This method will generate password hash.
     * @param password 
     * @return string 
     */
    private static function password_hash(#[\SensitiveParameter] string $password) : string {
        return password_hash(trim($password), PASSWORD_DEFAULT, self::HASH_OPTIONS);
    }

    /**
     * This method will create user.
     * @param data
     * @param do_root_check
     * @return array
     */
    public static function add(#[\SensitiveParameter] array $data, bool $do_root_check = true): array {
        $db_instance = get_db_instance();
        try {
            if($do_root_check == true) self::verify_root_user();

            // Begin Transaction
            $db_instance -> beginTransaction();

            // Query
            $query = <<<'EOS'
            INSERT INTO users
            (
                __ROOT_ID_COLUMN__,
                `name`,
                `username`,
                `password`,
                access_level,
                store_id,
                has_access
            )
            VALUES
            (
                __ROOT_ID_VALUE__,
                :name,
                :username,
                :password,
                :access_level,
                :store_id,
                :has_access
            );
            EOS;

            // Verify Details
            $name = trim($data['name']);
            $username = trim($data['username']);
            $password = trim($data['password']);
            $access_level = intval($data['access_level']);
            $store_id = intval($data['store_id']);
            
            if(!isset($name[3])) throw new Exception('Full Name must be atleast 4 characters long.');
            else if(!isset($username[3])) throw new Exception('Username must be atleast 4 characters long.');
            else if(!isset($password[7])) throw new Exception('Password must be atleast 8 characters long.');
            else if(!in_array($access_level, ACCESS_LEVELS)) throw new Exception('Invalid Access Level.');
            else if(!array_key_exists($store_id, StoreDetails::STORE_DETAILS)) throw new Exception('Invalid Store.');

            // Replace
            $query = str_replace(
                '__ROOT_ID_COLUMN__,', 
                isset($data['id']) ? '`id`,' : '',
                $query
            );

            $query = str_replace(
                '__ROOT_ID_VALUE__,',
                isset($data['id']) ? ':id,' : '',
                $query
            );

            // Prepare Statement
            $statement = $db_instance -> prepare($query);

            // Params
            $params = [
                ':name' => trim($name),
                ':username' => trim($username),
                ':password' => self::password_hash($password),
                ':access_level' => $access_level,
                ':store_id' => $access_level === ADMIN ? 1 : $store_id,
                ':has_access' => 1,
            ];

            // Add ID.
            if(isset($data['id'])) $params[':id'] = self::ROOT_USER_ID;

            // Execute
            $statement -> execute($params);
            
            // Check for Success
            if($db_instance -> lastInsertId() === false) throw new Exception('Unable to add user.');

            assert_success();

            // Commit
            $db_instance -> commit();

            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db_instance -> inTransaction()) $db_instance -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will check whether the user is valid and returns the user id if valid.
     * Else it will return null
     * @return array
     */
    public static function authenticate(#[\SensitiveParameter] array $data): array {
        try {
            require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/inventory.php";

            // Credentials
            $username = trim($data['username']);
            $password = trim($data['password']);
            
            // DB instance
            $db_instance = get_db_instance();

            // Statement
            $statement = $db_instance -> prepare(<<<'EOS'
            SELECT 
                *
            FROM 
                users 
            WHERE 
                `username` = :username
            LIMIT 1;
            EOS);
            $statement -> execute([':username' => $username,]);
            $user_record = $statement -> fetchAll(PDO::FETCH_ASSOC);

            // Verify User Record Exists
            if(!isset($user_record[0])) throw new Exception('User not found.');

            // Check for User Access.
            if(intval($user_record[0]['has_access']) === 0) throw new Exception('Access Provoked.');

            // Validate the password 
            if(password_verify($password, $user_record[0]['password'])) {

                /* Check for Rehash */ 
                if(password_needs_rehash($password, PASSWORD_DEFAULT, self::HASH_OPTIONS)) {

                    /* Update The Password */ 
                    $status = self::update_password($user_record[0]['id'], $password);
                    if($status['status'] === false) {
                        throw new Exception('Cannot rehash password due to: '. $status['message']);
                    }
                }

                // Store ID
                if($user_record[0]['access_level'] == ADMIN || $user_record[0]['store_id'] === StoreDetails::ALL_STORES) {
                    $store_id = $data['store_id'] ?? null;
                }
                else $store_id = $user_record[0]['store_id'];

                // Store Id
                $store_id = intval($store_id);

                // Check for Store.
                if($store_id === StoreDetails::ALL_STORES || (is_numeric($store_id) === false) || (isset(StoreDetails::STORE_DETAILS[$store_id]) === false)) throw new Exception('Store ID not selected.');

                // CSRF token
                $csrf_token = CSRF::generate_token();

                // GST/HST Tax Rate
                $gst_hst_rax_rate = FEDERAL_TAX_RATE;
                if(StoreDetails::STORE_DETAILS[$store_id]['use_hst']) {
                    $gst_hst_rax_rate += StoreDetails::STORE_DETAILS[$store_id]['hst_tax_rate'];
                }
                
                // Prepare Object 
                $user_detail = [
                    'id' => $user_record[0]['id'],
                    'name' => $user_record[0]['name'],
                    'isDev' => $user_record[0]['id'] == self::ROOT_USER_ID ? 1: 0,
                    'accessLevel' => $user_record[0]['access_level'],
                    /* Check for Admin User */ 
                    'hasAccess' => $user_record[0]['has_access'],
                    'csrfToken' => $csrf_token,
                    'profitMargins' => Inventory::fetch_profit_margins($store_id),
                    'sessionId' => session_id(),
                    'sessionToken' => SESSION_TOKEN,

                    /* Store Details */ 
                    'storeDetails' => [
                        'id' => $store_id,
                        'location' => StoreDetails::STORE_DETAILS[$store_id]['name'],
                        'businessName' => StoreDetails::STORE_DETAILS[$store_id]['address']['name'],
                        'gstHSTTaxRate' => $gst_hst_rax_rate,
                        'pstTaxRate' => StoreDetails::STORE_DETAILS[$store_id]['pst_tax_rate'],
                        'cipherKeyThisStore' => StoreDetails::STORE_DETAILS[$store_id]['cipher_key'],
                    ],
                ];

                // Set CSRF Token in Session 
                if(session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[CSRF::KEY] = $csrf_token;
                    $_SESSION['store_id'] = $store_id;
                    $_SESSION['user_id'] = $user_record[0]['id'];
                    $_SESSION['access_level'] = $user_record[0]['access_level'];
                }

                assert_success();

                return ['status' => true, 'data' => $user_detail];
            }
            return ['status' => false, 'message' => 'Invalid User.'];
        }
        catch(Throwable $th) {
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will update the password hash.
     * @param user_id
     * @param password
     * @return array
     */
    private static function update_password(int $user_id, #[\SensitiveParameter] string $password) : array {
        // Get DB Instance
        $db_instance = get_db_instance();
        try {
            // Begin Transaction
            $db_instance -> beginTransaction();
            
            // Prepare Statement
            $statement = $db_instance -> prepare(<<<'EOS'
            UPDATE 
                users 
            SET 
                `password` = :password
            WHERE 
                id = :id;
            EOS);

            $params = [
                ':password' => self::password_hash($password),
                ':id' => $user_id,
            ];

            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) {
                throw new Exception('update_password > Cannot Update Password.');
            }   

            assert_success();

            // Commit
            $db_instance -> commit();
            return ['status' => true];
        }
        catch(Throwable $th) {

            // Rollback
            if($db_instance -> inTransaction()) $db_instance -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will change the password.
     * @param data
     * @return array
     */
    public static function change_password(#[\SensitiveParameter] array $data): array {
        try {
            // Get DB Instance
            $db = get_db_instance();

            // User id
            $user_id = intval($data['user_id']);

            // Check for Root User
            if($user_id === self::ROOT_USER_ID && intval($_SESSION['user_id']) !== self::ROOT_USER_ID) throw new Exception('Cannot Change Password for Root User.');

            // Check for Self.
            if($data['for'] === 'self') {

                // Validate Old Password
                $old_password = trim($data['old_password']);
                $statement = $db -> prepare('SELECT `password` FROM users WHERE id = :id;');
                $statement -> execute([':id' => $user_id]);
                $record = $statement -> fetchAll(PDO::FETCH_ASSOC);
                if(isset($record[0])) {
                    if(password_verify($old_password, $record[0]['password']) === false) throw new Exception('Old Password does not match.');
                }
                else throw new Exception('change_password > Unable to find user.');
            }
            else if($data['for'] !== 'self' && $data['for'] !== 'user') throw new Exception('Invalid Action.');

            // Validate New password
            $new_password = trim($data['new_password']);

            // Check for valid length
            if(!isset($new_password[7])) throw new Exception('Password must be atleast 8 characters long.');

            // Check for success
            $result = self::update_password($user_id, $new_password);
            if($result['status'] === false) throw new Exception($result['message']);

            assert_success();

            // Return true
            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will update access status.
     * @param data
     * @return array
     */
    public static function update_access_status(array $data) : array {
        try {
            // Get DB Instance
            $db = get_db_instance();

            // Verify Root User
            self::verify_root_user();

            // Begin txn
            $db -> beginTransaction();

            // Prepare
            $statement = $db -> prepare(<<<'EOS'
            UPDATE 
                users 
            SET 
                has_access = :has_access
            WHERE 
                id = :id
            AND
                id != :root_user_id;
            EOS);

            $params = [
                ':id' => $data['user_id'],
                ':has_access' => $data['has_access'],
                ':root_user_id' => self::ROOT_USER_ID,
            ];

            // Execute
            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('update_access_status > Cannot Update Status.');

            assert_success();

            // Commit
            $db -> commit();

            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will verify root user.
     */
    private static function verify_root_user(): void {
        if(self::is_root_user() === false) throw new Exception('Invalid Access');
    }

    /**
     * This method will change user access level.
     * @param data
     * @return array
     */
    public static function change_user_access_level(array $data) : array {
        try {
            // Get DB Instance
            $db = get_db_instance();

            // Verify Root User
            self::verify_root_user();

            // Begin txn
            $db -> beginTransaction();

            // Prepare
            $statement = $db -> prepare(<<<'EOS'
            UPDATE
                users 
            SET 
                access_level = :access_level
            WHERE
                id = :id
            AND 
                id != :root_user_id;
            EOS);

            // Params
            $params = [
                ':access_level' => $data['access_level'],
                ':id' => $data['user_id'],
                ':root_user_id' => self::ROOT_USER_ID,
            ];

            // Execute
            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('change_user_access_level > Cannot Update Access Level.');

            assert_success();

            // Commit
            $db -> commit();

            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will change user store access.
     * @param data
     * @return array
     */
    public static function change_user_store_access(array $data) : array {
        try {
            // Get DB Instance
            $db = get_db_instance();

            // Verify Root User
            self::verify_root_user();

            // Begin txn
            $db -> beginTransaction();

            // Prepare
            $statement = $db -> prepare(<<<'EOS'
            UPDATE
                users 
            SET 
                store_id = :store_id
            WHERE
                id = :id
            AND 
                id != :root_user_id;
            EOS);

            // Params
            $params = [
                ':store_id' => $data['store_id'],
                ':id' => $data['user_id'],
                ':root_user_id' => self::ROOT_USER_ID,
            ];

            // Execute
            $is_successful = $statement -> execute($params);
            if($is_successful !== true || $statement -> rowCount() < 1) throw new Exception('um_change_user_store_access > Cannot Update Store Access.');

            assert_success();

            // Commit
            $db -> commit();

            return ['status' => true];
        }
        catch(Throwable $th) {
            if($db -> inTransaction()) $db -> rollBack();
            return ['status' => false, 'message' => $th -> getMessage()];
        }
    }

    /**
     * This method will return all users.
     * @param params
     * @return 
     */
    public static function fetch(array $params): array {
        $db = get_db_instance();

        // Query
        $query = <<<'EOS'
        SELECT 
            id, 
            `name`,
            `username`,
            access_level,
            store_id,
            has_access
        FROM 
            users
        WHERE 
            __STATEMENT__
            id NOT IN (:placeholder)
            __SEWAK_ACCESS__;      
        EOS;

        // Excluded Users
        $excluded_users = [
            // HARD CODE MYSELF OUT :)
            self::ROOT_USER_ID,
        ];

        // Parts
        if(SYSTEM_INIT_HOST === PARTS_HOST) $excluded_users[]= self::BOSS_USER_ID;

        $results = Utils::mysql_in_placeholder_pdo_substitute(
            $excluded_users,
            $query,
        );

        $query = $results['query'];
        $values = $results['values'];

        // Store Id
        $store_id = intval($_SESSION['store_id']);

        // Fetch for Specific Store 
        if(isset($params['store_id'])) {
            $query = str_replace('__STATEMENT__', ' (store_id = :store_id OR access_level = 2) AND ', $query);
            $values[':store_id'] = $store_id;
        }
        // Fetch Specific Type
        else if (isset($params['type']) && is_numeric($params['type'])) {
            $query = str_replace('__STATEMENT__', ' access_level = :access_level AND store_id = :store_id AND ', $query);
            $values[':access_level'] = $params['type'];
            $values[':store_id'] = $store_id;
        } 
        else $query = str_replace('__STATEMENT__', '', $query);

        if(SYSTEM_INIT_HOST === TENLEASING_HOST || SYSTEM_INIT_HOST === VANGUARD_HOST) {
            $query = str_replace(
                '__SEWAK_ACCESS__',
                ' OR id IN (10000)',
                $query,
            );
        }
        else if(SYSTEM_INIT_HOST === PARTS_HOST) {
            // Show Sewak in Edmonton Store Only.
            $query = str_replace(
                '__SEWAK_ACCESS__',
                $store_id === StoreDetails::EDMONTON ? ' OR id IN (10013)': '',
                $query,
            );
        }
        else {
            $query = str_replace(
                '__SEWAK_ACCESS__',
                '',
                $query,
            );
        } 

        // Prepare 
        $statement = $db -> prepare($query);

        // Execute 
        $statement -> execute($values);

        return ['status' => true, 'data' => $statement -> fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Logout 
     */
    public static function logout() : void {
        $_SESSION = [];
        session_unset();
        session_destroy();
    }

    /**
     * This method will check user access.
     * @param user_id
     * @param db
     * @return array
     */
    public static function check_access(int $user_id, PDO &$db): array {
        try {
            // Root User will Always have access.
            if($user_id === self::ROOT_USER_ID) return ['status' => true];
            $query = 'SELECT has_access FROM users WHERE id = :id AND has_access = 1;';
            $statement = $db -> prepare($query);
            $statement -> execute([':id' => $user_id]);
            $result = $statement -> fetchAll(PDO::FETCH_ASSOC);
            return ['status' => count($result) > 0 ? true : false];
        }
        catch(Exception $e) {
            return ['status' => false, 'message' => $e -> getMessage()];
        }
    }

    /**
     * This method will add a root user.
     */
    public static function add_root_user(): void {
        $data = [
            'id' => self::ROOT_USER_ID,
            'name' => 'Dewang Saxena',
            'username' => 'dewangs',
            'password' => '#&&vS}=NwJ2t7"rp7g/(vs*o.b+MpK7:',
            'access_level' => 0,
            'store_id' => 1,
        ];
        $ret = self::add($data, do_root_check: false);
        if($ret['status'] !== true) echo $ret['message'];
    }

    /**
     * This method will check whether the user is root.
     * @return bool
     */
    public static function is_root_user(): bool {
        return isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === self::ROOT_USER_ID;
    }

    /**
     * This method will fetch sales rep name.
     * @param sales_rep_id
     * @return string
     */
    public static function fetch_sales_rep_name(int $sales_rep_id): string {
        $db = get_db_instance();
        $statement = $db -> prepare('SELECT `name` FROM users WHERE id = :id;');
        $statement -> execute([':id' => $sales_rep_id]);
        return ($statement -> fetchAll(PDO::FETCH_ASSOC)[0]['name'] ?? '');
    } 
}
?>