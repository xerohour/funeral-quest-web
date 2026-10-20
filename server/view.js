import { rideOf } from "./catalog.js";

function decodeBody(body) {
  return String(body)
    .replace(/\/r\/n$/, "")
    .replaceAll("+", " ")
    .replaceAll("%22", '"')
    .replaceAll("%21", "!")
    .replaceAll("%2C", ",")
    .replaceAll("%3B", ";")
    .replaceAll("%3A", ":");
}

function plain(value) {
  return String(value ?? "")
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<[^>]+>/g, "")
    .replaceAll("&amp;", "&")
    .replaceAll("&lt;", "<")
    .replaceAll("&gt;", ">")
    .trim();
}

export function screenFromBody(body) {
  const fields = {};
  for (const part of decodeBody(body).split("&")) {
    if (!part) continue;
    const eq = part.indexOf("=");
    if (eq === -1) continue;
    fields[part.slice(0, eq)] = part.slice(eq + 1);
  }
  const actions = [];
  for (const key of ["3", "2", "1", "0", "a"]) {
    const label = fields[`st_option_${key}`];
    if (!label) continue;
    const action = { command: Number(fields[`i_command_${key}`]), label: label.trim() };
    if (fields[`i_command_sub_${key}`] != null && fields[`i_command_sub_${key}`] !== "") {
      action.sub = Number(fields[`i_command_sub_${key}`]);
    }
    actions.push(action);
  }
  const sent = Number(fields.i_sent);
  let kind = "location";
  if (fields.g_st_error) kind = "error";
  else if (sent === 5) kind = "news";
  else if (fields.st_object === "i_map") kind = "map";
  else if (fields.st_object === "m_main" && actions.length === 0) kind = "desk";
  else if (fields.st_url?.includes("funeral-ceremony")) kind = "ceremony";
  else if (fields.st_url?.includes("/people/")) kind = "customer";
  else if (fields.st_url?.includes("closed")) kind = "closed";
  else if (fields.st_object === "i_get_text" || fields.st_object === "i_getname") kind = "prompt";
  else if (fields.st_url?.includes("morning_coffee")) kind = "morning";
  const story = plain(fields.st_main || fields.st_message || fields.st_news || fields.g_st_error || fields.st_game_stats || "");
  return {
    kind,
    story,
    art: fields.st_url || fields.st_url_bg || "",
    prompt: kind === "prompt",
    promptKind: fields.st_object === "i_getname" ? "i_getname" : fields.st_object === "i_get_text" ? "i_get_text" : "",
    returnCommand: Number(fields.i_return_command || 0),
    returnSub: Number(fields.i_return_command_sub || 0),
    date: plain(fields.st_date || ""),
    topToday: plain(fields.st_top_today || ""),
    actions,
  };
}

export function publicPlayer(player) {
  if (!player) return null;
  return {
    name: player.name,
    logon: player.logon,
    funeralName: player.funeralName,
    money: player.money,
    turns: player.turns,
    exp: player.exp,
    buryPoints: player.buryPoints,
    luck: player.luck,
    wood: player.wood,
    silver: player.silver,
    gold: player.gold,
    plots: player.plots,
    transportName: rideOf(player).name,
    lockpicks: player.lockpicks ?? 0,
    hp: player.hp ?? 10,
    strength: player.strength ?? 3,
    psych: player.psych ?? 0,
  };
}

export const PARLOR_ACTIONS = [
  { command: 2, label: "Wait for a customer" },
  { command: 5, label: "News" },
  { command: 9, label: "Rankings" },
  { command: 12, label: "Town map" },
  { command: 13, label: "Yang's" },
  { command: 40, label: "Di-Mart" },
  { command: 39, label: "Bank" },
  { command: 37, label: "Hospital" },
  { command: 34, label: "Church" },
  { command: 22, label: "Bar" },
  { command: 3, label: "Parlor stats" },
  { command: 4, label: "Back to desk" },
  { command: 1, label: "Log off" },
];
