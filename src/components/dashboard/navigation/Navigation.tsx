import {
  HStack,
  Box,
  Text,
  Divider,
  Card,
  CardBody,
  VStack,
  Badge,
  Stack,
  Image,
} from "@chakra-ui/react";
import { GiReceiveMoney } from "react-icons/gi";
import { PiWarehouseFill, PiScalesLight } from "react-icons/pi";
import { PiCurrencyDollarSimpleThin } from "react-icons/pi";
import { HiOutlineDocumentReport } from "react-icons/hi";
import { TbReportMoney } from "react-icons/tb";
import { CiSettings, CiUser } from "react-icons/ci";
import { BiLogOut } from "react-icons/bi";
import { BsLock } from "react-icons/bs";
import { MdOutlineAdminPanelSettings } from "react-icons/md";
import {
  cardConfig,
  dividerConfig,
  navBgColor,
  numberFont,
} from "../../../shared/style";
import {
  SectionHeader,
  _Button,
  _Divider,
  _Label,
} from "../../../shared/Components";
import { interaction } from "../interaction";
import { HTTPService } from "../../../service/api-client";
import { getAttributeFromSession } from "../../../shared/functions";
import abs_logo from "/images/logo.png";
import {
  CLIENT_APP_VERSION,
  MODE_WASH,
  systemConfigMode,
  systemConfigModeColors,
} from "../../../shared/config";
import { FaUserTag } from "react-icons/fa";

// Admin Flag
const isAdmin = getAttributeFromSession("isAdmin") === "true" ? true : false;

/* Select Navigation User Icon */
let navUserIcon: any = null;
if (isAdmin)
  navUserIcon = <MdOutlineAdminPanelSettings fontSize="1.5em" color={"gold"} />;
else if (getAttributeFromSession("isReadOnly") === "true")
  navUserIcon = <BsLock fontSize="1.5em" color={"red"} />;
else navUserIcon = <CiUser fontSize="1.5em" color={"white"} />;

// Http Service
const httpService = new HTTPService();

/* Navigation */
const Navigation = () => {
  const { setVisibility } = interaction();
  return (
    <>
      <Card
        height="98vh"
        bgColor={navBgColor}
        border={"none"}
        variant={cardConfig.variant}
        borderRadius={cardConfig.borderRadius}
      >
        <CardBody padding={1}>
          <VStack alignContent={"center"}>
            <Image height="8vh" width="8vw" src={abs_logo}></Image>
          </VStack>
          <_Divider borderColor={dividerConfig.borderColor} />
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<GiReceiveMoney color="#BC13FE" />}
            label="Customer & Sales"
            onClick={() => {
              setVisibility("CS");
            }}
            bgColor={navBgColor}
          ></_Button>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<PiWarehouseFill color="#cfff04" />}
            label="Inventory & Serv."
            onClick={() => {
              setVisibility("INV");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <SectionHeader
            textAlign="center"
            color="#FFFCFF"
            marginTop={5}
            marginBottom={5}
            fontSize="1.2em"
            letterSpacing={2}
          >
            Reports
          </SectionHeader>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<PiCurrencyDollarSimpleThin color="#32CD32" />}
            label="Income Statement"
            onClick={() => {
              window.open("/income_statement", "_blank");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <_Button
            height={10}
            fontSize="1.3em"
            icon={<HiOutlineDocumentReport color="#D8BFD8" />}
            label="Customer Aged Summary"
            onClick={() => {
              window.open("/customer_aged_summary", "_blank");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<TbReportMoney color="red" />}
            label="Customer Statement"
            onClick={() => {
              window.open("/customer_statement", "_blank");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<FaUserTag color="#b19cd9" />}
            label="Customer Summary"
            onClick={() => {
              window.open("/customer_summary", "_blank");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<PiScalesLight color="#0096FF" />}
            label="Balance Sheet"
            onClick={() => {
              window.open("/balance_sheet", "_blank");
            }}
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>

          <Divider
            marginTop={1}
            borderColor={dividerConfig.borderColor}
            orientation="horizontal"
          ></Divider>
          <Card
            border={"none"}
            marginTop={2}
            bgColor={navBgColor}
            variant={cardConfig.variant}
            borderRadius={cardConfig.borderRadius}
          >
            <CardBody justifyContent={"left"} padding={1}>
              <HStack>
                <Box>{navUserIcon}</Box>
                <Box bgColor="">
                  <VStack
                    spacing="1px"
                    justifyItems={"left"}
                    justifyContent={"left"}
                  >
                    <Box>
                      <Box>
                        <Text
                          fontWeight={"thin"}
                          fontSize="0.8em"
                          color="#FFFCFF"
                          textAlign={"left"}
                          justifyContent={"left"}
                          letterSpacing={1}
                        >
                          {getAttributeFromSession("name")}
                        </Text>
                      </Box>
                      <Box width="100%">
                        <Stack
                          direction={{
                            sm: "column",
                            md: "column",
                            lg: "row",
                          }}
                        >
                          <Badge
                            variant="outline"
                            colorScheme="green"
                            fontSize={"0.6em"}
                            letterSpacing={1}
                          >
                            {getAttributeFromSession("roleDescription")}
                          </Badge>
                          <Badge
                            colorScheme="purple"
                            fontSize={"0.6em"}
                            letterSpacing={2}
                          >
                            {getAttributeFromSession("storeLocation")}
                          </Badge>
                        </Stack>
                      </Box>
                      <Box>
                        <Badge
                          fontSize="0.6em"
                          colorScheme={systemConfigModeColors}
                          letterSpacing={2}
                        >
                          {systemConfigMode === MODE_WASH ? "WASH" : "PARTS"}
                        </Badge>
                      </Box>
                    </Box>
                  </VStack>
                </Box>
              </HStack>
            </CardBody>
          </Card>
          <Divider
            marginTop={1}
            borderColor={dividerConfig.borderColor}
            orientation="horizontal"
          ></Divider>
          <Box>
            <_Button
              height={10}
              fontSize="1.5em"
              icon={<CiSettings color="#29AB87" />}
              label="Settings"
              onClick={() => {
                setVisibility("SET");
              }}
              marginTop={2}
              bgColor={navBgColor}
            ></_Button>
          </Box>
          <_Button
            height={10}
            fontSize="1.5em"
            icon={<BiLogOut color="#FF5349" />}
            label="Logout"
            onClick={() => {
              httpService.update({}, "um_logout");
              localStorage.clear();
              window.location.href = "/login";
            }}
            hoverBgColor="#e46b71"
            marginTop={2}
            bgColor={navBgColor}
          ></_Button>
          <Divider
            marginTop={1}
            borderColor={dividerConfig.borderColor}
            orientation="horizontal"
          ></Divider>
          <VStack align="start" paddingTop={1}>
            <Badge fontSize="0.8em" colorScheme="blue">
              {getAttributeFromSession("storeBusinessName")}
            </Badge>
            <Badge
              color="#5D3FD3"
              bgColor="#CCCCFF"
              fontSize="0.6em"
              letterSpacing={1}
              textTransform={"lowercase"}
              fontFamily={numberFont}
            >
              v{CLIENT_APP_VERSION}
            </Badge>
          </VStack>
        </CardBody>
      </Card>
    </>
  );
};

export default Navigation;
