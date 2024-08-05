import { create } from "zustand";

interface NavigationInteraction {
  customerAndSalesVisible: boolean;
  inventoryAndServicesVisible: boolean;
  settingsVisibile: boolean;
  setVisibility: (sectionName: "CS" | "INV" | "SET") => void;
}

export const interaction = create<NavigationInteraction>((set) => ({
  customerAndSalesVisible: true,
  inventoryAndServicesVisible: false,
  settingsVisibile: false,
  setVisibility: (sectionName: "CS" | "INV" | "SET") => {
    set({
      customerAndSalesVisible: false,
      inventoryAndServicesVisible: false,
      settingsVisibile: false,
    });
    if (sectionName == "CS") set({ customerAndSalesVisible: true });
    else if (sectionName == "INV") set({ inventoryAndServicesVisible: true });
    else if (sectionName == "SET") set({ settingsVisibile: true });
  },
}));
