import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";
import { TRANSACTION_TYPES } from "../../../shared/config";
import { getAmountInWords } from "../../../shared/toWords";

// Http Service
const httpService = new HTTPService();

/**
 * Transaction Details
 */
export interface TransactionDetails {
  id: number;
  txnId: string;
  type: number;
  date: string;
  originalAmount: number;
  amountOwing: number;
  discountAvailable: number;
  discountGiven: number;
  amountReceived: number;
  salesInvoiceId: number | null;
  salesInvoicePaymentMethod: number | null;
  isChecked: number;
  modified: string;
}

// Receipt Store Details
export interface ReceiptStoreDetails {
  id?: number;
  clientId?: number;
  clientLastModifiedTimestamp?: string;
  clientName?: string;
  date: Date;
  paymentMethod?: number;
  transactions: TransactionDetails[];
  totalAmountReceived: number;
  amountInWords?: string;
  totalDiscount: number;
  chequeNumber?: string;
  storeId?: number;
  comment?: string;
  modified?: string;
  initial?: {
    paymentMethod: number;
  };
}

/**
 * Receipt Store
 */
interface ReceiptStore extends ReceiptStoreDetails {
  setProperty: (propertyName: string, value: any) => void;
  calculateTotalAmountReceived: () => void;
  fetch: () => any;
  process: (isViewOrUpdate: boolean) => any;
  load: (receiptId: number) => any;
  setDetails: (details: ReceiptStoreDetails) => any;
  sendEmail: (selectedTxn: string) => any;
  deleteReceipt: () => any;
}

// Receipt Store
export const receiptStore = create<ReceiptStore>((set, get) => ({
  date: new Date(),
  transactions: [],
  totalAmountReceived: 0,
  totalDiscount: 0,
  setProperty: (propertyName: string, value: any) => {
    if (propertyName === "id") set({ id: value });
    else if (propertyName === "paymentMethod") set({ paymentMethod: value });
    else if (propertyName === "transactions") set({ transactions: value });
    else if (propertyName === "clientId") set({ clientId: value });
    else if (propertyName === "clientLastModifiedTimestamp")
      set({ clientLastModifiedTimestamp: value });
    else if (propertyName === "comment") set({ comment: value });
    else if (propertyName === "chequeNumber") set({ chequeNumber: value });
    else if (propertyName === "amountInWords") set({ amountInWords: value });
  },
  calculateTotalAmountReceived: () => {
    let transactions = get().transactions;
    let count = transactions.length;
    let totalAmountReceived = 0;
    for (let i = 0; i < count; ++i) {
      totalAmountReceived += transactions[i].amountReceived;
    }
    set({ totalAmountReceived: totalAmountReceived });
    set({ amountInWords: getAmountInWords(totalAmountReceived) });
  },
  fetch: async () => {
    let payload = {
      clientId: get().clientId,
      transactionType: TRANSACTION_TYPES["RC"],
    };
    return await httpService.fetch<any[]>(
      payload,
      "txn_fetch_outstanding_txn_for_receipt"
    );
  },
  process: async (isViewOrUpdate: boolean) => {
    let payload = JSON.parse(JSON.stringify(get()));
    payload["transactionType"] = TRANSACTION_TYPES["RC"];
    return await httpService.fetch<any[]>(
      payload,
      isViewOrUpdate ? "update_txn" : "create_txn"
    );
  },
  load: async (receiptId: number) => {
    let payload = {
      transactionType: TRANSACTION_TYPES["RC"],
      transaction_id: receiptId,
    };
    return await httpService.fetch<ReceiptStore>(
      payload,
      "fetch_transaction_by_id"
    );
  },
  setDetails: (details: ReceiptStoreDetails) => {
    set({ id: details.id });
    set({ clientId: details.clientId });
    set({ clientLastModifiedTimestamp: details.clientLastModifiedTimestamp });
    set({ clientName: details.clientName });
    let dateParts = details.date.toString().split("-");
    set({
      date: new Date(
        parseInt(dateParts[0]),
        parseInt(dateParts[1]) - 1,
        parseInt(dateParts[2])
      ),
    });
    set({ paymentMethod: details.paymentMethod });
    set({ transactions: details.transactions });
    set({ totalAmountReceived: details.totalAmountReceived });
    set({ amountInWords: getAmountInWords(details.totalAmountReceived) });
    set({ totalDiscount: details.totalDiscount });
    set({ storeId: details.storeId });
    set({ comment: details.comment });
    set({ initial: details.initial });
    set({ chequeNumber: details.chequeNumber });
    set({ modified: details.modified });
  },
  sendEmail: async (selectedTxn: string) => {
    let payload = {
      id: get().id,
      selectedTxn: selectedTxn,
    };
    return await httpService.fetch<ReceiptStore>(payload, "receipt_email");
  },
  deleteReceipt: async () => {
    let payload = {
      transactionType: TRANSACTION_TYPES["RC"],
      id: get().id,
      clientLastModifiedTimestamp: get().clientLastModifiedTimestamp,
      modified: get().modified,
    };
    return await httpService.fetch(payload, "delete_txn");
  },
}));
