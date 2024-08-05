import React, { ReactNode, useState } from "react";
import {
  Text,
  Button,
  ResponsiveValue,
  Input,
  Select,
  forwardRef,
  Divider,
  InputGroup,
  InputLeftElement,
  InputLeftAddon,
  Box,
} from "@chakra-ui/react";
import * as CSS from "csstype";
import { PiCurrencyDollarSimpleDuotone } from "react-icons/pi";
import { inputConfig, navBgColor } from "./style";
import { AttributeType } from "./config";
import { FcHome } from "react-icons/fc";

/* Button Element Prop */
interface _ButtonProp {
  label: string;
  size?: string;
  icon?: React.ReactElement;
  color?: string;
  bgColor?: string;
  marginTop?: number;
  marginBottom?: number;
  hoverBgColor?: string;
  height?: string | number;
  width?: string | number | {};
  fontSize?: string | number;
  borderWidth?: number;
  borderColor?: string;
  borderRadius?: number;
  justifyContent?: string;
  isDisabled?: boolean;
  isLoading?: boolean;
  loadingText?: string;
  variant?: string;
  _loading?: any;
  spinner?: any;
  fontFamily?: string;
  visibility?: string;
  onClick: () => void;
}

/* Navigation Element */
export const _Button = ({
  label,
  size = "xs",
  icon,
  color = "#FFFCFF",
  bgColor = "",
  hoverBgColor = "gray.100",
  height = "",
  width = "100%",
  fontSize = "1em",
  borderColor = "",
  borderWidth = 0,
  borderRadius = 2,
  justifyContent = "left",
  isDisabled = false,
  isLoading = false,
  variant = "",
  loadingText = "",
  _loading = {},
  fontFamily = "",
  visibility = "",
  spinner = <></>,
  onClick,
}: _ButtonProp) => {
  let _variant: AttributeType = {};
  if (variant != "") _variant["variant"] = variant;
  let _height: AttributeType = {};
  if (height != "") _height["height"] = height;
  let _fontFamily: AttributeType = {};
  if (fontFamily !== "") _fontFamily["fontFamily"] = fontFamily;
  let _visibility: AttributeType = {};
  if (visibility !== "") _visibility["visibility"] = visibility;

  return (
    <>
      {/* <Box
        marginTop={marginTop}
        marginBottom={marginBottom}
        bgColor={bgColor}
        borderRadius={5}
        width={width}
      > */}
      <Button
        {..._fontFamily}
        _loading={_loading}
        {..._variant}
        size={size}
        isDisabled={isDisabled}
        color={color}
        bgColor={bgColor}
        leftIcon={icon}
        justifyContent={justifyContent}
        borderRadius={borderRadius}
        paddingLeft={1}
        whiteSpace={"normal"}
        wordBreak={"break-word"}
        width={width}
        {..._height}
        fontSize={fontSize}
        borderColor={borderColor}
        borderWidth={borderWidth}
        _hover={{
          color: "#247BA0",
          bgColor: hoverBgColor,
        }}
        onClick={onClick}
        loadingText={loadingText}
        isLoading={isLoading}
        spinner={spinner}
        {..._visibility}
      >
        <Text
          textTransform="uppercase"
          fontSize={"0.5em"}
          fontWeight={"light"}
          letterSpacing={5}
          _hover={{ fontWeight: "bold" }}
        >
          {label}
        </Text>
      </Button>
      {/* </Box> */}
    </>
  );
};

interface SectionHeaderProps {
  children: ReactNode;
  textAlign?: ResponsiveValue<CSS.Property.TextAlign>;
  color?: string;
  fontSize?: string;
  fontWeight?: string;
  marginTop?: number;
  marginBottom?: number;
  letterSpacing?: number;
  fontFamily?: string;
}

/**
 * Section Header
 * @param children
 * @returns
 */
export const SectionHeader = ({
  color = "#023020",
  textAlign = "left",
  fontSize = "1vw",
  fontWeight = "bold",
  marginTop = 0,
  marginBottom = 0,
  letterSpacing = 1,
  children,
  fontFamily = "",
}: SectionHeaderProps) => {
  return (
    <Text
      textTransform="uppercase"
      fontSize={fontSize}
      fontWeight={fontWeight}
      letterSpacing={letterSpacing}
      color={color}
      textAlign={textAlign}
      marginTop={marginTop}
      marginBottom={marginBottom}
      fontFamily={fontFamily}
    >
      {children}
    </Text>
  );
};

// Void Method
const voidMethod = () => {};

export const _Input = forwardRef(function _Input(props, ref) {
  let borderBottomColor = props.borderBottomColor || "none";
  let borderBottomWidth = props.borderBottomWidth || 1;
  let borderLeftColor = props.borderLeftColor || "none";
  let borderLeftWidth = props.borderLeftWidth || 1;
  let borderRadius = props.borderRadius || inputConfig.borderRadius;
  let width = props.width || "100%";
  let size = props.size || "xs";
  let fontSize = props.fontSize || "1em";
  let fontWeight = props.fontWeight || "normal";
  let letterSpacing = props.letterSpacing || 1;
  let placeholder = props.placeholder || "";
  let defaultValue = props.defaultValue || "";
  let _defaultValue: { [key: string]: any } = {};
  if (defaultValue != "") _defaultValue["defaultValue"] = defaultValue;
  let type = props.type || "text";
  let minLength = props.minLength || "";
  let maxLength = props.maxLength || "";
  let isDisabled = props.isDisabled || false;
  let isReadOnly = props.isReadOnly || false;
  let onChange = props.onChange || voidMethod;
  let onBlur = props.onBlur || voidMethod;
  let onClick = props.onClick || voidMethod;
  let fontFamily = props.fontFamily || "";
  let value = props.value || "";
  let _value: { [key: string]: any } = {};
  if (value != "") _value["value"] = value;
  let _fontFamily: { [key: string]: string } = {};
  if (fontFamily != "") _fontFamily["fontFamily"] = fontFamily;
  let bgColor = props.bgColor || "white";
  let _borderColor: { [key: string]: string } = {};
  if (props.borderColor !== undefined)
    _borderColor["borderColor"] = props.borderColor;
  let textTransform = props.textTransform || "none";

  let key: { [key: string]: any } = {};
  if (props._key !== undefined) {
    key["key"] = props._key;
  }
  return (
    <>
      <Input
        {...key}
        {..._fontFamily}
        {..._value}
        {..._defaultValue}
        {..._borderColor}
        textTransform={textTransform}
        onChange={onChange}
        onBlur={onBlur}
        onClick={onClick}
        isReadOnly={isReadOnly}
        isDisabled={isDisabled}
        type={type}
        minLength={minLength}
        maxLength={maxLength}
        ref={ref}
        borderBottomColor={borderBottomColor}
        borderBottomWidth={borderBottomWidth}
        borderLeftColor={borderLeftColor}
        borderLeftWidth={borderLeftWidth}
        borderRadius={borderRadius}
        width={width}
        size={size}
        fontSize={fontSize}
        letterSpacing={letterSpacing}
        fontWeight={fontWeight}
        placeholder={placeholder}
        bgColor={bgColor}
      ></Input>
    </>
  );
});

export const _InputLeftAddon = forwardRef(function _InputLeftElement(
  props,
  ref
) {
  let borderBottomColor = props.borderBottomColor || "none";
  let borderBottomWidth = props.borderBottomWidth || 1;
  let borderLeftColor = props.borderLeftColor || "none";
  let borderLeftWidth = props.borderLeftWidth || 1;
  let borderRadius = props.borderRadius || inputConfig.borderRadius;
  let width = props.width || "100%";
  let size = props.size || "xs";
  let fontSize = props.fontSize || "1em";
  let fontWeight = props.fontWeight || "normal";
  let letterSpacing = props.letterSpacing || 1;
  let placeholder = props.placeholder || "";
  let defaultValue = props.defaultValue || "";
  let _value: { [key: string]: number } = {};
  if ((props.value || false) !== false) {
    _value["value"] = props.value;
  }
  let type = props.type || "text";
  let min = props.min || "";
  let max = props.max || "";
  let isDisabled = props.isDisabled || false;
  let isReadOnly = props.isReadOnly || false;
  let leftAddon = props.leftAddon || <></>;
  let onChange = props.onChange || voidMethod;
  let fontFamily = props.fontFamily || "";
  let _fontFamily: { [key: string]: string } = {};
  if (fontFamily != "") _fontFamily["fontFamily"] = fontFamily;
  let bgColor = props.bgColor || "white";
  let onBlur = props.onBlur || function () {};
  return (
    <>
      <InputGroup>
        <InputLeftAddon>{leftAddon}</InputLeftAddon>
        <Input
          {..._value}
          {..._fontFamily}
          onChange={onChange}
          transform="translateY(30%);"
          isReadOnly={isReadOnly}
          isDisabled={isDisabled}
          type={type}
          min={min}
          max={max}
          defaultValue={defaultValue}
          ref={ref}
          borderBottomColor={borderBottomColor}
          borderBottomWidth={borderBottomWidth}
          borderLeftColor={borderLeftColor}
          borderLeftWidth={borderLeftWidth}
          borderRadius={borderRadius}
          width={width}
          size={size}
          fontSize={fontSize}
          letterSpacing={letterSpacing}
          fontWeight={fontWeight}
          placeholder={placeholder}
          bgColor={bgColor}
          onBlur={onBlur}
        ></Input>
      </InputGroup>
    </>
  );
});

export const _InputLeftElement = forwardRef(function _InputLeftElement(
  props,
  ref
) {
  let borderBottomColor = props.borderBottomColor || "none";
  let borderBottomWidth = props.borderBottomWidth || 1;
  let borderLeftColor = props.borderLeftColor || "none";
  let borderLeftWidth = props.borderLeftWidth || 1;
  let borderRadius = props.borderRadius || inputConfig.borderRadius;
  let width = props.width || "100%";
  let size = props.size || "xs";
  let fontSize = props.fontSize || "1em";
  let fontWeight = props.fontWeight || "normal";
  let letterSpacing = props.letterSpacing || 1;
  let placeholder = props.placeholder || "";
  let defaultValue = props.defaultValue || "";
  let _value: { [key: string]: number } = {};
  if ((props.value || false) !== false) {
    _value["value"] = props.value;
  }
  let type = props.type || "text";
  let min = props.min || "";
  let max = props.max || "";
  let isDisabled = props.isDisabled || false;
  let isReadOnly = props.isReadOnly || false;
  let leftElement = props.leftElement || <></>;
  let onChange = props.onChange || voidMethod;
  let fontFamily = props.fontFamily || "";
  let _fontFamily: { [key: string]: string } = {};
  if (fontFamily != "") _fontFamily["fontFamily"] = fontFamily;
  let bgColor = props.bgColor || "white";
  let textAlign = props.textAlign || "";
  let onBlur = props.onBlur || voidMethod;
  let onFocus = props.onFocus || voidMethod;
  let onClick = props.onClick || voidMethod;
  let textTransform = props.textTransform || "";
  return (
    <>
      <InputGroup>
        <InputLeftElement>{leftElement}</InputLeftElement>
        <Input
          {..._value}
          {..._fontFamily}
          textAlign={textAlign}
          onChange={onChange}
          transform="translateY(30%);"
          isReadOnly={isReadOnly}
          isDisabled={isDisabled}
          type={type}
          min={min}
          max={max}
          defaultValue={defaultValue}
          ref={ref}
          borderBottomColor={borderBottomColor}
          borderBottomWidth={borderBottomWidth}
          borderLeftColor={borderLeftColor}
          borderLeftWidth={borderLeftWidth}
          borderRadius={borderRadius}
          width={width}
          size={size}
          fontSize={fontSize}
          letterSpacing={letterSpacing}
          fontWeight={fontWeight}
          placeholder={placeholder}
          bgColor={bgColor}
          onBlur={onBlur}
          onFocus={onFocus}
          onClick={onClick}
          textTransform={textTransform}
        ></Input>
      </InputGroup>
    </>
  );
});

export const _Select = forwardRef(function _Select(props, ref) {
  let options = props.options;
  let keys = Object.keys(options);
  let _options: ReactNode[] = [];
  for (let i = 0; i < keys.length; ++i) {
    _options.push(
      <option key={i} value={keys[i]}>
        {options[keys[i]]}
      </option>
    );
  }
  let width = props.width || "100%";
  let size = props.size || "xs";
  let fontSize = props.fontSize || "1em";
  let isDisabled = props.isDisabled || false;
  let borderRadius = props.borderRadius || 2.5;
  let variant = props.variant || "filled";
  let pointerEvents: { [key: string]: any } = {};
  if (isDisabled) {
    pointerEvents = { pointerEvents: "none" };
  }
  let fontFamily = props.fontFamily || "";
  return (
    <Select
      fontFamily={fontFamily}
      {...pointerEvents}
      variant={variant}
      value={props.value}
      onChange={props.onChange}
      size={size}
      width={width}
      fontSize={fontSize}
      ref={ref}
      borderRadius={borderRadius}
    >
      {..._options}
    </Select>
  );
});

export const _Label = ({
  children,
  color = "black",
  fontSize = "2vh",
  fontWeight = "normal",
  fontFamily = "",
  letterSpacing = "",
  textTransform = "none",
  textAlign = undefined,
  toggleVisibility = false,
  hiddenText = "• • • •",
  hide = false,
  _key = undefined,
}: {
  children: ReactNode;
  color?: string;
  fontSize?: number | string;
  fontWeight?: string;
  fontFamily?: string;
  letterSpacing?: string | number;
  textTransform?: ResponsiveValue<CSS.Property.TextTransform>;
  textAlign?: ResponsiveValue<CSS.Property.TextAlign>;
  toggleVisibility?: boolean;
  hiddenText?: string;
  hide?: boolean;
  _key?: string;
}) => {
  let _fontFamily: { [key: string]: string } = {};
  if (fontFamily != "") _fontFamily["fontFamily"] = fontFamily;

  // Show Status
  const [showText, setShowText] = useState<boolean>(
    toggleVisibility !== true ? true : hide === true ? false : true
  );

  const [hideStatus, setHideStatus] = useState<boolean>(hide);

  if (hide !== hideStatus) {
    setHideStatus(hide);
    setShowText(hide);
  }

  let key: { [key: string]: string } = {};
  if (_key) key["key"] = _key;
  return (
    <Text
      {...key}
      {..._fontFamily}
      color={color}
      textTransform={textTransform}
      letterSpacing={letterSpacing}
      fontWeight={fontWeight}
      fontSize={fontSize}
      textAlign={textAlign}
      onClick={() => {
        if (toggleVisibility) {
          setShowText(!showText);
        }
      }}
    >
      {showText ? children : hiddenText}
    </Text>
  );
};

// Tab Button
export const TabButton = ({ icon, label }: { icon: any; label: string }) => {
  return (
    <>
      {icon}
      &nbsp;{label}
    </>
  );
};

// Divider
export const _Divider = ({
  margin = { md: 2, sm: 2, lg: 5 },
  borderColor = "",
}: {
  margin?: any;
  borderColor?: string;
}) => {
  return (
    <Divider
      borderColor={borderColor}
      marginTop={margin}
      marginBottom={margin}
    ></Divider>
  );
};

// Currency
export const CurrencyIcon = ({
  currency = <PiCurrencyDollarSimpleDuotone color="#85bb65" />,
}: {
  currency?: ReactNode;
}) => {
  return <>{currency}</>;
};

/* Home */
export const HomeNavButton = () => {
  return (
    <Box width="100%">
      <_Button
        color="white"
        bgColor={navBgColor}
        label="Home"
        fontSize="1.2em"
        icon={<FcHome />}
        onClick={() => {
          window.location.href = "/";
        }}
      ></_Button>
      <_Divider margin={2} borderColor="black"></_Divider>
    </Box>
  );
};
