import { Box, Card, CardBody, HStack, useToast } from "@chakra-ui/react";
import {
  AutoSuggestStyle,
  iconColor,
  inputConfig,
  navBgColor,
  numberFont,
} from "../../shared/style";
import { memo, useState } from "react";
import {
  HomeNavButton,
  _Button,
  _Divider,
  _InputLeftElement,
  _Label,
} from "../../shared/Components";
import { PiWarehouseThin } from "react-icons/pi";
import {
  TbCategory2,
  TbLetterA,
  TbLetterC,
  TbLetterS,
  TbTallymarks,
  TbAdjustments,
} from "react-icons/tb";
import { BiDetail } from "react-icons/bi";
import { CiDollar } from "react-icons/ci";
import { APIResponse, HTTPService } from "../../service/api-client";
import {
  buildSearchListForItem,
  isSessionActive,
  redirectIfInvalidSession,
  showToast,
  toFixed,
} from "../../shared/functions";
import {
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  ITEM_DETAILS_TAG,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../shared/config";
import AutoSuggest from "react-autosuggest";

// Http Service Instance
const httpService = new HTTPService();

/**
 * Inventory Response Object
 */
export interface InventoryResponseObject {
  readonly id: number;
  quantity?: number;
  buyingCost: number;
  aisle: string;
  shelf: string;
  column: string;
  readonly lastModifiedTimestamp: string;
  readonly identifier: string;
  readonly description: string;
  readonly unit: string;
  readonly existingQuantity?: number;
}

// Inv Details Type
type InvDetailsType = { [_key: number]: InventoryResponseObject };

/**
 * Row Props
 */
interface RowProps {
  isDisabled: boolean;
  invDetails: InvDetailsType;
  _key: number;
}

/**
 * Row Element
 * @param _key
 * @returns
 */
const Row = memo(({ isDisabled, _key, invDetails }: RowProps) => {
  const [lastModifiedTimestamp, setLastModifiedTimestamp] = useState<string>("");
  const [description, setDescription] = useState<string>("");
  const [unit, setUnit] = useState<string>("");
  const [buyingCost, setBuyingCost] = useState<number>(0);
  const [amount, setAmount] = useState<number>(0);
  const [aisle, setAisle] = useState<string>("");
  const [shelf, setShelf] = useState<string>("");
  const [column, setColumn] = useState<string>("");
  const [quantity, setQuantity] = useState<number>(0);
  const [existingQuantity, setExistingQuantity] = useState<number>(0);

  const toast = useToast();
  const [selectedItem, setSelectedItem] = useState<string>("");
  const [itemSuggestions, setItemSuggestions] = useState<any>([]);

  // Select Load options
  const loadOptions = (searchTerm: string) => {
    httpService
      .fetch<InventoryResponseObject[]>(
        {
          search_term: ITEM_DETAILS_TAG + searchTerm,
          store_id: localStorage.getItem("storeId"),
        },
        "inv_fetch_item_details_for_adjust_inventory"
      )
      .then((res: any) => {
        let response: APIResponse<InventoryResponseObject[]> = res.data;
        if (response.status === true) {
          setItemSuggestions(buildSearchListForItem(response.data));
        } else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((_: any) => {});
  };

  const setItemDetails = (response: InventoryResponseObject) => {
    setLastModifiedTimestamp(response.lastModifiedTimestamp);
    setUnit(response.unit);
    setDescription(response.description);
    setBuyingCost(response.buyingCost);
    setAisle(response.aisle);
    setColumn(response.column);
    setShelf(response.shelf);
    setQuantity(0);
    setAmount(0);
    setExistingQuantity(response.existingQuantity || 0);
    invDetails[_key] = {
      id: response.id,
      quantity: 0,
      buyingCost: response.buyingCost,
      aisle: response.aisle,
      column: response.column,
      shelf: response.shelf,
      identifier: response.identifier,
      description: response.description,
      unit: response.unit,
      lastModifiedTimestamp: response.lastModifiedTimestamp,
    };
  };

  const resetItemDetails = () => {
    setUnit("");
    setDescription("");
    setBuyingCost(0);
    setAisle("");
    setColumn("");
    setShelf("");
    setQuantity(0);
    setAmount(0);
    setExistingQuantity(0);
    setLastModifiedTimestamp("");
    delete invDetails[_key];
  };

  return (
    <Box width="100%">
      <HStack spacing={1}>
        {/* Item Identifier */}
        <Box width="16%">
          <AutoSuggest
            suggestions={itemSuggestions}
            onSuggestionsClearRequested={() => setItemSuggestions([])}
            onSuggestionsFetchRequested={({ value }) => {
              if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
              loadOptions(value);
            }}
            onSuggestionSelected={(_: any, { suggestionIndex }) => {
              setItemDetails(itemSuggestions[suggestionIndex].value);
            }}
            getSuggestionValue={(suggestion: any) => {
              return `${suggestion.value.identifier}`;
            }}
            renderSuggestion={(suggestion: any) => (
              <span>&nbsp;{suggestion.label}</span>
            )}
            inputProps={{
              style: { width: "100%", ...AutoSuggestStyle },
              placeholder:
                `Search item...` +
                (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                  ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                  : ""),
              value: selectedItem,
              onChange: (_, { newValue }) => {
                setSelectedItem(newValue);
                if (newValue.trim() === "") {
                  resetItemDetails();
                }
              },
              disabled: isDisabled,
            }}
            highlightFirstSuggestion={true}
          ></AutoSuggest>
        </Box>
        {/* Last Modified Timestamp */}
        <Box width="8%" paddingLeft={1} >
          <_Label fontSize="0.8em" fontFamily={numberFont} letterSpacing={2}>{lastModifiedTimestamp}</_Label>
        </Box>
        {/* Inventory Quantity  */}
        <Box transform={"translateY(-6px);"} width="8%">
          <_InputLeftElement
            isReadOnly={true}
            key={existingQuantity}
            fontFamily={numberFont}
            placeholder="Qty."
            defaultValue={existingQuantity}
            type="number"
            leftElement={<TbTallymarks color="#FF9900" />}
            fontSize="0.8em"
            letterSpacing={inputConfig.letterSpacing}
          ></_InputLeftElement>
        </Box>
        {/* Quantity */}
        <Box transform={"translateY(-6px);"} width="8%">
          <_InputLeftElement
            key={quantity}
            fontFamily={numberFont}
            placeholder="Qty."
            defaultValue={quantity}
            type="number"
            leftElement={<TbTallymarks color="#FF9900" />}
            fontSize="0.8em"
            letterSpacing={inputConfig.letterSpacing}
            onBlur={(event: any) => {
              if (event) {
                let quantity: number = parseFloat(
                  event.target.value.trim() || "0"
                );
                let totalAmount: number = buyingCost * quantity;

                // Update Store
                invDetails[_key].quantity = quantity;
                setQuantity(quantity);
                setAmount(toFixed(totalAmount));
              }
            }}
          ></_InputLeftElement>
        </Box>
        {/* Unit Cost  */}
        <Box transform={"translateY(-6px);"} width="10%">
          <_InputLeftElement
            key={buyingCost}
            fontFamily={numberFont}
            defaultValue={buyingCost}
            placeholder="Unit Cost"
            fontSize="0.8em"
            type="text"
            leftElement={<CiDollar color="#944DF9" />}
            onBlur={(event: any) => {
              if (event) {
                let buyingCost: number = parseFloat(
                  event.target.value.trim() || "0"
                );
                let totalAmount: number = buyingCost * quantity;
                invDetails[_key].buyingCost = buyingCost;
                setBuyingCost(buyingCost);
                setAmount(toFixed(totalAmount));
              }
            }}
          ></_InputLeftElement>
        </Box>
        {/* Unit */}
        <Box transform={"translateY(-6px);"} width="8%">
          <_InputLeftElement
            defaultValue={unit}
            placeholder="Unit"
            fontSize="0.8em"
            type="text"
            isReadOnly={true}
            leftElement={<TbCategory2 color="#92C6E3" />}
          ></_InputLeftElement>
        </Box>
        {/* Description  */}
        <Box transform={"translateY(-6px);"} width="24%">
          <_InputLeftElement
            defaultValue={description}
            placeholder="Description"
            fontSize="0.8em"
            type="text"
            isReadOnly={true}
            leftElement={<BiDetail color="#944DF9" />}
          ></_InputLeftElement>
        </Box>
        {/* Amount */}
        <Box transform={"translateY(-6px);"} width="8%">
          <_InputLeftElement
            fontFamily={numberFont}
            defaultValue={amount}
            placeholder="Amount"
            fontSize="0.8em"
            type="text"
            isReadOnly={true}
            leftElement={<PiWarehouseThin color="#00E5FF" />}
          ></_InputLeftElement>
        </Box>
        {/* Aisle  */}
        <Box transform={"translateY(-6px);"} width="6%">
          <_InputLeftElement
            defaultValue={aisle}
            placeholder="Aisle"
            fontSize="0.8em"
            type="text"
            leftElement={<TbLetterA color="#00E5FF" />}
            onBlur={(event: any) => {
              if (event) {
                invDetails[_key].aisle = event.target.value.trim();
              }
            }}
          ></_InputLeftElement>
        </Box>
        {/* Shelf  */}
        <Box transform={"translateY(-6px);"} width="6%">
          <_InputLeftElement
            defaultValue={shelf}
            placeholder="Shelf"
            fontSize="0.8em"
            type="text"
            leftElement={<TbLetterS color="#00E5FF" />}
            onBlur={(event: any) => {
              if (event) {
                invDetails[_key].shelf = event.target.value.trim();
              }
            }}
          ></_InputLeftElement>
        </Box>
        {/* Column  */}
        <Box transform={"translateY(-6px);"} width="6%">
          <_InputLeftElement
            defaultValue={column}
            placeholder="Column"
            fontSize="0.8em"
            type="text"
            leftElement={<TbLetterC color="#00E5FF" />}
            onBlur={(event: any) => {
              if (event) {
                invDetails[_key].column = event.target.value.trim();
              }
            }}
          ></_InputLeftElement>
        </Box>
      </HStack>
    </Box>
  );
});

/**
 * Header
 * @returns
 */
const Header = () => {
  return (
    <>
      <HStack>
        <Box width="18%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Item Identifier
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Last Modified
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Inv. Qty.
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Quantity
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Unit Cost
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Unit
          </_Label>
        </Box>
        <Box width="24%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Description
          </_Label>
        </Box>
        <Box width="8%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Amount
          </_Label>
        </Box>
        <Box width="6%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Aisle
          </_Label>
        </Box>
        <Box width="6%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Shelf
          </_Label>
        </Box>
        <Box width="6%">
          <_Label
            textTransform="uppercase"
            letterSpacing={2}
            fontWeight="bold"
            fontSize="0.8em"
          >
            Column
          </_Label>
        </Box>
      </HStack>
      <_Divider margin={1}></_Divider>
    </>
  );
};

/**
 * This component will adjust inventory.
 * @returns
 */
const AdjustInventory = memo(() => {
  redirectIfInvalidSession();

  const [counter, setCounter] = useState<number>(1);
  const [invDetails] = useState<InvDetailsType>({});
  const toastInstance = useToast();
  const [isLoading, setIsLoading] = useState(false);
  const [isDisabled, setIsDisabled] = useState(false);
  const [label, setLabel] = useState<string>("");
  let iterIds: number[] = [];
  for (let i = 0; i < counter; ++i) iterIds.push(i);

  // Prepend Item Tag
  const prependItemTag = (invDetails: InvDetailsType): InvDetailsType => {
    let keys: string[] = Object.keys(invDetails);
    let count: number = keys.length;
    let temp: any = JSON.parse(JSON.stringify(invDetails));
    for (let i = 0; i < count; ++i) {
      temp[i].identifier = ITEM_DETAILS_TAG + invDetails[i].identifier;
      temp[i].description = ITEM_DETAILS_TAG + invDetails[i].description;
    }
    return temp;
  };

  /**
   * Click Handler
   */
  const clickHandler = () => {
    setIsLoading(true);
    setIsDisabled(true);

    // Prepend Item Tag
    let requestDetails: InvDetailsType = prependItemTag(invDetails);

    // Send Request to server
    try {
      httpService
        .add({ details: requestDetails }, "inv_adjust_inventory")
        .then((res: any) => {
          let response: APIResponse<void> = res.data;
          if (response.status !== true) setIsDisabled(false);
          setLabel(
            response.status === true
              ? "Adjustment Successful"
              : "Adjustment Failed"
          );
          showToast(
            toastInstance,
            response.status,
            response.status !== true
              ? response.message || UNKNOWN_SERVER_ERROR_MSG
              : ""
          );
        })
        .catch((error: any) => {
          setIsDisabled(false);
          showToast(toastInstance, false, error.message);
        })
        .finally(function () {});
    } catch (e) {
      setIsDisabled(false);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    isSessionActive() && (
      <>
        <Box width="20%">
          <HomeNavButton />
        </Box>
        <Box>
          <Box
            height="82vh"
            maxHeight="82vh"
            overflowY={"scroll"}
            tabIndex={1}
            onKeyUp={(e) => {
              if (isDisabled === false && e.key === "Enter")
                setCounter(counter + 1);
            }}
          >
            <Card height="85vh">
              <CardBody padding={1}>
                <_Label letterSpacing={2} fontSize={"1em"} fontWeight="bold">
                  ADJUST INVENTORY
                </_Label>
                <_Divider margin={2}></_Divider>
                <Header></Header>
                {iterIds.map((id, index) => {
                  return (
                    <Box
                      key={index}
                      marginTop={iterIds.length && index > 0 ? 2 : 0}
                    >
                      <Row
                        isDisabled={isDisabled}
                        invDetails={invDetails}
                        key={id}
                        _key={id}
                      ></Row>
                    </Box>
                  );
                })}
              </CardBody>
            </Card>
          </Box>
          <_Divider></_Divider>
          <HStack width="100%" spacing={10} justifyItems={"right"}>
            {isDisabled === false && (
              <Box width="15%">
                <_Button
                  loadingText="Adjusting..."
                  size="sm"
                  fontSize={"1.2em"}
                  label="Adjust Inventory"
                  icon={<TbAdjustments color={iconColor} />}
                  bgColor={navBgColor}
                  onClick={clickHandler}
                  isLoading={isLoading}
                  isDisabled={isDisabled}
                ></_Button>
              </Box>
            )}
            <Box width="85%">
              <_Label
                color={isDisabled ? "#5BCC3C" : "red"}
                textTransform="uppercase"
                fontSize={"0.8em"}
                fontWeight="bold"
                letterSpacing={2}
              >
                {label}
              </_Label>
            </Box>
          </HStack>
        </Box>
      </>
    )
  );
});

export default AdjustInventory;
