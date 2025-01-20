import {
  Badge,
  Box,
  Card,
  CardBody,
  Center,
  Checkbox,
  HStack,
  Spinner,
  Switch,
  VStack,
  useToast,
} from "@chakra-ui/react";
import {
  AutoSuggestStyle,
  navBgColor,
  numberFont,
} from "../../../shared/style";
import { APIResponse } from "../../../service/api-client";
import { ClientDetails, clientStore } from "../../client/store";
import {
  buildSearchListForClient,
  formatNumberWithDecimalPlaces,
  isSessionActive,
  redirectIfInvalidSession,
  showToast,
} from "../../../shared/functions";
import DatePicker from "react-datepicker";
import {
  HomeNavButton,
  _Button,
  _Divider,
  _Label,
} from "../../../shared/Components";
import { TfiReceipt } from "react-icons/tfi";
import { MdAlternateEmail } from "react-icons/md";
import { memo, useState } from "react";
import {
  AttributeType,
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../../shared/config";
import AutoSuggest from "react-autosuggest";
import { FcMoneyTransfer } from "react-icons/fc";
import {
  CustomerAgedSummary,
  customerStatementReport,
  SelectedClientsType,
} from "./customerStatementStore";
import { shallow } from "zustand/shallow";
import { ImCancelCircle } from "react-icons/im";

// Content Font Style
const contentFontStyle: AttributeType = {
  fontSize: "0.7em",
  fontFamily: numberFont,
  letterSpacing: 2,
  textTransform: "uppercase",
};

/**
 * Customer Detail Row
 * @param customer
 * @returns
 */
const CustomerDetailRow = memo(
  ({ customer, isEmailSent }: { customer: CustomerAgedSummary, isEmailSent?: boolean}) => {
    // Customer Statement Report
    const { getSelectedClients, setExcludedClients } =
      customerStatementReport(
        (state) => ({
          getSelectedClients: state.getSelectedClients,
          setExcludedClients: state.setExcludedClients,
        }),
        shallow
      );

    // Rerender flag
    const [rerender, setRerender] = useState<number>(0);
    
    // Customer Aged Summary Detail
    let customerAgedSummaryDetail: CustomerAgedSummary = getSelectedClients()[customer.client_id];

    // Exclusion Status
    let isExcluded: boolean = customerAgedSummaryDetail.is_excluded ? true : false;
      
    // Select Badge Style based on exclusion status
    let badgeStyle: AttributeType = {};
    if(isExcluded === true) {
      badgeStyle["colorScheme"] = "red";
      badgeStyle["variant"] = "outline";
    }

    else if(isEmailSent === true) {
      badgeStyle["colorScheme"] = "green";
    }

    else {
      badgeStyle["variant"] = "none";
    }

    return (
      isSessionActive() && (
        <HStack width="100%">
          <Box width="30%">
            <Badge {...contentFontStyle} {...badgeStyle}>{customer.client_name}</Badge>
          </Box>
          <Box width="10%">
            <_Label {...contentFontStyle}>
              $ {formatNumberWithDecimalPlaces(customer.total, 2)}
            </_Label>
          </Box>
          <Box width="10%">
            <_Label {...contentFontStyle}>
              $ {formatNumberWithDecimalPlaces(customer.current, 2)}
            </_Label>
          </Box>
          <Box width="10%">
            <_Label {...contentFontStyle}>
              $ {formatNumberWithDecimalPlaces(customer["31-60"], 2)}
            </_Label>
          </Box>
          <Box width="10%">
            <_Label {...contentFontStyle}>
              $ {formatNumberWithDecimalPlaces(customer["61-90"], 2)}
            </_Label>
          </Box>
          <Box width="10%">
            <_Label {...contentFontStyle}>
              $ {formatNumberWithDecimalPlaces(customer["91+"], 2)}
            </_Label>
          </Box>
          <Box width="10%">
            <Checkbox
              size="md"
              colorScheme="red"
              icon={<ImCancelCircle />}
              onChange={(_: any) => {
                setExcludedClients(customer.client_id);
                setRerender(rerender + 1);
              }}
            ></Checkbox>
          </Box>
        </HStack>
      )
    );
  }
);

/**
 * Customer List Header
 */
const CustomerListHeader = memo(() => {
  const { noOfSelectedClients } = customerStatementReport(
    (state) => ({
      noOfSelectedClients: state.noOfSelectedClients,
    }),
    shallow
  );
  if (isSessionActive() && noOfSelectedClients > 0) {
    return (
      <HStack width="100%">
            <Box width="30%">
              <Badge {...contentFontStyle} variant={"outline"}>
                Customer Name
              </Badge>
            </Box>
            <Box width="10%">
              <Badge {...contentFontStyle} variant={"outline"}>
                Total
              </Badge>
            </Box>
            <Box width="10%">
              <Badge {...contentFontStyle} variant={"outline"}>
                Current
              </Badge>
            </Box>
            <Box width="10%">
              <Badge {...contentFontStyle} variant={"outline"}>
                30-60
              </Badge>
            </Box>
            <Box width="10%">
              <Badge {...contentFontStyle} variant={"outline"}>
                61-90
              </Badge>
            </Box>
            <Box width="10%">
              <Badge {...contentFontStyle} variant={"outline"}>
                91+
              </Badge>
            </Box>
            <Box width="10%"><Badge {...contentFontStyle} variant="solid" colorScheme="red" >Excluded Client(s)</Badge></Box>
      </HStack>
    );
  }
})

/**
 * Customer List
 * @param list
 * @returns
 */
// const CustomerList = memo(() => {
//   // Customer Statement Report
//   const { customerAgedSummaryList } = customerStatementReport(
//     (state) => ({
//       customerAgedSummaryList: state.customerAgedSummaryList,
//     }),
//     shallow
//   );

//   // Customer List
//   const customerList: any = customerAgedSummaryList.map(
//     (customer: CustomerAgedSummary) => {
//       return <CustomerDetailRow customer={customer} key={customer.client_id}/> ;
//     }
//   );
// });

/**
 * Customer Aged Summary List.
 * @returns
 */
const CustomerAgedSummaryList = memo(() => {
  const toast = useToast();
  const { 
    attachTransactions, 
    generateRecordOfAllTransactions, 
    startDate,
    endDate, 
    sortAscending, 
    storeId, 
    email,
    getSelectedClients,
    fetchCustomerAgedSummary, 
    setDetail, 
    getNoOfSelectedClients 
  } =
    customerStatementReport(
      (state) => ({
        startDate: state.startDate,
        endDate: state.endDate,
        attachTransactions: state.attachTransactions,
        generateRecordOfAllTransactions: state.generateRecordOfAllTransactions,
        sortAscending: state.sortAscending,
        storeId: state.storeId,
        email: state.email,
        getSelectedClients: state.getSelectedClients,
        fetchCustomerAgedSummary: state.fetchCustomerAgedSummary,
        setDetail: state.setDetail,
        getNoOfSelectedClients: state.getNoOfSelectedClients,
      }),
      shallow
    );
  const [isButtonDisabled, setIsButtonDisabled] = useState<boolean>(false);

  // Is Loading
  const [isLoading, setIsLoading] = useState<boolean>(false);

  // Clients List
  const [clientsList, setClientsList] = useState<number[]>([]);

  // Selected Clients 
  const [selectedClients, setSelectedClients] = useState<SelectedClientsType>({});

  // Rerender
  const [rerender, setRerender] = useState<number>(0);

  // Fetch Customer Aged summary Handler
  const fetchCustomerAgedSummaryHandler = () => {
    setIsButtonDisabled(true);
    setIsLoading(true);
    fetchCustomerAgedSummary()
      .then((res: any) => {
        let response: APIResponse<CustomerAgedSummary[]> = res.data;
        if (response.status && response.data) {
          
          // Selected clients
          let temp: AttributeType = {};
          let tempList: number[] = [];
          let noOfClients: number = response.data.length || 0;
          for(let i = 0; i < noOfClients; ++i) {
            tempList.push(response.data[i].client_id);
            temp[response.data[i].client_id] = response.data[i];
          }

          setSelectedClients(temp);
          setClientsList(tempList);
          setDetail("selectedClients", temp);
          setDetail("noOfSelectedClients", noOfClients);
        } else {
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
          setDetail("selectedClients", {});
        }
      })
      .catch((err: any) => {
        setDetail("selectedClients", {});
        showToast(toast, false, err.message);
      })
      .finally(() => {
        setIsButtonDisabled(false);
        setIsLoading(false);
      });
  };

  let payload: AttributeType = {
    startDate: startDate ? startDate?.toISOString().substring(0, 10) : "",
    endDate: endDate ? endDate?.toISOString().substring(0, 10) : "",
    attachTransactions: (attachTransactions ? 1 : 0).toString(),
    generateRecordOfAllTransactions: (generateRecordOfAllTransactions
      ? 1
      : 0
    ).toString(),
    storeId: storeId,
  };



  /**
   * Send Batch Emails
   */
  const sendBatchEmails = () => {

    // Return if no client is loaded
    let clientIds: string[] = Object.keys(selectedClients);
    if(clientIds.length === 0) return;

    // Disable Button
    setIsButtonDisabled(true);

    // Refresh Clients list
    setSelectedClients(getSelectedClients());

    // Fetch No. of selected clients
    let noOfSelectedClients: number = getNoOfSelectedClients();

    // Current Client Id
    let clientId: number = 12782;
  
    for(let index = 0; index < noOfSelectedClients; ++index) {
      clientId = parseInt(clientIds[index]);
      if(selectedClients[clientId].is_excluded === false) {
        payload["clientId"] = clientId;

        email(payload).then((res: any) => {
          let result: APIResponse = res.data;
          if (result.status !== true) {
            showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
          } else {
            selectedClients[clientId].is_email_sent = true;
          }
        })
        .catch((_: any) => {
          selectedClients[clientId].is_email_sent = false;
        }).finally (() => {
          setRerender(rerender);
        });
      }
    }
  }

  const LAYOUT_CODE_1: any = <>
                <_Label fontSize="0.8em" textTransform={"uppercase"}>
                  Fetch Clients By Aged Summary
                </_Label>
                <HStack width="100%">
                  <HStack>
                    <_Label fontSize="0.8em">Sort by Lowest Amount owing:</_Label>
                    <Switch
                      id="email-alerts"
                      colorScheme="teal"
                      onChange={() => {
                        setDetail("setAscendingSort", sortAscending ^ 1);
                      }}
                    />
                  </HStack>
                  <_Button
                    isDisabled={isButtonDisabled}
                    icon={<FcMoneyTransfer />}
                    color="#90EE90"
                    bgColor="black"
                    fontSize="1.2em"
                    label="Fetch Customer Aged Summary"
                    onClick={fetchCustomerAgedSummaryHandler}
                    width="25%"
                  ></_Button>
                  <_Button
                    isDisabled={isButtonDisabled}
                    icon={<MdAlternateEmail color="#0096FF" />}
                    color="white"
                    bgColor="black"
                    fontSize="1.2em"
                    label="Send Batch Emails"
                    onClick={sendBatchEmails}
                    width="25%"
                  ></_Button>
                </HStack>
                <_Divider margin={0} />
              </>;

  const LAYOUT_CODE_2: any = 
    <VStack paddingTop={10}>
      <Center>
        <Spinner
            label="Loading Customer Aged Summary"
            thickness="2px"
            speed="1s"
            emptyColor="gray.100"
            color="#8565c4"
            boxSize={"24vh"}/>
        </Center>
    </VStack>;

  return (
    isSessionActive() && (
      <>
        <VStack align="start" width="100%">
          {LAYOUT_CODE_1}
          {isLoading === false && <>
            <Box height="60vh" overflowY={"scroll"} width="100%">
              <VStack align="start">
                <CustomerListHeader/>
                {clientsList.map((clientId: number) => {
                  return <CustomerDetailRow key={clientId} customer={selectedClients[clientId]} isEmailSent={selectedClients[clientId].is_email_sent}/>;
                })}
              </VStack>
            </Box>
          </>}
        </VStack>
        {isLoading && LAYOUT_CODE_2}
      </>
    )
  );
});

/**
 * Customer Statement
 * @returns
 */
const CustomerStatementV2 = memo(() => {
  redirectIfInvalidSession();

  // Customer Statement Report
  const {
    clientId,
    startDate,
    endDate,
    attachTransactions,
    generateRecordOfAllTransactions,
    storeId,
    email,
    print,
    setDetail,
  } = customerStatementReport(
    (state) => ({
      clientId: state.clientId,
      startDate: state.startDate,
      endDate: state.endDate,
      attachTransactions: state.attachTransactions,
      generateRecordOfAllTransactions: state.clientId,
      storeId: state.storeId,
      email: state.email,
      print: state.print,
      setDetail: state.setDetail,
    }),
    shallow
  );

  // Toast Handle
  const toast = useToast();

  // Fetch
  const { fetch } = clientStore();

  // Button State
  const [disableButton, setDisableButton] = useState<boolean>(false);

  // Handle Operation
  const handleOperation = (opType: string) => {
    setDisableButton(true);
    let payload: AttributeType = {
      clientId: clientId ? clientId?.toString() : "",
      startDate: startDate ? startDate?.toISOString().substring(0, 10) : "",
      endDate: endDate ? endDate?.toISOString().substring(0, 10) : "",
      attachTransactions: (attachTransactions ? 1 : 0).toString(),
      generateRecordOfAllTransactions: (generateRecordOfAllTransactions
        ? 1
        : 0
      ).toString(),
      storeId: storeId,
    };

    if (opType === "email") {
      email(payload)
        .then((res: any) => {
          let result: APIResponse = res.data;
          if (result.status !== true) {
            showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
            setDisableButton(false);
          } else {
            showToast(toast, true);
          }
        })
        .catch((err: any) => {
          showToast(toast, err.status, err.message);
          setDisableButton(false);
        });
    } else {
      print(payload);
      setDisableButton(false);
    }
  };

  // For AutoSuggestion
  const [selectedClient, setSelectedClient] = useState<string>("");
  const [clientSuggestions, setClientSuggestions] = useState<any>([]);

  // Select Load options
  const loadOptions = (searchTerm: string) => {
    fetch(searchTerm, true)
      .then((res: any) => {
        let response: APIResponse<ClientDetails[]> = res.data;
        if (response.status === true)
          setClientSuggestions(buildSearchListForClient(response.data));
        else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      });
  };

  return (
    isSessionActive() && (
      <Box width="100%">
        <Card
          height={window.screen.availHeight - window.screen.availHeight * 0.15}
        >
          <CardBody>
            <Box width="20%">
              <HomeNavButton></HomeNavButton>
            </Box>
            <VStack align="start" width="100%">
              <HStack width="100%">
                <Box width="25%">
                  <AutoSuggest
                    suggestions={clientSuggestions}
                    onSuggestionsClearRequested={() => setClientSuggestions([])}
                    onSuggestionsFetchRequested={({ value }) => {
                      if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
                      loadOptions(value);
                    }}
                    onSuggestionSelected={(_: any, { suggestionIndex }) => {
                      setDetail(
                        "clientId",
                        clientSuggestions[suggestionIndex].value.id
                      );
                    }}
                    getSuggestionValue={(suggestion: any) => {
                      return `${suggestion.value.primaryDetails.name}`;
                    }}
                    renderSuggestion={(suggestion: any) => (
                      <span>&nbsp;{suggestion.label}</span>
                    )}
                    inputProps={{
                      style: {
                        width: "22vw",
                        ...AutoSuggestStyle,
                      },
                      placeholder:
                        `Search clients...` +
                        (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                          ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                          : ""),
                      value: selectedClient,
                      onChange: (_, { newValue }) => {
                        setSelectedClient(newValue);
                        if (newValue.trim() === "") {
                          setDetail("clientId", null);
                        }
                      },
                    }}
                    highlightFirstSuggestion={true}
                  ></AutoSuggest>
                </Box>
                <Box>
                  <HStack spacing={10}>
                    <_Label fontSize="0.8em">Start Date:</_Label>
                    <DatePicker
                      disabled={clientId === null}
                      wrapperClassName="datepicker_style"
                      dateFormat={"MM/dd/yyyy"}
                      placeholderText="Txn. Date"
                      selected={startDate}
                      onChange={(date) => {
                        if (date !== null) {
                          setDetail("startDate", date);
                        }
                      }}
                      closeOnScroll={true}
                      maxDate={new Date()}
                    />
                  </HStack>
                </Box>
                <Box>
                  <HStack spacing={10}>
                    <_Label fontSize="0.8em">End Date:</_Label>
                    <DatePicker
                      disabled={clientId === null}
                      wrapperClassName="datepicker_style"
                      dateFormat={"MM/dd/yyyy"}
                      placeholderText="Txn. Date"
                      selected={endDate}
                      onChange={(date) => {
                        if (date !== null) {
                          setDetail("endDate", date);
                        }
                      }}
                      closeOnScroll={true}
                      maxDate={new Date()}
                    />
                  </HStack>
                </Box>
              </HStack>
            </VStack>
            <VStack align={"start"} marginTop={5}>
              <HStack>
                <Box>
                  <_Button
                    isDisabled={clientId === null}
                    color="white"
                    fontSize="1.2em"
                    bgColor={navBgColor}
                    label="Show Statement"
                    onClick={() => {
                      handleOperation("print");
                    }}
                    icon={<TfiReceipt color="#00A36C" />}
                  ></_Button>
                </Box>
                <Box>
                  <_Button
                    isDisabled={clientId === null || disableButton}
                    color="white"
                    fontSize="1.2em"
                    bgColor={navBgColor}
                    label="Email Statement(s)"
                    onClick={() => {
                      handleOperation("email");
                    }}
                    icon={<MdAlternateEmail color="#0096FF" />}
                  ></_Button>
                </Box>
                <Box>
                  <HStack spacing={5}>
                    <Badge colorScheme={"green"} fontSize="0.8em">
                      ATTACH TRANSACTIONS?
                    </Badge>
                    <Switch
                      isDisabled={clientId === null}
                      colorScheme="orange"
                      size="md"
                      onChange={() => {
                        setDetail("attachTransactions", !attachTransactions);
                      }}
                    />
                  </HStack>
                </Box>
                <Box>
                  <HStack spacing={5}>
                    <Badge colorScheme={"green"} fontSize="0.8em">
                      Generate Record of All Transactions?
                    </Badge>
                    <Switch
                      isDisabled={clientId === null}
                      colorScheme="blue"
                      size="md"
                      onChange={() => {
                        setDetail(
                          "generateRecordOfAllTransactions",
                          !generateRecordOfAllTransactions
                        );
                      }}
                    />
                  </HStack>
                </Box>
              </HStack>
            </VStack>
            <_Divider />
            <CustomerAgedSummaryList />
          </CardBody>
        </Card>
      </Box>
    )
  );
});

export default CustomerStatementV2;
