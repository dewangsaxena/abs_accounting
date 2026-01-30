import uuid from "react-native-uuid";

/**
 * This module defines shared functions.
 */
import { ProfitMarginIndex } from "../components/inventory/profitMarginStore";
import { DEFAULT_PROFIT_MARGIN_KEY } from "./config";
import CryptoJS from "crypto-js";
import { ItemDetailsForTransactions } from "../components/accounting/transactions/store";
import { ItemDetails } from "../components/inventory/itemStore";
import { ClientDetails } from "../components/client/store";
import { InventoryResponseObject } from "../components/inventory/AdjustInventory";

/**
 * This method will check for active session.
 * @returns bool
 */
export const isSessionActive = () => {
  return localStorage.getItem("isSessionActive") !== null;
};

/**
 * Check for Valid Session.
 */
export const redirectIfInvalidSession = () => {
  if (isSessionActive() === false) {
    window.location.href = "/login";
  }
};

/**
 * Format Number
 * @param number
 * @param precision
 * @returns number
 */
export const formatNumber = (
  number: number,
  precision: number | undefined = undefined
) => {
  if (precision === undefined) precision = 4;
  return new Intl.NumberFormat("en-CA", {
    maximumFractionDigits: precision,
  }).format(number);
};

/**
 * This method will format with decimal places.
 * @param number
 * @param precision
 * @returns number
 */
export const formatNumberWithDecimalPlaces = (
  number: number,
  precision: number | undefined = undefined
) => {
  // Set to Zero.
  if (isNaN(number)) number = 0;
  let addAddTrailingText = number == parseInt(number.toString());
  let formattedNumber = formatNumber(number, precision);
  if (addAddTrailingText === false) {
    let parts = formattedNumber.split(".");
    if (parts !== undefined) {
      if (parts.length === 2 && parts[1].length == 1) parts[1] = `${parts[1]}0`;
      return parts.join(".");
    } else return formattedNumber;
  } else {
    let trailingText = addAddTrailingText ? ".00" : "";
    return formattedNumber + trailingText;
  }
};

/**
 * This method will return attribute from Session.
 * @param attrName
 * @returns
 */
export const getAttributeFromSession = (attrName: string): any => {
  let sessionData: any = localStorage.getItem("sessionData");
  if (sessionData !== null && sessionData !== undefined) {
    try {
      let storeId: any = localStorage.getItem("storeId");
      if (storeId) storeId = parseInt(storeId);
      let decryptedData: any = decrypt(
        sessionData,
        localStorage.getItem("cipherKeyThisStore") || ""
      );

      /* Convert to Object from Json */
      decryptedData = JSON.parse(decryptedData);
      return decryptedData[attrName];
    } catch (e) {
      return null;
    }
  }
};

/**
 * This method will encrypt plaintext.
 * @param plainText
 * @param cipherKey
 * @returns
 */
export const encrypt = function (
  plainText: string,
  cipherKey: string
): string | null {
  try {
    return CryptoJS.AES.encrypt(plainText, cipherKey).toString();
  } catch (e) {
    console.error(e);
    return null;
  }
};

/**
 * This method will decrypt cipher text.
 * @param cipherText
 * @param cipherKey
 * @returns
 */
export const decrypt = function (
  cipherText: string,
  cipherKey: string
): string | null {
  try {
    return CryptoJS.AES.decrypt(cipherText, cipherKey).toString(
      CryptoJS.enc.Utf8
    );
  } catch (e) {
    console.error(e);
    return null;
  }
};

/**
 * This method format the number upto to 2 fixed decimal places by default.
 * @param number
 * @param precision
 * @returns
 */
export const toFixed = (number: number, precision: number = 2): number => {
  return parseFloat(number.toFixed(precision));
};

/**
 * This method will show Toast Instance.
 * @param toastInstance
 * @param status
 * @param message
 */
export const showToast = (
  toastInstance: any,
  status: boolean,
  message?: string,
  title?: string
) => {
  if (status === true && (message === undefined || message.length === 0))
    message = "Action Processed Successfully.";

  if (title === undefined || title.length === 0) {
    title = status ? "Successful" : "Error";
  }
  toastInstance({
    title: title,
    description: message,
    status: status ? "success" : "error",
    duration: status ? 2000 : 10000,
    variant: "left-accent",
    isClosable: true,
  });
};

/**
 * This method will return the UUID.
 * @returns UUID
 */
export const getUUID = (): string => {
  return uuid.v4() as string;
};

/**
 * Get Profit Margin Of Item Per Client.
 * @param profitMargins
 * @param itemIdentifier
 * @return
 */
export const getProfitMarginByItemIdentifierPrefix = (
  profitMargins: ProfitMarginIndex,
  itemIdentifier: string | number
): number => {
  itemIdentifier = itemIdentifier.toString().toUpperCase().trim();
  let prefixes = Object.keys(profitMargins);
  let noOfPrefixes: number = prefixes.length;
  let __profitMargin: number | null = null;

  // Store matching prefixes for Detecting Clashes
  let matchingPrefixes: string[] = [];
  for (let i = 0; i < noOfPrefixes; ++i) {
    if (itemIdentifier.startsWith(prefixes[i])) {
      __profitMargin = profitMargins[prefixes[i]];
      matchingPrefixes.push(prefixes[i]);
    }
  }

  // In case of matching prefixes
  // Always select the one with the largest length
  if (matchingPrefixes.length > 1) {
    let maxLength: number = 0;
    let index: number = 0;
    let count: number = matchingPrefixes.length;
    for (let i = 0; i < count; ++i) {
      if (matchingPrefixes[i].length > maxLength) {
        maxLength = matchingPrefixes[i].length;
        index = i;
      }
    }
    __profitMargin = profitMargins[matchingPrefixes[index]];
  }

  // Send DEFAULT Profit Margin
  if (__profitMargin === null)
    __profitMargin = profitMargins[DEFAULT_PROFIT_MARGIN_KEY];

  return __profitMargin;
};

/**
 * This method calculate COGS Margin.
 * @param price
 * @param cogsMargin
 * @returns
 */
export const calculateCOGSMarginByMargin = (
  price: number,
  cogsMargin: number
): number => {
  if (cogsMargin <= 0) {
    return -1;
  }
  let margin = (price * cogsMargin) / 100;
  return price + margin;
};

/**
 * This method will calculate profit margin.
 * @param sellingPrice
 * @param buyingCost
 * @returns
 */
export const calculateProfitMargin = (
  sellingPrice: number,
  buyingCost: number
): number => {
  let grossMargin = sellingPrice - Math.abs(buyingCost);
  grossMargin = grossMargin / sellingPrice;
  return toFixed(grossMargin * 100);
};

/**
 * This method will calculate COGS margin.
 * @param sellingPrice
 * @param buyingCost
 */
export const calculateCOGSMargin = (
  sellingPrice: number,
  buyingCost: number
): number => {
  let x = sellingPrice / Math.abs(buyingCost);
  if (x >= 1) x = x - 1;
  return toFixed(x * 100);
};

/**
 * This method will return profit margin color scheme.
 * @param profitMargin
 * @returns
 */
export const getProfitMarginColorScheme = (profitMargin: number) => {
  if (profitMargin < 0) return "red";
  else if (profitMargin > 0 && profitMargin <= 15) return "#FFBF00";
  else if (profitMargin > 15 && profitMargin < 25) return "#009E60";
  else if (profitMargin >= 25) return "#00FF7F";
  else return "#E5E4E2";
};

/**
 * This method will build search text options.
 * @param data
 * @param showQuantity
 * @returns search list
 */
export const buildSearchListForItem = (
  data:
    | ItemDetails[]
    | ItemDetailsForTransactions[]
    | InventoryResponseObject[]
    | undefined,
  showQuantity: boolean = false
): any => {
  if (data === undefined) return null;
  let newOptions: any = [];
  let length = data.length;
  let label: string = "";
  let storeId: number = parseInt(localStorage.getItem("storeId") || "-1");
  for (let i = 0; i < length; ++i) {
    label = `${data[i].identifier} ● ${data[i].description}`;

    // Add OEM if Exists
    // @ts-ignore
    if ((data[i]?.oem?.length || 0) > 0) label += ` ● ${data[i].oem}`;

    // Show Quantity Flag
    if (showQuantity) label += ` ● ${data[i].quantity}`;

    // @ts-ignore
    if (data[i].isInactive && data[i].isInactive[storeId])
      label += ` ● (INACTIVE)`;
    newOptions.push({
      label: label,
      value: data[i],
    });
  }
  return newOptions;
};

/**
 * This method will build search text options.
 * @param data
 * @returns search list
 */
export const buildSearchListForClient = (
  data: ClientDetails[] | undefined
): any => {
  if (data === undefined) return null;
  let newOptions = [];
  let length = data.length;
  let clientDetails: ClientDetails;
  let contactName: string = "";
  let statusTag: string = "";
  for (let i = 0; i < length; ++i) {
    contactName = "";
    statusTag = "";
    clientDetails = data[i];
    if (
      clientDetails.primaryDetails.contactName &&
      clientDetails.primaryDetails.contactName.toString().trim().length > 0
    ) {
      contactName = ` ● ${clientDetails.primaryDetails.contactName}`;
    }

    if (clientDetails.isInactive) statusTag = ` ● (INACTIVE)`;
    newOptions.push({
      label: `${clientDetails.primaryDetails.name}${contactName}${statusTag}`,
      value: clientDetails,
    });
  }
  return newOptions;
};

/**
 * This method will calculate tax by rate.
 * @param amount 
 * @param taxRate 
 * @returns 
 */
export const calculateTaxByRate = (amount: number, taxRate:number) : number => {
  let temp: number = amount * taxRate;
  return temp / 100;
}