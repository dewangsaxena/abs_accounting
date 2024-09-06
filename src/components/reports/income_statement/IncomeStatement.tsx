import {
  Badge,
  Box,
  Card,
  CardBody,
  Checkbox,
  HStack,
  SimpleGrid,
  VStack,
  useMediaQuery,
  useToast,
} from "@chakra-ui/react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
} from "recharts";
import {
  HomeNavButton,
  _Button,
  _Divider,
  _Label,
} from "../../../shared/Components";
import { filterStore } from "./incomeStatementStore";
import { shallow } from "zustand/shallow";
import {
  APP_HOST,
  AttributeType,
  MODE_WASH,
  Stores,
  systemConfigMode,
} from "../../../shared/config";
import DatePicker from "react-datepicker";
import { useState } from "react";
import { APIResponse } from "../../../service/api-client";
import {
  calculateCOGSMargin,
  calculateProfitMargin,
  redirectIfInvalidSession,
  formatNumberWithDecimalPlaces,
  getUUID,
  showToast,
} from "../../../shared/functions";
import { navBgColor, numberFont } from "../../../shared/style";
import { FaFilePdf } from "react-icons/fa";
import { FcLineChart } from "react-icons/fc";

const Filter = () => {
  const toast = useToast();
  const { startDate, endDate, setDate, setData, fetch, selectedStores } =
    filterStore(
      (state) => ({
        startDate: state.startDate,
        endDate: state.endDate,
        selectedStores: state.selectedStores,
        setDate: state.setDate,
        setData: state.setData,
        fetch: state.fetch,
      }),
      shallow
    );

  const [noStoreSelected, setNoStoreSelected] = useState<boolean>(true);
  const [disableButton, setDisableButton] = useState<boolean>(false);

  // Fetch Income Statement
  const fetchIncomeStatement = (setHideChart: any) => {
    setDisableButton(true);
    fetch()
      .then((res: any) => {
        let result: APIResponse = res.data;
        if (result.status === true) {
          setData(result.data);
          if (setHideChart !== null) setHideChart(true);
        }
      })
      .catch((err: any) => {
        showToast(toast, false, err.message);
      })
      .finally(() => {
        setDisableButton(false);
      });
  };

  // Generate
  const generate = () => {
    fetchIncomeStatement(null);
  };

  // Print
  const print = () => {
    const urlWithParam = new URL(APP_HOST + "/api.php");
    urlWithParam.searchParams.append("op", "income_statement");
    let dates = JSON.stringify({
      startDate: startDate,
      endDate: endDate,
    });
    urlWithParam.searchParams.append("dates", dates);
    urlWithParam.searchParams.append(
      "selectedStores",
      Object.keys(selectedStores).toString()
    );
    window.open(urlWithParam);
  };

  const stores = Stores.getActiveStores();

  return (
    <Card width="100%">
      <CardBody padding={1} width="100%">
        <VStack align="start">
          <Box width="30%">
            <HomeNavButton />
          </Box>
          <HStack width="100%">
            <Box width={{ sm: "50%", lg: "20%", md: "50%" }}>
              <HStack>
                <_Label fontSize="0.8em">Start Date:</_Label>
                <DatePicker
                  wrapperClassName="datepicker_style"
                  dateFormat={"MM/dd/yyyy"}
                  placeholderText="Txn. Date"
                  selected={startDate}
                  onChange={(date: any) => {
                    setDate("startDate", date);
                  }}
                  closeOnScroll={true}
                  maxDate={new Date()}
                />
              </HStack>
            </Box>
            <Box width="20%">
              <HStack>
                <_Label fontSize="0.8em">End Date:</_Label>
                <DatePicker
                  wrapperClassName="datepicker_style"
                  dateFormat={"MM/dd/yyyy"}
                  placeholderText="Txn. Date"
                  selected={endDate}
                  onChange={(date: any) => {
                    setDate("endDate", date);
                  }}
                  closeOnScroll={true}
                  maxDate={new Date()}
                />
              </HStack>
            </Box>
          </HStack>
          <HStack>
            <_Label fontSize="0.8em">Locations:</_Label>
            {Object.keys(stores).map((value: string) => {
              /* Skip All Stores and Vancouver */
              let newValue: number = parseInt(value);
              if (newValue == 1 || newValue == 5) return;
              return (
                <Checkbox
                  key={value}
                  colorScheme="gray"
                  onChange={() => {
                    if (selectedStores[newValue] === undefined) {
                      selectedStores[newValue] = newValue;
                      setNoStoreSelected(false);
                    } else {
                      delete selectedStores[newValue];
                      if (Object.keys(selectedStores).length === 0)
                        setNoStoreSelected(true);
                    }
                  }}
                >
                  <_Label fontSize="0.8em">{Stores.names[newValue]}</_Label>
                </Checkbox>
              );
            })}
          </HStack>
          <HStack width="25%">
            <_Button
              icon={<FcLineChart />}
              isDisabled={disableButton || noStoreSelected}
              color="#ADD8E6"
              bgColor={navBgColor}
              label="Generate"
              onClick={generate}
              fontSize="1.2em"
            ></_Button>
            <_Button
              icon={<FaFilePdf />}
              isDisabled={disableButton || noStoreSelected}
              color="#90EE90"
              bgColor={navBgColor}
              label="PDF"
              onClick={print}
              fontSize="1.2em"
            ></_Button>
          </HStack>
          <_Divider margin={1}></_Divider>
        </VStack>
      </CardBody>
    </Card>
  );
};

/**
 * Show Chart
 * @param data
 * @returns
 */
const ShowChart = ({ data }: { data: any[] }) => {
  return (
    <LineChart
      width={500}
      height={300}
      data={data}
      margin={{
        top: 5,
        right: 30,
        left: 20,
        bottom: 5,
      }}
    >
      <CartesianGrid strokeDasharray="6 6" />
      <XAxis dataKey="name" />
      <YAxis />
      <Tooltip />
      <Legend />
      <Line
        type={"linear"}
        dataKey="profit"
        stroke="#a6cc70"
        activeDot={{ r: 8 }}
      />
      <Line type="linear" dataKey="inventory" stroke="#800000" />
      <Line type="linear" dataKey="sales" stroke="#7851A9" />
    </LineChart>
  );
};

/**
 * Breakdown Wash
 * @param statement
 * @returns
 */
const BreakdownWash = ({ statement }: any) => {
  return (
    <VStack width="100%">
      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Part Sales</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4150], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Merchandise Sales</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4170], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Labour Revenue</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4175], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Sales</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4200], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Full Service</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4205], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Self Wash</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4210], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Oil & Grease</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4215], 2)}
          </_Label>
        </Box>
      </HStack>

      <HStack spacing={20} width="100%">
        <Box width="100%">
          <_Label fontSize={"0.8em"}>Miscellaneous Revenue</_Label>
        </Box>
        <Box>
          <_Label fontSize={"0.8em"} fontFamily={numberFont} letterSpacing={2}>
            {formatNumberWithDecimalPlaces(statement[4460], 2)}
          </_Label>
        </Box>
      </HStack>
    </VStack>
  );
};

/**
 * Breakdown
 * @param data
 */
const Breakdown = ({ statement }: any) => {
  let totalRevenue = statement[4020] - statement[4220] - statement[4240];

  if (systemConfigMode === MODE_WASH) {
    /* Part Sales */
    totalRevenue += isNaN(statement[4150]) ? 0 : statement[4150];

    /* Merchandise Sales */
    totalRevenue += statement[4170];

    /* Labour Revenue */
    totalRevenue += statement[4175];

    /* Sales */
    totalRevenue += statement[4200];

    /* Full Service */
    totalRevenue += statement[4205];

    /* Self Wash */
    totalRevenue += statement[4210];

    /* Oil & Grease */
    totalRevenue += statement[4215];

    /* Miscellaneous Revenue */
    totalRevenue += statement[4460];
  }

  let totalExpense = statement[1520];
  let profitMarginWithSalesReturn = calculateProfitMargin(
    totalRevenue,
    totalExpense
  );

  let cogsMargin = calculateCOGSMargin(totalRevenue, totalExpense);
  return (
    <Box width="100%">
      <VStack align={"start"} paddingLeft={8} paddingRight={8} width="100%">
        <_Label fontWeight="bold" fontSize={"0.8em"}>
          REVENUE
        </_Label>
        <VStack align="start" paddingLeft={4} width="100%">
          <_Label fontWeight="bold" fontSize={"0.8em"}>
            Sales Revenue
          </_Label>
          <Box width="100%">
            <VStack align="start" paddingLeft={4}>
              <HStack spacing={20} width="100%">
                <Box width="100%">
                  <_Label fontSize={"0.8em"}>Sales Inventory</_Label>
                </Box>
                <Box>
                  <_Label
                    fontSize={"0.8em"}
                    fontFamily={numberFont}
                    letterSpacing={2}
                  >
                    {formatNumberWithDecimalPlaces(statement[4020], 2)}
                  </_Label>
                </Box>
              </HStack>
              <HStack spacing={20} width="100%">
                <Box width="100%">
                  <_Label fontSize={"0.8em"}>Sales Returns</_Label>
                </Box>
                <Box>
                  <_Label
                    fontSize={"0.8em"}
                    fontFamily={numberFont}
                    letterSpacing={2}
                  >
                    {statement[4220] > 0 ? "-" : ""}
                    {formatNumberWithDecimalPlaces(statement[4220], 2)}
                  </_Label>
                </Box>
              </HStack>
              <HStack spacing={20} width="100%">
                <Box width="100%">
                  <_Label fontSize={"0.8em"}>
                    Early Payment Sales Discounts
                  </_Label>
                </Box>
                <Box>
                  <_Label
                    fontSize={"0.8em"}
                    fontFamily={numberFont}
                    letterSpacing={2}
                  >
                    {statement[4240] > 0 ? "-" : ""}
                    {formatNumberWithDecimalPlaces(statement[4240], 2)}
                  </_Label>
                </Box>
              </HStack>

              {/* Breakdown Wash */}
              {systemConfigMode === MODE_WASH && (
                <BreakdownWash statement={statement} />
              )}
            </VStack>
          </Box>
        </VStack>
        <HStack
          spacing={20}
          width="100%"
          borderTop={"1px"}
          borderBottom={"1px"}
        >
          <Box width="100%">
            <_Label fontSize={"0.8em"} fontWeight="bold">
              TOTAL REVENUE
            </_Label>
          </Box>
          <Box>
            <_Label
              fontSize={"0.8em"}
              fontWeight="bold"
              fontFamily={numberFont}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(totalRevenue, 2)}
            </_Label>
          </Box>
        </HStack>
        <Box marginTop={5}>
          <_Label fontWeight="bold" fontSize={"0.8em"}>
            EXPENSE
          </_Label>
        </Box>
        <VStack align="start" paddingLeft={4} width="100%">
          <_Label fontWeight="bold" fontSize={"0.8em"}>
            Inventory
          </_Label>
          <Box width="100%">
            <VStack align="start" paddingLeft={4}>
              <HStack spacing={20} width="100%">
                <Box width="100%">
                  <_Label fontSize={"0.8em"}>Cost of Goods Sold</_Label>
                </Box>
                <Box>
                  <_Label
                    fontSize={"0.8em"}
                    fontFamily={numberFont}
                    letterSpacing={2}
                  >
                    {formatNumberWithDecimalPlaces(totalExpense, 2)}
                  </_Label>
                </Box>
              </HStack>
            </VStack>
          </Box>
        </VStack>
        <HStack
          spacing={20}
          width="100%"
          borderTop={"1px"}
          borderBottom={"1px"}
        >
          <Box width="100%">
            <_Label fontSize={"0.8em"} fontWeight="bold">
              TOTAL EXPENSE
            </_Label>
          </Box>
          <Box>
            <_Label
              fontSize={"0.8em"}
              fontWeight="bold"
              fontFamily={numberFont}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(totalExpense, 2)}
            </_Label>
          </Box>
        </HStack>
        <HStack
          marginTop={5}
          width="100%"
          borderTop={"1px"}
          borderBottom={"1px"}
        >
          <Box width="70%">
            <_Label fontSize={"0.8em"} fontWeight="bold">
              NET INCOME
            </_Label>
          </Box>
          <Box width="30%">
            <_Label
              fontSize={"0.8em"}
              fontWeight="bold"
              textAlign={"right"}
              fontFamily={numberFont}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(totalRevenue + totalExpense, 2)}
            </_Label>
          </Box>
        </HStack>
        <HStack
          marginTop={1}
          width="100%"
          borderTop={"1px"}
          borderBottom={"1px"}
          borderStyle={"dashed"}
        >
          <Box width="70%">
            <Badge
              color="#5D3FD3"
              bgColor="#CCCCFF"
              fontSize={"0.8em"}
              fontWeight="bold"
            >
              PROFIT MARGIN
            </Badge>
          </Box>
          <Box width="30%">
            <_Label
              fontSize={"0.8em"}
              fontWeight="bold"
              textAlign={"right"}
              fontFamily={numberFont}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(
                totalExpense != 0 ? profitMarginWithSalesReturn : 0,
                2
              )}{" "}
              %
            </_Label>
          </Box>
        </HStack>
        <HStack
          marginTop={1}
          width="100%"
          borderTop={"1px"}
          borderBottom={"1px"}
          borderStyle={"dashed"}
        >
          <Box width="70%">
            <Badge colorScheme="blue" fontSize={"0.8em"} fontWeight="bold">
              C.O.G.S MARGIN
            </Badge>
          </Box>
          <Box width="30%">
            <_Label
              fontSize={"0.8em"}
              fontWeight="bold"
              textAlign={"right"}
              fontFamily={numberFont}
              letterSpacing={2}
            >
              {formatNumberWithDecimalPlaces(
                totalExpense != 0 ? cogsMargin : 0,
                2
              )}{" "}
              %
            </_Label>
          </Box>
        </HStack>
      </VStack>
    </Box>
  );
};

// Segment
const Segment = ({ currentStore }: { currentStore: number }) => {
  const { data, selectedStores } = filterStore(
    (state) => ({
      data: state.data,
      selectedStores: state.selectedStores,
    }),
    shallow
  );

  let props: AttributeType = {};
  const [isDesktopScreen] = useMediaQuery("(min-width: 1080px)");
  if (isDesktopScreen === false) props["minChildWidth"] = "120";
  return (
    <>
      <SimpleGrid columns={3} spacing={"3vw"} {...props}>
        {Object.keys(selectedStores).map((value) => {
          let storeId = parseInt(value);
          let chartData = data.chartData[storeId];
          let statement = data.statement[storeId];
          return (
            <Box key={getUUID()} width="100%">
              <VStack>
                <Badge
                  colorScheme={currentStore === storeId ? "purple" : "green"}
                  marginBottom={5}
                  letterSpacing={2}
                >
                  {Stores.names[storeId]}
                </Badge>
                <ShowChart data={chartData} />
                <Breakdown statement={statement} />
              </VStack>
            </Box>
          );
        })}
      </SimpleGrid>
    </>
  );
};

/**
 * Income Statement
 * @returns
 */
const IncomeStatement = () => {
  redirectIfInvalidSession();
  return (
    <Box>
      <VStack align={"start"}>
        <Filter />
        <Segment
          currentStore={parseInt(localStorage.getItem("storeId") || "0")}
        />
      </VStack>
    </Box>
  );
};

export default IncomeStatement;
