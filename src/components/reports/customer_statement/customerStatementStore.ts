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
  is_excluded?: boolean;
}

/**
 * Selected Clients Type
 */
export type SelectedClientsType = {
  [id: number]: CustomerAgedSummary;
};

/**
 * Customer Statement
 */
interface CustomerStatement {
  clientId?: number;
  startDate?: Date;
  endDate?: Date;
  attachTransactions: boolean;
  generateRecordOfAllTransactions: boolean;
  selectedClients: SelectedClientsType;
  noOfSelectedClients: number;
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
    selectedClients: {} as SelectedClientsType,
    customerAgedSummaryList: [],
    sortAscending: 0,
    noOfSelectedClients: 0,
    storeId: parseInt(localStorage.getItem("storeId") || "0"),
    email: async (payload: any) => {
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
      else if (name === "selectedClients") set({ selectedClients: value });
      else if (name === "noOfSelectedClients") set({noOfSelectedClients: value});
      else if (name === "setAscendingSort") set({ sortAscending: value });
    },
    fetchCustomerAgedSummary: async () => {
      return httpService.fetch(
        {
          storeId: get().storeId,
          tillDate: get().endDate?.toISOString().substring(0, 10),
          sortAscending: get().sortAscending,
          omitCreditRecords: 1,
        },
        "customer_aged_summary"
      );
    },
    setExcludedClients: (clientId: number) => {
      let selectedClients: SelectedClientsType = get().selectedClients;
      if (selectedClients[clientId].is_excluded === undefined || selectedClients[clientId].is_excluded === false) {
        selectedClients[clientId].is_excluded = true;
        get().noOfSelectedClients -= 1;
      } else {
        selectedClients[clientId].is_excluded = false;
        get().noOfSelectedClients += 1;
      }

      // Set directly, but do not rerender
      get().selectedClients = selectedClients;
    },
  })
);
