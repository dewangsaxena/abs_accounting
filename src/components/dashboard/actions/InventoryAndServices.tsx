import { ActionGroup, ActionGroupDivider, CanvasGrid } from "./Shared";
import { FiEdit2 } from "react-icons/fi";
import { AiOutlineStock } from "react-icons/ai";
import { IoMdAdd } from "react-icons/io";
import { PiWaveSineDuotone } from "react-icons/pi";
import { HiTrendingDown } from "react-icons/hi";
import {
    iconColor,
    inputConfig,
    navBgColor,
    numberFont,
    selectConfig,
} from "../../../shared/style";
import {
    Badge,
    Box,
    Card,
    CardBody,
    HStack,
    Select,
    VStack,
    useToast,
} from "@chakra-ui/react";
import {
    SectionHeader,
    _Button,
    _Divider,
    _Input,
    _InputLeftElement,
    _Label,
    _Select,
} from "../../../shared/Components";
import { CiDiscount1 } from "react-icons/ci";
import { useState } from "react";
import { BiSolidLock, BiSolidLockOpen } from "react-icons/bi";
import { IoStopCircleOutline } from "react-icons/io5";
import { RxUpdate } from "react-icons/rx";
import {
    ProfitMarginIndex,
    ProfitMarginsResponse,
    getProfitMargins,
    setProfitMargins,
} from "../../inventory/profitMarginStore";
import { getUUID, showToast } from "../../../shared/functions";
import { APIResponse, HTTPService } from "../../../service/api-client";
import { BsPatchCheck } from "react-icons/bs";
import { MdOutlineErrorOutline } from "react-icons/md";
import { CiText } from "react-icons/ci";
import { IoIosAddCircle } from "react-icons/io";
import { FcFullTrash } from "react-icons/fc";
import {
    APP_HOST,
    DEFAULT_PROFIT_MARGIN_KEY,
    MODE_PARTS,
    MODE_WASH,
    Stores,
    UNKNOWN_SERVER_ERROR_MSG,
    systemConfigMode,
} from "../../../shared/config";
import { FcInfo } from "react-icons/fc";
import { FcSalesPerformance } from "react-icons/fc";
import { FcSearch } from "react-icons/fc";

/** Price Margins */
const ProfitMarginsHandler = () => {
    const [isReadOnly, setIsReadOnly] = useState(true);
    const [isLoading, setIsLoading] = useState(false);
    const [wasSuccessful, setSuccessful] = useState(-1);

    // Profit Margins
    const [profitMargins, __setProfitMarginsState] =
        useState<ProfitMarginIndex>({});

    const [itemIdentifierPrefixKeys, setItemIdentifierPrefixKeys] = useState<
        string[]
    >([]);

    // Toast
    const toast = useToast();

    /**
     * Update Price Margins
     */
    const updateProfitMargins = async () => {
        if (!confirm("Do you really want to update?")) return false;

        setIsLoading(true);
        setSuccessful(-1);
        const httpService = new HTTPService();
        httpService
            .update<ProfitMarginIndex>(
                {
                    profitMargins: profitMargins,
                    lastModifiedTimestamp: localStorage.getItem(
                        "profitMarginsLastModifiedTimestamp"
                    ),
                },
                "inv_update_profit_margins"
            )
            .then((res: any) => {
                let response: APIResponse<ProfitMarginsResponse> = res.data;
                if (response.status === true && response.data) {
                    setProfitMargins(
                        response.data.profitMargins,
                        response.data.lastModifiedTimestamp
                    );
                    setIsReadOnly(true);
                    setSuccessful(1);
                } else {
                    showToast(
                        toast,
                        false,
                        response.message || UNKNOWN_SERVER_ERROR_MSG
                    );
                    setSuccessful(0);
                }
            })
            .catch((error: any) => {
                setSuccessful(0);
                showToast(
                    toast,
                    false,
                    error.message || UNKNOWN_SERVER_ERROR_MSG
                );
            })
            .finally(function () {
                setIsLoading(false);
            });
    };

    if (Object.keys(profitMargins).length === 0) {
        let __profitMargins = getProfitMargins();
        __setProfitMarginsState(__profitMargins);
        setItemIdentifierPrefixKeys(Object.keys(__profitMargins));
    }

    // New Item Identifier Prefix
    const [newItemIdentifierPrefix, setNewItemIdentifierPrefix] = useState<
        string | null
    >(null);

    // Add Item Identifier Prefix
    const addItemIdentifierPrefix = () => {
        if (newItemIdentifierPrefix && newItemIdentifierPrefix.length > 0) {
            let __standardProfitMargins = profitMargins;
            __standardProfitMargins[newItemIdentifierPrefix] = 0;
            __setProfitMarginsState(__standardProfitMargins);
            setItemIdentifierPrefixKeys(Object.keys(__standardProfitMargins));
            setNewItemIdentifierPrefix(null);
        }
    };

    // Delete Item Identifier Prefix
    const deleteItemIdentifierPrefix = (prefix: string) => {
        let __standardProfitMargins = profitMargins;
        if (prefix in __standardProfitMargins) {
            delete __standardProfitMargins[prefix];
            __setProfitMarginsState(__standardProfitMargins);
            setItemIdentifierPrefixKeys(Object.keys(__standardProfitMargins));
        }
    };

    return (
        <Card>
            <CardBody>
                <VStack width="100%" alignItems={"left"}>
                    <Box width="100">
                        <HStack>
                            <Box>
                                <SectionHeader
                                    fontSize="0.8em"
                                    letterSpacing={2}
                                >
                                    C.O.G.S MARGINS
                                </SectionHeader>
                                <HStack marginTop={1}>
                                    <FcInfo />
                                    <_Label fontSize="0.8em">
                                        <i>
                                            Add C.O.G.S Margin by Item Prefix.
                                        </i>
                                    </_Label>
                                </HStack>
                            </Box>
                            <Box>
                                {wasSuccessful === 1 ? (
                                    <BsPatchCheck
                                        fontSize="1.5em"
                                        color="#9DE18A"
                                    ></BsPatchCheck>
                                ) : wasSuccessful === 0 ? (
                                    <MdOutlineErrorOutline color="red"></MdOutlineErrorOutline>
                                ) : (
                                    ""
                                )}
                            </Box>
                        </HStack>
                    </Box>
                    <_Divider margin={1}></_Divider>
                    <HStack width="100%">
                        <Box width="50%">
                            <_InputLeftElement
                                defaultValue={""}
                                type="text"
                                isReadOnly={isReadOnly}
                                textTransform={"uppercase"}
                                borderBottomWidth={inputConfig.borderWidth}
                                borderRadius={inputConfig.borderRadius}
                                letterSpacing={inputConfig.letterSpacing}
                                size={inputConfig.size}
                                fontSize={inputConfig.fontSize}
                                leftElement={<CiText color={"purple"} />}
                                onBlur={(event: any) => {
                                    let temp: string =
                                        event.target.value.trim();
                                    if (temp.length > 0)
                                        setNewItemIdentifierPrefix(
                                            event.target.value
                                                .trim()
                                                .toUpperCase()
                                        );
                                    else setNewItemIdentifierPrefix(null);
                                }}
                            ></_InputLeftElement>
                        </Box>
                        <Box width="50%" transform={"translateY(25%)"}>
                            <_Button
                                fontSize="1.2em"
                                isDisabled={isReadOnly}
                                icon={<IoIosAddCircle color="lightblue" />}
                                color="lightblue"
                                bgColor={navBgColor}
                                label="Add"
                                onClick={addItemIdentifierPrefix}
                            ></_Button>
                        </Box>
                    </HStack>
                    <_Divider margin={2} />
                    <Box overflowY={"scroll"} maxHeight="20vh">
                        <VStack align="start">
                            {itemIdentifierPrefixKeys.map((prefix: string) => {
                                return (
                                    <HStack width="100%" key={getUUID()}>
                                        <Box width="30%">
                                            <Badge
                                                letterSpacing={1}
                                                fontSize="0.8em"
                                                colorScheme={
                                                    prefix ===
                                                    DEFAULT_PROFIT_MARGIN_KEY
                                                        ? "yellow"
                                                        : "cyan"
                                                }
                                                variant={
                                                    prefix ===
                                                    DEFAULT_PROFIT_MARGIN_KEY
                                                        ? "outline"
                                                        : "subtle"
                                                }
                                            >
                                                {prefix}
                                            </Badge>
                                        </Box>
                                        <Box
                                            transform={"translateY(-25%)"}
                                            width="50%"
                                        >
                                            <_InputLeftElement
                                                fontFamily={numberFont}
                                                defaultValue={
                                                    profitMargins[prefix]
                                                }
                                                type="number"
                                                isReadOnly={isReadOnly}
                                                borderBottomColor={
                                                    inputConfig.borderColor
                                                }
                                                borderBottomWidth={
                                                    inputConfig.borderWidth
                                                }
                                                borderRadius={
                                                    inputConfig.borderRadius
                                                }
                                                letterSpacing={
                                                    inputConfig.letterSpacing
                                                }
                                                size={inputConfig.size}
                                                fontSize={inputConfig.fontSize}
                                                leftElement={
                                                    <CiDiscount1
                                                        color={"#33FFBD"}
                                                    />
                                                }
                                                onBlur={(event: any) => {
                                                    let _profitMargin =
                                                        parseFloat(
                                                            event.target.value.trim()
                                                        );
                                                    if (
                                                        isNaN(_profitMargin) ===
                                                        false
                                                    ) {
                                                        let __profitMargins =
                                                            profitMargins;
                                                        __profitMargins[
                                                            prefix
                                                        ] = _profitMargin;
                                                        __setProfitMarginsState(
                                                            __profitMargins
                                                        );
                                                    }
                                                }}
                                            ></_InputLeftElement>
                                        </Box>
                                        {prefix !==
                                            DEFAULT_PROFIT_MARGIN_KEY && (
                                            <Box width="10%">
                                                <_Button
                                                    fontSize="1.2em"
                                                    isDisabled={isReadOnly}
                                                    icon={<FcFullTrash />}
                                                    color="lightblue"
                                                    bgColor="white"
                                                    label=""
                                                    onClick={() =>
                                                        deleteItemIdentifierPrefix(
                                                            prefix
                                                        )
                                                    }
                                                ></_Button>
                                            </Box>
                                        )}
                                    </HStack>
                                );
                            })}
                        </VStack>
                    </Box>
                    <_Divider margin={1}></_Divider>
                    <HStack spacing={5}>
                        <Box width="40%">
                            <_Button
                                fontSize="1.2em"
                                label={isReadOnly ? "Enable" : "Disable"}
                                onClick={() => {
                                    setIsReadOnly(!isReadOnly);
                                    setSuccessful(-1);
                                }}
                                color="black"
                                bgColor="white"
                                variant="outline"
                                borderColor="gray.200"
                                borderWidth={1}
                                icon={
                                    isReadOnly ? (
                                        <BiSolidLockOpen color={"#00A36C"} />
                                    ) : (
                                        <BiSolidLock color={"#00A36C"} />
                                    )
                                }
                            ></_Button>
                        </Box>
                        <Box width="40%">
                            <_Button
                                fontSize="1.2em"
                                _loading={{ fontSize: "0.8em" }}
                                loadingText="Updating..."
                                isLoading={isLoading}
                                isDisabled={isReadOnly}
                                label="Update"
                                onClick={updateProfitMargins}
                                bgColor={"#ceebdc"}
                                color="black"
                                icon={<RxUpdate color={iconColor} />}
                            ></_Button>
                        </Box>
                    </HStack>
                </VStack>
            </CardBody>
        </Card>
    );
};

const InventoryAndServices = () => {
    const [deadInventoryMonth, setDeadInventoryMonth] = useState<number>(3);
    const [deadInventoryYear, setDeadInventoryYear] = useState<number>(0);
    const [reportYear, setReportYear] = useState<number>(new Date().getFullYear());
    const [minCost, setMinCost] = useState<number>(0);
    const [maxCost, setMaxCost] = useState<number>(0);
    const [minQty, setMinQty] = useState<number>(0);
    const [maxQty, setMaxQty] = useState<number>(0);
    const [selectedStoreForDeadInventory, setSelectedStoreForDeadInventory] = useState<string>("0");
    
    // Stores
    const stores: any = Stores.getActiveStores();
    const CURRENT_STORE: any = localStorage.getItem("storeId") || null;
    return (
        <>
            <CanvasGrid>
                <ActionGroup
                    elementWidth={150}
                    title="Item/Service"
                    actions={[
                        {
                            icon: <IoMdAdd color={iconColor} />,
                            label: "Add",
                            onClick: () => {
                                window.open(
                                    "/item",
                                    "",
                                    "toolbar=no,scrollbars=yes,width=1000,height=550,top=200,left=350"
                                );
                            },
                        },
                        {
                            icon: <FiEdit2 color={iconColor} />,
                            label: "Modify",
                            onClick: () => {
                                window.open(
                                    "/item_modify",
                                    "",
                                    "toolbar=no,scrollbars=yes,width=1000,height=550,top=200,left=350"
                                );
                            },
                        },
                    ]}
                ></ActionGroup>
                <Box
                    visibility={
                        systemConfigMode === MODE_WASH ? "hidden" : "visible"
                    }
                >
                    <ActionGroup
                        elementWidth={180}
                        title="Adjust"
                        actions={[
                            {
                                icon: <AiOutlineStock color={iconColor} />,
                                label: "Quantity",
                                onClick: () => {
                                    if (systemConfigMode === MODE_PARTS)
                                        window.open(
                                            "/adjust_inventory",
                                            "_blank"
                                        );
                                },
                            },
                        ]}
                    ></ActionGroup>
                </Box>
                <ActionGroupDivider count={2}></ActionGroupDivider>
                <Box>
                    <ActionGroup
                        elementWidth={180}
                        title="Fetch"
                        actions={[
                            {
                                icon: <HiTrendingDown color={iconColor} />,
                                label: "Low Stock",
                                onClick: () => {
                                    if (systemConfigMode !== MODE_PARTS) return;
                                    else {
                                        window.open(
                                            `${APP_HOST}/api.php?action=low_stock&store_id=${CURRENT_STORE}`
                                        );
                                    }
                                },
                            },
                            {
                                icon: <PiWaveSineDuotone color={iconColor} />,
                                label: "Frequency",
                                onClick: () => {
                                    window.open("/item_frequency", "_blank");
                                },
                            },
                        ]}
                    ></ActionGroup>
                </Box>
                <Box>
                    <VStack align="left">
                        <Box>
                            <_Label fontSize="0.8em" textTransform={"uppercase"}>Fetch Dead Inventory Report</_Label>
                            <_Divider margin={2}/>
                            <HStack width="100%" >
                                <VStack width="100%">
                                    <_Select onChange={(event:any) => {
                                        setDeadInventoryMonth(parseInt(event.target.value));
                                    }} options={{0: "Select Month", 3: "3 Months", 6: "6 Months", 9: "9 Months", 12: "12 Months"}}></_Select>
                                    <_Label>OR</_Label>
                                    <_Input type="number" placeholder="Enter Year..." onBlur={(event: any) => {
                                        if(event) {
                                            let val = Number(event.target.value);
                                            if(isNaN(val) === false) {
                                                setDeadInventoryYear(event.target.value);
                                            }
                                        }
                                    }}></_Input>
                                    <Select
                                        defaultValue={CURRENT_STORE}
                                        size="xs"
                                        variant={selectConfig.variant}
                                        borderRadius={selectConfig.borderRadius}
                                        fontSize={selectConfig.fontSize}
                                        borderBottomColor={selectConfig.borderColor}
                                        borderBottomWidth={1}
                                        onChange={(event: any) => {
                                            setSelectedStoreForDeadInventory(event.target.value);
                                        }}
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
                                </VStack>
                                <_Button bgColor="black" fontSize={"1.2em"} icon={<IoStopCircleOutline color="#E3242B"/>} label="Fetch Dead Stock" onClick={() => {
                                    if((isNaN(deadInventoryYear) || deadInventoryYear == 0) && deadInventoryMonth == 0) return;
                                    const newURLParams = new URLSearchParams();
                                    
                                    if(deadInventoryYear != 0) {
                                        newURLParams.append("year", deadInventoryYear.toString());
                                    }
                                    else newURLParams.append("month", deadInventoryMonth.toString());

                                    // Append Store
                                    newURLParams.append("storeId", selectedStoreForDeadInventory || "");

                                    // Query Params
                                    const queryParams = newURLParams.toString();
                                    window.open(
                                        `${APP_HOST}/api.php?action=dead_inventory_report&${queryParams}`, 
                                        "_blank"
                                    );
                                }}></_Button>
                            </HStack>
                        </Box>
                        <Box marginTop={5}>
                        <_Label fontSize="0.8em" textTransform={"uppercase"}>Fetch Item Sold Report</_Label>
                            <_Divider margin={2}/>
                            <HStack>
                                <_Input defaultValue={reportYear} type="number" onBlur={(event:any) => {
                                    setReportYear(parseInt(event.target.value));
                                }}></_Input>
                                <_Button bgColor="black" fontSize={"1.2em"} icon={<FcSalesPerformance />}label="Fetch Sold Report" onClick={() => {
                                    window.open(
                                        `${APP_HOST}/api.php?action=fetch_item_sold_report&year=${reportYear}&storeId=${localStorage.getItem("storeId")}`, 
                                        "_blank"
                                    );
                                }}></_Button>
                            </HStack>
                        </Box>
                    </VStack>
                </Box>
                <_Divider/><_Divider/>
                <Box paddingRight={2}>
                    <VStack align="start">
                        <_Label fontSize={"0.8em"}>FETCH PARTS BY PRICE, QUANTITY</_Label>
                        <_Divider margin={1}></_Divider>
                        <HStack width="100%">
                            <Box width="25%">
                                <_Label fontSize={"0.7em"} color="blue">MIN $:</_Label>
                            </Box>
                            <Box width="75%">
                                <_Input fontSize="0.8em" type="number" onBlur={(event: any) => {
                                    let value = event.target.value;
                                    if(isNaN(value) === false) {
                                        setMinCost(parseInt(value));
                                    }
                                    else setMinCost(0);
                                }}></_Input>
                            </Box>
                        </HStack>
                        <HStack width="100%">
                            <Box width="25%">
                                <_Label fontSize={"0.7em"} color="blue">MAX $:</_Label>
                            </Box>
                            <Box width="75%">
                                <_Input fontSize="0.8em" type="number" onBlur={(event: any) => {
                                    let value = event.target.value;
                                    if(isNaN(value) === false) {
                                        setMaxCost(parseInt(value));
                                    }
                                    else setMaxCost(0);
                                }}></_Input>
                            </Box>
                        </HStack>
                        <_Divider margin={0}/>
                        <HStack width="100%">
                            <Box width="25%">
                                <_Label fontSize={"0.7em"} color="blue">MIN QTY:</_Label>
                            </Box>
                            <Box width="75%">
                                <_Input fontSize="0.8em" type="number" onBlur={(event: any) => {
                                    let value = event.target.value;
                                    if(isNaN(value) === false) {
                                        setMinQty(parseInt(value));
                                    }
                                    else setMinQty(0);
                                }}></_Input>
                            </Box>
                        </HStack>
                        <HStack width="100%">
                            <Box width="25%">
                                <_Label fontSize={"0.7em"} color="blue">MAX QTY:</_Label>
                            </Box>
                            <Box width="75%">
                                <_Input fontSize="0.8em" type="number" onBlur={(event: any) => {
                                    let value = event.target.value;
                                    if(isNaN(value) === false) {
                                        setMaxQty(parseInt(value));
                                    }
                                    else setMaxQty(0);
                                }}></_Input>
                            </Box>
                        </HStack>
                        <_Button icon={<FcSearch/>} fontSize="1em" bgColor="black" color="white" label="Find Items" onClick={() => {
                            const newURLParams = new URLSearchParams();
                            newURLParams.append("minCost", minCost.toString());
                            newURLParams.append("maxCost", maxCost.toString());
                            newURLParams.append("minQty", minQty.toString());
                            newURLParams.append("maxQty", minQty.toString());
                            window.open(
                                `${APP_HOST}/api.php?action=filter_items_by_price_quantity&storeId=${localStorage.getItem("storeId")}&minCost=${minCost}&maxCost=${maxCost}&minQty=${minQty}&maxQty=${maxQty}`, 
                                "_blank"
                            );
                        }}></_Button>
                    </VStack>
                </Box>
                <ProfitMarginsHandler />
            </CanvasGrid>
        </>
    );
};

export default InventoryAndServices;
