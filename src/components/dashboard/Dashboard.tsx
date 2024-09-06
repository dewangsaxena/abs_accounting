import { Grid, GridItem } from "@chakra-ui/react";
import Navigation from "./navigation/Navigation";
import CustomerAndSales from "./actions/CustomerAndSales";
import InventoryAndServices from "./actions/InventoryAndServices";
import { interaction } from "./interaction";
import Settings from "./actions/Settings";
import { redirectIfInvalidSession } from "../../shared/functions";
import Stats from "./actions/SummaryReportDetails";

const Dashboard = () => {
  const {
    customerAndSalesVisible,
    inventoryAndServicesVisible,
    settingsVisibile,
  } = interaction();

  // Check for Valid Session
  redirectIfInvalidSession();

  return (
    <>
      <Grid
        templateAreas={`"nav main stats"`}
        gridTemplateRows={"98vh"}
        gridTemplateColumns={{
          base: "18% 50% 30%",
          lg: "18% 50% 30%",
          md: "18% 50% 30%",
          sm: "50% 50% 10%",
        }}
        gap="1"
      >
        <GridItem area={"nav"}>
          <Navigation></Navigation>
        </GridItem>
        <GridItem area={"main"}>
          {customerAndSalesVisible && <CustomerAndSales></CustomerAndSales>}
          {inventoryAndServicesVisible && (
            <InventoryAndServices></InventoryAndServices>
          )}
          {settingsVisibile && <Settings></Settings>}
        </GridItem>
        <GridItem area={"stats"}>
          <Stats />
        </GridItem>
      </Grid>
    </>
  );
};

export default Dashboard;
