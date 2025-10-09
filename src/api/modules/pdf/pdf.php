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

class __GeneratePDF_SI_SR_CN_DN_QT {

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
        self::$pdf -> Cell(w:80, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['email_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:38, h:4, txt: 'CHECKS PAYABLE TO:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 7);
        self::$pdf -> Cell(w:30, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['payable_to'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w:30, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['address'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
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
        self::$pdf -> Cell(w:38, h:4, txt: strtoupper(self::$details['document_type']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Change font for details
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);

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
        self::$pdf -> Cell(w: 40, h:4, txt: self::$details['business_number'], border: self::SHOW_BORDER_FOR_DEBUG, ln: self::$details['pst_tax'] != 0 ? 0 : 1);

        // Add PST Number where applicable
        if((self::$details['pst_tax'] ?? 0) != 0.0) {
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
        self::$pdf -> Cell(w: 0, h:2, txt: 'Subject to the requirement below, all services carry a 30-day warranty from the date '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck' : 'Traction'), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(w: 100, h:2, txt: 'and service work listed above,along with the purchase and installment of any necessary parts and materials. I confirm I have', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 0, h:2, txt: (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'and Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Heavy Duty Parts').' completed the work. The owner must advise '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and' : 'Traction Heavy Duty Parts'), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 16, h: 2, txt: 'Payment Terms:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 84, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Payment for repairs are due upon the receipt of unit unless charged to customer\'s account. Payment of');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts').' of any warranty. claim within 5 days of the failure date. Certain');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'account is due in full by the 15\'thday of the month following the statement date. Unpaid balances will be charged interest of');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'Parts, products, accessories, materials, and other items used in completing the repair and servicework may be');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: '2% per month compounded monthly (26.82% per annum.).');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'manufactured and supplied by third parties. The quality and workmanship of such items are entirely outside the control of ');
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 20, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Garage Keepers Lien:');
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 80, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'By signing below you acknowledge and agree that the vehicle described above is subject to a');
        self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer Parts Ltd. & ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'). ' makes no warranties, whether expressed, implied,');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Garage Keepers\' Lien in favour of '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'). ' as permitted ');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'statutory, or otherwise, including any warrantyof merchantability or of fitness for a particular purpose with respect to such');
        self::$pdf -> Cell(w: 100, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'under the Garage Keepers\' Lien Act (Alberta/Canada), as ammended from time to time.');
        self::$pdf -> Cell(w: 0, h: 2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'such items. Responsibility for Vehicle and Contents: '. (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer PartsLtd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'));
        self::$pdf -> SetFont(self::ARIAL, 'B', 5);
        self::$pdf -> Cell(w: 23, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'Warranty Considerations:');
        self::$pdf -> SetFont(self::ARIAL, '', 5);
        self::$pdf -> Cell(w: 77, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt:  (self::$details['store_id'] != StoreDetails::VANCOUVER && self::$details['store_id'] != StoreDetails::DELTA ? 'ABS Truck and Trailer Parts Ltd. and ABS Truck Wash and Lube Ltd.' : 'Traction Heavy Duty Parts'). 'will submit warranty claim');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'is not responsible for loss or damage to the vehicle, or to articles, left in vehicles, in case of fire, theft,vandalism, or');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, txt: 'to the manufacturer for any portion of this repair that is designated for warranty considerations. If the manufacturer rejects the');
        self::$pdf -> Cell(w: 0, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'accident.');
        self::$pdf -> Cell(w: 100, h:2, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, txt: 'or portion of the claim, the owner shall pay that portion which is rejected in accordance with the Payment Terms set out above.');
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
        self::$MAX_CHARACTER_PER_FIELD['description'] = self::$MAX_CHARACTER_PER_FIELD['description'][$is_sales_return ? 1 : 0];

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
            self::$pdf->Image(PATH_TO_IMAGE_DIR. 'not_proof_of_purchase.png', 60, 80, 90, 0, 'PNG');
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
        self::$pdf -> Cell(w: 0, h:4, txt: "*** {$details['company_name']} NOW OPEN 7 DAYS A WEEK ***", border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

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

/**
 * This class defines methods for Generating Packaging Slip.
 */
class __GeneratePDF_PackagingSlip {

    // Max Characters acceptable per field in table
    public const MAX_CHARACTER_PER_FIELD = [
        'identifier' => 26,
        'quantity' => 4,
        'description' => 100,
    ];

    // Keys 
    public const KEYS = ['identifier', 'quantity', 'description'];

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
    private const TABLE_ELEMENTS_WIDTH = [50, 10, 135];

    // Data Table details
    private const MAX_ROWS_FIRST_PAGE = [34, 62];
    private const MAX_ROWS_SUBSEQUENT_PAGES = 77;

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
    private static $is_multi_page = false;
    private static $add_new_page_just_for_footer = false;

    /**
     * This method will add transaction details.
     * @return void 
     */
    private static function add_transaction_details(): void {

        // Set Font For Title
        self::$pdf -> SetFont(self::ARIAL, 'B', 13);

        // Company Name
        self::$pdf -> Cell(w:120, h:4, txt: self::$details['company_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);

        // Document Type
        self::$pdf -> Cell(w:38, h:4, txt: 'PACKAGING SLIP', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Change font for details
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);

        // Line 1
        self::$pdf -> Cell(w: 120, h:4, txt: self::$details['company_address_line_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Order #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['document_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 2
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:120, h:4, txt: self::$details['company_address_line_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Date:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['date'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 3
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:120, h:4, txt: self::$details['company_address_line_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'PO:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['po'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 4
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:120, h:4, txt: 'TEL: '. self::$details['company_tel'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'Unit #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['unit_no'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 5
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:120, h:4, txt: 'FAX: '. self::$details['company_fax'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 20, h:4, txt: 'VIN #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['vin'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        
        // Last Line 
        self::$pdf -> SetFont(self::ARIAL, '', 7.5);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
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
        
        // Change font for details
        self::$pdf -> SetFont(self::ARIAL, 'B', 7.5);
        self::$pdf -> Cell(w: 100, h:4, txt: 'Sold to:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);

        // Ship to (PARTS ONLY)
        self::$pdf -> Cell(w: 20, h:4, txt: 'Ship to:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        
        // Client Details
        self::$pdf -> Cell(w: 100, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        if(self::$pdf) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Client Address 
        if(isset(self::$details['client_details']['sold_to']['client_address_1'][0])) self::$pdf -> Cell(w: 100, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        if(isset(self::$details['client_details']['ship_to']['client_address_1'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        if(isset(self::$details['client_details']['sold_to']['client_address_2'][0])) self::$pdf -> Cell(w: 100, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        if(isset(self::$details['client_details']['ship_to']['client_address_2'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        if(isset(self::$details['client_details']['sold_to']['client_address_3'][0])) self::$pdf -> Cell(w: 100, h:4, txt: '    '.self::$details['client_details']['sold_to']['client_address_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        if(isset(self::$details['client_details']['ship_to']['client_address_3'][0])) self::$pdf -> Cell(w: 0, h:4, txt: '    '.self::$details['client_details']['ship_to']['client_address_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
    }

    /**
     * This method will add table header.
     */
    private static function add_table_header() : void {
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[0], h:4, txt: 'ITEM IDENTIFIER', border: 'TLBR', ln: 0);
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[1], h:4, txt: 'QTY', border: 'TRB', ln: 0, align: 'C');
        self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[2], h:4, txt: 'DESCRIPTION', border: 'TRB', ln: 1, align: 'C');
    }

    
    /**
     * This method will add table data.
     * @param data 
     */
    private static function add_table_data(array $data, bool $is_last_row) : void {
        // Add Bottom Border for cell if last row
        $border = $is_last_row ? 'B' : '';
        if(isset($data['identifier'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[0], h:4, txt: $data['identifier'], border: "L$border", ln: 0, align: 'L');
        if(isset($data['quantity'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[1], h:4, txt: $data['quantity'], border: $border, ln: 0);
        if(isset($data['description'])) self::$pdf -> Cell(w: self::TABLE_ELEMENTS_WIDTH[2], h:4, txt: $data['description'], border: "R$border", ln: 0, align: 'C');
        
        // Default line break
        $line_breaks = 2;
        if(isset($data['completed']) && $data['completed'] === true) $line_breaks += 1;

        // Render linebreak
        self::$pdf -> Ln($line_breaks);
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
        self::$pdf -> Cell(w: 25, h:4, txt: 'Order #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
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
     * This method will build table. 
     */
    private static function build_table() {
        self::add_table_header();

        // Row id
        $row_id = 0;

        // Change Font
        self::$pdf -> SetFont(self::COURIER, '', 8);

        // Put rows in first page first
        for($i = 0 ; $i < self::$first_page_rows; ++$i) {
            if(isset(self::$details['details'][$row_id])) {
                self::add_table_data(self::$details['details'][$row_id], $i == self::$first_page_rows - 1 || !isset(self::$details['details'][$row_id+1]));
                $row_id++;
            }
        }
        
        // Process multiple pages
        if(self::$is_multi_page && self::$add_new_page_just_for_footer === false) {

            // Generate Further pages
            for($page_no = 2; $page_no <= self::$no_of_pages; ++$page_no) {
                self::add_page($page_no);
                self::add_table_header();

                // Calculate no. of rows per page 
                $diff = self::$no_of_rows - $row_id;
                $no_of_rows_per_page = $diff > self::MAX_ROWS_SUBSEQUENT_PAGES ? self::MAX_ROWS_SUBSEQUENT_PAGES: $diff;

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
    }
    
    /**
     * This method will clear all instance variables.
     */
    private static function clear(): void { 
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

        for($i = 0; $i < $total_items_sold; ++$i) {
    
            // Data 
            $_data = [
                'identifier' => $records[$i]['identifier'],
                'quantity' => $records[$i]['quantity'],
                'description' => $records[$i]['description'],
            ];
    
            // Data Rows 
            $data_rows_required = [
                'identifier' => ceil(strlen($_data['identifier']) / self::MAX_CHARACTER_PER_FIELD['identifier']),
                'quantity' => ceil(strlen($_data['quantity']) / self::MAX_CHARACTER_PER_FIELD['quantity']),
                'description' => ceil(strlen($_data['description']) /self::MAX_CHARACTER_PER_FIELD['description']),
            ];
    
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
                        $data[$index][$key] = substr($_data[$key], $x * self::MAX_CHARACTER_PER_FIELD[$key], self::MAX_CHARACTER_PER_FIELD[$key]);
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
     */
    public static function generate(array $details) : void {

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
        self::$first_page_rows = self::$is_multi_page ? self::MAX_ROWS_FIRST_PAGE[1]: self::$no_of_rows;
        self::$no_of_additional_pages = ceil((self::$no_of_rows - self::$first_page_rows) / self::MAX_ROWS_SUBSEQUENT_PAGES);
        if(self::$is_multi_page) {
            if(self::$no_of_additional_pages > 0.0) {
                self::$no_of_pages += self::$no_of_additional_pages;
            }
            else self::$no_of_pages += 1;
        }

        // Set Flag
        self::$add_new_page_just_for_footer = self::$no_of_rows > self::MAX_ROWS_FIRST_PAGE[0] && self::$no_of_rows < self::MAX_ROWS_FIRST_PAGE[1];

        // Add Initial Page
        self::$pdf -> AddPage();

        // Transaction Specific Details
        self::add_transaction_details();
        
        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Client Details
        self::add_client_details();
        
        // PADDING 
        self::$pdf -> Cell(w: 0, h: 5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Build Table
        self::build_table();

        // Display on browser
        self::$pdf -> Output();

        // Clear 
        self::clear();
    }
}

/**
 * This class defines method for Generating Receipt's PDF. 
 */
class __GeneratePDF_Receipt {

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const SPACE_MONO_REGULAR = 'SpaceMono-Regular';
    private const SPACE_MONO_BOLD = 'SpaceMono-Bold';

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Data Table details
    private const MAX_ROWS_PER_PARTITION_PER_PAGE = [40, 55];
    private const MAX_ROWS_PER_PAGE = [self::MAX_ROWS_PER_PARTITION_PER_PAGE[0] * 2, self::MAX_ROWS_PER_PARTITION_PER_PAGE[1] * 2];

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    /**
     * This method will build header for transaction.
     * @param page_no
     */
    private static function build_transaction_record_header(int $page_no=1): void {

        // Set Left margin
        self::$pdf -> SetLeftMargin(8);
        self::$pdf -> SetRightMargin(8);

        // Box offsets
        $box_offset_x = 8;
        $box_offset_y = $page_no > 1 ? 18 : 76;

        // Create a box
        self::$pdf -> SetLineWidth(0.5);
        self::$pdf -> Rect($box_offset_x, $box_offset_y, 200, 12);

        // Add new lines
        if($page_no === 1) self::$pdf -> Ln(12);
        else {
            self::$pdf -> Cell(0, 4, "Page #: $page_no", border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');
            self::$pdf -> Ln(4);
        }

        // Company Name
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 10);
        self::$pdf -> Cell(0, 6, self::$details['company_name'], border: 'B', ln: 1);

        // Client name
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);
        self::$pdf -> Cell(100, 6, self::$details['client_name'], ln: 0);
        self::$pdf -> Cell(60, 6, self::$details['date'], ln: 0);
        self::$pdf -> Cell(20, 6, 'Receipt #:', ln: 0);
        self::$pdf -> Cell(0, 6, self::$details['id'], ln: 1, align: 'R');
    }

    /**
     * This method will add ledger column.
     */
    private static function add_ledger_columns(): void {

        // Set font to bold
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 8);

        // Left Partition
        self::$pdf -> Cell(40, 4, '', border: 'LT', ln: 0, align: 'L');
        self::$pdf -> Cell(16, 4, 'Discount', border: 'T', ln: 0, align: 'L');
        self::$pdf -> Cell(42, 4, 'Amt. Received', border: 'RT', ln: 0, align: 'R');

        // Right Partition
        self::$pdf -> Cell(40, 4, '', border: 'T', ln: 0, align: 'L');
        self::$pdf -> Cell(16, 4, 'Discount', border: 'T', ln: 0, align: 'L');
        self::$pdf -> Cell(0, 4, 'Amt. Received', border: 'RT', ln: 1, align: 'R');
    }

    /**
     * This method will add transaction record to the receipt body ledger.
     * @param txn
     * @param is_blank
     * @param is_last_row
     */
    private static function add_transactions(array $txn, bool $is_blank=false, bool $is_last_row=false): void {

        // Add bottom border
        $border = $is_last_row ? 'B' : '';

        if($is_blank) {
            self::$pdf -> Cell(40, 4, '', border: "L$border", ln: 0, align: 'L');
            self::$pdf -> Cell(16, 4, '', border: $border, ln: 0, align: 'R');
            self::$pdf -> Cell(42, 4, '', border: "R$border", ln: 0, align: 'R');

            self::$pdf -> Cell(40, 4, '', border: "L$border", ln: 0, align: 'L');
            self::$pdf -> Cell(16, 4, '', border: $border, ln: 0, align: 'R');
            self::$pdf -> Cell(0, 4, '', border: "R$border", ln: 1, align: 'R');
        }
        else {

            // Left Partition 
            self::$pdf -> Cell(40, 4, $txn[0]['id'], border: "L$border", ln: 0, align: 'L');
            
            self::$pdf -> Cell(16, 4, number_format($txn[0]['discount_given'], 2), border: $border, ln: 0, align: 'R');
            self::$pdf -> Cell(42, 4, number_format($txn[0]['amount_received'], 2), "R$border", ln: 0, align: 'R');

            // Right Partition
            self::$pdf -> Cell(40, 4, $txn[1]['id'] ?? '', border: $border, ln: 0, align: 'L');
            $discount = $txn[1]['discount'] ?? null;
            $discount = $discount !== null ? number_format($discount, 2) : '';
            self::$pdf -> Cell(16, 4, $discount ?? '', border: $border, ln: 0, align: 'R');

            // Check for valid number
            $amount_received = $txn[1]['amount_received'] ?? null;
            $amount_received = $amount_received !== null ? number_format($amount_received, 2) : '';
            self::$pdf -> Cell(0, 4, $amount_received, border: "R$border", ln: 1, align: 'R');
        }
    }

    /**
     * This method will partition transactions.
     * @return array
     */
    private static function partition_transactions() : array {

        // Transactions
        $txn = self::$details['receipt_items'];

        // No. of txn
        $no_of_txn = count($txn);

        // Current page index
        $current_page = 0;

        // Will store records
        $records = [];

        // Partition ID
        $partition_id = 0;

        // Rows in current page 
        $rows_in_current_page = 0;

        // Max rows per partition
        $max_rows_per_partition = 0;

        // Max rows in page
        $max_rows_in_current_page = 0;

        for($i = 0; $i < $no_of_txn; ++$i) {
            if(!isset($records[$current_page])) $records[$current_page] = [[], []];
            
            // Set max rows
            $index = $current_page == 0 ? 0 : 1;
            $max_rows_per_partition = self::MAX_ROWS_PER_PARTITION_PER_PAGE[$index];
            $max_rows_in_current_page = self::MAX_ROWS_PER_PAGE[$index];

            // Switch Partition
            if($rows_in_current_page > 0 && $rows_in_current_page % $max_rows_per_partition === 0) $partition_id ^= 1;

            if($rows_in_current_page >= $max_rows_in_current_page) {
                ++$current_page;
                $partition_id = 0;
                $rows_in_current_page = 1;
            }
            else ++$rows_in_current_page;

            // Add to records
            $records[$current_page][$partition_id] []= $txn[$i];            
        }

        // Add Padding rows
        $max_rows_per_partition = self::MAX_ROWS_PER_PARTITION_PER_PAGE[$current_page == 0 ? 0 : 1];

        // Get current no. of rows
        $current_rows_in_partition = count($records[$current_page][$partition_id]);

        for(; $current_rows_in_partition < $max_rows_per_partition; ++$current_rows_in_partition) {
            $records[$current_page][$partition_id] []= null;
        }

        // Restructure and return records
        return self::restructure_records($records);
    }

    /**
     * This method will restructure records.
     * @param txn
     * @return array
     */
    private static function restructure_records(array $txn) : array {
        $records = [];

        // No. of pages
        $no_of_pages = count($txn);

        for($current_page = 0; $current_page < $no_of_pages; ++$current_page) {

            // Max rows per partition
            $max_rows_per_partition = self::MAX_ROWS_PER_PARTITION_PER_PAGE[$current_page === 0 ? 0 : 1];
            
            // Init
            if(!isset($records[$current_page])) $records[$current_page] = [];

            for($i = 0; $i < $max_rows_per_partition; ++$i) {

                // Add row
                if(!isset($records[$current_page][$i])) $records[$current_page][$i] = [];

                // Add to partitions
                if(isset($txn[$current_page][0][$i])) {
                    $records[$current_page][$i] []= $txn[$current_page][0][$i];
                }
                if(isset($txn[$current_page][1][$i])) {
                    $records[$current_page][$i] []= $txn[$current_page][1][$i];
                }
            }
        }
        return $records;
    }

    /**
     * This method will build transactions records table.
     */
    private static function build_transaction_record_table() : void {

        // Page id counter
        $page_id = 1;

        // First page
        self::build_transaction_record_header($page_id);

        // Add ledger column
        self::add_ledger_columns();

        // Partition records
        $txn = self::partition_transactions();
        $no_of_pages = count($txn);

        for($current_page = 0; $current_page < $no_of_pages; ++$current_page) {

            // Max rows per partition
            $max_rows_per_partition = self::MAX_ROWS_PER_PARTITION_PER_PAGE[$current_page === 0 ? 0 : 1];

            // Set font
            self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);

            for($i = 0; $i < $max_rows_per_partition; ++$i) {

                // Add transactions
                self::add_transactions($txn[$current_page][$i], is_blank: !isset($txn[$current_page][$i][0]), is_last_row: $i + 1 === $max_rows_per_partition);

                if($current_page + 1 < $no_of_pages && $i + 1 === $max_rows_per_partition) {

                    // Add Amount
                    self::$pdf -> Cell(0, 4, '$ '. Utils::number_format(self::$details['sum_total']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align:'R');

                    // Add Page
                    self::$pdf -> AddPage();

                    // Update
                    ++$page_id;

                    // Add header
                    self::build_transaction_record_header($page_id);

                    // Add ledger column
                    self::add_ledger_columns();
                }
            }
        }

        // Set Font Styling
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);

        // Add timestamp
        self::$pdf -> Cell(100, 4, self::$details['created_timestamp'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align:'L');

        // Add Amount
        self::$pdf -> Cell(0, 4, (IS_CURRENCY_USD ? 'US': ''). '$ '. Utils::number_format(self::$details['sum_total']), border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align:'R');
    }

    /**
     * This method will build receipt slip.
     */
    private static function build_receipt_slip() : void {
        // Draw a box
        self::$pdf -> SetLineWidth(0.5);
        self::$pdf -> Rect(8, 10, 200, 60);

        // Title
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 16);
        self::$pdf -> Cell(0, 8, 'RECEIPT', border:self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'C');

        // Company Details
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 14);
        self::$pdf -> Cell(160, 6, self::$details['company_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0, align: 'L');

        // Receipt Details
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);
        self::$pdf -> Cell(20, 6, 'Receipt #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(0, 6, self::$details['id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');

        // Company Address
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 8);
        self::$pdf -> Cell(150, 4, self::$details['company_address_line_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'L');

        // Date 
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);

        // Update with format
        self::$details['date'] = Utils::convert_date_to_human_readable(self::$details['date']);
        self::$pdf -> Cell(0, 4, self::$details['date'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'R');

        // Company Address
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 8);
        self::$pdf -> Cell(150, 4, self::$details['company_address_line_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        self::$pdf -> Cell(150, 4, self::$details['company_address_line_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        self::$pdf -> Cell(150, 4, self::$details['company_tel'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        self::$pdf -> Cell(150, 4, self::$details['company_fax'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');

        // Amount Received
        self::$pdf -> Cell(20, 4, '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(35, 4, 'Amount Received:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'L');
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);
        self::$pdf -> Cell(0, 4, (IS_CURRENCY_USD ? 'US': ''). '$ '. number_format(self::$details['sum_total'], 2), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');

        self::$pdf -> Ln(4);

        // Client name
        self::$pdf -> SetFont(self::SPACE_MONO_BOLD, '', 8);
        self::$pdf -> Cell(20, 4, '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(20, 4, 'From:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        self::$pdf -> Cell(20, 4, '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(0, 4, strtoupper(trim(self::$details['client_name'])), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');

        // Signature
        self::$pdf -> SetFont(self::SPACE_MONO_REGULAR, '', 8);
        self::$pdf -> Cell(130, 4, 'Signature:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'R');
        self::$pdf -> Cell(0, 4, '', border: 'B', ln: 1, align: 'R');

        // Draw border around the box
        self::$pdf -> SetLineWidth(.8);
        self::$pdf -> Rect(208, 12, 1.5, 59.5, style:'F');
        self::$pdf -> Rect(10, 70, 199, 1.5, style:'F');
    }

    /**
     * This method will generate receipt for printing.
     * @param details
     * @param path
     * @param generate_file
     */
    public static function generate(array $details, string $path, bool $generate_file=false) : void {

        // Cache Details
        self::$details = $details;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Margins
        self::$pdf -> SetTopMargin(10);
        self::$pdf -> SetLeftMargin(10);

        // Add custom fonts
        self::$pdf -> AddFont(self::SPACE_MONO_REGULAR, '', 'space_mono_regular.php');
        self::$pdf -> AddFont(self::SPACE_MONO_BOLD, '', 'space_mono_bold.php');
        
        // Add page
        self::$pdf -> AddPage();

        // Receipt slip
        self::build_receipt_slip();
        
        // Build Transactions Details
        self::build_transaction_record_table();

        // Save to disk
        if($generate_file) self::$pdf -> Output('F', name: $path);
        else self::$pdf -> Output();
    }
}

/**
 * This class will generate customer statement.
 */
class __GenerateCustomerStatement {

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const ARIAL = 'Arial';
    private const COURIER = 'Courier';

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // No. of pages
    private static $no_of_pages = 1;

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // Fill colors
    private const FILL_COLORS = [true => [211, 211, 211], false => [255, 255, 255]];
    
    // Max rows per page 
    private const MAX_ROW_PER_PAGE = [43, 58];

    // Generate Record
    private static $generate_record = false;

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
        self::$pdf -> Cell(w:80, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['email_id'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:38, h:4, txt: 'CHECKS PAYABLE TO:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::ARIAL, 'B', 7);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::ARIAL, '', 7);
        self::$pdf -> Cell(w:30, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['payable_to'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(w:120, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w:30, h:4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['payment_details']['checks']['address'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
    }

    /**
     * This method will add header details.
     * @return void 
     */
    private static function add_header(): void {

        // Set Font For Title
        self::$pdf -> SetFont(self::COURIER, 'B', 13);

        // Company Name
        self::$pdf -> Cell(w:150, h:4, txt: self::$details['company_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);

        // Document Type
        self::$pdf -> Cell(w:38, h:4, txt: 'STATEMENT', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // PADDING 
        self::$pdf -> Cell(w: 0, h: 1.5, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Change font for details
        self::$pdf -> SetFont(self::COURIER, '', 7.5);

        // Line 1
        self::$pdf -> Cell(w: 110, h:4, txt: self::$details['company_address_line_1'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 7.5);
        self::$pdf -> Cell(w: 10, h:4, txt: 'DATE:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 0, h:4, txt: self::$details['date'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 2
        self::$pdf -> Cell(w:110, h:4, txt: self::$details['company_address_line_2'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w:110, h:4, txt: 'PLEASE RETURN THIS PORTION WITH YOUR PAYMENT.', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Line 3
        self::$pdf -> Cell(w:120, h:4, txt: self::$details['company_address_line_3'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Cell(150, 4, 'TEL:'. self::$details['company_tel'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        self::$pdf -> Cell(150, 4, 'FAX:'. self::$details['company_fax'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');

        // Add break lines
        self::$pdf -> Ln(4);

        // Add Payment Details
        self::payment_details();

        // Add Break Lines
        self::$pdf -> Ln(4);

        // Client Details 
        self::$pdf -> SetFont(self::COURIER, 'B', 7.5);
        self::$pdf -> Cell(w:110, h:4, txt: self::$details['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w:50, h:4, txt: self::$details['client_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(110, 4, self::$details['contact_name'], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0, align: 'L');
        self::$pdf -> Cell(100, 4, 'IF PAYING BY INVOICE, CHECK INDIVIDUAL INVOICES PAID.', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1, align: 'L');
        
        // Add break lines
        self::$pdf -> Ln(4);
        self::$pdf -> Cell(w: 110, h:4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 28, h:4, txt: 'AMOUNT REMITTED:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 58, h:4, txt: '', border:'B', ln: 1);
    }

    /**
     * This method will add page number.
     * @param current_page
     */
    private static function add_page_number (int $current_page) {
        self::$pdf -> SetFont(self::COURIER, '', 7.5);
        self::$pdf -> Cell(w: 15, h:4, txt: 'Page #:', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 90, h:4, txt: "$current_page of ". self::$no_of_pages, border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
    }

    /**
     * This method will add table header.
     */
    private static function add_table_header(): void {
        self::$pdf -> SetFont(self::COURIER, 'B', 8);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Transaction Date', border:1 , ln: 0);
        self::$pdf -> Cell(w: 26, h: 4, txt: 'Transaction #', border: 1, ln: 0);
        self::$pdf -> Cell(w: 32, h: 4, txt: 'Transaction Type', border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Balance', border: 1, ln: 0,);
        self::$pdf -> Cell(w: 22, h: 4, txt: '  Invoice #', border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: self::$generate_record === false ? 'Amount Due': 'Amount Total', border: 1, ln: 0);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: 1, ln: 1);
    }

    /**
     * This method will add table footer.
     */
    private static function add_table_footer(): void {

        // Details
        self::$pdf -> SetFont(self::COURIER, 'B', 8);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Age', border:1 , ln: 0);
        self::$pdf -> Cell(w: 26, h: 4, txt: 'Current', border: 1, ln: 0);
        self::$pdf -> Cell(w: 32, h: 4, txt: '31-60', border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Over 60', border: 1, ln: 0,);
        self::$pdf -> Cell(w: 22, h: 4, txt: '', border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Total', border: 1, ln: 0);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: 1, ln: 1);

        // Value
        self::$pdf -> SetFont(self::COURIER, 'B', 8);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Amount', border:1 , ln: 0);
        self::$pdf -> Cell(w: 26, h: 4, txt: Utils::number_format(self::$details['current']), border: 1, ln: 0);
        self::$pdf -> Cell(w: 32, h: 4, txt: Utils::number_format(self::$details['31-60']), border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: Utils::number_format(self::$details['60+']), border: 1, ln: 0,);
        self::$pdf -> Cell(w: 22, h: 4, txt: '', border: 1, ln: 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: Utils::number_format(self::$details['total_amount']), border: 1, ln: 0);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: 1, ln: 1);
    }

    /**
     * This method will add transaction record.
     * @param txn_record
     * @param do_fill
     * @param is_last_row_of_page
     * @param is_blank
     * @return void 
     */
    private static function add_transaction_records(array $txn_record, bool $do_fill=false, bool $is_last_row_of_page=false, bool $is_blank=false) : void {

        // Set font 
        self::$pdf -> SetFont(self::COURIER, '', 7.5);

        // Select fill color
        $fill_color = self::FILL_COLORS[$do_fill];

        // Set Fill color
        self::$pdf -> SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
        
        // Border config
        $border = $is_last_row_of_page ? 'LRB' : 'LR';

        if($is_blank) {
            self::$pdf -> Cell(w: 35, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 26, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 32, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 35, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 22, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 35, h: 4, txt: '', border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: $border, ln: 1, fill:$do_fill,);
        }
        else {
            self::$pdf -> Cell(w: 35, h: 4, txt: $txn_record['transaction_date'], border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 26, h: 4, txt: $txn_record['transaction_id'], border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 32, h: 4, txt: $txn_record['transaction_type'], border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 35, h: 4, txt: Utils::number_format($txn_record['balance']), border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 22, h: 4, txt: "  {$txn_record['sales_invoice_id']}", border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 35, h: 4, txt: Utils::number_format($txn_record['amount_total']), border: $border, ln: 0, fill:$do_fill,);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: $border, ln: 1, fill:$do_fill,);
        }
    }

    /**
     * This method will generate customer statement.
     * @param details 
     * @param filename
     * @param generate_record
     * @param generate_file
     */
    public static function generate(array $details, string $filename, bool $generate_record, bool $generate_file=false) : void {

        // Cache Details
        self::$details = $details;

        // Generate Record
        self::$generate_record = $generate_record;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Document Name
        self::$pdf -> SetTitle($filename);

        // Set Margins
        self::$pdf -> SetTopMargin(10);
        self::$pdf -> SetLeftMargin(10);

        // Add page
        self::$pdf -> AddPage();

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 7.5);

        // Add Header
        self::add_header();

        // Add padding 
        self::$pdf -> Ln(2);

        // Calculate no. of transactions 
        $no_of_transactions = count(self::$details['transaction_records']);

        // Calculate no. of pages required.
        if($no_of_transactions > self::MAX_ROW_PER_PAGE[0]) {
            self::$no_of_pages += ceil(($no_of_transactions - self::MAX_ROW_PER_PAGE[0]) / self::MAX_ROW_PER_PAGE[1]);
        }

        // Add Page
        $current_page = 1;
        self::add_page_number($current_page);

        // Add padding 
        self::$pdf -> Ln(1);
        
        // Add Header 
        self::add_table_header();

        // Flag
        $is_list_not_exhausted = true;

        // Add records
        $record_index = 0;

        // Records in past page
        $records_in_last_page = 0;

        // Fill flag
        $fill_flag = 1;

        for($page_no = 1; $page_no <= self::$no_of_pages; ++$page_no) {

            // Page selector
            $selector = $page_no === 1 ? 0 : 1;

            // Reset    
            $records_in_last_page = 0;
            for($i = 0; $i < self::MAX_ROW_PER_PAGE[$selector]; ++$i) {
                if(isset(self::$details['transaction_records'][$record_index])) {
                    $fill_flag ^= 1;
                    self::add_transaction_records(
                        self::$details['transaction_records'][$record_index++], 
                        $fill_flag ? true : false,
                        $i + 1 == self::MAX_ROW_PER_PAGE[$selector],
                    );
                    ++$records_in_last_page;
                }
                else {
                    $is_list_not_exhausted = false;
                    break;
                }
            }

            if($is_list_not_exhausted) {

                // Add page 
                self::$pdf -> AddPage();

                // Add page number
                self::add_page_number(++$current_page);
                
                // Add padding 
                self::$pdf -> Ln(1);

                // Add Header 
                self::add_table_header();
            }
        }

        // Add padding
        $no_of_rows_to_add = (self::MAX_ROW_PER_PAGE[$current_page === 1 ? 0 : 1] - $records_in_last_page - 2);
        for($i = 0; $i < $no_of_rows_to_add; ++$i) {
            $fill_flag ^= 1;
            self::add_transaction_records([], $fill_flag ? true : false, false, true);
        }

        // Add Footer
        self::add_table_footer();

        // Save to disk
        if($generate_file) self::$pdf -> Output('F', name: TEMP_DIR. $filename. '.pdf');
        else self::$pdf -> Output();
    }
}

class __GenerateBalanceSheet {

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const COURIER = 'Courier';

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // Account
    private static $accounts = null;

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Header
    private static function header() : void {
        self::$pdf -> SetLeftMargin(15);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: 50, h: 4, txt: 'ASSET', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Cell(w: 100, h: 4, txt: 'Balance Sheet As of '. self::$details['date_txt']. ' for '. self::$details['store_name'] , border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
    }

    private const partition_1_margin_title = 80;
    private const partition_1_margin_content = 40;
    private const partition_1_margin_group = self::partition_1_margin_title + self::partition_1_margin_content + 5;

    private static function current_assets(): void {
        self::$pdf -> Ln(6);
        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1000]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);
        
        self::$pdf -> SetLeftMargin(22);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1020]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1020]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1030]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1030]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1050]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1050]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1055]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1055]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1060]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1060]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1067]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1067]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[10001]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[10001]), border: 'B', ln: 1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1075]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1075]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1080]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1080]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1083]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1083]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1087]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1087]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1089]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1089]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1090]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1090]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1100]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1100]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1200]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1200]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1205]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1205]), border:'B', ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1230]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1230]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1300]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1300]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1320]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1320]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Ln(2);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1400]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1400]), border:'TB', ln:0);
    }

    private static function inventory_assets(): void {
        self::$pdf -> Ln(8);
        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1500]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1520]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1520]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1530]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1530]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetLeftMargin(18);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1540]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1540]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);        

        self::$pdf -> Ln(2);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1590]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1590]), border: 'TB', ln:1);
    }

    private static function capital_assets(): void {
        self::$pdf -> Ln(4);
        self::$pdf -> SetLeftMargin(18);
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1700]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1810]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1810]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1820]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1820]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1825]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1825]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1830]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1830]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1840]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1840]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1845]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1845]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1850]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1850]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1860]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1860]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1865]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1865]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1870]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1870]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1880]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1880]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Ln(2);
        
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1890]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1890]), border: 'TB', ln:1);
    }

    private static function other_non_current_assets() : void {
        self::$pdf -> Ln(4);
        self::$pdf -> SetLeftMargin(18);
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1900]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1910]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1910]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1920]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1920]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1930]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1930]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> Ln(2);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[1950]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[1950]), border: 'TB', ln:1);
    }

    private static function asset() : void {
        // Current Assets
        self::current_assets();

        // Inventory Assets
        self::inventory_assets();

        // Capital Assets
        self::capital_assets();

        // Other Non Current Assets 
        self::other_non_current_assets();
        self::$pdf -> SetLeftMargin(15);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Ln(4);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: 'TOTAL ASSET', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts['total_asset']), border: 'TB', ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 1, txt: '', border: 'B', ln:1);

        // Page Break
        self::$pdf -> AddPage();
    }

    private static function current_liabilities() : void {
        self::$pdf -> Cell(w: self::partition_1_margin_group + 3, h: 4, txt: 'LIABILITY', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Ln(8);
        self::$pdf -> SetLeftMargin(18);
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2000]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2100]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2100]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2115]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2115]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2120]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2120]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2130]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2130]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2133]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2133]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2134]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2134]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2135]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2135]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2140]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2140]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2145]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2145]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2160]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2160]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2170]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2170]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2180]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2180]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2185]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2185]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2190]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2190]), border: 'B', ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2195]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2195]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2230]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2230]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2234]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2234]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2235]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2235]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2236]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2236]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2237]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2237]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2238]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2238]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2240]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2240]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2250]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2250]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2260]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2260]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2270]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2270]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2280]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2280]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2310]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2310]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2312]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2312]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2315]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2315]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2320]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2320]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2325]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2325]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2330]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2330]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2335]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2335]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2340]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2340] ?? 0), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[10002]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[10002]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2460]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2460]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Ln(2);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2500]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2500]), border: 'TB', ln:1);
    }

    private static function long_term_liabilities(): void {
        self::$pdf -> Ln(4);
        self::$pdf -> SetLeftMargin(18);
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2600]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2620]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2620]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2630]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2630]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2640]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2640]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Ln(2);
        self::$pdf -> SetLeftMargin(15);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[2700]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[2700]), border: 'TB', ln:1);
        self::$pdf -> Ln(4);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: 'TOTAL LIABILITY', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts['total_liability']), border: 'TB', ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 1, txt: '', border: 'B', ln:1);
    }
    
    private static function liability(): void {
        self::current_liabilities();
        self::long_term_liabilities();
    }

    private static function retained_earnings(): void {
        self::$pdf -> Ln(3);
        self::$pdf -> SetLeftMargin(15);
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: 'EQUITY', border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);
        
        // Set Font 
        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_title, h: 4, txt: 'Retained Earnings', border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(2);

        self::$pdf -> SetLeftMargin(22);

        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[3560]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[3560]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);

        self::$pdf -> SetLeftMargin(18);
        self::$pdf -> Cell(w: self::partition_1_margin_group, h: 4, txt: AccountsConfig::ACCOUNTS_DETAILS[3600]['name'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[3600]), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> Ln(2);
        self::$pdf -> SetLeftMargin(15);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 4, h: 4, txt: 'Total Retained Earnings', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts[3600]), border: 'TB', ln:1);
        self::$pdf -> Ln(3);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: 'TOTAL EQUITY', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts['total_equity']), border: 'B', ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 1, txt: '', border: 'B', ln:1);
        self::$pdf -> Ln(3);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: 'LIABILITIES AND EQUITY', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 4, txt: Utils::number_format(self::$accounts['liabilities_and_equity']), border: 'B', ln:1);
        self::$pdf -> Cell(w: self::partition_1_margin_group + 7, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> Cell(w: self::partition_1_margin_content, h: 1, txt: '', border: 'B', ln:1);
    }
    
    private static function equity(): void {
        self::retained_earnings();
    }

    /**
     * This method will generate balance sheet
     * @param details
     */
    public static function generate(array $details): void {
        self::$details = $details;

        // Accounts
        self::$accounts = $details['accounts'];

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        $document_name = strtolower((SYSTEM_INIT_MODE === PARTS ? 'parts' : 'wash'). '_balance_sheet_'. $details['store_name']. '_'. str_replace(', ', '_', $details['date_txt']));
        self::$pdf -> SetTitle($document_name);

        // Set Compression ON
        self::$pdf -> SetCompression(true);

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 10,);

        // Add page
        self::$pdf -> AddPage();

        // Header
        self::header();

        // Asset
        self::asset();

        // Liability
        self::liability();

        // Equity 
        self::equity();

        // Output to Browser
        self::$pdf -> Output('I', "$document_name.pdf");
    }
}

class __GenerateCustomerAgedSummary {

    // Layout Settings
    private const ORIENTATION = 'L';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const COURIER = 'Courier';

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Cell Height
    private const CELL_HEIGHT = 3.5;

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // Amount Widths
    private const AMOUNT_WIDTHS = 24;

    // Header
    private static function header(): void {
        // Store Name
        self::$pdf -> SetFont(self::COURIER, 'B', 16,);
        self::$pdf -> Cell(w: 0, h: 4, txt: StoreDetails::STORE_DETAILS[self::$details['store_id']]['address']['name']. '('. StoreDetails::STORE_DETAILS[self::$details['store_id']]['name'].')', border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Ln(1);
        // self::$pdf -> Cell(w: 35, h: 4, txt: 'Customer Aged Summary As at '. self::$details['date'], border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> Cell(w: 218, h: 4, txt: 'Customer Aged Summary As at '. self::$details['date'], border: self::SHOW_BORDER_FOR_DEBUG, ln:0);
        self::$pdf -> SetTextColor(255, 0, 0);
        self::$pdf -> Cell(w: 35, h: 4, txt: 'Amounts are in '. (IS_CURRENCY_USD ? 'USD': 'CAD'), border: self::SHOW_BORDER_FOR_DEBUG, ln:1);
        self::$pdf -> SetTextColor(0, 0, 0);
    }

    // Table Header
    private static function table_header(): void {
        self::$pdf -> Ln(6);
        self::$pdf -> SetFont(self::COURIER, 'B', 8,);
        self::$pdf -> Cell(w: 130, h: self::CELL_HEIGHT, txt: 'Customer Name', border: 'B', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: 'Total', border: 'B', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: 'Current', border: 'B', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: '31 to 60', border: 'B', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: '61 to 90', border: 'B', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: '91+', border: 'B', ln:1);
        self::$pdf -> Ln(2);
    }

    // Row Content
    private static function row_content(array $data, bool $do_fill=false): void {
        self::$pdf -> SetFont(self::COURIER, '', 8,);
        $client_name = $data['client_name'];
        if(isset($data['phone_number'][1])) $client_name .= ' | '. $data['phone_number'];

        // Do Fill
        if($do_fill) self::$pdf -> SetFillColor(224, 224, 224);
        else self::$pdf -> SetFillColor(255, 255, 255);

        // Change Font
        if($data['total'] < 0) self::$pdf -> SetFont(self::COURIER, 'I', 8,);

        self::$pdf -> Cell(w: 130, h: self::CELL_HEIGHT, txt: $client_name, border: '', ln:0, fill: $do_fill);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($data['total']), border: '', ln:0, fill: $do_fill);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: $data['current'] != 0 ? Utils::number_format($data['current']) : '-', border: '', ln:0, fill: $do_fill);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: $data['31-60'] != 0 ? Utils::number_format($data['31-60']) : '-', border: '', ln:0, fill: $do_fill);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: $data['61-90'] != 0 ? Utils::number_format($data['61-90']): '-', border: '', ln:0, fill: $do_fill);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: $data['91+'] != 0 ? Utils::number_format($data['91+']) : '-', border: '', ln:1, fill: $do_fill);
    }

    private static function footer(float $total_receivables, float $current, float $_31_60, float $_61_90, float $_91): void {
        self::$pdf -> Ln(2);
        self::$pdf -> SetFont(self::COURIER, 'B', 8,);
        self::$pdf -> Cell(w: 130, h: 5, txt: 'TOTAL RECEIVABLES', border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);

        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($total_receivables), border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);

        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($current), border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);

        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($_31_60), border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);

        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($_61_90), border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);

        self::$pdf -> Cell(w: self::AMOUNT_WIDTHS, h: self::CELL_HEIGHT, txt: Utils::number_format($_91), border: 'T', ln:0);
        self::$pdf -> Cell(w: 2, h: self::CELL_HEIGHT, txt: '', border: '', ln:0);
    }

    // Prepare Rows
    private static function prepare_rows() : array {
        // Prepare Rows
        $summary = self::$details['summary'];
        $no_of_rows = count($summary);
        $index = 0;
        $total_receivables = 0;
        $current = 0 ;
        $_31_60 = 0;
        $_61_90 = 0;
        $_91 = 0;
        for($i = 0; $i < $no_of_rows; ++$i) {
            self::row_content($summary[$i], $index & 1 ? true : false);
            $index ^= 1;
            $total_receivables += $summary[$i]['total'];
            $current += $summary[$i]['current'];
            $_31_60 += $summary[$i]['31-60'];
            $_61_90 += $summary[$i]['61-90'];
            $_91 += $summary[$i]['91+'];
        }

        return [
            'total_receivables' => $total_receivables,
            'current' => $current,
            '31-60' => $_31_60,
            '61-90' => $_61_90,
            '+91' => $_91,
        ];
    }
    /**
     * This method will generate customer aged summary. 
     * @param details
     */
    public static function generate(array $details): void {
        self::$details = $details;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        $document_name = strtolower('customer_aged_summary_'. $details['store_name']. '_'. str_replace(', ', '_', $details['date']));
        self::$pdf -> SetTitle($document_name);

        // Set Compression ON
        self::$pdf -> SetCompression(true);

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 10,);

        // Add page
        self::$pdf -> AddPage();

        // Header
        self::header();

        // Table Header
        self::table_header();

        // Prepare Rows
        $totals = self::prepare_rows();

        // Set Footer
        self::footer(
            $totals['total_receivables'], 
            $totals['current'], 
            $totals['31-60'], 
            $totals['61-90'], 
            $totals['+91'], 
        );

        // Output to Browser
        self::$pdf -> Output('I', "$document_name.pdf");
    }
}

/**
 * This class will generate income statement.
 */
class __GenerateIncomeStatement {

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const COURIER = 'Courier';

    // PDF Instance
    private static $pdf = null;

    // Statement
    private static $statement = null;

    // Revenue and Expense
    private static $total_revenue = 0;
    private static $total_expense = 0;

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    /** Revenue */
    private static function revenue(): void {
        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Cell(w: 70, h: 4, txt: 'REVENUE', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Ln(5);
        self::$pdf -> Cell(w: 5, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 9,);
        self::$pdf -> Cell(w: 70, h: 4, txt: 'Sales Revenue', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::COURIER, '', 9,);

        // Sales Inventory
        self::$pdf -> Ln(2);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: 'Sales Inventory', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4020]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Sales Return 
        self::$pdf -> Ln(2);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4220], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: (self::$statement[4220] != 0 ? '-' : ''). Utils::number_format(self::$statement[4220]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        // Early Payment Sales Return
        self::$pdf -> Ln(2);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4240], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: (self::$statement[4240] != 0 ? '-' : ''). Utils::number_format(self::$statement[4240]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        if(SYSTEM_INIT_MODE === WASH) {

            // Part Sales
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4150], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4150]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Merchandise Sales
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4170], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4170]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Labour Revenue
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4175], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4175]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Sales
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4200], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4200]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Full Service
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4205], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4205]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Self Wash
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4210], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4210]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Oil & Grease
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4215], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4215]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

            // Miscellaneous Revenue 
            self::$pdf -> Ln(2);
            self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[4460], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
            self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[4460]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        }

        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Ln(5);
        self::$pdf -> Cell(w: 90, h: 4, txt: 'TOTAL REVENUE', border: 'TB', ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 9,);
        self::$pdf -> Cell(w: 0, h: 4, txt: Utils::number_format(self::$total_revenue), border: 'TB', ln: 1);
    }

    /** Expense */
    private static function expense(): void {
        self::$pdf -> Ln(5);
        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Cell(w: 70, h: 4, txt: 'EXPENSE', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> Ln(5);
        self::$pdf -> Cell(w: 5, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 9,);
        self::$pdf -> Cell(w: 70, h: 4, txt: 'Inventory', border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);
        self::$pdf -> SetFont(self::COURIER, '', 9,);

        // Sales Revenue
        self::$pdf -> Ln(2);
        self::$pdf -> Cell(w: 10, h: 4, txt: '', border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: AccountsConfig::ACCOUNT_NAMES[5000], border: self::SHOW_BORDER_FOR_DEBUG, ln: 0);
        self::$pdf -> Cell(w: 80, h: 4, txt: Utils::number_format(self::$statement[1520]), border: self::SHOW_BORDER_FOR_DEBUG, ln: 1);

        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Ln(5);
        self::$pdf -> Cell(w: 90, h: 4, txt: 'TOTAL EXPENSE', border: 'TB', ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 9,);
        self::$pdf -> Cell(w: 0, h: 4, txt: Utils::number_format(self::$total_expense), border: 'TB', ln: 1);
    }
    
    /** Net Income */
    private static function net_income(): void {
        self::$pdf -> Ln(5);
        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        self::$pdf -> Cell(w: 90, h: 4, txt: 'NET INCOME', border: 'TB', ln: 0);
        self::$pdf -> SetFont(self::COURIER, 'B', 9,);
        self::$pdf -> Cell(w: 0, h: 4, txt: Utils::number_format(self::$total_revenue + self::$total_expense), border: 'TB', ln: 1);
    }

    /** Header */
    private static function header(array $details): void {
        self::$pdf -> SetFont(self::COURIER, 'B', 11,);
        $text = 'Income Statement for '. $details['store_name']. ' from '. Utils::convert_date_to_human_readable($details['start_date']). ' till '. Utils::convert_date_to_human_readable($details['end_date']);
        self::$pdf -> Cell(w: 90, h: 4, txt: $text, border: '', ln: 1);
        self::$pdf -> Ln(1);
        self::$pdf -> Cell(w: 0, h: 4, txt: '', border: 'B', ln: 1);
        self::$pdf -> Ln(5);
    }

    /**
     * This method will generate income statement.
     * @param details
     */
    public static function generate(array $details): void {

        // Set Accounts
        self::$statement = $details['statement'];

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        $document_name = strtolower('income_statement_'. $details['store_name']. '_from_'. str_replace('-', '_', $details['start_date']).'_till_'. str_replace('-', '_', $details['end_date']));
        self::$pdf -> SetTitle($document_name);

        // Set Compression ON
        self::$pdf -> SetCompression(true);

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 10,);

        // Add page
        self::$pdf -> AddPage();

        self::$total_revenue = self::$statement[4020] - self::$statement[4220] - self::$statement[4240];
        self::$total_expense = self::$statement[1520];

        if(SYSTEM_INIT_MODE === WASH) {

            /* Part Sales */
            self::$total_revenue += self::$statement[4150];
            
            /* Merchandise Sales */
            self::$total_revenue += self::$statement[4170];

            /* Labour Revenue */
            self::$total_revenue += self::$statement[4175];

            /* Sales */
            self::$total_revenue += self::$statement[4200];

            /* Full Service */
            self::$total_revenue += self::$statement[4205];

            /* Self Wash */
            self::$total_revenue += self::$statement[4210];

            /* Oil & Grease */
            self::$total_revenue += self::$statement[4215];

            /* Miscellaneous Revenue */
            self::$total_revenue += self::$statement[4460];
        }

        // Header
        self::header($details);

        // Revenue
        self::revenue();

        // Expense
        self::expense();

        // Net Income 
        self::net_income();

        // Output to Browser
        self::$pdf -> Output('I', "$document_name.pdf");
    }
}

class __GenerateInventory {

    // Layout Settings
    private const ORIENTATION = 'P';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const COURIER = 'Courier';

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Header
    private static function header(): void {
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 14,);
        self::$pdf -> Cell(w: 50, h: 5, txt: 'IDENTIFIER', border: 1, ln: 0);
        self::$pdf -> Cell(w: 80, h: 5, txt: 'DESCRIPTION', border: 1, ln: 0);
        self::$pdf -> Cell(w: 30, h: 5, txt: 'QUANTITY', border: 1, ln: 0);
        self::$pdf -> Cell(w: 0, h: 5, txt: 'VALUE', border: 1, ln: 1);
    }

    // List
    private static function list(): void {
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        self::$pdf -> Cell(w: 50, h: 4, txt: 'IDENTIFIER', border: 1, ln: 0);
    }

    /**
     * This method will generate item sold quantity report for year and store.
     * @param item_details
     */
    public static function generate_item_sold_quantity(array &$item_details, int $store_id, int $year): void {

        $item_code = '';
        foreach($item_details as $item) {
            $identifier = $item['identifier'];
            $description = $item['description'];
            $quantity = $item['quantity'];
            $item_code .= <<<EOS
            <tr>
                <td>$identifier</td>
                <td><i>$description</i></td>
                <td>$quantity</td>
            </tr>
            EOS;
        }
        $store_details = StoreDetails::STORE_DETAILS[$store_id]['name']. ' FOR THE YEAR: '. $year;

        echo <<<EOS
        <html>
        <head>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
        <style>
        body {
            font-family: "Roboto Mono", monospace;
            font-optical-sizing: auto;
            font-weight: light;
            font-style: normal;
        }
        th {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            text-align: left;
            padding: 5px;
        }

        td {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            padding: 5px;
        }
        </style>
        </head>
        <body>
        <div style="margin-bottom: 0.8%;">
            <h2 style="text-transform: uppercase;">ITEM SOLD FOR $store_details</h2>
        </div>
        <table style="border-collapse:collapse">
            <thead>
                <tr style="border-bottom: 2px dashed black;">
                    <th>Identifier</th>
                    <th>Description</th>
                    <th>Qty Sold</th>
                </tr>
            </thead>
        $item_code
        </table>
        </body>
        </html>
        EOS;
    }
    
    /**
     * This method will generate inventory list in Plain HTML.
     * @param item_details
     * @param store_id
     */
    public static function generate_inventory_list(array &$item_details, int $store_id): void {
        $item_code = '';
        $total_inventory_value = 0;
        foreach($item_details as $item) {

            $identifier = $item['identifier'];
            $description = $item['description'];
            $quantity = $item['quantity'];
            $value = Utils::number_format($item['value']);
            if($value <= 0) continue;
            $buying_cost = Utils::number_format($item['buying_cost']);
            $item_code .= <<<EOS
            <tr>
                <td>$identifier</td>
                <td><i>$description</i></td>
                <td>$quantity</td>
                <td>$buying_cost</td>
                <td>$value</td>
            </tr>
            EOS;

            $total_inventory_value += $item['value'];
        }

        $total_inventory_value = Utils::number_format($total_inventory_value);
        $store_details = StoreDetails::STORE_DETAILS[$store_id]['name']. ' AS OF '. Utils::convert_date_to_human_readable(
            Utils::get_business_date($store_id)
        );
        echo <<<EOS
        <html>
        <head>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
        <style>
        body {
            font-family: "Roboto Mono", monospace;
            font-optical-sizing: auto;
            font-weight: light;
            font-style: normal;
        }
        th {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            text-align: left;
            padding: 5px;
        }

        td {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            padding: 5px;
        }
        </style>
        </head>
        <body>
        <div style="margin-bottom: 0.8%;">
            <h2 style="text-transform: uppercase;">INVENTORY FOR $store_details</h2>
        </div>
        <table style="border-collapse:collapse">
            <thead>
                <tr style="border-bottom: 2px dashed black;">
                    <th>Identifier</th>
                    <th>Description</th>
                    <th>Qty.</th>
                    <th>$ Per Item</th>
                    <th>Value</th>
                </tr>
            </thead>
        $item_code
        </table>
        <div style="margin-top:5%">
            <span style="font-size: 1em;">TOTAL INVENTORY VALUE: $ $total_inventory_value</span>
        </div>
        </body>
        </html>
        EOS;
    }

    /**
     * This method will generate dead inventory list.
     * @param inventory_details
     * @param item_details
     * @param month
     * @param year
     * @param store_id
     * @param show_last_dates_for_all_stores
     */
    public static function generate_dead_inventory_list(
        array &$inventory_details, 
        int $store_id, 
        int $month, 
        int $year, 
        int $show_last_dates_for_all_stores = 0,
        ): void {
        $item_details = $inventory_details['dead_stock'];
        $total_dead_inventory_value = Utils::number_format($inventory_details['value'], 2);
        if($year > 0) $date_text = "IN $year";
        else $date_text = "$month MONTHS AGO";
        $store_details = 
        StoreDetails::STORE_DETAILS[$store_id]['name']. 
        " ~ <u style='color:#800000;'>LAST SOLD $date_text</u>".
        ' ~ AS ON '. 
        Utils::format_to_human_readable_date(Utils::get_business_date($store_id));

        $item_code = '';
        foreach($item_details as $item) {

            $identifier = $item['identifier'];
            $description = $item['description'];
            $quantity = $item['quantity'];
            $value = Utils::number_format($item['value']);
            $never_sold = $item['never_sold'];
            if($value <= 0 && $never_sold == 0) continue;
            $buying_cost = Utils::number_format($item['buying_cost']);
            $last_sold = $never_sold == 0 ? Utils::format_to_human_readable_date($item['last_sold']) : $item['last_sold'];
            $last_sold_all_stores = $item['last_sold_all_stores'];

            $last_sold_all_stores_codes = '';
            if($show_last_dates_for_all_stores && count($last_sold_all_stores) > 1) {
                if(isset($last_sold_all_stores[$store_id])) unset($last_sold_all_stores[$store_id]);
                $last_sold_all_stores_codes = '<tr><td colspan="6"><ul>';
                foreach($last_sold_all_stores as $key => $last_sold_current_store) {
                    $store_name = StoreDetails::STORE_DETAILS[$key]['name'];
                    $last_sold_current_store = Utils::format_to_human_readable_date($last_sold_current_store);
                    $last_sold_all_stores_codes .= "<li>$store_name ~ $last_sold_current_store</li>";
                }
                $last_sold_all_stores_codes .= '</ul></td></tr>';
            }
            $item_code .= <<<EOS
            <tr>
                <td>$identifier</td>
                <td><i>$description</i></td>
                <td>$quantity</td>
                <td>$buying_cost</td>
                <td>$value</td>
                <td>$last_sold</td>
            </tr>
            $last_sold_all_stores_codes
            EOS;
        }

        // Total Items
        $total_items = $inventory_details['total_items'];
        $items_never_sold = $inventory_details['items_never_sold'];
        $items_sold = $total_items - $items_never_sold;

        echo <<<EOS
        <html>
        <head>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
        <style>
        body {
            font-family: "Roboto Mono", monospace;
            font-optical-sizing: auto;
            font-weight: light;
            font-style: normal;
        }
        th {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            text-align: left;
            padding: 5px;
        }

        td {
            letter-spacing: 0.1em;
            font-size: 1em;
            text-transform: uppercase;
            font-weight: normal;
            padding: 5px;
        }
        </style>
        </head>
        <body>
        <div style="margin-bottom: 0.8%;">
            <h2 style="text-transform: uppercase;">DEAD INVENTORY FOR $store_details</h2>
        </div>
        <table style="border-collapse:collapse">
            <thead>
                <tr style="border-bottom: 2px dashed black;">
                    <th>Identifier</th>
                    <th>Description</th>
                    <th>Qty.</th>
                    <th>$ Per Item</th>
                    <th>Value</th>
                    <th>Last Sold</th>
                </tr>
            </thead>
        $item_code
        </table>
        <div style="margin-top:5%">
            <span style="font-size: 1em;">TOTAL INVENTORY VALUE: $ $total_dead_inventory_value</span><br>
            <span style="font-size: 1em;">TOTAL ITEMS: $total_items</span><br>
            <span style="font-size: 1em;">TOTEL ITEMS EVER SOLD: $items_sold</span><br>
            <span style="font-size: 1em;color: red;">TOTAL ITEMS NEVER SOLD: $items_never_sold</span>
        </div>
        </body>
        </html>
        EOS;
    }

    /**
     * This method will generate inventory list.
     * @param details
     * @param store_id
     */
    public static function generate_inventory_list_pdf(array $details, int $store_id): void {

        // Details
        self::$details = $details;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        $document_name = 'Inventory List for '. StoreDetails::STORE_DETAILS[$store_id]['name'];
        self::$pdf -> SetTitle($document_name);

        // Set Compression ON
        self::$pdf -> SetCompression(true);

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 10,);

        // Add page
        self::$pdf -> AddPage();

        // Print Header
        self::header();

        self::list();

        self::$pdf -> Output('I', "$document_name.pdf");
    }

    /**
     * This method will display low stock items.
     * @param details
     */
    public static function low_stock(array $details): void {
        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        $document_name = 'Low Stock';
        self::$pdf -> SetTitle($document_name);

        // Set Compression ON
        self::$pdf -> SetCompression(true);

        // Set Font 
        self::$pdf -> SetFont(self::COURIER, '', 10,);

        // Add page
        self::$pdf -> AddPage();

        $identifier_width = 150;
        $restock_width = 0;

        // Print Header
        self::$pdf -> SetFont(self::COURIER, '', 16,);
        self::$pdf -> Cell(w: 80, h: 5, txt: '', border: 0, ln: 0);
        self::$pdf -> Cell(w: 0, h: 5, txt: 'LOW STOCK', border: 0, ln: 1);
        self::$pdf -> Ln(5);
        self::$pdf -> SetFont(self::COURIER, '', 14,);
        self::$pdf -> Cell(w: $identifier_width, h: 5, txt: 'IDENTIFIER', border: 1, ln: 0);
        self::$pdf -> Cell(w: 0, h: 5, txt: 'RESTOCK BY', border: 1, ln: 1);
        self::$pdf -> Ln(2);

        // Fill Colors
        $fill_colors = [
            [255, 255, 255],  // White
            [224, 224, 224],  // Light Gray
        ];

        $index = 0;
        
        // Print List
        self::$pdf -> SetFont(self::COURIER, '', 10,);
        foreach($details as $detail) {
            self::$pdf -> SetFillColor(...$fill_colors[$index]);
            self::$pdf -> Cell(w: $identifier_width, h: 5, txt: $detail['identifier'], border: 0, ln: 0, fill: true);
            self::$pdf -> Cell(w: 0, h: 5, txt: $detail['deficit'], border: 0, ln: 1, fill: true);
            $index ^= 1;
        }

        self::$pdf -> Output('I', "$document_name.pdf");
    }
}

class __GenerateLastPurchaseDateReport {
    // Layout Settings
    private const ORIENTATION = 'L';
    private const UNIT = 'mm';
    private const PAPER_SIZE = 'Letter';

    // Font Settings
    private const COURIER = 'Courier';

    // PDF Instance
    private static $pdf = null;

    // Details
    private static $details = null;

    // For Debugging.
    private const SHOW_BORDER_FOR_DEBUG = 0;

    // Header
    private static function header(): void {
        // Set Font 
        self::$pdf -> SetFont(self::COURIER, 'B', 12,);
        self::$pdf -> Cell(w: 0, h: 5, txt: 'Last Purchase Date till Date: '. self::$details['till_date'], border: 0, ln: 1);
        self::$pdf -> Ln(2);
        self::$pdf -> SetFont(self::COURIER, 'B', 10,);
        self::$pdf -> Cell(w: 60, h: 5, txt: 'Client Name', border: 0, ln: 0);
        self::$pdf -> Cell(w: 70, h: 5, txt: 'Contact Name', border: 0, ln: 0);
        self::$pdf -> Cell(w: 40, h: 5, txt: 'Phone Number', border: 0, ln: 0);
        self::$pdf -> Cell(w: 40, h: 5, txt: 'Category', border: 0, ln: 0);
        self::$pdf -> Cell(w: 0, h: 5, txt: 'Last Purchase Date', border: 0, ln: 1);
    }
    
    // List
    private static function list(): void {
        self::$pdf -> SetFont(self::COURIER, '', 8,);
        $index = 0;
        $clients = self::$details['clients'];
        foreach ($clients as $client) {

            if($index) self::$pdf -> SetFillColor(224, 224, 224);
            else self::$pdf -> SetFillColor(255, 255, 255);
            self::$pdf -> Cell(w: 60, h: 5, txt: $client['name'], border: 0, ln: 0, fill: true);
            self::$pdf -> Cell(w: 70, h: 5, txt: $client['contact_name'], border: 0, ln: 0, fill: true);
            self::$pdf -> Cell(w: 40, h: 5, txt: $client['phone_number_1'], border: 0, ln: 0, fill: true);
            self::$pdf -> Cell(w: 40, h: 5, txt: $client['category'], border: 0, ln:0, fill: true);
            self::$pdf -> Cell(w: 0, h: 5, txt: $client['last_purchase_date'], border: 0, ln: 1, fill: true);

            // Toggle Flag
            $index ^= 1;
        }
    }

    /**
     * This method will generate client list.
     * @param details
     */
    public static function generate(array $details): void {

        // Details
        self::$details = $details;

        // Handle 
        self::$pdf = new FPDF(self::ORIENTATION, self::UNIT, self::PAPER_SIZE);

        // Set Title
        self::$pdf -> SetTitle('Customer Last Purchase Date');

        // Set Compression ON
        self::$pdf -> SetCompression(true);        

        // Add page
        self::$pdf -> AddPage();

        // Print Header
        self::header();

        // List
        self::list();

        // Send to Browser
        self::$pdf -> Output();
    }
}

/**
 * Unified Class for Generating Transactions and Receipt.
 */
class GeneratePDF {

    /**
     * This method will generate balance sheet.
     * @param details
     */
    public static function balance_sheet(array $details): void {
        __GenerateBalanceSheet::generate($details);
    }

    /**
     * Wrapper method for generating transaction.
     * @param details
     * @param filename The filename.
     * @param generate_file
     * @return void 
     */
    public static function transaction(array $details, string $filename, bool $generate_file=false): void {
        __GeneratePDF_SI_SR_CN_DN_QT::generate($details, TEMP_DIR. $filename, $generate_file);
    }

    /**
     * Wrapper method for generating packaging slip.
     * @param details
     * @return void 
     */
    public static function packaging_slip(array $details): void {
        __GeneratePDF_PackagingSlip::generate($details);
    }

    /**
     * Wrapper method for generating receipts.
     * @param details
     * @param filename The filename
     * @return void
     */
    public static function receipt(array $details, string $filename, bool $generate_file=false): void {
        __GeneratePDF_Receipt::generate($details, TEMP_DIR. $filename, $generate_file);
    }

    /**
     * This method will generate customer statement.
     * @param details
     * @param filename
     * @param generate_record
     * @param generate_file
     * @return void 
     */
    public static function customer_statement(array $details, string $filename, bool $generate_record, bool $generate_file=false) : void {
        __GenerateCustomerStatement::generate($details, $filename, $generate_record, $generate_file);
    }

    /**
     * This method will generate customer aged summary. 
     * @param details
     */
    public static function customer_aged_summary(array $details): void {
        __GenerateCustomerAgedSummary::generate($details);
    }

    /**
     * This method will generate income statement.
     * @param details
     */
    public static function income_statement(array $details): void {
        __GenerateIncomeStatement::generate($details);
    }

    /**
     * This method will generate inventory list.
     * @param details
     * @param store_id
     */
    public static function generate_inventory_list(array &$details, int $store_id): void {
        __GenerateInventory::generate_inventory_list($details, $store_id);
    }

    /**
     * This method will generate dead inventory list.
     * @param inventory_details
     * @param store_id
     * @param month
     * @param year
     * @param include_last_sold_for_all_stores
     * @param min_cost_of_each_item
     * @param max_cost_of_each_item
     * @param min_qty_dead_stock
     * @param max_qty_dead_stock
     */
    public static function generate_dead_inventory_list(
        array &$inventory_details, 
        int $store_id, 
        int $month, 
        int $year, 
        int $include_last_sold_for_all_stores,
    ): void {
        __GenerateInventory::generate_dead_inventory_list(
            $inventory_details, 
            $store_id, 
            $month, 
            $year, 
            $include_last_sold_for_all_stores,
        );
    }

    /**
     * This method will generate item sold quantity report for year and store.
     * @param item_details
     */
    public static function generate_item_sold_quantity(array &$item_details, int $store_id, int $year): void {
        __GenerateInventory::generate_item_sold_quantity($item_details, $store_id, $year);
    }

    /**
     * This method will filter items by price and quantity.
     * @param store_id
     * @param min_cost
     * @param max_cost
     * @param min_qty
     * @param max_qty
     * @return void
     */
    public static function filter_items_by_price_and_quantity(int $store_id, float $min_cost = 0, float $max_cost = 0, int $min_qty = 0, int $max_qty = 0): void {
        $response = Inventory::filter_items_by_price_and_quantity($store_id, $min_cost, $max_cost, $min_qty, $max_qty);

        // Error Message
        if(count($response['data']['records']) === 0) die('No Items were found for the matching criteria.');

        echo <<<'EOS'
        <html>
        <head>
        </head>
        <body>
        <table>
        <thead>
        <tr>
            <th>Identifier</th>
            <th>Description</th>
            <th>Buying Cost</th>
            <th>Quantity</th>
            <th>Last Sold</th>
            <th>Total Value</th>
        </tr>
        <tbody>
        EOS;

        $items = $response['data']['records'];
        $total_inventory_value = Utils::number_format($response['data']['total_inventory_value']);

        foreach($items as $item) {
            $identifier = $item['identifier'];
            $description = $item['description'];
            $price = Utils::number_format($item['buying_cost']);
            $quantity = $item['quantity'];
            $last_sold = $item['last_sold'];
            $total_value = Utils::number_format($item['total_value']);

            echo <<<EOS
            <tr>
                <td>$identifier</td>
                <td>$description</td>
                <td>$price</td>
                <td>$quantity</td>
                <td>$last_sold</td>
                <td>$total_value</td>
            </tr>
            EOS;
        }
        echo <<<EOS
        </tbody>
        </table>

        <br><br>
        Total Inventory Value: $$total_inventory_value
        </body>
        </html>
        EOS;
    }

    /**
     * This method will generate low stock items.
     * @param details
     */
    public static function low_stock(array $details): void {
        __GenerateInventory::low_stock($details);
    }

    /**
     * This method will generate last purchase date report.
     * @param details
     */
    public static function last_purchase_date(array $details): void {
        __GenerateLastPurchaseDateReport::generate($details);
    }
}
?>