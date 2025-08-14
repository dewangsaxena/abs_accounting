/**
 * Configurations used the application.
 */
export const CLIENT_APP_VERSION = "2.2.38";

// Stores
export class Stores {
  /**
   * Store names.
   */
  public static readonly names: { [key: number]: string } = {
    1: "All",
    2: "Edmonton",
    3: "Calgary",
    4: "Nisku",
    5: "Vancouver",
    6: "Slave Lake",
    7: "Delta",
    8: "Regina",
    9: "Saskatoon"
  };

  // Inactive Stores
  private static readonly inactiveStores: Array<number> = [1, 5];

  // Get Active Stores.
  static getActiveStores(includeAllStore: boolean = false): AttributeType {
    let activeStores: AttributeType = {};
    Object.assign(activeStores, Stores.names);
    for (let i = 0; i < Stores.inactiveStores.length; ++i) {
      if (includeAllStore && Stores.inactiveStores[i] == 1) continue;
      delete activeStores[Stores.inactiveStores[i]];
    }
    return activeStores;
  }
}

// Domains Base URLS
const DOMAINS_BASE_URLS: AttributeType = {
  parts: "parts.absyeg.store",
  wash: "wash.absyeg.store",
  ten_leasing: "tenleasing.absyeg.store",
  localhost: "localhost",
};

/* Default System Init Mode */
export const MODE_WASH: number = 1;
export const MODE_PARTS: number = 2;

// System Initiation Flags
const isParts: boolean = location.hostname.includes(DOMAINS_BASE_URLS["parts"])
  ? true
  : false;
const isWash: boolean = location.hostname.includes(DOMAINS_BASE_URLS["wash"])
  ? true
  : false;
const isTenLeasing: boolean = location.hostname.includes(DOMAINS_BASE_URLS["ten_leasing"]) ? true : false;

/** Detault System Mode */
const defaultSystemMode: number = MODE_PARTS;

/* By Default, the System Config Mode is Parts */
export const systemConfigMode: number | null = 
  isWash
    ? MODE_WASH
  : 
  isParts
    ? MODE_PARTS
  : 
  isTenLeasing ? MODE_PARTS: defaultSystemMode;

// System Config Mode Colors
export const systemConfigModeColors: string | undefined = {
  1: "blue",
  2: "orange",
}[systemConfigMode];

// Client Category
export const clientCategory: { [id: number]: string } = {
  1: "Other/No Category",
  2: "Logistic",
  3: "Repair Shop",
  4: "Owner Driver",
  5: "Driver",
  6: "Transport",
  7: "Mobile Repair Van",
  8: "Transporter/Fleet",
};

/**
 * Non Credit Payment Method
 */
const nonCreditPaymentMethod: { [id: number]: string } = {
  1: "Cash",
  2: "Cheque",
  4: "American Express",
  5: "Mastercard",
  6: "VISA",
  8: "Debit",
};

// Receipt Payment Methods
export const receiptPaymentMethods: { [id: number]: string } = Object.assign(
  { 10: "Forgiven" },
  nonCreditPaymentMethod
);

// Payment Methods
export const paymentMethods: { [id: number]: string } = Object.assign(
  { 0: "Pay Later" },
  nonCreditPaymentMethod
);

// Forgiven payment method id
export const FORGIVEN_PAYMENT_METHOD_ID = 10;

// Pay Later
export const PAY_LATER_ID = 0;

// Cheque Payment ID
export const CHEQUE_ID = 2;

// Access Levels
export const accessLevels: { [id: number]: string } = {
  0: "Admin",
  1: "Sales Representative",
  2: "Read-Only",
};

// Identifiers
export const adminAccessLevel = 0;
export const salesRepresentativeAccessLevel = 1;
export const readOnlyAccessLevel = 2;

/* Transaction Types */
export const TRANSACTION_TYPES: { [txnType: string]: number } = {
  SI: 1,
  SR: 2,
  CN: 3,
  DN: 4,
  QT: 5,
  RC: 6,
  PI: 7,
};

/**
 * Transaction Types Without Receipt and Quotation.
 */
export const TRANSACTION_TYPES_RC_RECEIPT_QT: { [txnType: number]: string } = {
  1: "Sales Invoice",
  2: "Sales Return",
  3: "Credit Note",
  4: "Debit Note",
};

/** Attribute Type */
export type AttributeType<T = any> = { [key: string | number]: T };

/** Unknown Server Error */
export const UNKNOWN_SERVER_ERROR_MSG: string = "Unknown Server Error.";

// Default Profit Margin Key
export const DEFAULT_PROFIT_MARGIN_KEY = "DEFAULT";

/** This module contains config shared by entire application. */
export const APP_HOST = isParts
  ? "https://" + DOMAINS_BASE_URLS["parts"]
  : isWash
  ? "https://" + DOMAINS_BASE_URLS["wash"]
  : isTenLeasing ? 
    "https://" + DOMAINS_BASE_URLS["ten_leasing"]
  : "http://" + DOMAINS_BASE_URLS["localhost"];

/** Min Length before fetching */
export const AUTO_SUGGEST_MIN_INPUT_LENGTH: number = 1;

// Months
export const MONTHS: { [monthIndex: number]: string } = {
  0: "All",
  1: "January",
  2: "February",
  3: "March",
  4: "April",
  5: "May",
  6: "June",
  7: "July",
  8: "August",
  9: "September",
  10: "October",
  11: "November",
  12: "December",
};

// Item Details Tag
export const ITEM_DETAILS_TAG = "85163d53-ace8-4140-b83c-1c89294f6464";

/**
 * EHC Items List.
 */
export const EHC_ITEMS_LIST: number[] = [
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