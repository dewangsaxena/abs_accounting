import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";

// Http Service
const httpService = new HTTPService();

/**
 * Vendor Details
 */
export interface VendorDetails {
    id: number | null;
    name: string;
    isInactive: number;
    totalPurchased: number;
}

/**
 * Vendor Details Store
 */
export interface VendorDetailsStore extends VendorDetails {
    add: () => any;
    update: () => any;
    setDetails: (details: VendorDetails) => void;
    setField: (fieldName: string, fieldValue: any) => void;
    fetch: (
        searchTerm: string,
        excludeInactive?: boolean,
      ) => any;
    reset: () => any;
}

export const vendorDetailsStore = create<VendorDetailsStore>((set, get) => ({
    id: null,
    name: "",
    isInactive: 0,
    totalPurchased: 0,
    add: async () => {
        return await httpService.add(get(), "vendor_add");
    },
    update: async () => {
        return await httpService.add(get(), "vendor_update");
    },
    setDetails: (details: VendorDetails) => {
        set({id: details.id});
        set({name: details.name});
        set({isInactive: details.isInactive});
        set({totalPurchased: details.totalPurchased});
    },
    setField: (fieldName: string, fieldValue: any) => {
        if(fieldName === "id") set({id: fieldValue});
        else if(fieldName === "name") set({name: fieldValue});
        else if(fieldName === "isInactive") set({isInactive: fieldValue});
    },
    fetch: async (
        searchTerm: string,
        excludeInactive?: boolean,
      ) => {
        let payload = {
            term: searchTerm,
            exclude_inactive: excludeInactive ? 1 : 0,
        };
        return await httpService.fetch<VendorDetails[]>(payload, "vendor_fetch");
    },
    reset: () => {
        set({id: null});
        set({name: ''});
        set({isInactive: 0});
        set({totalPurchased: 0});
    },
}));