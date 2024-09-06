import { Box, Card, CardBody, HStack, VStack } from "@chakra-ui/react";
import { APP_HOST } from "../../../shared/config";
import {
  HomeNavButton,
  _Button,
  _Divider,
  _Input,
  _Label,
  _Select,
} from "../../../shared/Components";
import { useState } from "react";
import DatePicker from "react-datepicker";
import { Switch } from "@chakra-ui/react";
import { FcMoneyTransfer } from "react-icons/fc";
import { redirectIfInvalidSession } from "../../../shared/functions";
import { IoInformationCircleOutline } from "react-icons/io5";
import { BsFiletypeCsv } from "react-icons/bs";

const CustomerAgedSummary = () => {
  redirectIfInvalidSession();
  const currentStore = parseInt(localStorage.getItem("storeId") || "0");
  const [date, setDate] = useState<Date>(new Date());
  const [sortAscending, setSortAscending] = useState<number>(0);
  const [fetchHistoricalRecord, setFetchHistoricalRecord] = useState<number>(0);

  /**
   * This method will generate customer aged summary.
   * @param isCSV
   */
  const clickHandler = (isCSV: boolean = false) => {
    const urlWithParam = new URL(APP_HOST + "/api.php");
    urlWithParam.searchParams.append("op", "customer_aged_summary");
    urlWithParam.searchParams.append("storeId", currentStore.toString());
    let year = date.getFullYear();
    let month: any = date.getMonth() + 1;
    if (month < 10) month = `0${month}`;
    let day: any = date.getDate();
    if (day < 10) day = `0${day}`;
    urlWithParam.searchParams.append("tillDate", `${year}-${month}-${day}`);
    urlWithParam.searchParams.append("sortAscending", sortAscending.toString());
    urlWithParam.searchParams.append(
      "fetchHistoricalRecord",
      fetchHistoricalRecord.toString()
    );
    urlWithParam.searchParams.append("isCSV", isCSV ? "1" : "0");
    window.open(urlWithParam);
  };

  return (
    <>
      <Card>
        <CardBody>
          <VStack align={"start"}>
            <Box width="20%">
              <HomeNavButton />
            </Box>
            <Box width="100%">
              <HStack>
                <_Label fontSize={"0.8em"}>Generate Summary till:</_Label>
                <DatePicker
                  wrapperClassName="datepicker_style"
                  dateFormat={"MM/dd/yyyy"}
                  placeholderText="Txn. Date"
                  selected={date}
                  onChange={(date: any) => {
                    setDate(date);
                  }}
                  closeOnScroll={true}
                  maxDate={new Date()}
                />
              </HStack>
            </Box>
            <Box width="40%">
              <HStack>
                <_Label fontSize="0.8em">Sort by Lowest Amount owing:</_Label>
                <Switch
                  id="email-alerts"
                  colorScheme="teal"
                  onChange={() => {
                    setSortAscending(sortAscending ^ 1);
                  }}
                />
              </HStack>
            </Box>
            <Box width="100%">
              <HStack>
                <_Label fontSize="0.8em">Fetch Historical Record?</_Label>
                <IoInformationCircleOutline color="blue" />
                <_Label color="red" fontSize="0.8em">
                  This record is NOT up-to-date and will NOT be updated. It is
                  merely for historical record.
                </_Label>
                <Switch
                  id="email-alerts"
                  colorScheme="teal"
                  onChange={() => {
                    setFetchHistoricalRecord(fetchHistoricalRecord ^ 1);
                  }}
                />
              </HStack>
            </Box>
            <HStack width="100%">
              <Box width="25%">
                <_Button
                  icon={<FcMoneyTransfer />}
                  color="#90EE90"
                  bgColor="black"
                  fontSize="1.2em"
                  label="Fetch Customer Aged Summary"
                  onClick={() => {
                    clickHandler(false);
                  }}
                ></_Button>
              </Box>
              <Box width="25%">
                <_Button
                  icon={<BsFiletypeCsv />}
                  color="#ADD8E6"
                  bgColor="black"
                  fontSize="1.2em"
                  label="Download CSV"
                  onClick={() => {
                    clickHandler(true);
                  }}
                ></_Button>
              </Box>
            </HStack>
          </VStack>
        </CardBody>
      </Card>
    </>
  );
};

export default CustomerAgedSummary;
