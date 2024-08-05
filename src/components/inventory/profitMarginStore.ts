import { create } from "zustand";
import { HTTPService } from "../../service/api-client";
import { decrypt, encrypt } from "../../shared/functions";

// Http instance
const httpService = new HTTPService();

/** Profit Margin Index Type */
export type ProfitMarginIndex = { [itemIdentifierPrefix: string]: number };

/* Profit Margins Response */
export interface ProfitMarginsResponse {
  profitMargins: ProfitMarginIndex;
  lastModifiedTimestamp: string | undefined;
}

/**
 * Profit Margin Store
 */
interface ProfitMarginsStore {
  profitMargins: ProfitMarginIndex;
  setField: (prefix: string, margin: number) => void;
  set: () => any;
}

// Instance
export const profitMarginStore = create<ProfitMarginsStore>((set, get) => ({
  profitMargins: {} as ProfitMarginIndex,
  setField: (prefix: string, margin: number) => {
    let _profitMargins = get().profitMargins;
    _profitMargins[prefix] = margin;
    set({ profitMargins: _profitMargins });
  },
  set: async () => {
    let response = await httpService.update(get(), "inv_update_profit_margins");
    if (response.status === true) {
      setProfitMargins(get().profitMargins);
    }
  },
}));

/**
 * This method will set price margins.
 * @param priceMargins Price Margins
 * @param lastModifiedTimestamp
 */
export const setProfitMargins = (
  priceMargins: ProfitMarginIndex,
  lastModifiedTimestamp: string | undefined = undefined
) => {
  try {
    let cipherText = encrypt(
      JSON.stringify(priceMargins),
      localStorage.getItem("cipherKeyThisStore") || ""
    );
    if (cipherText !== null) {
      localStorage.setItem("profitMargins", cipherText);

      if (lastModifiedTimestamp) {
        localStorage.setItem(
          "profitMarginsLastModifiedTimestamp",
          lastModifiedTimestamp
        );
      }
    } else throw new Error("Unable to Encrypt Profit Margins.");
  } catch (e) {
    console.error(e);
    localStorage.setItem("profitMargins", "null");
    localStorage.setItem("profitMarginsLastModifiedTimestamp", "null");
  }
};

/**
 * This method will get the profit margins.
 * @returns
 */
export const getProfitMargins = (): ProfitMarginIndex => {
  try {
    // DO NOT CHANGE THE TYPE of "null" to null
    let cipherText = localStorage.getItem("profitMargins") || "null";
    if (cipherText != "null") {
      let plainText = decrypt(
        cipherText,
        localStorage.getItem("cipherKeyThisStore") || ""
      );
      if (plainText !== null) return JSON.parse(plainText);
    }
    throw new Error("Unable to fetch Profit Margins.");
  } catch (e) {
    return {};
  }
};

export default profitMarginStore;
