import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";
import { APP_HOST, AttributeType } from "../../../shared/config";

/** HTTP Service */
const httpService = new HTTPService();

/**
 * Customer Aged Summary
 */
export interface CustomerAgedSummary {
  "31-60": number;
  "61-90": number;
  "91+": number;
  client_id: number;
  client_name: number;
  current: number;
  phone_number: string;
  total: number;
  is_email_sent?: boolean;
}

/**
 * Customer Aged Summary List
 */
export type CustomerAgedSummaryList = CustomerAgedSummary[];

/**
 * Selected Clients Type
 */
export type SelectedClientsType = {
  [id: number]: number;
};

// Max Client Limit
export const MAX_SELECTED_CLIENT_LIMIT: number = 10;

/**
 * Customer Statement
 */
interface CustomerStatement {
  clientId?: number;
  startDate?: Date;
  endDate?: Date;
  attachTransactions: boolean;
  generateRecordOfAllTransactions: boolean;
  excludedClients: SelectedClientsType;
  customerAgedSummaryList: CustomerAgedSummaryList;
  noOfExcludedClients: number;
  storeId: number;
  sortAscending: number;
  email: (payload: any) => any;
  print: (payload: AttributeType) => void;
  setDetail: (name: string, value: any) => void;
  fetchCustomerAgedSummary: () => any;
  setExcludedClients: (clientId: number) => void;
}

/**
 * Customer Statement
 */
export const customerStatementReport = create<CustomerStatement>(
  (set, get) => ({
    attachTransactions: false,
    startDate: undefined,
    endDate: new Date(),
    generateRecordOfAllTransactions: false,
    excludedClients: {} as SelectedClientsType,
    customerAgedSummaryList: [],
    sortAscending: 0,
    noOfExcludedClients: 0,
    storeId: parseInt(localStorage.getItem("storeId") || "0"),
    email: async (payload: any) => {
      if (Object.keys(get().excludedClients).length > 0) {
        delete payload["clientID"];
      }
      return await httpService.fetch(payload, "email_customer_statement");
    },
    print: (payload: AttributeType) => {
      const myURL = new URL(APP_HOST + "/api.php");
      myURL.searchParams.append("action", "customer_statement");
      myURL.searchParams.append("clientId", payload.clientId);
      myURL.searchParams.append("startDate", payload.startDate);
      myURL.searchParams.append("endDate", payload.endDate);
      myURL.searchParams.append(
        "attachTransactions",
        payload.attachTransactions
      );
      myURL.searchParams.append(
        "generateRecordOfAllTransactions",
        payload.generateRecordOfAllTransactions
      );
      myURL.searchParams.append("storeId", payload.storeId);
      window.open(myURL.href, "_blank");
    },
    setDetail: (name: string, value: any) => {
      if (name === "clientId") set({ clientId: value });
      else if (name === "startDate") set({ startDate: value });
      else if (name === "endDate") set({ endDate: value });
      else if (name === "attachTransactions")
        set({ attachTransactions: value });
      else if (name === "generateRecordOfAllTransactions")
        set({ generateRecordOfAllTransactions: value });
      else if (name === "excludedClients") set({ excludedClients: value });
      else if (name === "setAscendingSort") set({ sortAscending: value });
      else if (name === "customerAgedSummaryList")
        set({ customerAgedSummaryList: value });
    },
    fetchCustomerAgedSummary: async () => {
      return httpService.fetch(
        {
          storeId: get().storeId,
          tillDate: get().endDate?.toISOString().substring(0, 10),
          sortAscending: get().sortAscending,
        },
        "customer_aged_summary"
      );
    },
    setExcludedClients: (clientId: number) => {
      let excludedClients: SelectedClientsType = get().excludedClients;
      if (excludedClients[clientId] === undefined) {
        excludedClients[clientId] = clientId;
        get().noOfExcludedClients += 1;
      } else {
        delete excludedClients[clientId];
        get().noOfExcludedClients -= 1;
      }
    },
  })
);
