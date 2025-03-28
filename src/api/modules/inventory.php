<?php

/**
 * This module will implements method to manage inventory.
 * @author Dewang Saxena, <dewang2610@gmail.com>
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');

require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/database.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/validate.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/csrf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

class Inventory {

    /* Operation Tags */
    public const ADD = 'inv_add';
    public const UPDATE = 'inv_update';
    public const FETCH = 'inv_fetch';
    public const FETCH_PROFIT_MARGINS = 'inv_fetch_profit_margins';
    public const UPDATE_PROFIT_MARGINS = 'inv_update_profit_margins';
    public const FETCH_ITEM_DETAILS_FOR_ADJUST_INVENTORY = 'inv_fetch_item_details_for_adjust_inventory';
    public const ADJUST_INVENTORY = 'inv_adjust_inventory';
    public const ITEM_DETAILS_FOR_TRANSACTIONS = 'inv_item_details_for_transactions';

    /**
     * Quantity Restriction Exempt Items
     */
    private const EXEMPT_ITEMS_ID_CAT_1 = [8813, 8812, 8816, 8817, 8814, 8811, 8809, 8827, 8824, 2538, 2539, 2540, 2545, 2546, 2547, 7522, 4369, 7521, 8205, 9364, 2495, 2500, 2496, 2492, 2493, 2494, 2488, 2489, 2490, 2508, 2511, 2514, 2517, 2518, 2520, 2521, 2522, 8831, 2527, 2528, 2529, 2536, 2501, 8838, 8823, 8819, 8855, 8820, 8851, 6434, 8833, 8835, 5581, 13116, 13115, 8806, 13124, 2515, 2560, 13125, 5588, 5587, 2561, 8847, 2504, 2505, 2507, 6454, 6455, 6453, 11609, 11608, 13113, 13114, 13120, 3678, 5158, 5171, 5590, 5593, 8830, 8754, 3662, 3663, 3666, 3667, 5615, 5613, 5614, 5610, 5589, 5611, 7628, 7620, 7623, 7622, 16189, 7626, 7625, 7624, 7627, 7621, 8853, 13575, 8852, 8856, 8854, 13119, 13121, 13118, 8832, 6434, 8825, 8828, 5609, 2491, 5668, 5259, 4901, 5270, 4911, 2508, 4936, 15060, 8808, 2543, 4144, 6465, 3672, 8837, 2537, 6463, 4145, 7820, 12614, 7821, 13574, 5168, 11781, 2556, 5798, 6835, 6833, 5797, 8840, 5799, 2549, 11477, 11476, 3628, 13576, 3610, 8829, 5592, 5591, 8743, 2497, 8804, 5660, 2499, 2498, 2524, 8805, 13117, 8742, 8822, 8821, 13579, 2531, 2532, 6842, 2533, 2534, 2551, 9152, 2553, 2554, 6641, 8839, 11472, 8842, 2541, 2542, 9020, 9021, 8761, 8758, 8767, 8751, 8752, 11790, 11791, 11784, 8781, 8780, 8784, 8749, 6841, 8757, 6840, 8786, 8782, 8783, 8784, 8750, 6025, 8745, 8753, 8763, 8764, 8760, 8746, 8775, 8743, 8744, 6640, 6022, 8779, 6023, 8766, 12892, 12492, 6978, 8848, 15076, 6900, 2559, 2558, 8849, 4929, 17330, 7512, 7513, 6960, 7504, 3962, 10705, 11341, 11342, 8145, 8789, 2530, 7309, 7499, 8947, 5293, 4099, 4098, 4115, 4116, 4181, 13444, 5380, 5379, 5383, 5384, 5385, 5386, 5387, 5533, 5531, 4090, 5536, 5537, 5538, 5539, 5540, 4261, 4263, 4264, 4349, 4266, 5532, 4388, 4430, 4392, 4393, 4394, 4395, 4396, 4471, 4473, 4479, 4208, 5218, 4948, 5461, 4131, 5262, 4904, 4216, 5250, 4961, 5468, 4158, 5296, 4910, 8879, 4960, 5467, 5221, 4958, 5466, 4157, 5269, 4962, 5469, 4160, 4945, 4217, 5223, 5471, 4160, 7901, 4914, 4219, 5227, 4969, 5475, 4640, 4915, 4221, 4222, 5235, 4971, 5477, 7902, 4075, 5230, 5233, 4973, 5479, 4641, 4213, 4135, 4950, 5462, 4134, 5263, 4953, 4209, 5216, 4951, 5460, 4133, 5261, 4903, 5484, 4200, 4223, 4248, 4249, 4250, 5238, 5224, 5226, 5228, 5232, 5234, 5236, 5237, 5240, 5241, 4963, 4965, 4966, 4970, 4972, 4974, 4976, 4076, 4977, 4978, 5458, 5470, 5473, 5476, 5478, 5480, 4161, 4162, 4163, 4165, 4168, 4169, 4170, 4171, 4172, 4173, 5288, 5287, 5271, 5272, 5273, 5274, 5275, 5277, 5278, 5280, 5281, 5282, 5284, 4968, 5285, 5286, 4913, 4912, 4916, 4919, 15171, 4920, 4921, 4922, 4923, 4924, 4925, 4926, 4928, 5488, 5489, 5490, 5491, 4106, 4107, 4108, 4109, 4110, 4111, 4927, 4212, 5220, 4955, 5463, 4140, 5266, 4907, 5487, 5215, 4949, 5260, 4902, 4211, 5219, 4954, 4136, 4138, 5264, 4905, 5485, 4345, 4346, 4347, 4348, 4351, 4352, 4350, 4478, 4265];
    private const EXEMPT_ITEMS_ID_CAT_2 = [17760, 10489, 3351, 10491, 17761, 12012, 12533, 12532, 10488, 3327, 3326, 11637, 3325, 9406, 11638, 17762, 11635, 17663, 17664, 11079, 17766, 17767, 17765, 17768, 17769, 17770, 17772, 11551, 17774, 17775, 17771, 17776, 17780, 17778, 17777, 4353, 17781, 17783, 17785, 17786, 17784, 17790, 17795, 17792, 17793, 17794, 17791];

    // EHC ITEMS
    // Discount and Taxes are disabled on these items.
    public const EHC_ITEMS = [
        21764, /* ENVIRONMENTAL FEE */
        25379, /* Environment FEE */
        22712, /* EHC FEE */
        26832, /* EHC ON OIL */
        33649, /* EHC ON OIL - JUG */
        19417, /* EHC-AB-333-C */
        24473, /* EHC-FEE */
        25219, /* EHCAB01 */
        31447, /* EHCAB02 */
        40057, /* EHCAB04 */
        40058, /* EHCAB06 */
    ];

    // Discount Disabled Items
    private const DISCOUNT_DISABLED_ITEMS = [
        StoreDetails::EDMONTON => [
            8080,
            7364,
            19730,
            12862,
            18937,
            14998,
            7319,
            13394,
            9178,
            23708,
            4892,
            23588,
            32130,

            /* New Flyer */
            2790,  /* S-28890 */ 
            15003,  /* Head Light */
            15004,  /* Head Light */
            8454, /* 4707p */
            4548, /* 4709 */
            6802, /* Brake Drum */
            12004, /* 171702 Turbo */
            22651, /* Door Handle */ 
            39, /* R21-6011 */
            45, /* R21-6011 */
            20992, /* Manifold Delete */
            26993, /* MIR3030TCL(Interax 3030) */
            38987, /* 1011500002A1 */
            38988, /* 1010600400 */
        ],
    ];

    // Item Details Tag
    // This will be prepended to encode value as string
    // This UUID v4 will be appended 
    // DO NOT CHANGE THIS.
    // IF CHANGED HERE, MAKE SURE TO CHANGE IN THE FRONT END AS WELL.
    public const ITEM_DETAILS_TAG = '85163d53-ace8-4140-b83c-1c89294f6464';

    /**
     * This method will remove item detail tag.
     * @param value
     * @return string
     */
    private static function remove_item_detail_tag(string $value): string {
        return str_replace(self::ITEM_DETAILS_TAG, '', $value);
    }

    /**
     * This method will check whether item is eligible for discount when creating a transaction.
     * @param item_id 
     * @param store_id
     * @return bool
     */
    private static function check_item_discount_disabled(int $item_id, int $store_id): bool {
        if(in_array($item_id, self::EHC_ITEMS)) return true;
        return isset(self::DISCOUNT_DISABLED_ITEMS[$store_id]) && in_array($item_id, self::DISCOUNT_DISABLED_ITEMS[$store_id]);
    }

    /**
     * This method will add items into the DB.
     * @param db
     * @param values 
     * @param initial_quantity
     * @param buying_cost
     * @return array
     */
    private static function add(PDO &$db, array $values, int $initial_quantity, float $buying_cost): array {
        $query = <<<'EOS'
        INSERT INTO
            items
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
            `last_sold`
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
            :last_sold
        );
        EOS;
        $statement = $db->prepare($query);
        $statement->execute($values);

        // Last Insert ID
        $last_insert_id = $db->lastInsertId();

        // Unable to Add Item
        if ($last_insert_id === false) return ['status' => false];

        // Add to inventory
        if ($initial_quantity > 0 && $buying_cost > 0) {
            // Build Params
            $params = [
                0 => [
                    'id' => $last_insert_id,
                    'identifier' => $values[':identifier'],
                    'quantity' => $initial_quantity,
                    'buyingCost' => $buying_cost,
                    'aisle' => '',
                    'shelf' => '',
                    'column' => '',
                ],
            ];

            // Store id 
            $store_id = intval($_SESSION['store_id']);

            // Adjust Inventory
            $ret = self::adjust_inventory($params, $store_id, $db);
            if ($ret['status'] === true) return ['status' => true];
            else return $ret;
        } else return ['status' => $last_insert_id !== false];
    }

    /**
     * This method will update item into the DB.
     * @param db
     * @param values 
     * @return array
     */
    private static function update(PDO &$db, array $values): array {
        // Item Id 
        $item_id = $values[':id'];

        /* Check for Latest Copy of Items Details */
        $query = 'SELECT `category`, prices, modified FROM items WHERE id = :id;';
        $statement = $db->prepare($query);
        $statement->execute([':id' => $item_id]);
        $record = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($record[0]['modified'] != $values[':last_modified_timestamp']) return [
            'message' => 'Cannot Update Stale Copy of Item. Reload the item and try again!.',
            'status' => false,
        ];

        // Check for Price Change
        $old_prices = json_decode($record[0]['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
        $new_prices = json_decode($values[':prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

        // Store ID
        $store_id = intval($_SESSION['store_id']);

        // If Prices does not exists for Store Id
        // Use 0 as base value.
        $old_buying_cost = $old_prices[$store_id]['buyingCost'] ?? 0;
        $new_buying_cost = $new_prices[$store_id]['buyingCost'];

        // Only Adjust for Inventory Type
        if ($record[0]['category'] === CATEGORY_INVENTORY && $old_buying_cost !== $new_buying_cost) {

            // Fetch Inventory Quantity
            $inventory_details = self::fetch_item_inventory_details_by_id([$item_id], $store_id, $db);
            if ($inventory_details['status'] !== true) throw new Exception($inventory_details['message']);
            else $inventory_details = $inventory_details['data'];

            // Existing Quantity
            $existing_quantity = $inventory_details[$item_id][$store_id]['quantity'] ?? 0;

            // Update The Balance Sheet
            $bs = AccountsConfig::ACCOUNTS;

            // Existing Value
            $existing_value = $existing_quantity * $old_buying_cost;

            // Remove Old Value
            BalanceSheetActions::update_account_value(
                $bs,
                AccountsConfig::INVENTORY_A,
                -$existing_value
            );

            // Calculate New Value
            $new_value = $existing_quantity * $new_buying_cost;
            BalanceSheetActions::update_account_value(
                $bs,
                AccountsConfig::INVENTORY_A,
                $new_value
            );

            // Adjust Balance Sheet
            BalanceSheetActions::update_from($bs, Utils::get_business_date($store_id), $store_id, $db);
        }

        $query = <<<'EOS'
        UPDATE items 
        SET
            `code` = :code,
            `identifier` = :identifier,
            `description` = :description,
            `oem` = :oem,
            `category` = :category,
            `unit` = :unit,
            `prices` = :prices,
            `account_assets` = :account_assets,
            `account_revenue` = :account_revenue,
            `account_cogs` = :account_cogs,
            `account_variance` = :account_variance,
            `account_expense` = :account_expense,
            `is_inactive` = :is_inactive,
            `is_discount_disabled` = :is_discount_disabled,
            `is_core` = :is_core,
            `memo` = :memo,
            `additional_information` = :additional_information,
            `reorder_quantity` = :reorder_quantity,
            modified = CURRENT_TIMESTAMP
        WHERE 
            `id` = :id
        AND 
            modified = :last_modified_timestamp;
        EOS;

        $statement = $db->prepare($query);
        $is_successful = $statement->execute($values);
        return ['status' => ($is_successful === true && $statement->rowCount() > 0)];
    }

    /**
     * This method will validate prices and update the prices.
     * @param prices
     * @return bool|string
     */
    private static function validate_prices(array &$prices): bool|string {
        // Fetch Stores 
        $stores = array_keys($prices);

        foreach ($stores as $store) {
            if (!is_numeric($prices[$store]['sellingPrice'] ?? null) || $prices[$store]['sellingPrice'] < 0) return 'Invalid Selling Price';
            else if (!is_numeric($prices[$store]['buyingCost'] ?? null) || $prices[$store]['buyingCost'] < 0) return 'Invalid Buying Cost';
            else {
                /* Preferred Price can be left blank */
                if (!is_numeric($prices[$store]['preferredPrice'] ?? null)) $prices[$store]['preferredPrice'] = 0;
                else if ($prices[$store]['preferredPrice'] < 0) return 'Invalid Preferred Price';
            }

            // Round of prices
            $prices[$store]['sellingPrice'] = Utils::round($prices[$store]['sellingPrice']);
            $prices[$store]['preferredPrice'] = Utils::round($prices[$store]['preferredPrice']);
            $prices[$store]['buyingCost'] = Utils::round($prices[$store]['buyingCost']);
        }
        return true;
    }

    /**
     * This method will add/update item.
     * @return string
     */
    public static function process_item(array $data): array {
        try {
            // Current store
            $store_id = $_SESSION['store_id'];

            // Create DB Instance
            $db = get_db_instance();

            // Begin Transaction
            $db->beginTransaction();

            // Sanitize Values
            $data = Utils::sanitize_values($data);

            // Validate Prices
            $ret = self::validate_prices($data['prices']);
            if ($ret !== true) throw new Exception($ret);

            // Validate Field Values
            if (!isset($data['identifier'][0])) throw new Exception('Identifier is Invalid.');
            else if (!isset($data['description'][0])) throw new Exception('Description is Invalid.');
            else if ($data['category'] != 0 && $data['category'] != 1) throw new Exception('Category is Invalid.');
            else if ($data['isCore'] != 0 && $data['isCore'] != 1) throw new Exception('Core is Invalid.');
            else if (!isset($data['unit'][0])) throw new Exception('Unit is Invalid.');
            if (is_numeric($data['reorderQuantity'][$store_id] ?? null)) {
                $data['reorderQuantity'][$store_id] = intval($data['reorderQuantity'][$store_id]);
                if ($data['reorderQuantity'][$store_id] < 0) throw new Exception('Reorder quantity is Invalid.');
            } else $data['reorderQuantity'][$store_id] = 0;

            // Validate Initial Quantity
            if ($data['action'] === 'add') {
                if (is_numeric($data['initialQuantity'] ?? null)) {
                    $data['initialQuantity'] = intval($data['initialQuantity']);
                    if ($data['initialQuantity'] < 0) throw new Exception('Reorder quantity is Invalid.');
                } else $data['initialQuantity'] = 0;
            }

            // Remove Tag
            $data['identifier'] = self::remove_item_detail_tag($data['identifier']);
            $data['description'] = self::remove_item_detail_tag($data['description']);

            // Values
            $values = [
                ':code' => "{$data['identifier']} {$data['description']}",
                ':identifier' => $data['identifier'],
                ':description' => $data['description'],
                ':oem' => $data['oem'] ?? null,
                ':category' => $data['category'],
                ':unit' => ucwords(strtolower($data['unit'])),
                ':prices' => json_encode($data['prices'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':account_assets' => $data['account']['assets'],
                ':account_revenue' => $data['account']['revenue'],
                ':account_cogs' => $data['account']['cogs'],
                ':account_variance' => $data['account']['variance'],
                ':account_expense' => $data['account']['expense'],
                ':is_core' => $data['isCore'],
                ':memo' => $data['memo'],
                ':additional_information' => $data['additionalInformation'],
                ':reorder_quantity' => json_encode($data['reorderQuantity'], JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ];

            // Select Action
            if ($data['action'] === 'inv_add') {
                $is_inactive = $data['isInactive'] ?? [];
                if (!array_key_exists($store_id, $is_inactive)) $is_inactive[$store_id] = 0;
                $values[':is_inactive'] = json_encode($is_inactive, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $values[':last_sold'] = '{}';
                $success_status = self::add($db, $values, $data['initialQuantity'], $data['prices'][$store_id]['buyingCost']);
            } else if ($data['action'] === 'inv_update') {
                $item_id = intval($data['id']);
                $values[':id'] = $item_id;
                $is_inactive = $data['isInactive'] ?? [];
                if (!array_key_exists($store_id, $is_inactive)) $is_inactive[$store_id] = 0;

                // Last Modified Timestamp
                $values[':last_modified_timestamp'] = $data['lastModifiedTimestamp'];

                // Convert to JSON 
                $values[':is_inactive'] = json_encode($is_inactive, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Disable Discount Flag
                $is_discount_disabled = $data['isDiscountDisabled'] ?? [];
                if(!array_key_exists($store_id, $is_discount_disabled)) $is_discount_disabled[$store_id] = 0;
                $values[':is_discount_disabled'] = json_encode($is_discount_disabled, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Update Item
                $response = self::update($db, $values);
                if ($response['status'] === false) throw new Exception($response['message']);

                // Set Flag
                $success_status = $response['status'];
            } else $success_status = false;

            // Assert
            assert_success();

            if ($success_status) {
                if ($db->inTransaction()) $db->commit();
                return ['status' => true];
            } else throw new Exception('Unable to Process Item.');
        } catch (Throwable $th) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will fetch the item detail.
     * @param params
     * @param store_id
     * @param set_id_as_index
     * @param db
     * @return array 
     */
    public static function fetch(array|null $params, int $store_id, bool $set_id_as_index = false, PDO &$db = null): array {
        try {
            // Get DB Instance
            if (is_null($db)) $db = get_db_instance();

            // Validate Store Id
            if (!is_numeric($store_id)) throw new Exception('Invalid Store Id.');

            // Fetch Profit Margins 
            $profit_margins = self::fetch_profit_margins($store_id)['profitMargins'];

            // Search Params
            $values = [];

            // Flag 
            $exclude_inactive = 0;

            if (isset($params['id'])) {
                $query = <<<'EOS'
                SELECT * FROM 
                    items 
                WHERE 
                    id = :id;
                EOS;
                $values[':id'] = $params['id'];
            } else if (isset($params['item_ids'])) {

                // Build Query
                $ret_value = Utils::mysql_in_placeholder(
                    $params['item_ids'],
                    <<<'EOS'
                    SELECT 
                        *
                    FROM 
                        items 
                    WHERE 
                        id IN (:placeholder);
                    EOS
                );

                // Set Params
                $query = $ret_value['query'];
                $values = $ret_value['values'];
            } else if (isset($params['term'])) {
                $query = <<<'EOS'
                SELECT 
                    * 
                FROM 
                    items 
                WHERE 
                    identifier LIKE :term
                    OR description LIKE :term
                    OR oem LIKE :term
                EOS;
                $values[':term'] = self::remove_item_detail_tag('%' . $params['term'] . '%');
                $exclude_inactive = intval($params['exclude_inactive'] ?? 0);

                // Add Limit
                $query .= ' LIMIT 100;';
            } else throw new Exception('Invalid Request.');

            // Fetch Data
            $statement = $db->prepare($query);
            $statement->execute($values);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);
            $items = [];
            $items_id = [];

            foreach ($records as $record) {

                // Is Inactive
                $is_inactive = json_decode($record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Add Key if not exists
                if (!array_key_exists($store_id, $is_inactive)) $is_inactive[$store_id] = 0;

                // Is Discount Disabled
                $is_discount_disabled = json_decode($record['is_discount_disabled'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(!array_key_exists($store_id, $is_discount_disabled)) $is_discount_disabled[$store_id] = 0;

                /* Include Only Active Items */
                if (($exclude_inactive === 1 && $is_inactive[$store_id] === $exclude_inactive) === false) {
                    $item_id = $record['id'];
                    $items_id[] = $item_id;
                    $items[$item_id] = [
                        'id' => $item_id,
                        'identifier' => self::ITEM_DETAILS_TAG . $record['identifier'],
                        'description' => self::ITEM_DETAILS_TAG . $record['description'],
                        'oem' => $record['oem'],
                        'category' => $record['category'],
                        'unit' => $record['unit'],
                        'prices' => json_decode($record['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                        'account' => [
                            'assets' => $record['account_assets'],
                            'revenue' => $record['account_revenue'],
                            'cogs' => $record['account_cogs'],
                            'variance' => $record['account_variance'],
                            'expense' => $record['account_expense'],
                        ],
                        'isCore' => $record['is_core'],
                        'memo' => $record['memo'],
                        'additionalInformation' => $record['additional_information'],
                        'reorderQuantity' => json_decode($record['reorder_quantity'], associative: true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                        'profitMargins' => $profit_margins,
                        /* Default */
                        'quantity' => 0,
                        'quantitiesAllStores' => [],
                        'lastModifiedTimestamp' => $record['modified'],
                        'quantity' => 0,
                        'aisle' => '',
                        'shelf' => '',
                        'column' => '',
                        'disableDiscount' => false,
                    ];

                    /* Check whether item discount is disabled. */
                    if(isset($is_discount_disabled[$store_id]) && $is_discount_disabled[$store_id] == 1) $items[$item_id]['disableDiscount'] = true;

                    // Add Is Inactive 
                    $items[$item_id]['isInactive'] = $is_inactive;

                    // Add Discount Disabled Flag
                    $items[$item_id]['isDiscountDisabled'] = $is_discount_disabled;

                    // Last Sold
                    $last_sold = json_decode($record['last_sold'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                    if(isset($last_sold[$store_id])) {
                        $last_sold = Utils::convert_date_to_human_readable($last_sold[$store_id]);
                    }
                    else $last_sold = 'N/A';

                    // Update
                    $items[$item_id]['lastSold'] = $last_sold;
                }
            }

            // Fetch Inventory Details for Items...
            if (count($items) > 0) {
                $inventory_details = self::fetch_item_inventory_details_by_id($items_id, db: $db);
                if (count($inventory_details['data']) > 0) {

                    $all_items = $inventory_details['data'];
                    $items_keys = array_keys($all_items);
                    foreach ($items_keys as $item_id) {
                        $current_item = $all_items[$item_id];
                        if (isset($current_item[$store_id]) === true) {
                            $item_details_current_store = $current_item[$store_id];
                            $items[$item_id]['quantity'] = $item_details_current_store['quantity'];
                            $items[$item_id]['aisle'] = $item_details_current_store['aisle'] ?? '';
                            $items[$item_id]['shelf'] = $item_details_current_store['shelf'] ?? '';
                            $items[$item_id]['column'] = $item_details_current_store['column'] ?? '';
                        }

                        // Add Details for All Stores
                        $all_stores = array_keys($current_item);
                        $quantities_all_stores = [];
                        foreach ($all_stores as $store) {
                            $quantities_all_stores[$store] = $current_item[$store]['quantity'] ?? 0;
                        }
                        $items[$item_id]['quantitiesAllStores'] = $quantities_all_stores;
                    }
                }
            }

            // Format results
            $formatted_results = [];
            if ($set_id_as_index) {
                foreach ($items as $item) {
                    $id = $item['id'];
                    $formatted_results[$id] = $item;
                }
            } else foreach ($items as $item) $formatted_results[] = $item;

            return ['status' => true, 'data' => $formatted_results];
        } catch (Throwable $th) {
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will fetch price margins.
     * @param store_id
     * @param db
     * @return array
     */
    public static function fetch_profit_margins(int $store_id, PDO | null &$db = null): array {
        try {
            if (is_null($db)) $db = get_db_instance();
            $statement = $db->prepare('SELECT profit_margins, modified FROM store_details WHERE id = :store_id;');
            $statement->execute([':store_id' => $store_id]);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!isset($records[0]['profit_margins'])) throw new Exception('Unable to fetch Price Margins.');
            $profit_margins = json_decode($records[0]['profit_margins'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

            // Set Default Key
            if (!isset($profit_margins[DEFAULT_PROFIT_MARGIN_KEY])) $profit_margins[DEFAULT_PROFIT_MARGIN_KEY] = 0;

            assert_success();
            return ['profitMargins' => $profit_margins, 'lastModifiedTimestamp' => $records[0]['modified']];
        } catch (Throwable $th) {

            // Default 
            return ['profitMargins' => [DEFAULT_PROFIT_MARGIN_KEY => 0], 'lastModifiedTimestamp' => null];
        }
    }

    /**
     * This method will update price margins.
     * @param data
     * @return array 
     */
    public static function update_profit_margins(array $data): array {
        $db = get_db_instance();
        try {
            // Profit Margins
            $profit_margins = $data['profitMargins'] ?? [];

            // Item Prefixes
            $item_prefixes = array_keys($profit_margins);

            // Validate Keys
            foreach ($item_prefixes as $key) {
                if (!is_numeric($profit_margins[$key] ?? null)) throw new Exception("$key is Invalid.");
                $profit_margins[$key] = Utils::round(floatval($profit_margins[$key]));
                if ($profit_margins[$key] <= 0) throw new Exception("$key margin must be non-zero positive.");
            }

            // Check for Default Key
            if (isset($profit_margins[DEFAULT_PROFIT_MARGIN_KEY]) === false) throw new Exception('"' . DEFAULT_PROFIT_MARGIN_KEY . '" key is required.');

            // Begin transaction
            $db->beginTransaction();

            // Prepare statement
            $statement = $db->prepare(<<<'EOS'
            UPDATE 
                store_details 
            SET 
                profit_margins = :profit_margins,
                modified = CURRENT_TIMESTAMP
            WHERE 
                id = :store_id
            AND 
                modified = :last_modified_timestamp;
            EOS);

            // Params
            $params = [
                ':profit_margins' => json_encode($profit_margins, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                ':store_id' => $_SESSION['store_id'],
                ':last_modified_timestamp' => $data['lastModifiedTimestamp'],
            ];

            // Execute
            $is_successful = $statement->execute($params);

            // Check for success
            if ($is_successful !== true || $statement->rowCount() < 1) throw new Exception('Unable to update price margins.');

            assert_success();

            // Commit changes
            $db->commit();

            // Fetch Latest
            return ['status' => true, 'data' => self::fetch_profit_margins($_SESSION['store_id'], $db)];
        } catch (Throwable $th) {
            if ($db->inTransaction()) $db->rollBack();
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will fetch item inventory details by ids and store id.
     * @param ids
     * @param store_id
     * @return array 
     */
    public static function fetch_item_inventory_details_by_id(array $ids, ?int $store_id = null, PDO &$db = null): array {
        try {
            $query = <<<EOS
            SELECT 
                item_id,
                quantity,
                `aisle`,
                `shelf`,
                `column`,
                store_id
            FROM 
                inventory 
            WHERE 
                item_id IN (:placeholder)
            EOS;
            $ret = Utils::mysql_in_placeholder($ids, $query);
            $values = $ret['values'];
            $query = $ret['query'];

            // Add Store Id.
            if (is_numeric($store_id)) $query .= " AND store_id = $store_id ";

            // Create new Connection
            if ($db === null) $db = get_db_instance();

            $statement = $db->prepare("$query;");
            $statement->execute($values);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);
            $results = [];
            $store_id = null;
            foreach ($records as $record) {
                $store_id = intval($record['store_id']);
                $item_id = intval($record['item_id']);
                if (!isset($results[$item_id])) $results[$item_id] = [];
                $results[$item_id][$store_id] = [
                    'item_id' => $item_id,
                    'quantity' => $record['quantity'],
                    'aisle' => $record['aisle'],
                    'shelf' => $record['shelf'],
                    'column' => $record['column'],
                ];
            }

            return ['status' => true, 'data' => $results];
        } catch (Throwable $th) {
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will fetch item details for adjusting inventory and for transactions.
     * @param search_term
     * @param store_id
     * @return array 
     */
    public static function fetch_item_details_for_adjust_inventory(string $search_term, int $store_id): array {
        try {
            $db = get_db_instance();

            // Statement 
            $statement = $db->prepare(<<<'EOS'
            SELECT 
                it.id, 
                it.identifier,
                it.description,
                it.unit,
                it.prices,
                it.account_assets,
                it.is_inactive
            FROM 
                items AS it
            WHERE 
                code LIKE :code
            LIMIT 100;
            EOS);

            // Remove Item Detail tag
            $search_term = self::remove_item_detail_tag($search_term);

            // Execute statement
            $statement->execute([':code' => "%$search_term%"]);

            // Fetch Records
            $item_records = $statement->fetchAll(PDO::FETCH_ASSOC);

            // Return with no record.
            if (!isset($item_records[0])) return ['status' => true, 'data' => []];

            // Response
            $response = [];

            // Fetch Item Location from Inventory
            $ids = [];
            foreach ($item_records as $record) {

                $is_inactive = json_decode($record['is_inactive'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

                // Skip Inactive Items.
                if (isset($is_inactive[$store_id]) && $is_inactive[$store_id] === 1) continue;

                $ids[] = $record['id'];
                $prices = json_decode($record['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                $response[$record['id']] = [
                    'id' => $record['id'],
                    'identifier' => self::ITEM_DETAILS_TAG . $record['identifier'],
                    'description' => self::ITEM_DETAILS_TAG . $record['description'],
                    'unit' => $record['unit'],
                    'buyingCost' => $prices[$store_id]['buyingCost'],
                    'aisle' => '',
                    'shelf' => '',
                    'column' => '',
                ];
            }

            // Formatted Results
            $formatted_results = [];
            if (count($ids) > 0) {
                // Query
                $query = <<<EOS
                SELECT 
                    item_id,
                    aisle,
                    shelf,
                    `column`,
                    quantity,
                    `modified`
                FROM 
                    inventory 
                WHERE  
                    item_id IN (:placeholder)
                AND
                    store_id = {$_SESSION['store_id']};
                EOS;
                
                $ret = Utils::mysql_in_placeholder($ids, $query);
                $values = $ret['values'];
                $query = $ret['query'];

                $statement = $db->prepare($query);
                $statement->execute($values);
                $inv_records = $statement->fetchAll(PDO::FETCH_ASSOC);
                $no_of_records = count($inv_records);
                for ($i = 0; $i < $no_of_records; ++$i) {
                    $current_record = $inv_records[$i];
                    $item_id = $current_record['item_id'];
                    $response[$item_id]['aisle'] = $current_record['aisle'];
                    $response[$item_id]['shelf'] = $current_record['shelf'];
                    $response[$item_id]['column'] = $current_record['column'];
                    $response[$item_id]['existingQuantity'] = $current_record['quantity'];
                    $timestamp = $current_record['modified'];
                    $local_timestamp = Utils::convert_utc_str_timestamp_to_localtime(
                        $timestamp,
                        $store_id,
                    );
                    $date = explode(' ', $local_timestamp);
                    $time = $date[1]. ' '. $date[2];
                    if(count($date) > 0) {
                        $date = Utils::convert_date_to_human_readable(Utils::get_YYYY_mm_dd($local_timestamp));
                        $current_date = Utils::convert_date_to_human_readable(Utils::get_business_date($store_id));
                        if($current_date === $date) $date = 'Today';
                        else {
                            $diff = Utils::get_difference_from_current_date($current_date, $local_timestamp, $store_id);
                            if($diff['d'] <= 1 && $diff['m'] === 0 and $diff['y'] === 0) $date = 'Yesterday';
                        }
                    }
                    else $date = '';
                    $response[$item_id]['lastModifiedTimestamp'] = "$date @ $time";
                }

                // Format Resonse
                foreach ($response as $r) {
                    $formatted_results[] = $r;
                }
            }

            return ['status' => true, 'data' => $formatted_results];
        } catch (Throwable $th) {
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will return profit margin by item identifier.
     * @param item_identifier
     * @param profit_margin
     * @return float
     */
    private static function get_profit_margin_by_item_identifier(string $item_identifier, array $profit_margins): float {
        $item_identifier = strtoupper(trim($item_identifier));
        $prefixes = array_keys($profit_margins);
        $no_of_prefixes = count($prefixes);
        $__profit_margin = null;
        $matching_prefixes = [];

        for ($i = 0; $i < $no_of_prefixes; ++$i) {
            if (str_starts_with($item_identifier, $prefixes[$i])) {
                $__profit_margin = $profit_margins[$prefixes[$i]];
                $matching_prefixes[] = $prefixes[$i];
            }
        }

        // In case of matching prefixes
        // Always select the one with the largest length
        if (count($matching_prefixes) > 0) {
            $max_length = 0;
            $index = 0;
            $count = count($matching_prefixes);

            for ($i = 0; $i < $count; ++$i) {
                $prefix_length = strlen($matching_prefixes[$i]);
                if ($prefix_length > $max_length) {
                    $max_length = $prefix_length;
                    $index = $i;
                }
            }

            // Set Profit Margin
            $__profit_margin = $profit_margins[$matching_prefixes[$index]];
        }

        // Send DEFAULT Profit Margin
        if (is_null($__profit_margin)) $__profit_margin = $profit_margins[DEFAULT_PROFIT_MARGIN_KEY];
        return $__profit_margin;
    }

    /**
     * This method will calculate new selling price.
     * @param store_id
     * @param existing_prices
     * @param profit_margins
     * @param buying_cost
     * @param item_identifier
     * @return array
     */
    private static function calculate_selling_price(int $store_id, array $existing_prices, array $profit_margins, float $buying_cost, string $item_identifier): array {

        // Set Base Price
        $selling_price = $buying_cost;

        // Set Profit Margin
        $new_profit_margin = self::get_profit_margin_by_item_identifier($item_identifier, $profit_margins);

        // Adjust Selling prices to consider Profit Margin
        $selling_price += (($buying_cost * $new_profit_margin) / 100);

        // Round off selling price
        $selling_price = Utils::round($selling_price);

        // Choose the higher value
        if ($existing_prices[$store_id]['sellingPrice'] < $selling_price) {
            $existing_prices[$store_id]['sellingPrice'] = $selling_price;
        }

        return $existing_prices;
    }

    /**
     * This method will insert inventory history.
     * @param details
     * @param store_id
     * @param db
     * @throws Exception
     */
    private static function insert_inventory_history(array $details, int $store_id, PDO &$db): void {
        $query = <<<'EOS'
        INSERT INTO inventory_history
        (
            `details`,
            `store_id`,
            `sales_rep_id`
        )
        VALUES
        (
            :details,
            :store_id,
            :sales_rep_id
        );
        EOS;

        // Remove Dedundant Details
        $count = count($details);

        for ($i = 0; $i < $count; ++$i) {
            unset($details[$i]['aisle']);
            unset($details[$i]['column']);
            unset($details[$i]['shelf']);
            unset($details[$i]['identifier']);
            unset($details[$i]['description']);
            unset($details[$i]['unit']);
        }

        $statement = $db->prepare($query);
        $is_successful = $statement->execute([
            ':details' => json_encode($details, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
            ':store_id' => $store_id,
            ':sales_rep_id' => $_SESSION['user_id']
        ]);

        if ($is_successful !== true || $statement->rowCount() < 0) throw new Exception('Unable to Insert Inventory History.');
    }

    /**
     * This method will adjust inventory.
     * @param details
     * @param store_id
     * @param db
     * @return array 
     */
    public static function adjust_inventory(array|null $details, int $store_id, PDO &$db = null): array {
        // Check for Wash
        if (SYSTEM_INIT_MODE === WASH) return ['status' => true];

        // Create Transaction
        if ($details === null) return ['status' => false, 'message' => 'Invalid Request.'];
        try {
            // New DB Connection
            $is_new_db_connection = $db === null;

            // Establish New DB Connection
            if ($is_new_db_connection === true) {
                $db = get_db_instance();
                $db->beginTransaction();
            }

            // Insert Inventory History 
            self::insert_inventory_history($details, $store_id, $db);

            // Prepare Statements
            $statement_fetch_existing_prices = $db->prepare(<<<'EOS'
            SELECT 
                id,
                identifier,
                prices
            FROM 
                items 
            WHERE 
                id = :item_id;
            EOS);

            $statement_check = $db->prepare(<<<'EOS'
            SELECT 
                it.identifier,
                it.id,
                it.prices,
                inv.quantity
            FROM 
                items AS it 
            LEFT JOIN 
                inventory AS inv
            ON 
                it.id = inv.item_id
            WHERE 
                inv.item_id = :item_id
            AND
                inv.store_id = :store_id;
            EOS);

            $statement_update = $db->prepare(<<<'EOS'
            UPDATE 
                inventory 
            SET 
                `quantity` = `quantity` + :quantity,
                `aisle` = :aisle,
                `shelf` = :shelf,
                `column` = :column,
                modified = CURRENT_TIMESTAMP
            WHERE 
                item_id = :item_id 
            AND 
                store_id = :store_id;
            EOS);

            $statement_insert = $db->prepare(<<<'EOS'
            INSERT INTO inventory 
            (
                `item_id`,
                `quantity`, 
                `store_id`,
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

            // Update Price Margins
            $statement_update_prices = $db->prepare(<<<'EOS'
            UPDATE
                items 
            SET 
                prices = :prices 
            WHERE 
                id = :item_id;
            EOS);

            // Profit Margins
            $profit_margins = self::fetch_profit_margins($store_id)['profitMargins'];

            // Update Balance Sheet
            $bs_statement = AccountsConfig::ACCOUNTS;

            // Keys
            $keys = array_keys($details);
            foreach ($keys as $key) {

                // Identifier
                $identifier = self::remove_item_detail_tag($details[$key]['identifier']);

                // Quantity
                $quantity_to_be_adjusted = is_numeric($details[$key]['quantity']) ? intval($details[$key]['quantity']) : null;

                // Quantity Can be negative but not NULL
                if ($quantity_to_be_adjusted === null) continue;

                // Buying Cost
                $buying_cost = is_numeric($details[$key]['buyingCost']) ? Utils::round(floatval($details[$key]['buyingCost'])) : null;

                // Buying Cost cannot be negative or null
                if ($buying_cost <= 0 || $buying_cost === null) throw new Exception('Invalid Buying Cost for ' . $identifier);

                // Item Id
                $item_id = $details[$key]['id'];

                // Check for entry
                $statement_check->execute([':item_id' => $item_id, ':store_id' => $store_id]);

                // Values
                $values = [
                    ':item_id' => $item_id,
                    ':quantity' => $quantity_to_be_adjusted,
                    ':aisle' => strtoupper(trim($details[$key]['aisle'] ?? '')),
                    ':shelf' => strtoupper(trim($details[$key]['shelf'] ?? '')),
                    ':column' => strtoupper(trim($details[$key]['column'] ?? '')),
                    ':store_id' => $store_id,
                ];

                // Fetch Quantity and Value Records for validation
                $records = $statement_check->fetchAll(PDO::FETCH_ASSOC);
                if (isset($records[0])) {
                    $item_record = $records[0];

                    // Fetch Existing Prices
                    $existing_prices = json_decode($item_record['prices'], associative: true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Prices JSON is invalid for ' . $identifier);

                    // Set Default Prices
                    if (!isset($existing_prices[$store_id])) {
                        $existing_prices[$store_id] = [
                            'storeId' => $store_id,
                            'sellingPrice' => 0,
                            'preferredPrice' => 0,
                            'buyingCost' => 0,
                        ];
                    }

                    // Existing Inventory Details
                    $existing_inv_quantity = $item_record['quantity'];
                    $existing_inv_value = $existing_inv_quantity * $existing_prices[$store_id]['buyingCost'];

                    // Validate Quantity
                    if ($quantity_to_be_adjusted < 0 && abs($quantity_to_be_adjusted) > $existing_inv_quantity) {
                        throw new Exception('Trying to deduct more quantity than what is in inventory for ' . $identifier);
                    }

                    // Only Update the Prices if quantity adjusted is positive.
                    // Web could be voiding all inventory without affecting the prices.
                    /* ADDING TO INVENTORY */
                    if ($quantity_to_be_adjusted > 0) {

                        // Deduct Existing Inventory Value
                        BalanceSheetActions::update_account_value(
                            $bs_statement,
                            AccountsConfig::INVENTORY_A,
                            -$existing_inv_value,
                        );

                        // Calculate Inventory Value to be adjusted
                        $value_to_be_adjusted = Utils::round($quantity_to_be_adjusted * $buying_cost);

                        if ($value_to_be_adjusted < 0 && abs($value_to_be_adjusted) > $existing_inv_value) {
                            throw new Exception('Cannot process negative value balance for ' . $identifier);
                        }

                        // Adjust Inventory value
                        $new_inv_value = $existing_inv_value + $value_to_be_adjusted;

                        // Adjust Quantity 
                        $new_inv_quantity = $existing_inv_quantity + $quantity_to_be_adjusted;

                        // Validate Quantity
                        self::validate_quantity_added($item_id, $quantity_to_be_adjusted);

                        // Calculate New Prices
                        $new_prices = self::calculate_selling_price($store_id, $existing_prices, $profit_margins, $buying_cost, $item_record['identifier']);

                        // Update Buying Cost
                        // Adjusted for Quantity
                        $new_prices[$store_id]['buyingCost'] = self::calculate_buying_cost($new_inv_value, $new_inv_quantity);

                        // New Total Inventory Value 
                        // (Old Quantity + New Quantity) * New Buying Cost
                        $new_total_inventory_value = ($existing_inv_quantity + $quantity_to_be_adjusted) * $new_prices[$store_id]['buyingCost'];

                        // Update Account Value
                        BalanceSheetActions::update_account_value(
                            $bs_statement,
                            AccountsConfig::INVENTORY_A,
                            $new_total_inventory_value,
                        );

                        // Update Prices
                        $is_successful = $statement_update_prices->execute([
                            ':item_id' => $item_id,
                            ':prices' => json_encode($new_prices, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                        ]);
                        if ($is_successful !== true && $statement_update_prices->rowCount() < 1) throw new Exception('Unable to Update Price for ' . $identifier);
                    }
                    /* DEDUCTING FROM INVENTORY */ 
                    else {
                        // Existing Buying Cost
                        $existing_buying_cost = $existing_prices[$store_id]['buyingCost'];

                        // Round of value to be adjusted
                        // This is Negative Value
                        $value_to_be_adjusted = Utils::round($quantity_to_be_adjusted * $existing_buying_cost);

                        // Validate 
                        if (strval(abs($value_to_be_adjusted)) > strval($existing_inv_value)) {
                            throw new Exception('Cannot process negative value balance for ' . $identifier);
                        }

                        // Deduct Existing Inventory Value
                        BalanceSheetActions::update_account_value(
                            $bs_statement,
                            AccountsConfig::INVENTORY_A,
                            $value_to_be_adjusted,  /* THIS IS ALREADY NEGATIVE */
                        );
                    }

                    // Update Inventory 
                    $is_successful = $statement_update->execute($values);
                    if ($is_successful !== true || $statement_update->rowCount() < 1) throw new Exception('Unable to Adjust Item(s).');
                }
                /* No Inventory Records Exists for this Item, So add a record */ 
                else {
                    // Only Update the Prices if quantity adjusted is positive.
                    // We could be voiding all inventory without affecting the prices.
                    if ($quantity_to_be_adjusted > 0) {

                        // Value to be adjusted
                        $value_to_be_adjusted = Utils::round($quantity_to_be_adjusted * $buying_cost);

                        // Update Account Value
                        BalanceSheetActions::update_account_value(
                            $bs_statement,
                            AccountsConfig::INVENTORY_A,
                            $value_to_be_adjusted,
                        );

                        // Insert record
                        $statement_insert->execute($values);
                        if ($db->lastInsertId() === false) throw new Exception('Unable to Insert Record for ' . $identifier);

                        // Validate quantity 
                        self::validate_quantity_added($item_id, $quantity_to_be_adjusted);

                        // Fetch Existing Prices
                        $statement_fetch_existing_prices->execute([':item_id' => $item_id]);
                        $item_record = $statement_fetch_existing_prices->fetchAll(PDO::FETCH_ASSOC);
                        if (isset($item_record[0])) $item_record = $item_record[0];
                        else throw new Exception('Unable to fetch Existing Prices for Item for ' . $identifier);

                        // Calculate New Prices
                        $existing_prices = json_decode($item_record['prices'], associative: true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Prices JSON is invalid for ' . $identifier);

                        // Default Prices
                        if (!isset($existing_prices[$store_id])) {
                            $existing_prices[$store_id] = [
                                'storeId' => $store_id,
                                'sellingPrice' => 0,
                                'preferredPrice' => 0,
                                'buyingCost' => 0,
                            ];
                        }

                        // Calculate Selling Price
                        $existing_prices = self::calculate_selling_price($store_id, $existing_prices, $profit_margins, $buying_cost, $item_record['identifier']);

                        // Update Buying Cost
                        // Adjusted for Quantity
                        $existing_prices[$store_id]['buyingCost'] = self::calculate_buying_cost($value_to_be_adjusted, $quantity_to_be_adjusted);

                        // Update Prices
                        $is_successful = $statement_update_prices->execute([
                            ':item_id' => $item_id,
                            ':prices' => json_encode($existing_prices, JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR),
                        ]);

                        if ($is_successful !== true && $statement_update_prices->rowCount() < 1) throw new Exception('Unable to Update Price for ' . $identifier);
                    } else throw new Exception('Item does not exists in Inventory: ' . $identifier);
                }
            }

            // Update Balance Sheet
            BalanceSheetActions::update_from($bs_statement, Utils::get_business_date($store_id), $store_id, $db);

            // Assert 
            assert_success();

            // DB Commit
            if ($is_new_db_connection && $db->inTransaction()) $db->commit();

            return ['status' => true];
        } catch (Throwable $th) {
            if ($is_new_db_connection && $db->inTransaction()) $db->rollBack();
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    /**
     * This method will calculate buying cost.
     * @param value 
     * @param quantity 
     * @return float
     */
    private static function calculate_buying_cost(float $value, int $quantity): float {
        return Utils::round($value / $quantity);
    }

    /**
     * This method will validate the quantity added.
     * @param item_id
     * @param quantity
     */
    private static function validate_quantity_added(int $item_id, float $quantity): void {
        if ($_SESSION['store_id'] == StoreDetails::EDMONTON && $_SESSION['access_level'] != ADMIN && LOCK_INVENTORY_LIMIT) {
            if (in_array($item_id, self::EXEMPT_ITEMS_ID_CAT_2)) {
                if ($quantity > 200) throw new Exception('Cannot add more than 200.');
            } else if (in_array($item_id, self::EXEMPT_ITEMS_ID_CAT_1)) {
                if ($quantity > 100) throw new Exception('Cannot add more than 100.');
            } else if ($quantity > 20) throw new Exception('Cannot add more than 20.');
        }
    }

    /**
     * Fetch Item Details for Transactions
     * @param search_term
     * @return array 
     */
    public static function item_details_for_transactions(string $search_term): array {
        $params = [
            'exclude_inactive' => 1,
            'term' => $search_term,
        ];
        return self::fetch($params, $_SESSION['store_id']);
    }

    /**
     * This method will fetch low stock items in the inventory for the store.
     * @param store_id
     */
    public static function fetch_low_stock(int $store_id): void {
        $db = get_db_instance();
        $query = <<<'EOS'
        SELECT 
            it.`identifier`, 
            it.`description`, 
            it.`reorder_quantity`,
            inv.`quantity`
        FROM 
            items AS it
        LEFT JOIN 
            inventory AS inv
        ON 
            it.id = inv.item_id
        WHERE 
            inv.store_id = :store_id;
        EOS;

        $statement = $db->prepare($query);
        $statement->execute([':store_id' => $store_id]);

        // Fetch items and inventory 
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Low Stock Items
        $low_stock_items = [];

        foreach ($items as $item) {
            $reorder_quantity = json_decode(json: $item['reorder_quantity'], associative: true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Unable to Decode Reorder quantity for Current store.');

            $inventory_quantity = floatval($item['quantity']);
            if (isset($reorder_quantity[$store_id]) && $inventory_quantity < $reorder_quantity[$store_id]) {
                $low_stock_items[] = [
                    'identifier' => $item['identifier'],
                    'description' => $item['description'],
                    'deficit' => $reorder_quantity[$store_id] - $inventory_quantity,
                ];
            }
        }
        GeneratePDF::low_stock($low_stock_items);
    }

    /**
     * This method will fetch item details from Transactions.
     * @param transaction_type
     * @param item_id
     * @param from_date
     * @param till_date
     * @return array 
     */
    private static function fetch_item_details_from_transactions(int $transaction_type, ?int $item_id, ?string $from_date = null, ?string $till_date = null): array {
        try {
            if ($transaction_type === SALES_INVOICE) $table_name = ' sales_invoice ';
            else if ($transaction_type === SALES_RETURN) $table_name = ' sales_return ';
            else throw new Exception('Invalid Transaction Type.');

            // Build Query
            $query = <<<EOS
            SELECT 
                id,
                `date`,
                details
            FROM 
                $table_name
            WHERE 
                store_id = :store_id 
            EOS;

            // Values
            $values = [':store_id' => intval($_SESSION['store_id'])];

            // Date Range
            if (isset($from_date)) {
                $query .= ' AND `date` >= :from_date ';
                $values[':from_date'] = $from_date;
            }
            if (isset($till_date)) {
                $query .= ' AND `date` <= :till_date ';
                $values[':till_date'] = $till_date;
            }

            // Flag
            $is_item_specified = false;

            // Filter for Specific item?
            if (isset($item_id)) {
                $is_item_specified = true;
                $query .= ' AND details LIKE :item_id ';
                $values[':item_id'] = "%\"item_id\":$item_id,%";
            }
            $query .= ' ORDER BY `date` ASC';

            $db = get_db_instance();
            $statement = $db->prepare($query);
            $statement->execute($values);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);

            // Transaction Record Details
            $transaction_record_details = [];

            foreach ($records as $record) {
                // Get Year and Month from Transaction Date
                $parts = explode('-', $record['date']);
                $year = intval($parts[0]);
                $month = intval($parts[1]);

                // Decode Items 
                $items = json_decode($record['details'], associative: true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Unable to Decode Items for Txn Id: ' . $record['id'] . ' (' . TRANSACTION_NAMES[$transaction_type] . ')');

                foreach ($items as $item) {
                    if ($is_item_specified && $item_id !== $item['id']) continue;
                    if (!isset($transaction_record_details[$item['id']][$year][$month])) {
                        if (!isset($transaction_record_details[$item['id']])) {
                            $transaction_record_details[$item['id']] = [$year => [$month => ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0]]];
                        } else if (!isset($transaction_record_details[$item['id']][$year])) {
                            $transaction_record_details[$item['id']][$year] = [$month => ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0]];
                        } else if (!isset($transaction_record_details[$item['id']][$year][$month])) {
                            $transaction_record_details[$item['id']][$year][$month] = ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0];
                        }
                    }
                    $transaction_record_details[$item['id']][$year][$month]['quantity'] += $item['quantity'];
                    $transaction_record_details[$item['id']][$year][$month]['totalSold'] += $item['amount'];
                    $transaction_record_details[$item['id']][$year][$month]['totalCost'] += ($item['buyingCost'] * $item['quantity']);
                }
            }
            return ['status' => true, 'data' => $transaction_record_details];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * This method will adjust details for all items.
     * @param items_from_sales_invoices
     * @param items_from_sales_returns 
     * @return array
     */
    private function adjust_quantity_for_all_items(array $items_from_sales_invoices, array $items_from_sales_returns): array {
        $adjusted_items = $items_from_sales_invoices;

        // Process Sales Return
        $items = array_keys($items_from_sales_returns);
        foreach ($items as $item) {
            $years = array_keys($items_from_sales_returns[$item]);
            foreach ($years as $year) {
                $months = array_keys($items_from_sales_returns[$item][$year]);
                foreach ($months as $month) {
                    if (!isset($adjusted_items[$item])) {
                        $adjusted_items[$item] = [$year => [$month => ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0]]];
                    } else if (!isset($adjusted_items[$item][$year])) {
                        $adjusted_items[$item][$year] = [$month => ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0]];
                    } else if (!isset($adjusted_items[$item][$year][$month])) {
                        $adjusted_items[$item][$year][$month] = ['quantity' => 0, 'total_sold' => 0, 'total_cost' => 0];
                    }
                    $adjusted_items[$item][$year][$month]['quantity'] -= $items_from_sales_returns[$item][$year][$month]['quantity'];
                    $adjusted_items[$item][$year][$month]['total_sold'] -= $items_from_sales_returns[$item][$year][$month]['total_sold'];
                    $adjusted_items[$item][$year][$month]['total_cost'] -= $items_from_sales_returns[$item][$year][$month]['total_cost'];
                }
            }
        }
        return $adjusted_items;
    }

    /**
     * This method will fetch item details by id.
     * @param item_ids
     * @return array
     */
    private static function fetch_item_details_by_id(array $item_ids = []): array {
        try {
            $db = get_db_instance();
            $query = <<<'EOS'
            SELECT 
                * 
            FROM 
                items
            WHERE 
                id in (:placeholder);
            EOS;
            $ret_value = Utils::mysql_in_placeholder($item_ids, $query);
            $query = $ret_value['query'];
            $values = $ret_value['values'];

            $statement = $db->prepare($query);
            $statement->execute($values);
            $records = $statement->fetchAll(PDO::FETCH_ASSOC);

            // Items
            $items = [];

            // Set Item ID as Index
            foreach ($records as $record) $items[$record['id']] = $record;

            return ['status' => true, 'data' => $items];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * This method will fetch the item Sale Frequency.
     * @return array 
     */
    public static function fetch_item_sale_frequency(?int $item_id = null, ?string $from_date = null, ?string $till_date = null): array {
        try {

            // Adjusted Frequency
            $items_adjusted_frequency = self::adjust_quantity_for_all_items(
                self::fetch_item_details_from_transactions(SALES_INVOICE, $item_id, $from_date, $till_date),
                self::fetch_item_details_from_transactions(SALES_RETURN, $item_id, $from_date, $till_date)
            );

            // Get all item ids
            $ids = array_keys($items_adjusted_frequency);
            if (!isset($ids[0])) return [];

            // Fetch Items by ID
            $items = self::fetch_item_details_by_id($ids);

            $item_frequency = [];
            foreach ($items as $item) {
                $id = $item['id'];
                $item_frequency[$id] = [
                    'identifier' => $item['identifier'],
                    'description' => $item['description'],
                    'frequency' => $items_adjusted_frequency[$item_id],
                ];
            }
            return ['status' => true, 'data' => $item_frequency];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * This method will generate inventory list.
     * @param details
     */
    public static function generate_inventory_list(int $store_id): void {
        $list = PrepareDetails_Inventory::generate_inventory_list($store_id);
        GeneratePDF::generate_inventory_list(
            $list,
            $store_id,
        );
    }

    /**
     * This method will fetch item frequency.
     * @param part_id
     * @param start_date
     * @param end_date
     * @return array
     */
    public static function frequency(int|null $part_id, string|null $start_date, string|null $end_date): array {
        $db = get_db_instance();
        try {
            // Part Not Selected
            if (is_numeric($part_id) === false) throw new Exception('Invalid Part Selected.');

            $report = [];
            $query = <<<'EOS'
            SELECT 
                `date`, 
                `details` 
            FROM 
                sales_invoice 
            WHERE 
                store_id = :store_id 
            AND 
                `details` LIKE :part_id
            EOS;

            // Store Id
            $store_id = intval($_SESSION['store_id']);

            // Params
            $params = [':store_id' => $store_id];
            if (isset($start_date[0])) {
                $query .= ' AND `date` >= :start_date ';
                $params[':start_date'] = $start_date;
            }
            if (isset($end_date[0])) {
                $query .= ' AND `date` <= :end_date ';
                $params[':end_date'] = $end_date;
            }

            $params[':part_id'] = <<<EOS
            %"itemId":$part_id,%
            EOS;
            $statement = $db->prepare($query);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            // Details Template
            $details_template = [
                'cogs' => 0,
                'profit' => 0,
                'totalSold' => 0,
                'quantity' => 0,
            ];

            // Months Template
            $month_template = [
                1 => $details_template,
                2 => $details_template,
                3 => $details_template,
                4 => $details_template,
                5 => $details_template,
                6 => $details_template,
                7 => $details_template,
                8 => $details_template,
                9 => $details_template,
                10 => $details_template,
                11 => $details_template,
                12 => $details_template,
            ];

            foreach ($result as $r) {

                // Split Date By Month and Year
                $date_parts = explode('-', $r['date']);

                // Decode Details
                $details = json_decode($r['details'], true, flags: JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);

                // Year and Month
                $year = intval($date_parts[0]);
                $month = intval($date_parts[1]);

                // Add Year
                if (!isset($report[$year])) $report[$year] = $month_template;

                foreach ($details as $item) {
                    if ($item['itemId'] === $part_id) {

                        // Quantity
                        $report[$year][$month]['quantity'] += ($item['quantity']);

                        // Total Sold
                        $report[$year][$month]['totalSold'] += ($item['pricePerItem'] * $item['quantity']);

                        // Profit 
                        $report[$year][$month]['profit'] += (($item['pricePerItem'] - $item['buyingCost']) * $item['quantity']);

                        // C.O.G.S
                        $report[$year][$month]['cogs'] += ($item['buyingCost'] * $item['quantity']);
                    }
                }
            }

            return ['status' => true, 'data' => $report];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * This method will update last sold for items.
     * @param items
     * @param date
     * @param store_id
     * @param db
     */
    public static function update_last_sold_for_items(array &$items, string &$date, int &$store_id, PDO &$db): void {

        // Fetch item ids
        $item_ids = [];
        foreach($items as $item) $item_ids[]= $item['itemId'];

        // Fetch Item Details
        $query = 'SELECT id, last_sold FROM items WHERE id IN (:placeholder);';
        $result = Utils::mysql_in_placeholder_pdo_substitute($item_ids, $query);
        $query = $result['query'];
        $params = $result['values'];
        $statement_fetch = $db -> prepare($query);
        $statement_fetch -> execute($params);
        $result = $statement_fetch -> fetchAll(PDO::FETCH_ASSOC);

        // Update
        $statement_update = $db -> prepare('UPDATE items SET last_sold = :last_sold WHERE id = :id;');
        foreach($result as $r) {
            $item_id = intval($r['id']);
            $last_sold = json_decode($r['last_sold'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

            // Update Last Sold Date
            $last_sold[$store_id] = $date;

            // Convert to json
            $last_sold = json_encode($last_sold, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);

            // Update 
            $is_successful = $statement_update -> execute([':last_sold' => $last_sold, ':id' => $item_id]);
            if($is_successful !== true && $statement_update -> rowCount() < 1) throw new Exception('Cannot Update Last Sold for transaction.');
        }
    }

    /**
     * This method will generate dead stock report.
     * @param store_id
     * @param month
     * @return array
     */
    private static function get_dead_inventory(int $store_id, int $month): array {
        $db = get_db_instance();

        $statement = $db -> prepare(<<<'EOS'
        SELECT 
            i.identifier, 
            i.`description`, 
            i.last_sold,
            i.prices,
            inv.quantity
        FROM
            items AS i
        LEFT JOIN 
            inventory AS inv
        ON 
            i.id = inv.item_id
        WHERE
            last_sold LIKE :last_sold_tag
        AND
            inv.quantity > 0
        AND
            inv.store_id = :store_id;
        EOS);
        $statement -> execute([':last_sold_tag' => "%\"$store_id\":%", ':store_id' => $store_id]);
        $results = $statement -> fetchAll(PDO::FETCH_ASSOC);

        $dead_stock = [];
        $total_dead_inventory_value = 0;
        foreach($results as $result) {
            $last_sold = json_decode($result['last_sold'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
            if(isset($last_sold[$store_id]) === false) continue;
            $date_diff = Utils::get_difference_from_current_date(
                $last_sold[$store_id],
                Utils::get_business_date($store_id),
                $store_id,
            );

            if($month >= 12) $flag = $date_diff['y'] >= 1;
            else if($date_diff['y'] >= 1 || $date_diff['m'] >= $month) $flag = true;
            else $flag = false;

            if($flag) {
                $prices = json_decode($result['prices'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                if(isset($prices[$store_id]) === false) continue;
                $value = $prices[$store_id]['buyingCost'] * $result['quantity'];
                $dead_stock[] = [
                    'identifier' => $result['identifier'],
                    'description' => $result['description'], 
                    'last_sold' => $last_sold[$store_id],
                    'quantity' => $result['quantity'],
                    'buying_cost' => $prices[$store_id]['buyingCost'],
                    'value' => $value,
                ];
                $total_dead_inventory_value += $value;
            }
        }
        return [
            'dead_stock' => $dead_stock,
            'value' => $total_dead_inventory_value,
        ];
    }

    /**
     * This method will generate dead inventory stock.
     * @param store_id
     * @param month
     */
    public static function generate_dead_inventory(int $store_id, int $month): void {
        $inventory_details = self::get_dead_inventory($store_id, $month);
        GeneratePDF::generate_dead_inventory_list(
            $inventory_details, 
            $store_id, 
            $month
        );
    }

    /**
     * This method will fetch quantity sold for all items.
     * @param store_id
     * @param year
     * @param sort_order
     * @return array
     */
    private static function __fetch_quantity_sold_for_all_items(int $store_id, int $year, int $sort_order): array {
        $db = get_db_instance();
        try {
            $params = [':store_id' => $store_id, ':year' => "$year-__-__"];

            // Item Frequency
            $items_frequency = [];

            // Fetch Sales Invoice
            $statement = $db -> prepare('SELECT `details` FROM sales_invoice WHERE store_id = :store_id AND `date` LIKE :year;');
            $statement -> execute($params);
            $invoices = $statement -> fetchAll(PDO::FETCH_ASSOC);

            foreach($invoices as $invoice) {
                $items = json_decode($invoice['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                foreach($items as $item) {
                    $item_id = $item['itemId'];
                    if(isset($items_frequency[$item_id]) === false) $items_frequency[$item_id] = 0;
                    $items_frequency[$item_id] += $item['quantity'];
                }
            }

            // Fetch Sales Returns
            $statement = $db -> prepare('SELECT `details` FROM sales_return WHERE store_id = :store_id AND `date` LIKE :year;');
            $statement -> execute($params);
            $sales_returns = $statement -> fetchAll(PDO::FETCH_ASSOC);

            foreach($sales_returns as $sales_return) {
                $items = json_decode($sales_return['details'], true, flags: JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
                foreach($items as $item) {
                    $item_id = $item['itemId'];
                    if(isset($items_frequency[$item_id]) && isset($items_frequency[$item_id]['returnQuantity']) && $items_frequency[$item_id]['returnQuantity'] > 0) {
                        $items_frequency[$item_id] -= $item['returnQuantity'];
                    }
                }
            }
            
            // Sort
            if($sort_order) arsort($items_frequency);
            else asort($items_frequency);

            $item_details = [];
            foreach($items_frequency as $item => $quantity) {
                $item_details[$item] = ['quantity' => $quantity];
            }

            // Fetch Item Details
            $query = 'SELECT id, identifier, `description` FROM items WHERE id IN (:placeholder);';
            $ret_values = Utils::mysql_in_placeholder_pdo_substitute(array_keys($item_details), $query);
            $query = $ret_values['query'];
            $values = $ret_values['values'];

            $statement = $db -> prepare($query);
            $statement -> execute($values);
            $item_records = $statement -> fetchAll(PDO::FETCH_ASSOC);
            foreach($item_records as $item) {
                $item_id = $item['id'];
                $item_details[$item_id]['identifier'] = $item['identifier'];
                $item_details[$item_id]['description'] = $item['description'];
            }
            return $item_details;
        }
        catch(Exception $e) {
            print_r($e -> getMessage());
            return [];
        }
    }

    /**
     * This method will fetch quantity sold fopr all items.
     * @param store_id
     * @param year
     * @param sort_order 0 -> Ascending, 1 -> Descending
     */
    public static function fetch_quantity_sold_for_all_items(int $store_id, int $year, int $sort_order=1): void {
        $item_details = self::__fetch_quantity_sold_for_all_items($store_id, $year, $sort_order);
        if(count($item_details)) {
            GeneratePDF::generate_item_sold_quantity($item_details, $store_id, $year);
        }
    }
}
