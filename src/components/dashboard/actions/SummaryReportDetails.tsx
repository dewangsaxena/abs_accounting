import {
  Badge,
  Box,
  Card,
  CardBody,
  HStack,
  Tooltip,
  VStack,
  useToast,
} from "@chakra-ui/react";
import { _Divider, _Label } from "../../../shared/Components";
import { FcInfo } from "react-icons/fc";
import { numberFont } from "../../../shared/style";
import { HTTPService } from "../../../service/api-client";
import { create } from "zustand";
import { formatNumberWithDecimalPlaces } from "../../../shared/functions";
import { CiTimer } from "react-icons/ci";

// Http Service
const httpService = new HTTPService();

// Summary Report Details
interface SummaryReportDetails {
  cogs: number;
  cogsMargin: number;
  netIncome: number;
  totalRevenue: number;
  salesReturn: number;
  profitMargin: number;
  receiptPayments: number;
  receiptDiscount: number;
}

// Summary Report Details Store
interface SummaryReportDetailsStore extends SummaryReportDetails {
  fetch: () => any;
}

export const summaryReportDetails = create<SummaryReportDetailsStore>(
  (_, __) => ({
    cogs: 0,
    cogsMargin: 0,
    netIncome: 0,
    totalRevenue: 0,
    salesReturn: 0,
    profitMargin: 0,
    receiptPayments: 0,
    receiptDiscount: 0,
    fetch: async () => {
      httpService.fetch<SummaryReportDetails>({}, "stats");
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

const InventoryReport = () => {
  const {
    cogs,
    cogsMargin,
    totalRevenue,
    salesReturn,
    profitMargin,
    netIncome,
    receiptPayments,
    receiptDiscount,
    fetch,
  } = summaryReportDetails((state) => ({
    cogs: state.cogs,
    cogsMargin: state.cogsMargin,
    totalRevenue: state.totalRevenue,
    salesReturn: state.salesReturn,
    profitMargin: state.profitMargin,
    netIncome: state.netIncome,
    receiptPayments: state.receiptPayments,
    receiptDiscount: state.receiptDiscount,
    fetch: state.fetch,
  }));

  fetch().then((res: any) => {
    console.log(res);
  });
  return (
    <VStack align={"start"} width="100%">
      <HStack width="100%">
        <Box width="10vw">
          <Badge bgColor="#04AF70" color="white" letterSpacing={2}>
            Total Revenue
          </Badge>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {formatNumberWithDecimalPlaces(totalRevenue)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Badge colorScheme="red" letterSpacing={2}>
            Sales Return
          </Badge>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          ${" "}
          {salesReturn > 0
            ? "-"
            : "" + formatNumberWithDecimalPlaces(salesReturn)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Cost of Goods Sold">
            <Badge colorScheme="orange" letterSpacing={2}>
              C.O.G.S
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          $ {cogs > 0 ? "-" : "" + formatNumberWithDecimalPlaces(cogs)}
        </_Label>
      </HStack>
      <HStack width="100%">
        <_Divider margin={1} />
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Net income after deducting Sales Returns and C.O.G.S">
            <Badge colorScheme="green" letterSpacing={2}>
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
          $ {formatNumberWithDecimalPlaces(netIncome)}
        </_Label>
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Profit Margin Adjusted for Sales Return">
            <Badge color="#5D3FD3" bgColor="#CCCCFF" letterSpacing={2}>
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
          <b>{formatNumberWithDecimalPlaces(profitMargin)} %</b>
        </_Label>
      </HStack>
      <HStack width="100%">
        <_Divider margin={1} />
      </HStack>
      <HStack>
        <Box width="10vw">
          <Tooltip label="Receipts Payments received">
            <Badge color="#5D3FD3" bgColor="#B5D33F" letterSpacing={2}>
              Receipt Payments
            </Badge>
          </Tooltip>
        </Box>
        <_Label fontFamily={numberFont} fontSize="0.8em" letterSpacing={2}>
          <b>$ {formatNumberWithDecimalPlaces(receiptPayments)}</b>
        </_Label>
      </HStack>
    </VStack>
  );
};

const StatsUpdateMsg = () => {
  return (
    <HStack>
      <CiTimer color="purple" />
      <_Label fontSize="0.6em" fontFamily={numberFont}>
        <i>Stats are updated every 15 Minutes.</i>
      </_Label>
    </HStack>
  );
};

const Stats = () => {
  const toast = useToast();
  return (
    <Card>
      <CardBody>
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
