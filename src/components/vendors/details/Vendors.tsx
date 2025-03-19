
import { Box, Checkbox, HStack, useToast, VStack } from "@chakra-ui/react";
import { buildSearchListForClient, redirectIfInvalidSession, showToast } from "../../../shared/functions";
import { VendorDetails, vendorDetailsStore } from "./store";
import { memo, useState } from "react";
import { APIResponse } from "../../../service/api-client";
import { AUTO_SUGGEST_MIN_INPUT_LENGTH, UNKNOWN_SERVER_ERROR_MSG } from "../../../shared/config";
import { _Button, _Input, _Label } from "../../../shared/Components";
import {
  inputConfig,
  iconColor,
  numberFont,
  AsyncSelectStyle,
  AutoSuggestStyle,
} from "../../../shared/style";
import AutoSuggest from "react-autosuggest";
import { LiaUserEditSolid, LiaUserPlusSolid } from "react-icons/lia";

/**
 * Vendor Details
 */
interface __VendorDetails {
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
    add, 
    update,
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

  /**
   * Click Handler
   */
  const clickHandler = () => {
    let isOperationSuccessful: boolean = false;
    try {
      if(isViewOrUpdate) {
        update()
        .then((res: any) => {
          let response: APIResponse<VendorDetails> = res.data;
          if(response.status !== true) {
            setInputDisable(false);
            isOperationSuccessful = false;
          }
          else isOperationSuccessful = true;
          showToast(toast, response.status, response.message);
        })
        .catch((error: any) => {
          isOperationSuccessful = false;
          showToast(toast, false, error.message);
          setInputDisable(false);
        })
        .finally(function () {
          if (isOperationSuccessful) window.location.reload();
          setLoadingState(false);
        });
      }
      else {
        add()
        .then((res: any) => {
          let response: APIResponse<VendorDetails> = res.data;
          if(response.status !== true) {
            setInputDisable(false);
            isOperationSuccessful = false;
          }
          else isOperationSuccessful = true;
          showToast(toast, response.status, response.message);
        })
        .catch((error: any) => {
          isOperationSuccessful = false;
          showToast(toast, false, error.message);
          setInputDisable(false);
        })
        .finally(function () {
          if (isOperationSuccessful) window.location.reload();
          setLoadingState(false);
        });
      }
    }
    catch(err) {
      setInputDisable(false);
    }
  }

  return <>
  {isViewOrUpdate && 
    <Box marginBottom={10}>
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

    <VStack align="left" spacing={10}>
      <_Input
        isDisabled={inputDisable}
        defaultValue={name}
        borderBottomColor={"red"}
        borderBottomWidth={inputConfig.borderWidth}
        borderRadius={inputConfig.borderRadius}
        size={inputConfig.size}
        fontSize={inputConfig.fontSize}
        letterSpacing={inputConfig.letterSpacing}
        placeholder="Vendor Name"
        onBlur={(event: any) => {
          if (event) {
            console.log(event.target.value);
            setField("name", event.target.value.trim());
          }
        }}
      ></_Input>
      <Checkbox
        key={`is_default_shipping_address.${id}`}
        isDisabled={inputDisable}
        colorScheme="red"
        onChange={() => {
          setField("isInactive", isInactive ^ 1);
        }}
      >
        <_Label fontSize="0.8em">Is Disabled?</_Label>
      </Checkbox>

      <_Button
        isDisabled={inputDisable}
        icon={
          isViewOrUpdate ? (
            <LiaUserEditSolid color={iconColor} />
          ) : (
            <LiaUserPlusSolid color={iconColor} />
          )
        }
        size="sm"
        label={(isViewOrUpdate ? "Update" : "Add") + " Vendor"}
        width="100%"
        bgColor={"white"}
        variant="outline"
        borderColor="gray.200"
        borderWidth={1}
        color="black"
        fontSize="1.2em"
        onClick={clickHandler}
        isLoading={loadingState}
      ></_Button>
    </VStack>
  </>;
}

export default Vendor;

