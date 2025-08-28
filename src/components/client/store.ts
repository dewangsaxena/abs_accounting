import { create } from "zustand";
import { HTTPService } from "../../service/api-client";
import { Prices } from "../inventory/itemStore";
import { DEFAULT_PROFIT_MARGIN_KEY } from "../../shared/config";
import { ProfitMarginIndex } from "../inventory/profitMarginStore";

// Http Service
const httpService = new HTTPService();

// Default Standard Profit Margin
let defaultStandardProfitMargin: ProfitMarginIndex = {} as ProfitMarginIndex;
defaultStandardProfitMargin[DEFAULT_PROFIT_MARGIN_KEY] = 0;

/**
 * Address
 */
export interface Address {
  name: string;
  contactName: string;
  street1: string;
  street2: string;
  city: string;
  postalCode: string;
  province: string;
  phoneNumber1: string;
  phoneNumber2: string;
  fax: string;
  emailId: string;
  country: number;
}

/**
 * Item Details for Client.
 */
export interface ItemDetailsForClient extends Prices {
  identifier?: string;
  description?: string;
}

/**
 * Client Details
 */
export interface ClientDetails {
  id: number | null;
  isInactive: number;
  primaryDetails: Address;
  shippingAddresses: Address;
  isDefaultShippingAddress: number;
  clientSince: Date;
  standardDiscount: number;
  standardProfitMargins: ProfitMarginIndex;
  creditLimit: number;
  amountOwing: number;
  earlyPaymentDiscount: number;
  earlyPaymentPaidWithinDays: number;
  netAmountDueWithinDays: number;
  defaultPaymentMethod: 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;
  defaultReceiptPaymentMethod: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;
  produceStatementForClient: number;
  disableFederalTaxes: number;
  disableProvincialTaxes: number;
  additionalEmailAddresses: string;
  category: number;
  memo: string;
  additionalInformation: string;
  disableCreditTransactions: number;
  initial: any;
  nameHistory: Address[];
  primaryDetailsHistory?: Address;
  lastModifiedTimestamp: string;
  salesInvoices?: { [invoiceId: number]: any };
  isSelfClient: number;
  customSellingPriceForItems: {
    [storeId: number]: { [itemId: number]: ItemDetailsForClient };
  };
  lastPurchaseDate: string;
  enforceSelfClientPriceLock: number;
  paymentCurrency: string;
  exchangeRateCADToUSD: number;
}

/**
 * Client Store.
 */
interface ClientStore extends ClientDetails {
  setField: (detailName: string, value: any) => void;
  setDetails: (details: ClientDetails) => void;
  fetch: (
    searchTerm: string,
    excludeInactive?: boolean,
    fetchInvoices?: boolean
  ) => any;
  add: () => any;
  update: () => any;
  reset: () => any;
}

/**
 * Client Store instance
 */
export const clientStore = create<ClientStore>((set, get) => ({
  id: null,
  isInactive: 0,
  primaryDetails: {
    name: "",
    contactName: "",
    street1: "",
    street2: "",
    city: "",
    postalCode: "",
    province: "",
    phoneNumber1: "",
    phoneNumber2: "",
    fax: "",
    emailId: "",
    country: 124,
  },
  shippingAddresses: {
    name: "",
    contactName: "",
    street1: "",
    street2: "",
    city: "",
    postalCode: "",
    province: "",
    phoneNumber1: "",
    phoneNumber2: "",
    fax: "",
    emailId: "",
    country: 124,
  },
  isDefaultShippingAddress: 1,
  clientSince: new Date(),
  standardDiscount: 0,
  standardProfitMargins: defaultStandardProfitMargin,
  amountOwing: 0,
  creditLimit: 0,
  earlyPaymentDiscount: 0,
  earlyPaymentPaidWithinDays: 0,
  netAmountDueWithinDays: 0,
  defaultPaymentMethod: 0,
  defaultReceiptPaymentMethod: 8,
  produceStatementForClient: 1,
  disableFederalTaxes: 0,
  disableProvincialTaxes: 0,
  additionalEmailAddresses: "",
  category: 1,
  memo: "",
  additionalInformation: "",
  disableCreditTransactions: 0,
  initial: {} as any,
  nameHistory: [] as any,
  lastModifiedTimestamp: "",
  isSelfClient: 0,
  customSellingPriceForItems: {},
  lastPurchaseDate: "",
  enforceSelfClientPriceLock: 1,
  paymentCurrency: "CAD",
  exchangeRateCADToUSD: 0,
  setField: (detailName: string, value: any) => {
    if (detailName === "id") set({ id: value });
    /* Primary Details */ else if (detailName === "primaryClientName") {
      set({ primaryDetails: { ...get().primaryDetails, name: value } });
    } else if (detailName === "primaryContactName") {
      set({
        primaryDetails: { ...get().primaryDetails, contactName: value },
      });
    } else if (detailName === "primaryStreet1") {
      set({ primaryDetails: { ...get().primaryDetails, street1: value } });
    } else if (detailName === "primaryStreet2") {
      set({ primaryDetails: { ...get().primaryDetails, street2: value } });
    } else if (detailName === "primaryCity") {
      set({ primaryDetails: { ...get().primaryDetails, city: value } });
    } else if (detailName === "primaryPostalCode") {
      set({
        primaryDetails: { ...get().primaryDetails, postalCode: value },
      });
    } else if (detailName === "primaryProvince") {
      set({ primaryDetails: { ...get().primaryDetails, province: value } });
    } else if (detailName === "primaryPhone1") {
      set({
        primaryDetails: { ...get().primaryDetails, phoneNumber1: value },
      });
    } else if (detailName === "primaryPhone2") {
      set({
        primaryDetails: { ...get().primaryDetails, phoneNumber2: value },
      });
    } else if (detailName === "primaryFax") {
      set({ primaryDetails: { ...get().primaryDetails, fax: value } });
    } else if (detailName === "primaryEmailId") {
      set({ primaryDetails: { ...get().primaryDetails, emailId: value } });
    } else if (detailName === "primaryCountry") {
      set({ primaryDetails: { ...get().primaryDetails, country: value } });
    } else if (detailName === "isDefaultShippingAddress")
      set({ isDefaultShippingAddress: value });
    else if (detailName === "shippingClientName") {
      /* Shipping Details */
      set({ shippingAddresses: { ...get().shippingAddresses, name: value } });
    } else if (detailName === "shippingContactName") {
      set({
        shippingAddresses: {
          ...get().shippingAddresses,
          contactName: value,
        },
      });
    } else if (detailName === "shippingStreet1") {
      set({
        shippingAddresses: { ...get().shippingAddresses, street1: value },
      });
    } else if (detailName === "shippingStreet2") {
      set({
        shippingAddresses: { ...get().shippingAddresses, street2: value },
      });
    } else if (detailName === "shippingCity") {
      set({ shippingAddresses: { ...get().shippingAddresses, city: value } });
    } else if (detailName === "shippingPostalCode") {
      set({
        shippingAddresses: { ...get().shippingAddresses, postalCode: value },
      });
    } else if (detailName === "shippingProvince") {
      set({
        shippingAddresses: { ...get().shippingAddresses, province: value },
      });
    } else if (detailName === "shippingPhone1") {
      set({
        shippingAddresses: {
          ...get().shippingAddresses,
          phoneNumber1: value,
        },
      });
    } else if (detailName === "shippingPhone2") {
      set({
        shippingAddresses: {
          ...get().shippingAddresses,
          phoneNumber2: value,
        },
      });
    } else if (detailName === "shippingFax") {
      set({ shippingAddresses: { ...get().shippingAddresses, fax: value } });
    } else if (detailName === "shippingEmailId") {
      set({
        shippingAddresses: { ...get().shippingAddresses, emailId: value },
      });
    } else if (detailName === "shippingCountry") {
      set({
        shippingAddresses: { ...get().shippingAddresses, country: value },
      });
    } else if (detailName === "standardDiscount")
      /* Other Details */
      set({ standardDiscount: value });
    else if (detailName === "creditLimit") set({ creditLimit: value });
    else if (detailName === "produceStatementForClient")
      set({ produceStatementForClient: value });
    else if (detailName === "disableFederalTaxes")
      set({ disableFederalTaxes: value });
    else if (detailName === "disableProvincialTaxes")
      set({ disableProvincialTaxes: value });
    else if (detailName === "disableCreditTransactions")
      set({ disableCreditTransactions: value });
    else if (detailName === "earlyPaymentDiscount")
      set({ earlyPaymentDiscount: value });
    else if (detailName === "earlyPaymentWithinDays")
      set({ earlyPaymentPaidWithinDays: value });
    else if (detailName === "netAmountDueWithinDays")
      set({ netAmountDueWithinDays: value });
    else if (detailName === "defaultPaymentMethod")
      set({ defaultPaymentMethod: value });
    else if (detailName === "defaultReceiptPaymentMethod")
      set({ defaultReceiptPaymentMethod: value });
    else if (detailName === "additionalEmailAddresses")
      set({ additionalEmailAddresses: value });
    else if (detailName === "memo") {
      set({ memo: value });
    } else if (detailName === "additionalInformation") {
      set({ additionalInformation: value });
    } else if (detailName === "category") set({ category: value });
    else if (detailName === "clientSince") set({ clientSince: value });
    else if (detailName === "isInactive") set({ isInactive: value });
    else if (detailName === "salesInvoices") set({ salesInvoices: value });
    else if (detailName === "paymentCurrency") set({ paymentCurrency: value });
    else if (detailName === "exchangeRateCADToUSD") set({ exchangeRateCADToUSD: value });
    else if (detailName == "customSellingPriceForItems") {
      set({ customSellingPriceForItems: value });
    } else if (detailName === "standardProfitMargin") {
      let standardProfitMargins = get().standardProfitMargins;
      if (Object.keys(standardProfitMargins).length === 0)
        standardProfitMargins = {} as ProfitMarginIndex;
      let itemIdentifierPrefix: string = value["prefix"];
      let margin: number = value["margin"];
      if (isNaN(margin)) margin = 0;
      standardProfitMargins[itemIdentifierPrefix] = margin;
      set({ standardProfitMargins: standardProfitMargins });
    } else if (detailName === "deleteProfitMargin") {
      let standardProfitMargins = get().standardProfitMargins;
      delete standardProfitMargins[value];
      set({ standardProfitMargins: standardProfitMargins });
    }
  },
  setDetails: (details: ClientDetails) => {
    set({ id: details.id });
    set({ isInactive: details.isInactive });
    set({ primaryDetails: details.primaryDetails });
    set({ shippingAddresses: details.shippingAddresses });
    set({ isDefaultShippingAddress: details.isDefaultShippingAddress });
    set({ clientSince: new Date(details.clientSince) });
    set({ standardDiscount: details.standardDiscount });
    set({ creditLimit: details.creditLimit });
    set({ amountOwing: details.amountOwing });
    set({ earlyPaymentDiscount: details.earlyPaymentDiscount });
    set({
      earlyPaymentPaidWithinDays: details.earlyPaymentPaidWithinDays,
    });
    set({ netAmountDueWithinDays: details.netAmountDueWithinDays });
    set({ defaultPaymentMethod: details.defaultPaymentMethod });
    set({
      defaultReceiptPaymentMethod: details.defaultReceiptPaymentMethod,
    });
    set({ produceStatementForClient: details.produceStatementForClient });
    set({ disableFederalTaxes: details.disableFederalTaxes });
    set({ disableProvincialTaxes: details.disableProvincialTaxes });
    set({ additionalEmailAddresses: details.additionalEmailAddresses });
    set({ category: details.category });
    set({ memo: details.memo });
    set({ additionalInformation: details.additionalInformation });
    set({ disableCreditTransactions: details.disableCreditTransactions });
    set({ initial: details.initial });
    set({ nameHistory: details.nameHistory });
    set({ primaryDetailsHistory: details.primaryDetails });
    let standardProfitMargins = details.standardProfitMargins;
    set({ standardProfitMargins: standardProfitMargins });
    set({ lastModifiedTimestamp: details.lastModifiedTimestamp });
    set({ isSelfClient: details.isSelfClient });
    set({ customSellingPriceForItems: details.customSellingPriceForItems });
    set({ lastPurchaseDate: details.lastPurchaseDate });
    set({ enforceSelfClientPriceLock: details.enforceSelfClientPriceLock });
    set({ paymentCurrency: details.paymentCurrency });
    set({ exchangeRateCADToUSD: details.exchangeRateCADToUSD });
  },
  fetch: async (
    searchTerm: string,
    excludeInactive: boolean = true,
  ) => {
    let payload = {
      term: searchTerm,
      exclude_inactive: excludeInactive ? 1 : 0,
    };
    return await httpService.fetch<ClientDetails[]>(payload, "client_fetch");
  },
  add: async () => {
    return await httpService.add(get(), "client_add");
  },
  update: async () => {
    return await httpService.update(get(), "client_update");
  },
  reset: () => {
    set({ id: null });
    set({ isInactive: 0 });
    set({
      primaryDetails: {
        name: "",
        contactName: "",
        street1: "",
        street2: "",
        city: "",
        postalCode: "",
        province: "",
        phoneNumber1: "",
        phoneNumber2: "",
        fax: "",
        emailId: "",
        country: 124,
      },
    });
    set({
      shippingAddresses: {
        name: "",
        contactName: "",
        street1: "",
        street2: "",
        city: "",
        postalCode: "",
        province: "",
        phoneNumber1: "",
        phoneNumber2: "",
        fax: "",
        emailId: "",
        country: 124,
      },
    });

    set({ isDefaultShippingAddress: 1 });
    set({ clientSince: new Date() });
    set({ standardDiscount: 0 });
    set({ standardProfitMargins: defaultStandardProfitMargin });
    set({ amountOwing: 0 });
    set({ creditLimit: 0 });
    set({ earlyPaymentDiscount: 0 });
    set({ earlyPaymentPaidWithinDays: 0 });
    set({ netAmountDueWithinDays: 0 });
    set({ defaultPaymentMethod: 0 });
    set({ defaultReceiptPaymentMethod: 8 });
    set({ produceStatementForClient: 1 });
    set({ disableFederalTaxes: 0 });
    set({ disableProvincialTaxes: 0 });
    set({ additionalEmailAddresses: "" });
    set({ category: 1 });
    set({ memo: "" });
    set({ additionalInformation: "" });
    set({ disableCreditTransactions: 0 });
    set({ initial: {} as any });
    set({ nameHistory: [] as any });
    set({ lastModifiedTimestamp: "" });
    set({ isSelfClient: 0 });
    set({ customSellingPriceForItems: {} as any });
    set({ enforceSelfClientPriceLock: 1});
  },
}));
