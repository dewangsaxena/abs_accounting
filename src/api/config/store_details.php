<?php
/**
 * Store Details
 */
class StoreDetails {

    // Store Identifies
    public const ALL_STORES = 1;
    public const EDMONTON = 2;
    public const CALGARY = 3;
    public const NISKU = 4;
    public const VANCOUVER = 5;
    public const SLAVE_LAKE = 6;
    public const DELTA = 7;
    public const REGINA = 8;

    // Business Name
    public const BUSINESS_NAME = [WASH => 'ABS TRUCK WASH AND LUBE LTD.', PARTS =>'ABS TRUCK & TRAILER PARTS LTD.'][SYSTEM_INIT_MODE];

    // Stores
    public const STORE_DETAILS = [
        self::ALL_STORES => null,
        self::EDMONTON => [
            'id' => self::EDMONTON, 
            'name' => 'Edmonton',
            'location_code' => 'E',
            'timezone' => 'America/Edmonton',
            'pst_tax_rate' => 0,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '727752099RT0001', 
                WASH => '845421445RT0001',
            ],
            'pst_number' => [
                PARTS => null,
                WASH => null,
            ],
            'address' => [
                'name' => self::BUSINESS_NAME,
                'street1' => '6030 125 AVENUE NW',
                'city' => 'EDMONTON',
                'province' => 'ALBERTA',
                'postal_code' => 'T5W 1Z6',
                'country' => 'CANADA',
                'tel' => '+1 (780) 479-4700',
                'fax' => '+1 (780) 479-7995',
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'abstruckparts@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSTRUCKPARTS@GMAIL.COM',
                'checks' => [
                    'payable_to' => self::BUSINESS_NAME,
                    'address' => '6030 125 AVE NW, EDMONTON, ALBERTA, T5W 1Z6',
                ]
            ],
            'cipher_key' => ';5_N$4{]7(nsk>^H4oU~zJ+&?bNC5nK(',
        ],
        self::CALGARY => [
            'id' => self::CALGARY, 
            'name' => 'Calgary',
            'location_code' => 'C',
            'timezone' => 'America/Edmonton',
            'pst_tax_rate' => 0,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '727752099RT0002', 
                WASH => null,
            ],
            'pst_number' => [
                PARTS => null,
                WASH => null,
            ],
            'address' => [
                'name' => self::BUSINESS_NAME,
                'street1' => '5150 76 AVE SE',
                'city' => 'CALGARY',
                'province' => 'ALBERTA',
                'postal_code' => 'T2C 2X2',
                'country' => 'CANADA',
                'tel' => '+1 (403) 300-6600',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'absparts.calgary@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSTRUCKPARTS@GMAIL.COM',
                'checks' => [
                    'payable_to' => self::BUSINESS_NAME,
                    'address' => '6030 125 AVE NW, EDMONTON, ALBERTA, T5W 1Z6',
                ]
            ],
            'cipher_key' => '#.0l>HH(HhxEP|pZ=gJN/d+f6V<zVnk{',
        ],
        self::NISKU => [
            'id' => self::NISKU, 
            'name' => 'Nisku',
            'location_code' => 'N',
            'timezone' => 'America/Edmonton',
            'pst_tax_rate' => 0,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '727752099RT0003', 
                WASH => '845421445RT0002',
            ],
            'pst_number' => [
                PARTS => null,
                WASH => null,
            ],
            'address' => [
                'name' => self::BUSINESS_NAME,
                'street1' => '2010 SPARROW DRIVE',
                'city' => 'NISKU',
                'province' => 'ALBERTA',
                'postal_code' => 'T9E 8A2',
                'country' => 'CANADA',
                'tel' => '+1 (780) 334-1900',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'abstruckparts_nisku@outlook.com', 
                    WASH => 'abstruckwash_nisku@outlook.com',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSTRUCKPARTS@GMAIL.COM',
                'checks' => [
                    'payable_to' => self::BUSINESS_NAME,
                    'address' => '6030 125 AVE NW, EDMONTON, ALBERTA, T5W 1Z6',
                ]
            ],
            'cipher_key' => 'T+=Xk;T6}!{jCK[h*p/>ty#?4ml_T)A9',
        ],
        self::VANCOUVER => [
            'id' => self::VANCOUVER, 
            'name' => 'Vancouver',
            'location_code' => 'V',
            'timezone' => 'America/Vancouver',
            'pst_tax_rate' => 7.00,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '727752099RT0004', 
                WASH => null,
            ],
            'pst_number' => [
                PARTS => 'PST-1480-1016',
                WASH => null,
            ],
            'address' => [
                'name' => 'TRACTION HEAVY DUTY PARTS',
                'street1' => '7351 PROGRESS PLACE',
                'city' => 'DELTA',
                'province' => 'BRITISH COLUMBIA',
                'postal_code' => 'V4G 1A1',
                'country' => 'CANADA',
                'tel' => '+1 (604)-952-0001',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'absparts.traction.delta@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSPARTS.TRACTION.DELTA@GMAIL.COM',
                'checks' => [
                    'payable_to' => 'TRACTION HEAVY DUTY PARTS',
                    'address' => '7351 PROGRESS PLACE, DELTA, BRITISH COLUMBIA, V4G 1A1',
                ],
            ],
            'cipher_key' => 'JL`=rtluOqF|bkVF/g=YOm#>,fk9q1>%',
        ],
        self::SLAVE_LAKE => [
            'id' => self::SLAVE_LAKE, 
            'name' => 'Slave Lake',
            'location_code' => 'SL',
            'timezone' => 'America/Edmonton',
            'pst_tax_rate' => 0,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '727752099RT0005', 
                WASH => null,
            ],
            'pst_number' => [
                PARTS => null,
                WASH => null,
            ],
            'address' => [
                'name' => self::BUSINESS_NAME,
                'street1' => '#2 301 CARIBOU TRAIL NW',
                'city' => 'SLAVE LAKE',
                'province' => 'ALBERTA',
                'postal_code' => 'T0G 2A0',
                'country' => 'CANADA',
                'tel' => '+1 (780)-849-1912',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'abstruckparts.slavelake@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSTRUCKPARTS@GMAIL.COM',
                'checks' => [
                    'payable_to' => self::BUSINESS_NAME,
                    'address' => 'PO BOX 55, SLAVE LAKE, ALBERTA, T0G 2A0',
                ]
            ],
            'cipher_key' => '+dH<do)&v9z#";BY-%y_L+Ocob4o@-,8',
        ],
        self::DELTA => [
            'id' => self::DELTA, 
            'name' => 'Delta',
            'location_code' => 'D',
            'timezone' => 'America/Vancouver',
            'pst_tax_rate' => 7.00,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '707291019RT0001', 
                WASH => null,
            ],
            'pst_number' => [
                PARTS => 'PST-1481-0085',
                WASH => null,
            ],
            'address' => [
                'name' => 'ABS TRUCK AND TRAILER PARTS BC LTD.',
                'street1' => '7351 PROGRESS PLACE',
                'city' => 'DELTA',
                'province' => 'BRITISH COLUMBIA',
                'postal_code' => 'V4G 1A1',
                'country' => 'CANADA',
                'tel' => '+1 (604)-952-0001',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts Ltd.'
                ],
                'bcc' => [
                    PARTS => 'absparts.traction.delta@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'ABSPARTS.TRACTION.DELTA@GMAIL.COM',
                'checks' => [
                    'payable_to' => 'ABS TRUCK AND TRAILER PARTS BC LTD.',
                    'address' => '7351 PROGRESS PLACE, DELTA, BRITISH COLUMBIA, V4G 1A1',
                ],
            ],
            'cipher_key' => 'K(|;Q7cKYnb~ZMa/6^67pb{}(bWm2/s5',
        ],

        // https://www.zeitverschiebung.net/en/timezone/america--regina
        self::REGINA => [
            'id' => self::REGINA, 
            'name' => 'Regina',
            'location_code' => 'R',
            'timezone' => 'America/Regina',
            'pst_tax_rate' => 6.00,
            'hst_tax_rate' => 0,
            'use_hst' => false,
            'business_number' => [
                PARTS => '700635212RC0001',
                WASH => null,
            ],
            'pst_number' => [
                PARTS => null,
                WASH => null,
            ],
            'address' => [
                'name' => 'ABS TRUCK AND TRAILER PARTS SK LTD.',
                'street1' => '1600 ROSS AVE EAST',
                'city' => 'REGINA',
                'province' => 'SASKATCHEWAN',
                'postal_code' => 'S4N 7A3',
                'country' => 'CANADA',
                'tel' => '',
                'fax' => ''
            ],
            'email' => [
                'from_name' => [
                    WASH => 'No reply from ABS Truck Wash and Lube SK Ltd.', 
                    PARTS => 'No reply from ABS Truck & Trailer Parts SK Ltd.'
                ],
                'bcc' => [
                    PARTS => 'abstruckparts.regina@gmail.com', 
                    WASH => '',
                ],
            ],
            'payment_details' => [
                'email_id' => 'abstruckparts.regina@gmail.com',
                'checks' => [
                    'payable_to' => '',
                    'address' => '',
                ],
            ],
            'cipher_key' => 'Ewk"tuZ2<$tqwnF2tc3X%5r(d?/:|9JU',
        ],
    ];
}

// No. of Stores
define('NO_OF_STORES', count(StoreDetails::STORE_DETAILS));

// Federal Tax Rate
define('FEDERAL_TAX_RATE',  5.00);

// Provincial Tax Rate
define('PROVINCIAL_TAX_RATE', is_numeric($_SESSION['store_id'] ?? null) ? StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['pst_tax_rate'] : null);

// Harmonized Sales Tax
define('HARMONIZED_SALES_TAX', is_numeric($_SESSION['store_id'] ?? null) ? StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['hst_tax_rate'] : null);

// Use HST For Store
define('USE_HST_FOR_STORE', is_numeric($_SESSION['store_id'] ?? null) ? StoreDetails::STORE_DETAILS[$_SESSION['store_id']]['use_hst'] : null);

// GST/HST Tax Rate *
define('GST_HST_TAX_RATE', USE_HST_FOR_STORE ? HARMONIZED_SALES_TAX : FEDERAL_TAX_RATE);

// Total Tax Rate 
define('TOTAL_TAX_RATE', USE_HST_FOR_STORE ? HARMONIZED_SALES_TAX : FEDERAL_TAX_RATE + PROVINCIAL_TAX_RATE);

?>