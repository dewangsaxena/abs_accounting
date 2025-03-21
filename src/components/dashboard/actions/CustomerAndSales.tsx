import {
  LiaUserPlusSolid,
  LiaUserEditSolid,
  LiaFileInvoiceDollarSolid,
} from "react-icons/lia";
import { MdOutlineRequestQuote } from "react-icons/md";
import { CiReceipt, CiSearch } from "react-icons/ci";
import { GiReceiveMoney, GiPayMoney } from "react-icons/gi";
import { ActionGroup, ActionGroupDivider, CanvasGrid } from "./Shared";
import { iconColor, numberFont } from "../../../shared/style";
import { SiConvertio } from "react-icons/si";
import { BiTransferAlt } from "react-icons/bi";
import {
  Box,
  HStack,
  Modal,
  ModalBody,
  ModalCloseButton,
  ModalContent,
  ModalFooter,
  ModalHeader,
  ModalOverlay,
  useDisclosure,
  useToast,
} from "@chakra-ui/react";
import { _Button, _Input, _Label, _Select } from "../../../shared/Components";
import { useState } from "react";
import { IoIosCloseCircle } from "react-icons/io";
import { APIResponse, HTTPService } from "../../../service/api-client";
import {
  MODE_PARTS,
  MODE_WASH,
  Stores,
  TRANSACTION_TYPES,
  UNKNOWN_SERVER_ERROR_MSG,
  systemConfigMode,
} from "../../../shared/config";
import { showToast } from "../../../shared/functions";

interface ModalProp {
  isOpen: any;
  onClose: any;
}

// Http Service
const httpService = new HTTPService();

/**
 * This component Shows Modal to convert quote to Invoice.
 * @param isOpen
 * @param onClose
 * @returns
 */
const ConvertQuoteModal = ({ isOpen, onClose }: ModalProp) => {
  const [quotations, setQuotations] = useState<string>("");

  // Status
  const [status, setStatus] = useState<boolean>(false);

  // Convert
  const __convert = async (quotations: string) => {
    let payload = {
      transactionType: TRANSACTION_TYPES["SI"],
      quotations: quotations,
    };
    return await httpService.fetch(payload, "convert_quote_to_invoice");
  };

  const toast = useToast();

  // Convert
  const convert = () => {
    setStatus(true);
    __convert(quotations.trim())
      .then((res: any) => {
        let result: APIResponse = res.data;
        if (result.status === true) {
          showToast(
            toast,
            true,
            "Successfully Converted Quotation into Sales Invoice."
          );
        } else {
          showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
          setStatus(false);
        }
      })
      .catch((err: any) => {
        setStatus(false);
        showToast(toast, false, err.message);
      });
  };

  const __onClose = () => {
    setStatus(false);
    onClose();
  };

  return (
    <Modal
      size="3xl"
      closeOnOverlayClick={false}
      isOpen={isOpen}
      onClose={__onClose}
    >
      <ModalOverlay />
      <ModalContent>
        <ModalHeader>
          <_Label
            fontSize="0.8em"
            letterSpacing={2}
            textTransform={"uppercase"}
          >
            Convert Quotations To Invoices.
          </_Label>
        </ModalHeader>
        <ModalCloseButton />
        <ModalBody pb={6}>
          <_Input
            fontFamily={numberFont}
            placeholder="Quotation ID. eg: 10007"
            type="text"
            fontSize="0.8em"
            letterSpacing={2}
            onBlur={(event: any) => {
              setQuotations(event.target.value.trim());
            }}
          ></_Input>
        </ModalBody>
        <ModalFooter>
          <HStack spacing={5}>
            <Box>
              <_Button
                isDisabled={status}
                color="#3CB371"
                icon={<SiConvertio color="#3CB371" />}
                label="Convert"
                onClick={convert}
                fontSize="1.2em"
              ></_Button>
            </Box>
            <Box>
              <_Button
                color="red"
                icon={<IoIosCloseCircle color="red" />}
                label="Close"
                onClick={__onClose}
                fontSize="1.2em"
              ></_Button>
            </Box>
          </HStack>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

/**
 * This component Shows Modal to Transfer Invoice Items to Store.
 * @param isOpen
 * @param onClose
 * @returns
 */
const TransferInvoiceToStore = ({ isOpen, onClose }: ModalProp) => {
  const [salesInvoices, setSalesInvoices] = useState<string>("");
  const [selectedStore, setSelectedStore] = useState<number>(1);

  // Status
  const [status, setStatus] = useState<boolean>(false);

  // Transfer
  const __transfer = async (salesInvoices: string) => {
    let payload = {
      transactionType: TRANSACTION_TYPES["SI"],
      sales_invoices: salesInvoices,
      transfer_to: selectedStore,
    };
    return await httpService.fetch(payload, "transfer_invoice");
  };

  const toast = useToast();

  // Transfer
  const transfer = () => {
    setStatus(true);
    __transfer(salesInvoices.trim())
      .then((res: any) => {
        let result: APIResponse = res.data;
        if (result.status !== true) {
          showToast(toast, false, result.message || UNKNOWN_SERVER_ERROR_MSG);
          setStatus(false);
        } else {
          showToast(
            toast,
            true,
            "Successfully Transferred Sales Invoice Items into Store."
          );
        }
      })
      .catch((err: any) => {
        setStatus(false);
        showToast(toast, false, err.message);
      });
  };

  const __onClose = () => {
    setStatus(false);
    onClose();
  };

  let stores = Stores.getActiveStores();
  stores[1] = "Select Store";

  return (
    <Modal
      size="3xl"
      closeOnOverlayClick={false}
      isOpen={isOpen}
      onClose={__onClose}
    >
      <ModalOverlay />
      <ModalContent>
        <ModalHeader>
          <_Label
            fontSize="0.8em"
            letterSpacing={2}
            textTransform={"uppercase"}
          >
            Transfer Invoices to Store
          </_Label>
        </ModalHeader>
        <ModalCloseButton />
        <ModalBody pb={6}>
          <HStack>
            <Box width="80%">
              <_Input
                placeholder="Sales Invoices must be comma-separated."
                type="text"
                fontSize="0.8em"
                letterSpacing={2}
                fontFamily={numberFont}
                onBlur={(event: any) => {
                  setSalesInvoices(event.target.value.trim());
                }}
              ></_Input>
            </Box>
            <Box width="20%">
              <_Select
                fontSize="0.8em"
                size="sm"
                width={"100%"}
                options={stores}
                onChange={(event: any) => {
                  if (event && event.target) {
                    setSelectedStore(parseInt(event.target.value));
                  } else setSelectedStore(1);
                }}
              ></_Select>
            </Box>
          </HStack>
        </ModalBody>
        <ModalFooter>
          <HStack spacing={5}>
            <Box>
              <_Button
                isDisabled={status}
                color="#3CB371"
                icon={<BiTransferAlt color="#3CB371" />}
                label="Transfer"
                fontSize="1.2em"
                onClick={transfer}
              ></_Button>
            </Box>
            <Box>
              <_Button
                color="red"
                icon={<IoIosCloseCircle color="red" />}
                label="Close"
                onClick={__onClose}
                fontSize="1.2em"
              ></_Button>
            </Box>
          </HStack>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
};

const CustomerAndSales = () => {
  const {
    isOpen: transferInvIsOpen,
    onOpen: transferInvOpen,
    onClose: transferInvOnClose,
  } = useDisclosure();

  const {
    isOpen: convertIsOpen,
    onOpen: convertOpen,
    onClose: convertOnClose,
  } = useDisclosure();
  return (
    <>
      <CanvasGrid>
        <ActionGroup
          elementWidth={150}
          title="Client"
          actions={[
            {
              icon: <LiaUserPlusSolid color={iconColor} />,
              label: "Add",
              onClick: () => {
                window.open(
                  "/client",
                  "",
                  "toolbar=no,scrollbars=yes,width=1000,height=500,top=200,left=300"
                );
              },
            },
            {
              icon: <LiaUserEditSolid color={iconColor} />,
              label: "Modify",
              onClick: () => {
                window.open(
                  "/client_modify",
                  "",
                  "toolbar=no,scrollbars=yes,width=1000,height=500,top=200,left=300"
                );
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroup
          elementWidth={150}
          title="Quotation"
          actions={[
            {
              icon: <MdOutlineRequestQuote color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/quotation", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/quotation_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroupDivider count={2} />
        <ActionGroup
          elementWidth={150}
          title="Sales Invoice"
          actions={[
            {
              icon: <LiaFileInvoiceDollarSolid color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/sales_invoice", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/sales_invoice_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroup
          elementWidth={150}
          title="Sales Return"
          actions={[
            {
              icon: <LiaFileInvoiceDollarSolid color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/sales_return", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/sales_return_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroupDivider count={2} />
        <ActionGroup
          elementWidth={150}
          title="Credit Note"
          actions={[
            {
              icon: <GiPayMoney color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/credit_note", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/credit_note_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroup
          elementWidth={150}
          title="Debit Note"
          actions={[
            {
              icon: <GiReceiveMoney color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/debit_note", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/debit_note_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <ActionGroupDivider count={2} />
        <ActionGroup
          elementWidth={150}
          title="Receipt"
          actions={[
            {
              icon: <CiReceipt color={iconColor} />,
              label: "Create",
              onClick: () => {
                window.open("/receipt", "_blank");
              },
            },
            {
              icon: <CiSearch color={iconColor} />,
              label: "Find",
              onClick: () => {
                window.open("/receipt_find", "_blank");
              },
            },
          ]}
        ></ActionGroup>
        <Box visibility={systemConfigMode === MODE_WASH ? "hidden" : "visible"}>
          <ActionGroup
            elementWidth={150}
            title="Misc."
            actions={[
              {
                icon: <SiConvertio color={iconColor} />,
                label: "Convert Quote",
                onClick: () => {
                  if (systemConfigMode === MODE_PARTS) convertOpen();
                },
              },
              {
                icon: <BiTransferAlt color={iconColor} />,
                label: "Transfer",
                onClick: () => {
                  if (systemConfigMode === MODE_PARTS) transferInvOpen();
                },
              },
            ]}
          ></ActionGroup>
        </Box>
        {/* <ActionGroupDivider count={2} />
        <Box visibility={systemConfigMode === MODE_WASH ? "hidden" : "visible"}>
          <ActionGroup
            elementWidth={150}
            title="Vendor"
            actions={[
              {
                icon: <LiaUserPlusSolid color={iconColor} />,
                label: "Create",
                onClick: () => {
                  window.open("/vendor", '_blank', "toolbar=no,scrollbars=yes,width=1000,height=300,top=200,left=300");
                },
              },
              {
                icon: <LiaUserEditSolid color={iconColor} />,
                label: "Update",
                onClick: () => {
                  window.open("/vendor_update", '_blank', "toolbar=no,scrollbars=yes,width=1000,height=300,top=200,left=300");
                },
              },
            ]}
          ></ActionGroup>
        </Box> */}
      </CanvasGrid>

      <TransferInvoiceToStore
        isOpen={transferInvIsOpen}
        onClose={transferInvOnClose}
      />
      <ConvertQuoteModal isOpen={convertIsOpen} onClose={convertOnClose} />
    </>
  );
};

export default CustomerAndSales;
