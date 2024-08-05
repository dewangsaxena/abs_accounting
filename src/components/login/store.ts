import { create } from "zustand";
import { HTTPService } from "../../service/api-client";
import { ProfitMarginsResponse } from "../inventory/profitMarginStore";

/**
 * Store Details
 */
interface StoreDetails {
  id: number;
  location: string;
  businessName: string;
  gstHSTTaxRate: number;
  pstTaxRate: number;
  cipherKeyThisStore: string;
}

/**
 * User Details
 */
export interface LoginDetails {
  id: number | null;
  name: string;
  accessLevel: number;
  hasAccess: number;
  csrfToken: string;
  profitMargins: ProfitMarginsResponse;
  sessionId: string;
  mode: number;
  sessionToken: string;
  storeDetails: StoreDetails;
}

interface LoginStore extends LoginDetails {
  loginHandler: (
    username: string | undefined,
    password: string | undefined,
    store: number | undefined
  ) => any;
}

export const loginStore = create<LoginStore>(() => ({
  id: null,
  name: "",
  accessLevel: -1,
  hasAccess: -1,
  csrfToken: "",
  sessionId: "",
  mode: 0,
  sessionToken: "",
  /* Default */
  storeDetails: {
    id: -1,
    location: "",
    businessName: "",
    gstHSTTaxRate: 0,
    pstTaxRate: 0,
    cipherKeyThisStore: "",
  },
  profitMargins: {
    profitMargins: { DEFAULT: 25, STP: 50, GRO: 0, PHI: 0, STM: 0, DON: 0 },
    lastModifiedTimestamp: undefined,
  },

  /**
   * This method will handle login.
   */
  loginHandler: (
    username: string | undefined,
    password: string | undefined,
    storeId: number | undefined
  ) => {
    const httpService = new HTTPService();
    return httpService.fetch<LoginDetails>(
      {
        username: username,
        password: password,
        store_id: storeId,
      },
      "um_authenticate"
    );
  },
}));
