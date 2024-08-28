import {
  Badge,
  Box,
  Button,
  Card,
  CardBody,
  Center,
  Checkbox,
  Divider,
  HStack,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  ModalOverlay,
  Popover,
  PopoverArrow,
  PopoverBody,
  PopoverCloseButton,
  PopoverContent,
  PopoverTrigger,
  SimpleGrid,
  Spinner,
  Textarea,
  Tooltip,
  VStack,
  useDisclosure,
  useToast,
} from "@chakra-ui/react";
import {
  CurrencyIcon,
  _Button,
  _Divider,
  _Input,
  _InputLeftAddon,
  _InputLeftElement,
  _Label,
  _Select,
} from "../../../shared/Components";
import {
  APP_HOST,
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  AttributeType,
  MODE_WASH,
  PAY_LATER_ID,
  TRANSACTION_TYPES,
  UNKNOWN_SERVER_ERROR_MSG,
  paymentMethods,
  systemConfigMode,
} from "../../../shared/config";
import {
  AsyncSelectStyle,
  AutoSuggestStyle,
  errorGradient,
  inputConfig,
  navBgColor,
  numberFont,
  resetGradient,
  successGradient,
} from "../../../shared/style";
import { memo, useEffect, useState } from "react";
import { FaInfo } from "react-icons/fa";
import { FcComments } from "react-icons/fc";
import AsyncSelect from "react-select/async";
import { Address, ClientDetails, clientStore } from "../../client/store";
import { APIResponse, HTTPService } from "../../../service/api-client";
import {
  ItemDetailsForTransactions,
  RowDetails,
  TransactionStoreFields,
  defaultRowItemDetails,
  transactionStore,
  txnStateStore,
} from "./store";
import {
  calculateCOGSMarginByMargin,
  checkForValidSession,
  formatNumberWithDecimalPlaces,
  getProfitMarginColorScheme,
  getProfitMarginByItemIdentifierPrefix,
  getUUID,
  showToast,
  toFixed,
  buildSearchListForItem,
  buildSearchListForClient,
  calculateProfitMargin,
  calculateCOGSMargin,
  getAttributeFromSession,
} from "../../../shared/functions";
import {
  BiMessageDetail,
  BiSolidDownArrow,
  BiSolidUpArrow,
} from "react-icons/bi";
import {
  AiFillPrinter,
  AiOutlineArrowLeft,
  AiOutlineArrowRight,
  AiOutlinePrinter,
} from "react-icons/ai";
import { MdAlternateEmail, MdDoneAll } from "react-icons/md";
import { TbReceiptTax } from "react-icons/tb";
import { CiDiscount1 } from "react-icons/ci";
import { IoIosPricetags } from "react-icons/io";
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import { BsLock, BsUnlock } from "react-icons/bs";
import { SiZeromq } from "react-icons/si";
import { useSearchParams } from "react-router-dom";
import { shallow } from "zustand/shallow";
import { GoVersions } from "react-icons/go";
import { IoIosCloseCircle } from "react-icons/io";
import { AiOutlineEyeInvisible } from "react-icons/ai";
import { BiSolidShow } from "react-icons/bi";
import AutoSuggest from "react-autosuggest";

// Http Service Instance for Transactions
const httpService = new HTTPService();

// Store ID
const storeId = parseInt(localStorage.getItem("storeId") || "0");

/* GST Tax Rate */
const GST_HST_TAX_RATE: number =
  parseFloat(getAttributeFromSession("gstHSTRaxRate")) || 0;

/* PST Tax Rate */
const PST_TAX_RATE: number =
  parseFloat(getAttributeFromSession("pstTaxRate")) || 0;

/**
 * Disable Credit Transactions Message Modal.
 * @return
 */
const DisableCreditTransactionsMessageModal = ({
  showModal,
  setShowModal,
  onClose,
}: {
  showModal: boolean;
  setShowModal: any;
  onClose?: any;
}) => {
  const { disableCreditTransactions } = transactionStore(
    (state) => ({
      disableCreditTransactions: state.disableCreditTransactions,
    }),
    shallow
  );

  const __onClose = () => {
    setShowModal(false);
    onClose();
  };

  return (
    <>
      <Modal
        closeOnOverlayClick={false}
        isOpen={disableCreditTransactions === 1 && showModal === true}
        onClose={__onClose}
      >
        <ModalOverlay />
        <ModalContent>
          <ModalHeader>
            <_Label letterSpacing={5}>NOTE</_Label>
          </ModalHeader>
          <ModalBody pb={6}>
            <_Label fontSize="0.8em">
              Credit Transactions Are DISABLED for this client due to
              Non-Payment in the past.
            </_Label>
          </ModalBody>
          <ModalFooter>
            <_Button
              icon={<IoIosCloseCircle />}
              label="CLOSE"
              color="red"
              onClick={__onClose}
            ></_Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
};

// Sales Representative
type SalesRepresentative = {
  [id: number]: string;
};

/**
 * Header
 * @param id
 * @param isViewOrUpdate
 * @param enableEditing
 * @param enableEditingHandler
 * @returns
 */
const Header = ({
  id,
  type,
  isViewOrUpdate,
  enableEditing,
  hidePrivateDetails,
  enableEditingHandler,
  setPrivateDetailsVisibility,
}: {
  id: number;
  type: number;
  isViewOrUpdate: boolean;
  enableEditing?: boolean;
  hidePrivateDetails?: boolean;
  enableEditingHandler: () => void;
  setPrivateDetailsVisibility: any;
}) => {
  const {
    creditAmount,
    subTotal,
    transactionType,
    paymentMethod,
    versionKeys,
    versionSelected,
    salesRepId,
    setProperty,
  } = transactionStore(
    (state) => ({
      creditAmount: state.creditAmount,
      subTotal: state.subTotal,
      transactionType: state.transactionType,
      paymentMethod: state.paymentMethod,
      versionKeys: state.versionKeys,
      versionSelected: state.versionSelected,
      salesRepId: state.salesRepId,
      setProperty: state.setProperty,
    }),
    shallow
  );

  /* Processed State */
  const { isProcessed } = txnStateStore((state) => ({
    isProcessed: state.isProcessed,
  }));

  // Flag
  const isPaid = creditAmount === 0;

  // For Modal Closed.
  const [showModal, setShowModal] = useState<boolean>(true);
  const { onClose: modalOnClose } = useDisclosure();

  const toast = useToast();

  // Sales Representative
  let [salesRepresentatives, setSalesRepresentatives] =
    useState<SalesRepresentative>({});

  // Fetch Sales Representatives
  const fetchSalesRepresentatives = () => {
    httpService
      .fetch<SalesRepresentative[]>(
        {
          type: 1,
        },
        "um_fetch"
      )
      .then((res: any) => {
        let response: APIResponse<any> = res.data;
        if (response.status === true) {
          let userData = response.data;
          let count = userData.length;
          let tempUsers: SalesRepresentative = {};
          tempUsers[0] = "Select...";
          for (let i = 0; i < count; ++i) {
            tempUsers[userData[i].id] = userData[i].name;
          }
          setSalesRepresentatives(tempUsers);
        } else {
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
        }
      });
  };

  if (Object.keys(salesRepresentatives).length === 0) {
    fetchSalesRepresentatives();
  }

  // Payment Methods
  let __paymentMethods: AttributeType = {};
  __paymentMethods[-1] = "0 ~ Select...";
  let paymentMethodsKeys = Object.keys(paymentMethods);
  let temp: number = 0;
  for (let i = 0; i < paymentMethodsKeys.length; ++i) {
    temp = parseInt(paymentMethodsKeys[i]);
    __paymentMethods[temp] = `${i + 1} ~ ` + paymentMethods[temp];
  }

  return (
    <>
      <Box width="100%">
        <DisableCreditTransactionsMessageModal
          showModal={showModal}
          setShowModal={setShowModal}
          onClose={modalOnClose}
        />
        <HStack width="100%">
          {(type == TRANSACTION_TYPES["SI"] ||
            type == TRANSACTION_TYPES["SR"]) && (
            <Box width="20%">
              {/* Payment Method */}
              <HStack>
                <Box>
                  <_Label fontSize="0.7em" letterSpacing={1}>
                    PAYMENT METHOD:
                  </_Label>
                </Box>
                <Box>
                  <_Select
                    isDisabled={enableEditing === false || isProcessed}
                    value={paymentMethod}
                    variant={"filled"}
                    borderRadius={2.5}
                    fontSize="0.7em"
                    options={__paymentMethods}
                    onChange={(event: any) => {
                      setProperty("paymentMethod", event.target.value);
                    }}
                  ></_Select>
                </Box>
              </HStack>
            </Box>
          )}
          {isViewOrUpdate && (
            <HStack spacing={6} width="70%">
              {/* Transaction ID  */}
              <Box>
                <Badge
                  color="#5D3FD3"
                  letterSpacing={2}
                  bgColor="#CCCCFF"
                  fontSize="0.7em"
                  fontFamily={numberFont}
                >
                  # {id}
                </Badge>
              </Box>
              {/* Paid/Unpaid Status  */}
              {transactionType !== TRANSACTION_TYPES["QT"] && (
                <Box>
                  <Badge
                    color={
                      isPaid
                        ? "#00FF7F"
                        : creditAmount < subTotal
                        ? "red"
                        : "#FBCEB1"
                    }
                    letterSpacing={2}
                    bgColor={
                      isPaid
                        ? "#40826D"
                        : creditAmount < subTotal
                        ? "black"
                        : "#97233F"
                    }
                    fontSize="0.7em"
                  >
                    {isPaid
                      ? "PAID ✔"
                      : creditAmount < subTotal
                      ? "PARTIALLY PAID 🞜"
                      : "UNPAID ✖"}
                  </Badge>
                </Box>
              )}
              {/* Enable Editing  */}
              <Box>
                <_Button
                  fontSize={"1.5em"}
                  icon={
                    enableEditing ? (
                      <BsUnlock color={"#E59528"} />
                    ) : (
                      <BsLock color={"#00A36C"} />
                    )
                  }
                  size="lg"
                  height={7}
                  label={(enableEditing ? "Disable" : "Enable") + " Editing"}
                  onClick={enableEditingHandler}
                  bgColor={"white"}
                  color="black"
                  borderColor="gray.200"
                  borderWidth={1}
                ></_Button>
              </Box>
              {versionKeys && Object.keys(versionKeys).length > 0 && (
                <Box>
                  <HStack>
                    <Box>
                      <_Label fontSize="0.7em" letterSpacing={1}>
                        VERSIONS:
                      </_Label>
                    </Box>
                    <Box>
                      <_Select
                        fontSize="0.7em"
                        options={versionKeys}
                        onChange={(event: any) => {
                          setProperty(
                            "versionSelected",
                            parseInt(event.target.value)
                          );
                        }}
                      ></_Select>
                    </Box>
                    <Box>
                      <_Button
                        icon={<GoVersions></GoVersions>}
                        color="#2070F0"
                        bgColor="#D8F020"
                        label="View Version"
                        fontSize="1.2em"
                        onClick={() => {
                          window.open(
                            `${APP_HOST}/api.php?op=print&t=${transactionType}&i=${id}&version=${versionSelected}`
                          );
                        }}
                      ></_Button>
                    </Box>
                  </HStack>
                </Box>
              )}
            </HStack>
          )}
          {/* Sales Representative */}
          <Box>
            <_Select
              isDisabled={isViewOrUpdate}
              value={salesRepId}
              fontSize="0.7em"
              options={salesRepresentatives}
              onChange={(event: any) => {
                setProperty("salesRepId", parseInt(event?.target.value));
              }}
            ></_Select>
          </Box>
          <Box flex={1}>
            <HStack justifyContent={"right"}>
              <Box>
                <HStack>
                  {/* Visibility Button  */}
                  <_Button
                    bgColor="#dfff00"
                    label=""
                    icon={
                      hidePrivateDetails ? (
                        <AiOutlineEyeInvisible color="#2000FF" />
                      ) : (
                        <BiSolidShow color="#2000FF" />
                      )
                    }
                    onClick={() => {
                      setPrivateDetailsVisibility(!hidePrivateDetails);
                    }}
                  ></_Button>
                </HStack>
              </Box>
            </HStack>
          </Box>
        </HStack>
      </Box>
    </>
  );
};

/**
 * Client Details Props
 */
interface ClientDetailsProps {
  clientDetails?: null;
}

/**
 * Build Address
 * @param address
 * @returns
 */
const buildAddress = (address: Address) => {
  let temp = address.contactName + "\n";
  if (address.city !== null && address.city.length > 0) temp += address.street1;
  if (address.street2 !== null && address.street2.length > 0)
    temp += "\n" + address.street2;
  if (address.city !== null && address.city.length > 0) {
    temp += "\n" + address.city;
    if (address.province !== null && address.province.length > 0) {
      temp += "\n" + address.province;
      if (address.postalCode !== null && address.postalCode.length > 0)
        temp += ", " + address.postalCode;
    }
  }
  if (
    Number.isInteger(address.phoneNumber1) &&
    address.phoneNumber1.toString().length > 0
  ) {
    temp += "\n" + address.phoneNumber1;
  }

  return temp;
};

/**
 * Item Header
 * @returns
 */
const ItemHeaderRow = ({ type }: { type: number }) => {
  return (
    <HStack spacing={2}>
      <Box width="1%">{}</Box>
      {(type === TRANSACTION_TYPES["SI"] ||
        type === TRANSACTION_TYPES["SR"]) && (
        <Box width="2%" fontSize={"0.7em"} letterSpacing={2}>
          <Tooltip label="Back Order" fontSize={"0.8em"} letterSpacing={2}>
            B.O?
          </Tooltip>
        </Box>
      )}
      <Box
        width={type == TRANSACTION_TYPES["SR"] ? "15%" : "18%"}
        textAlign={"center"}
      >
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          IDENTIFIER
        </_Label>
      </Box>
      {type == TRANSACTION_TYPES["SI"] && (
        <Box width="5%" textAlign={"center"}>
          <_Label fontSize={"0.7em"} letterSpacing={2}>
            INV. QTY.
          </_Label>
        </Box>
      )}
      <Box width="5%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          {type == TRANSACTION_TYPES["SR"] ? "TXN. QTY." : "QTY."}
        </_Label>
      </Box>
      {type == TRANSACTION_TYPES["SR"] && (
        <Box width="5%" textAlign={"center"}>
          <_Label fontSize={"0.7em"} letterSpacing={2}>
            RET. QTY.
          </_Label>
        </Box>
      )}
      <Box width="5%" textAlign={"left"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          UNIT
        </_Label>
      </Box>
      <Box
        width={type === TRANSACTION_TYPES["SI"] ? "25%" : "35%"}
        textAlign={"center"}
      >
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          DESCRIPTION
        </_Label>
      </Box>
      {(type == TRANSACTION_TYPES["SI"] || type == TRANSACTION_TYPES["QT"]) && (
        <Box width="5%" textAlign={"center"}>
          <_Label fontSize={"0.7em"} letterSpacing={2} color="#DC143C">
            OUR $
          </_Label>
        </Box>
      )}
      <Box width="6%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          BASE $
        </_Label>
      </Box>
      <Box width="6%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          DISCOUNT %
        </_Label>
      </Box>
      <Box width="6%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          $ / ITEM
        </_Label>
      </Box>
      <Box width="6%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          AMOUNT
        </_Label>
      </Box>
      <Box width="3%" textAlign={"center"}>
        <_Label fontSize={"0.7em"} letterSpacing={2}>
          TAX %
        </_Label>
      </Box>
    </HStack>
  );
};

/**
 * Calculate Price Per Item
 * @param itemPrice
 * @param discountRate
 * @returns
 */
const calculatePricePerItem = (itemPrice: number, discountRate: number) => {
  let discountAmount = (itemPrice * discountRate) / 100;
  return itemPrice - discountAmount;
};

/**
 * This method will calculate Amount.
 * @param pricePerItem
 * @param quantity
 */
const calculateAmount = (pricePerItem: number, quantity: number) => {
  return pricePerItem * quantity;
};

/**
 * Item Field Row Props
 */
interface ItemFieldRowProps {
  rowIndex: number;
  type: number;
  setIsClientChangedSuccessfully: any;
  enableEditing: boolean;
  hidePrivateDetails: boolean;
}

/**
 * Item Row
 * @returns
 */
const ItemFieldRow = memo(
  ({
    rowIndex,
    type,
    setIsClientChangedSuccessfully,
    enableEditing,
    hidePrivateDetails,
  }: ItemFieldRowProps) => {
    // Set Txn Details
    const {
      id,
      __lockCounter,
      clientDetails,
      details,
      itemDetailsForTransactions,
      selectedSalesInvoice,
      paymentMethod,
      disableFederalTaxes,
      disableProvincialTaxes,
      setProperty,
      updateAmounts,
    } = transactionStore(
      (state) => ({
        id: state.id,
        __lockCounter: state.__lockCounter,
        clientDetails: state.clientDetails,
        details: state.details,
        itemDetailsForTransactions: state.itemDetailsForTransactions,
        selectedSalesInvoice: state.selectedSalesInvoice,
        paymentMethod: state.paymentMethod,
        disableFederalTaxes: state.disableFederalTaxes,
        disableProvincialTaxes: state.disableProvincialTaxes,
        updateAmounts: state.updateAmounts,
        setProperty: state.setProperty,
      }),
      shallow
    );

    // Old Client
    // Used for comparison
    const [oldClient, setOldClient] = useState<ClientDetails | null>(
      clientDetails
    );

    /* Processed State */
    const { isProcessed } = txnStateStore((state) => ({
      isProcessed: state.isProcessed,
    }));

    // Hooks
    const [itemDetails, setItemDetails] =
      useState<ItemDetailsForTransactions | null>(
        details[rowIndex].itemId !== null &&
          itemDetailsForTransactions !== null &&
          itemDetailsForTransactions !== undefined
          ? itemDetailsForTransactions[details[rowIndex].itemId || -1]
          : null
      );
    const [amountPerItem, setAmountPerItem] = useState<number>(
      details[rowIndex].amountPerItem
    );
    const [pricePerItem, setPricePerItem] = useState<number>(
      details[rowIndex].pricePerItem > 0
        ? details[rowIndex].pricePerItem
        : details[rowIndex].quantity * details[rowIndex].basePrice
    );

    // Flag
    const [isItemSet, setItemSetFlag] = useState<boolean>(false);

    /**
     * Calculate All Amounts.
     * @param details
     * @return Object
     */
    const calculateAllAmounts = (
      detail: RowDetails,
      isSalesReturn: boolean = false
    ) => {
      let quantity = isSalesReturn
        ? detail.returnQuantity || 0
        : detail.quantity;
      let basePrice = detail.basePrice;
      let discountRate = detail.discountRate;
      let pricePerItem = toFixed(
        calculatePricePerItem(basePrice, discountRate)
      );
      let amountPerItem = toFixed(calculateAmount(pricePerItem, quantity));
      return {
        amountPerItem: amountPerItem,
        pricePerItem: pricePerItem,
      };
    };

    const [disableEditingItem, setDisableEditingItem] =
      useState<boolean>(false);

    useEffect(() => {
      setDisableEditingItem(
        id && paymentMethod !== PAY_LATER_ID ? true : false
      );
    }, [paymentMethod]);

    // Flag for disabling discount on item
    const [disableDiscountOnItem, setDisableDiscountOnItem] =
      useState<boolean>(false);

    const [selectedItem, setSelectedItem] = useState<string>(
      details[rowIndex].itemId !== null ? `${details[rowIndex].identifier}` : ""
    );

    /**
     * Change Amount for all rows as well as total amounts when the client changes.
     */
    useEffect(() => {
      if (clientDetails !== null) {
        setIsClientChangedSuccessfully(false);

        // Check for Existing Record
        // And Check for Client Change
        // Set Discount
        // [POSSIBLE DEAD CODE. REMOVE LATER]
        // if (
        //   (details[rowIndex].isExisting !== 1 ||
        //     (oldClient !== null && oldClient.id != clientDetails.id)) &&
        //   type === TRANSACTION_TYPES["SR"] &&
        //   id
        // ) {
        //   details[rowIndex].discountRate = clientDetails.standardDiscount;
        // }

        if (oldClient !== null && oldClient.id != clientDetails.id) {
          setProperty("selectedSalesInvoice", undefined);
          setProperty("selectedSalesInvoiceLastModifiedTimestamp", undefined);
        }

        // Calculate All Amounts
        let prices = calculateAllAmounts(
          details[rowIndex],
          type === TRANSACTION_TYPES["SR"]
        );
        details[rowIndex].pricePerItem = prices.pricePerItem;
        details[rowIndex].amountPerItem = prices.amountPerItem;

        // Set Amounts
        setAmountPerItem(prices.amountPerItem);
        setPricePerItem(prices.pricePerItem);

        /* Update Amounts */
        updateAmounts();

        // Set Status to true
        setIsClientChangedSuccessfully(true);

        // Set Old Client
        setOldClient(clientDetails);

        // Set Selected Item
        setSelectedItem(
          details[rowIndex].itemId !== null
            ? `${details[rowIndex].identifier}`
            : ""
        );
      }
    }, [clientDetails, selectedSalesInvoice]);

    // Calculate Profit margin, COGS Margin for this item.
    let profitMarginForThisItem: number = 0;
    let cogsMarginForThisItem: number = 0;
    let profitSignalColor = "#E5E4E2";
    if (itemDetails !== null) {
      let buyingCost = details[rowIndex].buyingCost;

      // Take Base Selling Price as default
      let originalSellingPrice = itemDetails.prices[storeId].sellingPrice;
      let sellingPrice = originalSellingPrice;

      // Check for Different Selling Price
      // If found, take that into consideration
      let temp = details[rowIndex].pricePerItem;
      if (!isNaN(temp)) sellingPrice = temp;

      profitMarginForThisItem = calculateProfitMargin(sellingPrice, buyingCost);
      cogsMarginForThisItem = calculateCOGSMargin(sellingPrice, buyingCost);

      // Determine profit/loss
      if (sellingPrice < buyingCost) {
        profitMarginForThisItem = -profitMarginForThisItem;
      }

      // Fetch Quantity
      let quantity: number = details[rowIndex].quantity;

      // Select Border Color
      if (quantity == 0) {
        profitSignalColor = "#E5E4E2";
        profitMarginForThisItem = 0;
      } else
        profitSignalColor = getProfitMarginColorScheme(profitMarginForThisItem);
    }

    // Is ReadOnly
    let isReadOnly: boolean = false;
    if (details[rowIndex].itemId === null) {
      if (itemDetails === null || __lockCounter !== 0) isReadOnly = true;
    }

    if (details[rowIndex].itemId !== null && isItemSet === false)
      setItemSetFlag(true);

    // Calculate Total Tax rate
    let totalTaxRate = 0;

    if (disableFederalTaxes === 0)
      totalTaxRate += details[rowIndex].gstHSTTaxRate;
    if (disableProvincialTaxes === 0 && details[rowIndex].pstTaxRate > 0) {
      totalTaxRate += details[rowIndex].pstTaxRate;
    }

    /**
     * Credit Or Debit Note Selected
     */
    const creditOrDebitNoteSelected = (event: any) => {
      details[rowIndex].itemId = 0;
      details[rowIndex].identifier = event.label;
      details[rowIndex].quantity = 0;
      details[rowIndex].unit = "Each";
      details[rowIndex].description = event.label;
      details[rowIndex].pricePerItem = 0;
      details[rowIndex].amountPerItem = 0;

      let discountRate = details[rowIndex].discountRate;
      if (clientDetails !== null) discountRate = 0;
      details[rowIndex].discountRate = discountRate;

      details[rowIndex].gstHSTTaxRate = disableFederalTaxes
        ? 0
        : GST_HST_TAX_RATE;
      details[rowIndex].pstTaxRate = disableProvincialTaxes ? 0 : PST_TAX_RATE;

      // Set item Set Flag
      setItemSetFlag(true);
    };

    /**
     * This method will select item selected.
     * @param event
     */
    const inventoryItemSelected = (event: any) => {
      // Reset Details to Default
      details[rowIndex] = { ...defaultRowItemDetails };

      let sellingPrice: number = 0;

      /* Set Original Selling Price */
      details[rowIndex].originalSellingPrice =
        event.value.prices[storeId].sellingPrice;

      if (clientDetails && clientDetails.isSelfClient) {
        // Selling Price
        sellingPrice =
          clientDetails && clientDetails.isSelfClient
            ? event.value.prices[storeId].buyingCost
            : event.value.prices[storeId].sellingPrice;
      } else {
        // Set Original Selling Price
        if (clientDetails) {
          // Check for Custom Selling Price
          let customSellingPriceForItems =
            clientDetails.customSellingPriceForItems[storeId];

          let itemId = event.value.id;

          // Check for Current Item in List
          let itemsList = Object.keys(customSellingPriceForItems);
          if (itemsList.indexOf(itemId.toString()) !== -1) {
            sellingPrice = customSellingPriceForItems[itemId].sellingPrice;
          } else {
            // Check Custom Profit Margin.
            let standardProfitMargins = clientDetails.standardProfitMargins;
            let profitMarginThisItem = getProfitMarginByItemIdentifierPrefix(
              standardProfitMargins,
              event.value.identifier
            );

            sellingPrice = calculateCOGSMarginByMargin(
              event.value.prices[storeId].buyingCost,
              profitMarginThisItem
            );

            if (sellingPrice === -1)
              sellingPrice = event.value.prices[storeId].sellingPrice;
          }
        } else {
          sellingPrice = event.value.prices[storeId].sellingPrice;
        }
      }

      // Set Base Selling Price
      details[rowIndex].basePrice = sellingPrice;

      // Set Account
      details[rowIndex].account = event.value.account;

      // Calculate COGS Margin
      if (
        calculateCOGSMargin(
          event.value.prices[storeId].buyingCost,
          event.value.prices[storeId].sellingPrice
        ) < 20
      ) {
        // Provide No discount on this item.
        details[rowIndex].discountRate = 0;
      } else {
        let discountRate = details[rowIndex].discountRate;
        if (clientDetails !== null) {
          discountRate = clientDetails.standardDiscount;
        }
        details[rowIndex].discountRate = discountRate;
      }

      // Disable Any Discount if client is self
      // Discount Any Discount if Item is on the flyer.
      if (
        (clientDetails && clientDetails.isSelfClient) ||
        event.value.disableDiscount === true
      ) {
        details[rowIndex].discountRate = 0;

        // Disable Editing Discount of Item
        setDisableDiscountOnItem(true);
      }

      // Set Item Details
      setItemDetails(event.value);

      // Set item Set Flag
      setItemSetFlag(true);

      // Set Details
      details[rowIndex].itemId = event.value.id;
      details[rowIndex].identifier = event.value.identifier;
      details[rowIndex].description = event.value.description;
      details[rowIndex].unit = event.value.unit;
      details[rowIndex].category = event.value.category;
      details[rowIndex].buyingCost = event.value.prices[storeId].buyingCost;
      details[rowIndex].gstHSTTaxRate = disableFederalTaxes
        ? 0
        : GST_HST_TAX_RATE;
      details[rowIndex].pstTaxRate = disableProvincialTaxes ? 0 : PST_TAX_RATE;
    };

    const toast = useToast();

    const [isBackOrderItem, setIsBackOrderItem] = useState<number>(
      details[rowIndex].isBackOrder
    );

    const [itemSuggestions, setItemSuggestions] = useState<any>([]);

    const loadOptionsForItem = (searchTerm: string) => {
      httpService
        .fetch<ItemDetailsForTransactions[]>(
          {
            search_term: searchTerm,
            store_id: localStorage.getItem("storeId"),
          },
          "inv_item_details_for_transactions"
        )
        .then((res: any) => {
          if (res.status === 200) {
            let response: APIResponse<ItemDetailsForTransactions[]> = res.data;
            if (response.status === true) {
              setItemSuggestions(buildSearchListForItem(response.data, true));
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

    // Select Load options for Credit Note
    const loadOptionsForCreditNote = (_: string) => {
      setItemSuggestions([
        {
          label: "Credit Note",
          value: "CN",
        },
      ]);
    };

    // Select load options for Debit Note
    const loadOptionsForDebitNote = (_: string) => {
      setItemSuggestions([
        {
          label: "Debit Note",
          value: "DN",
        },
      ]);
    };

    const onItemSelected = (selectedItem: any) => {
      if (
        type === TRANSACTION_TYPES["SI"] ||
        type === TRANSACTION_TYPES["QT"]
      ) {
        let itemDetails: ItemDetailsForTransactions = selectedItem.value;
        if (clientDetails) {
          if (
            clientDetails.customSellingPriceForItems[storeId][itemDetails.id]
          ) {
            itemDetails.prices[storeId].sellingPrice =
              clientDetails.customSellingPriceForItems[storeId][
                itemDetails.id
              ].sellingPrice;
            selectedItem.value = itemDetails;
          }
        }
        inventoryItemSelected(selectedItem);
      }

      if (
        type === TRANSACTION_TYPES["CN"] ||
        type === TRANSACTION_TYPES["DN"]
      ) {
        creditOrDebitNoteSelected(selectedItem);
      }
    };

    const onItemCleared = () => {
      // Enable Editing on Discount
      setDisableDiscountOnItem(false);
      setIsBackOrderItem(0);

      // Removing An Item
      setItemDetails(null);

      // Set Item Set Flag
      setItemSetFlag(false);

      // Reset Details
      details[rowIndex] = { ...defaultRowItemDetails };

      setPricePerItem(0);
      setAmountPerItem(0);

      // Clear Amount of the removed item
      updateAmounts();
    };

    return (
      <HStack spacing={2} marginTop={rowIndex == 0 ? 0 : 1}>
        {/* Item Details */}
        <Box width="1%">
          {itemDetails && (
            <Popover>
              <PopoverTrigger>
                <Button variant={"link"} size="xs">
                  <FaInfo color="#4781F2" />
                </Button>
              </PopoverTrigger>
              <PopoverContent width="100%">
                <PopoverArrow />
                <PopoverCloseButton />
                <PopoverBody>
                  <HStack spacing={5}>
                    <_Label fontSize="0.8em">Aisle:</_Label>
                    <_Label
                      fontSize="0.8em"
                      color={
                        itemDetails.aisle.length === 0 ? "lightgray" : "#29C8F1"
                      }
                    >
                      {itemDetails.aisle || <SiZeromq />}
                    </_Label>
                    <_Label fontSize="0.8em">Shelf:</_Label>
                    <_Label
                      fontSize="0.8em"
                      color={
                        itemDetails.shelf.length === 0 ? "lightgray" : "#29C8F1"
                      }
                    >
                      {itemDetails.shelf || <SiZeromq />}
                    </_Label>
                    <_Label fontSize="0.8em">Column:</_Label>
                    <_Label
                      fontSize="0.8em"
                      color={
                        itemDetails.column.length === 0
                          ? "lightgray"
                          : "#29C8F1"
                      }
                    >
                      {itemDetails.column || <SiZeromq />}
                    </_Label>
                  </HStack>
                  <HStack spacing={5}>
                    <HStack>
                      <_Label fontSize="0.8em" color="brown">
                        <i>Our Cost:</i>
                      </_Label>
                      <CurrencyIcon></CurrencyIcon>
                      <_Label
                        fontSize="0.8em"
                        fontFamily={numberFont}
                        letterSpacing={2}
                      >
                        {itemDetails !== null
                          ? formatNumberWithDecimalPlaces(
                              itemDetails.prices[storeId].buyingCost,
                              2
                            )
                          : "0"}
                      </_Label>
                    </HStack>
                    <HStack>
                      <_Label fontSize="0.8em" color="#6F1DF5">
                        <u>Selling Price:</u>
                      </_Label>
                      <CurrencyIcon></CurrencyIcon>
                      <_Label
                        fontSize="0.8em"
                        fontFamily={numberFont}
                        letterSpacing={2}
                      >
                        {itemDetails !== null
                          ? formatNumberWithDecimalPlaces(
                              itemDetails.prices[storeId].sellingPrice,
                              2
                            )
                          : "0"}
                      </_Label>
                    </HStack>
                  </HStack>
                  <HStack>
                    <HStack>
                      <_Label fontSize="0.8em" color="#097969">
                        Profit Margin:
                      </_Label>
                      <_Label color={profitSignalColor}>
                        {profitMarginForThisItem === 0 ? (
                          <SiZeromq />
                        ) : profitMarginForThisItem < 0 ? (
                          <BiSolidDownArrow />
                        ) : (
                          <BiSolidUpArrow />
                        )}
                      </_Label>
                    </HStack>
                    <_Label
                      fontSize="0.8em"
                      fontFamily={numberFont}
                      letterSpacing={2}
                    >
                      {profitMarginForThisItem.toFixed(2)}%
                    </_Label>
                  </HStack>
                  <HStack>
                    <HStack>
                      <_Label fontSize="0.8em" color="#899499">
                        C.O.G.S Margin:
                      </_Label>
                      <_Label color={profitSignalColor}>
                        {cogsMarginForThisItem === 0 ? (
                          <SiZeromq />
                        ) : cogsMarginForThisItem < 0 ? (
                          <BiSolidDownArrow />
                        ) : (
                          <BiSolidUpArrow />
                        )}
                      </_Label>
                    </HStack>
                    <_Label
                      fontSize="0.8em"
                      fontFamily={numberFont}
                      letterSpacing={2}
                    >
                      {cogsMarginForThisItem.toFixed(2)}%
                    </_Label>
                  </HStack>
                  <HStack>
                    <_Label fontSize="0.8em" color="#E48709">
                      Quantity:
                    </_Label>
                    <_Label
                      fontSize="0.8em"
                      fontFamily={numberFont}
                      letterSpacing={2}
                    >
                      {itemDetails.quantity || "0"}
                    </_Label>
                  </HStack>
                  <HStack>
                    <_Label fontSize="0.8em" color="purple">
                      Memo:
                    </_Label>
                    <_Label fontSize="0.8em">{itemDetails.memo || ""}</_Label>
                  </HStack>
                  <HStack>
                    <_Label fontSize="0.8em" color="green">
                      Additional Information:
                    </_Label>
                    <_Label fontSize="0.8em">
                      {itemDetails.additionalInformation || ""}
                    </_Label>
                  </HStack>
                </PopoverBody>
              </PopoverContent>
            </Popover>
          )}
        </Box>
        {/* Back Order */}
        {(type === TRANSACTION_TYPES["SI"] ||
          type === TRANSACTION_TYPES["SR"]) && (
          <Box width="2%" marginLeft={1} paddingBottom={0} paddingTop={1}>
            <Checkbox
              isReadOnly={type === TRANSACTION_TYPES["SR"]}
              isChecked={isBackOrderItem ? true : false}
              isDisabled={enableEditing === false || isItemSet === false}
              colorScheme="purple"
              onChange={() => {
                let newStatus: number = isBackOrderItem ^ 1;
                setIsBackOrderItem(newStatus);
                details[rowIndex].isBackOrder = newStatus;
                updateAmounts();
              }}
            ></Checkbox>
          </Box>
        )}
        {/* Item Identifier */}
        <Box width={type == TRANSACTION_TYPES["SR"] ? "15%" : "18%"}>
          {/* <AsyncSelect
            tabSelectsValue={true}
            key={getUUID()}
            isDisabled={
              type == TRANSACTION_TYPES["SR"] ||
              enableEditing === false ||
              __lockCounter !== 0 ||
              isProcessed ||
              disableEditingItem
            }
            placeholder="Identifier"
            styles={AsyncSelectStyle}
            isClearable={type == TRANSACTION_TYPES["SR"] ? false : true}
            {...defaultItemValue}
            // {...(type !== TRANSACTION_TYPES["SR"] && enableEditing === true ? {inputValue: details[rowIndex].identifier}: {})}
            onChange={(
              event: {
                label: string;
                value: ItemDetailsForTransactions | null;
              } | null
            ) => {
              if (event !== null && event.value !== null) {
                if (
                  type === TRANSACTION_TYPES["SI"] ||
                  type === TRANSACTION_TYPES["QT"]
                ) {
                  let value = event.value;
                  if (clientDetails) {
                    if (
                      clientDetails.customSellingPriceForItems[storeId][
                        value.id
                      ]
                    ) {
                      value.prices[storeId].sellingPrice =
                        clientDetails.customSellingPriceForItems[storeId][
                          value.id
                        ].sellingPrice;
                      event.value = value;
                    }
                  }

                  inventoryItemSelected(event);
                }

                if (
                  type === TRANSACTION_TYPES["CN"] ||
                  type === TRANSACTION_TYPES["DN"]
                ) {
                  creditOrDebitNoteSelected(event);
                }
              } else {
                // Enable Editing on Discount
                setDisableDiscountOnItem(false);
                setIsBackOrderItem(0);

                // Removing An Item 
                setItemDetails(null);

                // Set Item Set Flag
                setItemSetFlag(false);

                // Reset Details
                details[rowIndex] = { ...defaultRowItemDetails };

                setPricePerItem(0);
                setAmountPerItem(0);

                // Clear Amount of the removed item
                updateAmounts();
              }
            }}
            loadOptions={
              type === TRANSACTION_TYPES["SI"] ||
              type === TRANSACTION_TYPES["QT"]
                ? loadOptionsForItem
                : type === TRANSACTION_TYPES["CN"]
                ? loadOptionsForCreditNote
                : type === TRANSACTION_TYPES["DN"]
                ? loadOptionsForDebitNote
                : () => {}
            }
          ></AsyncSelect> */}
          <AutoSuggest
            suggestions={itemSuggestions}
            onSuggestionsClearRequested={() => setItemSuggestions([])}
            onSuggestionsFetchRequested={({ value }) => {
              if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
              if (
                type === TRANSACTION_TYPES["SI"] ||
                type === TRANSACTION_TYPES["QT"]
              )
                loadOptionsForItem(value);
              else if (type === TRANSACTION_TYPES["CN"]) {
                loadOptionsForCreditNote(value);
              } else if (type === TRANSACTION_TYPES["DN"]) {
                loadOptionsForDebitNote(value);
              }
            }}
            onSuggestionSelected={(_: any, { suggestionIndex }) => {
              onItemSelected(itemSuggestions[suggestionIndex]);
            }}
            getSuggestionValue={(suggestion: any) => {
              if (type === TRANSACTION_TYPES["CN"]) return "Credit Note";
              else if (type === TRANSACTION_TYPES["DN"]) return "Debit Note";
              return `${suggestion.value.identifier}`;
            }}
            renderSuggestion={(suggestion: any) => (
              <span>&nbsp;{suggestion.label}</span>
            )}
            inputProps={{
              style: {
                width:
                  type == TRANSACTION_TYPES["SR"]
                    ? "15vw"
                    : type === TRANSACTION_TYPES["QT"]
                    ? "17vw"
                    : "18vw",
                borderBottomColor: profitSignalColor,
                ...AutoSuggestStyle,
              },
              placeholder:
                `Search item...` +
                (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                  ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                  : ""),
              value: selectedItem,
              onChange: (_, { newValue }) => {
                setSelectedItem(newValue);
                if (newValue.trim() === "") {
                  onItemCleared();
                }
              },
              disabled:
                type == TRANSACTION_TYPES["SR"] ||
                enableEditing === false ||
                __lockCounter !== 0 ||
                isProcessed ||
                disableEditingItem,
              readOnly: type == TRANSACTION_TYPES["SR"],
            }}
            highlightFirstSuggestion={true}
          ></AutoSuggest>
        </Box>
        {/* Inventory Quantity  */}
        {type == TRANSACTION_TYPES["SI"] && (
          <Box width="5%" textAlign={"center"}>
            <_Label fontSize="0.75em" fontFamily={numberFont} letterSpacing={2}>
              {(itemDetails && itemDetails.quantity) || "0"}
            </_Label>
          </Box>
        )}
        {/* Quantity */}
        <Box width="5%" zIndex={0}>
          <_Input
            _key={getUUID()}
            isReadOnly={
              enableEditing === false ||
              isReadOnly ||
              isProcessed ||
              type === TRANSACTION_TYPES["SR"] ||
              disableEditingItem
            }
            borderBottomColor={profitSignalColor}
            type="number"
            fontSize="0.8em"
            fontFamily={numberFont}
            letterSpacing={2}
            defaultValue={details[rowIndex].quantity}
            onClick={(event: any) => {
              event.target.select();
            }}
            onBlur={(event: any) => {
              if (enableEditing === false) return;
              if (event === null || event.target === null) {
                if (
                  type === TRANSACTION_TYPES["SI"] ||
                  type === TRANSACTION_TYPES["QT"]
                ) {
                  if (itemDetails === null) return;
                }
                return;
              }

              // Set Quantity
              details[rowIndex].quantity = parseFloat(
                event.target.value.trim()
              );

              // Calculate Amounts
              let prices = calculateAllAmounts(
                details[rowIndex],
                type === TRANSACTION_TYPES["SR"]
              );

              // Update Prices
              details[rowIndex].pricePerItem = prices.pricePerItem;
              details[rowIndex].amountPerItem = prices.amountPerItem;

              // Set Amounts
              setPricePerItem(details[rowIndex].pricePerItem);
              setAmountPerItem(details[rowIndex].amountPerItem);

              updateAmounts();
            }}
          ></_Input>
        </Box>
        {/* Return Quantity */}
        {type == TRANSACTION_TYPES["SR"] && (
          <Box width="5%" textAlign={"center"}>
            <_Input
              isReadOnly={
                (isItemSet ? false : true) ||
                enableEditing === false ||
                isBackOrderItem ||
                isProcessed
              }
              borderBottomColor={profitSignalColor}
              fontSize="0.8em"
              type="number"
              fontFamily={numberFont}
              defaultValue={details[rowIndex].returnQuantity}
              onClick={(event: any) => {
                event.target.select();
              }}
              onBlur={(event: any) => {
                if (enableEditing === false) return;
                if (event && event.target) {
                  // Set Quantity
                  details[rowIndex].returnQuantity = parseFloat(
                    event.target.value.trim()
                  );

                  // Calculate Amounts
                  let prices = calculateAllAmounts(
                    details[rowIndex],
                    type === TRANSACTION_TYPES["SR"]
                  );

                  // Update Prices
                  details[rowIndex].pricePerItem = prices.pricePerItem;
                  details[rowIndex].amountPerItem = prices.amountPerItem;

                  // Set Amounts
                  setPricePerItem(details[rowIndex].pricePerItem);
                  setAmountPerItem(details[rowIndex].amountPerItem);

                  updateAmounts();
                }
              }}
            ></_Input>
          </Box>
        )}
        {/* Unit  */}
        <Box width="5%">
          <_Label fontSize={"0.7em"} letterSpacing={2}>
            {details[rowIndex].unit || itemDetails?.unit}
          </_Label>
        </Box>
        {/* Description */}
        <Box width={type === TRANSACTION_TYPES["SI"] ? "25%" : "35%"}>
          <_Input
            _key={getUUID()}
            onClick={(event: any) => {
              event.target.select();
            }}
            isReadOnly={
              enableEditing === false ||
              isReadOnly ||
              type === TRANSACTION_TYPES["SR"] ||
              isProcessed ||
              disableEditingItem
            }
            borderBottomColor={profitSignalColor}
            fontSize={"0.8em"}
            defaultValue={
              details[rowIndex].description || itemDetails?.description
            }
            onBlur={(event: any) => {
              if (event !== null && event.target !== null) {
                details[rowIndex].description = event.target.value.trim();
              }
            }}
          ></_Input>
        </Box>
        {(type == TRANSACTION_TYPES["SI"] ||
          type == TRANSACTION_TYPES["QT"]) && (
          <Box width="5%" textAlign={"center"}>
            {/* Buying Cost */}
            <_Label
              _key={getUUID()}
              fontSize="0.75em"
              fontFamily={numberFont}
              hide={hidePrivateDetails}
              toggleVisibility={true}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(details[rowIndex].buyingCost, 2)}
            </_Label>
          </Box>
        )}
        {/* Base Price */}
        <Box width="6%">
          <_Input
            _key={getUUID()}
            onClick={(event: any) => {
              event.target.select();
            }}
            isReadOnly={
              enableEditing === false ||
              isReadOnly ||
              type === TRANSACTION_TYPES["SR"] ||
              disableEditingItem ||
              isProcessed ||
              (clientDetails &&
                clientDetails.isSelfClient &&
                (type === TRANSACTION_TYPES["SI"] ||
                  type === TRANSACTION_TYPES["QT"]))
            }
            borderBottomColor={profitSignalColor}
            fontFamily={numberFont}
            type="text"
            fontSize="0.8em"
            defaultValue={
              isItemSet
                ? formatNumberWithDecimalPlaces(details[rowIndex].basePrice, 2)
                : 0
            }
            onBlur={(event: any) => {
              if (enableEditing === false) return;
              if (event === null || event.target === null) {
                if (
                  type === TRANSACTION_TYPES["SI"] ||
                  type === TRANSACTION_TYPES["QT"]
                ) {
                  if (itemDetails === null) return;
                }
                return;
              }

              // Set Price Per Item
              details[rowIndex].basePrice = parseFloat(
                event.target.value.trim()
              );

              // Calculate COGS Margin
              if (
                calculateCOGSMargin(
                  details[rowIndex].buyingCost,
                  details[rowIndex].basePrice
                ) < 20
              ) {
                // Provide No discount on this item.
                details[rowIndex].discountRate = 0;
              }

              // Calculate All Amounts
              let prices = calculateAllAmounts(
                details[rowIndex],
                type === TRANSACTION_TYPES["SR"]
              );

              details[rowIndex].pricePerItem = prices.pricePerItem;
              details[rowIndex].amountPerItem = prices.amountPerItem;

              // Set Amounts
              setPricePerItem(details[rowIndex].pricePerItem);
              setAmountPerItem(details[rowIndex].amountPerItem);

              /* Update Amount */
              updateAmounts();
            }}
          ></_Input>
        </Box>
        {/* Discount */}
        <Box width="6%">
          <_Input
            _key={getUUID()}
            onClick={(event: any) => {
              event.target.select();
            }}
            isReadOnly={
              disableDiscountOnItem ||
              disableEditingItem ||
              enableEditing === false ||
              isReadOnly ||
              type === TRANSACTION_TYPES["SR"] ||
              isProcessed ||
              (clientDetails &&
                clientDetails.isSelfClient &&
                (type === TRANSACTION_TYPES["SI"] ||
                  type === TRANSACTION_TYPES["QT"]))
            }
            borderBottomColor={profitSignalColor}
            type="text"
            fontSize="0.8em"
            fontFamily={numberFont}
            defaultValue={
              isItemSet
                ? formatNumberWithDecimalPlaces(
                    details[rowIndex].discountRate,
                    2
                  )
                : 0
            }
            onBlur={(event: any) => {
              if (
                enableEditing === false ||
                event === null ||
                event.target === null
              )
                return;

              // Set Discount
              details[rowIndex].discountRate = parseFloat(
                event.target.value.trim()
              );

              if (isNaN(details[rowIndex].discountRate))
                details[rowIndex].discountRate = 0;

              // Calculate All Amounts
              let prices = calculateAllAmounts(
                details[rowIndex],
                type === TRANSACTION_TYPES["SR"]
              );

              // Set
              details[rowIndex].pricePerItem = prices.pricePerItem;
              details[rowIndex].amountPerItem = prices.amountPerItem;

              // Set Amounts
              setPricePerItem(details[rowIndex].pricePerItem);
              setAmountPerItem(details[rowIndex].amountPerItem);

              updateAmounts();
            }}
          ></_Input>
        </Box>
        {/* Price / Item  */}
        <Box width="6%">
          <_Input
            _key={getUUID()}
            borderBottomColor={profitSignalColor}
            isReadOnly={true}
            type="text"
            fontSize="0.8em"
            fontFamily={numberFont}
            defaultValue={
              isItemSet
                ? formatNumberWithDecimalPlaces(pricePerItem, 2)
                : pricePerItem
            }
          ></_Input>
        </Box>
        {/* Amount */}
        <Box width="6%">
          <_Input
            _key={getUUID()}
            borderBottomColor={profitSignalColor}
            defaultValue={
              isItemSet
                ? formatNumberWithDecimalPlaces(amountPerItem, 2)
                : amountPerItem
            }
            type="text"
            isReadOnly={true}
            fontSize="0.8em"
            fontFamily={numberFont}
          ></_Input>
        </Box>
        {/* Tax  */}
        <Box width="3%">
          <_Input
            _key={getUUID()}
            borderBottomColor={profitSignalColor}
            isReadOnly={true}
            type="text"
            fontSize="0.8em"
            fontFamily={numberFont}
            defaultValue={
              type === TRANSACTION_TYPES["CN"] ||
              type === TRANSACTION_TYPES["DN"]
                ? formatNumberWithDecimalPlaces(totalTaxRate, 2)
                : itemDetails !== null
                ? formatNumberWithDecimalPlaces(totalTaxRate, 2)
                : isItemSet
                ? formatNumberWithDecimalPlaces(totalTaxRate, 2)
                : 0
            }
          ></_Input>
        </Box>
      </HStack>
    );
  }
);

/**
 * Items Table Props
 */
interface ItemsTableProps {
  setIsClientChangedSuccessfully: any;
  type: number;
  enableEditing: boolean;
  hidePrivateDetails: boolean;
}

/**
 * Items Table
 * @param type
 * @returns
 */
const ItemsTable = memo(
  ({
    setIsClientChangedSuccessfully,
    type,
    enableEditing,
    hidePrivateDetails,
  }: ItemsTableProps) => {
    const { __lockCounter, details, addRow } = transactionStore(
      (state) => ({
        __lockCounter: state.__lockCounter,
        details: Object.keys(state.details),
        addRow: state.addRow,
      }),
      shallow
    );

    const keys = Object.keys(details);
    return (
      <Box
        height={type === TRANSACTION_TYPES["SI"] ? "47.2vh" : "50.7vh"}
        overflow="auto"
        bgColor="white"
        tabIndex={0}
        onKeyDown={(event) => {
          if (
            type !== TRANSACTION_TYPES["SR"] &&
            __lockCounter === 0 &&
            (event.code === "Enter" || event.code === "NumpadEnter") &&
            enableEditing
          ) {
            addRow();
          }
        }}
      >
        <Card borderRadius={2}>
          <CardBody padding={1}>
            <ItemHeaderRow type={type} />
            {keys.map((key) => (
              <ItemFieldRow
                enableEditing={enableEditing}
                key={key}
                rowIndex={parseInt(key)}
                type={type}
                setIsClientChangedSuccessfully={setIsClientChangedSuccessfully}
                hidePrivateDetails={hidePrivateDetails}
              ></ItemFieldRow>
            ))}
          </CardBody>
        </Card>
      </Box>
    );
  }
);

/**
 * Header Details Props
 */
interface HeaderDetailsProps extends ClientDetailsProps {
  enableEditing: boolean;
  type?: number;
  name: string;
  isViewOrUpdate?: boolean;
  setIsClientChangedSuccessfully: any;
}

/**
 * Transaction Header Details
 * @returns
 */
const TransactionHeaderDetails = ({
  enableEditing,
  type,
  name,
  isViewOrUpdate = false,
  setIsClientChangedSuccessfully,
}: HeaderDetailsProps) => {
  // Fetch Client
  const { fetch: fetchClient } = clientStore();

  // Transaction Store Details
  const {
    id,
    clientDetails,
    po,
    unitNo,
    vin,
    driverName,
    trailerNumber,
    odometerReading,
    accountNumber,
    purchasedBy,
    previousTxnId,
    nextTxnId,
    __lockCounter,
    txnDate,
    selectedSalesInvoice,
    setProperty,
    fetchInvoicesByClientForSalesReturns,
  } = transactionStore(
    (state) => ({
      id: state.id,
      clientDetails: state.clientDetails,
      po: state.po,
      unitNo: state.unitNo,
      vin: state.vin,
      driverName: state.driverName,
      trailerNumber: state.trailerNumber,
      odometerReading: state.odometerReading,
      accountNumber: state.accountNumber,
      purchasedBy: state.purchasedBy,
      previousTxnId: state.previousTxnId,
      nextTxnId: state.nextTxnId,
      __lockCounter: state.__lockCounter,
      txnDate: state.txnDate,
      selectedSalesInvoice: state.selectedSalesInvoice,
      setProperty: state.setProperty,
      fetchInvoicesByClientForSalesReturns:
        state.fetchInvoicesByClientForSalesReturns,
    }),
    shallow
  );

  // This stores all invoices for selected
  const [allInvoicesForSelectedClient, setAllInvoicesForSelectedClient] =
    useState<number[]>([]);

  const toast = useToast();

  // Load Clients
  const loadOptionsForClient = (searchTerm: string) => {
    /* Hide Inactive Client */
    fetchClient(searchTerm, true, type === TRANSACTION_TYPES["SR"])
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

  // Build Search List for Invoices
  const buildSearchListForShowingInvoices = (data: any): any => {
    let response = [];
    if (data !== undefined) {
      let keys = Object.keys(data).reverse();
      let count = keys.length;
      for (let i = 0; i < count; ++i) {
        response.push({
          label: keys[i],
          value: data[parseInt(keys[i])],
        });
      }
    }
    return response;
  };

  // Build Search List for Invoices
  const buildSearchListForSelectingInvoices = (data: any): any => {
    let response = [];
    if (data !== undefined) {
      let keys = Object.keys(data);
      let count = keys.length;
      for (let i = 0; i < count; ++i) {
        response.push({
          label: keys[i],
          value: data[parseInt(keys[i])],
        });
      }
    }
    return response;
  };

  // Default Client
  let defaultClient: { [key: string]: { [key: string]: any } } = {};
  if (isViewOrUpdate) {
    defaultClient = {
      defaultValue: {
        label: clientDetails?.primaryDetails.name,
        value: clientDetails?.id,
      },
    };
  }

  /* Processed State */
  const { isProcessed } = txnStateStore((state) => ({
    isProcessed: state.isProcessed,
  }));

  // ReadOnly Flag
  const isReadOnly: boolean =
    enableEditing === false || (__lockCounter === 0 ? false : true);

  /**
   * This method will Load Invoices By Client Id for Sales Returns.
   */
  const loadInvoicesByClientForSalesReturns = (
    invoiceId: string,
    callback: (args: any) => void
  ) => {
    fetchInvoicesByClientForSalesReturns(invoiceId)
      .then((res: any) => {
        let response: APIResponse<any> = res.data;
        if (response.status === true) {
          let invoices: { [id: number]: any } = response.data;
          if (Object.keys(invoices).length === 0) invoices = {};
          callback(buildSearchListForSelectingInvoices(invoices));
        } else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      });
  };

  /* Show Selected Sales Invoice for Sales Return when Updating/Viewing */
  let defaultSelectedSalesInvoice = {};
  if (isViewOrUpdate) {
    defaultSelectedSalesInvoice = {
      defaultValue: {
        label: selectedSalesInvoice,
        value: selectedSalesInvoice,
      },
    };
  }

  // Client Suggestions
  const [clientSuggestions, setClientSuggestions] = useState<any>([]);
  const [selectedClient, setSelectedClient] = useState<string>(
    defaultClient.defaultValue.label
  );

  // Client Select
  const onClientSelect = (selectedClient: any) => {
    let clientDetails: ClientDetails = selectedClient.value;
    setIsClientChangedSuccessfully(false);

    // Clear Name History
    clientDetails.nameHistory = [];

    setProperty("clientDetails", clientDetails);

    // Only change the Payment method when creating new Transaction
    if (id === null) {
      setProperty("paymentMethod", -1);
    }

    // Change Tax Status
    setProperty("disableFederalTaxes", clientDetails.disableFederalTaxes);
    setProperty("disableProvincialTaxes", clientDetails.disableProvincialTaxes);

    // Change Early Payment Details
    setProperty("earlyPaymentDiscount", clientDetails.earlyPaymentDiscount);

    setProperty(
      "earlyPaymentPaidWithinDays",
      clientDetails.earlyPaymentPaidWithinDays
    );

    setProperty("netAmountDueWithinDays", clientDetails.netAmountDueWithinDays);

    setProperty(
      "disableCreditTransactions",
      clientDetails.disableCreditTransactions
    );

    // Fetch All Invoices by Client for Sales Returns
    if (type === TRANSACTION_TYPES["SR"]) {
      setAllInvoicesForSelectedClient(
        buildSearchListForShowingInvoices(clientDetails.salesInvoices)
      );
    }

    // Set Successful client change status
    setIsClientChangedSuccessfully(true);
  };

  return (
    <>
      <Box width="100%">
        <Card>
          <CardBody padding={1} borderRadius={3} bgColor={"#D2E2F4"}>
            <HStack>
              <Box width="60%">
                <VStack align={"left"}>
                  <HStack spacing={100}>
                    <Box>
                      <_Label
                        fontWeight="bold"
                        fontSize={"large"}
                        letterSpacing={5}
                      >
                        {isViewOrUpdate ? "UPDATE" : "CREATE"}{" "}
                        {`${name.toUpperCase()}`}
                      </_Label>
                    </Box>
                    {isViewOrUpdate && (
                      <Box>
                        <HStack width="100%" spacing={1}>
                          <Button
                            pointerEvents={previousTxnId ? "all" : "none"}
                            disabled={previousTxnId ? false : true}
                            height={6}
                            variant={"link"}
                            onClick={() => {
                              if (previousTxnId !== null) {
                                let uri: string = "";
                                if (type == TRANSACTION_TYPES["SI"])
                                  uri = "/sales_invoice_update";
                                window.location.href = `${uri}?id=${previousTxnId}`;
                              }
                            }}
                          >
                            <AiOutlineArrowLeft
                              color={
                                previousTxnId !== null ? "#097969" : "gray"
                              }
                            />
                          </Button>
                          <Button
                            pointerEvents={nextTxnId ? "all" : "none"}
                            disabled={nextTxnId ? false : true}
                            height={6}
                            variant={"link"}
                            onClick={() => {
                              if (nextTxnId !== null) {
                                let uri: string = "";
                                if (type == TRANSACTION_TYPES["SI"])
                                  uri = "/sales_invoice_update";
                                window.location.href = `${uri}?id=${nextTxnId}`;
                              }
                            }}
                          >
                            <AiOutlineArrowRight
                              color={nextTxnId !== null ? "#097969" : "gray"}
                            />
                          </Button>
                        </HStack>
                      </Box>
                    )}
                  </HStack>
                  {/* Client Details Header */}
                  <VStack align={"left"}>
                    <Box>
                      <Box>
                        <HStack>
                          <Box width="100%">
                            <_Divider margin={1}></_Divider>
                            <HStack width="100%">
                              {/* Client Details  */}
                              <VStack width="50%" align={"left"}>
                                <HStack>
                                  <Box width="100%">
                                    {/* <AsyncSelect
                                      // key={getUUID()}
                                      tabSelectsValue={true}
                                      isDisabled={
                                        isReadOnly ||
                                        (type === TRANSACTION_TYPES["SR"] &&
                                          id !== null) ||
                                        isProcessed
                                      }
                                      placeholder="Search Customer by Name, Phone, Email Id"
                                      styles={AsyncSelectStyle}
                                      cacheOptions={false}
                                      loadOptions={loadOptionsForClient}
                                      defaultOptions={false}
                                      {...defaultClient}
                                      // {...(type !== TRANSACTION_TYPES["SR"] && enableEditing === true
                                      //   ? {inputValue: clientDetails?.primaryDetails.name}
                                      //   : {})}
                                      onChange={(_event: any) => {
                                        let clientDetails: ClientDetails =
                                          _event.value;
                                        setIsClientChangedSuccessfully(false);

                                        // Clear Name History
                                        clientDetails.nameHistory = [];

                                        setProperty(
                                          "clientDetails",
                                          clientDetails
                                        );

                                        // Only change the Payment method when creating new Transaction
                                        if (id === null) {
                                          setProperty("paymentMethod", -1);
                                        }

                                        // Change Tax Status
                                        setProperty(
                                          "disableFederalTaxes",
                                          clientDetails.disableFederalTaxes
                                        );
                                        setProperty(
                                          "disableProvincialTaxes",
                                          clientDetails.disableProvincialTaxes
                                        );

                                        // Change Early Payment Details
                                        setProperty(
                                          "earlyPaymentDiscount",
                                          clientDetails.earlyPaymentDiscount
                                        );

                                        setProperty(
                                          "earlyPaymentPaidWithinDays",
                                          clientDetails.earlyPaymentPaidWithinDays
                                        );

                                        setProperty(
                                          "netAmountDueWithinDays",
                                          clientDetails.netAmountDueWithinDays
                                        );

                                        setProperty(
                                          "disableCreditTransactions",
                                          clientDetails.disableCreditTransactions
                                        );

                                        // Fetch All Invoices by Client for Sales Returns
                                        if (type === TRANSACTION_TYPES["SR"]) {
                                          setAllInvoicesForSelectedClient(
                                            buildSearchListForShowingInvoices(
                                              clientDetails.salesInvoices
                                            )
                                          );
                                        }

                                        // Set Successful client change status
                                        setIsClientChangedSuccessfully(true);
                                      }}
                                    /> */}
                                    <AutoSuggest
                                      suggestions={clientSuggestions}
                                      onSuggestionsClearRequested={() =>
                                        setClientSuggestions([])
                                      }
                                      onSuggestionsFetchRequested={({
                                        value,
                                      }) => {
                                        if (
                                          value.length <
                                          AUTO_SUGGEST_MIN_INPUT_LENGTH
                                        )
                                          return;
                                        loadOptionsForClient(value);
                                      }}
                                      onSuggestionSelected={(
                                        _: any,
                                        { suggestionIndex }
                                      ) => {
                                        onClientSelect(
                                          clientSuggestions[suggestionIndex]
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
                                          width: "100%",
                                          ...AutoSuggestStyle,
                                        },
                                        placeholder:
                                          `Search Customer by Name, Phone, Email Id...` +
                                          (AUTO_SUGGEST_MIN_INPUT_LENGTH > 1
                                            ? `(min ${AUTO_SUGGEST_MIN_INPUT_LENGTH} chars)`
                                            : ""),
                                        value: selectedClient,
                                        disabled:
                                          isReadOnly ||
                                          (type === TRANSACTION_TYPES["SR"] &&
                                            id !== null) ||
                                          isProcessed,
                                        onChange: (_, { newValue }) => {
                                          setSelectedClient(newValue);
                                          if (newValue.trim() === "") {
                                          }
                                        },
                                      }}
                                      highlightFirstSuggestion={true}
                                    ></AutoSuggest>
                                  </Box>
                                  <Box>
                                    <Button
                                      letterSpacing={1}
                                      color="#808080"
                                      variant="link"
                                      fontSize="0.8em"
                                      onClick={() => {
                                        window.open(
                                          "/client",
                                          "",
                                          "toolbar=no,scrollbars=yes,width=800,height=500,top=200,left=350"
                                        );
                                      }}
                                    >
                                      ADD
                                    </Button>
                                  </Box>
                                </HStack>
                                <Textarea
                                  defaultValue={
                                    clientDetails?.primaryDetails !== undefined
                                      ? buildAddress(
                                          clientDetails.primaryDetails
                                        )
                                      : ""
                                  }
                                  fontFamily={"JetBrains Mono"}
                                  bgColor="white"
                                  resize="none"
                                  height={20}
                                  isReadOnly={true}
                                  fontSize="0.7em"
                                  borderRadius={3}
                                  borderBottomWidth={2}
                                  borderBottomColor={inputConfig.borderColor}
                                ></Textarea>
                              </VStack>
                              {/* Shipping Address  */}
                              <VStack width="50%" align={"start"}>
                                {clientDetails && (
                                  <Box>
                                    <HStack>
                                      <Badge
                                        colorScheme={
                                          clientDetails.creditLimit > 0
                                            ? clientDetails.amountOwing <
                                              clientDetails.creditLimit
                                              ? "green"
                                              : "red"
                                            : "gray"
                                        }
                                        fontSize={"0.7em"}
                                        letterSpacing={2}
                                        fontFamily={numberFont}
                                      >
                                        ${" "}
                                        {formatNumberWithDecimalPlaces(
                                          clientDetails?.amountOwing,
                                          2
                                        )}{" "}
                                        ~ ${" "}
                                        {formatNumberWithDecimalPlaces(
                                          clientDetails?.creditLimit,
                                          2
                                        )}
                                      </Badge>
                                    </HStack>
                                  </Box>
                                )}
                                <Box
                                  width="100%"
                                  marginTop={clientDetails === null ? 8 : 1}
                                >
                                  <Textarea
                                    defaultValue={
                                      clientDetails?.shippingAddresses !==
                                      undefined
                                        ? buildAddress(
                                            clientDetails.shippingAddresses
                                          )
                                        : ""
                                    }
                                    bgColor="white"
                                    width="100%"
                                    resize="none"
                                    height={20}
                                    isReadOnly={true}
                                    fontSize="0.70em"
                                    borderRadius={3}
                                    borderBottomWidth={2}
                                    borderBottomColor={inputConfig.borderColor}
                                  ></Textarea>
                                </Box>
                              </VStack>
                            </HStack>
                          </Box>
                        </HStack>
                      </Box>
                    </Box>
                  </VStack>
                </VStack>
              </Box>
              {/* Transaction Specific Details  */}
              <Box>
                <SimpleGrid columns={2} spacing={2}>
                  <Box width="100%">
                    <HStack width="100%">
                      <Box width="25%">
                        <_Label fontSize="0.7em">DATE:</_Label>
                      </Box>
                      <Box width="75%">
                        <DatePicker
                          readOnly={isReadOnly || isProcessed}
                          wrapperClassName="datepicker_style"
                          dateFormat={"MM/dd/yyyy"}
                          placeholderText="Txn. Date"
                          selected={new Date(txnDate)}
                          onChange={(date) => {
                            if (date !== null) {
                              setProperty("txnDate", date.toISOString());
                            }
                          }}
                          closeOnScroll={true}
                          maxDate={new Date()}
                        />
                      </Box>
                    </HStack>
                  </Box>
                  {type === TRANSACTION_TYPES["SR"] && (
                    <Box width="100%" marginLeft={10}>
                      <AsyncSelect
                        // key={getUUID()}
                        tabSelectsValue={true}
                        isDisabled={
                          clientDetails === null ||
                          __lockCounter !== 0 ||
                          isViewOrUpdate
                        }
                        isClearable={true}
                        placeholder="Search Invoice by ID."
                        styles={AsyncSelectStyle}
                        loadOptions={loadInvoicesByClientForSalesReturns}
                        defaultOptions={allInvoicesForSelectedClient}
                        {...defaultSelectedSalesInvoice}
                        // {...(type !== TRANSACTION_TYPES["SR"] && enableEditing === true ? {inputValue: selectedSalesInvoice?.toString()}: {})}
                        onChange={(event: any) => {
                          if (event && event.value) {
                            setProperty(
                              "selectedSalesInvoice",
                              parseInt(event.label)
                            );
                            setProperty(
                              "selectedSalesInvoiceLastModifiedTimestamp",
                              event.value.lastModifiedTimestamp
                            );

                            setProperty("po", event.value.po);
                            setProperty("unitNo", event.value.unitNo);
                            setProperty("vin", event.value.vin);
                            setProperty("details", event.value.details);

                            setProperty(
                              "earlyPaymentDiscount",
                              event.value.earlyPaymentDiscount
                            );
                            setProperty(
                              "earlyPaymentPaidWithinDays",
                              event.value.earlyPaymentPaidWithinDays
                            );
                            setProperty(
                              "netAmountDueWithinDays",
                              event.value.netAmountDueWithinDays
                            );
                            setProperty(
                              "disableFederalTaxes",
                              event.value.disableFederalTaxes
                            );
                            setProperty(
                              "disableProvincialTaxes",
                              event.value.disableProvincialTaxes
                            );
                          }
                        }}
                      />
                    </Box>
                  )}
                  {type === TRANSACTION_TYPES["SI"] && (
                    <Box width="100%">
                      <HStack width="100%">
                        <Box width="25%">
                          <_Label fontSize="0.7em">P.O:</_Label>
                        </Box>
                        <Box width="75%">
                          <_Input
                            defaultValue={po}
                            isReadOnly={isReadOnly || isProcessed}
                            borderBottomColor={inputConfig.borderColor}
                            borderBottomWidth={inputConfig.borderWidth}
                            borderRadius={inputConfig.borderRadius}
                            size={inputConfig.size}
                            fontSize={inputConfig.fontSize}
                            fontFamily={numberFont}
                            letterSpacing={inputConfig.letterSpacing}
                            width="100%"
                            onBlur={(e: any) => {
                              if (e && e.target && e.target.value)
                                setProperty("po", e.target.value.trim());
                            }}
                          ></_Input>
                        </Box>
                      </HStack>
                    </Box>
                  )}
                  {type === TRANSACTION_TYPES["SI"] && (
                    <Box width="100%">
                      <HStack width="100%">
                        <Box width="25%">
                          <_Label fontSize="0.7em">UNIT #:</_Label>
                        </Box>
                        <Box width="75%">
                          <_Input
                            defaultValue={unitNo}
                            isReadOnly={isReadOnly || isProcessed}
                            borderBottomColor={inputConfig.borderColor}
                            borderBottomWidth={inputConfig.borderWidth}
                            borderRadius={inputConfig.borderRadius}
                            size={inputConfig.size}
                            fontSize={inputConfig.fontSize}
                            fontFamily={numberFont}
                            letterSpacing={inputConfig.letterSpacing}
                            width="100%"
                            onBlur={(e: any) => {
                              if (e && e.target && e.target.value)
                                setProperty("unitNo", e.target.value.trim());
                              else setProperty("unitNo", "");
                            }}
                          ></_Input>
                        </Box>
                      </HStack>
                    </Box>
                  )}
                  {type === TRANSACTION_TYPES["SI"] && (
                    <Box width="100%">
                      <HStack width="100%">
                        <Box width="25%">
                          <_Label fontSize="0.7em">V.I.N #:</_Label>
                        </Box>
                        <Box width="75%">
                          <_Input
                            maxLength={17}
                            defaultValue={vin}
                            isReadOnly={isReadOnly || isProcessed}
                            borderBottomColor={inputConfig.borderColor}
                            borderBottomWidth={inputConfig.borderWidth}
                            borderRadius={inputConfig.borderRadius}
                            size={inputConfig.size}
                            fontSize={inputConfig.fontSize}
                            fontFamily={numberFont}
                            letterSpacing={inputConfig.letterSpacing}
                            width="100%"
                            textTransform={"uppercase"}
                            onBlur={(e: any) => {
                              if (e && e.target && e.target.value) {
                                setProperty("vin", e.target.value.trim());
                              } else setProperty("vin", "");
                            }}
                          ></_Input>
                        </Box>
                      </HStack>
                    </Box>
                  )}

                  {(type === TRANSACTION_TYPES["SI"] ||
                    type === TRANSACTION_TYPES["QT"]) &&
                    systemConfigMode !== MODE_WASH && (
                      <Box width="100%">
                        <HStack width="100%">
                          <_Label fontSize="0.7em">ACCOUNT #:</_Label>
                          <Box width="75%">
                            <_Input
                              fontFamily={numberFont}
                              defaultValue={accountNumber}
                              isReadOnly={isReadOnly || isProcessed}
                              borderBottomColor={inputConfig.borderColor}
                              borderBottomWidth={inputConfig.borderWidth}
                              borderRadius={inputConfig.borderRadius}
                              size={inputConfig.size}
                              fontSize={inputConfig.fontSize}
                              letterSpacing={inputConfig.letterSpacing}
                              width="100%"
                              onBlur={(e: any) => {
                                if (e && e.target && e.target.value) {
                                  setProperty(
                                    "accountNumber",
                                    e.target.value.trim()
                                  );
                                } else setProperty("accountNumber", "");
                              }}
                            ></_Input>
                          </Box>
                        </HStack>
                      </Box>
                    )}

                  {type === TRANSACTION_TYPES["SI"] &&
                    systemConfigMode !== MODE_WASH && (
                      <Box width="100%">
                        <HStack width="100%">
                          <_Label fontSize="0.7em">PURCHASED BY:</_Label>
                          <Box width="75%">
                            <_Input
                              defaultValue={purchasedBy}
                              isReadOnly={isReadOnly || isProcessed}
                              borderBottomColor={inputConfig.borderColor}
                              borderBottomWidth={inputConfig.borderWidth}
                              borderRadius={inputConfig.borderRadius}
                              size={inputConfig.size}
                              fontSize={inputConfig.fontSize}
                              letterSpacing={inputConfig.letterSpacing}
                              width="100%"
                              textTransform={"capitalize"}
                              onBlur={(e: any) => {
                                if (e && e.target && e.target.value) {
                                  setProperty(
                                    "purchasedBy",
                                    e.target.value.trim()
                                  );
                                } else setProperty("purchasedBy", "");
                              }}
                            ></_Input>
                          </Box>
                        </HStack>
                      </Box>
                    )}

                  {type === TRANSACTION_TYPES["SI"] &&
                    systemConfigMode === MODE_WASH && (
                      <>
                        <Box width="100%">
                          <HStack width="100%">
                            <Box width="25%">
                              <_Label fontSize="0.7em">DRIVER NAME:</_Label>
                            </Box>
                            <Box width="75%">
                              <_Input
                                defaultValue={driverName}
                                isReadOnly={isReadOnly || isProcessed}
                                borderBottomColor={inputConfig.borderColor}
                                borderBottomWidth={inputConfig.borderWidth}
                                borderRadius={inputConfig.borderRadius}
                                size={inputConfig.size}
                                fontSize={inputConfig.fontSize}
                                letterSpacing={inputConfig.letterSpacing}
                                width="100%"
                                onBlur={(e: any) => {
                                  if (e && e.target && e.target.value)
                                    setProperty(
                                      "driverName",
                                      e.target.value.trim()
                                    );
                                  else setProperty("driverName", "");
                                }}
                              ></_Input>
                            </Box>
                          </HStack>
                        </Box>
                        <Box width="100%">
                          <HStack width="100%">
                            <Box width="25%">
                              <_Label fontSize="0.7em">TRAILER #:</_Label>
                            </Box>
                            <Box width="75%">
                              <_Input
                                defaultValue={trailerNumber}
                                isReadOnly={isReadOnly || isProcessed}
                                borderBottomColor={inputConfig.borderColor}
                                borderBottomWidth={inputConfig.borderWidth}
                                borderRadius={inputConfig.borderRadius}
                                size={inputConfig.size}
                                fontSize={inputConfig.fontSize}
                                fontFamily={numberFont}
                                letterSpacing={inputConfig.letterSpacing}
                                width="100%"
                                onBlur={(e: any) => {
                                  if (e && e.target && e.target.value)
                                    setProperty(
                                      "trailerNumber",
                                      e.target.value.trim()
                                    );
                                  else setProperty("trailerNumber", "");
                                }}
                              ></_Input>
                            </Box>
                          </HStack>
                        </Box>
                        <Box width="100%">
                          <HStack width="100%">
                            <Box width="25%">
                              <_Label fontSize="0.7em">ODOMETER R/D:</_Label>
                            </Box>
                            <Box width="75%">
                              <_Input
                                defaultValue={odometerReading}
                                isReadOnly={isReadOnly || isProcessed}
                                borderBottomColor={inputConfig.borderColor}
                                borderBottomWidth={inputConfig.borderWidth}
                                borderRadius={inputConfig.borderRadius}
                                size={inputConfig.size}
                                fontSize={inputConfig.fontSize}
                                fontFamily={numberFont}
                                letterSpacing={inputConfig.letterSpacing}
                                width="100%"
                                onBlur={(e: any) => {
                                  if (e && e.target && e.target.value)
                                    setProperty(
                                      "odometerReading",
                                      e.target.value.trim()
                                    );
                                  else setProperty("odometerReading", "");
                                }}
                              ></_Input>
                            </Box>
                          </HStack>
                        </Box>
                      </>
                    )}
                </SimpleGrid>
              </Box>
            </HStack>
          </CardBody>
        </Card>
      </Box>
    </>
  );
};

/**
 * Footer Props
 */
interface FooterProps {
  enableEditing: boolean;
  hidePrivateDetails: boolean;
}

/**
 * Footer Details
 * @returns Footer
 */
const FooterDetails = ({ enableEditing, hidePrivateDetails }: FooterProps) => {
  // Toast Instance
  const toast = useToast();

  const {
    id,
    transactionType,
    clientDetails,
    subTotal,
    txnDiscount,
    gstHSTTax,
    pstTax,
    sumTotal,
    cogs,
    notes,
    earlyPaymentDiscount,
    earlyPaymentPaidWithinDays,
    netAmountDueWithinDays,
    __lockCounter,
    salesRepId,
    setProperty,
    process,
    sendEmail,
  } = transactionStore(
    (state) => ({
      id: state.id,
      transactionType: state.transactionType,
      clientDetails: state.clientDetails,
      subTotal: state.subTotal,
      txnDiscount: state.txnDiscount,
      gstHSTTax: state.gstHSTTax,
      pstTax: state.pstTax,
      sumTotal: state.sumTotal,
      cogs: state.cogs,
      notes: state.notes,
      earlyPaymentDiscount: state.earlyPaymentDiscount,
      earlyPaymentPaidWithinDays: state.earlyPaymentPaidWithinDays,
      netAmountDueWithinDays: state.netAmountDueWithinDays,
      __lockCounter: state.__lockCounter,
      salesRepId: state.salesRepId,
      setProperty: state.setProperty,
      process: state.process,
      sendEmail: state.sendEmail,
    }),
    shallow
  );

  // Hooks
  const [disableProcessButton, setDisableProcessButton] =
    useState<boolean>(false);
  const [isProcessing, setIsProcessing] = useState<boolean>(false);

  // Gradient Hook
  const [gradient, setGradient] = useState<AttributeType>(resetGradient);

  /* Processed State */
  const { isProcessed, changeProcessedState } = txnStateStore((state) => ({
    isProcessed: state.isProcessed,
    changeProcessedState: state.changeProcessedState,
  }));

  let profitMarginThisTransaction = 0;
  let cogsMarginThisTransaction = 0;
  if (cogs > 0) {
    profitMarginThisTransaction = calculateProfitMargin(subTotal, cogs);
    cogsMarginThisTransaction = calculateCOGSMargin(subTotal, cogs);
  }

  const [disableEmailButton, setDisableEmailButton] = useState<boolean>(false);

  // Send Email
  const sendEmailHandler = () => {
    let isNotSuccessful = true;
    setDisableEmailButton(true);
    sendEmail()
      .then((res: any) => {
        let result: APIResponse = res.data;
        if (result.status === true) {
          showToast(toast, true, "Email Send Successfully.");
          isNotSuccessful = false;
        } else {
          showToast(toast, false, result.message || "Unable to Send Email.");
        }
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      })
      .finally(() => {
        if (isNotSuccessful) setDisableEmailButton(false);
      });
  };

  return (
    <Card borderRadius={3} {...gradient}>
      <CardBody padding={1}>
        <HStack alignItems="start" spacing={5}>
          {/* Early Payment Terms and Message  */}
          <VStack alignItems={"left"} width="30vw">
            <HStack>
              <_Label fontSize="0.7em" letterSpacing={1}>
                EARLY PAYMENT TERMS:
              </_Label>
              <CiDiscount1 color="#2AAA8A"></CiDiscount1>
              <Box paddingLeft={2} textAlign="left">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={1}
                  color="#097969"
                >
                  {formatNumberWithDecimalPlaces(earlyPaymentDiscount)}
                  %,
                </_Label>
              </Box>
              <_Label fontSize="0.7em" letterSpacing={2}>
                WITHIN
              </_Label>
              <Box textAlign="left">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={1}
                  color="#DE3163"
                >
                  {earlyPaymentPaidWithinDays}
                </_Label>
              </Box>
              <_Label fontSize="0.7em" letterSpacing={2}>
                DAYS, NET
              </_Label>
              <Box textAlign="left">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={1}
                  color="#AA4A44"
                >
                  <b>{netAmountDueWithinDays}</b>
                </_Label>
              </Box>
              <_Label fontSize="0.7em" letterSpacing={2}>
                DAYS.
              </_Label>
            </HStack>
            <HStack alignItems={"start"}>
              <HStack>
                <_Label fontSize="0.7em" letterSpacing={2}>
                  MESSAGE:
                </_Label>
                <BiMessageDetail color="blue" />
              </HStack>
              <Textarea
                borderBottomColor={inputConfig.borderColor}
                resize="none"
                fontSize="0.7em"
                padding={2}
                value={
                  (clientDetails?.additionalInformation || "") +
                  "\n" +
                  (clientDetails?.memo || "")
                }
                readOnly={true}
                borderRadius={2}
              ></Textarea>
            </HStack>
          </VStack>

          {/* Notes */}
          <HStack alignItems={"start"} width="25vw">
            <HStack>
              <_Label fontSize="0.7em" letterSpacing={1}>
                NOTES:
              </_Label>
              <FcComments />
            </HStack>
            <Textarea
              defaultValue={notes}
              isReadOnly={
                isProcessed ||
                enableEditing === false ||
                (__lockCounter === 0 ? false : true)
              }
              padding={2}
              resize="none"
              borderRadius={2}
              height="12vh"
              fontSize="0.8em"
              borderBottomColor={inputConfig.borderColor}
              onBlur={(event: any) => {
                if (event && event.target && event.target.value) {
                  setProperty("notes", event.target.value.trim());
                } else setProperty("notes", "");
              }}
            ></Textarea>
          </HStack>

          {/* Amounts */}
          <Box width="16vw" marginLeft={2}>
            <HStack alignItems={"start"}>
              <Box width="40%">
                <_Label fontSize="0.7em" letterSpacing={2}>
                  DISCOUNT:
                </_Label>
              </Box>
              <Box width="10%">
                <CiDiscount1 color="#2AAA8A"></CiDiscount1>
              </Box>
              <Box width="50%" textAlign="right">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={2}
                >
                  {formatNumberWithDecimalPlaces(txnDiscount, 2)}
                </_Label>
              </Box>
            </HStack>
            <HStack alignItems={"end"} marginTop={1}>
              <Box width="40%">
                <HStack>
                  <_Label fontSize="0.7em" letterSpacing={2}>
                    SUBTOTAL:
                  </_Label>
                </HStack>
              </Box>
              <Box width="10%">
                <IoIosPricetags color="#0096FF"></IoIosPricetags>
              </Box>
              <Box width="50%" textAlign="right">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={2}
                >
                  {formatNumberWithDecimalPlaces(subTotal, 2)}
                </_Label>
              </Box>
            </HStack>
            <HStack alignItems={"end"} marginTop={1}>
              <Box width="41.2%">
                {/* GST/HST: */}
                <_Label fontSize="0.7em" letterSpacing={2}>
                  GST/HST TAX:
                </_Label>
              </Box>
              <Box width="10%">
                <TbReceiptTax color="#E97451"></TbReceiptTax>
              </Box>
              <Box width="50%" textAlign="right">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={2}
                >
                  {formatNumberWithDecimalPlaces(gstHSTTax, 2)}
                </_Label>
              </Box>
            </HStack>
            <HStack alignItems={"end"} marginTop={1}>
              <Box width="41.7%">
                <_Label fontSize="0.7em" letterSpacing={2}>
                  PST TAX:
                </_Label>
              </Box>
              <Box width="10%">
                <TbReceiptTax color="#36454F"></TbReceiptTax>
              </Box>
              {/* PST Tax */}
              <Box width="50%" textAlign="right">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.7em"
                  letterSpacing={2}
                >
                  {formatNumberWithDecimalPlaces(pstTax, 2)}
                </_Label>
              </Box>
            </HStack>
            <Divider borderColor="black" marginTop={1}></Divider>
            <HStack alignItems={"end"} marginTop={1}>
              <Box width="41.7%">
                <_Label fontSize="0.65em" letterSpacing={2}>
                  <b>TOTAL:</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </_Label>
              </Box>
              <Box width="10%">
                <CurrencyIcon></CurrencyIcon>
              </Box>
              <Box width="50%" textAlign="right">
                <_Label
                  fontFamily={numberFont}
                  fontSize="0.65em"
                  letterSpacing={2}
                  fontWeight="bold"
                >
                  {formatNumberWithDecimalPlaces(sumTotal, 2)}
                </_Label>
              </Box>
            </HStack>
            {(transactionType === TRANSACTION_TYPES["SI"] ||
              transactionType === TRANSACTION_TYPES["QT"]) && (
              <VStack align={"start"}>
                <_Divider margin={0}></_Divider>
                <HStack width={"100%"}>
                  <Badge
                    bg={getProfitMarginColorScheme(profitMarginThisTransaction)}
                    letterSpacing={2}
                  >
                    PROFIT MARGIN
                  </Badge>
                  <Box width="100%" textAlign={"right"}>
                    <_Label
                      fontFamily={numberFont}
                      fontSize={"0.7em"}
                      fontWeight="bold"
                      hide={hidePrivateDetails}
                      toggleVisibility={true}
                      letterSpacing={2}
                    >
                      {formatNumberWithDecimalPlaces(
                        profitMarginThisTransaction,
                        2
                      )}{" "}
                      %
                    </_Label>
                  </Box>
                </HStack>
                <HStack width={"100%"}>
                  <Badge bg={"#EADDCA"} letterSpacing={2}>
                    C.O.G.S
                  </Badge>
                  <Box width="100%" textAlign={"right"}>
                    <_Label
                      fontFamily={numberFont}
                      fontSize={"0.7em"}
                      fontWeight="bold"
                      hide={hidePrivateDetails}
                      toggleVisibility={true}
                      letterSpacing={2}
                    >
                      {formatNumberWithDecimalPlaces(cogs, 2)} (
                      {formatNumberWithDecimalPlaces(
                        cogsMarginThisTransaction,
                        2
                      )}
                      %)
                    </_Label>
                  </Box>
                </HStack>
              </VStack>
            )}
          </Box>

          {/* Button */}
          <Box width="20%" marginLeft={2}>
            <SimpleGrid columns={1} spacing={2}>
              <HStack>
                <_Button
                  visibility={__lockCounter === 0 ? "" : "hidden"}
                  isDisabled={
                    enableEditing === false ||
                    sumTotal <= 0 ||
                    disableProcessButton ||
                    clientDetails == null ||
                    __lockCounter !== 0
                  }
                  variant="outline"
                  icon={
                    __lockCounter === 0 ? (
                      <MdDoneAll color="#50C878"></MdDoneAll>
                    ) : (
                      <BsLock color="#880808"></BsLock>
                    )
                  }
                  size="xs"
                  color="white"
                  onClick={() => {
                    if (salesRepId === 0) {
                      alert("Please Select the Sales Representive.");
                      return;
                    }
                    if (
                      id !== null &&
                      confirm("Do you want to update?") === false
                    )
                      return;
                    setIsProcessing(true);
                    setDisableProcessButton(true);
                    process()
                      .then((res: any) => {
                        let response: APIResponse<number> = res.data;
                        if (response.status === true) {
                          if (id === null) setProperty("id", response.data);

                          /* Set Process state to true */
                          changeProcessedState(true);

                          // Set Gradient to success
                          setGradient(successGradient);
                        } else {
                          setDisableProcessButton(false);

                          // Set Gradient to error
                          setGradient(errorGradient);
                        }

                        // Show Toast only on failure
                        if (response.status !== true)
                          showToast(
                            toast,
                            response.status,
                            response.message || UNKNOWN_SERVER_ERROR_MSG
                          );
                      })
                      .catch((error: any) => {
                        setDisableProcessButton(false);
                        showToast(toast, false, error.message);

                        // Set Gradient to error
                        setGradient(errorGradient);
                      })
                      .finally(function () {
                        // Change Processing status
                        setIsProcessing(false);
                      });
                  }}
                  label={__lockCounter === 0 ? "Process" : "Locked"}
                  width="50%"
                  height="6vh"
                  bgColor={__lockCounter === 0 ? "black" : "#ECCACA"}
                  borderColor="gray.200"
                  borderWidth={1}
                  isLoading={isProcessing}
                  loadingText="Processing"
                  fontSize={"1.25em"}
                  _loading={{
                    fontFamily: "Raleway",
                    fontSize: "0.6em",
                    textTransform: "uppercase",
                    letterSpacing: 5,
                    color: "#E97451",
                  }}
                  spinner={<Spinner size="xs" />}
                ></_Button>
                <_Button
                  isDisabled={
                    disableEmailButton || clientDetails === null || id === null
                  }
                  variant="outline"
                  icon={<MdAlternateEmail color="#0096FF"></MdAlternateEmail>}
                  size="xs"
                  color="white"
                  onClick={sendEmailHandler}
                  label="Email"
                  width="50%"
                  height="6vh"
                  bgColor={navBgColor}
                  borderColor="gray.200"
                  borderWidth={1}
                  fontSize={"1.25em"}
                ></_Button>
              </HStack>
              <HStack>
                <_Button
                  isDisabled={clientDetails === null || id === null}
                  variant="outline"
                  icon={<AiFillPrinter color="#5D3FD3"></AiFillPrinter>}
                  size="xs"
                  color="white"
                  onClick={() => {
                    window.open(
                      `${APP_HOST}/api.php?op=print&t=${transactionType}&i=${id}`
                    );
                    window.location.reload();
                  }}
                  label="Print"
                  width="50%"
                  height="6vh"
                  bgColor={navBgColor}
                  borderColor="gray.200"
                  borderWidth={1}
                  fontSize={"1.25em"}
                ></_Button>
                {transactionType === TRANSACTION_TYPES["SI"] && (
                  <_Button
                    isDisabled={clientDetails == null || id === null}
                    variant="outline"
                    icon={<AiOutlinePrinter color="#5D3FD3"></AiOutlinePrinter>}
                    size="xs"
                    color="white"
                    onClick={() => {
                      window.open(
                        `${APP_HOST}/api.php?op=packaging_slip&i=${id}`
                      );
                    }}
                    label="Packaging Slip"
                    width={"15vw"}
                    height="6vh"
                    bgColor={navBgColor}
                    borderColor="gray.200"
                    borderWidth={1}
                    fontSize={"1.25em"}
                  ></_Button>
                )}
              </HStack>
            </SimpleGrid>
          </Box>
        </HStack>
      </CardBody>
    </Card>
  );
};
/**
 * TransactionProps
 */
interface TransactionsProps {
  type: number;
  name: string;
  isViewOrUpdate?: boolean;
}

/**
 * Transactions
 * @returns
 */
const Transactions = ({
  type,
  name,
  isViewOrUpdate = false,
}: TransactionsProps) => {
  checkForValidSession();

  // Hooks
  const [enableEditing, setEnableEditing] = useState(!isViewOrUpdate);
  const enableEditingHandler = () => {
    setEnableEditing(!enableEditing);
  };
  const [isClientChangedSuccessfully, setIsClientChangedSuccessfully] =
    useState<boolean>(true);

  // Toast
  const toastHandle = useToast();

  const {
    id,
    transaction_type,
    fetchTransaction,
    setTransactionDetails,
    setProperty,
  } = transactionStore(
    (state) => ({
      id: state.id,
      transaction_type: state.transactionType,
      addRow: state.addRow,
      fetchTransaction: state.fetchTransaction,
      setTransactionDetails: state.setTransactionDetails,
      setProperty: state.setProperty,
    }),
    shallow
  );

  // Set Transaction Type
  if (transaction_type === null && isViewOrUpdate === false)
    setProperty("transactionType", type);

  // Transaction ID
  let transactionId: number | string | null = null;

  // Private Field Visibility Status
  const [hidePrivateDetails, setPrivateDetailsVisibility] =
    useState<boolean>(true);

  // Fetch Transactions Value
  if (isViewOrUpdate) {
    const [searchParams] = useSearchParams();
    transactionId = searchParams.get("id");

    if (id === null) {
      fetchTransaction(type, transactionId)
        .then((_response: any) => {
          let response: APIResponse<TransactionStoreFields> = _response.data;
          if (response.status === true && response.data) {
            setTransactionDetails(response.data);
          } else
            showToast(
              toastHandle,
              false,
              response.message || UNKNOWN_SERVER_ERROR_MSG
            );
        })
        .catch((error: any) => {
          showToast(toastHandle, false, error.message);
        });
    }
  }

  // Load
  if (isViewOrUpdate && id === null) {
    return (
      <Box marginTop="20%">
        <Center>
          <VStack>
            <Spinner
              speed="1s"
              thickness="4px"
              color="#5D3FD3"
              size="xl"
              emptyColor="#CCCCFF"
            />
            <_Label fontFamily="JetBrains Mono">
              Loading transaction details...
            </_Label>
          </VStack>
        </Center>
      </Box>
    );
  }

  return (
    <>
      <Box height="100%" padding={1}>
        <VStack>
          <Box width="100%">
            <Header
              id={id !== null ? id : -1}
              type={type}
              isViewOrUpdate={isViewOrUpdate}
              enableEditing={enableEditing}
              enableEditingHandler={enableEditingHandler}
              hidePrivateDetails={hidePrivateDetails}
              setPrivateDetailsVisibility={setPrivateDetailsVisibility}
            ></Header>
          </Box>
          <_Divider margin={1}></_Divider>
          <Box width="100%">
            <TransactionHeaderDetails
              enableEditing={enableEditing}
              type={type}
              name={name}
              isViewOrUpdate={isViewOrUpdate}
              setIsClientChangedSuccessfully={setIsClientChangedSuccessfully}
            />
          </Box>
          {isClientChangedSuccessfully === false && (
            <Box marginTop={5}>
              <Spinner
                speed="1s"
                thickness="4px"
                color="#5D3FD3"
                size="xl"
                emptyColor="#CCCCFF"
              />
            </Box>
          )}
          {isClientChangedSuccessfully && (
            <Box width="100%">
              <Box>
                <ItemsTable
                  enableEditing={enableEditing}
                  setIsClientChangedSuccessfully={
                    setIsClientChangedSuccessfully
                  }
                  type={type}
                  hidePrivateDetails={hidePrivateDetails}
                ></ItemsTable>
              </Box>
              <Box>
                <FooterDetails
                  enableEditing={enableEditing}
                  hidePrivateDetails={hidePrivateDetails}
                ></FooterDetails>
              </Box>
            </Box>
          )}
        </VStack>
      </Box>
    </>
  );
};

export default Transactions;
