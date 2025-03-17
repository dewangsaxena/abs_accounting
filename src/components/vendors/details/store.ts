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