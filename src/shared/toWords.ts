const ENDS_WITH_DOUBLE_ZERO_PATTERN =
  /(hundred|thousand|(m|b|tr|quadr)illion)$/;
const ENDS_WITH_TEEN_PATTERN = /teen$/;
const ENDS_WITH_Y_PATTERN = /y$/;
const ENDS_WITH_ZERO_THROUGH_TWELVE_PATTERN =
  /(zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)$/;
const ordinalLessThanThirteen: { [key: string]: string } = {
  zero: "zeroth",
  one: "first",
  two: "second",
  three: "third",
  four: "fourth",
  five: "fifth",
  six: "sixth",
  seven: "seventh",
  eight: "eighth",
  nine: "ninth",
  ten: "tenth",
  eleven: "eleventh",
  twelve: "twelfth",
};

/**
 * Converts a number-word into an ordinal number-word.
 * @example makeOrdinal('one') => 'first'
 * @param {string} words
 * @returns {string}
 */
const makeOrdinal = (words: any): string => {
  // Ends with *00 (100, 1000, etc.) or *teen (13, 14, 15, 16, 17, 18, 19)
  if (
    ENDS_WITH_DOUBLE_ZERO_PATTERN.test(words) ||
    ENDS_WITH_TEEN_PATTERN.test(words)
  ) {
    return words + "th";
  }
  // Ends with *y (20, 30, 40, 50, 60, 70, 80, 90)
  else if (ENDS_WITH_Y_PATTERN.test(words)) {
    return words.replace(ENDS_WITH_Y_PATTERN, "ieth");
  }
  // Ends with one through twelve
  else if (ENDS_WITH_ZERO_THROUGH_TWELVE_PATTERN.test(words)) {
    return words.replace(
      ENDS_WITH_ZERO_THROUGH_TWELVE_PATTERN,
      replaceWithOrdinalVariant
    );
  }
  return words;
};

const replaceWithOrdinalVariant = (_: any, numberWord: string): any => {
  return ordinalLessThanThirteen[numberWord];
};

const TEN = 10;
const ONE_HUNDRED = 100;
const ONE_THOUSAND = 1000;
const ONE_MILLION = 1000000;
const ONE_BILLION = 1000000000; //         1.000.000.000 (9)
const ONE_TRILLION = 1000000000000; //     1.000.000.000.000 (12)
const ONE_QUADRILLION = 1000000000000000; // 1.000.000.000.000.000 (15)
const MAX = 9007199254740992; // 9.007.199.254.740.992 (15)

const LESS_THAN_TWENTY = [
  "zero",
  "one",
  "two",
  "three",
  "four",
  "five",
  "six",
  "seven",
  "eight",
  "nine",
  "ten",
  "eleven",
  "twelve",
  "thirteen",
  "fourteen",
  "fifteen",
  "sixteen",
  "seventeen",
  "eighteen",
  "nineteen",
];

const TENTHS_LESS_THAN_HUNDRED = [
  "zero",
  "ten",
  "twenty",
  "thirty",
  "forty",
  "fifty",
  "sixty",
  "seventy",
  "eighty",
  "ninety",
];

/**
 * Converts an integer into words.
 * If number is decimal, the decimals will be removed.
 * @example toWords(12) => 'twelve'
 * @param {number|string} number
 * @param {boolean} [asOrdinal] - Deprecated, use toWordsOrdinal() instead!
 * @param {boolean} doProcessDecimal
 * @returns {string}
 */
const toWords = (
  _number: string | number,
  asOrdinal: boolean = false,
  doProcessDecimal: boolean = false
): string => {
  var words;
  let num = undefined;
  let number: number;
  if (typeof _number === "string") num = parseInt(_number, 10);
  else num = _number;

  number = num;

  if (!isFinite(num))
    throw new TypeError(
      "Not a finite number: " + number + " (" + typeof number + ")"
    );
  words = generateWords(num);

  /* Cents */
  let cents = "";

  let result = asOrdinal ? makeOrdinal(words) : words;

  /* Process Decimal Places */
  if (doProcessDecimal) {
    /* Check for Decimal Number */
    if (number - num > 0.0) {
      let index = ((number - num) * 100).toFixed(2);
      cents = toWords(index);
    }

    if (result.trim() !== "zero") result = `${result} and `;
    else result = "";

    /* Store Final Placeholder text */
    let placeholder_text = "";
    if (cents.trim().length != 0) placeholder_text = `${result} ${cents} cents`;
    else placeholder_text = `${result}`;
    return placeholder_text + " only";
  }

  if (result == "zero") return "";
  return `${result}`;
};

const generateWords = (number: number, arg: string[] = []): string => {
  var remainder, word;
  let words: string[] = arg;

  // We’re done
  if (number === 0) {
    return !words ? "zero" : words.join(" ").replace(/,$/, "");
  }
  // First run
  if (!words) {
    words = [];
  }
  // If negative, prepend “minus”
  if (number < 0) {
    words.push("minus");
    number = Math.abs(number);
  }

  if (number < 20) {
    remainder = 0;
    word = LESS_THAN_TWENTY[number];
  } else if (number < ONE_HUNDRED) {
    remainder = number % TEN;
    word = TENTHS_LESS_THAN_HUNDRED[Math.floor(number / TEN)];
    // In case of remainder, we need to handle it here to be able to add the “-”
    if (remainder) {
      word += " " + LESS_THAN_TWENTY[remainder];
      remainder = 0;
    }
  } else if (number < ONE_THOUSAND) {
    remainder = number % ONE_HUNDRED;
    word = generateWords(Math.floor(number / ONE_HUNDRED)) + " hundred";
  } else if (number < ONE_MILLION) {
    remainder = number % ONE_THOUSAND;
    word = generateWords(Math.floor(number / ONE_THOUSAND)) + " thousand,";
  } else if (number < ONE_BILLION) {
    remainder = number % ONE_MILLION;
    word = generateWords(Math.floor(number / ONE_MILLION)) + " million,";
  } else if (number < ONE_TRILLION) {
    remainder = number % ONE_BILLION;
    word = generateWords(Math.floor(number / ONE_BILLION)) + " billion,";
  } else if (number < ONE_QUADRILLION) {
    remainder = number % ONE_TRILLION;
    word = generateWords(Math.floor(number / ONE_TRILLION)) + " trillion,";
  } else if (number <= MAX) {
    remainder = number % ONE_QUADRILLION;
    word =
      generateWords(Math.floor(number / ONE_QUADRILLION)) + " quadrillion,";
  }

  words.push(word || "");
  return generateWords(remainder || 0, words);
};

/**
 * Capitalize words in the text
 * @param {string} text
 * @returns {string}
 */
const capitalize_words = (text: string): string => {
  return text.replace(/\w\S*/g, (w) =>
    w.replace(/^\w/, (c) => c.toUpperCase())
  );
};

const PLACEHOLDER_CHAR_LIMIT = 134;

/**
 * This method will return amount in words.
 * @param {number} totalAmount
 * @returns {string}
 */
export const getAmountInWords = (totalAmount: number): string => {
  let wholeTotalAmount = parseInt(`${totalAmount}`);
  let amountInWords = capitalize_words(toWords(wholeTotalAmount, false, false));

  /* Amount In words */
  /* Append - */
  let len = amountInWords.length;
  let decimalText = "";
  let decimalAmount: any = totalAmount - wholeTotalAmount;

  /* Build Decimal Text */
  decimalAmount = parseInt((decimalAmount * 100).toFixed(2));
  decimalText = `${decimalAmount} / 100`;

  /* Build dash count */
  let dashCount = PLACEHOLDER_CHAR_LIMIT - decimalText.length - len;
  let dashStr = "";
  for (let i = 0; i < dashCount; ++i) dashStr += "-";

  return `${amountInWords} ${dashStr} ${decimalText}`;
};
