const COLORS = {
  1: "#0000A8",
  2: "#007000",
  3: "#008080",
  4: "#FF0000",
  5: "#CC66CC",
  6: "#A85400",
  7: "#808080",
  8: "#505050",
  9: "#5454FC",
  0: "#00B000",
  "!": "#00A0A0",
  "@": "#FC5454",
  "#": "#FC54FC",
  $: "#FFFF33",
  y: "#FFFF33",
  w: "#FFFFFF",
  "^": "#000000",
};

/** Expand Funeral Quest backtick color codes the way funeral_util.cpp does. */
export function sethAnsi(input) {
  let out = "";
  const text = String(input);
  for (let i = 0; i < text.length; i++) {
    if (text[i] !== "`" || i + 1 >= text.length) {
      out += text[i];
      continue;
    }
    const code = text[++i];
    if (code === "=") {
      out += "%3D";
      continue;
    }
    if (code === "f" && i + 1 < text.length) {
      const size = 8 + Number(text[++i]) * 2;
      out += `<font size="${Number.isFinite(size) ? size : 12}">`;
      continue;
    }
    if (code === "a") continue;
    const color = COLORS[code];
    if (color) out += `<font color="${color}">`;
  }
  return out;
}

/** Match shared_util.cpp format_for_web_cst. Ampersands and equals stay intact. */
export function formatForWeb(value) {
  return String(value)
    .replaceAll(" ", "+")
    .replaceAll('"', "%22")
    .replaceAll("!", "%21")
    .replaceAll(",", "%2C")
    .replaceAll(";", "%3B")
    .replaceAll(":", "%3A");
}

export function cash(amount) {
  const negative = amount < 0;
  const digits = String(Math.abs(Math.trunc(amount)));
  const withCommas = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return `${negative ? "-" : ""}${withCommas}.00`;
}

export function decodeToken(value) {
  return String(value ?? "")
    .replaceAll("+", " ")
    .replace(/%([0-9a-fA-F]{2})/g, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
}

export function varsToBody(fields) {
  const raw = fields.map(([name, value]) => `${name}=${String(value).replaceAll("\r\n", "<br>").replaceAll("\n", "<br>")}`).join("&") + "&";
  const colored = sethAnsi(raw);
  return formatForWeb(colored) + "/r/n";
}
