/**
 * Receipts
 * https://colors.artyclick.com/color-names-dictionary/color-names/very-light-green-color
 */
import { memo, useEffect, useRef, useState } from "react";
import { ReceiptStoreDetails, TransactionDetails, receiptStore } from "./store";
import { shallow } from "zustand/shallow";
import {
  Badge,
  Box,
  Card,
  CardBody,
  Center,
  Checkbox,
  HStack,
  Link,
  SimpleGrid,
  Spinner,
  Textarea,
  VStack,
  useToast,
} from "@chakra-ui/react";
import {
  CurrencyIcon,
  _Button,
  _Divider,
  _Input,
  _InputLeftElement,
  _Label,
  _Select,
} from "../../../shared/Components";
import { AsyncSelectStyle, numberFont } from "../../../shared/style";
import {
  APP_HOST,
  CHEQUE_ID,
  TRANSACTION_TYPES,
  UNKNOWN_SERVER_ERROR_MSG,
  receiptPaymentMethods,
} from "../../../shared/config";
import { APIResponse } from "../../../service/api-client";
import { ClientDetails, clientStore } from "../../client/store";
import AsyncSelect from "react-select/async";
import {
  buildSearchListForClient,
  checkForValidSession,
  formatNumberWithDecimalPlaces,
  getUUID,
  showToast,
} from "../../../shared/functions";
import { LuReceipt } from "react-icons/lu";
import { IoPrint } from "react-icons/io5";
import { MdOutlineAlternateEmail } from "react-icons/md";
import { VIEW_URL_TABLE } from "../transactions/Filter";
import { useSearchParams } from "react-router-dom";
import { FaDeleteLeft } from "react-icons/fa6";
import { LiaMoneyCheckAltSolid } from "react-icons/lia";
import { IoIosLock } from "react-icons/io";
import { IoIosUnlock } from "react-icons/io";

interface ReceiptProps {
  isViewOrUpdate: boolean;
  enableEditing?: boolean;
}

interface HeaderProps extends ReceiptProps {
  setClientChangeStatus: any;
  setEnableEditing: any;
}

/**
 * Header
 */
const Header = memo(
  ({
    isViewOrUpdate,
    setClientChangeStatus,
    enableEditing,
    setEnableEditing,
  }: HeaderProps) => {
    const {
      id,
      clientId,
      clientName,
      date,
      paymentMethod,
      chequeNumber,
      amountInWords,
      setProperty,
      fetch,
    } = receiptStore(
      (state) => ({
        id: state.id,
        clientId: state.clientId,
        clientName: state.clientName,
        date: state.date,
        paymentMethod: state.paymentMethod,
        chequeNumber: state.chequeNumber,
        amountInWords: state.amountInWords,
        setProperty: state.setProperty,
        fetch: state.fetch,
      }),
      shallow
    );

    // Client Fetch Store
    const { fetch: fetchClient } = clientStore();

    // Select Load options
    const loadOptionsForClient = (
      searchTerm: string,
      callback: (args: any) => void
    ) => {
      fetchClient(searchTerm)
        .then((res: any) => {
          let response: APIResponse<ClientDetails[]> = res.data;
          if (response.status === true) {
            callback(buildSearchListForClient(response.data));
          } else
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

    const [hideChequeNumberInput, setHideChequeNumberInput] =
      useState<boolean>(true);

    // On Payment Method Change
    useEffect(() => {
      setHideChequeNumberInput(paymentMethod === CHEQUE_ID ? false : true);
    }, [paymentMethod]);

    // Toast
    const toast = useToast();

    // Load Client Details
    useEffect(() => {
      if (clientId !== undefined && isViewOrUpdate === false) {
        setClientChangeStatus(true);
        fetch()
          .then((res: any) => {
            let result: APIResponse<TransactionDetails[]> = res.data;
            if (result.status === true) {
              setProperty("transactions", result.data);
              setClientChangeStatus(false);
            } else
              showToast(
                toast,
                false,
                result.message || UNKNOWN_SERVER_ERROR_MSG
              );
          })
          .catch((err: any) => {
            showToast(toast, false, err.message);
          });
      }
    }, [clientId]);

    // CLient Async Select Key
    let clientAsyncSelectKey: { [key: string]: any } = {};

    // Default Client
    let defaultClient: { [key: string]: { [key: string]: any } } = {};
    if (isViewOrUpdate && clientId) {
      defaultClient = {
        defaultValue: {
          label: clientName,
          value: clientId,
        },
      };

      clientAsyncSelectKey = {
        key: getUUID(),
      };
      setClientChangeStatus(false);
    }
    return (
      <Card borderRadius={2} width="100%">
        <CardBody padding={2}>
          <VStack align={"start"}>
            <HStack width="100%">
              <Box width="10%">
                <_Label fontSize="0.7em" letterSpacing={2}>
                  PAYMENT METHOD:
                </_Label>
              </Box>
              <Box width="15%">
                <_Select
                  isDisabled={isViewOrUpdate === true ? !enableEditing : false}
                  value={paymentMethod}
                  variant={"filled"}
                  borderRadius={2.5}
                  fontSize="0.7em"
                  options={receiptPaymentMethods}
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
              <Box
                width="25%"
                transform={"translateY(-25%)"}
                display={hideChequeNumberInput ? "none" : "block"}
              >
                <_InputLeftElement
                  isReadOnly={isViewOrUpdate ? !enableEditing : false}
                  placeholder="Cheque Number"
                  defaultValue={chequeNumber ? chequeNumber : ""}
                  leftElement={<LiaMoneyCheckAltSolid color="green" />}
                  fontSize="0.8em"
                  fontFamily={numberFont}
                  letterSpacing={2}
                  onBlur={(event: any) => {
                    if (event && event.target) {
                      setProperty("chequeNumber", event.target.value.trim());
                    }
                  }}
                ></_InputLeftElement>
              </Box>
              <Box marginLeft="10vw">
                <Badge
                  letterSpacing={2}
                  bgColor="#ADD8E6"
                  fontSize="0.7em"
                  fontFamily={numberFont}
                >
                  {date.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  })}
                </Badge>
              </Box>
              {id && isViewOrUpdate === true && (
                <Box marginLeft="2vw">
                  <HStack spacing={10}>
                    <Badge
                      letterSpacing={2}
                      bgColor="#90EE90"
                      fontSize="0.7em"
                      fontFamily={numberFont}
                    >
                      # {id}
                    </Badge>
                    <_Button
                      fontSize="1.2em"
                      icon={
                        enableEditing === true ? (
                          <IoIosLock color={"red"} />
                        ) : (
                          <IoIosUnlock color={"green"} />
                        )
                      }
                      onClick={() => {
                        setEnableEditing(!enableEditing);
                      }}
                      color={enableEditing === true ? "red" : "green"}
                      label={
                        (enableEditing === true ? "Disable" : "Enable") +
                        " Editing"
                      }
                    ></_Button>
                  </HStack>
                </Box>
              )}
            </HStack>
            <Card bgColor="#adbbe6" borderRadius={2} width="100%">
              <CardBody padding={2}>
                <VStack>
                  <HStack width="100%">
                    <Box width="12%">
                      <_Label fontSize={"0.7em"} letterSpacing={2}>
                        RCV'D:
                      </_Label>
                    </Box>
                    <Box>
                      <_Label
                        fontSize={"0.7em"}
                        fontWeight="bold"
                        letterSpacing={2}
                      >
                        {amountInWords}
                      </_Label>
                    </Box>
                  </HStack>
                  <HStack width="100%">
                    <Box width="12%">
                      <_Label fontSize={"0.7em"} letterSpacing={2}>
                        FROM:
                      </_Label>
                    </Box>
                    <Box width="50%">
                      <AsyncSelect
                        tabSelectsValue={true}
                        {...clientAsyncSelectKey}
                        isDisabled={isViewOrUpdate}
                        styles={AsyncSelectStyle}
                        cacheOptions={false}
                        loadOptions={loadOptionsForClient}
                        isClearable={true}
                        defaultOptions={false}
                        {...defaultClient}
                        onChange={(event: any) => {
                          let clientDetails: ClientDetails;
                          if (event && event.value) {
                            clientDetails = event.value;
                            setProperty(
                              "paymentMethod",
                              clientDetails.defaultReceiptPaymentMethod
                            );
                            setProperty("clientId", clientDetails.id);
                            setProperty(
                              "clientLastModifiedTimestamp",
                              clientDetails.lastModifiedTimestamp
                            );
                          } else {
                            setProperty("clientId", undefined);
                            setProperty(
                              "clientLastModifiedTimestamp",
                              undefined
                            );
                          }
                        }}
                      />
                    </Box>
                  </HStack>
                </VStack>
              </CardBody>
            </Card>
          </VStack>
        </CardBody>
      </Card>
    );
  }
);

// Transaction Header
const TransactionHeader = memo(
  ({
    checkAllTransactions,
    toggleCheckAllTransactionsState,
  }: {
    checkAllTransactions: number;
    toggleCheckAllTransactionsState: any;
  }) => {
    return (
      <Box width="100%">
        <HStack width="100%" textAlign={"center"}>
          <Checkbox
            key={getUUID()}
            isChecked={checkAllTransactions & 1 ? true : false}
            onChange={() => {
              toggleCheckAllTransactionsState();
            }}
          ></Checkbox>
          <Box width="10vw">
            <Center>
              <Badge fontSize="0.65em" bgColor="white" letterSpacing={2}>
                DATE
              </Badge>
            </Center>
          </Box>
          <Box width="10vw">
            <Center>
              <Badge fontSize="0.65em" bgColor="white" letterSpacing={2}>
                TXN #
              </Badge>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <Badge fontSize="0.65em" bgColor="white" letterSpacing={2}>
                ORIGINAL AMOUNT
              </Badge>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <Badge fontSize="0.65em" bgColor="white" letterSpacing={2}>
                AMOUNT OWING
              </Badge>
            </Center>
          </Box>
          <Box width="14vw">
            <Center>
              <Badge fontSize="0.65em" bgColor="white" letterSpacing={2}>
                DISCOUNT AVAILABLE
              </Badge>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <Badge bg="#fbceb1" fontSize="0.65em" letterSpacing={2}>
                DISCOUNT GIVEN
              </Badge>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <Badge bg="#D0FFBC" fontSize="0.65em" letterSpacing={2}>
                AMOUNT RECEIVED
              </Badge>
            </Center>
          </Box>
        </HStack>
      </Box>
    );
  }
);

interface TransactionProps extends ReceiptProps {
  indexId: number;
  checkAllTransactions: number;
}

/**
 * Transaction
 */
const Transaction = memo(
  ({ isViewOrUpdate, indexId, checkAllTransactions }: TransactionProps) => {
    const { transactions, calculateTotalAmountReceived } = receiptStore(
      (state) => ({
        transactions: state.transactions,
        calculateTotalAmountReceived: state.calculateTotalAmountReceived,
      }),
      shallow
    );
    const [_rerender, setRerender] = useState<number>(0);
    const rerender = () => {
      setRerender(_rerender + 1);
    };

    const discountGivenRef = useRef<HTMLInputElement>(null);
    const amountReceivedRef = useRef<HTMLInputElement>(null);

    // Flag
    let __checkAllTransactions = checkAllTransactions & 1 ? true : false;
    if (__checkAllTransactions) transactions[indexId].isChecked = 1;
    else if (checkAllTransactions > 0 && transactions[indexId].isChecked != 3)
      transactions[indexId].isChecked = 0;

    let isChecked: boolean = false;

    // Checked
    if (
      __checkAllTransactions ||
      (transactions[indexId].isChecked ? true : false)
    ) {
      isChecked = true;
    }
    return (
      <Box width="100%">
        <HStack textAlign="center">
          <Checkbox
            isChecked={isChecked}
            onChange={() => {
              transactions[indexId].isChecked = transactions[indexId].isChecked
                ? 0
                : 3;
              rerender();
            }}
          ></Checkbox>
          <Box width="10vw">
            <Center>
              <Badge
                fontSize="0.65em"
                bgColor="white"
                letterSpacing={2}
                fontFamily={numberFont}
              >
                {transactions[indexId].date}
              </Badge>
            </Center>
          </Box>
          <Box width="10vw">
            <Center>
              <Badge
                fontSize="0.65em"
                bgColor="white"
                letterSpacing={2}
                fontFamily={numberFont}
              >
                <Link
                  /* Disable Tab On Link */
                  tabIndex={-1}
                  isExternal
                  href={
                    VIEW_URL_TABLE[transactions[indexId].type] +
                    transactions[indexId].id
                  }
                >
                  {transactions[indexId].txnId}
                </Link>
              </Badge>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <HStack>
                <CurrencyIcon />
                <Badge
                  fontSize="0.65em"
                  bgColor="white"
                  letterSpacing={2}
                  fontFamily={numberFont}
                >
                  {transactions[indexId].originalAmount}
                </Badge>
              </HStack>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <HStack>
                <CurrencyIcon />
                <Badge
                  fontSize="0.65em"
                  bgColor="white"
                  letterSpacing={2}
                  fontFamily={numberFont}
                >
                  {transactions[indexId].amountOwing}
                </Badge>
              </HStack>
            </Center>
          </Box>
          <Box width="15vw">
            <Center>
              <HStack>
                <CurrencyIcon />
                <Badge
                  fontSize="0.65em"
                  bgColor="white"
                  letterSpacing={2}
                  fontFamily={numberFont}
                >
                  {transactions[indexId].discountAvailable}
                </Badge>
              </HStack>
            </Center>
          </Box>
          <Box width="15vw" transform={"translateY(-25%)"}>
            <Center>
              <_InputLeftElement
                isReadOnly={
                  isViewOrUpdate ||
                  transactions[indexId].type === TRANSACTION_TYPES["CN"] ||
                  transactions[indexId].type === TRANSACTION_TYPES["DN"]
                }
                ref={discountGivenRef}
                type="number"
                defaultValue={transactions[indexId].discountGiven}
                fontFamily={numberFont}
                fontSize="0.8em"
                leftElement={<CurrencyIcon></CurrencyIcon>}
                key={getUUID()}
                onClick={(event: any) => {
                  let discountGiven = 0;
                  if (event && event.target) {
                    discountGiven = parseFloat(event.target.value.trim());
                    if (isNaN(discountGiven)) {
                      transactions[indexId].discountGiven =
                        transactions[indexId].discountAvailable;
                      if (discountGivenRef && discountGivenRef.current) {
                        discountGivenRef.current.value =
                          transactions[indexId].discountAvailable.toFixed(4);
                      }
                    } else {
                      event.target.select();
                    }
                    calculateTotalAmountReceived();
                  }
                }}
                onBlur={(event: any) => {
                  if (event && event.target) {
                    let discountGiven = parseFloat(event.target.value.trim());
                    if (isNaN(discountGiven)) {
                      transactions[indexId].discountGiven =
                        transactions[indexId].discountAvailable;
                      if (discountGivenRef && discountGivenRef.current) {
                        discountGivenRef.current.value =
                          transactions[indexId].discountAvailable.toString();
                      }
                    } else if (discountGiven === 0) {
                      transactions[indexId].discountGiven = 0;
                      if (discountGivenRef && discountGivenRef.current) {
                        discountGivenRef.current.value = (0).toString();
                      }
                    } else if (
                      discountGiven > transactions[indexId].discountAvailable
                    ) {
                      transactions[indexId].discountGiven =
                        transactions[indexId].discountAvailable;
                      if (discountGivenRef && discountGivenRef.current) {
                        discountGivenRef.current.value =
                          transactions[indexId].discountAvailable.toString();
                      }
                    } else {
                      transactions[indexId].discountGiven = parseFloat(
                        discountGiven.toFixed(4)
                      );
                      if (discountGivenRef && discountGivenRef.current) {
                        discountGivenRef.current.value =
                          discountGiven.toFixed(4);
                      }
                    }
                    calculateTotalAmountReceived();
                  }
                }}
              ></_InputLeftElement>
            </Center>
          </Box>
          <Box width="15vw" transform={"translateY(-25%)"}>
            <Center>
              <_InputLeftElement
                isReadOnly={isViewOrUpdate}
                ref={amountReceivedRef}
                type="number"
                defaultValue={transactions[indexId].amountReceived}
                fontFamily={numberFont}
                fontSize="0.8em"
                leftElement={<CurrencyIcon></CurrencyIcon>}
                key={getUUID()}
                onClick={(event: any) => {
                  let amountReceived = 0;
                  if (event && event.target) {
                    amountReceived = parseFloat(event.target.value.trim());
                    if (isNaN(amountReceived)) {
                      amountReceived =
                        transactions[indexId].amountOwing -
                        transactions[indexId].discountGiven;
                      transactions[indexId].amountReceived = parseFloat(
                        amountReceived.toFixed(4)
                      );
                    } else {
                      amountReceived =
                        transactions[indexId].amountOwing -
                        transactions[indexId].discountGiven;
                      transactions[indexId].amountReceived = parseFloat(
                        amountReceived.toFixed(4)
                      );
                    }
                    transactions[indexId].amountReceived = amountReceived;
                    if (amountReceivedRef && amountReceivedRef.current) {
                      amountReceivedRef.current.value =
                        transactions[indexId].amountReceived.toFixed(4);
                    }
                    event.target.select();
                    calculateTotalAmountReceived();
                  }
                }}
                onBlur={(event: any) => {
                  if (event && event.target) {
                    let amountReceived = parseFloat(event.target.value.trim());
                    let temp = 0;
                    if (isNaN(amountReceived)) {
                      temp =
                        transactions[indexId].amountOwing -
                        transactions[indexId].discountGiven;
                    } else if (amountReceived === 0) {
                      temp = 0;
                    } else if (
                      amountReceived > transactions[indexId].amountOwing
                    ) {
                      temp =
                        transactions[indexId].amountOwing -
                        transactions[indexId].discountGiven;
                    } else {
                      temp = amountReceived;
                    }
                    transactions[indexId].amountReceived = temp;
                    if (amountReceivedRef && amountReceivedRef.current)
                      amountReceivedRef.current.value =
                        transactions[indexId].amountReceived.toFixed(4);

                    calculateTotalAmountReceived();
                  }
                }}
              ></_InputLeftElement>
            </Center>
          </Box>
        </HStack>
      </Box>
    );
  }
);

interface TransactionListProps extends ReceiptProps {
  isClientChange: boolean;
}

const TransactionsList = memo(
  ({ isViewOrUpdate, isClientChange }: TransactionListProps) => {
    const [checkAllTransactions, setCheckAllTransactions] = useState<number>(0);

    const toggleCheckAllTransactionsState = () => {
      setCheckAllTransactions(checkAllTransactions + 1);
    };

    const { clientId, transactions } = receiptStore(
      (state) => ({
        clientId: state.clientId,
        transactions: state.transactions,
      }),
      shallow
    );

    let isClientNotLoaded = clientId === undefined;
    const list = isClientNotLoaded ? (
      <></>
    ) : (
      <Card borderRadius={2} width="100%">
        <CardBody padding={2} width="100%">
          <Box width="100%">
            <VStack align="start" width="100%">
              <TransactionHeader
                checkAllTransactions={checkAllTransactions}
                toggleCheckAllTransactionsState={
                  toggleCheckAllTransactionsState
                }
              />
              {transactions.map((_, index) => {
                return (
                  <Transaction
                    isViewOrUpdate={isViewOrUpdate}
                    checkAllTransactions={checkAllTransactions}
                    key={getUUID()}
                    indexId={index}
                  ></Transaction>
                );
              })}
            </VStack>
          </Box>
        </CardBody>
      </Card>
    );

    const code = (
      <Box width="100%" height="65vh" overflow="auto">
        {list}
      </Box>
    );

    if (isClientNotLoaded) return code;
    if (isClientChange)
      return (
        <Box width="100%">
          <Center>
            <Spinner
              speed="0.8s"
              size="xl"
              thickness="2px"
              color="#10EFA7"
              emptyColor="#EF1058"
            ></Spinner>
          </Center>
        </Box>
      );

    return code;
  }
);

/**
 * Footer
 * @param isViewOrUpdate
 * @return JSX.Element
 */
const Footer = memo(({ isViewOrUpdate, enableEditing }: ReceiptProps) => {
  const [opStatus, setOpStatus] = useState<boolean>(false);
  const {
    id,
    transactions,
    totalAmountReceived,
    comment,
    setProperty,
    process,
    sendEmail,
    deleteReceipt,
  } = receiptStore(
    (state) => ({
      id: state.id,
      transactions: state.transactions,
      totalAmountReceived: state.totalAmountReceived,
      comment: state.comment,
      setProperty: state.setProperty,
      process: state.process,
      sendEmail: state.sendEmail,
      deleteReceipt: state.deleteReceipt,
    }),
    shallow
  );

  /**
   * This method will extract selected transactions.
   * @return array
   */
  const extractSelectedTransactions = () => {
    let txns: { [transactionType: number]: number[] } = {};
    let count: number = transactions.length;
    for (let i = 0; i < count; ++i) {
      if (transactions[i].isChecked) {
        if (typeof txns[transactions[i].type] === "undefined")
          txns[transactions[i].type] = [];
        txns[transactions[i].type].push(transactions[i].id);
      }
    }
    return txns;
  };

  const toast = useToast();

  // Process Handler
  const processHandler = () => {
    setOpStatus(true);
    process(isViewOrUpdate)
      .then((res: any) => {
        let response: APIResponse<number> = res.data;
        if (response.status === true) setProperty("id", response.data);
        else setOpStatus(false);
        showToast(
          toast,
          response.status,
          response.status !== true
            ? response.message || UNKNOWN_SERVER_ERROR_MSG
            : ""
        );
      })
      .catch((err: any) => {
        setOpStatus(false);
        showToast(toast, false, err.message);
      });
  };

  const [disableEmailButton, setDisableEmailButton] = useState<boolean>(false);

  // Send Email Handler
  const sendEmailHandler = (selectedTxn: string) => {
    setDisableEmailButton(true);
    let isNotSuccessful = true;
    sendEmail(selectedTxn)
      .then((res: any) => {
        let result: APIResponse = res.data;
        if (result.status === true) {
          showToast(toast, true, "Email Send Successfully.");
          isNotSuccessful = false;
        } else showToast(toast, false, "Unable to Send Email.");
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      })
      .finally(() => {
        if (isNotSuccessful) setDisableEmailButton(false);
      });
  };

  const [deleteBtnStatus, setDeleteBtnStatus] = useState<boolean>(false);

  // Delete Handler
  const deleteHandler = () => {
    let promptResponse = prompt(
      "Please enter 'yes' to confirm the deletion of the receipt."
    );
    if (promptResponse && promptResponse.trim().toLowerCase() === "yes") {
      setDeleteBtnStatus(true);
      let isNotSuccessful = true;
      deleteReceipt()
        .then((res: any) => {
          let result: APIResponse = res.data;
          if (result.status === true) {
            showToast(toast, true, "Receipt Deleted Successfully.");
            isNotSuccessful = false;
          } else
            showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
        })
        .catch((err: any) => {
          showToast(toast, false, err.message);
        })
        .finally(() => {
          if (isNotSuccessful) setDeleteBtnStatus(false);
        });
    } else showToast(toast, true, "Prompt Cancelled");
  };

  return (
    <Box width="100%" borderTopWidth={1} borderTopColor={"gray"} paddingTop={2}>
      <HStack>
        <Box width="30%">
          <VStack align="left">
            <_Label letterSpacing={2} fontSize="0.7em">
              COMMENT:
            </_Label>
            <Textarea
              key={getUUID()}
              defaultValue={comment}
              onBlur={(event: any) => {
                if (event && event.target) {
                  setProperty("comment", event.target.value.trim());
                }
              }}
              resize="none"
              borderRadius={2}
              padding={2}
              fontSize="0.8em"
              letterSpacing={2}
              width={"100%"}
            ></Textarea>
          </VStack>
        </Box>
        <Box marginLeft="40%" width="30%">
          <VStack align="end">
            <Box>
              <HStack>
                <Badge letterSpacing={2} bg="#D0FFBC" fontSize="0.7em">
                  TOTAL RECEIVED
                </Badge>
                <_Label>:</_Label>
                <CurrencyIcon></CurrencyIcon>
                <_Label fontSize="0.8em" fontFamily={numberFont}>
                  {formatNumberWithDecimalPlaces(totalAmountReceived, 2)}
                </_Label>
              </HStack>
            </Box>
            <SimpleGrid columns={2}>
              <Box>
                <_Button
                  isDisabled={
                    opStatus || (isViewOrUpdate && enableEditing === false)
                  }
                  borderRadius={2}
                  bgColor="white"
                  color="black"
                  icon={<LuReceipt color="green" />}
                  label="Process"
                  onClick={processHandler}
                  fontSize="1.2em"
                ></_Button>
              </Box>
              {id && (
                <>
                  <Box>
                    <_Button
                      fontSize="1.2em"
                      width="100%"
                      borderRadius={2}
                      bgColor="white"
                      color="black"
                      icon={<IoPrint color="orange" />}
                      label="Print"
                      onClick={() => {
                        let selectedTxns = btoa(
                          JSON.stringify(extractSelectedTransactions())
                        );
                        window.open(
                          `${APP_HOST}/api.php?op=print&t=${TRANSACTION_TYPES["RC"]}&i=${id}&s=${selectedTxns}`
                        );
                      }}
                    ></_Button>
                  </Box>
                  <Box paddingTop={2}>
                    <_Button
                      fontSize="1.2em"
                      isDisabled={disableEmailButton}
                      width="100%"
                      borderRadius={2}
                      bgColor="white"
                      color="black"
                      icon={<MdOutlineAlternateEmail color="blue" />}
                      label="Email"
                      onClick={() => {
                        let selectedTxns = btoa(
                          JSON.stringify(extractSelectedTransactions())
                        );
                        sendEmailHandler(selectedTxns);
                      }}
                    ></_Button>
                  </Box>
                  {isViewOrUpdate && (
                    <Box paddingTop={2}>
                      <_Button
                        fontSize="1.2em"
                        isDisabled={deleteBtnStatus}
                        width="100%"
                        borderRadius={2}
                        bgColor="white"
                        color="black"
                        icon={<FaDeleteLeft color="red" />}
                        label="DELETE"
                        onClick={deleteHandler}
                      ></_Button>
                    </Box>
                  )}
                </>
              )}
            </SimpleGrid>
          </VStack>
        </Box>
      </HStack>
    </Box>
  );
});

/**
 * Receipts Component
 * @param isViewOrUpdate
 */
const Receipts = ({ isViewOrUpdate }: ReceiptProps) => {
  checkForValidSession();
  const [isClientChange, setClientChangeStatus] = useState<boolean>(true);
  const toast = useToast();
  const { id, load, setDetails } = receiptStore(
    (state) => ({
      id: state.id,
      load: state.load,
      setDetails: state.setDetails,
    }),
    shallow
  );

  // Enable Editing
  const [enableEditing, setEnableEditing] = useState<boolean>(false);

  // Use Search Params
  const [searchParams] = useSearchParams();

  if (isViewOrUpdate) {
    let transactionId = searchParams.get("id");

    if (id === undefined && transactionId !== null) {
      load(parseInt(transactionId))
        .then((res: any) => {
          let result: APIResponse<ReceiptStoreDetails> = res.data;
          if (result.status === true && result.data) {
            setDetails(result.data);
          } else
            showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
        })
        .catch((err: any) => {
          showToast(toast, false, err.message);
        });
    }
  }
  return (
    <>
      <Box>
        <VStack align={"start"}>
          <Header
            isViewOrUpdate={isViewOrUpdate}
            setClientChangeStatus={setClientChangeStatus}
            enableEditing={enableEditing}
            setEnableEditing={setEnableEditing}
          ></Header>
          <TransactionsList
            isViewOrUpdate={isViewOrUpdate}
            isClientChange={isClientChange}
          />
          <Footer
            isViewOrUpdate={isViewOrUpdate}
            enableEditing={enableEditing}
          ></Footer>
        </VStack>
      </Box>
    </>
  );
};

export default Receipts;
