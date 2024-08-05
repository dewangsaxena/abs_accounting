import { Box, Card, CardBody, HStack, Select } from "@chakra-ui/react";
import { navBgColor, numberFont, selectConfig } from "../../../shared/style";
import { APP_HOST, MONTHS, Stores } from "../../../shared/config";
import {
  HomeNavButton,
  _Button,
  _Divider,
  _Input,
  _Select,
} from "../../../shared/Components";
import { useState } from "react";
import { LiaBalanceScaleSolid } from "react-icons/lia";
import { checkForValidSession } from "../../../shared/functions";

const BalanceSheet = () => {
  checkForValidSession();
  const currentStore = parseInt(localStorage.getItem("storeId") || "0");
  const currentDate = new Date();
  const [store, setStore] = useState<number>(currentStore);
  const [month, setMonth] = useState<number>(currentDate.getMonth() + 1);

  const currentYear = currentDate.getFullYear();
  const [year, setYear] = useState<number>(currentYear);

  // Stores
  let stores = Stores.getActiveStores();
  return (
    <>
      <Card>
        <CardBody>
          <Box width="20%">
            <HomeNavButton />
          </Box>
          <HStack>
            <Box width="10%">
              <Select
                defaultValue={store}
                size="xs"
                variant={selectConfig.variant}
                borderRadius={selectConfig.borderRadius}
                fontSize={selectConfig.fontSize}
                borderBottomColor={selectConfig.borderColor}
                borderBottomWidth={1}
                onChange={(event: any) => {
                  if (event) {
                    setStore(parseInt(event.target.value));
                  }
                }}
              >
                {Object.keys(stores).map((store, index) => (
                  <option key={index} value={store}>
                    {Stores.names[parseInt(store)]}
                  </option>
                ))}
              </Select>
            </Box>
            <Box width="10%">
              <_Select
                value={month}
                fontSize={"0.75em"}
                options={MONTHS}
                onChange={(event: any) => {
                  setMonth(parseInt(event.target.value));
                }}
              ></_Select>
            </Box>
            <Box width="10%">
              <_Input
                defaultValue={year}
                type="number"
                fontSize="0.8em"
                fontFamily={numberFont}
                onBlur={(event: any) => {
                  if (event && event.target) {
                    let year = parseInt(event.target.value);
                    setYear(year);
                  } else setYear(currentYear);
                }}
              ></_Input>
            </Box>
            <Box width="20%">
              <_Button
                icon={<LiaBalanceScaleSolid color="#90EE90" />}
                color="#90EE90"
                bgColor={navBgColor}
                label="Fetch Balance Sheet"
                fontSize="1.2em"
                onClick={() => {
                  const urlWithParam = new URL(APP_HOST + "/api.php");
                  urlWithParam.searchParams.append("op", "balance_sheet");
                  urlWithParam.searchParams.append("storeId", store.toString());
                  urlWithParam.searchParams.append("month", month.toString());
                  urlWithParam.searchParams.append("year", year.toString());
                  window.open(urlWithParam);
                }}
              ></_Button>
            </Box>
          </HStack>
        </CardBody>
      </Card>
    </>
  );
};

export default BalanceSheet;
