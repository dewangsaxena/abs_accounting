<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

/**
 * This class will define Special Exceptions/Access.
 */
class SpecialExceptions {

    /* Enable Special Access */ 
    const ENABLE_SPECIAL_ACCESS = true;

    /* Users With Special Access */ 
    const USERS_WITH_SPECIAL_ACCESS = [
        PARTS_HOST => [
            StoreDetails::EDMONTON => [
                10007, /* LUCKY */
                10010, /* PRITPAL */
                10015 /* JAIDEEP */
            ],
            StoreDetails::SLAVE_LAKE => [
                10015, /* JAIDEEP */
            ],
        ],
    ][SYSTEM_INIT_HOST] ?? [];

    // Customer aged summary client exlusion
    const CUSTOMER_AGED_SUMMARY_CLIENT_EXCLUSIONS = [

        // Parts 
        PARTS_HOST => [
            /* Dead */
            13371 => 'DB CUSTOMS',
            16396 => 'DAVE\'S DIESEL REPAIR',
            15867 => 'VELOCITY TRUCK CENTRES',
            16745 => 'UNITED DIESEL',

            /* Other */
            18021 => '1573670 AB LTD',
            18695 => 'VR ENTERPRISES',
            15793 => 'AFFINITY TUNING INC',
            13928 => 'DIESEL GUYS TRUCK REPAIR',
            15749 => '1994426 Ab Ltd',
            14409 => 'SUMMIT TRAILER LTD',
            14285 => 'FATEH TRUCKING LTD',
            14111 => '12899281 CANADA INC / BIRRING LO',
            13856 => 'BABA SAHIB SINGH TRUCKING LTD /',
            10725 => 'AUTO GATES TRUCKING LTD',
            14468 => 'BRIMSTONE SULPHUR INC',
        ],
    ];

    /* Check Over 60+ Balance For Client For Credit Transaction */ 
    private const CHECK_OVER_60_PLUS_BALANCE_DUE_OF_CLIENT_FOR_CREDIT_TRANSACTION_PER_STORE = [
        PARTS_HOST => [
            StoreDetails::EDMONTON => false,
            StoreDetails::CALGARY => false,
            StoreDetails::NISKU => false,
            StoreDetails::SLAVE_LAKE => false,
            StoreDetails::VANCOUVER => false,
            StoreDetails::DELTA => false,
            StoreDetails::REGINA => false,
            StoreDetails::SASKATOON => true,
        ],
        WASH_HOST => [
            StoreDetails::NISKU => false,
        ],
    ][SYSTEM_INIT_HOST] ?? [];
    
    /* Whitelist for Clients per store */ 
    private const CLIENT_BALANCE_OVER_DUE_WHITELIST = [
        PARTS_HOST => [
            StoreDetails::CALGARY => [
                14597, /* Shokee Trucking Ltd */
                14816, /* AS TRUCK & TRAILER REPAIRS LTD */
                14595, /* JPS TRUCKING */
                14583, /* RAM ENTERPRISES */
                15248, /* D Sohi Trucking Ltd */
                14627, /* HAWK HD MECHANICAL LTD */
                14511, /* KMC TRUCK AND TRAILER REPAIR LTD */
                18052, /* 2387544 AB LTD */ 
                11548, /* MAAN TRANSPORT LTD */
            ],
            StoreDetails::SASKATOON => [
                12089,  /* SST TRUCKING LTD */
            ]
        ],
    ][SYSTEM_INIT_HOST] ?? [];

    /**
     * This method will check client for balance due.
     * @param client_id
     * @param store_id
     * @return bool
     */
    public static function allow_balance_due_check_for_client(int $client_id, int $store_id): bool {
        // Default 
        if(isset(self::CHECK_OVER_60_PLUS_BALANCE_DUE_OF_CLIENT_FOR_CREDIT_TRANSACTION_PER_STORE[$store_id]) === false) return true;

        // Check for exceptions
        if(self::CHECK_OVER_60_PLUS_BALANCE_DUE_OF_CLIENT_FOR_CREDIT_TRANSACTION_PER_STORE[$store_id]) {
            if(isset(self::CLIENT_BALANCE_OVER_DUE_WHITELIST[$store_id])) {
                if(in_array($client_id, self::CLIENT_BALANCE_OVER_DUE_WHITELIST[$store_id])) return false;
            }
            return true;
        }
        else return false;
    }
}
?>