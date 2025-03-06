import {
  Badge,
  Box,
  Card,
  CardBody,
  CardHeader,
  Center,
  Checkbox,
  Grid,
  GridItem,
  HStack,
  Popover,
  PopoverArrow,
  PopoverBody,
  PopoverCloseButton,
  PopoverContent,
  PopoverTrigger,
  Spinner,
  VStack,
  useToast,
} from "@chakra-ui/react";
import {
  CurrencyIcon,
  HomeNavButton,
  _Button,
  _Divider,
  _Input,
  _Label,
  _Select,
} from "../../../shared/Components";
import {
  AsyncSelectStyle,
  AutoSuggestStyle,
  navBgColor,
  numberFont,
} from "../../../shared/style";
import DatePicker from "react-datepicker";
import {
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  MONTHS,
  TRANSACTION_TYPES,
  TRANSACTION_TYPES_RC_RECEIPT_QT,
  UNKNOWN_SERVER_ERROR_MSG,
  paymentMethods,
  receiptPaymentMethods,
} from "../../../shared/config";
import {
  buildSearchListForClient,
  buildSearchListForItem,
  calculateProfitMargin,
  redirectIfInvalidSession,
  formatNumberWithDecimalPlaces,
  getUUID,
  showToast,
  isSessionActive,
} from "../../../shared/functions";
import AsyncSelect from "react-select/async";
import { ItemDetailsForTransactions } from "./store";
import { APIResponse, HTTPService } from "../../../service/api-client";
import { create } from "zustand";
import { shallow } from "zustand/shallow";
import { FaSearchengin } from "react-icons/fa6";
import { memo, useEffect, useState } from "react";
import { FcDocument } from "react-icons/fc";
import { FcInfo } from "react-icons/fc";
import { ClientDetails, clientStore } from "../../client/store";
/* https://www.npmjs.com/package/react-autosuggest
 * https://codesandbox.io/s/react-autosuggest-example-with-hooks-mreii?file=/src/index.js
 */
import AutoSuggest from "react-autosuggest";
import { GrNext } from "react-icons/gr";
import { GrPrevious } from "react-icons/gr";
import { itemStore } from "../../inventory/itemStore";

// Http Service Instance for Transactions
const httpService = new HTTPService();

// Color Map
const colorMap = {
  /* Paid, Unpaid */
  creditAmount: ["#DAF7A6", "#FBCEB1"],
};

// Filter Props.
interface FilterProps {
  readonly type: number;
  setShowSpinner?: any;
}

// Transaction Details
interface TransactionDetails {
  id: number;
  type: number;
  date: string;
  clientName: string;
  sumTotal: number;
  subTotal?: number;
  cogs?: number;
  creditAmount?: number;
  salesInvoiceId?: number;
}

interface _TransactionDetailsRow extends TransactionDetails {
  bgColor: string;
}

/**
 * Filter Details
 */
interface FilterDetails {
  transactionType?: number;
  transactionId?: number;
  salesInvoiceId?: number;
  clientId?: number;
  txnStartDate?: Date;
  txnEndDate?: Date;
  month?: number;
  year?: number;
  transactionAmount?: number;
  transactionAmountGreaterThanEqualTo?: number;
  transactionAmountLessThanEqualTo?: number;
  poNumber?: string;
  unitNumber?: string;
  vinNumber?: string;
  itemIdentifier?: number;
  findByCore: number;
  findUnpaidTransactions: number;
  findPaidTransactions: number;
  findByBackOrder: number;
  address?: string;
  paymentMethod?: number;
  subTotal?: number;
  sumTotal?: number;
  transactionsDetails: TransactionDetails[];
  transactionTypeForReceipt?: number;
  transactionNumberForReceipt?: number;
  salesRepId?: number;
  __offset: number;
  setProperty: (propertyName: string, value: any) => void;
  search: (sendOffset: boolean, direction: number) => any;
}

// Store
const filterStore = create<FilterDetails>((set, get) => ({
  transactionsDetails: [],
  paymentMethod: -1,
  findByCore: 0,
  findUnpaidTransactions: 0,
  findPaidTransactions: 0,
  findByBackOrder: 0,
  __offset: 0,
  setProperty: (propertyName: string, value: any) => {
    if (propertyName === "transactionType") set({ transactionType: value });
    else if (propertyName === "salesInvoiceId") set({ salesInvoiceId: value });
    else if (propertyName === "transactionId") set({ transactionId: value });
    else if (propertyName === "clientId") {
      set({ clientId: value });
    } else if (propertyName === "txnStartDate") set({ txnStartDate: value });
    else if (propertyName === "txnEndDate") set({ txnEndDate: value });
    else if (propertyName === "month") set({ month: value });
    else if (propertyName === "year") set({ year: value });
    else if (propertyName === "transactionAmount")
      set({ transactionAmount: value });
    else if (propertyName === "transactionAmountGreaterThanEqualTo")
      set({ transactionAmountGreaterThanEqualTo: value });
    else if (propertyName === "transactionAmountLessThanEqualTo")
      set({ transactionAmountLessThanEqualTo: value });
    else if (propertyName === "poNumber") set({ poNumber: value.trim() });
    else if (propertyName === "unitNumber") set({ unitNumber: value.trim() });
    else if (propertyName === "vinNumber") set({ vinNumber: value.trim() });
    else if (propertyName === "itemIdentifier") set({ itemIdentifier: value });
    else if (propertyName === "findByCore") set({ findByCore: value });
    else if (propertyName === "findUnpaidTransactions")
      set({ findUnpaidTransactions: value });
    else if (propertyName === "findPaidTransactions")
      set({ findPaidTransactions: value });
    else if (propertyName === "findByBackOrder")
      set({ findByBackOrder: value });
    else if (propertyName === "address") set({ address: value });
    else if (propertyName === "paymentMethod") set({ paymentMethod: value });
    else if (propertyName === "subTotal") set({ subTotal: value });
    else if (propertyName === "sumTotal") set({ sumTotal: value });
    else if (propertyName === "transactionsDetails")
      set({ transactionsDetails: value });
    else if (propertyName === "transactionNumberForReceipt")
      set({ transactionNumberForReceipt: value });
    else if (propertyName === "transactionTypeForReceipt") {
      set({ transactionTypeForReceipt: value });
    } else if (propertyName === "salesRepId") {
      set({ salesRepId: value });
    } else if (propertyName === "__offset") {
      set({ __offset: value });
    }
  },
  search: async (sendOffset: boolean, direction: number = 1) => {
    let payload = JSON.parse(JSON.stringify(get()));
    if (sendOffset === false) {
      payload["__offset"] = 0;
    } else payload["__direction"] = direction;
    delete payload["transactionsDetails"];
    return await httpService.fetch<TransactionDetails[]>(payload, "txn_search");
  },
}));

// Filtered Response
interface FilteredResponse {
  records: TransactionDetails[];
  __offset: number;
}

// Sales Rep Type
type SalesRepresentativeType = { [id: number]: string };

// Transactions Filter
const SearchPanel = ({ type, setShowSpinner }: FilterProps) => {
  const {
    transactionId,
    salesInvoiceId,
    txnStartDate,
    txnEndDate,
    year,
    transactionAmount,
    transactionAmountGreaterThanEqualTo,
    transactionAmountLessThanEqualTo,
    poNumber,
    unitNumber,
    vinNumber,
    findByCore,
    findUnpaidTransactions,
    findPaidTransactions,
    findByBackOrder,
    address,
    paymentMethod,
    search,
    setProperty,
  } = filterStore(
    (state) => ({
      transactionId: state.transactionId,
      salesInvoiceId: state.salesInvoiceId,
      clientId: state.clientId,
      txnStartDate: state.txnStartDate,
      txnEndDate: state.txnEndDate,
      year: state.year,
      transactionAmount: state.transactionAmount,
      transactionAmountGreaterThanEqualTo: state.transactionAmountGreaterThanEqualTo,
      transactionAmountLessThanEqualTo: state.transactionAmountLessThanEqualTo,
      poNumber: state.poNumber,
      unitNumber: state.unitNumber,
      vinNumber: state.vinNumber,
      itemIdentifier: state.itemIdentifier,
      findByCore: state.findByCore,
      findUnpaidTransactions: state.findUnpaidTransactions,
      findPaidTransactions: state.findPaidTransactions,
      findByBackOrder: state.findByBackOrder,
      address: state.address,
      paymentMethod: state.paymentMethod,
      subTotal: state.subTotal,
      sumTotal: state.sumTotal,
      transactionsDetails: state.transactionsDetails,
      setProperty: state.setProperty,
      search: state.search,
    }),
    shallow
  );

  // Sales Reps
  let [salesReps, setSalesReps] = useState<SalesRepresentativeType>({});

  // Flag
  const [areSalesRepLoaded, setAreSalesRepLoaded] = useState<boolean>(false);

  // Fetch Users
  const fetchUsers = () => {
    httpService
      .fetch<SalesRepresentativeType[]>(
        {
          store_id: localStorage.getItem("storeId") || null,
        },
        "um_fetch"
      )
      .then((res: any) => {
        let response: APIResponse<any> = res.data;
        if (response.status === true) {
          let userData = response.data;
          let count = userData.length;
          let tempUsers: SalesRepresentativeType = {};
          tempUsers[0] = "Select...";
          for (let i = 0; i < count; ++i) {
            tempUsers[userData[i].id] = userData[i].name;
          }
          setSalesReps(tempUsers);
          setAreSalesRepLoaded(true);
        } else {
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
        }
      });
  };

  // Fetch Users
  if (areSalesRepLoaded === false) fetchUsers();

  // Toast
  const toast = useToast();

  // Client Fetch Store
  const { fetch: fetchClient } = clientStore();

  const showExtraFilters =
    type === TRANSACTION_TYPES["SI"] || type === TRANSACTION_TYPES["SR"];

  // Txn Types for Receipt
  let txnTypesForReceipt: { [txnType: number]: string } = {
    0: "Select Transaction...",
    ...TRANSACTION_TYPES_RC_RECEIPT_QT,
  };

  // Use Effect
  useEffect(() => {
    setProperty("transactionType", type);
  }, []);

  // Select Load options
  const loadOptionsForClient = (
    searchTerm: string,
    callback: (args: any) => void
  ) => {
    fetchClient(searchTerm, true, false)
      .then((res: any) => {
        let response: APIResponse<ClientDetails[]> = res.data;
        if (response.status === true) {
          callback(buildSearchListForClient(response.data));
        } else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((_: any) => {});
  };

  const [selectedItem, setSelectedItem] = useState<string>("");
  const [itemSuggestions, setItemSuggestions] = useState<any>([]);

  // Item Store
  const { fetchItemDetailsForTransactions } = itemStore();

  const loadOptionsForItem = (searchTerm: string) => {
    fetchItemDetailsForTransactions(searchTerm, localStorage.getItem("storeId"))
      .then((res: any) => {
        if (res.status === 200) {
          let response: APIResponse<ItemDetailsForTransactions[]> = res.data;
          if (response.status === true) {
            setItemSuggestions(buildSearchListForItem(response.data));
          } else
            showToast(
              toast,
              false,
              response.message || UNKNOWN_SERVER_ERROR_MSG
            );
        }
      })
      .catch((_: any) => {});
  };

  /**
   * This method will Handle On Click Handler.
   * @param sendOffset
   * @param direction
   */
  const onClickHandler = (
    sendOffset: boolean = false,
    direction: number = 1
  ) => {
    setShowSpinner(true);
    search(sendOffset, direction)
      .then((res: any) => {
        let result: APIResponse<FilteredResponse> = res.data;
        let subTotal = 0;
        let sumTotal = 0;
        let __offset: number = 0;
        let records: TransactionDetails[] = [];
        if (result.status === true) {
          let response: FilteredResponse =
            result.data || ({ records: [], __offset: 0 } as FilteredResponse);
          records = response.records;
          let noOfRecords = records.length;
          __offset = response.__offset;

          for (let i = 0; i < noOfRecords; ++i) {
            subTotal += records[i].subTotal || 0;
            sumTotal += records[i].sumTotal;
          }
          if (noOfRecords === 0) showToast(toast, true, "No record(s) found.");
        } else {
          showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
        }
        setProperty("subTotal", subTotal);
        setProperty("sumTotal", sumTotal);
        setProperty("__offset", __offset);
        setProperty("transactionsDetails", records);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      })
      .finally(() => {
        setShowSpinner(false);
      });
  };

  // Add Unselected Payment Methods
  let __paymentMethods = paymentMethods;
  let __receiptPaymenteMethods = receiptPaymentMethods;
  __receiptPaymenteMethods[-1] = __paymentMethods[-1] = "Select...";

  return (
    <>
      <Card bgColor="#EEF5FF">
        <CardBody padding={2}>
          <VStack align={"normal"}>
            <HomeNavButton />
            {/* Transaction ID  */}
            <HStack>
              <Box width="40%">
                <_Label fontSize="0.7em">Transaction ID:</_Label>
              </Box>
              <Box width="60%">
                <_Input
                  _key={getUUID()}
                  defaultValue={transactionId}
                  fontSize={"0.75em"}
                  type="number"
                  onBlur={(event: any) => {
                    setProperty(
                      "transactionId",
                      event && event.target
                        ? parseInt(event.target.value)
                        : undefined
                    );
                  }}
                ></_Input>
              </Box>
            </HStack>

            {/* Sales Invoice ID */}
            {type === TRANSACTION_TYPES["SR"] && (
              <HStack>
                <Box width="40%">
                  <_Label fontSize="0.7em">Sales Invoice ID:</_Label>
                </Box>
                <Box width="60%">
                  <_Input
                    _key={getUUID()}
                    defaultValue={salesInvoiceId}
                    fontSize={"0.75em"}
                    type="number"
                    onBlur={(event: any) => {
                      setProperty(
                        "salesInvoiceId",
                        event && event.target
                          ? parseInt(event.target.value)
                          : undefined
                      );
                    }}
                  ></_Input>
                </Box>
              </HStack>
            )}

            {/* Client */}
            <HStack>
              <Box width="30%">
                <_Label fontSize="0.75em">Client:</_Label>
              </Box>
              <Box width="70%">
                <AsyncSelect
                  tabSelectsValue={true}
                  styles={AsyncSelectStyle}
                  cacheOptions={false}
                  loadOptions={loadOptionsForClient}
                  isClearable={true}
                  defaultOptions={false}
                  onChange={(event: any) => {
                    setProperty(
                      "clientId",
                      event && event.value
                        ? parseInt(event.value.id)
                        : undefined
                    );
                  }}
                />
              </Box>
            </HStack>

            {/* Date From */}
            <HStack>
              <Box width="30%">
                <_Label fontSize="0.75em">Date (From):</_Label>
              </Box>
              <Box width="70%">
                <DatePicker
                  wrapperClassName="datepicker_style"
                  dateFormat={"MM/dd/yyyy"}
                  placeholderText="Txn. Date"
                  selected={
                    txnStartDate !== undefined
                      ? new Date(txnStartDate)
                      : new Date()
                  }
                  onChange={(date: any) => {
                    setProperty("txnStartDate", date ? date : undefined);
                  }}
                  closeOnScroll={true}
                  maxDate={new Date()}
                />
              </Box>
            </HStack>

            {/* Date(Till) */}
            <HStack>
              <Box width="30%">
                <_Label fontSize="0.75em">Date (Till):</_Label>
              </Box>
              <Box width="70%">
                <DatePicker
                  wrapperClassName="datepicker_style"
                  dateFormat={"MM/dd/yyyy"}
                  placeholderText="Txn. Date"
                  selected={
                    txnEndDate !== undefined ? new Date(txnEndDate) : new Date()
                  }
                  onChange={(date: any) => {
                    setProperty("txnEndDate", date ? date : undefined);
                  }}
                  closeOnScroll={true}
                  maxDate={new Date()}
                />
              </Box>
            </HStack>

            {/* Month/Year */}
            <HStack>
              <Box width="30%">
                <_Label fontSize="0.75em">Month/Year:</_Label>
              </Box>
              <Box width="70%">
                <HStack>
                  <_Select
                    fontSize={"0.75em"}
                    options={MONTHS}
                    onChange={(event: any) => {
                      setProperty("month", parseInt(event.target.value));
                    }}
                  ></_Select>
                  <_Input
                    _key={getUUID()}
                    defaultValue={year}
                    fontSize={".8em"}
                    fontFamily={numberFont}
                    type="number"
                    onBlur={(event: any) => {
                      setProperty(
                        "year",
                        event && event.target
                          ? parseInt(event.target.value)
                          : undefined
                      );
                    }}
                  ></_Input>
                </HStack>
              </Box>
            </HStack>

            {/* Transaction Amount */}
            <HStack>
              <Box width="40%">
                <_Label fontSize="0.75em">Transaction Amount:</_Label>
              </Box>
              <Box width="60%">
                <_Input
                  _key={getUUID()}
                  defaultValue={transactionAmount}
                  fontSize={".8em"}
                  fontFamily={numberFont}
                  type="number"
                  onBlur={(event: any) => {
                    setProperty(
                      "transactionAmount",
                      event && event.target
                        ? parseFloat(event.target.value)
                        : undefined
                    );
                  }}
                ></_Input>
              </Box>
            </HStack>

            <HStack>
              <Box width="40%">
                <_Label fontSize="0.75em">Transaction Amount &gt;=:</_Label>
              </Box>
              <Box width="60%">
                <_Input
                  _key={getUUID()}
                  defaultValue={transactionAmountGreaterThanEqualTo}
                  fontSize={".8em"}
                  fontFamily={numberFont}
                  type="number"
                  onBlur={(event: any) => {
                    setProperty(
                      "transactionAmountGreaterThanEqualTo",
                      event && event.target
                        ? parseFloat(event.target.value)
                        : undefined
                    );
                  }}
                ></_Input>
              </Box>
            </HStack>

            <HStack>
              <Box width="40%">
                <_Label fontSize="0.75em">Transaction Amount &lt;=:</_Label>
              </Box>
              <Box width="60%">
                <_Input
                  _key={getUUID()}
                  defaultValue={transactionAmountLessThanEqualTo}
                  fontSize={".8em"}
                  fontFamily={numberFont}
                  type="number"
                  onBlur={(event: any) => {
                    setProperty(
                      "transactionAmountLessThanEqualTo",
                      event && event.target
                        ? parseFloat(event.target.value)
                        : undefined
                    );
                  }}
                ></_Input>
              </Box>
            </HStack>

            {/* P.O */}
            {showExtraFilters && (
              <HStack>
                <Box width="40%">
                  <_Label fontSize="0.75em" fontFamily={numberFont}>
                    PO Number:
                  </_Label>
                </Box>
                <Box width="60%">
                  <_Input
                    _key={getUUID()}
                    defaultValue={poNumber}
                    fontSize={".8em"}
                    fontFamily={numberFont}
                    type="text"
                    onBlur={(event: any) => {
                      setProperty(
                        "poNumber",
                        event && event.target ? event.target.value : undefined
                      );
                    }}
                  ></_Input>
                </Box>
              </HStack>
            )}

            {/* Unit */}
            {showExtraFilters && (
              <HStack>
                <Box width="40%">
                  <_Label fontSize="0.75em" fontFamily={numberFont}>
                    Unit Number:
                  </_Label>
                </Box>
                <Box width="60%">
                  <_Input
                    _key={getUUID()}
                    defaultValue={unitNumber}
                    fontSize={".8em"}
                    fontFamily={numberFont}
                    type="text"
                    onBlur={(event: any) => {
                      setProperty(
                        "unitNumber",
                        event && event.target ? event.target.value : undefined
                      );
                    }}
                  ></_Input>
                </Box>
              </HStack>
            )}

            {/* VIN */}
            {showExtraFilters && (
              <HStack>
                <Box width="40%">
                  <_Label fontSize="0.75em" fontFamily={numberFont}>
                    V.I.N:
                  </_Label>
                </Box>
                <Box width="60%">
                  <_Input
                    _key={getUUID()}
                    defaultValue={vinNumber}
                    fontSize={".8em"}
                    fontFamily={numberFont}
                    type="text"
                    onBlur={(event: any) => {
                      setProperty(
                        "vinNumber",
                        event && event.target ? event.target.value : undefined
                      );
                    }}
                  ></_Input>
                </Box>
              </HStack>
            )}

            {/* Part Number */}
            {(showExtraFilters || type === TRANSACTION_TYPES["QT"]) && (
              <HStack>
                <Box width="30%">
                  <_Label fontSize="0.75em">Identifier:</_Label>
                </Box>
                <Box width="70%">
                  <AutoSuggest
                    suggestions={itemSuggestions}
                    onSuggestionsClearRequested={() => setItemSuggestions([])}
                    onSuggestionsFetchRequested={({ value }) => {
                      if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
                      loadOptionsForItem(value);
                    }}
                    onSuggestionSelected={(_: any, { suggestionIndex }) => {
                      setProperty(
                        "itemIdentifier",
                        itemSuggestions[suggestionIndex].value.id
                      );
                    }}
                    getSuggestionValue={(suggestion: any) => {
                      return `${suggestion.value.identifier}`;
                    }}
                    renderSuggestion={(suggestion: any) => (
                      <span>&nbsp;{suggestion.label}</span>
                    )}
                    inputProps={{
                      style: { width: "12.5vw", ...AutoSuggestStyle },
                      placeholder:
                        `Search item...` +
                        (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                          ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                          : ""),
                      value: selectedItem,
                      onChange: (_, { newValue }) => {
                        setSelectedItem(newValue);
                        if (newValue.trim() === "") {
                          setProperty("itemIdentifier", null);
                        }
                      },
                      disabled: false,
                    }}
                    highlightFirstSuggestion={true}
                  ></AutoSuggest>
                </Box>
              </HStack>
            )}
            {/* Sales Representative */}
            {type !== TRANSACTION_TYPES["RC"] && (
              <>
                <HStack>
                  <Box width="40%">
                    <_Label fontSize="0.75em">Sales Rep.:</_Label>
                  </Box>
                  <Box width="60%">
                    <_Select
                      size="sm"
                      fontSize="0.8em"
                      width={"100%"}
                      options={salesReps}
                      onChange={(event: any) => {
                        setProperty("salesRepId", parseInt(event.target.value));
                      }}
                    ></_Select>
                  </Box>
                </HStack>
              </>
            )}
            {showExtraFilters && (
              <>
                <HStack>
                  <Box width="40%">
                    <Checkbox
                      isDisabled={true}
                      key={getUUID()}
                      colorScheme="gray"
                      isChecked={findByCore ? true : false}
                      onChange={() => {
                        setProperty("findByCore", findByCore ^ 1);
                      }}
                    >
                      <_Label fontSize="0.75em">Find by Core?</_Label>
                    </Checkbox>
                  </Box>
                  <Box width="50%">
                    <Checkbox
                      key={getUUID()}
                      colorScheme="red"
                      isChecked={findUnpaidTransactions ? true : false}
                      onChange={() => {
                        setProperty(
                          "findUnpaidTransactions",
                          findUnpaidTransactions ^ 1
                        );
                      }}
                    >
                      <_Label fontSize="0.75em">
                        Find by Unpaid Transaction(s)?
                      </_Label>
                    </Checkbox>
                  </Box>
                </HStack>
                <HStack>
                  <Box width="50%">
                    <Checkbox
                      key={getUUID()}
                      colorScheme="green"
                      isChecked={findPaidTransactions ? true : false}
                      onChange={() => {
                        setProperty(
                          "findPaidTransactions",
                          findPaidTransactions ^ 1
                        );
                      }}
                    >
                      <_Label fontSize="0.75em">
                        Find Paid Transaction(s)?
                      </_Label>
                    </Checkbox>
                  </Box>
                  <Box width="50%">
                    <Checkbox
                      key={getUUID()}
                      colorScheme="purple"
                      isChecked={findByBackOrder ? true : false}
                      onChange={() => {
                        setProperty("findByBackOrder", findByBackOrder ^ 1);
                      }}
                    >
                      <_Label fontSize="0.75em">Find By BackOrder?</_Label>
                    </Checkbox>
                  </Box>
                </HStack>
              </>
            )}

            {/* Find By Address */}
            <HStack>
              <Box width="30%">
                <_Label fontSize="0.75em">Find by Address:</_Label>
              </Box>
              <Box width="70%">
                <_Input
                  _key={getUUID()}
                  defaultValue={address}
                  fontSize={".8em"}
                  fontFamily={numberFont}
                  type="text"
                  onBlur={(event: any) => {
                    setProperty(
                      "address",
                      event && event.target
                        ? event.target.value.trim()
                        : undefined
                    );
                  }}
                ></_Input>
              </Box>
            </HStack>
            {(showExtraFilters || type === TRANSACTION_TYPES["RC"]) && (
              <HStack>
                <Box width="30%">
                  <_Label fontSize="0.75em">Payment Method:</_Label>
                </Box>
                <Box width="70%">
                  <_Select
                    value={paymentMethod}
                    variant={"filled"}
                    borderRadius={2.5}
                    fontSize="0.8em"
                    options={
                      type === TRANSACTION_TYPES["RC"]
                        ? __receiptPaymenteMethods
                        : __paymentMethods
                    }
                    onChange={(event: any) => {
                      setProperty(
                        "paymentMethod",
                        event && event.target
                          ? parseInt(event.target.value)
                          : undefined
                      );
                    }}
                  ></_Select>
                </Box>
              </HStack>
            )}
            {type === TRANSACTION_TYPES["RC"] && (
              <VStack align="start">
                <_Divider margin={1} />
                <HStack>
                  <FcInfo color="#4781F2" />
                  <_Label fontSize="0.7em">
                    <u>Search Txn By Type and ID.</u>
                  </_Label>
                </HStack>
                <HStack>
                  <_Label fontSize="0.7em">Transaction Type:</_Label>
                  <_Select
                    fontSize="0.8em"
                    options={txnTypesForReceipt}
                    onChange={(event: any) => {
                      setProperty(
                        "transactionTypeForReceipt",
                        event && event.target
                          ? parseInt(event.target.value)
                          : undefined
                      );
                    }}
                  ></_Select>
                </HStack>
                <HStack>
                  <_Label fontSize="0.7em">Transaction Number:</_Label>
                  <_Input
                    defaultValue={address}
                    fontSize={".8em"}
                    fontFamily={numberFont}
                    type="text"
                    onBlur={(event: any) => {
                      setProperty(
                        "transactionNumberForReceipt",
                        event && event.target
                          ? event.target.value.trim()
                          : undefined
                      );
                    }}
                  ></_Input>
                </HStack>
              </VStack>
            )}
            <_Divider margin={1}></_Divider>
            <HStack>
              <_Button
                fontSize={"1.3em"}
                icon={<FaSearchengin />}
                color="#BDB5D5"
                bgColor={navBgColor}
                label="Search Transaction(s)"
                onClick={() => {
                  onClickHandler(false);
                }}
              ></_Button>
            </HStack>
            <_Divider margin={1}></_Divider>
            <HStack>
              <_Button
                fontSize={"1.2em"}
                icon={<GrPrevious />}
                color="green"
                bgColor={"#D8E1DC"}
                label="Previous"
                onClick={() => {
                  onClickHandler(true, -1);
                }}
              ></_Button>
              <_Button
                fontSize={"1.2em"}
                icon={<GrNext />}
                color="green"
                bgColor={"#D8E1DC"}
                label="Next"
                onClick={() => {
                  onClickHandler(true, 1);
                }}
              ></_Button>
            </HStack>
          </VStack>
        </CardBody>
      </Card>
    </>
  );
};

// VIew URL Table
export const VIEW_URL_TABLE: { [key: number]: string } = {
  1: "sales_invoice_update?id=",
  2: "sales_return_update?id=",
  3: "credit_note_update?id=",
  4: "debit_note_update?id=",
  5: "quotation_update?id=",
  6: "receipt_update?id=",
};

/**
 * Transactions List
 */
const TransactionsList = memo(({ type }: { type: number }) => {
  const { subTotal, sumTotal, transactionsDetails } = filterStore(
    (state) => ({
      subTotal: state.subTotal,
      sumTotal: state.sumTotal,
      transactionsDetails: state.transactionsDetails,
    }),
    shallow
  );

  return (
    <>
      <Box marginBottom={2}>
        {sumTotal && sumTotal > 0 ? (
          <Card marginBottom={5} borderRadius={2}>
            <CardBody padding={2}>
              <HStack>
                <_Label fontSize="0.8em">
                  Sum Total{" "}
                  {subTotal && subTotal > 0 ? <>(Sub Total)</> : <></>}:
                </_Label>
                <CurrencyIcon></CurrencyIcon>
                <Badge
                  fontFamily={numberFont}
                  bg="white"
                  color={"black"}
                  letterSpacing={2}
                >
                  {formatNumberWithDecimalPlaces(sumTotal, 2)}{" "}
                  {subTotal && subTotal > 0 ? (
                    <>( {formatNumberWithDecimalPlaces(subTotal, 2)} )</>
                  ) : (
                    <></>
                  )}
                </Badge>
                <_Label fontSize="0.8em">| No. of Record(s):</_Label>
                <Badge
                  fontFamily={numberFont}
                  bg="white"
                  color={"black"}
                  letterSpacing={2}
                >
                  {transactionsDetails.length}
                </Badge>
              </HStack>
            </CardBody>
          </Card>
        ) : (
          <></>
        )}
        <Card borderRadius={2}>
          <CardHeader padding={2}>
            <HStack>
              <Box width="1%" textAlign={"center"}>
                <_Label letterSpacing={5} fontSize="0.8em">
                  &nbsp;
                </_Label>
              </Box>
              <Box width="13%" textAlign={"center"}>
                <_Label letterSpacing={5} fontSize="0.8em">
                  TXN #
                </_Label>
              </Box>
              {type === TRANSACTION_TYPES["SR"] && (
                <Box width="13%" textAlign={"center"}>
                  <_Label letterSpacing={5} fontSize="0.8em">
                    SALES INV. #
                  </_Label>
                </Box>
              )}
              <Box
                width={type === TRANSACTION_TYPES["SR"] ? "27%" : "40%"}
                textAlign={"center"}
              >
                <_Label letterSpacing={5} fontSize="0.8em">
                  CLIENT NAME
                </_Label>
              </Box>
              <Box width="10%" textAlign={"center"}>
                <_Label letterSpacing={5} fontSize="0.8em">
                  TXN DATE
                </_Label>
              </Box>
              <Box width="20%" textAlign={"center"}>
                <_Label letterSpacing={5} fontSize="0.8em">
                  TXN AMOUNT
                </_Label>
              </Box>
            </HStack>
          </CardHeader>
        </Card>
      </Box>
      <Box width="100%" height="90vh" overflow="auto">
        {transactionsDetails.map((txn: TransactionDetails, index: number) => {
          return (
            <TransactionDetailsRow
              key={getUUID()}
              id={txn.id}
              type={type}
              date={txn.date}
              clientName={txn.clientName}
              sumTotal={txn.sumTotal}
              subTotal={txn.subTotal}
              cogs={txn.cogs}
              creditAmount={txn.creditAmount}
              bgColor={index & 1 ? "white" : "#EEF5FF"}
              salesInvoiceId={txn.salesInvoiceId}
            />
          );
        })}
      </Box>
    </>
  );
});

/**
 * Transaction Details Row.
 * @param param
 * @returns
 */
const TransactionDetailsRow = memo(
  ({
    id,
    type,
    date,
    clientName,
    subTotal,
    sumTotal,
    cogs,
    creditAmount,
    bgColor,
    salesInvoiceId,
  }: _TransactionDetailsRow) => {
    let profitMarginThisTransaction = 0;
    if (type === TRANSACTION_TYPES["SI"] && cogs && subTotal && cogs > 0) {
      profitMarginThisTransaction = calculateProfitMargin(subTotal, cogs);
    }

    return (
      <Card
        bgColor={bgColor}
        borderRadius={2}
        borderLeftWidth={5}
        borderLeftColor={"#5D3FD3"}
        marginBottom={1}
      >
        <CardBody padding={2}>
          <HStack>
            <Box width="1%" textAlign="center">
              {type !== TRANSACTION_TYPES["RC"] && (
                <Popover>
                  <PopoverTrigger>
                    <FcInfo color="#4781F2" />
                  </PopoverTrigger>
                  <PopoverContent borderColor="blue.800" borderRadius={2}>
                    <PopoverArrow />
                    <PopoverCloseButton />
                    <PopoverBody>
                      <VStack align={"start"}>
                        {type === TRANSACTION_TYPES["SI"] && (
                          <HStack>
                            <Badge bgColor="#E6E6FA" letterSpacing={2}>
                              PROFIT MARGIN:
                            </Badge>
                            <_Label fontSize="0.8em" fontFamily={numberFont}>
                              {formatNumberWithDecimalPlaces(
                                profitMarginThisTransaction,
                                2
                              )}{" "}
                              %
                            </_Label>
                          </HStack>
                        )}
                        {type !== TRANSACTION_TYPES["RC"] && creditAmount && (
                          <HStack>
                            <Badge
                              letterSpacing={2}
                              bgColor={
                                colorMap.creditAmount[
                                  creditAmount === 0 ? 0 : 1
                                ]
                              }
                            >
                              Amount Owing:
                            </Badge>
                            <CurrencyIcon></CurrencyIcon>
                            <_Label fontFamily={numberFont} fontSize="0.8em">
                              {formatNumberWithDecimalPlaces(creditAmount, 2)}
                            </_Label>
                          </HStack>
                        )}
                      </VStack>
                    </PopoverBody>
                  </PopoverContent>
                </Popover>
              )}
            </Box>
            <Box width="12%" textAlign="center">
              <Badge
                fontFamily={numberFont}
                letterSpacing={2}
                fontSize="0.75em"
                borderRadius={2}
                bgColor={
                  colorMap["creditAmount"][
                    creditAmount && creditAmount > 0 ? 1 : 0
                  ]
                }
              >
                {id}
              </Badge>
            </Box>
            {type === TRANSACTION_TYPES["SR"] && (
              <Box width="12%" textAlign="center">
                <Badge
                  fontFamily={numberFont}
                  letterSpacing={2}
                  fontSize="0.75em"
                  borderRadius={2}
                  color="#5D3FD3"
                  bgColor="#CCCCFF"
                >
                  {salesInvoiceId}
                </Badge>
              </Box>
            )}
            <Box
              width={type === TRANSACTION_TYPES["SR"] ? "31%" : "43%"}
              textAlign="center"
            >
              <_Label letterSpacing={2} fontSize="0.8em">
                {clientName}
              </_Label>
            </Box>
            <Box width="8%" textAlign="center">
              <_Label
                fontFamily={numberFont}
                letterSpacing={2}
                fontSize="0.8em"
              >
                {date}
              </_Label>
            </Box>
            <Box width="20%" textAlign="center">
              <Center>
                <HStack>
                  <CurrencyIcon></CurrencyIcon>
                  <_Label
                    fontFamily={numberFont}
                    letterSpacing={2}
                    fontSize="0.8em"
                  >
                    {formatNumberWithDecimalPlaces(sumTotal, 2)}
                  </_Label>
                </HStack>
              </Center>
            </Box>
            <Box width="10%" textAlign="center">
              <_Button
                bgColor={"#ddd3ee"}
                borderRadius={1}
                fontSize={"1.4em"}
                icon={<FcDocument />}
                color="#7B66FF"
                label={`View`}
                onClick={() => {
                  window.open(VIEW_URL_TABLE[type] + id, "_blank");
                }}
              ></_Button>
            </Box>
          </HStack>
        </CardBody>
      </Card>
    );
  }
);

/**
 * Filter Component
 * @param type
 * @returns
 */
const Filter = ({ type }: FilterProps) => {
  redirectIfInvalidSession();

  const [showSpinner, setShowSpinner] = useState<boolean>(false);
  return (
    isSessionActive() && (
      <>
        <Grid templateColumns="repeat(5, 1fr)" gap={2}>
          <GridItem colSpan={1}>
            <SearchPanel type={type} setShowSpinner={setShowSpinner} />
          </GridItem>
          <GridItem colSpan={4}>
            {showSpinner ? (
              <VStack paddingTop={"15%"} spacing={5}>
                <Spinner
                  label="Fetching Transactions..."
                  thickness="2px"
                  speed="1s"
                  emptyColor="gray.100"
                  color="#8565c4"
                  boxSize={"24vh"}
                />
                <_Label
                  fontSize="1em"
                  fontFamily={numberFont}
                  letterSpacing={5}
                >
                  Fetching Transactions...
                </_Label>
              </VStack>
            ) : (
              <TransactionsList type={type} />
            )}
          </GridItem>
        </Grid>
      </>
    )
  );
};

export default Filter;
