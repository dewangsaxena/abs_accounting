import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";

// Http Service
const httpService = new HTTPService();

export interface VendorDetails {
    id: number | null;
    name: string;
    isInactive: number;
    totalPurchased: number;
}

export interface VendorDetailsStore extends VendorDetails {
    add: () => any;
    update: () => any;
    setDetails: (details: VendorDetails) => void;
    setField: (fieldName: string, fieldValue: any) => void;
    fetch: (
        searchTerm: string,
        excludeInactive?: boolean,
        fetchInvoices?: boolean
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
        else if(fieldName === "name") set({id: fieldValue});
        else if(fieldName === "isInactive") set({id: fieldValue});
        else if(fieldName === "totalPurchased") set({id: fieldValue});
    },
    fetch: (
        searchTerm: string,
        excludeInactive?: boolean,
        fetchInvoices?: boolean
      ) => {
    },
    reset: () => {
        set({id: null});
        set({name: ''});
        set({isInactive: 0});
        set({totalPurchased: 0});
    },
}));