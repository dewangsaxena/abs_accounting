<?php
/*
This file contains configurations used by the application.

@author Dewang Saxena <dewang2610@gmail.com>
*/

/**
 * Client App Version
 */
define('CLIENT_APP_VERSION', '2.2.25');

/* Hosts */
define('__LOCALHOST__', 0);
define('__ABS_COMPANY__', 1);
define('__WASH_ABS_COMPANY__', 2);
define('__PARTS_ABS_COMPANY__', 3);
define('__ALPHA_WASH_ABS__', 4);
define('__ALPHA_PARTS_ABS__', 5);
define('__TESTING__', 6);
define('__DEMO__', 7);
define('__PARTS_V2__', 8);
define('__WASH_V2__', 9);

/* Modes */
define('WASH', 1);
define('PARTS', 2);

/* Catgeory */
define('CATEGORY_SERVICE', 0);
define('CATEGORY_INVENTORY', 1);

/* Select Credentials Based On Server */
$mode = null;
$domain = $_SERVER['SERVER_NAME'];
if ($domain === 'localhost') {
    $offset = __LOCALHOST__;
    $mode = PARTS;
    if (!defined('IS_LOCALHOST')) {
        define('IS_LOCALHOST', true);
        define('DISABLE_EMAIL_ON_LOCALHOST', true);
    }
} else if ($domain === 'abs.company') {
    $offset = __ABS_COMPANY__;
    $mode = PARTS;
} else if ($domain === 'wash.abs.company') {
    $offset = __WASH_V2__;
    $mode = WASH;
} else if ($domain === 'parts.abs.company') {
    $offset = __PARTS_V2__;
    $mode = PARTS;
} else if ($domain === 'alpha.abs.company') {
    $offset = __ALPHA_WASH_ABS__;
    $mode = PARTS;
} else if ($domain === 'beta.abs.company') {
    $offset = __ALPHA_PARTS_ABS__;
    $mode = PARTS;
} else if ($domain === 'testing.abs.company') {
    $offset = __TESTING__;
    $mode = PARTS;
} else if ($domain === 'demo.abs.company') {
    $offset = __DEMO__;
    $mode = PARTS;
    http_response_code(404);
    die;
} else if ($domain === 'partsv2.abs.company') {
    $offset = __PARTS_V2__;
    $mode = PARTS;
} else if ($domain === 'washv2.abs.company') {
    $offset = __WASH_V2__;
    $mode = WASH;
} else if ($domain === 'parts.absyeg.store') {
    $offset = __PARTS_V2__;
    $mode = PARTS;
} else if ($domain === 'wash.absyeg.store') {
    $offset = __WASH_V2__;
    $mode = WASH;
} else die('Invalid Domain');

/* Business Specific Configuration. */
define('SYSTEM_INIT_MODE', $mode);

// Credentials
// This should always be loaded after the Domain is selected.
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/credentials.php";

// Store Details
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/store_details.php";

/* Temp Directory */
define('TEMP_DIR', "{$_SERVER['DOCUMENT_ROOT']}/tmp/");

/* Images Directory */
define('PATH_TO_IMAGE_DIR', "{$_SERVER['DOCUMENT_ROOT']}/" . (defined('IS_LOCALHOST') ? 'public/images/' : 'images/'));

// Roles(privilege)
define('ADMIN', 0);
define('SALES_REPRESENTATIVE', 1);
define('READ_ONLY', 2);

/* Country Lookup */
const COUNTRY = [
    124 => 'Canada',
    840 => 'United States of America',
    156 => 'China',
    356 => 'India',
];

/* One Time Customer ID */
define('ONE_TIME_CUSTOMER_ID', 8);

// Payment Methods
class PaymentMethod
{
    public const PAY_LATER = 0;
    public const CASH = 1;
    public const CHEQUE = 2;
    public const PAD = 3;
    public const AMERICAN_EXPRESS = 4;
    public const MASTERCARD = 5;
    public const VISA = 6;
    public const ONLINE_PAYMENT = 7;
    public const DEBIT = 8;
    public const DIRECT_DEPOSIT = 9;
    public const FORGIVEN = 10;

    // Debit Payment Methods
    public const DEBIT_PAYMENT_METHODS = [
        'Cash' => self::CASH,
        self::CASH => 'Cash',
        'Cheque' => self::CHEQUE,
        self::CHEQUE => 'Cheque',
        /* Pre-Authorized Debit(PAD) (CANADA ONLY) */
        'PAD' => self::PAD,
        self::PAD => 'PAD',
        'American Express' => self::AMERICAN_EXPRESS,
        self::AMERICAN_EXPRESS => 'American Express',
        'Mastercard' => self::MASTERCARD,
        self::MASTERCARD => 'Mastercard',
        'Visa' => self::VISA,
        self::VISA => 'Visa',
        'Online Payment' => self::ONLINE_PAYMENT,
        self::ONLINE_PAYMENT => 'Online Payment',
        'Debit' => self::DEBIT,
        self::DEBIT => 'Debit',
        'Direct Deposit' => self::DIRECT_DEPOSIT,
        self::DIRECT_DEPOSIT => 'Direct Deposit',
    ];

    /**
     * Receipt Payment Methods.
     */
    const RECEIPT_PAYMENT_METHODS = [
        ...self::DEBIT_PAYMENT_METHODS,
        'Forgiven' => self::FORGIVEN,
        self::FORGIVEN => 'Forgiven',
    ];

    /**
     * This will store forms of payment accepted by the system. It will store values accessibly bidirectionally.
     * This is also compatible with Receipts values.
     */
    public const MODES_OF_PAYMENT = [
        'Pay Later' => PaymentMethod::PAY_LATER,
        PaymentMethod::PAY_LATER => 'Pay Later',
        ...PaymentMethod::DEBIT_PAYMENT_METHODS
    ];
}

/* Access Levels */
define('ACCESS_LEVELS', [ADMIN, SALES_REPRESENTATIVE, READ_ONLY]);

/* LOCK INVENTORY LIMIT */
define('LOCK_INVENTORY_LIMIT', false);

/* Transaction Types */
define('SALES_INVOICE', 1);
define('SALES_RETURN', 2);
define('CREDIT_NOTE', 3);
define('DEBIT_NOTE', 4);
define('QUOTATION', 5);
define('RECEIPT', 6);

/* Transactions Names */
define('TRANSACTION_NAMES', [
    SALES_INVOICE => 'Sales Invoice',
    SALES_RETURN => 'Sales Return',
    CREDIT_NOTE => 'Credit Note',
    DEBIT_NOTE => 'Debit Note',
    QUOTATION => 'Quotation',
    RECEIPT => 'Receipt',
]);

// Transaction abbr
define('TRANSACTION_NAMES_ABBR', [
    SALES_INVOICE => 'IN',
    SALES_RETURN => 'SR',
    CREDIT_NOTE => 'CN',
    DEBIT_NOTE => 'DN',
    QUOTATION => 'QT',
    RECEIPT => 'RT',
]);

/**
 * This method will check for errors. If any, it throw an Exception with an error message.
 * @throws Exception
 */
function assert_success(): void {
    $last_error = error_get_last();
    if (is_null($last_error) === false) throw new Exception($last_error['message'] . ' in file ' . $last_error['file'] . ' on line : ' . $last_error['line']);
}

/* CHANGE THIS TO INVALIDATE ALL EXISTING SESSIONS. */
define('SESSION_TOKEN', 'A(eySY")+Ym`EU4cD%V+.uT!Dt.a@!&LLW?]j[[,f:Hu<"n^RSUK5:ZAPADPsYv|');

/* CHECK USER ACCESS ON REQUEST */
define('CHECK_USER_ACCESS_ON_REQUEST', false);

/* Special Exceptions/Access */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/special_exceptions.php";

// Default Profit Margin Key
define('DEFAULT_PROFIT_MARGIN_KEY', 'DEFAULT');
