import {
  Badge,
  Box,
  Card,
  CardBody,
  HStack,
  SimpleGrid,
  Switch,
  Tag,
  TagCloseButton,
  TagLabel,
  VStack,
  useToast,
} from "@chakra-ui/react";
import { AutoSuggestStyle, navBgColor } from "../../../shared/style";
import { APIResponse, HTTPService } from "../../../service/api-client";
import { ClientDetails, clientStore } from "../../client/store";
import {
  buildSearchListForClient,
  checkForValidSession,
  getUUID,
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
import { useState } from "react";
import {
  APP_HOST,
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../../shared/config";
import { IoMdAddCircle } from "react-icons/io";
import AutoSuggest from "react-autosuggest";
import { FcMoneyTransfer } from "react-icons/fc";

/** HTTP Service */
const httpService = new HTTPService();

/**
 * Customer Details List
 */
interface CustomerDetailsList {
  id: number;
  name: string;
}

/**
 * Customer Details List Props
 */
interface CustomerDetailsListProps {
  list: { [id: number]: CustomerDetailsList };
  deleteClient: any;
}

/**
 * Customer List
 * @param list
 * @param deleteClient
 * @returns
 */
const CustomerList = ({ list, deleteClient }: CustomerDetailsListProps) => {
  return (
    <SimpleGrid columns={2} spacingX={"1em"} spacingY={"1em"}>
      {Object.keys(list).map((clientId: string, index: number) => {
        return (
          <HStack spacing={0} padding={1} width="100%" key={getUUID()}>
            <Tag
              color="black"
              bgColor={["#CBC3E3", "#C3E3CB"][index & 1]}
              borderRadius={2}
            >
              <TagLabel letterSpacing={2}>
                {list[parseInt(clientId)].name}
              </TagLabel>
              <TagCloseButton
                color="#EE4B2B"
                onClick={() => {
                  deleteClient(list[parseInt(clientId)].id);
                }}
              />
            </Tag>
          </HStack>
        );
      })}
    </SimpleGrid>
  );
};

/**
 * Customer Aged Summary
 */
interface CustomerAgedSummary {
  "31-60": number;
  "61-90": number;
  "91+": number;
  client_id: number;
  client_name: number;
  current: number;
  phone_number: string;
  total: number;
}

/**
 * Customer Aged Summary List.
 * @returns
 */
const CustomerAgedSummaryList = () => {
  const toast = useToast();
  const currentStore = parseInt(localStorage.getItem("storeId") || "0");
  const [date, setDate] = useState<Date>(new Date());
  const [sortAscending, setSortAscending] = useState<number>(0);
  const [customerAgedSummary, setCustomerAgedSummary] = useState<
    CustomerAgedSummary[]
  >([]);
  const [isButtonDisabled, setIsButtonDisabled] = useState<boolean>(false);

  /**
   * Fetch Customer Aged Summary
   */
  const fetchCustomerAgedSummary = () => {
    setIsButtonDisabled(true);
    httpService
      .fetch(
        {
          storeId: currentStore,
          tillDate: date,
          sort: sortAscending,
        },
        "customer_aged_summary"
      )
      .then((res: any) => {
        let response: APIResponse<CustomerAgedSummary[]> = res.data;
        if (response.status && response.data) {
          setCustomerAgedSummary(response.data);
        } else {
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
          setCustomerAgedSummary([]);
        }
      })
      .catch((err: any) => {
        setCustomerAgedSummary([]);
        showToast(toast, false, err.message);
      })
      .finally(() => {
        setIsButtonDisabled(false);
      });
  };
  return (
    <VStack align="start">
      <HStack width="100%">
        <_Label fontSize={"0.8em"}>Generate Client Summary till:</_Label>
        <DatePicker
          wrapperClassName="datepicker_style"
          dateFormat={"MM/dd/yyyy"}
          placeholderText="Txn. Date"
          selected={date}
          onChange={(date: any) => {
            setDate(date);
          }}
          closeOnScroll={true}
          maxDate={new Date()}
        />
        <HStack>
          <_Label fontSize="0.8em">Sort by Lowest Amount owing:</_Label>
          <Switch
            id="email-alerts"
            colorScheme="teal"
            onChange={() => {
              setSortAscending(sortAscending ^ 1);
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
          onClick={fetchCustomerAgedSummary}
          width="25%"
        ></_Button>
      </HStack>
    </VStack>
  );
};

/**
 * Customer Statement
 * @returns
 */
const CustomerStatement = () => {
  checkForValidSession();
  const toast = useToast();

  // Fetch
  const { fetch } = clientStore();

  // States
  const [clientId, setClientId] = useState<number | null>(null);
  const [clientName, setClientName] = useState<string | null>(null);
  const [startDate, setStartDate] = useState<Date | null>(null);
  const [endDate, setEndDate] = useState<Date>(new Date());
  const [attachTransactions, setAttachTransactions] = useState<boolean>(false);
  const [generateRecordOfAllTransactions, setGenerateRecordOfAllTransactions] =
    useState<boolean>(false);

  // Button State
  const [disableButton, setDisableButton] = useState<boolean>(false);

  // Selected Client State
  const [selectedClients, setSelectedClients] = useState<{
    [id: number]: CustomerDetailsList;
  }>({});

  const [noOfSelectedClient, setNoOfSelectedClients] = useState<number>(0);

  // Max Client Limit
  const maxSelectedClientAllowed: number = 5;

  // Send Email
  const sendEmail = async (payload: any) => {
    return await httpService.fetch(payload, "email_customer_statement");
  };

  // Handle Operation
  const handleOperation = (opType: string) => {
    setDisableButton(true);
    let payload: { [key: string]: any } = {
      clientId: clientId ? clientId?.toString() : "",
      startDate: startDate ? startDate?.toISOString().substring(0, 10) : "",
      endDate: endDate ? endDate?.toISOString().substring(0, 10) : "",
      attachTransactions: (attachTransactions ? 1 : 0).toString(),
      generateRecordOfAllTransactions: (generateRecordOfAllTransactions
        ? 1
        : 0
      ).toString(),
      storeId: localStorage.getItem("storeId") || "",
    };

    if (opType === "email") {
      if (noOfSelectedClient > 0) {
        delete payload["clientId"];
        payload["selectedClients"] = Object.keys(selectedClients);
      }
      sendEmail(payload)
        .then((res: any) => {
          let result: APIResponse = res.data;
          if (result.status !== true) {
            showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
            setDisableButton(false);
          } else {
            showToast(toast, true);
            // window.location.reload();
          }
        })
        .catch((err: any) => {
          showToast(toast, err.status, err.message);
          setDisableButton(false);
        });
    } else {
      const myURL = new URL(APP_HOST + "/api.php");
      myURL.searchParams.append("op", "customer_statement");
      myURL.searchParams.append("clientId", payload.clientId);
      myURL.searchParams.append("startDate", payload.startDate);
      myURL.searchParams.append("endDate", payload.endDate);
      myURL.searchParams.append(
        "attachTransactions",
        payload.attachTransactions
      );
      myURL.searchParams.append(
        "generateRecordOfAllTransactions",
        payload.generateRecordOfAllTransactions
      );
      myURL.searchParams.append("storeId", payload.storeId);
      setDisableButton(false);
      window.open(myURL.href, "_blank");
      window.location.reload();
    }
  };

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

  // Add Client
  const addClient = () => {
    // Add Client
    if (
      noOfSelectedClient < maxSelectedClientAllowed &&
      clientId &&
      clientName
    ) {
      let newClientList: { [id: number]: CustomerDetailsList } =
        selectedClients;

      if (newClientList[clientId] === undefined) {
        newClientList[clientId] = { id: clientId, name: clientName };
        setSelectedClients(newClientList);
        setNoOfSelectedClients(noOfSelectedClient + 1);
      }
    }
  };

  // Delete Client
  const deleteClient = (clientIdToDelete: number) => {
    let newClientList: { [id: number]: CustomerDetailsList } = selectedClients;
    if (clientIdToDelete in newClientList) {
      delete newClientList[clientIdToDelete];
      setSelectedClients(newClientList);
      setNoOfSelectedClients(noOfSelectedClient - 1);
    }
  };

  // Client Select
  const onClientSelect = (selectedClient: any) => {
    setClientId(selectedClient.value.id);
    setClientName(selectedClient.value.primaryDetails.name);
  };

  return (
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
              <Box>
                <_Button
                  isDisabled={noOfSelectedClient >= maxSelectedClientAllowed}
                  label=""
                  icon={<IoMdAddCircle color="#90EE90" />}
                  onClick={addClient}
                  fontSize="1.2em"
                  bgColor="black"
                ></_Button>
              </Box>
              <Box width="25%">
                <AutoSuggest
                  suggestions={clientSuggestions}
                  onSuggestionsClearRequested={() => setClientSuggestions([])}
                  onSuggestionsFetchRequested={({ value }) => {
                    if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
                    loadOptions(value);
                  }}
                  onSuggestionSelected={(_: any, { suggestionIndex }) => {
                    onClientSelect(clientSuggestions[suggestionIndex]);
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
                        setClientId(null);
                        setClientName(null);
                      }
                    },
                    disabled: noOfSelectedClient >= maxSelectedClientAllowed,
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
                        setStartDate(date);
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
                        setEndDate(date);
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
                      setAttachTransactions(!attachTransactions);
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
                      setGenerateRecordOfAllTransactions(
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
          <_Divider />
          <VStack align="start">
            <CustomerList list={selectedClients} deleteClient={deleteClient} />
          </VStack>
        </CardBody>
      </Card>
    </Box>
  );
};

export default CustomerStatement;
