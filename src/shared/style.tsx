import { Global } from "@emotion/react";
import { AttributeType } from "./config";

/* Input Placeholder */
export const letterSpacingConfig = {
  input: 2,
  button: 2,
};

export const inputConfig = {
  letterSpacing: 2,
  fontSize: "0.8em",
  fontWeight: "bold",
  borderRadius: 2,
  variant: "outline",
  textAlign: "left" as const,
  borderWidth: 1,
  borderColor: "purple",
  size: "xs",
};

export const inputPlaceholderConfig = {
  opacity: 1,
  color: "gray.500",
  letterSpacing: 2,
};

export const selectConfig = {
  borderRadius: 5,
  fontSize: "0.75em",
  borderWidth: 2,
  borderColor: "rebeccapurple",
  variant: "outline",
};

export const buttonConfig = {
  borderRadius: 5,
  fontSize: "0.75em",
  borderLeftWidth: 2,
  borderLeftColor: "rebeccapurple",
  variant: "outline",
  borderColor: "white",
  color: "rebeccapurple",
  _hover: {
    bgColor: "gray.100",
  },
};
export const iconConfig = {
  color: "#136207",
};

export const cardConfig = {
  bgColor: "white",
  borderColor: "rebeccapurple",
  borderRadius: 5,
  boxShadow: "0.2em 0.2em rebeccapurple",
  variant: "outline",
  borderLeftColor: "rebeccapurple",
  borderLeftWidth: 5,
};

export const dividerConfig = {
  borderColor: "whitesmoke", //"#7851a9"
};

// Number Font.
export const numberFont = "Space Grotesk";

// https://htmlcolorcodes.com/colors/shades-of-black/
// https://www.eggradients.com/shades-of-black-color
export const navBgColor = "#023020"; // #023020

export const GlobalStyle = () => {
  return (
    <Global
      styles={`
      #root {
        padding: 0.5vw;
      }

      /* Datepicker Style */
      .datepicker_style {
        font-family: "${numberFont}" !important;
        color: black !important;
        font-size: smaller !important;
      }

      body {
        background-color: white; /* #FFFEFC  #F5FEFD */
        font-family: "JetBrains Mono";
      }

      /* Copied from https://fonts.googleapis.com/css2?family=Open+Sans&display=swap */

      /* cyrillic-ext */
      @font-face {
        font-family: 'Raleway';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/raleway/v28/1Ptxg8zYS_SKggPN4iEgvnHyvveLxVvaorCFPrEHJA.woff2) format('woff2');
        unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
      }
      /* cyrillic */
      @font-face {
        font-family: 'Raleway';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/raleway/v28/1Ptxg8zYS_SKggPN4iEgvnHyvveLxVvaorCMPrEHJA.woff2) format('woff2');
        unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
      }
      /* vietnamese */
      @font-face {
        font-family: 'Raleway';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/raleway/v28/1Ptxg8zYS_SKggPN4iEgvnHyvveLxVvaorCHPrEHJA.woff2) format('woff2');
        unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
      }
      /* latin-ext */
      @font-face {
        font-family: 'Raleway';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/raleway/v28/1Ptxg8zYS_SKggPN4iEgvnHyvveLxVvaorCGPrEHJA.woff2) format('woff2');
        unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
      }
      /* latin */
      @font-face {
        font-family: 'Raleway';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/raleway/v28/1Ptxg8zYS_SKggPN4iEgvnHyvveLxVvaorCIPrE.woff2) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
      }

      /* Rubik : https://fonts.googleapis.com/css2?family=Rubik&display=swap */
      /* arabic */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFUkU1Z4Y.woff2) format('woff2');
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0898-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC;
      }
      /* cyrillic-ext */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFWkU1Z4Y.woff2) format('woff2');
        unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
      }
      /* cyrillic */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFU0U1Z4Y.woff2) format('woff2');
        unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
      }
      /* hebrew */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFVUU1Z4Y.woff2) format('woff2');
        unicode-range: U+0590-05FF, U+200C-2010, U+20AA, U+25CC, U+FB1D-FB4F;
      }
      /* latin-ext */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFWUU1Z4Y.woff2) format('woff2');
        unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
      }
      /* latin */
      @font-face {
        font-family: 'Rubik';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/rubik/v28/iJWZBXyIfDnIV5PNhY1KTN7Z-Yh-B4iFV0U1.woff2) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
      }

      /* JetBrains Mono START */
      /* cyrillic-ext */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTN1OVgaY.woff2) format('woff2');
        unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
      }
      /* cyrillic */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTPlOVgaY.woff2) format('woff2');
        unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
      }
      /* greek */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTOVOVgaY.woff2) format('woff2');
        unicode-range: U+0370-03FF;
      }
      /* vietnamese */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTNVOVgaY.woff2) format('woff2');
        unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
      }
      /* latin-ext */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTNFOVgaY.woff2) format('woff2');
        unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
      }
      /* latin */
      @font-face {
        font-family: 'JetBrains Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/jetbrainsmono/v18/tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxTOlOV.woff2) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
      }
      /* JetBrains Mono END */

      /* Space Mono Begin */ 
      /* vietnamese */
      @font-face {
        font-family: 'Space Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacemono/v13/i7dPIFZifjKcF5UAWdDRYE58RWq7.woff2) format('woff2');
        unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
      }
      /* latin-ext */
      @font-face {
        font-family: 'Space Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacemono/v13/i7dPIFZifjKcF5UAWdDRYE98RWq7.woff2) format('woff2');
        unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
      }
      /* latin */
      @font-face {
        font-family: 'Space Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacemono/v13/i7dPIFZifjKcF5UAWdDRYEF8RQ.woff2) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
      }
      /* Space Mono End */ 

      /* Space Grotesk */ 
      /* vietnamese */
      @font-face {
        font-family: 'Space Grotesk';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacegrotesk/v16/V8mQoQDjQSkFtoMM3T6r8E7mF71Q-gOoraIAEj7oUXsrPMBTTA.woff2) format('woff2');
        unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB;
      }
      /* latin-ext */
      @font-face {
        font-family: 'Space Grotesk';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacegrotesk/v16/V8mQoQDjQSkFtoMM3T6r8E7mF71Q-gOoraIAEj7oUXsqPMBTTA.woff2) format('woff2');
        unicode-range: U+0100-02AF, U+0304, U+0308, U+0329, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
      }
      /* latin */
      @font-face {
        font-family: 'Space Grotesk';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url(https://fonts.gstatic.com/s/spacegrotesk/v16/V8mQoQDjQSkFtoMM3T6r8E7mF71Q-gOoraIAEj7oUXskPMA.woff2) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
      }
      /* Space Grotesk End */
  `}
    ></Global>
  );
};

/* Icon Color */
export const iconColor = "#8D4BE0";

/**
 * Async Select Style
 */
export const AsyncSelectStyle = {
  clearIndicator: (baseStyles: any) => ({
    ...baseStyles,
    transform: "translateY(-5px);",
    color: "red",
  }),
  placeholder: (baseStyles: any) => ({
    ...baseStyles,
    letterSpacing: 1,
    transform: "translateY(-5px);",
    fontSize: "0.7em",
    textTransform: "uppercase",
    fontFamily: "Raleway",
  }),
  indicatorSeparator: (baseStyles: any) => ({
    ...baseStyles,
    transform: "translateY(-6px);",
    height: "18px",
  }),
  dropdownIndicator: (baseStyles: any) => ({
    ...baseStyles,
    transform: "translateY(-6px);",
  }),
  menuList: (baseStyles: any) => ({
    ...baseStyles,
    borderWidth: "2px",
    borderRadius: 2,
    padding: 0,
    margin: 0,
    fontSize: "0.8em",
    fontFamily: "JetBrains Mono",
    letterSpacing: 2,
  }),
  singleValue: (baseStyles: any) => ({
    ...baseStyles,
    letterSpacing: 1,
    transform: "translateY(-5px);",
    fontSize: "0.8em",
  }),
  input: (baseStyles: any) => ({
    ...baseStyles,
    fontSize: "0.8em",
    margin: 0,
    padding: 0,
    letterSpacing: 1,
    transform: "translateY(-5px);",
  }),
  control: (baseStyles: any) => ({
    ...baseStyles,
    margin: 0,
    borderColor: "#E8E8E8",
    paddingLeft: 0,
    marginLeft: 0,
    borderRadius: 2,
    minHeight: 20,
    height: 25,
    fontFamily: "JetBrains Mono",
  }),
};

/**
 * Success Gradient
 */
export const successGradient: AttributeType = {
  bgColor: "#ceebdc",
  bgGradient:
    "linear(90deg, rgba(206,235,220,1) 0%, rgba(255,255,255,1) 100%);",
};

/**
 * Error Gradient
 */
export const errorGradient: AttributeType = {
  bgColor: "#f0adad",
  bgGradient:
    "linear(90deg, rgba(240,173,173,1) 0%, rgba(255,255,255,1) 100%);",
};

/**
 * Reset Gradient
 */
export const resetGradient: AttributeType = {
  bgColor: "#ffffff",
  bgGradient: "",
};

// Auto Suggest Style
export const AutoSuggestStyle: AttributeType = {
  borderRadius: 2,
  height: "1.9em",
  backgroundColor: "white",
  paddingLeft: "0.5em",
  paddingRight: "0.5em",
};
