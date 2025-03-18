
import { Box, HStack, useToast } from "@chakra-ui/react";
import { buildSearchListForClient, redirectIfInvalidSession, showToast } from "../../../shared/functions";
import { VendorDetails, vendorDetailsStore } from "./store";
import { useState } from "react";
import { APIResponse } from "../../../service/api-client";
import { AUTO_SUGGEST_MIN_INPUT_LENGTH, UNKNOWN_SERVER_ERROR_MSG } from "../../../shared/config";
import { _Label } from "../../../shared/Components";
import {
  inputConfig,
  iconColor,
  numberFont,
  AsyncSelectStyle,
  AutoSuggestStyle,
} from "../../../shared/style";
import AutoSuggest from "react-autosuggest";

/**
 * Vendor Details
 */
interface __VendorDetails extends VendorDetails {
  isViewOrUpdate: boolean;
}

/**
 * Vendor Details
 * @returns 
 */
const Vendor = ({isViewOrUpdate}: __VendorDetails ) => {

  // Check for Active Session
  redirectIfInvalidSession();
  
  // Toast Handle.
  const toast = useToast();

  // Vendor Details Store
  const {
    id,
    name,
    isInactive,
    totalPurchased,
    setDetails,
    setField,
    fetch,
    reset,
  } = vendorDetailsStore();

  // States
  const [loadingState, setLoadingState] = useState(false);
  const [inputDisable, setInputDisable] = useState(
    isViewOrUpdate && id === null ? true : false
  );
  
  const [selectedVendor, setSelectedVendor] = useState<string>("");
  const [vendorSuggestions, setVendorSuggestions] = useState<any>([]);

  // Select Load options
  const loadOptions = (searchTerm: string) => {
      fetch(searchTerm, false)
      .then((res: any) => {
        let response: APIResponse<VendorDetails[]> = res.data;
        if (response.status === true) {}
        // setVendorSuggestions(buildSearchListForClient(response.data));
        else
        showToast(
            toast,
            false,
            response.message || UNKNOWN_SERVER_ERROR_MSG
        );
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      });
  };

  return <>
  
  {isViewOrUpdate && 
    <Box>
      <HStack>
        <Box width="10%">
          <_Label fontSize="0.8em">Showing Details for: </_Label>
        </Box>
        <Box width="80%">
            <AutoSuggest
              suggestions={vendorSuggestions}
              onSuggestionsClearRequested={() =>
                setVendorSuggestions([])
              }
              onSuggestionsFetchRequested={({ value }) => {
                if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH)
                  return;
                loadOptions(value);
                setLoadingState(false);
                setInputDisable(false);
              }}
              onSuggestionSelected={(_: any, { suggestionIndex }) => {
                // onClientSelect(clientSuggestions[suggestionIndex]);
              }}
              getSuggestionValue={(suggestion: any) => {
                return `${suggestion.value.primaryDetails.name}`;
              }}
              renderSuggestion={(suggestion: any) => (
                <span>&nbsp;{suggestion.label}</span>
              )}
              inputProps={{
                style: {
                  width: "100%",
                  ...AutoSuggestStyle,
                },
                placeholder:
                  `Search vendors...` +
                  (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                    ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                    : ""),
                value: selectedVendor,
                onChange: (_, { newValue }) => {
                  setSelectedVendor(newValue);
                  if (newValue.trim() === "") {
                    setLoadingState(true);
                    setInputDisable(true);
                    reset();
                  }
                },
              }}
              highlightFirstSuggestion={true}
            ></AutoSuggest>
          </Box>
      </HStack>
    </Box>
    }
  </>;
}

export default Vendor;

