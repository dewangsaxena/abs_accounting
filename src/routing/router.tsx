import { createBrowserRouter } from "react-router-dom";
import Login from "../components/login/Login";
import Dashboard from "../components/dashboard/Dashboard";
import Client from "../components/client/Client";
import AdjustInventory from "../components/inventory/AdjustInventory";
import Transactions from "../components/accounting/transactions/Transactions";
import Item from "../components/inventory/item";
import { TRANSACTION_TYPES } from "../shared/config";
import Filter from "../components/accounting/transactions/Filter";
import Receipts from "../components/accounting/receipts/Receipts";
import IncomeStatement from "../components/reports/income_statement/IncomeStatement";
import BalanceSheet from "../components/reports/balance_sheet/BalanceSheet";
import CustomerAgedSummary from "../components/reports/customer_aged_summary/CustomerAgedSummary";
import CustomerStatement from "../components/reports/customer_statement/CustomerStatement";
import CustomerSummary from "../components/reports/customer_summary/CustomerSummary";
import CustomerList from "../components/reports/customer_list/CustomerList";

const router = createBrowserRouter([
  {
    path: "/login",
    element: <Login></Login>,
  },
  {
    path: "/",
    element: <Dashboard></Dashboard>,
  },
  {
    path: "*",
    element: <Dashboard></Dashboard>,
  },
  {
    path: "/client",
    element: <Client></Client>,
  },
  {
    path: "/client_modify",
    element: <Client isViewOrUpdate={true}></Client>,
  },
  {
    path: "/item",
    element: <Item></Item>,
  },
  {
    path: "/item_modify",
    element: <Item isViewOrUpdate={true}></Item>,
  },
  {
    path: "/adjust_inventory",
    element: <AdjustInventory></AdjustInventory>,
  },
  {
    path: "/sales_invoice",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["SI"]}
        name="Sales Invoice"
        isViewOrUpdate={false}
      ></Transactions>
    ),
  },
  {
    path: "/sales_invoice_update",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["SI"]}
        name="Sales Invoice"
        isViewOrUpdate={true}
      ></Transactions>
    ),
  },
  {
    path: "/sales_invoice_find",
    element: <Filter type={TRANSACTION_TYPES["SI"]}></Filter>,
  },
  {
    path: "/quotation",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["QT"]}
        name="Quotation"
        isViewOrUpdate={false}
      ></Transactions>
    ),
  },
  {
    path: "/quotation_update",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["QT"]}
        name="Quotation"
        isViewOrUpdate={true}
      ></Transactions>
    ),
  },
  {
    path: "/quotation_find",
    element: <Filter type={TRANSACTION_TYPES["QT"]}></Filter>,
  },
  {
    path: "/credit_note",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["CN"]}
        name="Credit Note"
        isViewOrUpdate={false}
      ></Transactions>
    ),
  },
  {
    path: "/credit_note_update",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["CN"]}
        name="Credit Note"
        isViewOrUpdate={true}
      ></Transactions>
    ),
  },
  {
    path: "/credit_note_find",
    element: <Filter type={TRANSACTION_TYPES["CN"]}></Filter>,
  },
  {
    path: "/debit_note",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["DN"]}
        name="Debit Note"
        isViewOrUpdate={false}
      ></Transactions>
    ),
  },
  {
    path: "/debit_note_update",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["DN"]}
        name="Debit Note"
        isViewOrUpdate={true}
      ></Transactions>
    ),
  },
  {
    path: "/debit_note_find",
    element: <Filter type={TRANSACTION_TYPES["DN"]}></Filter>,
  },
  {
    path: "/sales_return",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["SR"]}
        name="Sales Returns"
        isViewOrUpdate={false}
      ></Transactions>
    ),
  },
  {
    path: "/sales_return_update",
    element: (
      <Transactions
        type={TRANSACTION_TYPES["SR"]}
        name="Sales Returns"
        isViewOrUpdate={true}
      ></Transactions>
    ),
  },
  {
    path: "/sales_return_find",
    element: <Filter type={TRANSACTION_TYPES["SR"]}></Filter>,
  },
  {
    path: "/receipt",
    element: <Receipts isViewOrUpdate={false}></Receipts>,
  },
  {
    path: "/receipt_update",
    element: <Receipts isViewOrUpdate={true}></Receipts>,
  },
  {
    path: "/receipt_find",
    element: <Filter type={TRANSACTION_TYPES["RC"]}></Filter>,
  },
  {
    path: "/income_statement",
    element: <IncomeStatement />,
  },
  {
    path: "/balance_sheet",
    element: <BalanceSheet />,
  },
  {
    path: "/customer_aged_summary",
    element: <CustomerAgedSummary />,
  },
  {
    path: "/customer_statement",
    element: <CustomerStatement />,
  },
  {
    path: "/customer_summary",
    element: <CustomerSummary />,
  },
  {
    path: "/customer_list",
    element: <CustomerList/>
  }
]);

export default router;
