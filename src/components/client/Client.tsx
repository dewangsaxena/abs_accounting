import { memo, useEffect, useState } from "react";
import {
  Address,
  ClientDetails,
  ItemDetailsForClient,
  clientStore,
} from "./store";
import {
  Badge,
  Box,
  Card,
  CardBody,
  Checkbox,
  CreateToastFnReturn,
  HStack,
  Radio,
  RadioGroup,
  SimpleGrid,
  Image,
  Text,
  Stack,
  Tab,
  TabList,
  TabPanel,
  TabPanels,
  Tabs,
  Textarea,
  Tooltip,
  VStack,
  useToast,
} from "@chakra-ui/react";
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
  inputConfig,
  iconColor,
  numberFont,
  AsyncSelectStyle,
  AutoSuggestStyle,
} from "../../shared/style";
import { FaAddressCard } from "react-icons/fa";
import { IoIosOptions, IoMdInformation } from "react-icons/io";
import { HiOutlineInformationCircle } from "react-icons/hi";
import { MdOutlineAlternateEmail } from "react-icons/md";
import { CiDiscount1 } from "react-icons/ci";
import { LiaUserPlusSolid, LiaUserEditSolid } from "react-icons/lia";
import AsyncSelect from "react-select/async";
import { APIResponse } from "../../service/api-client";
import {
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  AttributeType,
  DEFAULT_PROFIT_MARGIN_KEY,
  UNKNOWN_SERVER_ERROR_MSG,
  clientCategory,
  paymentMethods,
  receiptPaymentMethods,
} from "../../shared/config";
import { GiTakeMyMoney } from "react-icons/gi";
import {
  buildSearchListForClient,
  buildSearchListForItem,
  redirectIfInvalidSession,
  formatNumberWithDecimalPlaces,
  getUUID,
  showToast,
  isSessionActive,
  toFixed,
} from "../../shared/functions";
import { SingleDatepicker } from "chakra-dayzed-datepicker";
import { ItemDetails, itemStore } from "../inventory/itemStore";
import { FcFullTrash } from "react-icons/fc";
import { LuHistory } from "react-icons/lu";
import { IoIosAddCircle } from "react-icons/io";
import { CiText } from "react-icons/ci";
import AutoSuggest from "react-autosuggest";
import { shallow } from "zustand/shallow";
import flag_CA from "/images/flag_CA.svg";
import flag_US from "/images/flag_US.svg";

// Countries Supported
const COUNTRIES_SUPPORTED = { 124: "Canada" };

// Item Details
interface __ItemDetailsDetails extends ItemDetailsForClient {
  _key: string;
  onBlur: any;
  deleteItem: any;
}

const __ItemDetails = memo(
  ({
    _key,
    identifier,
    description,
    buyingCost,
    sellingPrice,
    onBlur,
    deleteItem,
  }: __ItemDetailsDetails) => {
    return (
      <Card key={_key} bgColor="white" shadow={"none"} width="100%">
        <CardBody padding={0}>
          <HStack>
            <Badge variant="subtle" colorScheme="green">
              {identifier}
            </Badge>
            <Badge variant="outline" colorScheme="purple">
              {description}
            </Badge>
            <Badge
              variant="outline"
              colorScheme="orange"
              fontFamily={numberFont}
            >
              Buying Cost:&nbsp;&nbsp;
              {formatNumberWithDecimalPlaces(buyingCost)}
            </Badge>
            <_Label fontSize="0.8em">Selling Price:</_Label>
            <Box width="20%">
              <_Input
                fontSize={"0.8em"}
                letterSpacing={2}
                fontFamily={numberFont}
                defaultValue={sellingPrice}
                type="number"
                onBlur={onBlur}
                onClick={(event: any) => {
                  event.target.select();
                }}
              ></_Input>
            </Box>
            <Box>
              <_Button
                label="Delete"
                icon={<FcFullTrash />}
                onClick={deleteItem}
                color="red"
                fontSize="1.2em"
              ></_Button>
            </Box>
          </HStack>
        </CardBody>
      </Card>
    );
  }
);

/**
 * Special Pricing for Item.
 */
const SpecialPricingForItem = memo(() => {
  // Toast Handle.
  const toast = useToast();

  // Client Detail Store
  const { id, customSellingPriceForItems, setField } = clientStore();

  // Item Store
  let { fetch: fetchItem } = itemStore();

  // Select Load options
  const loadItemOptions = (
    searchTerm: string,
    callback: (args: any) => void
  ) => {
    fetchItem(searchTerm, false)
      .then((res: any) => {
        let response: APIResponse<ItemDetails[]> = res.data;
        if (response.status === true)
          callback(buildSearchListForItem(response.data));
        else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((_: any) => {});
  };

  // Store Id
  const storeId = parseInt(localStorage.getItem("storeId") || "-1");

  // Custom Selling Price for Item in this store.
  const [customSellingPriceForItemsThisStore, setCustomSellingPriceForItems] =
    useState<{
      [itemId: number]: ItemDetailsForClient;
    }>({});

  // Set Custom Selling Price for Items
  useEffect(() => {
    if (id) {
      if (storeId in customSellingPriceForItems) {
        setCustomSellingPriceForItems(customSellingPriceForItems[storeId]);
      }
    }
  }, [id]);

  // Rerender
  const [rerender, setRerender] = useState<number>(0);

  // Selected Item
  const [selectedItem, setSelectedItem] = useState<ItemDetails | null>(null);

  // Add Custom Pricing For Item
  const addCustomPricingForItem = () => {
    if (
      selectedItem &&
      selectedItem.id &&
      storeId in customSellingPriceForItems
    ) {
      if (selectedItem.id in customSellingPriceForItemsThisStore === false) {
        customSellingPriceForItemsThisStore[selectedItem.id] = {
          identifier: selectedItem.identifier,
          description: selectedItem.description,
          buyingCost: selectedItem.prices[storeId].buyingCost,
          preferredPrice: selectedItem.prices[storeId].preferredPrice,
          sellingPrice: selectedItem.prices[storeId].sellingPrice,
          storeId: storeId,
        };
        let __customSellingPriceForItems = customSellingPriceForItems;
        __customSellingPriceForItems[storeId] =
          customSellingPriceForItemsThisStore;
        setField("customSellingPriceForItems", __customSellingPriceForItems);
        setRerender(rerender + 1);
      }
    }
  };

  const deleteItem = (itemId: number) => {
    let itemIdentifier = customSellingPriceForItemsThisStore[itemId].identifier;
    delete customSellingPriceForItemsThisStore[itemId];
    let __customSellingPriceForItems = customSellingPriceForItems;
    __customSellingPriceForItems[storeId] = customSellingPriceForItemsThisStore;
    setField("customSellingPriceForItems", __customSellingPriceForItems);
    setRerender(rerender + 1);
    showToast(toast, true, itemIdentifier + " deleted.");
  };

  return (
    <>
      <_Label fontSize="0.8em" letterSpacing={2}>
        Add Custom Price for Items.
      </_Label>
      <_Divider borderColor="gray" margin={2}></_Divider>
      <Box width="100%">
        <HStack>
          <Box width="50%">
            <AsyncSelect
              tabSelectsValue={true}
              isClearable={true}
              styles={AsyncSelectStyle}
              cacheOptions={false}
              loadOptions={loadItemOptions}
              defaultOptions={false}
              onChange={(event: any) => {
                if (event) {
                  setSelectedItem(event.value);
                } else setSelectedItem(null);
              }}
            />
          </Box>
          <Box width="20%">
            <_Button
              fontSize="1.2em"
              color="green"
              label="Add Item"
              onClick={addCustomPricingForItem}
            ></_Button>
          </Box>
        </HStack>
      </Box>
      <VStack spacing={4} marginTop={5}>
        {Object.keys(customSellingPriceForItemsThisStore).map((itemId) => {
          return (
            <__ItemDetails
              key={itemId}
              _key={getUUID()}
              identifier={
                customSellingPriceForItemsThisStore[parseInt(itemId)].identifier
              }
              description={
                customSellingPriceForItemsThisStore[parseInt(itemId)]
                  .description
              }
              buyingCost={
                customSellingPriceForItemsThisStore[parseInt(itemId)].buyingCost
              }
              preferredPrice={
                customSellingPriceForItemsThisStore[parseInt(itemId)]
                  .preferredPrice
              }
              sellingPrice={
                customSellingPriceForItemsThisStore[parseInt(itemId)]
                  .sellingPrice
              }
              storeId={storeId}
              onBlur={(event: any) => {
                if (event && event.target) {
                  let sellingPrice: number = parseFloat(event.target.value);
                  customSellingPriceForItemsThisStore[
                    parseInt(itemId)
                  ].sellingPrice = sellingPrice;
                } else
                  customSellingPriceForItemsThisStore[
                    parseInt(itemId)
                  ].sellingPrice = 0;
              }}
              deleteItem={() => {
                deleteItem(parseInt(itemId));
              }}
            />
          );
        })}
      </VStack>
    </>
  );
});

interface SharedClientProps {
  toast?: CreateToastFnReturn;
  inputDisable?: boolean;
  isViewOrUpdate?: boolean;
  clickHandler?: any;
  loadingState?: boolean;
}

const ClientPrimaryDetails = memo(({ inputDisable }: SharedClientProps) => {
  // Client Detail Store
  const {
    id,
    primaryDetails,
    isDefaultShippingAddress,
    clientSince,
    setField,
  } = clientStore(
    (state) => ({
      id: state.id,
      isInactive: state.isInactive,
      primaryDetails: state.primaryDetails,
      shippingAddresses: state.shippingAddresses,
      isDefaultShippingAddress: state.isDefaultShippingAddress,
      clientSince: state.clientSince,
      setField: state.setField,
    }),
    shallow
  );

  return (
    <SimpleGrid columns={2} spacing={5}>
      <VStack>
        <_Input
          _key={`name.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.name}
          borderBottomColor={"red"}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Client Name"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryClientName", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`cname.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.contactName}
          borderBottomColor={"red"}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Contact Name"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryContactName", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`street1.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.street1}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Street Address Line 1"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryStreet1", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`street2.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.street2}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Street Address Line 2"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryStreet2", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`city.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.city}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="City"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryCity", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`postal_code.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.postalCode}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Postal Code"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryPostalCode", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`province.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.province}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="State/Province"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryProvince", event.target.value.trim());
            }
          }}
        ></_Input>
      </VStack>
      <VStack alignItems={"left"}>
        <_Input
          _key={`phone_number_1.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.phoneNumber1}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Phone Number 1"
          type="text"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryPhone1", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`phone_number_2.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.phoneNumber2}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Phone Number 2"
          type="text"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryPhone2", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`fax.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.fax}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Fax"
          type="number"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryFax", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`email_id.${id}`}
          isDisabled={inputDisable}
          defaultValue={primaryDetails?.emailId}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Email ID"
          onBlur={(event: any) => {
            if (event) {
              setField("primaryEmailId", event.target.value.trim());
            }
          }}
        ></_Input>
        <SingleDatepicker
          key={`client_since.${id}`}
          propsConfigs={{
            inputProps: {
              size: "sm",
              fontSize: inputConfig.fontSize,
              fontWeight: "normal",
              borderRadius: inputConfig.borderRadius,
              borderBottomWidth: inputConfig.borderColor,
              borderBottomColor: inputConfig.borderColor,
              height: "24px",
              variant: "outline",
              fontFamily: "JetBrains Mono",
            },
          }}
          disabled={inputDisable}
          maxDate={new Date()}
          date={clientSince}
          onDateChange={(date) => setField("clientSince", date)}
        ></SingleDatepicker>
        <_Select
          key={`primary_country.${id}`}
          isDisabled={inputDisable}
          onChange={(event: any) => {
            setField("primaryCountry", event.target.value);
          }}
          options={COUNTRIES_SUPPORTED}
          width={"100%"}
        ></_Select>
        <Checkbox
          key={`is_default_shipping_address.${id}`}
          isDisabled={inputDisable}
          isChecked={isDefaultShippingAddress ? true : false}
          colorScheme="green"
          onChange={() => {
            setField("isDefaultShippingAddress", isDefaultShippingAddress ^ 1);
          }}
        >
          <_Label fontSize="0.8em">Is Default Address?</_Label>
        </Checkbox>
      </VStack>
    </SimpleGrid>
  );
});

const ShippingAddressTab = memo(({ inputDisable }: SharedClientProps) => {
  // Client Detail Store
  const { id, shippingAddresses, isDefaultShippingAddress, setField } =
    clientStore(
      (state) => ({
        id: state.id,
        shippingAddresses: state.shippingAddresses,
        isDefaultShippingAddress: state.isDefaultShippingAddress,
        setField: state.setField,
      }),
      shallow
    );

  return (
    <SimpleGrid columns={2} spacing={5}>
      <VStack>
        <_Input
          _key={`sa_name.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.name}
          borderBottomColor={"red"}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Client Name"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingClientName", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_cname.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.contactName}
          borderBottomColor={"red"}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Contact Name"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingContactName", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_street_1.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.street1}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Street Address Line 1"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingStreet1", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_street_2.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.street2}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Street Address Line 2"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingStreet2", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_city.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.city}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="City"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingCity", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_postal_code.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.postalCode}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Postal Code"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingPostalCode", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_province.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.province}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="State/Province"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingProvince", event.target.value.trim());
            }
          }}
        ></_Input>
      </VStack>
      <VStack alignItems={"left"}>
        <_Input
          _key={`sa_phone_number_1.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.phoneNumber1}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Phone Number 1"
          type="number"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingPhone1", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          _key={`sa_phone_number_1.${id}`}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.phoneNumber2}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Phone Number 2"
          type="number"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingPhone2", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          key={getUUID()}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.fax}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Fax"
          type="number"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingFax", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Input
          key={getUUID()}
          isDisabled={inputDisable}
          isReadOnly={isDefaultShippingAddress ? true : false}
          defaultValue={shippingAddresses?.emailId}
          borderBottomColor={inputConfig.borderColor}
          borderBottomWidth={inputConfig.borderWidth}
          borderRadius={inputConfig.borderRadius}
          size={inputConfig.size}
          fontSize={inputConfig.fontSize}
          letterSpacing={inputConfig.letterSpacing}
          placeholder="Email ID"
          onBlur={(event: any) => {
            if (event) {
              setField("shippingEmailId", event.target.value.trim());
            }
          }}
        ></_Input>
        <_Select
          key={getUUID()}
          isDisabled={inputDisable || (isDefaultShippingAddress ? true : false)}
          onSelect={(event: any) => {
            setField("shippingCountry", event.target.value);
          }}
          options={COUNTRIES_SUPPORTED}
          width={"100%"}
        ></_Select>
      </VStack>
    </SimpleGrid>
  );
});

const ClientOptions = memo(({ inputDisable }: SharedClientProps) => {
  // Client Detail Store
  const {
    id,
    standardDiscount,
    standardProfitMargins,
    creditLimit,
    earlyPaymentDiscount,
    earlyPaymentPaidWithinDays,
    netAmountDueWithinDays,
    defaultPaymentMethod,
    defaultReceiptPaymentMethod,
    produceStatementForClient,
    disableFederalTaxes,
    disableProvincialTaxes,
    additionalEmailAddresses,
    disableCreditTransactions,
    paymentCurrency,
    exchangeRateUSDToCAD,
    setField,
  } = clientStore(
    (state) => ({
      id: state.id,
      standardDiscount: state.standardDiscount,
      standardProfitMargins: state.standardProfitMargins,
      creditLimit: state.creditLimit,
      earlyPaymentDiscount: state.earlyPaymentDiscount,
      earlyPaymentPaidWithinDays: state.earlyPaymentPaidWithinDays,
      netAmountDueWithinDays: state.netAmountDueWithinDays,
      defaultPaymentMethod: state.defaultPaymentMethod,
      defaultReceiptPaymentMethod: state.defaultReceiptPaymentMethod,
      produceStatementForClient: state.produceStatementForClient,
      disableFederalTaxes: state.disableFederalTaxes,
      disableProvincialTaxes: state.disableProvincialTaxes,
      additionalEmailAddresses: state.additionalEmailAddresses,
      disableCreditTransactions: state.disableCreditTransactions,
      paymentCurrency: state.paymentCurrency,
      exchangeRateUSDToCAD: state.exchangeRateCADToUSD,
      setField: state.setField,
    }),
    shallow
  );

  // Item keys
  let standardProfitMarginSubstringKeys = Object.keys(standardProfitMargins);

  // New Item Prefix
  const [newItemPrefix, setNewItemPrefix] = useState<string | null>(null);

  // Remove Forgiven from Payment Method
  const __receiptPaymentMethods: AttributeType = receiptPaymentMethods;
  delete __receiptPaymentMethods[10];

  return (
    <>
      <Badge
        fontSize={"0.8em"}
        letterSpacing={5}
        colorScheme="blue"
        variant="outline"
      >
        Discounts and Credit
      </Badge>
      <VStack alignItems={"left"} marginTop={5}>
        {/* Standard Discount, Credit Limit, Produce Forms */}
        <Stack
          direction={{ sm: "column", md: "row", lg: "row" }}
          spacing={{ sm: 5, md: 5, lg: 10 }}
        >
          <Box>
            <HStack spacing={5}>
              <Box>
                <_Label fontSize="0.8em">Standard Discount %:</_Label>
              </Box>
              <Box>
                <_InputLeftElement
                  _key={`sd.${id}`}
                  isDisabled={inputDisable}
                  fontFamily={numberFont}
                  defaultValue={standardDiscount}
                  type="number"
                  borderBottomColor={inputConfig.borderColor}
                  borderBottomWidth={inputConfig.borderWidth}
                  borderRadius={inputConfig.borderRadius}
                  size={inputConfig.size}
                  fontSize={inputConfig.fontSize}
                  letterSpacing={inputConfig.letterSpacing}
                  width={"100%"}
                  leftElement={<CiDiscount1 color={"#33FFBD"} />}
                  onBlur={(event: any) => {
                    if (event) {
                      setField("standardDiscount", event.target.value.trim());
                    }
                  }}
                ></_InputLeftElement>
              </Box>
            </HStack>
          </Box>
          <Box>
            <HStack spacing={5}>
              <Box>
                <_Label fontSize="0.8em">Credit Limit:</_Label>
              </Box>
              <Box>
                <_InputLeftElement
                  _key={`credit_limit.${id}`}
                  isDisabled={inputDisable}
                  fontFamily={numberFont}
                  defaultValue={creditLimit}
                  type="number"
                  borderBottomColor={inputConfig.borderColor}
                  borderBottomWidth={inputConfig.borderWidth}
                  borderRadius={inputConfig.borderRadius}
                  size={inputConfig.size}
                  fontSize={inputConfig.fontSize}
                  letterSpacing={inputConfig.letterSpacing}
                  width={"100%"}
                  leftElement={<GiTakeMyMoney color={"#FF9900"} />}
                  onBlur={(event: any) => {
                    if (event) {
                      setField("creditLimit", event.target.value.trim());
                    }
                  }}
                ></_InputLeftElement>
              </Box>
            </HStack>
          </Box>
          <Box>
            <HStack spacing={5}>
              <Checkbox
                key={`produce_statement.${id}`}
                isDisabled={inputDisable}
                colorScheme="green"
                isChecked={produceStatementForClient ? true : false}
                onChange={() => {
                  setField(
                    "produceStatementForClient",
                    produceStatementForClient ^ 1
                  );
                }}
              >
                <_Label fontSize="0.8em">Produce Stmt. for this client?</_Label>
              </Checkbox>
            </HStack>
          </Box>
          <Box>
            <HStack spacing={5}>
              <Checkbox
                key={`disable_credit_txn.${id}`}
                isDisabled={inputDisable}
                fontStyle={"italic"}
                colorScheme="red"
                isChecked={disableCreditTransactions ? true : false}
                onChange={() => {
                  setField(
                    "disableCreditTransactions",
                    disableCreditTransactions ^ 1
                  );
                }}
              >
                <_Label fontSize="0.8em">Disable Credit Transactions?</_Label>
              </Checkbox>
            </HStack>
          </Box>
        </Stack>
        <Box width="100%">
          <_Divider />
          <Badge
            fontSize={"0.8em"}
            letterSpacing={5}
            colorScheme={"teal"}
            variant="outline"
          >
            Payment Currency
          </Badge>
          <HStack spacing={10} marginTop={5}>
            <Box width="100%">
              <VStack align="start">
                <HStack spacing={5} width="100%">
                  <RadioGroup width="100%" color="purple" onChange={(currency: any) => {
                    setField("paymentCurrency", currency);
                  }} value={paymentCurrency}>
                    <HStack spacing={20} width="100%">
                      <Radio value='CAD' colorScheme="red">
                        <HStack>
                          <_Label fontSize="0.9em">CAD $</_Label>
                          <Image width="3.5vw" src={flag_CA}></Image>
                        </HStack>
                      </Radio>
                      <Radio value='USD' colorScheme="blue">
                        <HStack width="100%">
                          <_Label fontSize="0.9em">USD $</_Label>
                          <Image width="3.5vw" src={flag_US}></Image>
                        </HStack>
                      </Radio>
                    </HStack>
                    {paymentCurrency === "USD" && <HStack marginTop={5} spacing={20} width="100%">
                      <Box width="15%">
                        <_Label fontSize="0.8em">USD Exchange Rate: </_Label>
                      </Box>
                      <Box width="10%">
                        <_Input fontFamily={numberFont} type="number" defaultValue={exchangeRateUSDToCAD} fontSize="0.8em" onBlur={(event: any) => {
                          let exchangeRate: number = parseFloat(event.target.value);
                          if(isNaN(exchangeRate) === false) {
                            setField("exchangeRateCADToUSD", toFixed(exchangeRate, 2));
                          }
                          else setField("exchangeRateCADToUSD", 0);
                        }}></_Input>
                        </Box>
                    </HStack>}
                  </RadioGroup>
                </HStack>
              </VStack>
            </Box>
          </HStack>
        </Box>
        <Box width="100%">
          <_Divider />
          <Badge
            fontSize={"0.8em"}
            letterSpacing={5}
            colorScheme="orange"
            variant="outline"
          >
            Tax Status
          </Badge>
          <HStack spacing={10} marginTop={5}>
            <Box>
              <HStack spacing={5}>
                <Checkbox
                  key={`disable_federal_tax.${id}`}
                  isDisabled={inputDisable}
                  colorScheme="red"
                  isChecked={disableFederalTaxes ? true : false}
                  onChange={() => {
                    setField("disableFederalTaxes", disableFederalTaxes ^ 1);
                  }}
                >
                  <_Label fontSize="0.8em">Disable Federal Taxes?</_Label>
                </Checkbox>
              </HStack>
            </Box>
            <Box>
              <HStack spacing={5}>
                <Checkbox
                  key={`disable_provincial_tax.${id}`}
                  isDisabled={inputDisable}
                  colorScheme="yellow"
                  isChecked={disableProvincialTaxes ? true : false}
                  onChange={() => {
                    setField(
                      "disableProvincialTaxes",
                      disableProvincialTaxes ^ 1
                    );
                  }}
                >
                  <_Label fontSize="0.8em">Disable Provincial Taxes?</_Label>
                </Checkbox>
              </HStack>
            </Box>
          </HStack>
        </Box>
        <_Divider />
        <Box width="100%">
          <Badge
            fontSize={"0.8em"}
            letterSpacing={5}
            colorScheme="yellow"
            variant="outline"
          >
            Early Payment Discount
          </Badge>
          <HStack spacing={{ sm: 1, md: 1, lg: 1 }} marginTop={5}>
            <Box width="33%">
              <HStack>
                <Box width="60%">
                  <_Label fontSize="0.8em">Early Payment Terms:</_Label>
                </Box>
                <Box width="30%">
                  <_Input
                    _key={`early_payment_discount.${id}`}
                    isDisabled={inputDisable}
                    fontFamily={numberFont}
                    defaultValue={earlyPaymentDiscount}
                    type="number"
                    fontSize={inputConfig.fontSize}
                    borderBottomColor={inputConfig.borderColor}
                    borderBottomWidth={inputConfig.borderWidth}
                    borderRadius={inputConfig.borderRadius}
                    onBlur={(event: any) => {
                      if (event) {
                        setField(
                          "earlyPaymentDiscount",
                          event.target.value.trim()
                        );
                      }
                    }}
                  ></_Input>
                </Box>
                <Box>
                  <_Label fontSize="0.8em">%, </_Label>
                </Box>
              </HStack>
            </Box>
            <Box width="30%">
              <HStack>
                <Box width="40%">
                  <_Label fontSize="0.8em">if paid within:</_Label>
                </Box>
                <Box width="30%">
                  <_Input
                    _key={`early_payment_discount.${id}`}
                    isDisabled={inputDisable}
                    fontFamily={numberFont}
                    defaultValue={earlyPaymentPaidWithinDays}
                    type="number"
                    fontSize={inputConfig.fontSize}
                    borderBottomColor={inputConfig.borderColor}
                    borderBottomWidth={inputConfig.borderWidth}
                    borderRadius={inputConfig.borderRadius}
                    onBlur={(event: any) => {
                      if (event) {
                        setField(
                          "earlyPaymentWithinDays",
                          event.target.value.trim()
                        );
                      }
                    }}
                  ></_Input>
                </Box>
                <Box>
                  <_Label fontSize="0.8em">days(s).</_Label>
                </Box>
              </HStack>
            </Box>
            <Box width="40%">
              <HStack>
                <Box width="50%">
                  <_Label fontSize="0.8em">Net amount due within</_Label>
                </Box>
                <Box width="30%">
                  <_Input
                    _key={`net_amount_due_within.${id}`}
                    isDisabled={inputDisable}
                    fontFamily={numberFont}
                    defaultValue={netAmountDueWithinDays}
                    type="number"
                    fontSize={inputConfig.fontSize}
                    borderBottomColor={inputConfig.borderColor}
                    borderBottomWidth={inputConfig.borderWidth}
                    borderRadius={inputConfig.borderRadius}
                    onBlur={(event: any) => {
                      if (event) {
                        setField(
                          "netAmountDueWithinDays",
                          event.target.value.trim()
                        );
                      }
                    }}
                  ></_Input>
                </Box>
                <Box>
                  <_Label fontSize="0.8em">days(s).</_Label>
                </Box>
              </HStack>
            </Box>
          </HStack>
        </Box>
        <_Divider margin={2} />
        <Box>
          <Badge
            fontSize={"0.8em"}
            letterSpacing={5}
            color="#5D3FD3"
            variant="outline"
          >
            Standard Profit Margins
          </Badge>
          <VStack alignItems={"left"} marginTop={5}>
            {/* Add Custom Prefix  */}
            <Box width="50%">
              <HStack>
                <Box width="100%" transform={"translateY(-25%)"}>
                  <_InputLeftElement
                    _key={`add_custom_prefix.${id}`}
                    type="text"
                    borderBottomWidth={inputConfig.borderWidth}
                    borderRadius={inputConfig.borderRadius}
                    size={inputConfig.size}
                    letterSpacing={inputConfig.letterSpacing}
                    leftElement={<CiText color={"purple"} />}
                    fontSize="0.7em"
                    placeholder="Item Prefix"
                    textTransform={"uppercase"}
                    onBlur={(event: any) => {
                      setNewItemPrefix(event.target.value.trim().toUpperCase());
                    }}
                  ></_InputLeftElement>
                </Box>
                <_Button
                  icon={<IoIosAddCircle color="green" />}
                  color="green"
                  bgColor="white"
                  label="Add Item Prefix"
                  fontSize="1.2em"
                  onClick={() => {
                    if (newItemPrefix && newItemPrefix.length > 0) {
                      setField("standardProfitMargin", {
                        prefix: newItemPrefix,
                        margin: 0,
                      });
                      setNewItemPrefix(null);
                    }
                  }}
                ></_Button>
              </HStack>
              <_Divider margin={2}></_Divider>
            </Box>
            {standardProfitMarginSubstringKeys.map((prefix: string) => {
              return (
                <HStack spacing={5} key={getUUID()}>
                  <Box width="25%" transform={"translateY(25%)"}>
                    <Badge
                      colorScheme={
                        prefix === DEFAULT_PROFIT_MARGIN_KEY ? "gray" : "green"
                      }
                      fontSize="0.8em"
                      letterSpacing={2}
                      variant={
                        prefix === DEFAULT_PROFIT_MARGIN_KEY
                          ? "outline"
                          : "subtle"
                      }
                    >
                      {prefix}
                    </Badge>
                  </Box>
                  <Box width="20%">
                    <_InputLeftElement
                      _key={`standard_profit_margin.${id}`}
                      isDisabled={inputDisable}
                      fontFamily={numberFont}
                      defaultValue={standardProfitMargins[prefix]}
                      type="number"
                      borderBottomColor={inputConfig.borderColor}
                      borderBottomWidth={inputConfig.borderWidth}
                      borderRadius={inputConfig.borderRadius}
                      size={inputConfig.size}
                      fontSize={inputConfig.fontSize}
                      letterSpacing={2}
                      width={"100%"}
                      leftElement={<GiTakeMyMoney color={"green"} />}
                      onBlur={(event: any) => {
                        let profitMargin = parseFloat(
                          event.target.value.trim()
                        );
                        if (
                          prefix === DEFAULT_PROFIT_MARGIN_KEY &&
                          isNaN(profitMargin) === false &&
                          profitMargin > 0
                        ) {
                          let _ans = prompt(
                            "Do you want to set this profit margin as DEFAULT for all items?. Enter 'YES' to confirm and update."
                          );
                          if (
                            _ans !== null &&
                            _ans.toUpperCase().trim() !== "YES"
                          )
                            profitMargin = standardProfitMargins[prefix];
                        }

                        if (event) {
                          setField("standardProfitMargin", {
                            prefix: prefix,
                            margin: profitMargin,
                          });
                        }
                      }}
                    ></_InputLeftElement>
                  </Box>
                  {prefix !== DEFAULT_PROFIT_MARGIN_KEY && (
                    <Box transform={"translateY(25%)"}>
                      <_Button
                        label="Delete"
                        color="red"
                        icon={<FcFullTrash />}
                        onClick={() => {
                          if (confirm("Do you want to delete this item?")) {
                            setField("deleteProfitMargin", prefix);
                          }
                        }}
                      ></_Button>
                    </Box>
                  )}
                </HStack>
              );
            })}
          </VStack>
        </Box>
        <_Divider margin={5} />
        <Box>
          <Badge
            fontSize={"0.8em"}
            letterSpacing={5}
            colorScheme="green"
            variant="outline"
          >
            PAYMENT METHODS
          </Badge>
          <Stack
            direction={{ sm: "column", md: "row", lg: "row" }}
            spacing={{ sm: 5, md: 5, lg: 5 }}
            marginTop={5}
          >
            <Box width={{ sm: "100%", md: "100%", lg: "100%" }}>
              <HStack>
                <Box width="45%">
                  <_Label fontSize="0.8em">Default Payment Method:</_Label>
                </Box>
                <Box width="50%">
                  <_Select
                    _key={`default_payment_method.${id}`}
                    isDisabled={inputDisable}
                    value={defaultPaymentMethod}
                    options={paymentMethods}
                    fontSize={inputConfig.fontSize}
                    onChange={(event: any) => {
                      setField(
                        "defaultPaymentMethod",
                        parseInt(event.target.value)
                      );
                    }}
                  ></_Select>
                </Box>
              </HStack>
            </Box>
            <Box width={{ sm: "100%", md: "100%", lg: "100%" }}>
              <HStack>
                <Box width="20%">
                  <_Label fontSize="0.8em">Receipt:</_Label>
                </Box>
                <Box width="50%">
                  <_Select
                    _key={`receipt_payment_method.${id}`}
                    isDisabled={inputDisable}
                    value={defaultReceiptPaymentMethod}
                    options={__receiptPaymentMethods}
                    fontSize={inputConfig.fontSize}
                    onChange={(event: any) => {
                      setField(
                        "defaultReceiptPaymentMethod",
                        parseInt(event.target.value)
                      );
                    }}
                  ></_Select>
                </Box>
              </HStack>
            </Box>
          </Stack>
        </Box>
        <_Divider></_Divider>
        <Box>
          <Badge
            fontFamily={numberFont}
            fontSize={"0.8em"}
            letterSpacing={5}
            color="#A88357"
            variant="outline"
          >
            ADDITIONAL EMAIL ADDRESSES
          </Badge>
          <Stack
            direction={{ sm: "column", md: "row", lg: "row" }}
            spacing={{ sm: 5, md: 5, lg: 10 }}
            marginTop={5}
          >
            <Box width="25%">
              <_Label fontSize="0.8em">
                Additional Email Addresses(Comma Separated):
              </_Label>
            </Box>
            <Box width="75%">
              <_InputLeftElement
                _key={`additional_email_addresses.${id}`}
                isDisabled={inputDisable}
                borderBottomColor={inputConfig.borderColor}
                borderBottomWidth={inputConfig.borderWidth}
                borderRadius={inputConfig.borderRadius}
                defaultValue={additionalEmailAddresses}
                fontSize={inputConfig.fontSize}
                size="xs"
                leftElement={<MdOutlineAlternateEmail color={iconColor} />}
                onBlur={(event: any) => {
                  if (event) {
                    setField(
                      "additionalEmailAddresses",
                      event.target.value.trim()
                    );
                  }
                }}
              ></_InputLeftElement>
            </Box>
          </Stack>
        </Box>
      </VStack>
    </>
  );
});

const NameHistory = memo(() => {
  // Client Detail Store
  const { nameHistory } = clientStore(
    (state) => ({
      nameHistory: state.nameHistory,
    }),
    shallow
  );

  return nameHistory.map((details: Address, index: number) => {
    return (
      <VStack align={"start"} marginBottom={5} key={getUUID()}>
        <_Label fontSize="1em" fontFamily={numberFont}>
          <b>{index + 1}:</b>
        </_Label>
        {/* Client Name  */}
        <HStack>
          <_Label fontSize="0.8em">
            <i>Client Name:</i>
          </_Label>
          <_Label fontSize="0.8em">{details.name}</_Label>
        </HStack>

        {/* Contact Name  */}
        <HStack>
          <_Label fontSize="0.8em">
            <i>Contact Name:</i>
          </_Label>
          <_Label fontSize="0.8em">{details.contactName}</_Label>
        </HStack>

        {/* Phone Number  */}
        <HStack>
          <_Label fontSize="0.8em" fontFamily={numberFont}>
            <i>Phone Number(s):</i>
          </_Label>
          <_Label fontSize="0.8em" fontFamily={numberFont}>
            {details.phoneNumber1}{" "}
            {details.phoneNumber2 && details.phoneNumber2.length > 0
              ? ", " + details.phoneNumber2
              : ""}
          </_Label>
        </HStack>

        {/* Email */}
        <HStack>
          <_Label fontSize="0.8em" fontFamily={numberFont}>
            <i>Email ID:</i>
          </_Label>
          <_Label fontSize="0.8em" fontFamily={numberFont}>
            {details.emailId}
          </_Label>
        </HStack>
        <_Divider margin={1} />
      </VStack>
    );
  });
});

// Client Footer
const ClientFooter = memo(
  ({
    inputDisable,
    isViewOrUpdate,
    clickHandler,
    loadingState,
  }: SharedClientProps) => {
    // Client Detail Store
    const {
      id,
      isInactive,
      amountOwing,
      category,
      lastPurchaseDate,
      setField,
    } = clientStore(
      (state) => ({
        id: state.id,
        isInactive: state.isInactive,
        amountOwing: state.amountOwing,
        category: state.category,
        lastPurchaseDate: state.lastPurchaseDate,
        setField: state.setField,
      }),
      shallow
    );

    return (
      <>
        <Box width={{ sm: "80%", md: "80%", lg: "85%" }}>
          <HStack spacing={5}>
            <Box>
              <Checkbox
                key={`isInactive.${id}`}
                isDisabled={inputDisable}
                colorScheme="red"
                isChecked={isInactive ? true : false}
                onChange={() => {
                  setField("isInactive", isInactive ^ 1);
                }}
              >
                <_Label fontSize="0.8em">Is Inactive?</_Label>
              </Checkbox>
            </Box>
            <Box>
              <HStack>
                <_Label fontSize="0.8em">Category:</_Label>
                <Box>
                  <_Select
                    _key={`category.${id}`}
                    isDisabled={inputDisable}
                    value={category}
                    options={clientCategory}
                    fontSize={inputConfig.fontSize}
                    onChange={(event: any) => {
                      setField("category", event.target.value);
                    }}
                  ></_Select>
                </Box>
              </HStack>
            </Box>
            <Box>
              <HStack>
                <Box>
                  <_Label fontSize="0.8em">Amount Owing:</_Label>
                </Box>
                <Box>
                  <HStack>
                    <Box>
                      <CurrencyIcon />
                    </Box>
                    <Box>
                      <_Label
                        fontSize="0.8em"
                        letterSpacing={2}
                        fontFamily={numberFont}
                      >
                        {formatNumberWithDecimalPlaces(amountOwing, 2)}
                      </_Label>
                    </Box>
                  </HStack>
                </Box>
              </HStack>
            </Box>
            <Box>
              {lastPurchaseDate !== "" && (
                <Tooltip label="Last Purchase Date">
                  <Badge variant={"outline"}>
                    {new Date(lastPurchaseDate).toLocaleDateString(undefined, {
                      year: "numeric",
                      month: "long",
                      day: "numeric",
                    })}
                  </Badge>
                </Tooltip>
              )}
            </Box>
          </HStack>
        </Box>
        <Box width={{ sm: "20%", md: "20%", lg: "15%" }}>
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
            label={isViewOrUpdate ? "Update" : "Add"}
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
      </>
    );
  }
);

const Client = memo(
  ({ isViewOrUpdate = false }: { isViewOrUpdate?: boolean }) => {
    // Check for Active Session
    redirectIfInvalidSession();

    // Toast Handle.
    const toast = useToast();

    // Client Detail Store
    const {
      id,
      memo,
      additionalInformation,
      setDetails,
      setField,
      fetch,
      add,
      update,
      reset,
    } = clientStore();

    // States
    const [loadingState, setLoadingState] = useState(false);
    const [inputDisable, setInputDisable] = useState(
      isViewOrUpdate && id === null ? true : false
    );

    const [selectedClient, setSelectedClient] = useState<string>("");
    const [clientSuggestions, setClientSuggestions] = useState<any>([]);

    // Select Load options
    const loadOptions = (searchTerm: string) => {
      fetch(searchTerm, false)
        .then((res: any) => {
          let response: APIResponse<ClientDetails[]> = res.data;
          if (response.status === true)
            setClientSuggestions(buildSearchListForClient(response.data));
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
     * This method will handle Add/Update Event.
     */
    const clickHandler = () => {
      if (isViewOrUpdate) {
        if (!confirm("Do you really want to update?")) return false;
      }
      setLoadingState(true);
      setInputDisable(true);

      // Flag
      let isOperationSuccessful: boolean = false;
      try {
        if (isViewOrUpdate) {
          update()
            .then((res: any) => {
              let response: APIResponse<ClientDetails> = res.data;
              if (response.status !== true) {
                setInputDisable(false);
                isOperationSuccessful = false;
              } else isOperationSuccessful = true;
              showToast(toast, response.status, response.message || "");
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
        } else {
          add()
            .then((res: any) => {
              let response: APIResponse<ClientDetails> = res.data;
              if (response.status !== true) {
                setInputDisable(false);
                isOperationSuccessful = false;
              } else isOperationSuccessful = true;
              showToast(toast, response.status, response.message || "");
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
      } catch (err) {
        setInputDisable(false);
      }
    };

    // Client Select
    const onClientSelect = (selectedClient: any) => {
      setDetails(selectedClient.value);
    };

    return (
      isSessionActive() && (
        <>
          <Box bgColor="white" paddingLeft={5} paddingRight={5}>
            <Tabs>
              <TabList marginBottom={5}>
                <Tab>
                  <TabButton icon={<FaAddressCard />} label="Address" />
                </Tab>
                <Tab>
                  <TabButton icon={<FaAddressCard />} label="Ship-to-Address" />
                </Tab>
                <Tab>
                  <TabButton icon={<IoIosOptions />} label="Options" />
                </Tab>
                <Tab>
                  <TabButton icon={<IoIosOptions />} label="Custom Price" />
                </Tab>
                <Tab>
                  <TabButton
                    icon={<HiOutlineInformationCircle />}
                    label="Memo"
                  />
                </Tab>
                <Tab>
                  <TabButton
                    icon={<IoMdInformation />}
                    label="Additional Information"
                  />
                </Tab>
                <Tab>
                  <TabButton icon={<LuHistory />} label="Name History" />
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
                        suggestions={clientSuggestions}
                        onSuggestionsClearRequested={() =>
                          setClientSuggestions([])
                        }
                        onSuggestionsFetchRequested={({ value }) => {
                          if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH)
                            return;
                          loadOptions(value);
                          setLoadingState(false);
                          setInputDisable(false);
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
                            width: "100%",
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
                  <_Divider borderColor="purple" />
                </Box>
              )}

              {/* Client Details */}
              <TabPanels>
                {/* Client Details */}
                <TabPanel>
                  <ClientPrimaryDetails
                    toast={toast}
                    inputDisable={inputDisable}
                  ></ClientPrimaryDetails>
                </TabPanel>
                {/* Ship to Address  */}
                <TabPanel>
                  <ShippingAddressTab inputDisable={inputDisable} />
                </TabPanel>
                {/* OPTIONS  */}
                <TabPanel padding={0}>
                  <ClientOptions inputDisable={inputDisable} />
                </TabPanel>
                {/* Custom  */}
                <TabPanel padding={0}>
                  <SpecialPricingForItem />
                </TabPanel>
                {/* Memo  */}
                <TabPanel>
                  <Textarea
                    key={`memo.${id}`}
                    isDisabled={inputDisable}
                    defaultValue={memo}
                    placeholder="Memo"
                    size="sm"
                    height={"20vh"}
                    borderRadius={2}
                    resize={"none"}
                    onBlur={(event: any) => {
                      if (event) {
                        setField("memo", event.target.value.trim());
                      }
                    }}
                  />
                </TabPanel>
                {/* Additional Information  */}
                <TabPanel>
                  <Textarea
                    key={`additional_information.${id}`}
                    isDisabled={inputDisable}
                    defaultValue={additionalInformation}
                    placeholder="Additional Information"
                    size="sm"
                    height={"20vh"}
                    borderRadius={2}
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

                {/* Name History */}
                <TabPanel>
                  <NameHistory />
                </TabPanel>
              </TabPanels>
            </Tabs>
            <_Divider></_Divider>

            <HStack>
              <ClientFooter
                loadingState={loadingState}
                isViewOrUpdate={isViewOrUpdate}
                clickHandler={clickHandler}
              ></ClientFooter>
            </HStack>
          </Box>
        </>
      )
    );
  }
);

export default Client;
