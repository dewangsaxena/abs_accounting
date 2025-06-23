<?php 
require_once "{$_SERVER['DOCUMENT_ROOT']}/src/api/config/configurations.php";

/**
 * This file defines few public accounts ID.
 */
class AccountsConfig {
    const VISA_RECEIVABLE = 1080;
    const MASTERCARD_RECEIVABLE = 1083;
    const AMEX_RECEIVABLE = 1087;
    const TOTAL_CREDIT_CARD_RECEIVABLES = 1090;
    const TOTAL_RECEIVABLES = 1230;
    const CHEQUE_RECEIVABLES = 10001;
    const TOTAL_CASH = 1075;
    const ACCOUNTS_RECEIVABLE = 1200;
    const CASH_TO_BE_DEPOSITED = 1020;
    const SALES_RETURN = 4220;
    const EARLY_PAYMENT_PURCHASE_DISCOUNT = 5240;
    const EARLY_PAYMENT_SALES_DISCOUNT = 4240;
    const TOTAL_DISCOUNT = 10000;
    const PURCHASES = 5200;
    const PURCHASE_RETURNS = 5220;
    const CHEQUING_BANK_ACCOUNT = 1060;
    const ACCOUNTS_PAYABLE = 2100;
    /* WHEN CHANGING ANY REVENUE ACCOUNT UPDATE WHEREVER THIS IS USED */ 
    const SALES_INVENTORY_A = 4020;
    const INVENTORY_A = 1520;

    /* Taxes */
    const GST_HST_CHARGED_ON_SALE = 2310;
    const GST_HST_PAID_ON_PURCHASES = 2315;
    const PST_CHARGED_ON_SALE = 10002;

    /* Tax Refund */
    const GST_HST_REFUND = 2335;
    const PST_REFUND = 2340;

    /** 
     * Accounts with their Names.
     */
    public const ACCOUNT_NAMES = [
        1000 => 'Current Assets',
        1020 => 'Cash Accepted', /*'Cash to be deposited',*/
        1030 => 'Cash Draws',
        1050 => 'Petty Cash',
        1055 => 'Savings Bank Account',
        1060 => 'Checking Bank Account',/*'Chequing Bank Account',*/
        1067 => 'Foreign Currency Bank',
        1075 => 'Total Cash',
        1080 => 'Visa',
        1083 => 'MasterCard',
        1087 => 'American Express',
        1089 => 'Other Credit Card',
        1090 => 'Total Credit Card',
        1100 => 'Investments',
        1200 => 'Accounts Receivable',
        1205 => 'Allowance for Doubtful Accounts',
        1220 => 'Advances & Loans',
        1230 => 'Total Receivable',
        1300 => 'Purchase Prepayments',
        1320 => 'Prepaid Expenses',
        1400 => 'Total Current Assets',
        1500 => 'Inventory Assets',
        1520 => 'Inventory A',
        1530 => 'Inventory B',
        1540 => 'Inventory C',
        1590 => 'Total Inventory Assets',
        1700 => 'Capital Assets',
        1810 => 'Leasehold Improvements',
        1820 => 'Office Furniture & Equipment',
        1825 => 'Accum. Amort. -Furn. & Equip.',
        1830 => 'Net - Furniture & Equipment',
        1840 => 'Vehicle',
        1845 => 'Accum. Amort. -Vehicle',
        1850 => 'Net - Vehicle',
        1860 => 'Building',
        1865 => 'Accum. Amort. -Building',
        1870 => 'Net - Building',
        1880 => 'Land',
        1890 => 'Total Capital Assets',
        1900 => 'Other Non-Current Assets',
        1910 => 'Computer Software',
        1920 => 'Goodwill',
        1930 => 'Incorporation Cost',
        1950 => 'Total Other Non-Current Assets',
        2000 => 'Current Liabilities',
        2100 => 'Accounts Payable',
        2115 => 'Import Duty Clearing',
        2120 => 'Bank Loan - Current Portion',
        2130 => 'Bank Advances',
        2133 => 'Visa Payable',
        2134 => 'MasterCard Payable',
        2135 => 'American Express Payable',
        2140 => 'Other Credit Card Payable',
        2145 => 'Total Credit Card Payables',
        2160 => 'Corporate Taxes Payable',
        2170 => 'Vacation payable',
        2180 => 'EI Payable',
        2185 => 'CPP Payable',
        2190 => 'Federal Income Tax Payable',
        2195 => 'Total Receiver General',
        2230 => 'WCB Payable',
        2234 => 'User-Defined Expense 1 Payable',
        2235 => 'User-Defined Expense 2 Payable',
        2236 => 'User-Defined Expense 3 Payable',
        2237 => 'User-Defined Expense 4 Payable',
        2238 => 'User-Defined Expense 5 Payable',
        2240 => 'Deduction 1 Payable',
        2250 => 'Deduction 2 Payable',
        2260 => 'Deduction 3 Payable',
        2270 => 'Deduction 4 Payable',
        2280 => 'Deduction 5 Payable',
        2310 => 'GST/HST Charged on Sales',
        2312 => 'GST/HST Charged on Sales - Rate 2',
        2315 => 'GST/HST Paid on Purchases',
        2320 => 'GST/HST Payroll Deductions',
        2325 => 'GST/HST Adjustments',
        2330 => 'ITC Adjustments',
        2335 => 'GST/HST Owing (Refund)',
        2340 => 'PST Owing (Refund)',
        2460 => 'Prepaid Sales/Deposits',
        2500 => 'Total Current Liabilities',
        2600 => 'Long Term Liabilities',
        2620 => 'Bank Loans',
        2630 => 'Mortgage Payable',
        2640 => 'Loans from Owners',
        2700 => 'Total Long Term Liabilities',
        3000 => 'Owners Equity',
        3010 => 'Owners Contribution',
        3015 => 'Owners Withdrawals',
        3560 => 'Retained Earnings - Previous Year',
        3600 => 'Current Earnings',
        3700 => 'Total Owners Equity',
        4000 => 'Sales Revenue',
        4020 => 'Sales Inventory A',
        4030 => 'Sales Inventory B',
        4040 => 'Sales Inventory C',
        4200 => 'Sales',
        4220 => 'Sales Returns',
        4240 => 'Early Payment Sales Discounts',
        4260 => 'Net Sales',
        4400 => 'Other Revenue',
        4420 => 'Freight Revenue',
        4440 => 'Interest Revenue',
        4460 => 'Miscellaneous Revenue',
        4500 => 'Total Other Revenue',
        5000 => 'Cost of Goods Sold',
        5020 => 'Inventory A Cost',
        5030 => 'Inventory B Cost',
        5040 => 'Inventory C Cost',
        5100 => 'Inventory Variance',
        5120 => 'Item Assembly Costs',
        5130 => 'Adjustment Write-off',
        5140 => 'Transfer Costs',
        5190 => 'Subcontracts',
        5200 => 'Purchases',
        5220 => 'Purchase Returns',
        5240 => 'Early Payment Purchase Discounts',
        5290 => 'Net Purchases',
        5300 => 'Freight Expense',
        5350 => 'Total Cost of Goods Sold',
        5400 => 'Payroll Expenses',
        5410 => 'Wages & Salaries',
        5420 => 'EI Expense',
        5430 => 'CPP Expense',
        5440 => 'WCB Expense',
        5464 => 'User-Defined Expense 1 Expense',
        5465 => 'User-Defined Expense 2 Expense',
        5466 => 'User-Defined Expense 3 Expense',
        5467 => 'User-Defined Expense 4 Expense',
        5468 => 'User-Defined Expense 5 Expense',
        5470 => 'Employee Benefits',
        5490 => 'Total Payroll Expense',
        5600 => 'General & Administrative Expenses',
        5610 => 'Accounting & Legal',
        5615 => 'Advertising & Promotions',
        5620 => 'Bad Debts',
        5625 => 'Business Fees & Licenses',
        5630 => 'Cash Short/Over',
        5640 => 'Courier & Postage',
        5645 => 'Credit Card Charges',
        5650 => 'Currency Exchange & Rounding',
        5660 => 'Amortization Expense',
        5680 => 'Income Taxes',
        5685 => 'Insurance',
        5690 => 'Interest & Bank Charges',
        5700 => 'Office Supplies',
        5720 => 'Property Taxes',
        5730 => 'Motor Vehicle Expenses',
        5740 => 'Miscellaneous Expenses',
        5750 => 'Realized Exchange Gain/Loss',
        5760 => 'Rent',
        5765 => 'Repair & Maintenance',
        5780 => 'Telephone',
        5784 => 'Travel & Entertainment',
        5789 => 'Travel & Ent:Non-Reimbursable',
        5790 => 'Utilities',
        5890 => 'Visa Commissions',
        5892 => 'MasterCard Commissions',
        5894 => 'American Express Commissions',
        5896 => 'Other Credit Card Commissions',
        5899 => 'Total Credit Card Commissions',
        5999 => 'Total General & Admin. Expenses',

        /* Custom Account */ 
        10000 => 'Total Discount',
        10001 => 'Cheque Received',
        10002 => 'PST Charged on Sales',
        
        /* Wash Specific */
        4150 => 'Part Sales',
        4170 => 'Merchandise Sales',
        4175 => 'Labour Revenue',
        4205 => 'Full Service',
        4210 => 'Self Wash',
        4215 => 'Oil & Grease',
    ];

    /** Accounts */
    const ACCOUNTS = [
        1000 => 0.0, 1020 => 0.0, 1030 => 0.0, 1050 => 0.0, 1055 => 0.0, 1060 => 0.0, 1067 => 0.0, 1075 => 0.0, 1080 => 0.0, 1083 => 0.0,
        1087 => 0.0, 1089 => 0.0, 1090 => 0.0, 1100 => 0.0, 1200 => 0.0, 1205 => 0.0, 1220 => 0.0, 1230 => 0.0, 1300 => 0.0, 1320 => 0.0,
        1400 => 0.0, 1500 => 0.0, 1520 => 0.0, 1530 => 0.0, 1540 => 0.0, 1590 => 0.0, 1700 => 0.0, 1810 => 0.0, 1820 => 0.0, 1825 => 0.0,
        1830 => 0.0, 1840 => 0.0, 1845 => 0.0, 1850 => 0.0, 1860 => 0.0, 1865 => 0.0, 1870 => 0.0, 1880 => 0.0, 1890 => 0.0, 1900 => 0.0,
        1910 => 0.0, 1920 => 0.0, 1930 => 0.0, 1950 => 0.0, 2000 => 0.0, 2100 => 0.0, 2115 => 0.0, 2120 => 0.0, 2130 => 0.0, 2133 => 0.0,
        2134 => 0.0, 2135 => 0.0, 2140 => 0.0, 2145 => 0.0, 2160 => 0.0, 2170 => 0.0, 2180 => 0.0, 2185 => 0.0, 2190 => 0.0, 2195 => 0.0,
        2230 => 0.0, 2234 => 0.0, 2235 => 0.0, 2236 => 0.0, 2237 => 0.0, 2238 => 0.0, 2240 => 0.0, 2250 => 0.0, 2260 => 0.0, 2270 => 0.0,
        2280 => 0.0, 2310 => 0.0, 2312 => 0.0, 2315 => 0.0, 2320 => 0.0, 2325 => 0.0, 2330 => 0.0, 2335 => 0.0, 2340 => 0.0, 2460 => 0.0, 
        2500 => 0.0, 2600 => 0.0, 2620 => 0.0, 2630 => 0.0, 2640 => 0.0, 2700 => 0.0, 3000 => 0.0, 3010 => 0.0, 3015 => 0.0, 3560 => 0.0, 
        3600 => 0.0, 3700 => 0.0, 4000 => 0.0, 4020 => 0.0, 4030 => 0.0, 4040 => 0.0, 4200 => 0.0, 4220 => 0.0, 4240 => 0.0, 4260 => 0.0, 
        4400 => 0.0, 4420 => 0.0, 4440 => 0.0, 4460 => 0.0, 4500 => 0.0, 5000 => 0.0, 5020 => 0.0, 5030 => 0.0, 5040 => 0.0, 5100 => 0.0, 
        5120 => 0.0, 5130 => 0.0, 5140 => 0.0, 5190 => 0.0, 5200 => 0.0, 5220 => 0.0, 5240 => 0.0, 5290 => 0.0, 5300 => 0.0, 5350 => 0.0, 
        5400 => 0.0, 5410 => 0.0, 5420 => 0.0, 5430 => 0.0, 5440 => 0.0, 5464 => 0.0, 5465 => 0.0, 5466 => 0.0, 5467 => 0.0, 5468 => 0.0, 
        5470 => 0.0, 5490 => 0.0, 5600 => 0.0, 5610 => 0.0, 5615 => 0.0, 5620 => 0.0, 5625 => 0.0, 5630 => 0.0, 5640 => 0.0, 5645 => 0.0, 
        5650 => 0.0, 5660 => 0.0, 5680 => 0.0, 5685 => 0.0, 5690 => 0.0, 5700 => 0.0, 5720 => 0.0, 5730 => 0.0, 5740 => 0.0, 5750 => 0.0, 
        5760 => 0.0, 5765 => 0.0, 5780 => 0.0, 5784 => 0.0, 5789 => 0.0, 5790 => 0.0, 5890 => 0.0, 5892 => 0.0, 5894 => 0.0, 5896 => 0.0, 
        5899 => 0.0, 5999 => 0.0, 
        
        /* Custom */ 
        10000 => 0.0, 10001 => 0.0, 10002 => 0.0,
        
        /* Wash */
        4150 => 0.0,
        4170 => 0.0,
        4175 => 0.0,
        4205 => 0.0,
        4210 => 0.0,
        4215 => 0.0,
    ];

    /** Account Details */
    public const ACCOUNTS_DETAILS = [
        1000 => ['number' => 1000, 'name' => 'Current Assets', 'code' => '1000 Current Assets'],
        /*1020 => ['number' => 1020, 'name' => 'Cash to be deposited', 'code' => '1020 Cash to be deposited'],*/
        1020 => ['number' => 1020, 'name' => 'Cash Accepted', 'code' => '1020 Cash Accepted'],
        1030 => ['number' => 1030, 'name' => 'Cash Draws', 'code' => '1030 Cash Draws'],
        1050 => ['number' => 1050, 'name' => 'Petty Cash', 'code' => '1050 Petty Cash'],
        1055 => ['number' => 1055, 'name' => 'Savings Bank Account', 'code' => '1055 Savings Bank Account'],
        /*1060 => ['number' => 1060, 'name' => 'Chequing Bank Account', 'code' => '1060 Chequing Bank Account'],*/
        1060 => ['number' => 1060, 'name' => 'Checking Bank Account', 'code' => '1060 Checking Bank Account'],
        1067 => ['number' => 1067, 'name' => 'Foreign Currency Bank', 'code' => '1067 Foreign Currency Bank'],
        1075 => ['number' => 1075, 'name' => 'Total Cash', 'code' => '1075 Total Cash'],
        1080 => ['number' => 1080, 'name' => 'Visa', 'code' => '1080 Visa'],
        1083 => ['number' => 1083, 'name' => 'MasterCard', 'code' => '1083 MasterCard'],
        1087 => ['number' => 1087, 'name' => 'American Express', 'code' => '1087 American Express'],
        1089 => ['number' => 1089, 'name' => 'Other Credit Card', 'code' => '1089 Other Credit Card'],
        1090 => ['number' => 1090, 'name' => 'Total Credit Card', 'code' => '1090 Total Credit Card'],
        1100 => ['number' => 1100, 'name' => 'Investments', 'code' => '1100 Investments'],
        1200 => ['number' => 1200, 'name' => 'Accounts Receivable', 'code' => '1200 Accounts Receivable'],
        1205 => ['number' => 1205, 'name' => 'Allowance for Doubtful Accounts', 'code' => '1205 Allowance for Doubtful Accounts'],
        1220 => ['number' => 1220, 'name' => 'Advances & Loans', 'code' => '1220 Advances & Loans'],
        1230 => ['number' => 1230, 'name' => 'Total Receivable', 'code' => '1230 Total Receivable'],
        1300 => ['number' => 1300, 'name' => 'Purchase Prepayments', 'code' => '1300 Purchase Prepayments'],
        1320 => ['number' => 1320, 'name' => 'Prepaid Expenses', 'code' => '1320 Prepaid Expenses'],
        1400 => ['number' => 1400, 'name' => 'Total Current Assets', 'code' => '1400 Total Current Assets'],
        1500 => ['number' => 1500, 'name' => 'Inventory Assets', 'code' => '1500 Inventory Assets'],
        1520 => ['number' => 1520, 'name' => 'Inventory A', 'code' => '1520 Inventory A'],
        1530 => ['number' => 1530, 'name' => 'Inventory B', 'code' => '1530 Inventory B'],
        1540 => ['number' => 1540, 'name' => 'Inventory C', 'code' => '1540 Inventory C'],
        1590 => ['number' => 1590, 'name' => 'Total Inventory Assets', 'code' => '1590 Total Inventory Assets'],
        1700 => ['number' => 1700, 'name' => 'Capital Assets', 'code' => '1700 Capital Assets'],
        1810 => ['number' => 1810, 'name' => 'Leasehold Improvements', 'code' => '1810 Leasehold Improvements'],
        1820 => ['number' => 1820, 'name' => 'Office Furniture & Equipment', 'code' => '1820 Office Furniture & Equipment'],
        1825 => ['number' => 1825, 'name' => 'Accum. Amort. -Furn. & Equip.', 'code' => '1825 Accum. Amort. -Furn. & Equip.'],
        1830 => ['number' => 1830, 'name' => 'Net - Furniture & Equipment', 'code' => '1830 Net - Furniture & Equipment'],
        1840 => ['number' => 1840, 'name' => 'Vehicle', 'code' => '1840 Vehicle'],
        1845 => ['number' => 1845, 'name' => 'Accum. Amort. -Vehicle', 'code' => '1845 Accum. Amort. -Vehicle'],
        1850 => ['number' => 1850, 'name' => 'Net - Vehicle', 'code' => '1850 Net - Vehicle'],
        1860 => ['number' => 1860, 'name' => 'Building', 'code' => '1860 Building'],
        1865 => ['number' => 1865, 'name' => 'Accum. Amort. -Building', 'code' => '1865 Accum. Amort. -Building'],
        1870 => ['number' => 1870, 'name' => 'Net - Building', 'code' => '1870 Net - Building'],
        1880 => ['number' => 1880, 'name' => 'Land', 'code' => '1880 Land'],
        1890 => ['number' => 1890, 'name' => 'Total Capital Assets', 'code' => '1890 Total Capital Assets'],
        1900 => ['number' => 1900, 'name' => 'Other Non-Current Assets', 'code' => '1900 Other Non-Current Assets'],
        1910 => ['number' => 1910, 'name' => 'Computer Software', 'code' => '1910 Computer Software'],
        1920 => ['number' => 1920, 'name' => 'Goodwill', 'code' => '1920 Goodwill'],
        1930 => ['number' => 1930, 'name' => 'Incorporation Cost', 'code' => '1930 Incorporation Cost'],
        1950 => ['number' => 1950, 'name' => 'Total Other Non-Current Assets', 'code' => '1950 Total Other Non-Current Assets'],
        2000 => ['number' => 2000, 'name' => 'Current Liabilities', 'code' => '2000 Current Liabilities'],
        2100 => ['number' => 2100, 'name' => 'Accounts Payable', 'code' => '2100 Accounts Payable'],
        2115 => ['number' => 2115, 'name' => 'Import Duty Clearing', 'code' => '2115 Import Duty Clearing'],
        2120 => ['number' => 2120, 'name' => 'Bank Loan - Current Portion', 'code' => '2120 Bank Loan - Current Portion'],
        2130 => ['number' => 2130, 'name' => 'Bank Advances', 'code' => '2130 Bank Advances'],
        2133 => ['number' => 2133, 'name' => 'Visa Payable', 'code' => '2133 Visa Payable'],
        2134 => ['number' => 2134, 'name' => 'MasterCard Payable', 'code' => '2134 MasterCard Payable'],
        2135 => ['number' => 2135, 'name' => 'American Express Payable', 'code' => '2135 American Express Payable'],
        2140 => ['number' => 2140, 'name' => 'Other Credit Card Payable', 'code' => '2140 Other Credit Card Payable'],
        2145 => ['number' => 2145, 'name' => 'Total Credit Card Payables', 'code' => '2145 Total Credit Card Payables'],
        2160 => ['number' => 2160, 'name' => 'Corporate Taxes payable', 'code' => '2160 Corporate Taxes payable'],
        2170 => ['number' => 2170, 'name' => 'Vacation payable', 'code' => '2170 Vacation payable'],
        2180 => ['number' => 2180, 'name' => 'EI Payable', 'code' => '2180 EI Payable'],
        2185 => ['number' => 2185, 'name' => 'CPP Payable', 'code' => '2185 CPP Payable'],
        2190 => ['number' => 2190, 'name' => 'Federal Income Tax Payable', 'code' => '2190 Federal Income Tax Payable'],
        2195 => ['number' => 2195, 'name' => 'Total Receiver General', 'code' => '2195 Total Receiver General'],
        2230 => ['number' => 2230, 'name' => 'WCB Payable', 'code' => '2230 WCB Payable'],
        2234 => ['number' => 2234, 'name' => 'User-Defined Expense 1 Payable', 'code' => '2234 User-Defined Expense 1 Payable'],
        2235 => ['number' => 2235, 'name' => 'User-Defined Expense 2 Payable', 'code' => '2235 User-Defined Expense 2 Payable'],
        2236 => ['number' => 2236, 'name' => 'User-Defined Expense 3 Payable', 'code' => '2236 User-Defined Expense 3 Payable'],
        2237 => ['number' => 2237, 'name' => 'User-Defined Expense 4 Payable', 'code' => '2237 User-Defined Expense 4 Payable'],
        2238 => ['number' => 2238, 'name' => 'User-Defined Expense 5 Payable', 'code' => '2238 User-Defined Expense 5 Payable'],
        2240 => ['number' => 2240, 'name' => 'Deduction 1 Payable', 'code' => '2240 Deduction 1 Payable'],
        2250 => ['number' => 2250, 'name' => 'Deduction 2 Payable', 'code' => '2250 Deduction 2 Payable'],
        2260 => ['number' => 2260, 'name' => 'Deduction 3 Payable', 'code' => '2260 Deduction 3 Payable'],
        2270 => ['number' => 2270, 'name' => 'Deduction 4 Payable', 'code' => '2270 Deduction 4 Payable'],
        2280 => ['number' => 2280, 'name' => 'Deduction 5 Payable', 'code' => '2280 Deduction 5 Payable'],
        2310 => ['number' => 2310, 'name' => 'GST/HST Charged on Sales', 'code' => '2310 GST/HST Charged on Sales'],
        2312 => ['number' => 2312, 'name' => 'GST/HST Charged on Sales - Rate 2', 'code' => '2312 GST/HST Charged on Sales - Rate 2'],
        2315 => ['number' => 2315, 'name' => 'GST/HST Paid on Purchases', 'code' => '2315 GST/HST Paid on Purchases'],
        2320 => ['number' => 2320, 'name' => 'GST/HST Payroll Deductions', 'code' => '2320 GST/HST Payroll Deductions'],
        2325 => ['number' => 2325, 'name' => 'GST/HST Adjustments', 'code' => '2325 GST/HST Adjustments'],
        2330 => ['number' => 2330, 'name' => 'ITC Adjustments', 'code' => '2330 ITC Adjustments'],
        2335 => ['number' => 2335, 'name' => 'GST/HST Owing (Refund)', 'code' => '2335 GST/HST Owing (Refund)'],
        2340 => ['number' => 2340, 'name' => 'PST Owing (Refund)', 'code' => '2340 PST Owing (Refund)'],
        2460 => ['number' => 2460, 'name' => 'Prepaid Sales/Deposits', 'code' => '2460 Prepaid Sales/Deposits'],
        2500 => ['number' => 2500, 'name' => 'Total Current Liabilities', 'code' => '2500 Total Current Liabilities'],
        2600 => ['number' => 2600, 'name' => 'Long Term Liabilities', 'code' => '2600 Long Term Liabilities'],
        2620 => ['number' => 2620, 'name' => 'Bank Loans', 'code' => '2620 Bank Loans'],
        2630 => ['number' => 2630, 'name' => 'Mortgage Payable', 'code' => '2630 Mortgage Payable'],
        2640 => ['number' => 2640, 'name' => 'Loans from Owners', 'code' => '2640 Loans from Owners'],
        2700 => ['number' => 2700, 'name' => 'Total Long Term Liabilities', 'code' => '2700 Total Long Term Liabilities'],
        3000 => ['number' => 3000, 'name' => 'Owners Equity', 'code' => '3000 Owners Equity'],
        3010 => ['number' => 3010, 'name' => 'Owners Contribution', 'code' => '3010 Owners Contribution'],
        3015 => ['number' => 3015, 'name' => 'Owners Withdrawals', 'code' => '3015 Owners Withdrawals'],
        3560 => ['number' => 3560, 'name' => 'Retained Earnings - Previous Year', 'code' => '3560 Retained Earnings - Previous Year'],
        3600 => ['number' => 3600, 'name' => 'Current Earnings', 'code' => '3600 Current Earnings'],
        3700 => ['number' => 3700, 'name' => 'Total Owners Equity', 'code' => '3700 Total Owners Equity'],
        4000 => ['number' => 4000, 'name' => 'Sales Revenue', 'code' => '4000 Sales Revenue'],
        4020 => ['number' => 4020, 'name' => 'Sales Inventory A', 'code' => '4020 Sales Inventory A'],
        4030 => ['number' => 4030, 'name' => 'Sales Inventory B', 'code' => '4030 Sales Inventory B'],
        4040 => ['number' => 4040, 'name' => 'Sales Inventory C', 'code' => '4040 Sales Inventory C'],
        4200 => ['number' => 4200, 'name' => 'Sales', 'code' => '4200 Sales'],
        4220 => ['number' => 4220, 'name' => 'Sales Returns', 'code' => '4220 Sales Returns'],
        4240 => ['number' => 4240, 'name' => 'Early Payment Sales Discounts', 'code' => '4240 Early Payment Sales Discounts'],
        4260 => ['number' => 4260, 'name' => 'Net Sales', 'code' => '4260 Net Sales'],
        4400 => ['number' => 4400, 'name' => 'Other Revenue', 'code' => '4400 Other Revenue'],
        4420 => ['number' => 4420, 'name' => 'Freight Revenue', 'code' => '4420 Freight Revenue'],
        4440 => ['number' => 4440, 'name' => 'Interest Revenue', 'code' => '4440 Interest Revenue'],
        4460 => ['number' => 4460, 'name' => 'Miscellaneous Revenue', 'code' => '4460 Miscellaneous Revenue'],
        4500 => ['number' => 4500, 'name' => 'Total Other Revenue', 'code' => '4500 Total Other Revenue'],
        5000 => ['number' => 5000, 'name' => 'Cost of Goods Sold', 'code' => '5000 Cost of Goods Sold'],
        5020 => ['number' => 5020, 'name' => 'Inventory A Cost', 'code' => '5020 Inventory A Cost'],
        5030 => ['number' => 5030, 'name' => 'Inventory B Cost', 'code' => '5030 Inventory B Cost'],
        5040 => ['number' => 5040, 'name' => 'Inventory C Cost', 'code' => '5040 Inventory C Cost'],
        5100 => ['number' => 5100, 'name' => 'Inventory Variance', 'code' => '5100 Inventory Variance'],
        5120 => ['number' => 5120, 'name' => 'Item Assembly Costs', 'code' => '5120 Item Assembly Costs'],
        5130 => ['number' => 5130, 'name' => 'Adjustment Write-off', 'code' => '5130 Adjustment Write-off'],
        5140 => ['number' => 5140, 'name' => 'Transfer Costs', 'code' => '5140 Transfer Costs'],
        5190 => ['number' => 5190, 'name' => 'Subcontracts', 'code' => '5190 Subcontracts'],
        5200 => ['number' => 5200, 'name' => 'Purchases', 'code' => '5200 Purchases'],
        5220 => ['number' => 5220, 'name' => 'Purchase Returns', 'code' => '5220 Purchase Returns'],
        5240 => ['number' => 5240, 'name' => 'Early Payment Purchase Discounts', 'code' => '5240 Early Payment Purchase Discounts'],
        5290 => ['number' => 5290, 'name' => 'Net Purchases', 'code' => '5290 Net Purchases'],
        5300 => ['number' => 5300, 'name' => 'Freight Expense', 'code' => '5300 Freight Expense'],
        5350 => ['number' => 5350, 'name' => 'Total Cost of Goods Sold', 'code' => '5350 Total Cost of Goods Sold'],
        5400 => ['number' => 5400, 'name' => 'Payroll Expenses', 'code' => '5400 Payroll Expenses'],
        5410 => ['number' => 5410, 'name' => 'Wages & Salaries', 'code' => '5410 Wages & Salaries'],
        5420 => ['number' => 5420, 'name' => 'EI Expense', 'code' => '5420 EI Expense'],
        5430 => ['number' => 5430, 'name' => 'CPP Expense', 'code' => '5430 CPP Expense'],
        5440 => ['number' => 5440, 'name' => 'WCB Expense', 'code' => '5440 WCB Expense'],
        5464 => ['number' => 5464, 'name' => 'User-Defined Expense 1 Expense', 'code' => '5464 User-Defined Expense 1 Expense'],
        5465 => ['number' => 5465, 'name' => 'User-Defined Expense 2 Expense', 'code' => '5465 User-Defined Expense 2 Expense'],
        5466 => ['number' => 5466, 'name' => 'User-Defined Expense 3 Expense', 'code' => '5466 User-Defined Expense 3 Expense'],
        5467 => ['number' => 5467, 'name' => 'User-Defined Expense 4 Expense', 'code' => '5467 User-Defined Expense 4 Expense'],
        5468 => ['number' => 5468, 'name' => 'User-Defined Expense 5 Expense', 'code' => '5468 User-Defined Expense 5 Expense'],
        5470 => ['number' => 5470, 'name' => 'Employee Benefits', 'code' => '5470 Employee Benefits'],
        5490 => ['number' => 5490, 'name' => 'Total Payroll Expense', 'code' => '5490 Total Payroll Expense'],
        5600 => ['number' => 5600, 'name' => 'General & Administrative Expenses', 'code' => '5600 General & Administrative Expenses'],
        5610 => ['number' => 5610, 'name' => 'Accounting & Legal', 'code' => '5610 Accounting & Legal'],
        5615 => ['number' => 5615, 'name' => 'Advertising & Promotions', 'code' => '5615 Advertising & Promotions'],
        5620 => ['number' => 5620, 'name' => 'Bad Debts', 'code' => '5620 Bad Debts'],
        5625 => ['number' => 5625, 'name' => 'Business Fees & Licenses', 'code' => '5625 Business Fees & Licenses'],
        5630 => ['number' => 5630, 'name' => 'Cash Short/Over', 'code' => '5630 Cash Short/Over'],
        5640 => ['number' => 5640, 'name' => 'Courier & Postage', 'code' => '5640 Courier & Postage'],
        5645 => ['number' => 5645, 'name' => 'Credit Card Charges', 'code' => '5645 Credit Card Charges'],
        5650 => ['number' => 5650, 'name' => 'Currency Exchange & Rounding', 'code' => '5650 Currency Exchange & Rounding'],
        5660 => ['number' => 5660, 'name' => 'Amortization Expense', 'code' => '5660 Amortization Expense'],
        5680 => ['number' => 5680, 'name' => 'Income Taxes', 'code' => '5680 Income Taxes'],
        5685 => ['number' => 5685, 'name' => 'Insurance', 'code' => '5685 Insurance'],
        5690 => ['number' => 5690, 'name' => 'Interest & Bank Charges', 'code' => '5690 Interest & Bank Charges'],
        5700 => ['number' => 5700, 'name' => 'Office Supplies', 'code' => '5700 Office Supplies'],
        5720 => ['number' => 5720, 'name' => 'Property Taxes', 'code' => '5720 Property Taxes'],
        5730 => ['number' => 5730, 'name' => 'Motor Vehicle Expenses', 'code' => '5730 Motor Vehicle Expenses'],
        5740 => ['number' => 5740, 'name' => 'Miscellaneous Expenses', 'code' => '5740 Miscellaneous Expenses'],
        5750 => ['number' => 5750, 'name' => 'Realized Exchange Gain/Loss', 'code' => '5750 Realized Exchange Gain/Loss'],
        5760 => ['number' => 5760, 'name' => 'Rent', 'code' => '5760 Rent'],
        5765 => ['number' => 5765, 'name' => 'Repair & Maintenance', 'code' => '5765 Repair & Maintenance'],
        5780 => ['number' => 5780, 'name' => 'Telephone', 'code' => '5780 Telephone'],
        5784 => ['number' => 5784, 'name' => 'Travel & Entertainment', 'code' => '5784 Travel & Entertainment'],
        5789 => ['number' => 5789, 'name' => 'Travel & Ent:Non-Reimbursable', 'code' => '5789 Travel & Ent:Non-Reimbursable'],
        5790 => ['number' => 5790, 'name' => 'Utilities', 'code' => '5790 Utilities'],
        5890 => ['number' => 5890, 'name' => 'Visa Commissions', 'code' => '5890 Visa Commissions'],
        5892 => ['number' => 5892, 'name' => 'MasterCard Commissions', 'code' => '5892 MasterCard Commissions'],
        5894 => ['number' => 5894, 'name' => 'American Express Commissions', 'code' => '5894 American Express Commissions'],
        5896 => ['number' => 5896, 'name' => 'Other Credit Card Commissions', 'code' => '5896 Other Credit Card Commissions'],
        5899 => ['number' => 5899, 'name' => 'Total Credit Card Commissions', 'code' => '5899 Total Credit Card Commissions'],
        5999 => ['number' => 5999, 'name' => 'Total General & Admin. Expenses', 'code' => '5999 Total General & Admin. Expenses'],

        /* Custom Account */ 
        10000 => ['number' => 10000, 'name' => 'Total Discount', 'code' => '10000 Total Discount'],
        10001 => ['number' => 10001, 'name' => 'Cheque Received', 'code' => '10001 Cheque'],
        10002 => ['number' => 10002, 'name' => 'PST Charged on Sales'],

        /* Wash specific */ 
        4150 => ['number' => 4150, 'name' => 'Part Sales', 'code' => '4150 Part Sales'],
        4170 => ['number' => 4170, 'name' => 'Merchandise Sales', 'code' => '4170 Merchandise Sales'],
        4175 => ['number' => 4175, 'name' => 'Labour Revenue', 'code' => '4175 Labour Revenue'],
        4205 => ['number' => 4205, 'name' => 'Full Service', 'code' => '4205 Full Service'],
        4210 => ['number' => 4210, 'name' => 'Self Wash', 'code' => '4210 Self Wash'],
        4215 => ['number' => 4215, 'name' => 'Oil & Grease', 'code' => '4215 Oil & Grease'],
    ];

    /**
     * This method will return the account code by payment method.
     * @return int
     */
    public static function get_account_code_by_payment_method(int $payment_method) : int {
        switch($payment_method) {
            case PaymentMethod::MODES_OF_PAYMENT['Pay Later']:
                return AccountsConfig::ACCOUNTS_RECEIVABLE;
            case PaymentMethod::MODES_OF_PAYMENT['Cash']:
                /* Earlier Used to be CASH_TO_BE_DEPOSITED */
                return AccountsConfig::CHEQUING_BANK_ACCOUNT;
            case PaymentMethod::MODES_OF_PAYMENT['Cheque']:
                return AccountsConfig::CHEQUE_RECEIVABLES;
            case PaymentMethod::MODES_OF_PAYMENT['PAD']:
                throw new Exception('Payment Method "PAD" not supported.');
            case PaymentMethod::MODES_OF_PAYMENT['American Express']: 
                return AccountsConfig::AMEX_RECEIVABLE;
            case PaymentMethod::MODES_OF_PAYMENT['Mastercard']: 
                return AccountsConfig::MASTERCARD_RECEIVABLE;
            case PaymentMethod::MODES_OF_PAYMENT['Visa']: 
                return AccountsConfig::VISA_RECEIVABLE;
            case PaymentMethod::MODES_OF_PAYMENT['Online Payment']: 
                throw new Exception('Payment Method "Online Payment" not supported.');
            case PaymentMethod::MODES_OF_PAYMENT['Debit']: 
                return AccountsConfig::CHEQUING_BANK_ACCOUNT;
        }
        throw new Exception('Invalid Payment Method.');
    }
}
?>