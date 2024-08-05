import { ReactNode, useRef, useState } from "react";
import { Canvas } from "./Shared";
import {
  Badge,
  Box,
  Divider,
  Stack,
  Switch,
  VStack,
  useToast,
} from "@chakra-ui/react";
import { _Button, _Input, _Label, _Select } from "../../../shared/Components";
import { iconColor, navBgColor } from "../../../shared/style";
import { CiSaveUp2 } from "react-icons/ci";
import { SiOpenaccess } from "react-icons/si";
import { APIResponse, HTTPService } from "../../../service/api-client";
import {
  Stores,
  UNKNOWN_SERVER_ERROR_MSG,
  accessLevels,
} from "../../../shared/config";
import { BiUserPlus } from "react-icons/bi";
import { getAttributeFromSession, showToast } from "../../../shared/functions";

// HTTP Service
const httpService = new HTTPService();

// User Data Type
type UserDataType = { [id: number]: string };
type UserDetailsDataType = {
  [id: number]: { name: string; has_access: number };
};

interface SegmentProps {
  label?: string;
  children?: ReactNode;
  isAdmin?: boolean;
}

const Segment = ({ label, children, isAdmin }: SegmentProps) => {
  return (
    <Box>
      <VStack align={"start"} spacing={5}>
        <Badge
          variant={"outline"}
          colorScheme={isAdmin ? "cyan" : "purple"}
          letterSpacing={2}
        >
          {label}
        </Badge>
        <Box width="50vw">{children}</Box>
      </VStack>
    </Box>
  );
};

const ChangePassword = () => {
  const oldPasswordRef = useRef<any>("");
  const newPasswordRef = useRef<any>("");
  const verifyPasswordRef = useRef<any>("");
  const toast = useToast();
  const [isLoading, setIsLoading] = useState(false);
  const [isDisabled, setIsDisabled] = useState(false);

  return (
    <Box>
      <VStack align="stretch">
        <Box>
          <Stack
            direction={{ sm: "column", md: "column", lg: "row" }}
            spacing={{ sm: 1, md: 1, lg: 10 }}
          >
            <Box width={["100%", "100%", "25%"]}>
              <_Label fontSize="0.8em" >
                Old Password:
              </_Label>
            </Box>
            <Box width={["100%", "100%", "75%"]}>
              <_Input
                type="password"
                ref={oldPasswordRef}
                borderBottomColor={"purple"}
                borderBottomWidth={2}
                borderRadius={0}
                width={"80%"}
                size={"sm"}
                fontSize={"0.8em"}
                letterSpacing={2}
                fontWeight={"normal"}
              ></_Input>
            </Box>
          </Stack>
        </Box>

        <Box>
          <Stack
            direction={{ sm: "column", md: "column", lg: "row" }}
            spacing={{ sm: 1, md: 1, lg: 10 }}
          >
            <Box width={["100%", "100%", "25%"]}>
              <_Label fontSize="0.8em" >
                New Password:
              </_Label>
            </Box>
            <Box width={["100%", "100%", "75%"]}>
              <_Input
                type="password"
                ref={newPasswordRef}
                borderBottomColor={"purple"}
                borderBottomWidth={2}
                borderRadius={0}
                width={"80%"}
                size={"sm"}
                fontSize={"0.8em"}
                letterSpacing={2}
                fontWeight={"normal"}
              ></_Input>
            </Box>
          </Stack>
        </Box>

        <Box>
          <Stack
            direction={{ sm: "column", md: "column", lg: "row" }}
            spacing={{ sm: 1, md: 1, lg: 10 }}
          >
            <Box width={["100%", "100%", "25%"]}>
              <_Label fontSize="0.8em" >
                Verify Password:
              </_Label>
            </Box>
            <Box width={["100%", "100%", "75%"]}>
              <_Input
                type="password"
                ref={verifyPasswordRef}
                borderBottomColor={"purple"}
                borderBottomWidth={2}
                borderRadius={0}
                width={"80%"}
                size={"sm"}
                fontSize={"0.8em"}
                letterSpacing={2}
                fontWeight={"normal"}
              ></_Input>
            </Box>
          </Stack>
        </Box>
        <Box width={"50%"}>
          <_Button
            isDisabled={isDisabled}
            isLoading={isLoading}
            loadingText="Changing..."
            icon={<CiSaveUp2 color={iconColor} />}
            height={"10"}
            width={{ sm: "100%", md: "100%", lg: "40%" }}
            borderRadius={5}
            justifyContent="left"
            fontSize="1.2em"
            bgColor={navBgColor}
            label="Update"
            onClick={() => {
              setIsDisabled(true);
              setIsLoading(true);
              let userId = getAttributeFromSession("userId");
              if (userId !== null) {
                let oldPassword = oldPasswordRef?.current.value.trim();
                let newPassword = newPasswordRef?.current.value.trim();
                let verifyPassword = verifyPasswordRef?.current.value.trim();
                if (newPassword !== verifyPassword) {
                  alert("New and Verify Password does not match.");
                  return;
                } else {
                  httpService
                    .update(
                      {
                        user_id: userId,
                        old_password: oldPassword,
                        new_password: newPassword,
                        for: "self",
                      },
                      "um_update_password"
                    )
                    .then((_res: any) => {
                      let response: APIResponse<any> = _res.data;
                      if (response.status === true) {
                        showToast(toast, true, "Password Updated.");
                      } else {
                        showToast(
                          toast,
                          false,
                          response.message || UNKNOWN_SERVER_ERROR_MSG
                        );
                        setIsDisabled(false);
                      }
                      setIsLoading(false);
                    });
                }
              }
            }}
          ></_Button>
        </Box>
      </VStack>
    </Box>
  );
};

const ChangePasswordForUser = ({ users }: { users: UserDataType }) => {
  const userIdRef = useRef<HTMLSelectElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const toast = useToast();
  const [isLoading, setIsLoading] = useState(false);
  const [isDisabled, setIsDisabled] = useState(false);
  return (
    <Box>
      <Stack
        spacing={{ sm: 10, md: 10, lg: 5 }}
        direction={{ sm: "column", md: "column", lg: "row" }}
      >
        <Box>
          <_Select
            size="sm"
            fontSize="0.8em"
            width={"100%"}
            ref={userIdRef}
            options={users}
          ></_Select>
        </Box>
        <Box width="50%">
          <_Input
            type="password"
            ref={passwordRef}
            borderBottomColor={"purple"}
            borderBottomWidth={2}
            borderRadius={0}
            width={"100%"}
            size={"xs"}
            fontSize={"0.8em"}
            letterSpacing={2}
            fontWeight={"normal"}
            placeholder={"New Password"}
          ></_Input>
        </Box>
        <Box>
          <_Button
            isLoading={isLoading}
            isDisabled={isDisabled}
            icon={<CiSaveUp2 color={iconColor} />}
            height={8}
            size="sm"
            fontSize="1.2em"
            bgColor={navBgColor}
            label="Update"
            onClick={() => {
              setIsDisabled(true);
              setIsLoading(true);
              let userId = userIdRef?.current?.value;
              let newPassword = passwordRef?.current?.value.trim();
              httpService
                .update(
                  {
                    user_id: userId,
                    new_password: newPassword,
                    for: "user",
                  },
                  "um_update_password"
                )
                .then((_res: any) => {
                  let response: APIResponse<any> = _res.data;
                  if (response.status === true) {
                    showToast(toast, true, "Password Updated.");
                  } else {
                    showToast(
                      toast,
                      false,
                      response.message || UNKNOWN_SERVER_ERROR_MSG
                    );
                    setIsDisabled(false);
                  }
                  setIsLoading(false);
                });
            }}
          ></_Button>
        </Box>
      </Stack>
    </Box>
  );
};
const ChangeUserAccess = ({
  users,
  userDetails,
}: {
  users: UserDataType;
  userDetails: UserDetailsDataType;
}) => {
  const userIdRef = useRef<HTMLSelectElement>(null);
  const [revokeAccess, setRevokeAccess] = useState(false);
  const toast = useToast();
  const [isLoading, setIsLoading] = useState(false);
  return (
    <Box>
      <Stack
        spacing={{ sm: 10, md: 10, lg: 5 }}
        direction={{ sm: "column", md: "column", lg: "row" }}
      >
        <Box>
          <_Select
            size="sm"
            fontSize="0.8em"
            width={"100%"}
            ref={userIdRef}
            options={users}
            onChange={() => {
              // Select Current selected user
              let currentUser = parseInt(userIdRef?.current?.value || "0");
              setRevokeAccess(
                userDetails[currentUser]?.has_access || 0 ? false : true
              );
            }}
          ></_Select>
        </Box>
        <Box width="30%">
          <Stack direction={["column", "column", "row"]}>
            <Switch
              isChecked={revokeAccess}
              colorScheme="red"
              size="md"
              onChange={() => {
                setRevokeAccess(!revokeAccess);
              }}
            />
            <Badge
              colorScheme={revokeAccess ? "red" : "green"}
              fontSize="0.8em"
            >
              {revokeAccess ? "ACCESS REVOKED" : "ACCESS ALLOWED"}
            </Badge>
          </Stack>
        </Box>
        <Box>
          <_Button
            isLoading={isLoading}
            loadingText="Changing..."
            icon={<SiOpenaccess color={iconColor} />}
            height={8}
            fontSize="1.2em"
            size="sm"
            bgColor={navBgColor}
            label="Change Status"
            onClick={() => {
              setIsLoading(true);
              let payload = {
                user_id: userIdRef?.current?.value,
                has_access: revokeAccess ? 0 : 1,
              };
              httpService
                .update(payload, "um_update_status")
                .then((res: any) => {
                  let response: APIResponse<void> = res.data;
                  if (response.status === true) {
                    showToast(toast, true, "Status Updated");
                  } else {
                    showToast(
                      toast,
                      false,
                      response.message || UNKNOWN_SERVER_ERROR_MSG
                    );
                  }
                  setIsLoading(false);
                });
            }}
          ></_Button>
        </Box>
      </Stack>
    </Box>
  );
};

const AddUser = () => {
  const usernameRef = useRef<HTMLInputElement>(null);
  const fullNameRef = useRef<HTMLInputElement>(null);
  const passwordRef = useRef<HTMLInputElement>(null);
  const accessLevelRef = useRef<HTMLSelectElement>(null);
  const storeRef = useRef<HTMLSelectElement>(null);
  const toast = useToast();
  const [isDisabled, setIsDisabled] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  return (
    <Box>
      <VStack align="start">
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Label fontSize={"0.8em"}>
              Username:
            </_Label>
          </Box>
          <Box width="60%">
            <_Input
              ref={usernameRef}
              borderBottomColor={"purple"}
              borderBottomWidth={2}
              borderRadius={0}
              width={"100%"}
              size={"sm"}
              fontSize={"0.8em"}
              letterSpacing={2}
              fontWeight={"normal"}
            ></_Input>
          </Box>
        </Stack>
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Label  fontSize={"0.8em"}>
              Sales Rep. Full Name:
            </_Label>
          </Box>
          <Box width="60%">
            <_Input
              ref={fullNameRef}
              borderBottomColor={"purple"}
              borderBottomWidth={2}
              borderRadius={0}
              width={"100%"}
              size={"sm"}
              fontSize={"0.8em"}
              letterSpacing={2}
              fontWeight={"normal"}
            ></_Input>
          </Box>
        </Stack>
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Label fontSize={"0.8em"}>
              Password:
            </_Label>
          </Box>
          <Box width="60%">
            <_Input
              type="password"
              ref={passwordRef}
              borderBottomColor={"purple"}
              borderBottomWidth={2}
              borderRadius={0}
              width={"100%"}
              size={"sm"}
              fontSize={"0.8em"}
              letterSpacing={2}
              fontWeight={"normal"}
            ></_Input>
          </Box>
        </Stack>
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Label fontSize={"0.8em"}>
              Access Level:
            </_Label>
          </Box>
          <Box width="60%">
            <_Select
              ref={accessLevelRef}
              size="sm"
              width={"100%"}
              options={accessLevels}
              fontSize="0.8em"
            ></_Select>
          </Box>
        </Stack>
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Label  fontSize={"0.8em"}>
              Store:
            </_Label>
          </Box>
          <Box width="60%">
            <_Select
              ref={storeRef}
              size="sm"
              width={"100%"}
              options={Stores.names}
              fontSize="0.8em"
            ></_Select>
          </Box>
        </Stack>
        <Stack
          direction={{ sm: "column", md: "column", lg: "row" }}
          spacing={5}
          width={{ sm: "50vw", md: "50vw", lg: "30vw" }}
        >
          <Box width="40%">
            <_Button
              loadingText="Adding..."
              isDisabled={isDisabled}
              isLoading={isLoading}
              icon={<BiUserPlus color={iconColor} />}
              height={8}
              size="sm"
              bgColor={navBgColor}
              label="Add User"
              fontSize="1.2em"
              onClick={() => {
                setIsDisabled(true);
                setIsLoading(true);

                let payload = {
                  username: usernameRef?.current?.value,
                  name: fullNameRef?.current?.value,
                  password: passwordRef?.current?.value,
                  access_level: parseInt(
                    accessLevelRef?.current?.value || "-1"
                  ),
                  store_id: parseInt(storeRef?.current?.value || "-1"),
                };
                httpService.add(payload, "um_add").then((_res: any) => {
                  let response: APIResponse<any> = _res.data;
                  if (response.status === true) {
                    showToast(toast, true, "User Added");
                  } else {
                    showToast(
                      toast,
                      false,
                      response.message || UNKNOWN_SERVER_ERROR_MSG
                    );
                    setIsDisabled(false);
                  }
                  setIsLoading(false);
                });
              }}
            ></_Button>
          </Box>
        </Stack>
      </VStack>
    </Box>
  );
};

const ChangeUserAccessLevel = ({ users }: { users: UserDataType }) => {
  const userRef = useRef<HTMLSelectElement>();
  const accessLevelRef = useRef<HTMLSelectElement>();
  const [isLoading, setIsLoading] = useState(false);
  const toast = useToast();
  return (
    <>
      <VStack alignItems={"start"}>
        <Stack direction={{ sm: "column", md: "column", lg: "row" }}>
          <_Select
            ref={userRef}
            size="sm"
            width="100%"
            options={users}
            fontSize="0.8em"
          ></_Select>
          <_Select
            ref={accessLevelRef}
            size="sm"
            width={"100%"}
            fontSize="0.8em"
            options={{
              0: "Admin",
              1: "Sales Representative",
              2: "Read-Only",
            }}
          ></_Select>
        </Stack>
        <_Button
          isLoading={isLoading}
          loadingText="Changing..."
          icon={<SiOpenaccess color={iconColor} />}
          height={8}
          fontSize="1.2em"
          size="sm"
          width={{ sm: "100%", md: "100%", lg: "40%" }}
          bgColor={navBgColor}
          label="Change Access Level"
          onClick={() => {
            setIsLoading(true);

            let payload = {
              user_id: userRef?.current?.value,
              access_level: accessLevelRef?.current?.value,
            };

            httpService
              .update(payload, "um_change_user_access_level")
              .then((res: any) => {
                let response: APIResponse = res.data;
                if (response.status === true) {
                  showToast(toast, true, "User Access Level Changed.");
                } else {
                  showToast(
                    toast,
                    false,
                    response.message || UNKNOWN_SERVER_ERROR_MSG
                  );
                }
                setIsLoading(false);
              });
          }}
        ></_Button>
      </VStack>
    </>
  );
};

// Flag
let areUsersFetched: boolean = false;

const Settings = () => {
  let [usersData, setUsersData] = useState<UserDetailsDataType>({});
  let [users, setUsers] = useState<UserDataType>({});

  const toast = useToast();

  // Is Admin
  let isAdmin = parseInt(getAttributeFromSession("isAdmin"));

  // Fetch Users
  const fetchUsers = () => {
    httpService.fetch<UserDataType[]>({}, "um_fetch").then((res: any) => {
      let response: APIResponse<any> = res.data;
      if (response.status === true) {
        let userData = response.data;
        let count = userData.length;
        let tempUsersData: UserDetailsDataType = {};
        let tempUsers: UserDataType = {};
        for (let i = 0; i < count; ++i) {
          tempUsersData[userData[i].id] = {
            name: userData[i].name,
            has_access: userData[i].has_access,
          };

          tempUsers[userData[i].id] = userData[i].name;
        }

        setUsers(tempUsers);
        setUsersData(tempUsersData);
        areUsersFetched = true;
      } else {
        showToast(toast, false, response.message || UNKNOWN_SERVER_ERROR_MSG);
      }
    });
  };

  // Fetch Users
  if (areUsersFetched === false) fetchUsers();
  return (
    <Canvas>
      <Segment label="Change Password" children={<ChangePassword />}></Segment>
      <Divider marginTop={[5, 5, 5]} marginBottom={[5, 5, 5]}></Divider>
      {isAdmin === 1 && (
        <>
          <Segment
            isAdmin={true}
            label="Change Password for"
            children={<ChangePasswordForUser users={users} />}
          ></Segment>
          <Divider marginTop={[5, 5, 5]} marginBottom={[5, 5, 5]}></Divider>
          <Segment
            isAdmin={true}
            label="Change User Access for"
            children={
              <ChangeUserAccess users={users} userDetails={usersData} />
            }
          ></Segment>
          <Divider marginTop={[5, 5, 5]} marginBottom={[5, 5, 5]}></Divider>
          <Segment
            isAdmin={true}
            label="Add User"
            children={<AddUser />}
          ></Segment>
          <Divider marginTop={[5, 5, 5]} marginBottom={[5, 5, 5]}></Divider>
          <Segment
            isAdmin={true}
            label="Change User Access Level"
            children={<ChangeUserAccessLevel users={users} />}
          ></Segment>
        </>
      )}
    </Canvas>
  );
};

export default Settings;
