<?php 
/**
 * This module will generate PDF for Sales Invoice/Sales Return/Credit Note/Debit Note.
 * 
 * Convert Custom font to .php 
 * https://www.fpdf.org/makefont/
 * Download all files and place them in fpdf\font.
 * 
 * @author Dewang Saxena, dewang2610@gmail.com
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/utils.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/receipt.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/third_party/fpdf/fpdf.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/modules/transactions/sales_return.php";

class __GeneratePDF_SI_SR_CN_DN_QT_Traction {

    // Max Characters acceptable per field in table
    private static $MAX_CHARACTER_PER_FIELD = [
        'identifier' => 15,
        'unit' => 8,
        'quantity' => 4,
        'description' => [31, 25],
        'tax' => 7,
        'basePrice' => 15,
        'discount' => 7,
        'pricePerItem' => 15,
        'amount' => 15,
        'restockingRate' => 6,
    ];

    // Keys 
    public const KEYS = ['identifier', 'unit', 'quantity', 'description', 'tax', 'basePrice', 'discountRate', 'pricePerItem', 'amountPerItem', 'isBackOrder'];

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const ARIAL = 'Arial';
    private const COURIER = 'Courier';

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Width for table elements 
    private const TABLE_ELEMENTS_WIDTH = [28, 15, 10, 45, 12, 22, 12, 22, 22, 10];

    // Data Table details
    private const MAX_ROWS_FIRST_PAGE = [26, 43];
    private const MAX_ROWS_SUBSEQUENT_PAGES = 65;
    private const MAX_ROWS_SUBSEQUENT_PAGES_WITHOUT_FOOTER = 55;

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // No. of pages
    private static $no_of_pages = 1;
    private static $no_of_additional_pages = 0;

    // No. of rows
    private static $no_of_rows = 0;
    private static $first_page_rows = 0;

    // Flags
    private static $is_wash = null;
    private static $is_parts = null;
    private static $is_multi_page = false;
    private static $add_new_page_just_for_footer = false;
    private static $transaction_type = null;

    // Backorder
    private const BACKORDER_TAG = '(BACKORDER) ';

    // Row height
    private const TABLE_ROW_HEIGHT = 3.5;

    /**
     * This method will add payment details.
     */
    private static function payment_details() : void {

        // Set Font For Title
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:80, h:4, txt: 'PAYMENTS CAN BE MADE VIA.', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7.5);
        self::$pdf -> Cell(w:40, h:4, txt: 'Interac e-Transfer:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w:80, h:4, txt: STORE_DETAILS[self::$details['store_id']]['payment_details']['email_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:38, h:4, txt: 'CHECKS PAYABLE TO:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 7);
        self::$pdf -> Cell(w:30, h:4, txt: STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['payable_to'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w:30, h:4, txt: STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['address'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
    }

    /**
     * This method will add transaction details.
     * @return void 
     */
    private static function add_transaction_details(): void {

        // Set Font For Title
        self::$pdf -> SetFont(self::ARIAL, 'B', 13);

        // Offset for Right Section
        $offset_right_section = 150;

        // Company Name
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: self::$details['company_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);

        // Document Type
        $document_type = strtoupper(self::$details['document_type']);
        if(self::$transaction_type == QUOTATION) {
            $document_type = 'TEMP INVOICE';
        }
        self::$pdf -> Cell(w:38, h:4, txt: $document_type === 'SALES INVOICE' ? 'INVOICE': $document_type, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Change font for details
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);

        // Temp invoice
        if(self::$transaction_type == QUOTATION) {
            self::$details['document_type'] = 'Temp Invoice';
        }

        // Line 1
        self::$pdf -> Cell(w: $offset_right_section, h:4, txt: self::$details['company_address_line_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: self::$details['document_type'].' #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['document_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 2
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: self::$details['company_address_line_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Date:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: strtoupper(self::$details['date']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 3
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: self::$details['company_address_line_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'PO:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['po'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 4
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: 'TEL: '. self::$details['company_tel'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Unit #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['unit_no'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 5
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: 'FAX: '. self::$details['company_fax'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'VIN #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['vin'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Custom Details will be from Line 6
        // Line 6 
        if(self::$details['config_mode'] === WASH) {
            // Driver name
            self::$pdf -> SetFont(self::ARIAL, '', 7.5);
            self::$pdf -> Cell(w:$offset_right_section, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 20, h:4, txt: 'Driver Name:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 7.5);
            self::$pdf -> Cell(w: 0, h:4, txt: self::$details['driver_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Odometer Reading
            self::$pdf -> SetFont(self::ARIAL, '', 7.5);
            self::$pdf -> Cell(w:$offset_right_section, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 20, h:4, txt: 'Odometer RD.:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 7.5);
            self::$pdf -> Cell(w: 0, h:4, txt: self::$details['odometer_reading'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Trailer Number
            self::$pdf -> SetFont(self::ARIAL, '', 7.5);
            self::$pdf -> Cell(w:$offset_right_section, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 20, h:4, txt: 'Trailer Number:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 7.5);
            self::$pdf -> Cell(w: 0, h:4, txt: self::$details['trailer_number'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }   
        
        // Purchased By 
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Purchased By:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['purchased_by'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w: 30, h:4, txt: 'Sales Representative:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 50, h:4, txt: strtoupper(self::$details['sales_rep_name']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);

        // Account Number
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section - 80, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 22, h:4, txt: 'Account Number:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['account_number'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Last Line 
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:$offset_right_section, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Page #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        $no_of_pages = self::$no_of_pages;
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: "1 of $no_of_pages", border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
    }

    /**
     * This method will add client details to pdf.
     * @return void
     */
    private static function add_client_details() : void {

        // Skip for Credit and Debit note
        if (self::$transaction_type !== 1 && self::$transaction_type !== 2 && self::$transaction_type !== 5) return;

        // Offset
        $offset = 120;

        // Change font for details
        self::$pdf -> SetFont(self::ARIAL, 'B', 7.5);
        self::$pdf -> Cell(w: $offset, h:4, txt: self::$transaction_type == 5 ? 'Generated for:' : 'Sold to:', border: self::SHOW_BORDER_FOR_DEBUG, ln: self::$is_wash ? 1 : (self::$transaction_type === 1 && self::$is_parts ? 0 : 1));

        // Ship to (PARTS ONLY)
        if(self::$transaction_type === 1 && self::$is_parts) self::$pdf -> Cell(w: 20, h:4, txt: 'Ship to:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        
        // Client Details
        self::$pdf -> Cell(w: $offset, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: self::$transaction_type === 1 && self::$is_parts ? 0 : 1);
        if(self::$transaction_type === 1 && self::$is_parts) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Switch to new line
        $ln = isset(self::$details['client_details']['ship_to']['client_address_1'][0]) && self::$transaction_type === 1 && self::$is_parts ? 0 : 1;
        
        // Client Address 
        if(isset(self::$details['client_details']['sold_to']['client_address_1'][0])) self::$pdf -> Cell(w: $offset, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: $ln);
        if(self::$transaction_type === 1 && self::$is_parts) {
            if(isset(self::$details['client_details']['ship_to']['client_address_1'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }

        $ln = isset(self::$details['client_details']['ship_to']['client_address_2'][0]) && self::$transaction_type === 1 && self::$is_parts ? 0 : 1;
        if(isset(self::$details['client_details']['sold_to']['client_address_2'][0])) self::$pdf -> Cell(w: $offset, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: $ln);
        if(self::$transaction_type === 1 && self::$is_parts) {
            if(isset(self::$details['client_details']['ship_to']['client_address_2'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }

        $ln = isset(self::$details['client_details']['ship_to']['client_address_3'][0]) && self::$transaction_type === 1 && self::$is_parts ? 0 : 1;
        if(isset(self::$details['client_details']['sold_to']['client_address_3'][0])) self::$pdf -> Cell(w: $offset, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: $ln);
        if(self::$transaction_type === 1 && self::$is_parts) {
            if(isset(self::$details['client_details']['ship_to']['client_address_3'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }
    }

    /**
     * This method will format for backorder.
     * @param details
     */
    private static function format_for_backorder(array &$details): void {
        if($details['isBackOrder'] == 1) {
            $details['identifier'] = '('. $details['identifier'].')';
            $details['quantity'] = '('. $details['quantity'].')'; 
            $details['description'] = self::BACKORDER_TAG. $details['description'];
            $details['amountPerItem'] = '('. $details['amountPerItem'].')';
        }
    }

    /**
     * This method will add business number.
     */
    private static function add_business_number() {
        self::$pdf -> SetFont(self::ARIAL, 'B', 7.5);
        self::$pdf -> Cell(w: 40, h:4, txt: 'Business Number:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 40, h:4, txt: self::$details['business_number'], border: self::SHOW_BORDER_FOR_DEBUG, ln: self::$details['show_pst_number'] ? 0 : 1);

        // Add PST Number where applicable
        if(self::$details['show_pst_number']) {
            self::$pdf -> SetFont(self::ARIAL, 'B', 7.5);
            self::$pdf -> Cell(w: 40, h:4, txt: 'PST Number:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 7.5);
            self::$pdf -> Cell(w: 40, h:4, txt: self::$details['pst_number'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }
    }

    /**
     * This method will add table header.
     */
    private static function add_table_header() : void {
        $is_sales_return = self::$transaction_type === SALES_RETURN;

        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[0], h:4, txt: 'ITEM IDENTIFIER', border: 'TLBR', ln: 0);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[1], h:4, txt: 'UNIT', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[2], h:4, txt: 'QTY', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[3] + ($is_sales_return ? 0 : self::TABLE_ELEMENTS_WIDTH[9]), h:4, txt: 'DESCRIPTION', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[4], h:4, txt: 'TAX %', border: 'TRB', ln: 0);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[5], h:4, txt: 'BASE PRICE', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[6], h:4, txt: 'DISC%', border: 'TRB', ln: 0);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[7], h:4, txt: 'UNIT PRICE', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[8], h:4, txt: 'AMOUNT', border: 'TRB', ln: $is_sales_return ? 0 : 1, align: 'C');
        if($is_sales_return) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[9], h:4, txt: 'RSTK', border: 'TRB', ln: 1, align: 'C');
    }

    /**
     * This method will add footer. 
     * @param add_padding Whether to add padding or not.
     */
    private static function footer(int $last_page=2, int $last_page_rows=0, bool $add_padding=true): void {
        if($last_page_rows > 50) self::add_page($last_page);
        if($add_padding) self::$pdf -> SetY(self::$details['pst_tax'] != 0 ? -89 : -83);
        
        // Flag
        $is_sales_return = self::$transaction_type === 2; 
        $is_quotations = self::$transaction_type === 5;
        $is_not_salvage_parts = SYSTEM_INIT_HOST !== __SALVAGE_PARTS__;

        // US Dollar Tag
        $us_dollar_tag = (IS_CURRENCY_USD ? 'US': '');

        // Add Line break
        self::$pdf -> Ln(1);
        self::$pdf -> SetFont(self::COURIER, 'U', 6);
        self::$pdf -> Cell(w: 35, h: 4, txt: Utils::get_local_timestamp(self::$details['timestamp'], self::$details['store_id']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'IB', 8);
        self::$pdf -> Cell(w: 111, h: 4, txt: '*** No Return or Warranty on Electrical Products and Accessories. ***', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'C');
        self::$pdf -> SetFont(self::ARIAL, 'B', 8);

        self::$pdf -> Cell(w: 22, h: 4, txt: 'Subtotal:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 8);
        self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['sub_total'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
        self::$pdf -> SetFont(self::COURIER, 'I', 6);
        self::$pdf -> Cell(w: 35, h: 4, txt: Utils::get_local_timestamp(self::$details['modified'], self::$details['store_id']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 111, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 8);
        self::$pdf -> Cell(w: 22, h: 4, txt: 'Total Discount:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 8);
        self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['txn_discount'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
        self::$pdf -> SetFont(self::ARIAL, 'B', 8);
        
        // Restocking fees for sales return
        if($is_sales_return) {
            self::$pdf -> Cell(w: 146, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'R');
            self::$pdf -> Cell(w: 22, h: 4, txt: 'Restocking Fees:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 8);
            self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['restocking_fees'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
        }
        
        self::$pdf -> Cell(w: 28, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 8);
        self::$pdf -> Cell(w: 118, h: 4, txt: 'Please note all Returns may be subjected upto '. SalesReturn::MAX_RESTOCKING_RATE.'% re-stocking fee.', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'C');

        self::$pdf -> Cell(w: 22, h: 4, txt: 'Total GST/HST Tax:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 8);
        self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['gst_hst_tax'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');

        // Show PST if Provincial Tax rate is to be used.
        if(self::$details['pst_tax'] != 0.00) {
            self::$pdf -> SetFont(self::ARIAL, 'B', 8);
            self::$pdf -> Cell(w: 146, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 22, h: 4, txt: 'Total PST Tax:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 8);
            self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['pst_tax'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
        }

        self::$pdf -> SetFont(self::ARIAL, 'B', 8);
        self::$pdf -> Cell(w: 146, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'C');
        self::$pdf -> Cell(w: 22, h: 4, txt: 'Total Amount:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 8);
        self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['sum_total'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');

        if($is_quotations === false) {
            self::$pdf -> SetFont(self::ARIAL, 'B', 8);
            self::$pdf -> Cell(w: 146, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'C');
            self::$pdf -> Cell(w: 22, h: 4, txt: 'Amount Paid:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 8);
            self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['amount_paid'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');

            self::$pdf -> SetFont(self::ARIAL, 'B', 8);
            self::$pdf -> Cell(w: 146, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'C');
            self::$pdf -> Cell(w: 22, h: 4, txt: $is_sales_return ? 'Amt. O/S:' : 'Amount Owing:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> SetFont(self::COURIER, '', 8);
            self::$pdf -> Cell(w: 0, h: 4, txt: "$$us_dollar_tag ".number_format(self::$details['amount_owing'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
        }   
    
        self::$pdf -> SetFont(self::ARIAL, 'BUI', 13);
        self::$pdf -> Cell(w: 0, h: 6, txt: 'Terms and Conditions', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'C');

        // Messages
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 23, h:2, txt: 'Repair Acknowledgement:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 77, h:2, txt: 'I, the undersigned owner or representative, acknowledge the indebtedness related to the repair', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 18, h:2, txt: 'Warranty Limitation:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 5);

        if($is_not_salvage_parts) {
            self::$pdf -> Cell(w: 0, h:2, txt: 'Subject to the requirement below, all services carry a 30-day warranty from the date '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck' : 'Traction'), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }
        else self::$pdf -> Cell(w: 0, h:2, txt: 'No Warranty Unless mentioned on Invoice. All Sales are final. No returns.', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        
        self::$pdf -> Cell(w: 100, h:2, txt: 'and service work listed above,along with the purchase and installment of any necessary parts and materials. I confirm I have', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        if($is_not_salvage_parts) self::$pdf -> Cell(w: 0, h:2, txt: (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'and Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Heavy Duty Parts').' completed the work. The owner must advise '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and' : 'Traction Heavy Duty Parts'), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        else self::$pdf -> Cell(w: 0, h:2, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 16, h: 2, txt: 'Payment Terms:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 84, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Payment for repairs are due upon the receipt of unit unless charged to customer\'s account. Payment of');
        
        if($is_not_salvage_parts) self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts').' of any warranty. claim within 5 days of the failure date. Certain');
        else self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'account is due in full by the 15\'thday of the month following the statement date. Unpaid balances will be charged interest of');
        
        if($is_not_salvage_parts) {
            self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'Parts, products, accessories, materials, and other items used in completing the repair and servicework may be');
        }
        else self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: '2% per month compounded monthly (26.82% per annum.).');
        
        if($is_not_salvage_parts) self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'manufactured and supplied by third parties. The quality and workmanship of such items are entirely outside the control of ');
        else self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 20, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Garage Keepers Lien:');
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 80, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'By signing below you acknowledge and agree that the vehicle described above is subject to a');

        if(SYSTEM_INIT_HOST != __SALVAGE_PARTS__) {
            $store_name = (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer Parts Ltd. & ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts');
            self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: $store_name. ' makes no warranties, whether expressed, implied,');
        }
        else {
            $store_name = 'ABS Salvage used Parts Ltd.';
            self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt:'');
        }
        
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Garage Keepers\' Lien in favour of '. $store_name. ' as permitted ');
        
        if($is_not_salvage_parts) {
            self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'statutory, or otherwise, including any warranty of merchantability or of fitness for a particular purpose with respect to such');
        }
        else self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');
        self::$pdf -> Cell(w: 100, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'under the Garage Keepers\' Lien Act (Alberta/Canada), as ammended from time to time.');
        if($is_not_salvage_parts) {
            self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'such items. Responsibility for Vehicle and Contents: '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer PartsLtd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'));    
        }
        else self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');
        
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 23, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Warranty Considerations:');
        self::$pdf -> SetFont(self::ARIAL, '', 5);

        if(SYSTEM_INIT_HOST != __SALVAGE_PARTS__) {
            self::$pdf -> Cell(w: 77, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt:  (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'). 'will submit warranty claim');
        }
        else self::$pdf -> Cell(w: 77, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'No Warranty Unless mentioned on Invoice. All Sales are final. No returns.' ); 

        
        if(SYSTEM_INIT_HOST != __SALVAGE_PARTS__) {
            self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'is not responsible for loss or damage to the vehicle, or to articles, left in vehicles, in case of fire, theft,vandalism, or');
            self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'to the manufacturer for any portion of this repair that is designated for warranty considerations. If the manufacturer rejects the');
            self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'accident.');
        }
        else self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: '');

        if(SYSTEM_INIT_HOST != __SALVAGE_PARTS__) {
            self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'or portion of the claim, the owner shall pay that portion which is rejected in accordance with the Payment Terms set out above.');
        }
        
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w: 100, h:5, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: '');
        self::$pdf -> Cell(w: 20, h:5, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Signature:');
        self::$pdf -> Cell(w: 50, h:5, border: 'B', ln: 0, txt: '');
    }
    
    /**
     * This method will add table data.
     * @param data 
     * @param is_last_row
     * @param is_blank
     */
    private static function add_table_data(array $data, bool $is_last_row, bool $is_blank=false) : void {
        
        // Add Bottom Border for cell if last row
        $border = $is_last_row ? 'B' : '';

        // Is Sales Return
        $is_sales_return = self::$transaction_type === SALES_RETURN;

        if($is_blank) {
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[0], h:self::TABLE_ROW_HEIGHT, txt: '', border: "LR$border", ln: 0, align: 'L');
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[1], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0, align: 'L');
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[2], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0);
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[3] + ($is_sales_return ? 0 : + self::TABLE_ELEMENTS_WIDTH[9]), h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0, align: 'L');
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[4], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0);
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[5], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0, align: 'L');
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[6], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0);
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[7], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 0, align: 'L');
            self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[8], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: $is_sales_return ? 0 : 1, align: 'L');
            if($is_sales_return) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[9], h:self::TABLE_ROW_HEIGHT, txt: '', border: "R$border", ln: 1, align: 'L');
        }
        else {
            if(isset($data['identifier'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[0], h: self::TABLE_ROW_HEIGHT, txt: $data['identifier'], border: "LR$border", ln: 0, align: 'L');
            if(isset($data['unit'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[1], h:self::TABLE_ROW_HEIGHT, txt: $data['unit'], border: "R$border", ln: 0, align: 'L');
            if(isset($data['quantity'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[2], h:self::TABLE_ROW_HEIGHT, txt: $data['quantity'], border: "R$border", ln: 0);
            if(isset($data['description'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[3] + ($is_sales_return ? 0 : + self::TABLE_ELEMENTS_WIDTH[9]), h:self::TABLE_ROW_HEIGHT, txt: $data['description'], border: "R$border", ln: 0, align: 'L');
            if(isset($data['tax'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[4], h:self::TABLE_ROW_HEIGHT, txt: $data['tax'], border: "R$border", ln: 0);
            if(isset($data['basePrice'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[5], h:self::TABLE_ROW_HEIGHT, txt: $data['basePrice'], border: "R$border", ln: 0, align: 'L');
            if(isset($data['discountRate'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[6], h:self::TABLE_ROW_HEIGHT, txt: $data['discountRate'], border: "R$border", ln: 0);
            if(isset($data['pricePerItem'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[7], h:self::TABLE_ROW_HEIGHT, txt: $data['pricePerItem'], border: "R$border", ln: 0, align: 'L');
            if(isset($data['amountPerItem'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[8], h:self::TABLE_ROW_HEIGHT, txt: $data['amountPerItem'], border: "R$border", ln: $is_sales_return ? 0 : 1, align: 'L');
            if($is_sales_return) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[9], h: self::TABLE_ROW_HEIGHT, txt: floatval($data['restockingRate'] ?? 0.0). '%', border: "R$border", ln: 1, align: 'L');
        }
    }

    /**
     * This method will add another page.
     */
    private static function add_page(int $current_page) {
        self::$pdf -> AddPage();

        // Add Document ID and Page No.
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        
        // Document ID
        self::$pdf -> Cell(w: 150, h:4, txt: '', border: '', ln: 0);
        self::$pdf -> Cell(w: 25, h:4, txt: self::$details['document_type'].' #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['document_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Page No. 
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w: 150, h:4, txt: '', border: '', ln: 0);
        self::$pdf -> Cell(w: 25, h:4, txt: 'Page #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        $no_of_pages = self::$no_of_pages;
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: "Page $current_page of $no_of_pages", border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(w: 0, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
    }

    /**
     * This method will calculate the no. of required by the last page.
     * @return int 
     */
    private static function no_of_rows_last_page() : int {
        $row_id = self::$first_page_rows;

        // Default
        $last_page_rows = 0;

        // Generate Further pages
        for($page_no = 2; $page_no <= self::$no_of_pages; ++$page_no) {

            // Reset
            $last_page_rows = 0;

            // Calculate no. of rows per page 
            $diff = self::$no_of_rows - $row_id;
            $no_of_rows_per_page = $diff > self::MAX_ROWS_SUBSEQUENT_PAGES ? self::MAX_ROWS_SUBSEQUENT_PAGES: $diff;

            for($i = 0; $i < $no_of_rows_per_page; ++$i) {
                if(isset(self::$details['details'][$row_id++])) ++$last_page_rows;
            }
        }
        return $last_page_rows;
    }

    /**
     * This method will build table. 
     * @param skip_table_header_for_last_page
     * @return int 
     */
    private static function build_table(bool $skip_table_header_for_last_page): int{

        // Last Page Rows
        $last_page_rows = 0;

        // Add Header
        self::add_table_header();

        // Row id
        $row_id = 0;

        // Change Font
        self::$pdf -> SetFont(self::COURIER, '', 8);

        // Put rows in first page first
        for($i = 0 ; $i < self::$first_page_rows; ++$i) {
            if(isset(self::$details['details'][$row_id])) {
                self::add_table_data(self::$details['details'][$row_id], $i == self::$first_page_rows - 1 /*|| !isset(self::$details['details'][$row_id+1]) */); 
            }
            else self::add_table_data([], $i == self::$first_page_rows - 1, true);
            $row_id++;
        }
        
        // Process multiple pages
        if(self::$is_multi_page && self::$add_new_page_just_for_footer === false) {

            // Generate Further pages
            for($page_no = 2; $page_no <= self::$no_of_pages; ++$page_no) {
                self::add_page($page_no);
                if(($skip_table_header_for_last_page && $page_no == self::$no_of_pages) === false) self::add_table_header();

                // Calculate no. of rows per page 
                $diff = self::$no_of_rows - $row_id;
                $no_of_rows_per_page = $diff > self::MAX_ROWS_SUBSEQUENT_PAGES ? self::MAX_ROWS_SUBSEQUENT_PAGES: $diff;

                // Update
                $last_page_rows = $no_of_rows_per_page;

                // Change Font
                self::$pdf -> SetFont(self::COURIER, '', 8);

                for($i = 0; $i < $no_of_rows_per_page; ++$i) {
                    if(isset(self::$details['details'][$row_id])) {
                        self::add_table_data(self::$details['details'][$row_id++], $i == $no_of_rows_per_page - 1);
                    }
                }
            }
        }

        // Just add a new page for display footer
        else if(self::$add_new_page_just_for_footer) {
            self::add_page(2);
        }

        return $last_page_rows;
    }
    
    /**
     * This method will clear all instance variables.
     */
    private static function clear(): void { 
        self::$is_wash = null;
        self::$is_parts = null;
        self::$is_multi_page = null;
        self::$pdf = null;
        self::$details = null;
        self::$no_of_pages = 1;
        self::$no_of_additional_pages = 0;
        self::$no_of_rows = 0;
        self::$first_page_rows = 0;
        self::$add_new_page_just_for_footer = false;
    }

    /**
     * This method will format the record details to properly fill table.
     * @param records
     * @return array
     */
    private static function format(array $records) : array {
        $total_items_sold = count($records);

        // Data Row Index
        $data_row_index = 0;
    
        // Table Data
        $data = [];

        // Check for Sales return
        $is_sales_return = self::$transaction_type === SALES_RETURN ? 1 : 0;

        // Set Data rows is transaction is Sales Return
        if(is_array(self::$MAX_CHARACTER_PER_FIELD['description'])) {
            self::$MAX_CHARACTER_PER_FIELD['description'] = self::$MAX_CHARACTER_PER_FIELD['description'][$is_sales_return ? 1 : 0];
        }

        for($i = 0; $i < $total_items_sold; ++$i) {
            
            // Data 
            $_data = [
                'identifier' => strval($records[$i]['identifier']),
                'unit' => $records[$i]['unit'] ?? 'Each',
                'quantity' => strval($records[$i][$is_sales_return ? 'returnQuantity' : 'quantity']),
                'description' => strval($records[$i]['description']),
                'tax' => number_format($records[$i]['gstHSTTaxRate'] + $records[$i]['pstTaxRate'], 2),
                'basePrice' => number_format(strval($records[$i]['basePrice']), 2),
                'discountRate' => number_format($records[$i]['discountRate'], 2),
                'pricePerItem' => number_format($records[$i]['pricePerItem'], 2),
                'amountPerItem' => number_format($records[$i]['amountPerItem'], 2), 
                'isBackOrder' => $records[$i]['isBackOrder'],
            ];

            // Restocking Rate
            if($is_sales_return) $_data['restockingRate'] = number_format($records[$i]['restockingRate'] ?? 0, 2);

            // Format for Backorder
            self::format_for_backorder($_data);

            // Data Rows 
            $data_rows_required = [
                'identifier' => ceil((strlen($_data['identifier'])) / self::$MAX_CHARACTER_PER_FIELD['identifier']),
                'unit' => ceil(strlen($_data['unit']) / self::$MAX_CHARACTER_PER_FIELD['unit']),
                'quantity' => ceil((strlen($_data['quantity'])) / self::$MAX_CHARACTER_PER_FIELD['quantity']),
                'description' => ceil((strlen($_data['description'])) / self::$MAX_CHARACTER_PER_FIELD['description']),
                'tax' => ceil(strlen($_data['tax']) / self::$MAX_CHARACTER_PER_FIELD['tax']),
                'basePrice' => ceil(strlen($_data['basePrice']) / self::$MAX_CHARACTER_PER_FIELD['basePrice']),
                'discountRate' => ceil(strlen($_data['discountRate']) / self::$MAX_CHARACTER_PER_FIELD['discount']),
                'pricePerItem' => ceil(strlen($_data['pricePerItem']) / self::$MAX_CHARACTER_PER_FIELD['pricePerItem']),
                'amountPerItem' => ceil((strlen($_data['amountPerItem'])) / self::$MAX_CHARACTER_PER_FIELD['amount']),
                'isBackOrder' => 1,
            ];

            // Restocking Rate
            if($is_sales_return) $data_rows_required['restockingRate'] = ceil((strlen($_data['restockingRate'])) / self::$MAX_CHARACTER_PER_FIELD['restockingRate']);

            // Get Max Rows Required
            $max_no_of_rows_required = max(array_values($data_rows_required));

            // Add Total rows required.
            for($j = 0; $j < $max_no_of_rows_required; ++$j) $data[]= [];

            // Keys 
            $keys = array_keys($_data);

            foreach($keys as $key) {
                $no_of_rows_required = $data_rows_required[$key];

                if($no_of_rows_required > 1) {
                    $temp = $data_row_index;
                    for($x = 0 ; $x < $no_of_rows_required; ++$x) {

                        // Index
                        $index = $temp + $x;

                        // Add all keys
                        foreach(self::KEYS as $k) if(!isset($data[$index][$k][0])) $data[$index][$k] = '';
                        $data[$index][$key] = trim(substr($_data[$key], $x * self::$MAX_CHARACTER_PER_FIELD[$key], self::$MAX_CHARACTER_PER_FIELD[$key]));
                    }
                }
                else {
                    $data[$data_row_index][$key] = $_data[$key];
                }
            }

            // Add completed key
            $data_row_index += $max_no_of_rows_required;
            $data[$data_row_index - 1]['completed'] = true;
        }
        return $data;
    }

    /**
     * This function will print Transaction Document.
     * @param details The data to be printed.
     * @param path The full path to the file.
     * @param generate_file
     */
    public static function generate(array $details, string $path, bool $generate_file=false) : void {

        // Set Instance Variables
        self::$is_wash = $details['config_mode'] === WASH;
        self::$is_parts = $details['config_mode'] === PARTS;

        // Transaction Type
        self::$transaction_type = $details['txn_type_id'];

        // Format Records
        $details['details'] = self::format($details['details']);
        
        // Cache
        self::$details = $details;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Margins
        self::$pdf -> SetTopMargin(10);
        self::$pdf -> SetLeftMargin(10);

        // Calculate No. of pages
        // Get no. of rows
        self::$no_of_rows = count(self::$details['details']);

        // Flag
        self::$is_multi_page = self::$no_of_rows > self::MAX_ROWS_FIRST_PAGE[0];

        // Set No. of pages
        self::$first_page_rows = self::$is_multi_page ? self::MAX_ROWS_FIRST_PAGE[1] : (self::$is_wash ? self::MAX_ROWS_FIRST_PAGE[0] - 4 : self::MAX_ROWS_FIRST_PAGE[0]);
        
        self::$no_of_additional_pages = ceil((self::$no_of_rows - self::$first_page_rows) / self::MAX_ROWS_SUBSEQUENT_PAGES);
        if(self::$is_multi_page) {
            if(self::$no_of_additional_pages > 0.0) {
                self::$no_of_pages += self::$no_of_additional_pages;
            }
            else self::$no_of_pages += 1;
        }

        // Flag
        $skip_table_header_for_last_page = false;

        // Add new page 
        if(self::no_of_rows_last_page() >= self::MAX_ROWS_SUBSEQUENT_PAGES_WITHOUT_FOOTER) {
            self::$no_of_pages++;
            $skip_table_header_for_last_page = true;
        }
        
        // Set Flag
        self::$add_new_page_just_for_footer = self::$no_of_rows > self::MAX_ROWS_FIRST_PAGE[0] && self::$no_of_rows < self::MAX_ROWS_FIRST_PAGE[1];

        // Add Initial Page
        self::$pdf -> AddPage();

        // Traction Logo
        if(self::$details['store_id'] == StoreDetails::VANCOUVER || self::$details['store_id'] == StoreDetails::DELTA || self::$details['store_id'] == StoreDetails::CALGARY) {
            self::$pdf->Image(file:PATH_TO_IMAGE_DIR. 'traction_banner.png', x:65, y:14, h: 26, w:80, type:'PNG');
        }

        // Quotations are not valid proof of purchase.
        if(self::$transaction_type === QUOTATION) {
            self::$pdf->Image(PATH_TO_IMAGE_DIR. 'proof_of_purchase.png', 60, 80, 90, 0, 'PNG');
        }

        // Insert Paid Image Only if amount owing is 0.0
        if((self::$transaction_type === SALES_INVOICE || self::$transaction_type === SALES_RETURN || self::$transaction_type === CREDIT_NOTE || self::$transaction_type === DEBIT_NOTE) && self::$details['amount_owing'] == 0.0) {
            self::$pdf->Image(PATH_TO_IMAGE_DIR. 'paid.png', 60, 80, 90, 0, 'PNG');
        }

        // Show Unpaid Image if Amount Owing > 0.0
        if((self::$details['is_old_version'] ?? false) === false && (self::$transaction_type === SALES_INVOICE || self::$transaction_type === SALES_RETURN || self::$transaction_type === CREDIT_NOTE || self::$transaction_type === DEBIT_NOTE) && self::$details['amount_owing'] > 0.0) {
            self::$pdf->Image(PATH_TO_IMAGE_DIR. 'on_account.png', 60, 80, 90, 0, 'PNG');
        }

        // Show Old Version Image
        if(self::$details['is_old_version'] ?? false) {
            self::$pdf->Image(PATH_TO_IMAGE_DIR. 'old_version.png', 60, 80, 90, 0, 'PNG');
        }

        // Transaction Specific Details
        self::add_transaction_details(self::$pdf, $details);
        
        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Payment Details
        self::payment_details();

        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Client Details
        self::add_client_details(self::$pdf, $details, self::$is_parts, self::$is_wash);
        
        // PADDING 
        self::$pdf -> Cell(w: 0, h: 5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Message
        self::$pdf -> Cell(w:10, h:4, txt: '');
        self::$pdf -> SetFont(self::ARIAL, 'BUI', 10);
        self::$pdf -> Cell(w: 0, h:4, txt: "*** {$details['company_name']} OPEN 7 DAYS A WEEK ***", border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Padding 
        self::$pdf -> Cell(w: 0, h: 2, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Business Number
        self::add_business_number();

        // Padding 
        self::$pdf -> Cell(w: 0, h: 1, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Build Table
        $last_page_rows = self::build_table($skip_table_header_for_last_page);

        // Footer
        self::footer(last_page: self::$no_of_pages + 1, last_page_rows: $last_page_rows, add_padding:!self::$add_new_page_just_for_footer);

        // Save on disk
        if($generate_file) self::$pdf -> Output('F', $path);
        else self::$pdf -> Output();

        // Clear 
        self::clear();
    }
}
?>