import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";
import { AttributeType } from "../../../shared/config";

// Http Service
const httpService = new HTTPService();

export interface MonthlyReport {
  sumTotal: number;
  subTotal: number;
  profitMargin: number;
  cogsMargin: number;
  amountReceived: number;
}

// Summary
export interface Summary {
  goodsCost: number;
  subTotal: number;
  monthlyReport: AttributeType<MonthlyReport>;
}

// YTD
interface YTD {
  sumTotal: number;
  subTotal: number;
  sumReturned: number;
  subReturned: number;
  cogsMargin: number;
  profitMargin: number;
}

// Overall Stats
export interface CustomerSummaryOverallStats {
  ytd: YTD;
  lastPurchaseDate: string;
}

// Customer Summary Report
export interface CustomerSummaryReport extends CustomerSummaryOverallStats {
  name: string;
  category: number;
  summary: AttributeType<Summary>;
}

// Response
export interface CustomerSummaryReportResponse {
  report: AttributeType<CustomerSummaryReport>;
  __offset: number;
}

// Customer Summary Report
interface CustomerSummaryReportDetails {
  selectedClients: Array<number> | undefined;
  minimumAmount: number;
  maximumAmount: number;
  yearFrom: number;
  yearTill: number;
  category: number;
  report: AttributeType<CustomerSummaryReport>;
  __offset: number;
  setDetail: (detailName: string, value: any) => void;
  fetch: (sendOffset: boolean) => any;
  setReport: (response: CustomerSummaryReportResponse) => void;
}

const currentYear: number = new Date().getFullYear();

// Customer Summary Report
export const customerSummaryReport = create<CustomerSummaryReportDetails>(
  (set, get) => ({
    selectedClients: undefined,
    minimumAmount: 0,
    maximumAmount: 0,
    yearFrom: currentYear,
    yearTill: currentYear,
    category: 0,
    report: {} as AttributeType,
    __offset: 0,
    setDetail: (detailName: string, value: any) => {
      if (detailName === "selectedClients") set({ selectedClients: [value] });
      else if (detailName === "minimumAmount") set({ minimumAmount: value });
      else if (detailName === "maximumAmount") set({ maximumAmount: value });
      else if (detailName === "yearFrom") set({ yearFrom: value });
      else if (detailName === "yearTill") set({ yearTill: value });
      else if (detailName === "category") set({ category: value });
    },
    fetch: async (sendOffset: boolean) => {
      let payload: AttributeType = {
        selectedClients: get().selectedClients,
        minimumAmount: get().minimumAmount,
        maximumAmount: get().maximumAmount,
        yearFrom: get().yearFrom,
        yearTill: get().yearTill,
        category: get().category,
        __offset: sendOffset ? get().__offset : 0,
      };

      return await httpService.fetch<
        AttributeType<CustomerSummaryReportResponse>
      >(payload, "customer_summary");
    },
    setReport: (response: CustomerSummaryReportResponse) => {
      set({ report: response.report });
      set({ __offset: response.__offset });
    },
  })
);
