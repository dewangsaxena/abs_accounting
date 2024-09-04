import {
  Badge,
  Box,
  Card,
  CardBody,
  HStack,
  Tooltip,
  VStack,
  useInterval,
  useToast,
} from "@chakra-ui/react";
import { _Divider, _Label } from "../../../shared/Components";
import { FcInfo } from "react-icons/fc";
import { numberFont } from "../../../shared/style";
import { APIResponse, HTTPService } from "../../../service/api-client";
import { create } from "zustand";
import {
  formatNumberWithDecimalPlaces,
  showToast,
} from "../../../shared/functions";
import { CiTimer } from "react-icons/ci";
import { useEffect } from "react";

// Http Service
const httpService = new HTTPService();

// Summary Report Details
interface SummaryReportDetails {
  cogs: number;
  cogsMargin: number;
  netIncome: number;
  discount: number;
  totalRevenue: number;
  salesReturn: number;
  profitMargin: number;
  receiptPayments: number;
  receiptDiscount: number;
  lastUpdated?: Date;
}

// Summary Report Details Store
interface SummaryReportDetailsStore extends SummaryReportDetails {
  fetch: () => any;
  setStats: (details: SummaryReportDetails) => void;
  setLastUpdated: (lastUpdated: Date) => void;
}

export const summaryReportDetails = create<SummaryReportDetailsStore>(
  (set, _) => ({
    cogs: 0,
    cogsMargin: 0,
    netIncome: 0,
    totalRevenue: 0,
    discount: 0,
    salesReturn: 0,
    profitMargin: 0,
    receiptPayments: 0,
    receiptDiscount: 0,
    lastUpdated: new Date(),
    fetch: async () => {
      return httpService.fetch<SummaryReportDetails>({}, "stats");
    },
    setStats: (details: SummaryReportDetails) => {
      set({ cogs: details.cogs });
      set({ cogsMargin: details.cogsMargin });
      set({ netIncome: details.netIncome });
      set({ discount: details.discount });
      set({ totalRevenue: details.totalRevenue });
      set({ salesReturn: details.salesReturn });
      set({ profitMargin: details.profitMargin });
      set({ receiptPayments: details.receiptPayments });
      set({ receiptDiscount: details.receiptDiscount });
    },
    setLastUpdated: (lastUpdated: Date) => {
      set({ lastUpdated: lastUpdated });
    },
  })
);

const Header = () => {
  let date = new Date().toDateString();
  return (
    <VStack align="start">
      <HStack>
        <FcInfo />
        <_Label letterSpacing={2} fontSize="0.8em" fontFamily={numberFont}>
          Summary Report for <b>{date}</b>
        </_Label>
      </HStack>
    </VStack>
  );
};

// Update Interval
const UPDATE_INTERVAL: number = 300_000;
const INTERVAL_IN_MINUTES: number = UPDATE_INTERVAL / 1000 / 60;

const InventoryReport = () => {
  const {
    cogs,
    cogsMargin,
    totalRevenue,
    salesReturn,
    profitMargin,
    netIncome,
    discount,
    receiptPayments,
    receiptDiscount,
    fetch,
    setStats,
    setLastUpdated,
  } = summaryReportDetails((state) => ({
    cogs: state.cogs,
    cogsMargin: state.cogsMargin,
    totalRevenue: state.totalRevenue,
    salesReturn: state.salesReturn,
    profitMargin: state.profitMargin,
    discount: state.discount,
    netIncome: state.netIncome,
    receiptPayments: state.receiptPayments,
    receiptDiscount: state.receiptDiscount,
    fetch: state.fetch,
    setStats: state.setStats,
    setLastUpdated: state.setLastUpdated,
  }));

  // Toast Handle
  const toast = useToast();

  // Update Stats
  const updateStats = (response: SummaryReportDetails) => {
    setStats(response);
    setLastUpdated(new Date());
  };

  const ERROR_MESSAGE: string = "Unable to Fetch Stats.";

  // Execute Once only...
  useEffect(() => {
    fetch().then((res: any) => {
      let response: APIResponse<SummaryReportDetails> = res.data;
      if (response.status && response.data) {
        updateStats(response.data);
      } else showToast(toast, false, ERROR_MESSAGE);
    });
  }, []);

  useInterval(() => {
    fetch().then((res: any) => {
      let response: APIResponse<SummaryReportDetails> = res.data;
      if (response.status && response.data) {
        updateStats(response.data);
      } else showToast(toast, false, ERROR_MESSAGE);
    });
  }, UPDATE_INTERVAL);

  return (
    <VStack align={"start"} width="100%">
      <HStack width="100%">
        <Box width="10vw">
          <Badge
            bgColor="#04AF70"
            color="white"
            letterSpacing={2}
            borderRadius={0}
          >
            Total Revenue
          </Badge>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {formatNumberWithDecimalPlaces(totalRevenue, 2)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Badge colorScheme="red" letterSpacing={2} borderRadius={0}>
            Sales Return
          </Badge>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          ${" "}
          {salesReturn > 0
            ? "" + formatNumberWithDecimalPlaces(salesReturn, 2)
            : "-"}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Cost of Goods Sold">
            <Badge colorScheme="orange" letterSpacing={2} borderRadius={0}>
              C.O.G.S
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {cogs > 0 ? "" + formatNumberWithDecimalPlaces(cogs, 2) : "-"}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Transaction Discount">
            <Badge
              color="white"
              bgColor="#F88379"
              letterSpacing={2}
              borderRadius={0}
            >
              Discount
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          ${" "}
          {discount > 0 ? "" + formatNumberWithDecimalPlaces(discount, 2) : "-"}
        </_Label>
      </HStack>
      <HStack width="100%">
        <_Divider margin={1} />
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Net income after deducting Sales Returns and C.O.G.S">
            <Badge colorScheme="green" letterSpacing={2} borderRadius={0}>
              Net Income
            </Badge>
          </Tooltip>
        </Box>
        <_Label
          fontFamily={numberFont}
          fontSize="0.8em"
          letterSpacing={2}
          hide={true}
          toggleVisibility={true}
        >
          $ {formatNumberWithDecimalPlaces(netIncome, 2)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Profit Margin Adjusted for Sales Return">
            <Badge
              color="#5D3FD3"
              bgColor="#CCCCFF"
              letterSpacing={2}
              borderRadius={0}
            >
              Profit Margin
            </Badge>
          </Tooltip>
        </Box>
        <_Label
          fontFamily={numberFont}
          fontSize="0.8em"
          letterSpacing={2}
          hide={true}
          toggleVisibility={true}
        >
          {formatNumberWithDecimalPlaces(profitMargin, 2)} %
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="C.O.G.S Margin">
            <Badge
              color="#14EB71"
              bgColor="#1422EB"
              letterSpacing={2}
              borderRadius={0}
            >
              C.O.G.S Margin
            </Badge>
          </Tooltip>
        </Box>
        <_Label
          fontFamily={numberFont}
          fontSize="0.8em"
          letterSpacing={2}
          hide={true}
          toggleVisibility={true}
        >
          {formatNumberWithDecimalPlaces(cogsMargin, 2)} %
        </_Label>
      </HStack>
      <HStack width="100%">
        <_Divider margin={1} />
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Receipts Payments received">
            <Badge
              color="#5D3FD3"
              bgColor="#B5D33F"
              letterSpacing={2}
              borderRadius={0}
            >
              Receipt Payments
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {formatNumberWithDecimalPlaces(receiptPayments, 2)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Receipts discounts">
            <Badge
              color="white"
              bgColor="#F88379"
              letterSpacing={2}
              borderRadius={0}
            >
              Receipt Discount
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {formatNumberWithDecimalPlaces(receiptDiscount, 2)}
        </_Label>
      </HStack>
    </VStack>
  );
};

const StatsUpdateMsg = () => {
  const { lastUpdated } = summaryReportDetails();
  return (
    <VStack align="start">
      <HStack>
        <CiTimer color="purple" />
        <_Label fontSize="0.6em" fontFamily={numberFont}>
          <i>Stats are updated every {INTERVAL_IN_MINUTES} Minutes.</i>
        </_Label>
      </HStack>
      <HStack>
        <_Label fontSize="0.6em" fontFamily={numberFont}>
          Last Updated:
        </_Label>
        <_Label
          fontSize="0.6em"
          fontFamily={numberFont}
          letterSpacing={2}
          fontWeight="bold"
        >
          {lastUpdated?.toLocaleTimeString()}
        </_Label>
      </HStack>
    </VStack>
  );
};

const Stats = () => {
  return (
    <Card>
      <CardBody borderLeftColor={"#5D3FD3"} borderLeftWidth={5}>
        <VStack align={"start"}>
          {/* Header */}
          <Header />
          <_Divider margin={1} />

          {/* Inventory Report */}
          <InventoryReport />
          <_Divider margin={1} />

          {/* Stats Update Msg  */}
          <StatsUpdateMsg />
        </VStack>
      </CardBody>
    </Card>
  );
};

export default Stats;
