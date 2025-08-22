import { useState } from "react";
import {
  Badge,
  Box,
  Card,
  CardBody,
  Checkbox,
  HStack,
  Tab,
  TabList,
  TabPanel,
  TabPanels,
  Tabs,
  Textarea,
  VStack,
  useToast,
} from "@chakra-ui/react";
import { RiNumbersFill } from "react-icons/ri";
import { AiFillEdit, AiFillTags, AiOutlineBarcode } from "react-icons/ai";
import {
  MdOutlinePrecisionManufacturing,
  MdOutlineAccountBalance,
} from "react-icons/md";
import { HiOutlineInformationCircle } from "react-icons/hi";
import { IoMdInformation } from "react-icons/io";
import { BiDetail } from "react-icons/bi";
import { BsGraphDown } from "react-icons/bs";
import { APIResponse } from "../../service/api-client";
import {
  inputConfig,
  iconColor,
  numberFont,
  navBgColor,
  AutoSuggestStyle,
} from "../../shared/style";
import {
  _Button,
  _Input,
  _Label,
  _Select,
  TabButton,
  _Divider,
  _InputLeftElement,
  CurrencyIcon,
} from "../../shared/Components";
import {
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  Stores,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../shared/config";
import { ItemDetails, Prices, itemStore } from "./itemStore";
import {
  buildSearchListForItem,
  calculateCOGSMargin,
  redirectIfInvalidSession,
  formatNumber,
  getProfitMarginByItemIdentifierPrefix,
  showToast,
  toFixed,
  isSessionActive,
} from "../../shared/functions";
import { accountsConfig } from "../../shared/accounts";
import { ProfitMarginIndex, getProfitMargins } from "./profitMarginStore";
import { TbCategory2, TbTallymarks } from "react-icons/tb";
import AutoSuggest from "react-autosuggest";

/**
 * Price Cards.
 * @param storeId
 * @param prices
 * @param profitMargins
 * @param setField
 * @returns
 */
const PricesCard = ({
  storeId,
  profitMargins,
  doesPricesExistsForCurrentStore,
  currentStoreId,
  quantitiesAllStores,
}: {
  storeId: number;
  profitMargins: ProfitMarginIndex;
  doesPricesExistsForCurrentStore: boolean;
  currentStoreId: number;
  quantitiesAllStores: { [key: number]: number };
}) => {
  // Item Store
  const { prices, identifier, setField } = itemStore();

  /* Check whether Price margin exists for current store */
  if (doesPricesExistsForCurrentStore === false) {
    return;
  }

  // Current Store Prices
  let currentStorePrices: Prices = prices[storeId];

  // Add Default Prices
  if (storeId in prices === false) {
    currentStorePrices = {
      storeId: storeId,
      sellingPrice: 0,
      preferredPrice: 0,
      buyingCost: 0,
    };
  }

  /**
   * Check for readonly card.
   */
  const isReadOnly: boolean = storeId !== currentStoreId;

  /**
   * Check for Disabled(UnInitilized Item).
   */
  const isDisabled: boolean = identifier === undefined || identifier === "";

  /**
   * Calculate COGS Margin.
   */
  let cogsMargin: number = calculateCOGSMargin(
    currentStorePrices.sellingPrice,
    currentStorePrices.buyingCost
  );

  return (
    <Card minWidth="35vmax">
      <CardBody padding={2} borderWidth={1} borderRadius={5}>
        <VStack alignItems={"start"}>
          <Badge
            variant={
              currentStoreId == currentStorePrices.storeId ? "subtle" : "solid"
            }
            bgColor={
              currentStoreId == currentStorePrices.storeId
                ? "#CBC3E3"
                : "#AFE1AF"
            }
            fontSize="0.8em"
            letterSpacing={2}
            textTransform={"uppercase"}
          >
            {Stores.names[currentStorePrices.storeId]}
          </Badge>

          {/* Only Show Quantities when Viewing/Updating Item. */}
          {quantitiesAllStores[currentStorePrices.storeId] >= 0 && (
            <HStack>
              <_Label
                fontSize="0.7em"
                letterSpacing={2}
                textTransform={"uppercase"}
              >
                QUANTITY:
              </_Label>
              <_Label
                fontSize="0.7em"
                letterSpacing={5}
                textTransform={"uppercase"}
                fontFamily={numberFont}
                fontWeight="bold"
              >
                {formatNumber(quantitiesAllStores[currentStorePrices.storeId])}
              </_Label>
            </HStack>
          )}

          <_Divider margin={{ lg: 1 }}></_Divider>
          <VStack width="100%" align="start">
            <Box width="100%">
              <_Label
                fontSize="0.8em"
                letterSpacing={1}
                fontFamily={numberFont}
              >
                Selling Price:{" "}
                {isNaN(cogsMargin) ? "" : `(${formatNumber(cogsMargin)}%)`}
              </_Label>
            </Box>
            <Box transform="translateY(-25%);" width="100%">
              <_InputLeftElement
                isDisabled={isDisabled}
                key={currentStorePrices.sellingPrice}
                fontFamily={numberFont}
                isReadOnly={isReadOnly}
                defaultValue={currentStorePrices.sellingPrice}
                type="number"
                borderBottomColor={inputConfig.borderColor}
                borderBottomWidth={inputConfig.borderWidth}
                borderRadius={inputConfig.borderRadius}
                size={inputConfig.size}
                fontSize={inputConfig.fontSize}
                fontWeight="bold"
                letterSpacing={inputConfig.letterSpacing}
                width={"100%"}
                leftElement={<CurrencyIcon></CurrencyIcon>}
                onBlur={(event: any) => {
                  if (event && currentStoreId === storeId) {
                    if (identifier === undefined || identifier === "") {
                      alert("Item Identifier is Invalid.");
                    }
                    let sellingPrice = parseFloat(
                      event.target.value.trim() || 0
                    );
                    setField("sellingPrice", toFixed(
                      sellingPrice, 
                      2 /* THE PRECISION HERE SHOULD ALWAYS BE 2 */
                    ));
                  }
                }}
              ></_InputLeftElement>
            </Box>
          </VStack>
          <VStack width="100%" align="start">
            <Box width="100%">
              <_Label
                fontSize="0.8em"
                letterSpacing={1}
                fontFamily={numberFont}
              >
                Preferred Price:
              </_Label>
            </Box>
            <Box transform="translateY(-25%);" width="100%">
              <_InputLeftElement
                isDisabled={isDisabled}
                isReadOnly={isReadOnly}
                key={currentStorePrices.preferredPrice}
                fontFamily={numberFont}
                defaultValue={currentStorePrices.preferredPrice}
                type="number"
                borderBottomColor={inputConfig.borderColor}
                borderBottomWidth={inputConfig.borderWidth}
                borderRadius={inputConfig.borderRadius}
                size={inputConfig.size}
                fontWeight="bold"
                fontSize={inputConfig.fontSize}
                letterSpacing={inputConfig.letterSpacing}
                width={"100%"}
                leftElement={<CurrencyIcon></CurrencyIcon>}
                onBlur={(event: any) => {
                  if (event && storeId === currentStoreId) {
                    let preferredPrice = parseFloat(
                      event.target.value.trim() || 0
                    );
                    setField("preferredPrice", toFixed(preferredPrice));
                  }
                }}
              ></_InputLeftElement>
            </Box>
          </VStack>
          <VStack width="100%" align="start">
            <Box width="100%">
              <_Label
                fontSize="0.8em"
                letterSpacing={1}
                fontFamily={numberFont}
              >
                Buying Price:
              </_Label>
            </Box>
            <Box transform="translateY(-25%);" width="100%">
              <_InputLeftElement
                onBlur={(event: any) => {
                  if (event && storeId === currentStoreId) {
                    if (identifier === undefined || identifier === "") {
                      alert("Item Identifier is Invalid.");
                      return;
                    }

                    // Buying Cost
                    let buyingCost: number = parseFloat(
                      event.target.value.trim() || "0"
                    );

                    let sellingPrice = buyingCost;

                    // Calculate Profit Margin Percentage
                    let profitMarginPercentage =
                      getProfitMarginByItemIdentifierPrefix(
                        profitMargins,
                        identifier
                      );

                    let temp = (buyingCost * profitMarginPercentage) / 100;
                    sellingPrice += temp;

                    // Update Prices
                    setField("buyingCost", [
                      toFixed(buyingCost),
                      toFixed(sellingPrice),
                    ]);
                  }
                }}
                isDisabled={isDisabled}
                key={currentStorePrices.buyingCost}
                isReadOnly={isReadOnly}
                defaultValue={currentStorePrices.buyingCost}
                type="number"
                borderBottomColor={inputConfig.borderColor}
                borderBottomWidth={inputConfig.borderWidth}
                borderRadius={inputConfig.borderRadius}
                size={inputConfig.size}
                fontWeight="bold"
                fontSize={inputConfig.fontSize}
                letterSpacing={inputConfig.letterSpacing}
                width={"100%"}
                fontFamily={numberFont}
                leftElement={<CurrencyIcon></CurrencyIcon>}
              ></_InputLeftElement>
            </Box>
          </VStack>
        </VStack>
      </CardBody>
    </Card>
  );
};

/**
 * Items.
 * @param isViewOrUpdate
 * @returns
 */
const Item = ({ isViewOrUpdate = false }: { isViewOrUpdate?: boolean }) => {
  redirectIfInvalidSession();

  /**
   * Get Store Id.
   */
  const storeId = parseInt(localStorage.getItem("storeId") || "-1");

  /**
   * Store Details
   */
  let {
    id,
    identifier,
    description,
    oem,
    quantity,
    unit,
    reorderQuantity,
    prices,
    profitMargins,
    memo,
    additionalInformation,
    category,
    isCore,
    isInactive,
    isDiscountDisabled, 
    storeId: _storeId,
    account,
    quantitiesAllStores,
    lastSold,
    setField,
    setDetails,
    fetch,
    add,
    update,
    reset,
  } = itemStore();

  // Hooks
  const [disableButton, setDisableButton] = useState(false);
  const [loadingState, setLoadingState] = useState(false);
  const [isItemLoaded, setIsItemLoadedStatus] = useState(false);

  // Toast
  const toast = useToast();

  /**
   * Initialize Prices.
   */
  if (isViewOrUpdate === false && storeId in prices === false) {
    prices[storeId] = {
      storeId: storeId,
      sellingPrice: 0,
      buyingCost: 0,
      preferredPrice: 0,
    };
  }

  /* Set price margins */
  if (Object.keys(profitMargins).length === 0)
    setField("profitMargins", getProfitMargins());

  const [selectedItem, setSelectedItem] = useState<string>("");
  const [itemSuggestions, setItemSuggestions] = useState<any>([]);

  // Select Load options
  const loadOptionsForItem = (searchTerm: string) => {
    fetch(searchTerm, false)
      .then((res: any) => {
        let response: APIResponse<ItemDetails[]> = res.data;
        if (response.status === true) {
          setItemSuggestions(buildSearchListForItem(response.data));
        } else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((_: any) => {});
  };

  /**
   * Current Store.
   */
  const currentStoreId = parseInt(localStorage.getItem("storeId") || "0");

  /**
   * Click Handler.
   */
  const clickHandler = () => {
    if (isViewOrUpdate) {
      if (!confirm("Do you really want to update?")) return false;
    }
    setDisableButton(true);
    setLoadingState(true);

    // Flag
    let isOperationSuccessful: boolean = false;

    try {
      if (isViewOrUpdate) {
        update()
          .then((res: any) => {
            let response: APIResponse<ItemDetails> = res.data;
            if (response.status !== true) {
              setDisableButton(false);
              isOperationSuccessful = false;
            } else isOperationSuccessful = true;
            showToast(
              toast,
              response.status,
              response.status === false
                ? response.message || UNKNOWN_SERVER_ERROR_MSG
                : ""
            );
          })
          .catch((error: any) => {
            isOperationSuccessful = false;
            setDisableButton(false);
            showToast(toast, false, error.message);
          })
          .finally(function () {
            if (isOperationSuccessful) window.location.reload();
            setLoadingState(false);
          });
      } else {
        add()
          .then((res: any) => {
            let response: APIResponse<ItemDetails> = res.data;
            if (response.status !== true) {
              isOperationSuccessful = false;
              setDisableButton(false);
            } else isOperationSuccessful = true;
            showToast(
              toast,
              response.status,
              response.status === false
                ? response.message || UNKNOWN_SERVER_ERROR_MSG
                : ""
            );
          })
          .catch((error: any) => {
            isOperationSuccessful = false;
            setDisableButton(false);
            showToast(toast, false, error.message);
          })
          .finally(function () {
            if (isOperationSuccessful) window.location.reload();
            setLoadingState(false);
          });
      }
    } catch (e) {
      setLoadingState(false);
      setDisableButton(false);
    }
  };

  // All Stores
  let _pricesStores = Object.keys(prices);

  /* Current Store Should be Displayed First */
  let pricesStores: string[] = [currentStoreId.toString()];

  /* Add the Rest of the Stores */
  for (let i = 0; i < _pricesStores.length; ++i) {
    if (pricesStores.includes(_pricesStores[i]) === false)
      pricesStores.push(_pricesStores[i]);
  }

  // Flag
  const doesPricesExistsForCurrentStore = pricesStores.includes(
    `${currentStoreId}`
  );

  // Set Default Store id
  if (doesPricesExistsForCurrentStore === false) {
    prices[currentStoreId] = {
      storeId: currentStoreId,
      sellingPrice: 0,
      buyingCost: 0,
      preferredPrice: 0,
    };
  }

  return (
    isSessionActive() && (
      <>
        <Box paddingLeft={5} paddingRight={5} bgColor="white">
          <Tabs>
            <TabList marginBottom={5}>
              <Tab>
                <TabButton icon={<RiNumbersFill />} label="Quantity" />
              </Tab>
              <Tab>
                <TabButton icon={<AiFillTags />} label="Pricing" />
              </Tab>
              {isViewOrUpdate === false && (
                <Tab>
                  <TabButton icon={<TbTallymarks />} label="Add Quantity" />
                </Tab>
              )}
              <Tab>
                <TabButton icon={<TbCategory2 />} label="Unit" />
              </Tab>
              <Tab>
                <TabButton icon={<MdOutlineAccountBalance />} label="Linked" />
              </Tab>
              <Tab>
                <TabButton icon={<HiOutlineInformationCircle />} label="Memo" />
              </Tab>
              <Tab>
                <TabButton
                  icon={<IoMdInformation />}
                  label="Additional Information"
                />
              </Tab>
            </TabList>

            {/* Show Search Param  */}
            {isViewOrUpdate && (
              <Box>
                <HStack spacing={20}>
                  <Box width="10%">
                    <_Label fontSize="0.8em">Showing Details for: </_Label>
                  </Box>
                  <Box width="80%">
                    <AutoSuggest
                      suggestions={itemSuggestions}
                      onSuggestionsClearRequested={() => setItemSuggestions([])}
                      onSuggestionsFetchRequested={({ value }) => {
                        if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH)
                          return;
                        loadOptionsForItem(value);
                      }}
                      onSuggestionSelected={(_: any, { suggestionIndex }) => {
                        setIsItemLoadedStatus(true);
                        setDetails(itemSuggestions[suggestionIndex].value);
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
                            setIsItemLoadedStatus(false);
                            reset();
                          }
                        },
                        disabled: false,
                      }}
                      highlightFirstSuggestion={true}
                    ></AutoSuggest>
                  </Box>
                </HStack>
                <_Divider />
              </Box>
            )}

            <Box width="100%">
              <HStack spacing={2}>
                <Box width="25%">
                  <_InputLeftElement
                    defaultValue={identifier}
                    placeholder="Item Identifier"
                    fontWeight="bold"
                    borderRadius={inputConfig.borderRadius}
                    borderBottomColor={"red"}
                    borderBottomWidth={inputConfig.borderWidth}
                    size={inputConfig.size}
                    fontSize={inputConfig.fontSize}
                    fontFamily={numberFont}
                    letterSpacing={inputConfig.letterSpacing}
                    width={"100%"}
                    leftElement={<AiOutlineBarcode color={"#4169e1"} />}
                    onBlur={(event: any) => {
                      if (event) {
                        setField("identifier", event.target.value.trim());
                      }
                    }}
                  ></_InputLeftElement>
                </Box>
                <Box width="45%">
                  <_InputLeftElement
                    defaultValue={description}
                    placeholder="Item Description"
                    fontWeight="bold"
                    borderRadius={inputConfig.borderRadius}
                    borderBottomColor={"red"}
                    borderBottomWidth={inputConfig.borderWidth}
                    size={inputConfig.size}
                    fontSize={inputConfig.fontSize}
                    letterSpacing={inputConfig.letterSpacing}
                    width={"100%"}
                    leftElement={<BiDetail color={"#2AAA8A"} />}
                    onBlur={(event: any) => {
                      if (event) {
                        setField("description", event.target.value.trim());
                      }
                    }}
                  ></_InputLeftElement>
                </Box>
                <Box width="15%">
                  <_InputLeftElement
                    placeholder="OEM"
                    defaultValue={oem}
                    borderRadius={inputConfig.borderRadius}
                    borderBottomColor={inputConfig.borderColor}
                    borderBottomWidth={inputConfig.borderWidth}
                    size={inputConfig.size}
                    fontSize={inputConfig.fontSize}
                    letterSpacing={inputConfig.letterSpacing}
                    width={"100%"}
                    leftElement={
                      <MdOutlinePrecisionManufacturing color={"#2AAA8A"} />
                    }
                    onBlur={(event: any) => {
                      if (event) {
                        setField("oem", event.target.value.trim());
                      }
                    }}
                  ></_InputLeftElement>
                </Box>
                <Box width="15%" transform="translateY(25%);">
                  <_Select
                    size="xs"
                    isDisabled={true}
                    value={category}
                    options={{ 0: "Service", 1: "Inventory" }}
                    onChange={() => {}}
                  ></_Select>
                </Box>
              </HStack>
            </Box>
            <_Divider></_Divider>
            <TabPanels>
              <TabPanel padding={0}>
                <VStack alignItems={"left"}>
                  <Box>
                    <_Label
                      letterSpacing={2}
                      fontSize={"0.8em"}
                      fontWeight={"bold"}
                    >
                      AVAILABLE
                    </_Label>
                  </Box>
                  <Box>
                    <HStack>
                      <Box width="25%">
                        <HStack spacing={10}>
                          <_Label letterSpacing={2} fontSize="0.8em">
                            Quantity:
                          </_Label>
                          <_Label
                            fontFamily={numberFont}
                            fontWeight="bold"
                            letterSpacing={3}
                            fontSize="0.8em"
                          >
                            {isNaN(quantity) ? 0 : formatNumber(quantity)}
                          </_Label>
                        </HStack>
                      </Box>
                      <Box width="25%">
                        <HStack spacing={5}>
                          <_Label letterSpacing={2} fontSize="0.8em">
                            Value:
                          </_Label>
                          <Box width="100%">
                            <HStack>
                              <CurrencyIcon></CurrencyIcon>
                              <_Label
                                fontFamily={numberFont}
                                fontWeight="bold"
                                letterSpacing={3}
                                fontSize="0.8em"
                              >
                                {isNaN(quantity * prices[storeId]?.buyingCost)
                                  ? 0
                                  : formatNumber(
                                      quantity * prices[storeId].buyingCost
                                    )}
                              </_Label>
                            </HStack>
                          </Box>
                        </HStack>
                      </Box>
                    </HStack>
                  </Box>
                </VStack>
                <_Divider></_Divider>
                <HStack width="100%" spacing={10}>
                  <VStack alignItems={"left"} width="40%" >
                    <Box width="100%">
                      <_Label
                        letterSpacing={2}
                        fontSize={"0.8em"}
                        fontWeight={"bold"}
                      >
                        REORDER QUANTITY
                      </_Label>
                    </Box>
                    <Box width="100%">
                      <HStack width="100%">
                        <Box width="100%">
                          <HStack spacing={2}>
                            <Box width="50%" >
                              <_Label letterSpacing={1} fontSize="0.8em">
                                Minimum level:
                              </_Label>
                            </Box>
                            <Box width="50%" transform="translateY(-30%);">
                              <_InputLeftElement
                                fontFamily={numberFont}
                                type="number"
                                defaultValue={
                                  reorderQuantity[currentStoreId] || 0
                                }
                                placeholder="Minimum level"
                                borderRadius={inputConfig.borderRadius}
                                borderBottomColor={inputConfig.borderColor}
                                borderBottomWidth={inputConfig.borderWidth}
                                size={inputConfig.size}
                                fontWeight="bold"
                                fontSize={inputConfig.fontSize}
                                letterSpacing={inputConfig.letterSpacing}
                                width={"100%%"}
                                leftElement={<BsGraphDown color={"#FFBD33"} />}
                                onBlur={(event: any) => {
                                  if (event) {
                                    setField(
                                      "reorderQuantity",
                                      event.target.value.trim()
                                    );
                                  }
                                }}
                              ></_InputLeftElement>
                            </Box>
                          </HStack>
                        </Box>
                      </HStack>
                    </Box>
                  </VStack>
                  <VStack>
                    <Box width="100%">
                      <_Label
                          letterSpacing={2}
                          fontSize={"0.8em"}
                          fontWeight={"bold"}
                          color="#8B0000"
                        >
                          DISABLE DISCOUNT
                      </_Label>
                    </Box>
                    <Box width="100%">
                      <Checkbox
                        isChecked={
                          currentStoreId in isDiscountDisabled
                            ? isDiscountDisabled[currentStoreId]
                              ? true
                              : false
                            : false
                        }
                        onChange={() => {
                          if (currentStoreId in isDiscountDisabled) {
                            setField("isDiscountDisabled", isDiscountDisabled[currentStoreId] ^ 1);
                          } else {
                            setField("isDiscountDisabled", 0);
                          }
                        }}
                        size="md"
                        colorScheme="red"
                      >
                        <_Label fontSize="0.8em">Is Discount Disabled?</_Label>
                      </Checkbox>
                    </Box>
                  </VStack>
                </HStack>
              </TabPanel>

              <TabPanel padding={0}>
                <Box overflowX="scroll" overflowY={"hidden"} width="100%">
                  <HStack spacing={5}>
                    {/* On view or update */}
                    {isItemLoaded === true &&
                      isViewOrUpdate === true &&
                      pricesStores.map((_storeId) => {
                        const storeId = parseInt(_storeId);
                        return (
                          <PricesCard
                            quantitiesAllStores={quantitiesAllStores}
                            doesPricesExistsForCurrentStore={
                              doesPricesExistsForCurrentStore
                            }
                            key={storeId}
                            profitMargins={profitMargins}
                            storeId={storeId}
                            currentStoreId={currentStoreId}
                          ></PricesCard>
                        );
                      })}

                    {/* Adding Item  */}
                    {isViewOrUpdate === false && (
                      <Box>
                        <PricesCard
                          quantitiesAllStores={quantitiesAllStores}
                          doesPricesExistsForCurrentStore={
                            doesPricesExistsForCurrentStore
                          }
                          profitMargins={profitMargins}
                          storeId={storeId}
                          currentStoreId={currentStoreId}
                        ></PricesCard>
                      </Box>
                    )}
                  </HStack>
                </Box>
              </TabPanel>

              {isViewOrUpdate === false && (
                <TabPanel>
                  <HStack>
                    <Box>
                      <_Label fontSize="0.8em" letterSpacing={2}>
                        ADD INITIAL QUANTITY:
                      </_Label>
                    </Box>
                    <Box transform="translateY(-25%);">
                      <_InputLeftElement
                        type="number"
                        defaultValue={0}
                        placeholder={"Qty."}
                        borderRadius={inputConfig.borderRadius}
                        borderBottomColor={inputConfig.borderColor}
                        borderBottomWidth={inputConfig.borderWidth}
                        size={inputConfig.size}
                        fontWeight="bold"
                        fontSize={inputConfig.fontSize}
                        letterSpacing={inputConfig.letterSpacing}
                        width={"100%"}
                        leftElement={<TbTallymarks color={"#34495E"} />}
                        onBlur={(event: any) => {
                          if (event) {
                            setField(
                              "initialQuantity",
                              event.target.value.trim()
                            );
                          }
                        }}
                      ></_InputLeftElement>
                    </Box>
                  </HStack>
                </TabPanel>
              )}

              <TabPanel>
                <HStack spacing={5}>
                  <Box>
                    <_Label fontSize="0.8em" letterSpacing={2}>
                      UNIT:
                    </_Label>
                  </Box>
                  <Box transform="translateY(-25%);">
                    <_InputLeftElement
                      type="text"
                      key={unit}
                      defaultValue={unit}
                      placeholder={"Unit"}
                      borderRadius={inputConfig.borderRadius}
                      borderBottomColor={inputConfig.borderColor}
                      borderBottomWidth={inputConfig.borderWidth}
                      size={inputConfig.size}
                      fontWeight="bold"
                      fontSize={inputConfig.fontSize}
                      letterSpacing={inputConfig.letterSpacing}
                      width={"100%"}
                      leftElement={<TbCategory2 color={"#add8e6"} />}
                      onBlur={(event: any) => {
                        if (event) {
                          setField("unit", event.target.value.trim());
                        }
                      }}
                    ></_InputLeftElement>
                  </Box>
                </HStack>
              </TabPanel>

              <TabPanel>
                <VStack alignItems={"left"} width="100%">
                  <HStack>
                    <Box width="10%">
                      <_Label fontSize="0.8em">Assets:</_Label>
                    </Box>
                    <Box>
                      <_Select
                        fontSize="0.8em"
                        options={accountsConfig}
                        value={account.assets}
                        isDisabled={true}
                        onChange={() => {}}
                      ></_Select>
                    </Box>
                  </HStack>
                  <HStack>
                    <Box width="10%">
                      <_Label fontSize="0.8em">Revenue:</_Label>
                    </Box>
                    <Box>
                      <_Select
                        fontSize="0.8em"
                        options={accountsConfig}
                        value={account.revenue}
                        isDisabled={true}
                        onChange={() => {}}
                      ></_Select>
                    </Box>
                  </HStack>
                  <HStack>
                    <Box width="10%">
                      <_Label fontSize="0.8em">C.O.G.S:</_Label>
                    </Box>
                    <Box>
                      <_Select
                        fontSize="0.8em"
                        options={accountsConfig}
                        value={account.cogs}
                        isDisabled={true}
                        onChange={() => {}}
                      ></_Select>
                    </Box>
                  </HStack>
                  <HStack>
                    <Box width="10%">
                      <_Label fontSize="0.8em">Variance:</_Label>
                    </Box>
                    <Box>
                      <_Select
                        fontSize="0.8em"
                        options={accountsConfig}
                        value={account.variance}
                        isDisabled={true}
                        onChange={() => {}}
                      ></_Select>
                    </Box>
                  </HStack>
                </VStack>
              </TabPanel>

              <TabPanel>
                <Textarea
                  rows={8}
                  defaultValue={memo}
                  placeholder="Memo"
                  size="sm"
                  borderRadius={5}
                  resize={"none"}
                  onBlur={(event: any) => {
                    if (event) {
                      setField("memo", event.target.value.trim());
                    }
                  }}
                />
              </TabPanel>
              <TabPanel>
                <Textarea
                  rows={8}
                  defaultValue={additionalInformation}
                  placeholder="Additional Information"
                  size="sm"
                  borderRadius={5}
                  resize={"none"}
                  onBlur={(event: any) => {
                    if (event) {
                      setField(
                        "additionalInformation",
                        event.target.value.trim()
                      );
                    }
                  }}
                />
              </TabPanel>
            </TabPanels>
          </Tabs>
          <_Divider></_Divider>
          <HStack spacing={20}>
            <Box width="80%">
              <HStack>
                <Box width="25%">
                  <Checkbox
                    isChecked={
                      currentStoreId in isInactive
                        ? isInactive[currentStoreId]
                          ? true
                          : false
                        : false
                    }
                    onChange={() => {
                      if (currentStoreId in isInactive) {
                        setField("isInactive", isInactive[currentStoreId] ^ 1);
                      } else {
                        setField("isInactive", 0);
                      }
                    }}
                    size="md"
                    colorScheme="red"
                  >
                    <_Label fontSize="0.8em">Is Inactive?</_Label>
                  </Checkbox>
                </Box>
                <Box width="25%">
                  <Checkbox
                    isChecked={isCore ? true : false}
                    onChange={() => {
                      setField("isCore", isCore ^ 1);
                    }}
                    size="md"
                    colorScheme="blue"
                  >
                    <_Label fontSize="0.8em">Is Core?</_Label>
                  </Checkbox>
                </Box>
                <Box width="40%">
                  <HStack>
                  <_Label fontSize="0.8em">Last Sold : </_Label>
                  <_Label fontSize="0.8em" fontFamily={numberFont} letterSpacing={2} textTransform={"uppercase"}><b>{lastSold}</b></_Label>
                  </HStack>
                </Box>
              </HStack>
            </Box>
            <Box width="20%">
              <HStack>
                <Box width="100%">
                  <_Button
                    isDisabled={
                      disableButton || (isViewOrUpdate && id === null)
                    }
                    icon={
                      isViewOrUpdate ? (
                        <AiFillEdit color={iconColor} />
                      ) : (
                        <AiFillEdit color={iconColor} />
                      )
                    }
                    size="sm"
                    label={isViewOrUpdate ? "Update" : "Add"}
                    width="100%"
                    bgColor={navBgColor}
                    borderColor="gray.200"
                    color={"white"}
                    borderWidth={1}
                    fontSize="1.2em"
                    variant="outline"
                    onClick={clickHandler}
                    isLoading={loadingState}
                  ></_Button>
                </Box>
              </HStack>
            </Box>
          </HStack>
        </Box>
      </>
    )
  );
};

export default Item;
