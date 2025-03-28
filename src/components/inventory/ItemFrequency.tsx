import {
  Badge,
  Box,
  Card,
  CardBody,
  Center,
  Grid,
  GridItem,
  HStack,
  Tooltip,
  useToast,
  VStack,
} from "@chakra-ui/react";
import { create } from "zustand";
import {
  _Button,
  _Divider,
  _Label,
  _Select,
  HomeNavButton,
} from "../../shared/Components";
import {
  AttributeType,
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  MODE_PARTS,
  MONTHS,
  systemConfigMode,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../shared/config";
import { useState } from "react";
import { APIResponse, HTTPService } from "../../service/api-client";
import {
  buildSearchListForItem,
  calculateCOGSMargin,
  calculateProfitMargin,
  formatNumber,
  formatNumberWithDecimalPlaces,
  isSessionActive,
  redirectIfInvalidSession,
  showToast,
} from "../../shared/functions";
import AutoSuggest from "react-autosuggest";
import { AutoSuggestStyle, navBgColor, numberFont } from "../../shared/style";
import DatePicker from "react-datepicker";
import { FaSearchengin } from "react-icons/fa6";
import { ItemDetails, itemStore } from "./itemStore";
import { Spinner } from "@chakra-ui/react";
import { CiSettings } from "react-icons/ci";
import { FaRegCalendarAlt } from "react-icons/fa";

// HTTP Service.
const httpService = new HTTPService();

// Report Report
interface ReportDetail {
  cogs: number;
  profit: number;
  totalSold: number;
  quantity: number;
}

// Month
type Month = AttributeType<ReportDetail>;

// Report Type
type Report = AttributeType<Month>;

// Frequency
interface FrequencyDetails {
  partId: number | null | undefined;
  identifier: string | null;
  description: string | null;
  startDate: Date | null | undefined;
  endDate: Date | null | undefined;
  report: Report | null;
  existingQuantity: number;
}

interface _FrequencyDetails extends FrequencyDetails {
  fetch: () => any;
  setDetail: (detailName: string, value: any) => void;
  setReport: (data: Report | null) => void;
}

// Freuqnecy Detail Store
export const frequencyDetailsStore = create<_FrequencyDetails>((set, get) => ({
  partId: null,
  identifier: null,
  description: null,
  startDate: null,
  endDate: null,
  report: null,
  existingQuantity: 0,
  fetch: async () => {
    let payload = {
      partId: get().partId,
      startDate: get().startDate,
      endDate: get().endDate,
    };
    return httpService.fetch(payload, "item_frequency");
  },
  setDetail: (detailName: string, value: any) => {
    if (detailName === "itemDetails") {
      if (value) {
        set({ partId: value.id });
        set({ identifier: value.identifier });
        set({ description: value.description });
        set({ existingQuantity: value.quantity });
      } else {
        set({ partId: null });
        set({ identifier: null });
        set({ description: null });
        set({ existingQuantity: 0 });
      }
    } else if (detailName === "startDate") set({ startDate: value });
    else if (detailName === "endDate") set({ endDate: value });
  },
  setReport: (report: Report | null) => {
    set({ report: report });
  },
}));

// Search Filter
const SearchFilter = ({
  loadingState,
  setLoadingState,
}: {
  loadingState: boolean;
  setLoadingState: any;
}) => {
  const { startDate, endDate, fetch, setDetail, setReport } =
    frequencyDetailsStore((state) => ({
      startDate: state.startDate,
      endDate: state.endDate,
      fetch: state.fetch,
      setDetail: state.setDetail,
      setReport: state.setReport,
    }));

  const labelConfig: AttributeType = {
    fontSize: "0.7em",
  };

  const [selectedItem, setSelectedItem] = useState<string>("");
  const [itemSuggestions, setItemSuggestions] = useState<any>([]);

  // Toast Handle
  const toast = useToast();

  // Fetch Item
  const { fetch: fetchItem } = itemStore();

  // Load options for Itme
  const loadOptionsForItem = (searchTerm: string) => {
    fetchItem(searchTerm)
      .then((res: any) => {
        if (res.status === 200) {
          let response: APIResponse<ItemDetails[]> = res.data;
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
      .catch((err: any) => {
        showToast(toast, false, err.message || UNKNOWN_SERVER_ERROR_MSG);
      });
  };

  // Fetch Item Frequency
  const fetchItemFrequency = () => {
    setLoadingState(true);
    fetch()
      .then((res: any) => {
        let response: APIResponse<Report> = res.data;
        if (response.status && response.data) {
          setReport(response.data);
        } else setReport(null);
      })
      .catch((err: any) => {
        showToast(toast, false, err.message || UNKNOWN_SERVER_ERROR_MSG);
      })
      .finally(() => {
        setLoadingState(false);
      });
  };

  return (
    <Card>
      <CardBody padding={2}>
        <VStack width="100%">
          <HomeNavButton />
          <HStack width="100%">
            <Box width="40%">
              <_Label {...labelConfig}>Part Identifier:</_Label>
            </Box>
            <Box width="60%">
              <AutoSuggest
                suggestions={itemSuggestions}
                onSuggestionsClearRequested={() => setItemSuggestions([])}
                onSuggestionsFetchRequested={({ value }) => {
                  if (value.length < AUTO_SUGGEST_MIN_INPUT_LENGTH) return;
                  loadOptionsForItem(value);
                }}
                onSuggestionSelected={(_: any, { suggestionIndex }) => {
                  setDetail(
                    "itemDetails",
                    itemSuggestions[suggestionIndex].value
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
                      setDetail("itemDetails", null);
                    }
                    setReport(null);
                  },
                  disabled: false,
                }}
                highlightFirstSuggestion={true}
              ></AutoSuggest>
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="40%">
              <_Label {...labelConfig}>Start Date:</_Label>
            </Box>
            <Box width="60%">
              <DatePicker
                wrapperClassName="datepicker_style"
                dateFormat={"MM/dd/yyyy"}
                placeholderText="Start Date"
                selected={startDate}
                onChange={(date: any) => {
                  setDetail("startDate", date ? date : undefined);
                }}
                closeOnScroll={true}
                maxDate={new Date()}
              />
            </Box>
          </HStack>
          <HStack width="100%">
            <Box width="40%">
              <_Label {...labelConfig}>End Date:</_Label>
            </Box>
            <Box width="60%">
              <DatePicker
                wrapperClassName="datepicker_style"
                dateFormat={"MM/dd/yyyy"}
                placeholderText="End Date"
                selected={endDate}
                onChange={(date: any) => {
                  setDetail("endDate", date ? date : undefined);
                }}
                closeOnScroll={true}
                maxDate={new Date()}
              />
            </Box>
          </HStack>
          <_Button
            isDisabled={loadingState}
            onClick={fetchItemFrequency}
            icon={<FaSearchengin />}
            label="Fetch Frequency"
            color="#BDB5D5"
            bgColor={navBgColor}
            fontSize="1.3em"
          ></_Button>
        </VStack>
      </CardBody>
    </Card>
  );
};

/**
 * Report Card
 * @param year
 * @returns
 */
const ReportCard = ({ year }: { year: number }) => {
  const { report } = frequencyDetailsStore((state) => ({
    report: state.report,
  }));

  // Stats
  let cogs: number = 0;
  let profit: number = 0;
  let totalSold: number = 0;
  let quantity: number = 0;
  if (report) {
    let currentMonth: number = 0;
    let month: string[] = Object.keys(report[year] || []);
    for (let i = 0; i < month.length; ++i) {
      currentMonth = parseInt(month[i]);
      cogs += report[year][currentMonth].cogs;
      profit += report[year][currentMonth].profit;
      totalSold += report[year][currentMonth].totalSold;
      quantity += report[year][currentMonth].quantity;
    }
  }

  const badgeStyleConfig: AttributeType = {
    width: "100%",
    textAlign: "right",
    fontFamily: numberFont,
    letterSpacing: 2,
    borderRadius: 0,
  };

  return (
    <Card borderRadius={0} width="80%">
      <CardBody padding={2}>
        <VStack align={"start"}>
          <HStack>
            <FaRegCalendarAlt color="purple" fontSize="1em" />
            <Badge
              colorScheme="green"
              borderRadius={0}
              fontSize="0.9em"
              letterSpacing={5}
              fontFamily={numberFont}
            >
              YTD Details for {year}
            </Badge>
          </HStack>
          <_Divider margin={2} />

          <Box width="80%">
            <HStack width="100%">
              <Box width="50%">
                <_Label {...labelStyleConfig}>Total Purchased:</_Label>
              </Box>
              <Box width="30%">
                <Badge {...badgeStyleConfig} bgColor="purple.100">
                  $ {formatNumberWithDecimalPlaces(totalSold, 2)}
                </Badge>
              </Box>
            </HStack>
            <HStack width="100%">
              <Box width="50%">
                <_Label {...labelStyleConfig}>Profit / Profit Margin:</_Label>
              </Box>
              <Box width="30%">
                <Badge {...badgeStyleConfig} colorScheme="green">
                  $ {formatNumberWithDecimalPlaces(profit, 2)} /{" "}
                  {formatNumberWithDecimalPlaces(
                    calculateProfitMargin(totalSold, cogs),
                    2
                  )}{" "}
                  %
                </Badge>
              </Box>
            </HStack>
            <HStack width="100%">
              <Box width="50%">
                <_Label {...labelStyleConfig}>C.O.G.S / C.O.G.S Margin:</_Label>
              </Box>
              <Box width="30%">
                <Badge {...badgeStyleConfig} colorScheme="orange">
                  $ {formatNumberWithDecimalPlaces(cogs, 2)} /{" "}
                  {formatNumberWithDecimalPlaces(
                    calculateCOGSMargin(totalSold, cogs),
                    2
                  )}{" "}
                  %
                </Badge>
              </Box>
            </HStack>
            <HStack width="100%">
              <Box width="50%">
                <_Label {...labelStyleConfig}>Quantity Sold:</_Label>
              </Box>
              <Box width="30%">
                <Badge {...badgeStyleConfig} colorScheme="teal">
                  {formatNumberWithDecimalPlaces(quantity, 2)}
                </Badge>
              </Box>
            </HStack>
          </Box>
          <_Divider margin={2} />
          <BreakDownByMonth year={year} />
        </VStack>
      </CardBody>
    </Card>
  );
};

// Months
const MONTHS_INDEX = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

/**
 * Breakdown by month
 * @param year
 * @returns
 */
const BreakDownByMonth = ({ year }: { year: number }) => {
  const { report } = frequencyDetailsStore((state) => ({
    report: state.report,
  }));

  const badgeConfig = {
    letterSpacing: 2,
    fontSize: "0.7em",
    borderRadius: 0,
  };
  const badgeConfig2 = {
    fontSize: "0.75em",
    letterSpacing: 2,
    fontFamily: numberFont,
    borderRadius: 0,
    fontWeight: "normal",
  };

  if (report) {
    return (
      <>
        {MONTHS_INDEX.map((month: number) => {
          let profitMargin: number = calculateProfitMargin(
            report[year][month].totalSold,
            report[year][month].cogs
          );

          let cogsMargin: number = calculateCOGSMargin(
            report[year][month].totalSold,
            report[year][month].cogs
          );
          return (
            <HStack width="100%">
              <Box width="20%">
                <Badge {...badgeConfig} colorScheme="blue" variant="subtle">
                  {MONTHS[month]}
                </Badge>
              </Box>
              <Tooltip label="Total Sold">
                <Badge {...badgeConfig2} colorScheme="white">
                  ${" "}
                  {formatNumberWithDecimalPlaces(
                    report[year][month].totalSold,
                    2
                  )}
                </Badge>
              </Tooltip>
              <_Label>~</_Label>
              <Tooltip label="Profit">
                <Badge {...badgeConfig2} colorScheme="white">
                  ${" "}
                  {formatNumberWithDecimalPlaces(report[year][month].profit, 2)}
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
              <Tooltip label="C.O.G.S">
                <Badge {...badgeConfig2} colorScheme="white">
                  $ {formatNumberWithDecimalPlaces(report[year][month].cogs, 2)}
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
              <Tooltip label="Quantity Sold">
                <Badge
                  {...badgeConfig2}
                  colorScheme="white"
                  fontWeight={"bold"}
                >
                  {formatNumber(report[year][month].quantity, 2)}
                </Badge>
              </Tooltip>
            </HStack>
          );
        })}
      </>
    );
  } else return <></>;
};

// Report
const Report = () => {
  const { identifier, description, existingQuantity, report } =
    frequencyDetailsStore((state) => ({
      identifier: state.identifier,
      description: state.description,
      existingQuantity: state.existingQuantity,
      report: state.report,
    }));

  let yearsKeys: string[] = [];
  let __years: AttributeType = {};

  if (report) {
    yearsKeys = Object.keys(report);
    for (let i = 0; i < yearsKeys.length; ++i) {
      __years[yearsKeys[i]] = parseInt(yearsKeys[i]);
    }
  }

  const [selectedYear, setSelectedYear] = useState<number>(
    yearsKeys.length > 0
      ? parseInt(yearsKeys[yearsKeys.length - 1])
      : new Date().getFullYear()
  );

  return (
    <Card borderLeftColor={"green"} borderLeftWidth={5} borderRadius={0}>
      <CardBody padding={2}>
        <VStack align="start" width="100%">
          <HStack>
            <CiSettings color="green" />
            <Badge
              padding={2}
              colorScheme="transparent"
              color="green.500"
              borderRadius={0}
              fontSize={"0.8em"}
              letterSpacing={2}
              fontFamily={numberFont}
            >
              {identifier}
            </Badge>
            <_Label fontSize={"0.8em"} letterSpacing={2} color="green">
              <i>{description}</i>
            </_Label>
            {systemConfigMode === MODE_PARTS && <>
              <_Label>~</_Label>
              <Tooltip label="Existing Quantity">
                <Badge
                  fontSize="0.8em"
                  letterSpacing={2}
                  fontWeight="bold"
                  bgColor="transparent"
                  fontFamily={numberFont}
                >
                  {existingQuantity}
                </Badge>
              </Tooltip>
              </>
            }
            
          </HStack>
          <_Divider margin={0} />
          {yearsKeys.length > 0 ? (
            <VStack width="100%" align="start">
              <HStack width="100%">
                <Box width="20%">
                  <_Label {...labelStyleConfig}>
                    Show Report for the year:
                  </_Label>
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
              <ReportCard year={selectedYear} />
            </VStack>
          ) : (
            <_Label {...labelStyleConfig}>
              No Record found for this item.
            </_Label>
          )}
        </VStack>
      </CardBody>
    </Card>
  );
};

const labelStyleConfig: AttributeType = {
  fontSize: "0.75em",
  letterSpacing: 2,
  textTransform: "uppercase",
};

const ItemFrequency = () => {
  redirectIfInvalidSession();
  const [loadingState, setLoadingState] = useState<boolean>(false);
  const { partId, report } = frequencyDetailsStore((state) => ({
    partId: state.partId,
    report: state.report,
  }));
  return (
    isSessionActive() && (
      <Grid
        templateAreas={`"filter report"`}
        gridTemplateColumns={"25% 75%"}
        gap={2}
      >
        <GridItem area={"filter"}>
          <SearchFilter
            loadingState={loadingState}
            setLoadingState={setLoadingState}
          />
        </GridItem>
        <GridItem area={"report"}>
          {loadingState && (
            <Center>
              <Spinner
                speed="1s"
                thickness="4px"
                color="#5D3FD3"
                boxSize={"12vh"}
                emptyColor="#CCCCFF"
              />
            </Center>
          )}
          {partId && loadingState === false && report && <Report />}
        </GridItem>
      </Grid>
    )
  );
};

export default ItemFrequency;
