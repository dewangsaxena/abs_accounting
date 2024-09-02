import {
  Badge,
  Box,
  Card,
  CardBody,
  Center,
  Grid,
  GridItem,
  HStack,
  useToast,
  VStack,
} from "@chakra-ui/react";
import { create } from "zustand";
import { _Button, _Label, HomeNavButton } from "../../shared/Components";
import {
  AttributeType,
  AUTO_SUGGEST_MIN_INPUT_LENGTH,
  UNKNOWN_SERVER_ERROR_MSG,
} from "../../shared/config";
import { useState } from "react";
import { APIResponse, HTTPService } from "../../service/api-client";
import { buildSearchListForItem, showToast } from "../../shared/functions";
import AutoSuggest from "react-autosuggest";
import { AutoSuggestStyle, navBgColor } from "../../shared/style";
import DatePicker from "react-datepicker";
import { FaSearchengin } from "react-icons/fa6";
import { ItemDetails } from "./itemStore";
import { Spinner } from "@chakra-ui/react";

// HTTP Service.
const httpService = new HTTPService();

// Yearly Report
interface YearlyReport {
  cogs: number;
  profit: number;
  sellingCost: number;
  quantity: number;
}

// Report
interface Report {
  year: AttributeType<YearlyReport>;
}

// Frequency
interface FrequencyDetails {
  partId: number | null | undefined;
  identifier: string | null;
  description: string | null;
  startDate: Date | null | undefined;
  endDate: Date | null | undefined;
  report: Report | null;
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
      set({ partId: value.id });
      set({ identifier: value.identifier });
      set({ description: value.description });
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

  // Load options for Itme
  const loadOptionsForItem = (searchTerm: string) => {
    httpService
      .fetch<ItemDetails[]>({ term: searchTerm }, "inv_fetch")
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
                      setDetail("partId", null);
                    }
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

// Report
const Report = () => {
  const { identifier, description, report } = frequencyDetailsStore(
    (state) => ({
      identifier: state.identifier,
      description: state.description,
      report: state.report,
    })
  );
  return (
    <Card borderLeftColor={"purple"} borderLeftWidth={5} borderRadius={0}>
      <CardBody>
        <HStack>
          <Badge
            padding={2}
            bgColor="transparent"
            borderLeftColor="green"
            borderLeftWidth={5}
            borderRadius={0}
            fontSize={"0.8em"}
            letterSpacing={2}
          >
            {identifier}
          </Badge>
          <_Label fontSize={"0.8em"} letterSpacing={2}>
            <i>{description}</i>
          </_Label>
        </HStack>
      </CardBody>
    </Card>
  );
};

const ItemFrequency = () => {
  const [loadingState, setLoadingState] = useState<boolean>(false);
  const { partId } = frequencyDetailsStore((state) => ({
    partId: state.partId,
  }));
  return (
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
              speed="0.8s"
              size="xl"
              thickness="2px"
              color="#10EFA7"
              emptyColor="#EF1058"
            ></Spinner>
          </Center>
        )}
      </GridItem>
    </Grid>
  );
};

export default ItemFrequency;
