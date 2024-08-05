import React from "react";
import ReactDOM from "react-dom/client";
import "./index.css";
import { ChakraProvider } from "@chakra-ui/react";
import { extendTheme } from "@chakra-ui/react";
import { GlobalStyle } from "./shared/style.tsx";
import { RouterProvider } from "react-router-dom";
import router from "./routing/router.tsx";

const theme = extendTheme({
  fonts: {
    heading: "Rubik",
    body: "Raleway",
  },
  components: {
    Input: {
      parts: ["field"],
      baseStyle: {
        field: {
          fontSize: "0.8em",
          fontWeight: "bold",
          letterSpacing: 2,
          _placeholder: {
            color: "gray.500",
            opacity: 1,
          },
        },
      },
    },
  },
});

ReactDOM.createRoot(document.getElementById("root") as HTMLElement).render(
  <React.StrictMode>
    <ChakraProvider theme={theme}>
      <GlobalStyle />
      <RouterProvider router={router} />
    </ChakraProvider>
  </React.StrictMode>
);
