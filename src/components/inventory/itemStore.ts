import { create } from "zustand";
import { HTTPService } from "../../service/api-client";
import {
  AttributeType,
  ITEM_DETAILS_TAG,
  MODE_PARTS,
  systemConfigMode,
} from "../../shared/config";
import { ProfitMarginIndex } from "./profitMarginStore";
import { ItemDetailsForTransactions } from "../accounting/transactions/store";

// Http instance
const httpService: HTTPService = new HTTPService();

// Store ID
const storeId = parseInt(localStorage.getItem("storeId") || "-1");

/**
 * Accounts
 */
export interface Account {
  assets?: number;
  revenue?: number;
  cogs?: number;
  variance?: number;
  expense?: number;
}

/**
 * Item Prices
 */
export interface Prices {
  storeId: number;
  sellingPrice: number;
  preferredPrice: number;
  buyingCost: number;
}

/**
 * Item Details
 */
export interface ItemDetails {
  id: number | null;
  identifier: string;
  description: string;
  oem: string;
  quantity: number;
  quantitiesAllStores: AttributeType;
  unit: string;
  reorderQuantity: { [storeId: number]: number };
  initialQuantity?: number;
  prices: { [storeId: number]: Prices };
  profitMargins: ProfitMarginIndex;
  memo: string;
  additionalInformation: string;
  category: number;
  isInactive: { [storeId: number]: number };
  isDiscountDisabled: { [storeId:number]: number };
  isCore: number;
  account: Account;
  storeId: number;
  lastModifiedTimestamp: string;
  lastSold: string;
}

/**
 * Item Store.
 */
interface ItemStore extends ItemDetails {
  setField: (
    fieldName:
      | "id"
      | "identifier"
      | "description"
      | "oem"
      | "quantity"
      | "unit"
      | "reorderQuantity"
      | "initialQuantity"
      | "sellingPrice"
      | "preferredPrice"
      | "buyingCost"
      | "profitMargins"
      | "memo"
      | "additionalInformation"
      | "category"
      | "isInactive"
      | "isDiscountDisabled"
      | "isCore"
      | "accountAssets"
      | "accountRevenue"
      | "accountCOGS"
      | "accountVariance"
      | "accountExpense",
    value: any
  ) => void;
  setDetails: (details: ItemDetails) => void;
  fetch: (searchTerm: string, excludeInactive?: boolean) => any;
  fetchItemDetailsForTransactions: (searchTerm: string, storeId: any) => any;
  add: () => any;
  update: () => any;
  reset: () => any;
  prependItemTag: () => void;
}

/**
 * Item Store Instance.
 */
export const itemStore = create<ItemStore>((set, get) => ({
  id: null,
  identifier: "",
  description: "",
  oem: "",
  quantity: 0,
  quantitiesAllStores: {},
  unit: "Each",
  reorderQuantity: {},
  initialQuantity: 0,
  prices: {},
  profitMargins: {} as ProfitMarginIndex,
  memo: "",
  additionalInformation: "",
  category: systemConfigMode === MODE_PARTS ? 1 : 0,
  isCore: 0,
  isInactive: {},
  isDiscountDisabled: {},
  storeId: storeId,
  account: {
    assets: systemConfigMode === MODE_PARTS ? 1520 : 0,
    revenue: systemConfigMode === MODE_PARTS ? 4020 : 0,
    cogs: systemConfigMode === MODE_PARTS ? 5020 : 0,
    variance: systemConfigMode === MODE_PARTS ? 5100 : 0,
    expense: 0,
  },
  lastModifiedTimestamp: "",
  lastSold: "",
  prependItemTag: () => {
    let identifier: string = ITEM_DETAILS_TAG + get().identifier;
    let description: string = ITEM_DETAILS_TAG + get().description;
    set({ identifier: identifier });
    set({ description: description });
  },
  setField: (
    fieldName:
      | "id"
      | "identifier"
      | "description"
      | "oem"
      | "quantity"
      | "quantitiesAllStores"
      | "unit"
      | "reorderQuantity"
      | "initialQuantity"
      | "sellingPrice"
      | "preferredPrice"
      | "buyingCost"
      | "profitMargins"
      | "memo"
      | "additionalInformation"
      | "category"
      | "isInactive"
      | "isDiscountDisabled"
      | "isCore"
      | "accountAssets"
      | "accountRevenue"
      | "accountCOGS"
      | "accountVariance"
      | "accountExpense",
    value: any
  ) => {
    if (fieldName === "id") set({ id: value });
    if (fieldName === "identifier") set({ identifier: value });
    if (fieldName === "description") set({ description: value });
    if (fieldName === "oem") set({ oem: value });
    if (fieldName === "quantity") set({ quantity: value });
    if (fieldName === "quantitiesAllStores")
      set({ quantitiesAllStores: value });
    if (fieldName === "unit") set({ unit: value });
    if (fieldName === "initialQuantity") set({ initialQuantity: value });
    if (fieldName === "reorderQuantity") {
      let reorderQuantity = get().reorderQuantity;
      reorderQuantity[get().storeId] = parseInt(value);
      set({ reorderQuantity: reorderQuantity });
    }
    if (fieldName === "sellingPrice") {
      let temp = get().prices;
      let storeId = get().storeId;
      if (!(storeId in temp)) {
        temp[storeId] = {
          storeId: storeId,
          sellingPrice: 0,
          preferredPrice: 0,
          buyingCost: 0,
        };
      }
      temp[storeId].sellingPrice = parseFloat(value);
      set({ prices: temp });
    }
    if (fieldName === "preferredPrice") {
      let temp = get().prices;
      let storeId = get().storeId;
      if (!(storeId in temp)) {
        temp[storeId] = {
          storeId: storeId,
          sellingPrice: 0,
          preferredPrice: 0,
          buyingCost: 0,
        };
      }
      temp[storeId].preferredPrice = parseFloat(value);
      set({ prices: temp });
    }
    if (fieldName === "buyingCost") {
      let temp = get().prices;
      let storeId = get().storeId;
      if (!(storeId in temp)) {
        temp[storeId] = {
          storeId: storeId,
          sellingPrice: 0,
          preferredPrice: 0,
          buyingCost: 0,
        };
      }
      temp[storeId].buyingCost = parseFloat(value[0]);
      temp[storeId].sellingPrice = parseFloat(value[1]);
      set({ prices: temp });
    }
    if (fieldName === "profitMargins") set({ profitMargins: value });
    if (fieldName === "memo") set({ memo: value });
    if (fieldName === "additionalInformation")
      set({ additionalInformation: value });
    if (fieldName === "isInactive") {
      let is_inactive = get().isInactive;
      is_inactive[get().storeId] = value;
      set({ isInactive: is_inactive });
    }
    if (fieldName === "isDiscountDisabled") {
      let isDiscountDisabled =  get().isDiscountDisabled;
      isDiscountDisabled[get().storeId] = value;
      set({isDiscountDisabled: isDiscountDisabled});
    }
    if (fieldName === "isCore") set({ isCore: value });
    if (fieldName === "accountAssets") {
      let account: Account = get().account;
      account.assets = value;
      set({ account: account });
    }
    if (fieldName === "accountRevenue") {
      let account: Account = get().account;
      account.revenue = value;
      set({ account: account });
    }
    if (fieldName === "accountCOGS") {
      let account: Account = get().account;
      account.cogs = value;
      set({ account: account });
    }
    if (fieldName === "accountVariance") {
      let account: Account = get().account;
      account.variance = value;
      set({ account: account });
    }
    if (fieldName === "accountExpense") {
      let account: Account = get().account;
      account.expense = value;
      set({ account: account });
    }
  },
  setDetails: (details: ItemDetails) => {
    set({ id: details.id });
    set({ identifier: details.identifier });
    set({ description: details.description });
    set({ oem: details.oem });
    set({ quantity: details.quantity });
    set({ unit: details.unit });
    set({ profitMargins: details.profitMargins });
    set({ reorderQuantity: details.reorderQuantity });
    set({ prices: details.prices });
    set({ memo: details.memo });
    set({ additionalInformation: details.additionalInformation });
    set({ isInactive: details.isInactive });
    set({isDiscountDisabled: details.isDiscountDisabled});
    set({ isCore: details.isCore });
    set({ account: details.account });
    set({ quantitiesAllStores: details.quantitiesAllStores });
    set({ lastModifiedTimestamp: details.lastModifiedTimestamp });
    set({ lastSold: details.lastSold });
  },
  fetch: async (searchTerm: string, excludeInactive: boolean = false) => {
    let payload = {
      term: ITEM_DETAILS_TAG + searchTerm,
      exclude_inactive: excludeInactive ? 1 : 0,
    };
    return await httpService.fetch<ItemDetails[]>(payload, "inv_fetch");
  },
  fetchItemDetailsForTransactions: async (searchTerm: string, storeId: any) => {
    let payload = {
      search_term: ITEM_DETAILS_TAG + searchTerm,
      store_id: storeId,
    };
    return await httpService.fetch<ItemDetailsForTransactions[]>(
      payload,
      "inv_item_details_for_transactions"
    );
  },
  add: async () => {
    get().prependItemTag();
    return await httpService.add(get(), "inv_add");
  },
  update: async () => {
    get().prependItemTag();
    return await httpService.update(get(), "inv_update");
  },
  reset: () => {
    set({ id: null });
    set({ identifier: "" });
    set({ description: "" });
    set({ oem: "" });
    set({ quantity: 0 });
    set({ quantitiesAllStores: {} });
    set({ unit: "Each" });
    set({ reorderQuantity: {} });
    set({ initialQuantity: 0 });
    set({ prices: {} });
    set({ profitMargins: {} as ProfitMarginIndex });
    set({ memo: "" });
    set({ additionalInformation: "" });
    set({ isCore: 0 });
    set({ isInactive: {} });
    set({ lastModifiedTimestamp: "" });
    set({ lastSold: "" });
  },
}));
