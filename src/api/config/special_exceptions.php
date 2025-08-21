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
        StoreDetails::EDMONTON => [
            10007, /* LUCKY */
            10010, /* PRITPAL */
            10015 /* JAIDEEP */
        ],
    ];

    // Customer aged summary client exlusion
    const CUSTOMER_AGED_SUMMARY_CLIENT_EXCLUSIONS = [

        /* Dead */
        13371 => 'DB CUSTOMS',
        16396 => 'DAVE\'S DIESEL REPAIR',
        15867 => 'VELOCITY TRUCK CENTRES',
        16745 => 'UNITED DIESEL',


        /* Other */
        18021 => '1573670 AB LTD',
        18695 => 'VR ENTERPRISES',
    ];

    /* Check Over 60+ Balance For Client For Credit Transaction */ 
    private const CHECK_OVER_60_PLUS_BALANCE_DUE_OF_CLIENT_FOR_CREDIT_TRANSACTION_PER_STORE = [
        PARTS => [
            StoreDetails::EDMONTON => true,
            StoreDetails::CALGARY => true,
            StoreDetails::NISKU => true,
            StoreDetails::SLAVE_LAKE => true,
            StoreDetails::VANCOUVER => true,
            StoreDetails::DELTA => true,
            StoreDetails::REGINA => true,
            StoreDetails::SASKATOON => true,
        ],
        WASH => [
            StoreDetails::NISKU => true,
        ],
    ][SYSTEM_INIT_MODE];
    
    /* Whitelist for Clients per store */ 
    private const CLIENT_BALANCE_OVER_DUE_WHITELIST = [
        PARTS => [
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
    ][SYSTEM_INIT_MODE];

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