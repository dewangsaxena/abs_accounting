
import { Box, Checkbox, HStack, SimpleGrid, useToast } from "@chakra-ui/react";
import { formatNumberWithDecimalPlaces, redirectIfInvalidSession, showToast } from "../../../shared/functions";
import { VendorDetails, vendorDetailsStore } from "./store";
import { useState } from "react";
import { APIResponse } from "../../../service/api-client";
import { AUTO_SUGGEST_MIN_INPUT_LENGTH, UNKNOWN_SERVER_ERROR_MSG } from "../../../shared/config";
import { _Button, _Divider, _Input, _Label, CurrencyIcon } from "../../../shared/Components";
import {
  inputConfig,
  iconColor,
  numberFont,
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

const buildSearchListForVendor = (
  data: VendorDetails[] | undefined
): any => {
  console.log(data);
  if (data === undefined) return null;
  let newOptions = [];
  let length = data.length;
  let vendorDetails: VendorDetails;
  let statusTag: string = "";
  for (let i = 0; i < length; ++i) {
    statusTag = "";
    vendorDetails = data[i];
    if (vendorDetails.isInactive) statusTag = ` ● (INACTIVE)`;
    newOptions.push({
      label: `${vendorDetails.name}${statusTag}`,
      value: vendorDetails,
    });
  }
  console.log(newOptions);
  return newOptions;
};

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

  // On Vendor Select
  const onVendorSelect = (selectedVendor: any) => {
    setDetails(selectedVendor.value);
  };

  // Select Load options
  const loadOptions = (searchTerm: string) => {
      fetch(searchTerm, false)
      .then((res: any) => {
        let response: APIResponse<VendorDetails[]> = res.data;
        if (response.status === true) {
          setVendorSuggestions(buildSearchListForVendor(response.data));
        }
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

  return <Box padding={2}>
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
                onVendorSelect(vendorSuggestions[suggestionIndex]);
              }}
              getSuggestionValue={(suggestion: any) => {
                return `${suggestion.value.name}`;
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

    <SimpleGrid columns={3} spacing={10}>
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
            setField("name", event.target.value.trim());
          }
        }}
      ></_Input>
      <HStack>
        <_Label fontSize={"0.9em"}>Amount Purchased: </_Label>
        <CurrencyIcon/>
        <_Label fontFamily={numberFont} fontSize="0.9em">{formatNumberWithDecimalPlaces(totalPurchased)}</_Label>
      </HStack>
      <Checkbox
        key={`is_inactive.${id}`}
        isDisabled={inputDisable}
        isChecked={isInactive ? true : false}
        colorScheme="red"
        onChange={() => {
          setField("isInactive", isInactive ^ 1);
        }}
      >
        <_Label fontSize="0.8em">Is Disabled?</_Label>
      </Checkbox>
    </SimpleGrid>

    <Box marginTop={10}>
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
      </Box>
  </Box>;
}

export default Vendor;

