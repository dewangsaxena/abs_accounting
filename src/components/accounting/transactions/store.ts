import { create } from "zustand";
import { ClientDetails } from "../../client/store";
import { toFixed } from "../../../shared/functions";
import { HTTPService } from "../../../service/api-client";
import { Account, Prices } from "../../inventory/itemStore";
import { ITEM_DETAILS_TAG, TRANSACTION_TYPES } from "../../../shared/config";

// Http Service
const httpService = new HTTPService();

/**
 * Row Details
 */
export interface RowDetails {
  itemId: number | null;
  category: number | null;
  quantity: number;
  unit: string;
  identifier: string;
  description: string;
  basePrice: number;
  discountRate: number;
  pricePerItem: number;
  amountPerItem: number;
  gstHSTTaxRate: number;
  pstTaxRate: number;
  buyingCost: number;
  originalSellingPrice: number;
  isBackOrder: number;

  /* Account Details */
  account: Account;

  /* Sales Return Specific Values */
  returnQuantity?: number;
  /*invoiceAmount?: number;*/

  /* Meta Data */
  isExisting?: number;
}

/**
 * Default Row Item Details.
 */
export const defaultRowItemDetails: RowDetails = {
  itemId: null,
  category: null,
  amountPerItem: 0,
  basePrice: 0,
  identifier: "",
  description: "",
  discountRate: 0,
  /*invoiceAmount: 0,*/
  returnQuantity: 0,
  pricePerItem: 0,
  quantity: 0,
  gstHSTTaxRate: 0,
  pstTaxRate: 0,
  buyingCost: 0,
  originalSellingPrice: 0,
  unit: "",
  account: {} as Account,
  isBackOrder: 0,
};

/**
 * Item details for Transactions
 */
export interface ItemDetailsForTransactions {
  id: number;
  category: number;
  quantity: number;
  prices: { [storeId: number]: Prices };
  aisle: string;
  shelf: string;
  column: string;
  identifier: string;
  description: string;
  oem?: string;
  unit: string;
  account: Account;
  memo: string;
  additionalInformation: string;
  disableDiscount?: boolean;
}

/**
 * Transaction Store Fields
 */
export interface TransactionStoreFields {
  id: number | null;
  transactionType: number | null;
  clientDetails: ClientDetails | null;
  /* ISO 8601 */
  txnDate: string;
  po?: string;
  unitNo?: string;
  vin?: string;
  driverName?: string;
  odometerReading?: string;
  trailerNumber?: string;
  previousTxnId: number | null;
  nextTxnId: number | null;
  paymentMethod: number;
  details: RowDetails[];
  subTotal: number;
  txnDiscount: number;
  cogs: number /* For Sales Invoice Only */;
  gstHSTTax: number;
  pstTax: number;
  creditAmount: number;
  sumTotal: number;
  salesRepId: number;
  storeId: number | null;
  notes: string;
  __lockCounter: number;
  disableItemEditing: number;
  disableFederalTaxes: number;
  disableProvincialTaxes: number;
  earlyPaymentDiscount: number;
  earlyPaymentPaidWithinDays: number;
  netAmountDueWithinDays: number;
  accountNumber?: string;
  purchasedBy?: string;
  isInvoiceTransferred?: number;
  versionKeys?: { [timestamp: number]: string };
  versionSelected?: number;
  disableCreditTransactions?: number;
  /* Old Transaction Details */
  initial: { [detailName: string]: any };
  itemDetailsForTransactions?: {
    [itemId: number]: ItemDetailsForTransactions;
  } | null;
  lastModifiedTimestamp?: string;
  selectedSalesInvoice?: number;
  selectedSalesInvoiceLastModifiedTimestamp?: string;
}

/**
 * Transaction Store Type
 */
export interface TransactionStore extends TransactionStoreFields {
  setProperty: (detailName: string, value: any) => void;
  addRow: () => void;
  updateAmounts: () => void;
  process: () => Promise<any>;
  fetchInvoicesByClientForSalesReturns: (invoiceId: string) => any;
  fetchTransaction: (
    transactionType: number,
    transactionId: number | string | null
  ) => any;
  setTransactionDetails: (details: TransactionStoreFields) => void;
  sendEmail: () => any;
}

/**
 * Transaction Store
 */
export const transactionStore = create<TransactionStore>((set, get) => ({
  id: null,
  transactionType: null,
  clientDetails: null,
  txnDate: new Date().toISOString(),
  po: "",
  unitNo: "",
  vin: "",
  driverName: "",
  odometerReading: "",
  trailerNumber: "",
  previousTxnId: null,
  nextTxnId: null,
  paymentMethod: -1,
  /* Add one item by default */
  details: [{ ...defaultRowItemDetails }],
  creditAmount: 0,
  subTotal: 0,
  txnDiscount: 0,
  cogs: 0,
  gstHSTTax: 0,
  pstTax: 0,
  sumTotal: 0,
  salesRepId: 0,
  storeId: parseInt(localStorage.getItem("storeId") || ""),
  notes: "",
  __lockCounter: 0,
  disableItemEditing: 0,
  disableFederalTaxes: 0,
  disableProvincialTaxes: 0,
  earlyPaymentDiscount: 0,
  earlyPaymentPaidWithinDays: 0,
  netAmountDueWithinDays: 0,
  accountNumber: "",
  purchasedBy: "",
  versionKeys: {} as any,
  versionSelected: undefined,
  disableCreditTransactions: 0,
  /* Initial Transaction Details */
  initial: {},
  itemDetailsForTransactions: null,
  setProperty: (detailName: string, value: any) => {
    if (detailName === "paymentMethod") set({ paymentMethod: value });
    else if (detailName === "txnDate") set({ txnDate: value });
    else if (detailName === "po") set({ po: value });
    else if (detailName === "unitNo") set({ unitNo: value });
    else if (detailName === "vin") set({ vin: value.trim().toUpperCase() });
    else if (detailName === "driverName") set({ driverName: value });
    else if (detailName === "odometerReading") set({ odometerReading: value });
    else if (detailName === "trailerNumber") set({ trailerNumber: value });
    else if (detailName === "notes") set({ notes: value });
    else if (detailName === "id") set({ id: value });
    else if (detailName === "transactionType") set({ transactionType: value });
    else if (detailName === "selectedSalesInvoiceLastModifiedTimestamp")
      set({ selectedSalesInvoiceLastModifiedTimestamp: value });
    else if (detailName === "clientDetails") set({ clientDetails: value });
    else if (detailName === "disableFederalTaxes")
      set({ disableFederalTaxes: value });
    else if (detailName === "disableProvincialTaxes")
      set({ disableProvincialTaxes: value });
    else if (detailName === "details") {
      set({ details: value });
    } else if (detailName === "selectedSalesInvoice")
      set({ selectedSalesInvoice: value });
    else if (detailName === "earlyPaymentDiscount")
      set({ earlyPaymentDiscount: value });
    else if (detailName === "earlyPaymentPaidWithinDays")
      set({ earlyPaymentPaidWithinDays: value });
    else if (detailName === "netAmountDueWithinDays")
      set({ netAmountDueWithinDays: value });
    else if (detailName === "accountNumber") set({ accountNumber: value });
    else if (detailName === "purchasedBy") set({ purchasedBy: value });
    else if (detailName === "versionSelected") set({ versionSelected: value });
    else if (detailName === "disableCreditTransactions")
      set({ disableCreditTransactions: value });
    else if (detailName === "salesRepId") {
      set({ salesRepId: value });
    }
  },
  addRow: () => {
    let txnDetails = get().details;
    txnDetails.push({ ...defaultRowItemDetails });
    set({ details: txnDetails });
  },
  updateAmounts: () => {
    let rowDetails = get().details;
    let totalRows = rowDetails.length;
    let subTotal: number = 0,
      txnDiscount: number = 0,
      gstHSTTax: number = 0,
      pstTax: number = 0,
      cogs: number = 0;

    let base_price: number = 0,
      price_per_item: number = 0,
      quantity: number = 0,
      discount_per_item: number = 0,
      temp: number = 0;

    for (let i = 0; i < totalRows; ++i) {
      if (rowDetails[i].isBackOrder === 0 && rowDetails[i].quantity > 0) {
        subTotal += rowDetails[i].amountPerItem;

        /* Calculate Total Discount */
        base_price = rowDetails[i].basePrice;
        price_per_item = rowDetails[i].pricePerItem;
        quantity =
          get().transactionType === TRANSACTION_TYPES["SR"]
            ? rowDetails[i].returnQuantity || 0
            : rowDetails[i].quantity;
        discount_per_item = base_price * quantity - price_per_item * quantity;
        txnDiscount += discount_per_item;

        // COGS
        cogs += rowDetails[i].buyingCost * quantity;

        /* Calculate GST/HST Tax Amount */
        temp =
          (rowDetails[i].gstHSTTaxRate * rowDetails[i].amountPerItem) / 100;
        gstHSTTax += temp;

        /* Calculate PST Tax Amount */
        temp = (rowDetails[i].pstTaxRate * rowDetails[i].amountPerItem) / 100;
        pstTax += temp;
      }
    }

    // Round off
    subTotal = toFixed(subTotal);
    gstHSTTax = toFixed(gstHSTTax);
    pstTax = toFixed(pstTax);
    txnDiscount = toFixed(txnDiscount);
    cogs = toFixed(cogs);

    set({ subTotal: subTotal });
    set({ gstHSTTax: gstHSTTax });
    set({ pstTax: pstTax });
    set({ txnDiscount: txnDiscount });
    set({ sumTotal: toFixed(subTotal + gstHSTTax + pstTax) });
    set({ cogs: cogs });
  },
  process: async (): Promise<any> => {
    // Remove Invalid Rows
    let details: RowDetails[] = JSON.parse(JSON.stringify(get().details));
    let noOfDetails = details.length;
    let sanitizedDetails = [];
    let transactionType: number | null = get().transactionType;

    for (let i = 0; i < noOfDetails; ++i) {
      if (details[i].itemId !== null) {
        if (
          transactionType === TRANSACTION_TYPES["SI"] ||
          transactionType === TRANSACTION_TYPES["SR"] ||
          transactionType === TRANSACTION_TYPES["QT"]
        ) {
          details[i].identifier = ITEM_DETAILS_TAG + details[i].identifier;
          details[i].description = ITEM_DETAILS_TAG + details[i].description;
        }
        sanitizedDetails.push(details[i]);
      }
    }
    get().updateAmounts();
    let __get = get();
    if (__get.clientDetails?.salesInvoices)
      delete __get.clientDetails?.salesInvoices;
    let payload: TransactionStoreFields = JSON.parse(JSON.stringify(__get));

    // Assign sanitized Details
    payload.details = sanitizedDetails;

    if (get().id === null) {
      return httpService.add<number>(payload, "create_txn");
    } else {
      return httpService.update<void>(payload, "update_txn");
    }
  },
  fetchInvoicesByClientForSalesReturns: async (invoiceId: string) => {
    let payload: { [key: string]: any } = {
      client_id: get().clientDetails?.id,
      invoice_id: invoiceId,
    };
    return await httpService.fetch<any[]>(
      payload,
      "fetch_sales_invoices_for_client"
    );
  },
  fetchTransaction: async (
    transactionType: number,
    transactionId: number | string | null
  ) => {
    let payload = {
      transactionType: transactionType,
      transaction_id: transactionId,
    };
    return await httpService.fetch<TransactionStoreFields>(
      payload,
      "fetch_transaction_by_id"
    );
  },
  setTransactionDetails: (details: TransactionStoreFields) => {
    set({ id: details.id });
    set({ transactionType: details.transactionType });
    set({ clientDetails: details.clientDetails });
    set({ txnDate: details.txnDate });
    set({ po: details.po });
    set({ unitNo: details.unitNo });
    set({ vin: details.vin });
    set({ driverName: details.driverName });
    set({ odometerReading: details.odometerReading });
    set({ trailerNumber: details.trailerNumber });
    set({ previousTxnId: details.previousTxnId });
    set({ nextTxnId: details.nextTxnId });
    set({ paymentMethod: details.paymentMethod });
    set({ details: details.details });
    set({ creditAmount: details.creditAmount });
    set({ subTotal: details.subTotal });
    set({ txnDiscount: details.txnDiscount });
    set({ gstHSTTax: details.gstHSTTax });
    set({ pstTax: details.pstTax });
    set({ sumTotal: details.sumTotal });
    set({ salesRepId: details.salesRepId });
    set({ storeId: details.storeId });
    set({ notes: details.notes });
    set({ __lockCounter: details.__lockCounter });
    set({ disableFederalTaxes: details.disableFederalTaxes });
    set({ disableProvincialTaxes: details.disableProvincialTaxes });
    set({ disableItemEditing: details.disableItemEditing });
    set({ earlyPaymentDiscount: details.earlyPaymentDiscount });
    set({ earlyPaymentPaidWithinDays: details.earlyPaymentPaidWithinDays });
    set({ netAmountDueWithinDays: details.netAmountDueWithinDays });
    set({ isInvoiceTransferred: details.isInvoiceTransferred });
    set({ accountNumber: details.accountNumber });
    set({ purchasedBy: details.purchasedBy });
    set({ versionKeys: details.versionKeys });
    set({ initial: details.initial });
    set({
      itemDetailsForTransactions: details.itemDetailsForTransactions,
    });
    set({ lastModifiedTimestamp: details.lastModifiedTimestamp });

    /* Sales Return Specific Details */
    set({ selectedSalesInvoice: details.selectedSalesInvoice });
    set({
      selectedSalesInvoiceLastModifiedTimestamp:
        details.selectedSalesInvoiceLastModifiedTimestamp,
    });
    if (details.versionKeys) {
      let versionKeys = Object.keys(details.versionKeys);
      if (versionKeys.length > 0) {
        set({ versionSelected: parseInt(versionKeys[0]) });
      }
    }
  },
  sendEmail: async () => {
    let payload = {
      transactionType: get().transactionType,
      transactionId: get().id,
    };
    return await httpService.fetch(payload, "txn_email");
  },
}));

/* Store State */
interface StoreState {
  isProcessed: boolean;
  changeProcessedState: (state: boolean) => void;
}

export const txnStateStore = create<StoreState>((set, _) => ({
  isProcessed: false,
  changeProcessedState: (state: boolean) => {
    set({ isProcessed: state });
  },
}));
