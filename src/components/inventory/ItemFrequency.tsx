import {
  Box,
  Card,
  CardBody,
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

// HTTP Service.
const httpService = new HTTPService();

// Report
interface Report {
  year: AttributeType;
}

// Frequency
interface FrequencyDetails {
  partId: number | null | undefined;
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
    if (detailName === "partId") set({ partId: value });
    else if (detailName === "startDate") set({ startDate: value });
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
      .catch((_: any) => {});
  };

  // Click Handler
  const onClickHandler = () => {
    fetch().then((res: any) => {
      let response: APIResponse<Report> = res.data;
      if (response.status && response.data) {
        setReport(response.data);
      } else setReport(null);
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
                    "partId",
                    itemSuggestions[suggestionIndex].value.id
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
            onClick={onClickHandler}
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

const ItemFrequency = () => {
  const [loadingState, setLoadingState] = useState<boolean>(false);
  return (
    <HStack>
      <Box width="25%">
        <SearchFilter
          loadingState={loadingState}
          setLoadingState={setLoadingState}
        />
      </Box>
    </HStack>
  );
};

export default ItemFrequency;
