import {
  Box,
  Card,
  CardBody,
  FormControl,
  Input,
  InputGroup,
  InputLeftElement,
  Select,
  VStack,
  Image,
  HStack,
  Divider,
  useToast,
  Badge,
} from "@chakra-ui/react";
import { useRef, useState } from "react";
import {
  inputConfig,
  selectConfig,
  dividerConfig,
  navBgColor,
  iconColor,
  numberFont,
} from "../../shared/style";
import { BiSolidLogIn } from "react-icons/bi";
import { MdAccountCircle, MdVisibility, MdVisibilityOff } from "react-icons/md";
import {
  APP_VERSION,
  MODE_WASH,
  Stores,
  UNKNOWN_SERVER_ERROR_MSG,
  accessLevels,
  readOnlyAccessLevel,
  systemConfigMode,
  systemConfigModeColors,
} from "../../shared/config";
import abs_logo from "/images/logo.png";
import { _Button, _Input } from "../../shared/Components";
import { LoginDetails, loginStore } from "./store";
import { APIResponse } from "../../service/api-client";
import { encrypt, showToast } from "../../shared/functions";
import {
  ProfitMarginsResponse,
  setProfitMargins,
} from "../inventory/profitMarginStore";

const Login = () => {
  if (localStorage.getItem("isSessionActive") !== null) {
    window.location.href = "/";
  }

  // States
  const [hidePassword, setHidePassword] = useState(true);
  const [loading, setLoadingState] = useState(false);

  // Toast
  const toast = useToast();

  // Refs
  const usernameRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const storeIdRef = useRef<HTMLSelectElement>(null);

  // Store
  const { loginHandler } = loginStore();

  // Handler
  const onClickHandler = () => {
    localStorage.clear();
    setLoadingState(true);
    const username = usernameRef.current?.value;
    const password = passwordRef.current?.value;
    const storeId = parseInt(storeIdRef.current?.value || "1");
    loginHandler(username, password, storeId)
      .then((_res: any) => {
        let _response: APIResponse<any> = _res.data;
        showToast(
          toast,
          _response.status === true ? true : false,
          _response.status !== true
            ? _response.message || UNKNOWN_SERVER_ERROR_MSG
            : ""
        );
        if (_response.status === true && _response.data !== undefined) {
          let response: LoginDetails = _response.data;

          // Session Data
          let sessionData: { [key: string]: any } = {};

          // Access Level
          let accessLevel = response.accessLevel;
          sessionData["isAdmin"] = accessLevel === 0 ? "1" : "0";
          sessionData["userId"] = `${response.id}`;
          sessionData["name"] = `${response.name}`;
          sessionData["accessLevel"] = response.accessLevel.toString();
          sessionData["storeLocation"] = response.storeDetails.location;
          sessionData["storeBusinessName"] = response.storeDetails.businessName;
          sessionData["gstHSTRaxRate"] = response.storeDetails.gstHSTTaxRate;
          sessionData["pstTaxRate"] = response.storeDetails.pstTaxRate;
          sessionData["roleDescription"] = accessLevels[accessLevel];
          sessionData["isReadOnly"] =
            accessLevel === readOnlyAccessLevel ? "1" : "0";
          sessionData["sessionId"] = response.sessionId;
          sessionData["sessionToken"] = response.sessionToken;

          /* Cipher Key This Store */
          let cipherKeyThisStore: string =
            response.storeDetails.cipherKeyThisStore;

          localStorage.setItem("cipherKeyThisStore", cipherKeyThisStore);

          let sessionCipherData: string | null = encrypt(
            JSON.stringify(sessionData),
            cipherKeyThisStore
          );

          if (sessionCipherData !== null)
            localStorage.setItem("sessionData", sessionCipherData);

          /* Store Id */
          localStorage.setItem("storeId", `${response.storeDetails.id}`);

          /* Session Active Flag */
          localStorage.setItem("isSessionActive", "1");

          // CSRF Token
          localStorage.setItem("csrfToken", response.csrfToken);

          // Set Profit Margins
          let profitMarginResponse: ProfitMarginsResponse =
            response.profitMargins;
          setProfitMargins(
            profitMarginResponse.profitMargins,
            profitMarginResponse.lastModifiedTimestamp
          );

          // Redirect to Dashboard.
          window.location.href = "/";
        }
      })
      .catch((error: any) => {
        showToast(toast, false, error.message);
      })
      .finally(function () {
        setLoadingState(false);
      });
  };

  // Stores
  const stores: any = Stores.getActiveStores();

  return (
    <>
      <Box marginTop="25vh" marginLeft={{ lg: "30vw", sm: "25vw" }}>
        <Card
          width={{ sm: "50vw", lg: "30vw" }}
          /* https://getcssscan.com/css-box-shadow-examples */
          boxShadow={` rgba(0, 0, 0, 0.4) 0px 2px 4px, rgba(0, 0, 0, 0.3) 0px 7px 13px -3px, rgba(0, 0, 0, 0.2) 0px -3px 0px inset;`}
        >
          <CardBody>
            <HStack
              height={"25vh"}
              spacing={6}
              divider={
                <Divider
                  borderColor={dividerConfig.borderColor}
                  orientation="vertical"
                ></Divider>
              }
            >
              <Image width={"10vw"} height="10vh" src={abs_logo}></Image>
              <VStack spacing={5} align="end">
                <FormControl>
                  <InputGroup>
                    <InputLeftElement
                      transform={"translateY(-20%)"}
                      children={<MdAccountCircle color={iconColor} />}
                    ></InputLeftElement>
                    <Input
                      ref={usernameRef}
                      placeholder="Username"
                      type="text"
                      size={inputConfig.size}
                      fontSize={inputConfig.fontSize}
                      variant={inputConfig.variant}
                      textAlign={inputConfig.textAlign}
                      borderRadius={inputConfig.borderRadius}
                      borderBottomColor={inputConfig.borderColor}
                      borderLeftWidth={1}
                    />
                  </InputGroup>
                </FormControl>
                <FormControl>
                  <InputGroup>
                    <InputLeftElement
                      transform={"translateY(-20%)"}
                      onClick={() => setHidePassword(!hidePassword)}
                      children={
                        hidePassword ? (
                          <MdVisibility color={iconColor} />
                        ) : (
                          <MdVisibilityOff color={iconColor} />
                        )
                      }
                    ></InputLeftElement>
                    <Input
                      ref={passwordRef}
                      placeholder="Password"
                      type={hidePassword ? "password" : "text"}
                      size={inputConfig.size}
                      fontSize={inputConfig.fontSize}
                      variant={inputConfig.variant}
                      textAlign={inputConfig.textAlign}
                      borderRadius={inputConfig.borderRadius}
                      borderBottomColor={inputConfig.borderColor}
                      borderLeftWidth={1}
                    />
                  </InputGroup>
                </FormControl>
                <FormControl>
                  <InputGroup>
                    <Select
                      ref={storeIdRef}
                      placeholder="Select Store"
                      size="xs"
                      variant={selectConfig.variant}
                      borderRadius={selectConfig.borderRadius}
                      fontSize={selectConfig.fontSize}
                      borderBottomColor={selectConfig.borderColor}
                      borderBottomWidth={1}
                    >
                      {Object.keys(stores).map((store, index) => (
                        <option
                          key={index}
                          value={store}
                          disabled={store == "1"}
                        >
                          {Stores.names[parseInt(store)]}
                        </option>
                      ))}
                    </Select>
                  </InputGroup>
                </FormControl>
                <FormControl>
                  <_Button
                    justifyContent="left"
                    isLoading={loading}
                    loadingText="Loading..."
                    bgColor={navBgColor}
                    icon={<BiSolidLogIn color={iconColor} />}
                    label="Login"
                    fontSize="1.2em"
                    size="sm"
                    onClick={onClickHandler}
                  ></_Button>
                </FormControl>
                <HStack>
                  <Badge
                    colorScheme={systemConfigModeColors}
                    fontSize="0.6em"
                    letterSpacing={1}
                    textTransform={"uppercase"}
                  >
                    {systemConfigMode === MODE_WASH ? "WASH" : "PARTS"}
                  </Badge>
                  <Badge
                    color="#5D3FD3"
                    bgColor="#CCCCFF"
                    fontSize="0.6em"
                    letterSpacing={1}
                    textTransform={"lowercase"}
                    fontFamily={numberFont}
                  >
                    v{APP_VERSION}
                  </Badge>
                </HStack>
              </VStack>
            </HStack>
          </CardBody>
        </Card>
      </Box>
    </>
  );
};

export default Login;
