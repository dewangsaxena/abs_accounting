import { Card, CardBody, HStack, VStack } from "@chakra-ui/react";
import { memo, useState } from "react";
import {
  _Button,
  _Divider,
  _Label,
  HomeNavButton,
} from "../../../shared/Components";
import { AttributeType } from "../../../shared/config";
import DatePicker from "react-datepicker";
import { create } from "zustand";
import { HTTPService } from "../../../service/api-client";
import { FaSearchengin } from "react-icons/fa6";
import { navBgColor } from "../../../shared/style";

// Http Service
const httpService = new HTTPService();

// Customer List
interface CustomerList {
  lastPurchaseDate: Date | undefined;
  fetch: () => any;
}

// Customer List Store
const customerListStore = create<CustomerList>((set, get) => ({
  lastPurchaseDate: undefined,
  fetch: () => {},
}));

// Search Filter
const SearchFilter = memo(() => {
  const labelStyleConfig: AttributeType = {
    fontSize: "0.8em",
  };

  const [lastPurchaseDate, setLastPurchaseDate] = useState<Date | undefined>(
    undefined
  );

  return (
    <Card bgColor="#EEF5FF">
      <CardBody padding={2}>
        <HomeNavButton />
        <VStack align="start">
          <HStack>
            <_Label {...labelStyleConfig}>
              Search by Last Purchase Date till:
            </_Label>
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
          <_Divider margin={0} />
          <_Button
            label="Search Client(s)"
            icon={<FaSearchengin />}
            onClick={() => {}}
            color="#BDB5D5"
            bgColor={navBgColor}
            fontSize={"1.5em"}
          ></_Button>
        </VStack>
      </CardBody>
    </Card>
  );
});

// Customer List
const CustomerList = memo(() => {
  return (
    <HStack>
      <SearchFilter />
    </HStack>
  );
});

export default CustomerList;
