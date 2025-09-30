/*
This file defines the database structure used by the application.

https://sebhastian.com/mysql-insert-if-not-exists/

# Change Timezone 
[mysqld]
default-time-zone = "+00:00"
wait_timeout = 1200
max_allowed_packet = 1024M

[PHP.INI]
max_execution_time = 0
max_input_time = 0
memory_limit = 1024M
upload_max_filesize = 1024M
post_max_size = 1024M
date.timezone=UTC

[config.inc.php and config.default.php]
$cfg['ExecTimeLimit'] = 0;

@author Dewang Saxena, <dewang2610@gmail.com>
@date July 13, 2023
*/

/* Store Details */
CREATE TABLE store_details(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(32) NOT NULL UNIQUE,
    profit_margins JSON NOT NULL DEFAULT '{}',
    restocking_rate NUMERIC(4, 4) UNSIGNED DEFAULT 0,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_store_location ON store_details(`name`);

/* Add Stores */
INSERT INTO store_details
(
    id,
    `name`
)
VALUES
(
    1, 
    'All Stores'
),
(
    2, 
    'Edmonton'
),
(
    3, 
    'Calgary'
),
(
    4, 
    'Nisku'
),
(
    5, 
    'Vancouver'
),
(
    6, 
    'Slave Lake'
),
(
    7, 
    'Delta'
),
(
    8,
    'Regina'
),
(
    9,
    'Saskatoon'
);

/* User Information */ 
CREATE TABLE users(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'This will also act as the User ID',
    `name` VARCHAR(40) NOT NULL COMMENT 'The name of the user.',
    `username` VARCHAR(24) NOT NULL UNIQUE COMMENT 'The username of the user.',
    `password` VARCHAR(256) NOT NULL COMMENT 'The Password.',
    access_level TINYINT UNSIGNED NOT NULL COMMENT '0 -> Admin, 1 -> Sales Associate, 2 -> Read Only',
    store_id SMALLINT UNSIGNED NOT NULL,
    has_access TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Access Flag: 1 => yes, 0 => no.',
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_users_store_id FOREIGN KEY(store_id) REFERENCES store_details(id)
);
ALTER TABLE users AUTO_INCREMENT=10000;

/* Client */
CREATE TABLE IF NOT EXISTS clients(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(128) NOT NULL,
    contact_name VARCHAR(64) NOT NULL,
    street1 VARCHAR(64) NOT NULL,
    street2 VARCHAR(64),
    city VARCHAR(31) NOT NULL,
    province VARCHAR(85) NOT NULL,
    postal_code CHAR(10) NOT NULL,
    country SMALLINT UNSIGNED NOT NULL COMMENT 'https://en.wikipedia.org/wiki/ISO_3166-1_numeric',
    phone_number_1 CHAR(11),
    phone_number_2 CHAR(11),
    fax CHAR(11),
    email_id VARCHAR(320),
    additional_email_addresses TEXT DEFAULT NULL,
    client_since DATE,
    disable_credit_transactions BOOLEAN NOT NULL DEFAULT 0,
    is_default_shipping_address BOOLEAN NOT NULL DEFAULT 0,
    default_receipt_payment_method TINYINT UNSIGNED DEFAULT NULL,
    default_payment_method TINYINT UNSIGNED NOT NULL DEFAULT 0,
    standard_discount JSON NOT NULL DEFAULT '{}',
    standard_profit_margins JSON NOT NULL DEFAULT '{}',
    early_payment_discount JSON NOT NULL DEFAULT '{}',
    early_payment_paid_within_days JSON NOT NULL DEFAULT '{}',
    net_amount_due_within_days JSON NOT NULL DEFAULT '{}',
    produce_statement_for_client BOOLEAN DEFAULT 1,
    memo TEXT,
    additional_information TEXT,
    is_inactive JSON NOT NULL DEFAULT '{}',

    /* Category */ 
    category TINYINT UNSIGNED NOT NULL DEFAULT 1,

    /*
    This is the MAX credit limit available to a client.

    Here NULL signifies absence of any credit limit.
    Whereas NOT NULL Values signifies the credit limit. Eg. 0, 1000, 10000
    */
    credit_limit JSON NOT NULL DEFAULT '{}',

    /* Amount Owing */ 
    amount_owing JSON NOT NULL DEFAULT '{}',

    /* This will store Addresses in a JSON format. 
    Shipping Addresses(Ship-to-addresses) will also contain an additional field "default-ship-to-address". 
    */
    shipping_addresses JSON,

    /*
    This will store the name history.
    */
    name_history JSON DEFAULT '[]',

    /* Custom Selling Price for Items */ 
    custom_selling_price_for_items JSON NOT NULL DEFAULT '{}',

    /* Disable Taxes for Client */ 
    disable_federal_taxes JSON NOT NULL DEFAULT '{}',
    disable_provincial_taxes JSON NOT NULL DEFAULT '{}',

    /* Last Purchase Date */ 
    last_purchase_date JSON NOT NULL DEFAULT '{}',

    /* Send Quotations to additional Email Addresses */
    send_quotations_to_additional_email_addresses JSON NOT NULL DEFAULT '{}',
    
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
/* Auto Increment Update */
ALTER TABLE clients AUTO_INCREMENT=10000;
CREATE INDEX idx_client_name ON clients(`name`);
CREATE INDEX idx_client_phone1 ON clients(phone_number_1);
CREATE INDEX idx_client_phone2 ON clients(phone_number_2);

/* ITEMS */
CREATE TABLE IF NOT EXISTS items(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(128) UNIQUE NOT NULL COMMENT 'code = item_identifier + description. Used for faster indexing.',
    identifier CHAR(64) UNIQUE NOT NULL,
    `description` VARCHAR(64),
    `oem` VARCHAR(64) DEFAULT NULL,

    /* Service / Inventory */
    category TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0: Service, 1: Inventory',
    unit VARCHAR(16),

    /* Prices */
    prices JSON NOT NULL DEFAULT '{}',

    /* Linked Accounts */
    account_assets SMALLINT UNSIGNED NOT NULL,
    account_revenue SMALLINT UNSIGNED NOT NULL,
    account_cogs SMALLINT UNSIGNED NOT NULL,
    account_variance SMALLINT UNSIGNED NOT NULL,
    account_expense SMALLINT UNSIGNED NOT NULL,

    /* 0 = Inactive, 1 = Active */
    is_inactive JSON NOT NULL DEFAULT '{}',

    /* Is Core? */ 
    is_core TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Is this item core?',

    memo TEXT,
    additional_information TEXT,

    /* Reorder Quantity */ 
    reorder_quantity JSON NOT NULL DEFAULT '{}',

    /* Images */ 
    images JSON NOT NULL DEFAULT '{}',

    /* Discount Disabled */
    is_discount_disabled JSON NOT NULL DEFAULT '{}',

    /* Last Sold */
    last_sold JSON NOT NULL DEFAULT '{}',

    /* Dates */ 
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_items ON items(code);

/* Inventory */
CREATE TABLE IF NOT EXISTS inventory(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    store_id SMALLINT UNSIGNED NOT NULL,
    aisle VARCHAR(8),
    shelf VARCHAR(8),
    `column` VARCHAR(8), 
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_inv_location FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_inv_item_id FOREIGN KEY(item_id) REFERENCES items(id)
);
CREATE INDEX idx_inventory ON inventory(item_id);

/* Inventory History */ 
CREATE TABLE IF NOT EXISTS inventory_history(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `details` JSON NOT NULL,
    sales_rep_id INT UNSIGNED NOT NULL, 
    store_id SMALLINT UNSIGNED NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_inv_history_location FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_inv_history_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);

/* Sales Invoice */
CREATE TABLE IF NOT EXISTS sales_invoice(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    shipping_address JSON NOT NULL,
    credit_amount NUMERIC(13, 4) DEFAULT 0,
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    receipt_discount NUMERIC(13, 4) NOT NULL,
    payment_method TINYINT UNSIGNED NOT NULL,
    details JSON NOT NULL,
    po VARCHAR(32) DEFAULT NULL,
    unit_no VARCHAR(32) DEFAULT NULL,
    vin VARCHAR(17) DEFAULT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    sales_rep_history JSON NOT NULL DEFAULT '[]',
    driver_name VARCHAR(64) DEFAULT NULL,
    odometer_reading VARCHAR(32)  DEFAULT NULL,
    trailer_number VARCHAR(32) DEFAULT NULL,

    /* Amount Eligible for Receipt Discount */
    amount_eligible_for_receipt_discount NUMERIC(13, 4) NOT NULL DEFAULT 0,

    /* COGS */ 
    cogs NUMERIC(13, 4) NOT NULL,

    /* Federal Taxes Status */ 
    disable_federal_taxes TINYINT NOT NULL DEFAULT 0, 

    /* Provincial Taxes Status */
    disable_provincial_taxes TINYINT NOT NULL DEFAULT 0,

    /* Early Payment Discount */ 
    early_payment_discount TINYINT UNSIGNED NOT NULL DEFAULT 0,
    early_payment_paid_within_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
    net_amount_due_within_days TINYINT UNSIGNED NOT NULL DEFAULT 0,

    /* Is Invoice Transferred */
    is_invoice_transferred TINYINT NOT NULL DEFAULT 0,

    /* Account Number */
    account_number VARCHAR(32) DEFAULT NULL,

    /* Purchased By */
    purchased_by VARCHAR(32) DEFAULT NULL,

    /* Versions */ 
    versions JSON DEFAULT NULL,

    /* Lock Invoice. This is to prevent editing of invoice if any receipt is made. */ 
    __lock_counter SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    /* Meta Data */
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_si_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_si_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_si_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE sales_invoice AUTO_INCREMENT = 10000;
CREATE INDEX idx_sales_invoice_client_id ON sales_invoice(client_id);
CREATE INDEX idx_sales_invoice_store_id ON sales_invoice(store_id);
CREATE INDEX idx_sales_invoice_sales_rep_id ON sales_invoice(sales_rep_id);

/* Sales Return */
CREATE TABLE IF NOT EXISTS sales_return(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sales_invoice_id BIGINT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    shipping_address JSON NOT NULL,
    credit_amount NUMERIC(13, 4) DEFAULT 0,
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    receipt_discount NUMERIC(13, 4) NOT NULL,
    restocking_fees NUMERIC(13, 4) UNSIGNED NOT NULL,
    payment_method TINYINT UNSIGNED NOT NULL,
    details JSON NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    sales_rep_history JSON NOT NULL DEFAULT '[]',

    -- Transaction Specific Details 
    po VARCHAR(32) DEFAULT NULL,
    unit_no VARCHAR(32) DEFAULT NULL,
    vin VARCHAR(17) DEFAULT NULL,

    /* Amount Eligible for Receipt Discount */
    amount_eligible_for_receipt_discount NUMERIC(13, 4) NOT NULL DEFAULT 0,

    /* C.O.G.R */ 
    cogr NUMERIC(13, 4) NOT NULL,

    /* Federal Taxes Status */ 
    disable_federal_taxes TINYINT NOT NULL DEFAULT 0, 

    /* Provincial Taxes Status */
    disable_provincial_taxes TINYINT NOT NULL DEFAULT 0,

    /* Early Payment Discount */ 
    early_payment_discount TINYINT UNSIGNED NOT NULL DEFAULT 0,
    early_payment_paid_within_days TINYINT UNSIGNED NOT NULL DEFAULT 0,
    net_amount_due_within_days TINYINT UNSIGNED NOT NULL DEFAULT 0,

    /* Sales Invoice Payment Method */
    sales_invoice_payment_method TINYINT UNSIGNED NOT NULL,

    /* Versions */ 
    versions JSON DEFAULT NULL,

    /* Lock Invoice. This is to prevent editing of invoice if any receipt is made. */ 
    __lock_counter SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    /* Meta Data */
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_sr_sales_invoice_id FOREIGN KEY(sales_invoice_id) REFERENCES sales_invoice(id),
    CONSTRAINT FK_sr_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_sr_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_sr_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE sales_return AUTO_INCREMENT = 10000;
CREATE INDEX idx_sales_return_client_id ON sales_return(client_id);
CREATE INDEX idx_sales_return_store_id ON sales_return(store_id);
CREATE INDEX idx_sales_return_sales_rep_id ON sales_return(sales_rep_id);

/* Credit Note */
CREATE TABLE IF NOT EXISTS credit_note(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    credit_amount NUMERIC(13, 4) DEFAULT 0,
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    details JSON NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    sales_rep_history JSON NOT NULL DEFAULT '[]',

    /* Federal Taxes Status */ 
    disable_federal_taxes TINYINT NOT NULL DEFAULT 0, 

    /* Provincial Taxes Status */
    disable_provincial_taxes TINYINT NOT NULL DEFAULT 0,

    /* Versions */ 
    versions JSON DEFAULT NULL,

    /* Lock Txn. This is to prevent editing of txn if any receipt is made. */ 
    __lock_counter SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    /* Meta Data */
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_cn_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_cn_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_cn_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE credit_note AUTO_INCREMENT = 10000;
CREATE INDEX idx_credit_note_client_id ON credit_note(client_id);
CREATE INDEX idx_credit_note_store_id ON credit_note(store_id);
CREATE INDEX idx_credit_note_sales_rep_id ON credit_note(sales_rep_id);

/* Debit Note */
CREATE TABLE IF NOT EXISTS debit_note(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    credit_amount NUMERIC(13, 4) DEFAULT 0,
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    details JSON NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    sales_rep_history JSON NOT NULL DEFAULT '[]',

    /* Federal Taxes Status */ 
    disable_federal_taxes TINYINT NOT NULL DEFAULT 0, 

    /* Provincial Taxes Status */
    disable_provincial_taxes TINYINT NOT NULL DEFAULT 0,

    /* Versions */ 
    versions JSON DEFAULT NULL,
    
    /* Lock Txn. This is to prevent editing of txn if any receipt is made. */ 
    __lock_counter SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    /* Meta Data */
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_dn_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_dn_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_dn_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE debit_note AUTO_INCREMENT = 10000;
CREATE INDEX idx_debit_note_client_id ON debit_note(client_id);
CREATE INDEX idx_debit_note_store_id ON debit_note(store_id);
CREATE INDEX idx_debit_note_sales_rep_id ON debit_note(sales_rep_id);

/* Quotations */
CREATE TABLE IF NOT EXISTS quotation(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    details JSON NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    sales_rep_history JSON NOT NULL DEFAULT '[]',

    /* Account Number */
    account_number VARCHAR(32) DEFAULT NULL,

    /* Federal Taxes Status */ 
    disable_federal_taxes TINYINT NOT NULL DEFAULT 0, 

    /* Provincial Taxes Status */
    disable_provincial_taxes TINYINT NOT NULL DEFAULT 0,

    /* Versions */ 
    versions JSON DEFAULT NULL,

    /* Meta Data */
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_qt_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_qt_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_qt_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE quotation AUTO_INCREMENT=10000;
CREATE INDEX idx_quotation_client_id ON quotation(client_id);
CREATE INDEX idx_quotation_store_id ON quotation(store_id);
CREATE INDEX idx_quotation_sales_rep_id ON quotation(sales_rep_id);

/**
 * This will store income statements as per timestamp.
 */
CREATE TABLE income_statement(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `statement` JSON NOT NULL,
    `date` DATE NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_is_store_id FOREIGN KEY(store_id) REFERENCES store_details(id)
);
CREATE INDEX idx_income_statement_date ON income_statement(`date`);

/**
 * This will store balance statements as per timestamp.
 */
CREATE TABLE balance_sheet(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `statement` JSON NOT NULL,
    `date` DATE NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_bs_store_id FOREIGN KEY(store_id) REFERENCES store_details(id)
);
CREATE INDEX idx_balance_sheet_date ON balance_sheet(`date`);

/**
 * Customer Aged Summary.
 */
CREATE TABLE customer_aged_summary(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `statement` JSON NOT NULL,
    `date` DATE NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_cs_store_id FOREIGN KEY(store_id) REFERENCES store_details(id)
);
CREATE INDEX idx_customer_aged_summary_date ON customer_aged_summary(`date`);

/*
 * Receipts.
 */
CREATE TABLE receipt(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW(),
    details JSON NOT NULL,
    sum_total NUMERIC(13, 4) NOT NULL,
    total_discount NUMERIC(13, 4) NOT NULL,
    payment_method TINYINT UNSIGNED NOT NULL,
    cheque_number VARCHAR(32) DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    do_conceal BOOLEAN NOT NULL DEFAULT FALSE,
    sales_rep_id INT UNSIGNED NOT NULL, 
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_rc_client_id FOREIGN KEY(client_id) REFERENCES clients(id),
    CONSTRAINT FK_rc_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_rc_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE receipt AUTO_INCREMENT=10000;
CREATE INDEX idx_receipt_client_id ON receipt(client_id);
CREATE INDEX idx_receipt_sales_rep_id ON receipt(sales_rep_id);

-- Purchase Vendors
CREATE TABLE purchase_vendors(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(128) NOT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE purchase_vendors AUTO_INCREMENT=10000;
CREATE INDEX idx_purchase_vendors_name ON purchase_vendors(`name`);

/*
-- Vendors
CREATE TABLE vendors(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(128) NOT NULL,
    is_inactive JSON NOT NULL DEFAULT '{}',
    total_purchased NUMERIC(13, 4) NOT NULL DEFAULT 0,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE vendors AUTO_INCREMENT = 10000;
CREATE INDEX idx_vendor_name ON vendors(`name`);

-- Purchase Invoices
CREATE TABLE purchase_invoices(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL DEFAULT NOW() COMMENT 'This store date in Local Timzone.',
    credit_amount NUMERIC(13, 4) DEFAULT 0,
    sum_total NUMERIC(13, 4) NOT NULL,
    sub_total NUMERIC(13, 4) NOT NULL,
    gst_hst_tax NUMERIC(13, 4) NOT NULL,
    pst_tax NUMERIC(13, 4) NOT NULL,
    txn_discount NUMERIC(13, 4) NOT NULL,
    details JSON NOT NULL,
    store_id SMALLINT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL COMMENT 'Txn. Specific Notes.',
    sales_rep_id INT UNSIGNED NOT NULL, 
    /* COGP: Cogs of Goods Purchased */ 
    cogp NUMERIC(13, 4) NOT NULL,
    purchased_by VARCHAR(32) DEFAULT NULL,
    account_number VARCHAR(32) DEFAULT NULL,
    po VARCHAR(32) DEFAULT NULL,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_vp_vendor_id FOREIGN KEY(vendor_id) REFERENCES vendors(id),
    CONSTRAINT FK_vp_store_id FOREIGN KEY(store_id) REFERENCES store_details(id),
    CONSTRAINT FK_vp_sales_rep_id FOREIGN KEY(sales_rep_id) REFERENCES users(id)
);
ALTER TABLE purchase_invoices AUTO_INCREMENT = 10000;
CREATE INDEX idx_purchase_invoices_vendor_id ON purchase_invoices(vendor_id);
CREATE INDEX idx_purchase_invoices_store_id ON purchase_invoices(store_id);
CREATE INDEX idx_purchase_invoices_sales_rep_id ON purchase_invoices(sales_rep_id);
*/