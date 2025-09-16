import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";

// Income Statement
interface IncomeStatement {
  chartData: { [storeId: number]: any };
  statement: { [accountNumber: number]: number };
  summaryOfAllStores: {[param: string] : number};
}

/**
 * Filter Store Details
 */
interface FilterStoreDetails {
  startDate: Date;
  endDate: Date;
  selectedStores: { [storeId: number]: number };
  data: IncomeStatement;
}

interface FilterStoreDetailsStore extends FilterStoreDetails {
  fetch: () => any;
  setDate: (_type: string, value: Date) => void;
  setData: (data: any) => void;
}

// Http Service
const httpService = new HTTPService();

// Filter Store
export const filterStore = create<FilterStoreDetailsStore>((set, get) => ({
  startDate: new Date(),
  endDate: new Date(),
  selectedStores: {},
  data: {} as IncomeStatement,
  fetch: async () => {
    let payload = JSON.parse(JSON.stringify(get()));
    return await httpService.fetch<FilterStoreDetails>(
      payload,
      "fetch_income_statement"
    );
  },
  setDate: (_type: string, value: Date) => {
    if (_type === "startDate") set({ startDate: value });
    else if (_type === "endDate") set({ endDate: value });
  },
  setData: (data: any) => {
    set({ data: data });
  },
}));
