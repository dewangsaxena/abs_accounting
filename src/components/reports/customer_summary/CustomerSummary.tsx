import {
  Badge,
  Box,
  Card,
  CardBody,
  Center,
  Grid,
  GridItem,
  HStack,
  SimpleGrid,
  Spinner,
  Tooltip,
  useToast,
  VStack,
} from "@chakra-ui/react";
import AsyncSelect from "react-select/async";
import { AsyncSelectStyle, numberFont } from "../../../shared/style";
import { ClientDetails, clientStore } from "../../client/store";
import { APIResponse } from "../../../service/api-client";
import {
  buildSearchListForClient,
  redirectIfInvalidSession,
  formatNumberWithDecimalPlaces,
  getUUID,
  showToast,
  isSessionActive,
} from "../../../shared/functions";
import {
  APP_HOST,
  AttributeType,
  clientCategory,
  MONTHS,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../../shared/config";
import {
  _Button,
  _Divider,
  _Input,
  _Label,
  _Select,
  CurrencyIcon,
  HomeNavButton,
} from "../../../shared/Components";
import { HiOutlineDocumentReport } from "react-icons/hi";
import {
  CustomerSummaryOverallStats,
  CustomerSummaryReport,
  customerSummaryReport,
  CustomerSummaryReportResponse,
  MonthlyReport,
  Summary,
} from "./customer_summary";
import { shallow } from "zustand/shallow";
import { useState } from "react";
import { IoReloadCircleOutline } from "react-icons/io5";
import { FcInfo } from "react-icons/fc";
import { MdOutlineCategory } from "react-icons/md";
import { SlCalender } from "react-icons/sl";
import DatePicker from "react-datepicker";
import { FaUser } from "react-icons/fa";

/**
 * Customer Summary
 * @returns
 */
const CustomerSummary = () => {
  redirectIfInvalidSession();

  // Loading Status
  const [isLoading, setLoadingStatus] = useState<boolean>(false);

  return (
    isSessionActive() && (
      <Grid templateColumns="repeat(5, 1fr)" gap={2}>
        <GridItem colSpan={1}>
          <Filter isLoading={isLoading} setLoadingStatus={setLoadingStatus} />
        </GridItem>
        <GridItem colSpan={4}>
          <Box overflow="auto" height="97vh">
            <ClientList isLoading={isLoading} />
          </Box>
        </GridItem>
      </Grid>
    )
  );
};

// Client List
const ClientList = ({ isLoading }: { isLoading: boolean }) => {
  const { report } = customerSummaryReport(
    (state) => ({
      report: state.report,
    }),
    shallow
  );
  const clientList = Object.keys(report);
  if (isLoading) {
    return (
      <Box marginTop="10%">
        <Center>
          <VStack spacing={10}>
            <Spinner
              speed="1s"
              thickness="4px"
              color="#5D3FD3"
              boxSize={"12vh"}
              emptyColor="#CCCCFF"
            />
            <_Label>Loading Client Summary details...</_Label>
          </VStack>
        </Center>
      </Box>
    );
  }

  if (clientList.length === 0) {
    return (
      <>
        <_Label>No records found.</_Label>
      </>
    );
  }
  return (
    <VStack align="start">
      <Card
        padding={0}
        borderRadius={0}
        borderLeftRadius={0}
        borderLeftWidth={5}
        borderLeftColor="blue"
      >
        <CardBody padding={2}>
          <HStack>
            <FcInfo />
            <_Label fontSize="0.8em">
              No. of records: {clientList.length}
            </_Label>
          </HStack>
        </CardBody>
      </Card>
      {clientList.map((clientId: string, index: number) => {
        let response: __CustomerSummaryReportResponse = {
          ...report[parseInt(clientId)],
          index: index,
        };
        return (
          <Box width="100%" key={getUUID()}>
            <ClientReport {...response} />
            <_Divider borderColor="#d3d3d3" margin={5} />
          </Box>
        );
      })}
    </VStack>
  );
};

// Overall Stats
const OverallStats = ({
  lastPurchaseDate,
  ytd,
}: CustomerSummaryOverallStats) => {
  const textConfig: AttributeType = {
    fontSize: "0.75em",
    letterSpacing: 2,
  };
  const badgeConfig: AttributeType = {
    ...textConfig,
    borderRadius: 2,
    fontFamily: numberFont,
    width: "10vw",
    textAlign: "right",
  };
  return (
    <VStack align="start" width="100%">
      <HStack>
        <SlCalender color="green" />
        <Badge
          fontSize="0.8em"
          colorScheme="green"
          letterSpacing={5}
          borderRadius={0}
        >
          YTD Details for {new Date().getFullYear()}
        </Badge>
      </HStack>
      <Box width="100%">
        <SimpleGrid columns={2} width="100%" spacing={2}>
          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Total Purchased:</_Label>
            </Box>
            <Badge {...badgeConfig}>
              $ {formatNumberWithDecimalPlaces(ytd.sumTotal, 2)}
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>YTD:</_Label>
            </Box>
            <Badge {...badgeConfig}>
              ${" "}
              {formatNumberWithDecimalPlaces(ytd.sumTotal - ytd.sumReturned, 2)}
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Total Returned:</_Label>
            </Box>
            <Badge {...badgeConfig} bgColor="#FBCEB1">
              $ {formatNumberWithDecimalPlaces(ytd.sumReturned, 2)}
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Profit Margin:</_Label>
            </Box>
            <Badge bgColor="#0BDA51" {...badgeConfig}>
              {formatNumberWithDecimalPlaces(ytd.profitMargin, 2)} %
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Net Purchased:</_Label>
            </Box>
            <Badge {...badgeConfig}>
              ${" "}
              {formatNumberWithDecimalPlaces(ytd.sumTotal - ytd.sumReturned, 2)}
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>C.O.G.S Margin:</_Label>
            </Box>
            <Badge bgColor="#DFFF00" {...badgeConfig}>
              {formatNumberWithDecimalPlaces(ytd.cogsMargin, 2)} %
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Total Sale(Subtotal):</_Label>
            </Box>
            <Badge
              {...badgeConfig}
              variant={"solid"}
              bgColor="#d2f8d2"
              color="black"
            >
              ${" "}
              {formatNumberWithDecimalPlaces(ytd.subTotal - ytd.subReturned, 2)}
            </Badge>
          </HStack>

          <HStack width="100%">
            <Box width="40%">
              <_Label {...textConfig}>Last Purchase Date:</_Label>
            </Box>
            <Badge {...badgeConfig} variant={"outline"}>
              {lastPurchaseDate}
            </Badge>
          </HStack>
        </SimpleGrid>
      </Box>
    </VStack>
  );
};

interface MonthlyReportType extends MonthlyReport {
  monthName: string;
}

// Monthly Report
const __MonthlyReport = ({
  monthName,
  sumTotal,
  subTotal,
  profitMargin,
  cogsMargin,
  amountReceived,
}: MonthlyReportType) => {
  const badgeConfig = {
    letterSpacing: 2,
    fontSize: "0.7em",
    borderRadius: 2,
  };
  const badgeConfig2 = {
    fontSize: "0.75em",
    letterSpacing: 2,
    fontFamily: numberFont,
    borderRadius: 0,
    fontWeight: "normal",
  };
  return (
    <HStack width="100%">
      <Box width="20%">
        <Badge {...badgeConfig} colorScheme="blue" variant="subtle">
          {monthName}
        </Badge>
      </Box>
      <Tooltip label="Sum Total">
        <Badge {...badgeConfig2} colorScheme="white">
          $ {formatNumberWithDecimalPlaces(sumTotal, 2)}
        </Badge>
      </Tooltip>
      <_Label>~</_Label>
      <Tooltip label="Sub Total">
        <Badge {...badgeConfig2} colorScheme="white">
          $ {formatNumberWithDecimalPlaces(subTotal, 2)}
        </Badge>
      </Tooltip>
      <_Label>~</_Label>
      <Tooltip label="Profit Margin">
        <Badge
          {...badgeConfig2}
          bgColor={profitMargin > 0 ? "#0BDA51" : "white"}
          color={profitMargin > 0 ? "white" : "black"}
        >
          {formatNumberWithDecimalPlaces(profitMargin, 2)} %
        </Badge>
      </Tooltip>
      <_Label>~</_Label>
      <Tooltip label="C.O.G.S Margin">
        <Badge
          {...badgeConfig2}
          bgColor={cogsMargin > 0 ? "#DFFF00" : "white"}
          color="black"
        >
          {formatNumberWithDecimalPlaces(cogsMargin, 2)} %
        </Badge>
      </Tooltip>
      <_Label>~</_Label>
      <Tooltip label="Amount Received. This is also the amount eligible for commission.">
        <Box
          {...badgeConfig2}
          borderBottomWidth={0}
          borderBottomColor={
            sumTotal > 0.0
              ? amountReceived === subTotal
                ? "#0BDA51"
                : "#E32636"
              : "#BEBEBE"
          }
          paddingLeft={1}
          paddingRight={1}
        >
          $ {formatNumberWithDecimalPlaces(amountReceived, 2)}
        </Box>
      </Tooltip>
      {subTotal > 0.0 && (
        <Box>
          <HStack>
            <Badge
              color={
                amountReceived === subTotal
                  ? "#00FF7F"
                  : amountReceived > 0.0
                  ? "red"
                  : "#FBCEB1"
              }
              letterSpacing={2}
              bgColor={
                amountReceived === subTotal
                  ? "#40826D"
                  : amountReceived > 0.0
                  ? "black"
                  : "#97233F"
              }
              fontSize="0.7em"
              borderRadius={0}
            >
              {amountReceived === subTotal
                ? "PAID ✔"
                : amountReceived > 0.0
                ? "PARTIALLY PAID 🞜"
                : "UNPAID ✖"}
            </Badge>
            {amountReceived > 0.0 && amountReceived < subTotal ? (
              <>
                <_Label>~</_Label>
                <Tooltip label="Amount Unpaid">
                  <Badge {...badgeConfig2} colorScheme="white">
                    ${" "}
                    {formatNumberWithDecimalPlaces(
                      subTotal - amountReceived,
                      2
                    )}
                  </Badge>
                </Tooltip>
              </>
            ) : (
              <></>
            )}
          </HStack>
        </Box>
      )}
    </HStack>
  );
};

// Yearly Record
const YearlyRecord = ({ goodsCost, subTotal, monthlyReport }: Summary) => {
  let labelConfig = {
    fontSize: "0.8em",
    letterSpacing: 2,
    fontFamily: numberFont,
  };

  let badgeConfig = {
    fontSize: "0.7em",
    letterSpacing: 2,
  };

  let months: string[] = Object.keys(monthlyReport);

  return (
    <Card width="100%" borderRadius={10} borderLeftRadius={0}>
      <CardBody
        width="100%"
        padding={2}
        borderLeftWidth={5}
        borderLeftColor={"#CCCCFF"}
      >
        <VStack align="start">
          <HStack width="100%">
            <Box width="20%">
              <Badge {...badgeConfig}>Cost of Goods Sold</Badge>
            </Box>
            <Box width="50%">
              <HStack>
                <CurrencyIcon />
                <_Label {...labelConfig}>
                  {formatNumberWithDecimalPlaces(goodsCost, 2)}
                </_Label>
              </HStack>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="20%">
              <Badge {...badgeConfig}>SubTotal</Badge>
            </Box>
            <Box width="50%">
              <HStack>
                <CurrencyIcon />
                <_Label {...labelConfig}>
                  {formatNumberWithDecimalPlaces(subTotal, 2)}
                </_Label>
              </HStack>
            </Box>
          </HStack>
          <_Divider margin={1} />
          {months.map((month: string) => {
            return (
              <VStack align="start" width="100%" key={getUUID()}>
                <__MonthlyReport
                  monthName={MONTHS[parseInt(month)]}
                  {...monthlyReport[parseInt(month)]}
                />
              </VStack>
            );
          })}
        </VStack>
      </CardBody>
    </Card>
  );
};

// Yearly Report
const YearlyReport = (summary: AttributeType<Summary>) => {
  // Years
  const yearsKeys: string[] = Object.keys(summary);
  let __years: AttributeType = {};
  for (let i = 0; i < yearsKeys.length; ++i) {
    __years[yearsKeys[i]] = yearsKeys[i];
  }
  const [selectedYear, setSelectedYear] = useState<number>(
    yearsKeys.length > 0
      ? parseInt(yearsKeys[yearsKeys.length - 1])
      : new Date().getFullYear()
  );
  return (
    <VStack width="100%" align="start">
      <HStack width="100%">
        <Box width="20%">
          <_Label fontSize="0.8em">View record for the year:</_Label>
        </Box>
        <Box width="10%">
          <_Select
            value={selectedYear}
            fontSize="0.9em"
            fontFamily={numberFont}
            options={__years}
            onChange={(event: any) => {
              setSelectedYear(parseInt(event.target.value));
            }}
          ></_Select>
        </Box>
      </HStack>
      <YearlyRecord {...summary[selectedYear]} />
    </VStack>
  );
};

interface __CustomerSummaryReportResponse extends CustomerSummaryReport {
  index: number;
}

// Client Report
const ClientReport = ({
  index,
  category,
  lastPurchaseDate,
  name,
  summary,
  ytd,
}: __CustomerSummaryReportResponse) => {
  return (
    <Box>
      <Card
        borderRadius={10}
        borderLeftRadius={0}
        borderLeftWidth={5}
        borderLeftColor={index & 1 ? "#9ca6d9" : "#A6D99C"}
      >
        <CardBody>
          <VStack align="start">
            <VStack spacing={1} align="start">
              <HStack>
                <FaUser color="#CCCCFF" />
                <Badge
                  borderRadius={2}
                  fontSize="1em"
                  color="#5D3FD3"
                  letterSpacing={2}
                  bgColor="#CCCCFF"
                  colorScheme={"green"}
                >
                  {name}
                </Badge>
              </HStack>
              <HStack>
                <MdOutlineCategory color="#5D3FD3" fontSize="1.2em" />
                <Badge variant={"outline"} letterSpacing={2} fontSize="0.62em">
                  {clientCategory[category]}
                </Badge>
              </HStack>
            </VStack>
            <_Divider margin={1} />
            <OverallStats lastPurchaseDate={lastPurchaseDate} ytd={ytd} />
            <_Divider margin={2} />
            <YearlyReport {...summary} />
          </VStack>
        </CardBody>
      </Card>
    </Box>
  );
};

/**
 * Filter
 * @returns
 */
const Filter = ({
  isLoading,
  setLoadingStatus,
}: {
  isLoading: boolean;
  setLoadingStatus: React.Dispatch<React.SetStateAction<boolean>>;
}) => {
  // Fetch Client
  const { fetch: fetchClient } = clientStore();

  const {
    minimumAmount,
    maximumAmount,
    yearFrom,
    yearTill,
    category,
    setDetail,
    setReport,
    fetch: fetchReport,
  } = customerSummaryReport();

  // Toast
  const toast = useToast();

  // Load Clients
  const loadOptionsForClient = (
    searchTerm: string,
    callback: (args: any) => void
  ) => {
    fetchClient(searchTerm, true)
      .then((res: any) => {
        let response: APIResponse<ClientDetails[]> = res.data;
        if (response.status === true)
          callback(buildSearchListForClient(response.data));
        else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      });
  };

  // Generate Summary
  const generateSummary = (sendOffset: boolean = false) => {
    setLoadingStatus(true);
    fetchReport(sendOffset)
      .then((_res: any) => {
        let response: APIResponse<CustomerSummaryReportResponse> = _res.data;
        if (response.status === true && response.data) {
          setReport(response.data);
        } else
          showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      })
      .finally(() => {
        setLoadingStatus(false);
      });
  };

  const __clientCategory: AttributeType = {
    0: "All Categories",
    ...clientCategory,
  };

  // Last Purchase Date
  const [lastPurchaseDate, setLastPurchaseDate] = useState<Date | null>(null);

  return (
    <Card bgColor="#EEF5FF">
      <CardBody padding={2}>
        <VStack width="100%">
          <HomeNavButton />
          <Box width="100%">
            <AsyncSelect
              isDisabled={isLoading}
              isClearable={true}
              tabSelectsValue={true}
              placeholder="Search Customer..."
              styles={AsyncSelectStyle}
              cacheOptions={false}
              loadOptions={loadOptionsForClient}
              defaultOptions={false}
              onChange={(_event: any) => {
                if (_event && _event.value) {
                  let event: ClientDetails = _event.value;
                  if (event.id) setDetail("selectedClients", event.id);
                }
              }}
            />
          </Box>
          <HStack width="100%">
            <Box width="50%">
              <_Label fontSize="0.75em">Minimum Amount:</_Label>
            </Box>
            <Box width="50%">
              <_Input
                isDisabled={isLoading}
                defaultValue={minimumAmount}
                type="number"
                fontSize="0.8em"
                onBlur={(event: any) => {
                  if (event && event.target) {
                    setDetail("minimumAmount", parseInt(event.target.value));
                  }
                }}
              ></_Input>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="50%">
              <_Label fontSize="0.75em">Maxiumum Amount:</_Label>
            </Box>
            <Box width="50%">
              <_Input
                isDisabled={isLoading}
                defaultValue={maximumAmount}
                type="number"
                fontSize="0.8em"
                onBlur={(event: any) => {
                  if (event && event.target) {
                    setDetail("maximumAmount", parseInt(event.target.value));
                  }
                }}
              ></_Input>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="50%">
              <_Label fontSize="0.75em">Year (From):</_Label>
            </Box>
            <Box width="50%">
              <_Input
                isDisabled={isLoading}
                defaultValue={yearFrom}
                type="number"
                fontSize="0.8em"
                onBlur={(event: any) => {
                  if (event && event.target) {
                    setDetail("yearFrom", parseInt(event.target.value));
                  }
                }}
              ></_Input>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="50%">
              <_Label fontSize="0.75em">Year (Till):</_Label>
            </Box>
            <Box width="50%">
              <_Input
                isDisabled={isLoading}
                defaultValue={yearTill}
                type="number"
                fontSize="0.8em"
                onBlur={(event: any) => {
                  if (event && event.target) {
                    setDetail("yearTill", parseInt(event.target.value));
                  }
                }}
              ></_Input>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="30%">
              <_Label fontSize="0.75em">Category:</_Label>
            </Box>
            <Box width="70%">
              <_Select
                isDisabled={isLoading}
                value={category}
                options={__clientCategory}
                fontSize={"0.75em"}
                onChange={(event: any) => {
                  setDetail("category", parseInt(event.target.value));
                }}
              ></_Select>
            </Box>
          </HStack>
          <_Divider margin={1} />
          <Box width="100%">
            <_Label fontSize="0.7em" textTransform={"uppercase"}>
              Search Clients by Last Purchase Date.
            </_Label>
          </Box>
          <HStack width="100%">
            <_Label fontSize="0.8em">Before Date:</_Label>
            <DatePicker
              wrapperClassName="datepicker_style"
              dateFormat={"MM/dd/yyyy"}
              placeholderText="Txn. Date"
              selected={lastPurchaseDate}
              onChange={(date: any) => {
                setLastPurchaseDate(date);
              }}
              closeOnScroll={true}
              maxDate={new Date()}
            />
          </HStack>
          <_Button
            color={"green"}
            bgColor="white"
            isDisabled={lastPurchaseDate !== null ? false : true}
            onClick={() => {
              if (lastPurchaseDate) {
                let month: number | string = lastPurchaseDate.getMonth() + 1;
                if (month < 10) month = `0${month}`;

                let date: number | string = lastPurchaseDate.getDate();
                if (date < 10) date = `0${date}`;
                let _date: string =
                  lastPurchaseDate.getFullYear() + "-" + month + "-" + date;
                const urlWithParam = new URL(APP_HOST + "/api.php");
                urlWithParam.searchParams.append("op", "last_purchase_before");
                urlWithParam.searchParams.append(
                  "storeId",
                  localStorage.getItem("storeId") || "0"
                );
                urlWithParam.searchParams.append("lastPurchaseBefore", _date);
                window.open(urlWithParam.href);
              }
            }}
            fontSize="1.5em"
            icon={<FaUser fontSize="0.8em" />}
            label="View Clients"
          ></_Button>
          <_Divider margin={2} />
          <_Button
            isDisabled={isLoading}
            color={"#ADD8E6"}
            fontSize="1.5em"
            bgColor="black"
            icon={<HiOutlineDocumentReport color="#ADD8E6" />}
            label="Generate Summary"
            onClick={() => generateSummary(false)}
          ></_Button>
          <>
            <_Divider margin={2} />
            <_Button
              isDisabled={isLoading}
              color={"green"}
              fontSize="1.5em"
              bgColor="#D8E1DC"
              icon={<IoReloadCircleOutline color="green" />}
              label="Fetch More Records"
              onClick={() => {
                generateSummary(true);
              }}
            ></_Button>
          </>
        </VStack>
      </CardBody>
    </Card>
  );
};
export default CustomerSummary;
