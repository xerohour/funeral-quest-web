import fs from "node:fs";
import path from "node:path";
import { cash, decodeToken, varsToBody } from "./ansi.js";
import { rideOf, TRANSPORT } from "./catalog.js";
import { createScriptRuntime } from "./fqscript.js";

const COMMAND = {
  quit: 1,
  wait: 2,
  stats: 3,
  main: 4,
  news: 5,
  yesterday: 7,
  announcement: 8,
  ranking: 9,
  map: 12,
  yangs: 13,
  bar: 22,
  chatStart: 23,
  chatUpdate: 24,
  soft: 25,
  hard: 26,
  reaction: 11,
  sympathy: 27,
  guilt: 29,
  finish: 30,
  riskAccept: 31,
  cityHall: 32,
  church: 34,
  graveyard: 35,
  spirit: 36,
  hospital: 37,
  comp: 38,
  bank: 39,
  store: 40,
  options: 43,
  script: 44,
  front: 41,
  crime: 42,
  getname: 17,
  mail: 20,
  reply: 21,
  bankCallback: 104,
  storeCallback: 105,
  yangsCallback: 14,
  spiritCallback: 100,
  hospitalCallback: 101,
  compCallback: 102,
  battleCallback: 103,
  optionsCallback: 108,
  scriptCallback: 109,
};

const SENT = {
  fail: -1,
  intro: 1,
  unknownUser: 2,
  newDetails: 3,
  newName: 4,
  news: 5,
  quit: 1,
  loadMovie: 2,
  object: 10,
  add: 14,
  callback: 16,
  ranking: 9,
  generic: 33,
};

const CASKETS = [
  { id: "wood", label: "wood", price: 800, points: 1 },
  { id: "silver", label: "silver", price: 2600, points: 2 },
  { id: "gold", label: "gold", price: 5300, points: 3 },
];

const PEOPLE = {
  male: {
    teen: ["flash/people/m_teen.swf", "flash/people/m_teen_2.swf"],
    adult: ["flash/people/m_adult.swf", "flash/people/m_adult_2.swf", "flash/people/m_adult_3.swf", "flash/people/m_adult_4.swf"],
    old: ["flash/people/m_old.swf", "flash/people/m_old_2.swf", "flash/people/m_old_5.swf", "flash/people/m_old_6.swf"],
  },
  female: {
    teen: ["flash/people/f_teen.swf", "flash/people/f_teen_2.swf"],
    adult: ["flash/people/f_adult.swf", "flash/people/f_adult_2.swf"],
    old: ["flash/people/f_old.swf", "flash/people/f_old_2.swf"],
  },
};

const FIRST_NAMES = {
  male: ["Arthur", "Walter", "Edgar", "Frank", "Harold", "George", "Henry", "Albert"],
  female: ["Helen", "Ruth", "Mabel", "Clara", "Edna", "Margaret", "Dorothy", "Evelyn"],
};
const DEATH_OBJECTS = ["car", "tractor", "fridge", "piano", "bull", "elephant", "truck", "fork-lift", "exercise machine", "power boat", "computer", "house"];
const DEATH_METHODS = ["hit by", "smashed by", "run over by", "thrown by", "crushed by", "done in by", "fatally stuck inside", "smacked a good one by"];
const INCOME_BUDGET = {
  Poor: [801, 2800],
  Average: [1900, 5400],
  Rich: [3700, 7530],
  "Super Rich": [4600, 18500],
};

function pick(list) {
  return list[Math.floor(Math.random() * list.length)];
}

function between(min, max) {
  return min + Math.floor(Math.random() * (max - min + 1));
}

function parseRequest(url) {
  const marker = "request.xp?";
  const at = url.indexOf(marker);
  if (at === -1) return null;
  let rest = url.slice(at + marker.length);
  const hash = rest.indexOf("#");
  if (hash !== -1) rest = rest.slice(0, hash);
  const question = rest.indexOf("?");
  const amp = rest.indexOf("&");
  let split = rest.length;
  if (question !== -1) split = question;
  if (amp !== -1 && amp < split) split = amp;
  const command = decodeToken(rest.slice(0, split));
  const query = rest.slice(split).replace(/^[?&]/, "");
  const tokens = {};
  if (query) {
    for (const part of query.split("&")) {
      if (!part) continue;
      const eq = part.indexOf("=");
      const key = decodeToken(eq === -1 ? part : part.slice(0, eq));
      const value = decodeToken(eq === -1 ? "" : part.slice(eq + 1));
      tokens[key] = value;
    }
  }
  return { command, tokens };
}

function stats(player) {
  return [
    ["i_money", cash(player.money)],
    ["i_exp", cash(player.exp)],
    ["i_turns", cash(player.turns)],
    ["i_sp", cash(player.buryPoints)],
    ["i_luck", player.luck],
    ["i_coffin_wood", cash(player.wood)],
    ["i_coffin_silver", cash(player.silver)],
    ["i_coffin_gold", cash(player.gold)],
  ];
}

function scene(fields) {
  return varsToBody(fields);
}

function continueButton(command = COMMAND.main) {
  return [
    ["st_option_3", "Continue"],
    ["i_command_3", command],
    ["b_kill_buttons", 1],
  ];
}

function ageBand(age) {
  if (age < 20) return "teen";
  if (age < 55) return "adult";
  return "old";
}

function ageLabel(age) {
  const band = ageBand(age);
  if (band === "teen") return "teenage";
  if (band === "adult") return "middle aged";
  return "old";
}

const bootedAt = Date.now();

export function createGame(store, publicRoot) {
  function art(relative) {
    const file = path.join(publicRoot, relative.replaceAll("/", path.sep));
    return fs.existsSync(file) ? relative : "flash/places/desk.swf";
  }

  function center(text, width) {
    if (text.length >= width) return text;
    return `${" ".repeat(Math.floor((width - text.length) / 2))}${text}`;
  }

  function uptime() {
    const seconds = Math.max(1, (Date.now() - bootedAt) / 1000);
    if (seconds / 60 > 59) return `${String(Math.round(seconds / 3600)).padStart(6)} Hour(s)`;
    if (seconds > 59) return `${String(Math.round(seconds / 60)).padStart(6)} Min`;
    return `${String(Math.round(seconds)).padStart(6)} Sec`;
  }

  function intro() {
    const online = Object.values(store.state.sessions).filter((entry) => entry.playerId != null).length;
    const population = store.state.players.length;
    return scene([
      ["st_intro_news", `<u><a href="http://www.rtsoft.com/fq">FQServer 1.0.</a></u> Copyright 2001-2011: <u><a href="http://www.rtsoft.com">Robinson Technologies</a></u>  Uptime:${uptime()}`],
      ["st_no_more", ""],
      ["st_game_stats", center(`Game Info: No Reset Mode Active (day ${store.state.day}) - ${online} of ${population} players on now.`, 120)],
      ["st_server_news", " "],
      ["i_sent", SENT.intro],
    ]);
  }

  function sellerOfTheDay() {
    let best = null;
    let score = 0;
    for (const person of store.state.players) {
      if (person.active === false) continue;
      if ((person.dayLastLoggedOn ?? 0) !== store.state.day) continue;
      if ((person.buryPoints ?? 0) > score) {
        best = person;
        score = person.buryPoints;
      }
    }
    return best;
  }

  function highestExperience(skip = []) {
    let best = null;
    let score = 0;
    for (const person of store.state.players) {
      if (person.active === false || skip.includes(person)) continue;
      if ((person.exp ?? 0) > score) {
        best = person;
        score = person.exp;
      }
    }
    return best;
  }

  function topSeller() {
    return sellerOfTheDay();
  }

  function paper(items, empty) {
    return items?.length ? items.join("\n\n") : empty;
  }

  function showFront(session, day) {
    session.newsDay = day;
    const yesterday = day !== store.state.day;
    const items = yesterday ? store.state.yesterday : store.state.news;
    const top = topSeller();
    return scene([
      ["st_date", `Day ${day}`],
      ["st_top_today", top ? `${top.name} from ${top.funeralName} with ${top.buryPoints} Bury Points.` : "Sadly, no one has managed to sell anything yet today."],
      ["st_top_yesterday", store.state.topYesterday || "No one died yesterday."],
      ["st_news", paper(items, "Nothing has happened today.")],
      ["i_yesterday", yesterday ? 1 : 0],
      ["i_sent", SENT.news],
    ]);
  }

  function showCrime(session) {
    const day = session.newsDay ?? store.state.day;
    const yesterday = day !== store.state.day;
    const items = yesterday ? store.state.crimeYesterday : store.state.crime;
    return scene([
      ["st_date", `Day ${day}`],
      ["st_news", paper(items, "There has been no crime reported today.")],
      ["i_yesterday", yesterday ? 1 : 0],
      ["i_sent", SENT.news],
    ]);
  }

  function addNews(text) {
    store.state.news = store.state.news || [];
    store.state.news.push(text);
  }

  function linkify(text) {
    let work = text;
    for (;;) {
      const start = work.indexOf("[");
      const end = work.indexOf("]", start + 1);
      if (start === -1 || end === -1) return work;
      const url = work.slice(start + 1, end).replaceAll("[", "").replaceAll("]", "");
      const link = `\`#<a href="http://${url}" target="_blank">${url}\`6</a>`;
      work = `${work.slice(0, start)}${link}${work.slice(end + 1)}`;
    }
  }

  function announce(session, player, tokens) {
    const message = linkify(filterChat(String(tokens.st_note || "")).replaceAll("`", "").replace(/[<&^*+]/g, ""));
    addNews(`\`8${player.name} of ${player.funeralName} makes an announcement:\`6\n`);
    addNews(`${message}\n\n`);
    store.save();
    return showFront(session, store.state.day);
  }

  function loadFortunes(name) {
    const file = path.join(publicRoot, "..", name);
    if (!fs.existsSync(file)) return ["The paper is blank!"];
    const lines = fs.readFileSync(file, "utf8")
      .split(/\r?\n/)
      .filter((line) => line.startsWith("message|"))
      .map((line) => line.slice("message|".length).trim())
      .filter(Boolean);
    return lines.length ? lines : ["The paper is blank!"];
  }

  function loadRatings() {
    const file = path.join(publicRoot, "..", "ratings.txt");
    const ratings = new Map();
    if (!fs.existsSync(file)) return ratings;
    for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
      const bar = line.indexOf("|");
      if (bar < 1) continue;
      const index = Number(line.slice(0, bar));
      if (index >= 1 && index <= 100) ratings.set(index, line.slice(bar + 1));
    }
    return ratings;
  }

  function loadSellItems(name) {
    const file = path.join(publicRoot, "..", name);
    const items = [];
    let current = { article: "a", name: "", price: 0 };
    if (!fs.existsSync(file)) return items;
    for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
      const parts = line.split("|");
      const key = parts[0];
      const value = parts[1] ?? "";
      if (key === "reference") current.article = value;
      else if (key === "name") current.name = value;
      else if (key === "price") current.price = Number(value) || 0;
      else if (key === "add_item") {
        if (current.name) items.push({ article: current.article, name: current.name, price: current.price });
        current = { article: "a", name: "", price: 0 };
      }
    }
    return items;
  }

  const ratings = loadRatings();
  const scriptRoot = path.join(publicRoot, "..", "script");
  const scriptRuns = new Map();
  let scriptGlobals = null;

  function readScriptText(file) {
    const buf = fs.readFileSync(file);
    if (buf.length >= 2 && buf[0] === 0xff && buf[1] === 0xfe) return buf.subarray(2).toString("utf16le");
    return buf.toString("utf8").replace(/^\uFEFF/, "");
  }

  function scriptFile(relative) {
    const slashed = String(relative || "").replaceAll("\\", "/").replace(/^script\//, "");
    const file = path.resolve(scriptRoot, slashed);
    const fromRoot = path.relative(scriptRoot, file);
    if (!slashed || fromRoot.startsWith("..") || path.isAbsolute(fromRoot) || !fs.existsSync(file)) return null;
    return file;
  }

  function ensureScriptGlobals() {
    if (scriptGlobals) return scriptGlobals;
    scriptGlobals = new Map([
      ["true", { type: "int", value: 1 }],
      ["false", { type: "int", value: 0 }],
    ]);
    const file = scriptFile("init.c");
    if (!file) return scriptGlobals;
    const runtime = createScriptRuntime({
      globals: scriptGlobals,
      call() {
        return { type: "int", value: 0 };
      },
      spawn() {
        return null;
      },
    });
    runtime.start(readScriptText(file), "init.c", []);
    return scriptGlobals;
  }

  function buttonKey(number) {
    return { 1: "3", 2: "2", 3: "1", 4: "0", 5: "a" }[number] || "3";
  }

  function centerButton(label) {
    const text = String(label ?? "");
    if (text.length >= 10) return text;
    return `${" ".repeat(Math.floor((15 - text.length) / 2))}${text}`;
  }

  function addPacketButton(packet, number, label, command, sub) {
    const key = buttonKey(number);
    let next = `${packet}st_option_${key}=${centerButton(label)}&i_command_${key}=${command}&`;
    if (sub != null) next += `i_command_sub_${key}=${sub}&`;
    return next;
  }

  function statPacket(person) {
    const standing = rankOf(person);
    const rating = ratings.get(standing.percent) || "(No text for this rating.  Email Seth some ideas!)";
    const fields = [
      ...stats(person),
      ["i_plots", cash(person.plots ?? 0)],
      ["i_psych", `Level ${cash((person.psych ?? 0) + 1)}`],
      ["st_rating", `#${standing.rank} - ${rating}`],
      ["i_yangs", cash(Math.max(0, 1 - (person.yangsSent ?? 0)))],
      ["i_visits", cash(Math.max(0, 2 - (person.visits ?? 0)))],
      ["i_hit_max", cash(person.hpMax ?? 10)],
      ["i_strength", cash(person.strength ?? 3)],
      ["i_lockpick", cash(person.lockpicks ?? 0)],
      ["st_ride", rideOf(person).name],
    ];
    return fields.map(([key, value]) => `${key}=${value}&`).join("");
  }

  function packetToScene(body) {
    if (!body) return null;
    const fields = [];
    for (const part of String(body).split("&")) {
      if (!part) continue;
      const eq = part.indexOf("=");
      const key = eq === -1 ? part : part.slice(0, eq);
      let value = eq === -1 ? "" : part.slice(eq + 1);
      if (key === "st_url" || key === "st_url_bg") value = value.replaceAll("\\", "/");
      fields.push([key, value]);
    }
    return scene(fields);
  }

  function finishPacket(body, mode) {
    let packet = String(body ?? "");
    const creates = packet.includes("st_object=");
    if (mode === "overlay" && creates) packet += "i_return_command=109&";
    let sent = SENT.generic;
    if (creates) sent = mode === "overlay" ? SENT.add : SENT.object;
    else if (mode === "stats") sent = 3;
    return `${packet}i_sent=${sent}&`;
  }

  const customFields = ["smoke", "safe", "padlock", "lockpicks", "padlockStrength"];
  const tagFields = ["wood", "silver", "gold"];

  function customValue(person, index) {
    if (!person) return 0;
    const field = customFields[index];
    if (!field) return 0;
    if (field === "smoke" || field === "safe" || field === "padlock") return person[field] ? 1 : 0;
    return Math.trunc(person[field] ?? 0);
  }

  function setCustom(person, index, value) {
    const field = customFields[index];
    if (!person || !field) return;
    const next = Math.trunc(value);
    if (field === "smoke" || field === "safe" || field === "padlock") person[field] = next ? 1 : 0;
    else person[field] = next;
  }

  function hostCall(session, key, args, api, playerIndexValue) {
    const text = (index) => String(args[index]?.value ?? "");
    const num = (index) => Math.trunc(Number(args[index]?.value) || 0);
    const person = () => playerAt(playerIndexValue);
    if (key === "ADDCUSTOM") return { type: "string", value: `${text(0)}${text(1)}=${text(2)}&` };
    if (key === "ADDBUTTON") return { type: "string", value: addPacketButton(text(0), num(1), text(2), COMMAND.scriptCallback, num(3)) };
    if (key === "ADDBUTTONTOWNMAP") return { type: "string", value: addPacketButton(text(0), num(1), text(2), COMMAND.map, null) };
    if (key === "ADDBUTTONHOME") return { type: "string", value: addPacketButton(text(0), num(1), text(2), COMMAND.main, null) };
    if (key === "ADDBUTTONSTORE") return { type: "string", value: addPacketButton(text(0), num(1), text(2), COMMAND.store, null) };
    if (key === "ADDBUTTONCHANGENAME") return { type: "string", value: addPacketButton(text(0), num(1), text(2), COMMAND.options, null) };
    if (key === "ADDUPDATETAGS") {
      const owner = person();
      const tags = tagFields.map((field) => `i_coffin_${field}=${cash(owner?.[field] ?? 0)}&`).join("");
      return { type: "string", value: text(0) + tags };
    }
    if (key === "ADDUPDATESTATS") {
      const owner = person();
      return { type: "string", value: owner ? text(0) + statPacket(owner) : text(0) };
    }
    if (key === "SETLOCATION") {
      session.location = text(0);
      return { type: "int", value: 0 };
    }
    if (key === "SENDPACKETANDWAIT") return { wait: true, packet: finishPacket(text(0), "wait") };
    if (key === "SENDPACKETANDWAITOVERLAY") return { wait: true, packet: finishPacket(text(0), "overlay") };
    if (key === "SENDPACKETANDWAITSTATS") return { wait: true, packet: finishPacket(text(0), "stats") };
    if (key === "STRINGEXPAND") return { type: "string", value: text(0) };
    if (key === "LOGMSG" || key === "LOGERROR") return { type: "int", value: 0 };
    if (key === "RANDOMRANGE") return { type: "int", value: between(num(0), num(1)) };
    if (key === "RANDOM") return { type: "int", value: between(0, num(0)) };
    if (key === "GETTAGS") {
      const owner = playerAt(num(0));
      const field = tagFields[num(1)];
      return { type: "int", value: owner && field ? Math.trunc(owner[field] ?? 0) : 0 };
    }
    if (key === "SETTAGS" || key === "MODTAGS") {
      const owner = playerAt(num(0));
      const field = tagFields[num(1)];
      if (owner && field) owner[field] = key === "SETTAGS" ? num(2) : Math.trunc((owner[field] ?? 0) + num(2));
      return { type: "int", value: owner && field ? Math.trunc(owner[field] ?? 0) : 0 };
    }
    if (key === "GETCUSTOMBYID") return { type: "int", value: customValue(playerAt(num(0)), num(1)) };
    if (key === "SETCUSTOMBYID" || key === "MODCUSTOMBYID") {
      const owner = playerAt(num(0));
      const current = customValue(owner, num(1));
      setCustom(owner, num(1), key === "SETCUSTOMBYID" ? num(2) : current + num(2));
      return { type: "int", value: customValue(owner, num(1)) };
    }
    if (key === "ISPLAYERONNOW") {
      const owner = playerAt(num(0));
      return { type: "int", value: owner && isOnline(owner, null) ? 1 : 0 };
    }
    if (key === "GETNAMEFROMID") return { type: "string", value: playerAt(num(0))?.name || "" };
    if (key === "GETHOMENAMEFROMID") return { type: "string", value: playerAt(num(0))?.funeralName || "" };
    if (key === "GETTOTALBURIALS") return { type: "int", value: Math.trunc(playerAt(num(0))?.plots ?? 0) };
    if (key === "MODTOTALBURIALS") {
      const owner = playerAt(num(0));
      if (owner) owner.plots = Math.trunc((owner.plots ?? 0) + num(1));
      return { type: "int", value: Math.trunc(owner?.plots ?? 0) };
    }
    if (key === "ADDTONEWS") {
      addNews(text(0));
      return { type: "int", value: 0 };
    }
    if (key === "BROADCASTTV") {
      if (store.state.tvOn !== false) pushChat(`\`7The TV: \`w${text(0)}`);
      return { type: "int", value: 0 };
    }
    if (key === "MAILTEXT") {
      const owner = playerAt(num(0));
      if (owner) {
        owner.inbox = owner.inbox || [];
        owner.inbox.push({ text: text(1), art: "" });
      }
      return { type: "int", value: 0 };
    }
    if (key === "MAILPICTURE") {
      const owner = playerAt(num(0));
      const note = owner?.inbox?.[owner.inbox.length - 1];
      if (note && !note.letter) note.art = text(1).replaceAll("\\", "/");
      return { type: "int", value: 0 };
    }
    if (key === "MAILCONTINUEBUTTON") return { type: "int", value: 0 };
    if (key === "RUNSCRIPTNORETURN") {
      api.spawn(text(0));
      return { type: "int", value: 0 };
    }
    return { type: "int", value: 0 };
  }

  function beginScript(session, player, relativePath) {
    const file = scriptFile(relativePath);
    if (!file || !player) return null;
    const index = playerIndex(player);
    const runtime = createScriptRuntime({
      globals: ensureScriptGlobals(),
      call(name, values, api) {
        return hostCall(session, name, values, api, index);
      },
      spawn(scriptPath) {
        return beginScript(session, player, scriptPath);
      },
    });
    scriptRuns.set(session.id, runtime);
    runtime.start(readScriptText(file), path.basename(file), [
      ["i_my_id", { type: "int", value: index }],
      ["i_my_result", { type: "int", value: 0 }],
      ["st_my_name", { type: "string", value: player.name }],
      ["st_my_text", { type: "string", value: "" }],
    ]);
    return runtime;
  }

  function startFqScript(session, player, relativePath) {
    const runtime = beginScript(session, player, relativePath);
    if (!runtime) return main(session, player);
    store.save();
    return packetToScene(scriptRuns.get(session.id)?.packetOf()) || main(session, player);
  }

  function resumeFqScript(session, player, result) {
    const runtime = scriptRuns.get(session.id);
    if (!runtime?.waiting()) return main(session, player);
    runtime.resume(result);
    store.save();
    return packetToScene(scriptRuns.get(session.id)?.packetOf()) || main(session, player);
  }
  function loadStoryEvents(name, picture = "flash/places/embalming-room.swf") {
    const file = path.join(publicRoot, "..", name);
    const events = [];
    if (!fs.existsSync(file)) return events;
    let low = -229999999;
    let high = 299999999;
    let current = null;
    const flush = () => {
      if (current?.message) events.push(current);
      current = null;
    };
    for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("//")) continue;
      const parts = trimmed.split("|");
      const key = parts[0];
      if (key === "range") {
        flush();
        low = Number(parts[1]);
        high = Number(parts[2]);
      } else if (key === "start_entry") {
        flush();
        current = {
          low,
          high,
          title: "",
          message: "",
          giveRandom: "",
          addLog: "",
          pic: picture,
          bp: [0, 0],
          luck: [0, 0],
          turns: [0, 0],
        };
      } else if (current && key === "title") current.title = parts.slice(1).join("|");
      else if (current && key === "message") current.message = parts.slice(1).join("|");
      else if (current && key === "give_random") current.giveRandom = parts.slice(1).join("|");
      else if (current && key === "add_log") current.addLog = parts.slice(1).join("|");
      else if (current && key === "set_pic") current.pic = parts[1];
      else if (current && key === "mod_bp") current.bp = [Number(parts[1]), Number(parts[2])];
      else if (current && key === "mod_luck") current.luck = [Number(parts[1]), Number(parts[2])];
      else if (current && key === "mod_turns") current.turns = [Number(parts[1]), Number(parts[2])];
    }
    flush();
    return events;
  }

  function loadRisks() {
    const file = path.join(publicRoot, "..", "risks.txt");
    const risks = [];
    const blank = () => ({
      startMessage: "",
      startUrl: "",
      startBg: "",
      accept: "Accept",
      risk: "Risk",
      acceptMessage: "",
      acceptUrl: "",
      acceptBg: "",
      successMessage: "",
      successUrl: "",
      successBg: "",
      failMessage: "",
      failUrl: "",
      failBg: "",
    });
    let current = blank();
    if (!fs.existsSync(file)) return risks;
    for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("//")) continue;
      const parts = trimmed.split("|");
      const key = parts[0];
      const value = parts.slice(1).join("|");
      if (key === "start_message") current.startMessage = value;
      else if (key === "start_url") current.startUrl = value;
      else if (key === "start_url_bg") current.startBg = value;
      else if (key === "normal") current.accept = value;
      else if (key === "risk") current.risk = value;
      else if (key === "success_message") current.successMessage = value;
      else if (key === "success_url") current.successUrl = value;
      else if (key === "success_url_bg") current.successBg = value;
      else if (key === "fail_message") current.failMessage = value;
      else if (key === "fail_url") current.failUrl = value;
      else if (key === "fail_url_bg") current.failBg = value;
      else if (key === "normal_message") current.acceptMessage = value;
      else if (key === "normal_url") current.acceptUrl = value;
      else if (key === "normal_url_bg") current.acceptBg = value;
      else if (key === "add_risk") {
        if (current.startMessage) risks.push(current);
        current = blank();
      }
    }
    return risks;
  }

  const softOptions = loadSellItems("soft_sell.txt");
  const upgrades = loadSellItems("hard_sell.txt");
  const storyEvents = loadStoryEvents("events.txt");
  const newsBlurbs = loadStoryEvents("news_blurbs.txt", "flash/stuff/money_bag_2.swf");
  const risks = loadRisks();
  const cursedFortunes = loadFortunes("yangs.txt");
  const kindFortunes = loadFortunes("yangs_nice.txt");

  function quoteForReply(content) {
    let text = String(content ?? "").replaceAll("\r\n", "\n");
    const marker = text.lastIndexOf("\n>");
    if (marker !== -1) {
      let start = marker + 1;
      const after = text.indexOf("\n", start);
      if (after !== -1) {
        start = after + 1;
        if (text[start] === "\n") start += 1;
      }
      text = text.slice(start);
    }
    return `>${text.replaceAll("\n", "\n>")}\n\n`;
  }

  function showInbox(player) {
    const note = player.inbox.shift();
    store.save();
    if (note.letter) {
      const body = String(note.body ?? "");
      return scene([
        ...stats(player),
        ["st_url", art("flash/places/desk.swf")],
        ["st_object", "m_main"],
        ["st_quote", quoteForReply(body)],
        ["st_option_2", "    Reply"],
        ["i_command_2", COMMAND.reply],
        ["i_his_id", note.fromIndex],
        ["st_option_3", "Continue"],
        ["i_command_3", COMMAND.main],
        ["b_kill_buttons", 1],
        ["st_main", `\`w\`$You have received a letter from \`w${note.fromName}\`$:\n\n${body}\n`],
        ["i_sent", SENT.object],
      ]);
    }
    return scene([
      ...stats(player),
      ["st_url", art(note.art || "flash/people/man_prowl.swf")],
      ["st_object", "m_main"],
      ["st_main", note.text],
      ...continueButton(),
      ["i_sent", SENT.object],
    ]);
  }

  function sendMail(player, tokens) {
    const index = Number(tokens.i_his_id);
    const target = Number.isInteger(index) ? playerAt(index) : null;
    if (!target) {
      return scene([
        ["st_object", "i_message"],
        ["st_message", "Can't email, player doesn't exist?"],
        ["i_sent", SENT.add],
      ]);
    }
    const body = String(tokens.st_input ?? "");
    target.inbox = target.inbox || [];
    target.inbox.push({
      letter: true,
      fromIndex: playerIndex(player),
      fromName: player.name,
      body,
    });
    pushChatTo(target.id, "You have mail waiting for you at your parlor.");
    store.save();
    return scene([["i_sent", SENT.callback]]);
  }

  function showDelivery(player) {
    const note = player.deliveries.shift();
    store.save();
    return scene([
      ...stats(player),
      ["st_url", art(note.art || "flash/chinese_takeout.swf")],
      ["st_main", note.text],
      ...continueButton(),
      ["i_sent", SENT.loadMovie],
    ]);
  }

  function localDate() {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }

  function awardStanding(person, expBonus, luckBonus, message) {
    postMail(person, message);
    applyLuck(person, luckBonus);
    applyExp(person, expBonus);
  }

  function awardYesterday() {
    const seller = sellerOfTheDay();
    store.state.topYesterday = seller
      ? `${seller.name} from ${seller.funeralName} with ${seller.buryPoints} Bury Points.\n\n`
      : "No one died yesterday.";
    const expBonus = 20;
    const luckBonus = 25;
    if (seller) {
      awardStanding(
        seller,
        expBonus,
        luckBonus,
        `\`wCongratulations!\n\`$You were the top seller for yesterday.  You earn \`w${cash(luckBonus)}\`$ luck and \`w${cash(expBonus)}\`$ Bury Points.\n`,
      );
    }
    const first = highestExperience();
    if (first) {
      awardStanding(
        first,
        expBonus,
        luckBonus,
        `\`wCongratulations!\n\`$You ran the top parlor in town yesterday.  You earn \`w${cash(luckBonus)}\`$ luck and \`w${cash(expBonus)}\`$ Bury Points.\n`,
      );
    }
    const second = highestExperience(first ? [first] : []);
    if (second) {
      const halfExp = Math.trunc(expBonus * 0.5);
      const halfLuck = Math.trunc(luckBonus * 0.5);
      awardStanding(
        second,
        halfExp,
        halfLuck,
        `\`wCongratulations!\n\`$You were the second highest parlor in town yesterday. You earn \`w${cash(halfLuck)}\`$ luck and \`w${cash(halfExp)}\`$ Bury Points.\n`,
      );
    }
    const third = highestExperience([first, second].filter(Boolean));
    if (third) {
      const quarterExp = Math.trunc(expBonus * 0.25);
      const quarterLuck = Math.trunc(luckBonus * 0.25);
      awardStanding(
        third,
        quarterExp,
        quarterLuck,
        `\`wCongratulations!\n\`$You were the third highest parlor in town yesterday. You earn \`w${cash(quarterLuck)}\`$ luck and \`w${cash(quarterExp)}\`$ Bury Points.\n`,
      );
    }
  }

  function closeForMidnight(person, day) {
    person.buryPoints = 0;
    person.yangsSent = 0;
    person.tagsReceived = 0;
    if ((person.dayLastLoggedOn ?? 0) + 15 < day) {
      person.active = false;
      pushChat(`${person.name} closes shop and leaves town.`);
    }
    if ((person.dayLastLoggedOn ?? 0) + 1 < day) person.police = false;
    if (person.active !== false && (person.exp ?? 0) === 0 && !isOnline(person, null)) {
      person.active = false;
      pushChat(`${person.name} closes shop and leaves town without a single sale.`);
    }
  }

  function postDailyBlurb() {
    const choices = newsBlurbs.filter((entry) => store.state.day >= entry.low && store.state.day <= entry.high);
    const event = pick(choices);
    if (!event) return;
    addNews(`\`7${event.title}\n\`^${event.message}\n\n`);
    if (!event.giveRandom) return;
    const people = store.state.players.filter((person) => person.active !== false);
    if (!people.length) return;
    const person = pick(people);
    const points = span(event.bp[0], event.bp[1]);
    const luck = span(event.luck[0], event.luck[1]);
    const turns = span(event.turns[0], event.turns[1]);
    const reward = rewardText([
      [points, "Bury Points", "Bury Points"],
      [luck, "luck", "luck"],
      [turns, "turns", "turns"],
    ]);
    postMail(person, `${String(event.giveRandom).replaceAll("\\n", "\n")}${reward}${reward ? "." : ""}`, event.pic);
    applyExp(person, points);
    applyLuck(person, luck);
    applyTurns(person, turns);
    if (event.addLog) addNews(`${fillBlanks(event.addLog, person).replaceAll("*HOME*", person.funeralName)}\n\n`);
  }

  function rollDay() {
    const today = localDate();
    if (store.state.date === today) return false;
    const firstStamp = !store.state.date;
    store.state.date = today;
    if (!firstStamp) {
      awardYesterday();
      store.state.yesterday = store.state.news || [];
      store.state.crimeYesterday = store.state.crime || [];
      store.state.news = [];
      store.state.crime = [];
      store.state.day = (store.state.day ?? 1) + 1;
      for (const person of store.state.players) {
        if (person.active === false) continue;
        closeForMidnight(person, store.state.day);
      }
      postDailyBlurb();
      console.log(`Midnight maint for day ${store.state.day}`);
    }
    return true;
  }

  function maintain() {
    const rolled = rollDay();
    if (rolled) store.save();
    return rolled;
  }

  function refreshDay(session, player) {
    const rolled = rollDay();
    const previous = player.dayLastLoggedOn ?? 0;
    if (previous === store.state.day) {
      if (rolled) store.save();
      return;
    }
    player.dayLastLoggedOn = store.state.day;
    player.turns = 15 + rideOf(player).bonus;
    player.yangsSent = 0;
    player.kickedOut = false;
    player.visits = 0;
    player.police = false;
    player.buryPoints = 0;
    player.tagsReceived = 0;
    if (previous === 0) session.pendingMorning = "first";
    else session.pendingMorning = session.gaveLogonMessage ? "awake" : "return";
    store.save();
  }

  function main(session, player) {
    const ride = rideOf(player);
    session.script = null;
    if (player.inbox?.length) return showInbox(player);
    if (player.deliveries?.length) return showDelivery(player);
    const pending = session.pendingMorning || null;
    session.pendingMorning = null;
    const bonus = ride.bonus > 0 ? `Your ${ride.name} gives you time for \`w${ride.bonus}\`$ extra customers. ` : "";
    const fields = [...stats(player)];
    if (pending) {
      session.gaveLogonMessage = true;
      const greeting = pending === "first"
        ? `\`wWelcome to Funeral Quest, ${player.name}.  Good luck.\`^\n\n\`$You unlock the front door and enter your beloved ${player.funeralName} Funeral Parlor.  Ah, it's great to be alive.\n\n${bonus}`
        : pending === "return"
          ? `\`wWelcome back, ${player.name}!\`^\n\n\`$You unlock the front door and enter your beloved ${player.funeralName} Funeral Parlor.  Ah, it's great to be alive.\n\n${bonus}`
          : `\`wYou're eyes grow heavy.  You wake up the next morning, still at your desk.\n\n\`$You quickly freshen up.. who knows what great things await ${player.funeralName} Funeral Parlor today?\n\n${bonus}`;
      fields.push(
        ["st_url", art("flash/morning_coffee.swf")],
        ["st_object", "m_main"],
        ["st_main", greeting],
        ...continueButton(),
        ["i_sent", SENT.object],
      );
    } else if (!session.gaveLogonMessage) {
      session.gaveLogonMessage = true;
      fields.push(
        ["st_url", art("flash/places/desk.swf")],
        ["st_object", "m_main"],
        ["st_main", `\`$Welcome back, \`w${player.name}\`$.  Unfinished business?\n`],
        ["i_sent", SENT.object],
      );
    } else {
      fields.push(
        ["st_url", art("flash/places/desk.swf")],
        ["st_object", "m_main"],
        ["st_main", player.police ? "`$You are sitting at your desk.  Police are outside, you feel safe.\n" : "`$You are sitting at your desk.\n"],
        ["i_sent", SENT.object],
      );
    }
    store.save();
    return scene(fields);
  }

  function location(player, url, message) {
    return genericPlace(player, url, message, [{ key: "3", label: "Continue", command: COMMAND.main }]);
  }

  const LOVED_MOODS = ["In Shock", "Dazed", "Confused", "Crying", "Numb", "Disturbed", "Frustrated", "Irked", "Angry", "Pissed"];
  const GUILTY_MOODS = ["Chipper", "In a hurry", "Natural", "Tight Lipped", "Nervous", "Frustrated", "Annoyed", "Irked", "Angry", "Pissed"];
  const INCOMES = ["Poor", "Average", "Rich", "Super Rich"];
  const CASKET_NAME = { wood: "Wood", silver: "Silver", gold: "Gold" };

  function moodName(loved, value) {
    const names = loved ? LOVED_MOODS : GUILTY_MOODS;
    if (value <= 1) return names[0];
    return names[Math.min(names.length - 1, Math.ceil(value) - 1)];
  }

  function customerNoun(customer) {
    const band = ageBand(customer.age);
    if (customer.male) return band === "teen" ? "boy" : "man";
    return band === "teen" ? "girl" : "woman";
  }

  function customerTitle(customer) {
    const title = `${ageLabel(customer.age)} ${customerNoun(customer)}`;
    return title.charAt(0).toUpperCase() + title.slice(1);
  }

  function relationFor(age, deceasedMale) {
    const parent = ageBand(age) === "teen"
      ? (deceasedMale ? "dad" : "mom")
      : (deceasedMale ? "father" : "mother");
    const words = deceasedMale
      ? ["brother", parent, "son", "husband", "cousin", "grandfather", "boyfriend", "grandson", "friend"]
      : ["sister", parent, "daughter", "wife", "cousin", "grandmother", "girlfriend", "granddaughter", "friend"];
    return pick(words);
  }

  function spendingLimit(income) {
    const [min, max] = INCOME_BUDGET[income];
    return between(min, max);
  }

  function skillCheck(luck, weight) {
    const roll = Math.floor(Math.random() * 100);
    const adjusted = luck + Math.trunc((50 - luck) * (1 - weight));
    return roll <= adjusted;
  }

  function readCustomer(player, customer) {
    const psych = player.psych ?? 0;
    const he = customer.male ? "he" : "she";
    const He = customer.male ? "He" : "She";
    const his = customer.male ? "his" : "her";
    const him = customer.male ? "him" : "her";
    const notes = [];
    if (skillCheck(20 + psych * 13, 0.98)) {
      customer.showResistance = true;
      notes.push(`\`wYou successfully analyze ${his} resistance.\`$`);
    }
    if (psych > 0 && skillCheck(psych * 14, 0.98)) {
      notes.push(customer.loved ? `\`w${He} seems heartbroken.\`$` : `\`w${He} seems guilty.\`$`);
    }
    if (psych > 4 && skillCheck(psych * 15 - 65, 0.98)) {
      customer.budget *= 2;
      notes.push("`wSpending limit doubled.`$");
    }
    if (psych > 1 && skillCheck(psych * 15 - 25, 0.98)) {
      notes.push(`\`wYou estimate ${he} is worth about $${cash(customer.budget)}.\`$`);
    }
    if (psych > 2 && skillCheck(psych * 15 - 35, 0.98)) {
      customer.cheapness *= 0.5;
      notes.push("`wResistance cut in half.`$");
    }
    if (psych > 3 && skillCheck(psych * 15 - 50, 0.98)) {
      customer.moodValue *= 0.5;
      notes.push("`wMood cut in half.`$");
    }
    customer.insight = notes.length
      ? `\n\n${notes.join(" ")}`
      : `\n\n\`$You are unable to analyze anything extra about ${him}.`;
  }

  function makeCustomer(player) {
    const male = Math.random() < 0.5;
    const age = between(16, 78);
    const deceasedMale = Math.random() < 0.5;
    const income = pick(INCOMES);
    const budget = spendingLimit(income);
    let casketIndex = 0;
    for (let attempt = 0; attempt < 12; attempt += 1) {
      const candidate = Math.floor(Math.random() * CASKETS.length);
      if (CASKETS[candidate].price <= budget) {
        casketIndex = candidate;
        break;
      }
    }
    const band = ageBand(age);
    const loved = Math.random() < 0.5;
    const customer = {
      name: pick(FIRST_NAMES[male ? "male" : "female"]),
      male,
      age,
      loved,
      deceasedMale,
      relation: relationFor(age, deceasedMale),
      deathObject: pick(DEATH_OBJECTS),
      deathMethod: pick(DEATH_METHODS),
      casketIndex,
      casket: CASKETS[casketIndex],
      upgrade: -1,
      options: [],
      income,
      moodValue: Math.random() * 10,
      cheapness: Math.random() * 10,
      showResistance: false,
      budget,
      bill: CASKETS[casketIndex].price,
      mood: 0,
      portrait: pick(PEOPLE[male ? "male" : "female"][band]),
      background: `flash/background/background_${between(1, 4)}.swf`,
    };
    readCustomer(player, customer);
    return customer;
  }

  function customerText(customer) {
    const title = customerTitle(customer);
    const casket = CASKET_NAME[customer.casket.id];
    const resistance = customer.showResistance
      ? ` \`5(Resistance: \`#${Math.round(customer.cheapness * 10)}%25\`5)`
      : "";
    return `\`w${title}: \`$"Please, I need a funeral service for my ${customer.relation}. I would need a plot and a ${casket} casket for ${customer.deceasedMale ? "him" : "her"}."${customer.insight}\n\nTotal: $${cash(customer.bill)}${resistance}`;
  }

  function showCustomer(player, customer) {
    return scene([
      ...stats(player),
      ["st_url", art(customer.portrait)],
      ["st_url_bg", art(customer.background)],
      ["st_mood", moodName(customer.loved, customer.moodValue)],
      ["st_class", customer.income],
      ["st_main", customerText(customer)],
      ["st_option_3", "     Done"],
      ["i_command_3", COMMAND.finish],
      ["st_option_2", "  Soft Sell"],
      ["i_command_2", COMMAND.soft],
      ["st_option_1", "  Hard Sell"],
      ["i_command_1", COMMAND.hard],
      ["st_option_0", "   Sympathy"],
      ["i_command_0", COMMAND.sympathy],
      ["st_option_a", "     Guilt"],
      ["i_command_a", COMMAND.guilt],
      ["b_kill_buttons", 1],
      ["b_show_stats", 1],
      ["i_sent", SENT.loadMovie],
    ]);
  }

  function walkOut(player, customer, lead = "", lines) {
    const title = customerTitle(customer);
    const replies = lines ?? [
      "\"You know what? I really don't like the way you work. Bye.\"",
      "\"Enough is enough.  I'm sick of you.\"",
      "\"I just realized that you disgust me.  Sayoonara.\"",
      "\"Can we cut the crap for a second here?  I guess not, bye.\"",
    ];
    const him = customer.male ? "him" : "her";
    const he = customer.male ? "he" : "she";
    let message = `${lead}\`w${title}: \`$${pick(replies)}\n`;
    const others = store.state.players.filter((entry) => entry.active !== false && entry.id !== player.id);
    if (others.length) {
      const other = pick(others);
      message += `\n\`$You watch ${him} through the window after ${he} leaves.  You see ${him} go into ${other.funeralName} to see ${other.name}!`;
      const stolen = 5 + Math.floor(Math.random() * 5);
      const young = customerTitle(customer).toLowerCase();
      postMail(
        other,
        `\n\`$${young.charAt(0).toUpperCase()}${young.slice(1)} walks in with a dead body. "Hello ${other.male === false ? "ma'am" : "sir"}, I would like you to know I got treated like crap by ${player.name} of ${player.funeralName}. I know you'll do a better job!"\n\nYou earn \`w${stolen}\`$ Bury Points for stealing this sale!\n`,
        customer.portrait,
      );
      other.buryPoints = (other.buryPoints ?? 0) + stolen;
      other.exp = (other.exp ?? 0) + stolen;
    }
    store.state.news = store.state.news || [];
    store.state.news.push(`"A ${customer.income} ${ageLabel(customer.age)} ${customerNoun(customer)} just left ${player.funeralName} looking pissed," a reporter says.`);
    return scene([
      ...stats(player),
      ["st_url", art("flash/stuff/money_bag.swf")],
      ["st_main", message],
      ["st_option_3", " Continue"],
      ["i_command_3", COMMAND.main],
      ["b_kill_buttons", 1],
      ["b_show_stats", 0],
      ["i_sent", SENT.loadMovie],
    ]);
  }

  function finishSale(player, customer) {
    customer.bill = priceOf(customer);
    const bonus = 25;
    let extra = "";
    let points = Math.trunc(customer.bill * 0.0064);
    const who = customer.male ? "he" : "she";
    if (customer.bill === customer.budget) {
      points += 200;
      extra = `\`wEXCELLENT! \`$For making this funeral cost the max ${who} could pay you receive a \`w200\`$ BP bonus!\n\n`;
      store.state.news = store.state.news || [];
      store.state.news.push(`\`7${player.name} makes a perfect sale, the industry is in awe.\n\n`);
    } else if (Math.abs(customer.bill - customer.budget) <= 50) {
      points += bonus + 10;
      extra = `\`wGREAT! \`$For getting within 50 of this customers limit you receive a \`w${bonus + 10}\`$ BP bonus!\n\n`;
    } else if (Math.abs(customer.bill - customer.budget) <= 100) {
      points += bonus;
      extra = `\`wNICE! \`$For getting within 100 of this customers limit you receive a \`w${bonus}\`$ BP bonus!\n\n`;
    }
    player.buryPoints += points;
    player.exp += points;
    player[customer.casket.id] += 1;
    player.plots += 1;
    const tag = CASKET_NAME[customer.casket.id];
    return scene([
      ...stats(player),
      ["st_url", art("flash/stuff/money_bag_2.swf")],
      ["st_main", `\`w\`$The service is a success!  Total bill is $${cash(customer.bill)}.\n\n${extra}You earn \`w${points}\`$ Bury Points and a ${tag} sales tag.`],
      ["st_option_3", " Continue"],
      ["i_command_3", COMMAND.main],
      ["b_kill_buttons", 1],
      ["b_show_stats", 0],
      ["i_sent", SENT.loadMovie],
    ]);
  }

  function frandom(max) {
    return Math.random() * max;
  }

  function priceOf(customer) {
    let price = CASKETS[customer.casketIndex].price;
    for (const option of customer.options) price += option.price;
    for (let index = 0; index <= customer.upgrade; index += 1) price += upgrades[index].price;
    return price;
  }

  function totalSuffix(customer) {
    customer.bill = priceOf(customer);
    const resistance = customer.showResistance
      ? ` \`5(Resistance: \`#${Math.round(customer.cheapness * 10)}%25\`5)`
      : "";
    return `\n\nTotal: $${cash(customer.bill)}${resistance}`;
  }

  function continueSale(player, customer, message) {
    return scene([
      ...stats(player),
      ["st_url", art(customer.portrait)],
      ["st_url_bg", art(customer.background)],
      ["st_mood", moodName(customer.loved, customer.moodValue)],
      ["st_class", customer.income],
      ["st_main", message + totalSuffix(customer)],
      ["st_option_3", "     Done"],
      ["i_command_3", COMMAND.finish],
      ["b_kill_buttons", 1],
      ["st_option_2", "  Soft Sell"],
      ["i_command_2", COMMAND.soft],
      ["st_option_1", "  Hard Sell"],
      ["i_command_1", COMMAND.hard],
      ["st_option_0", "   Sympathy"],
      ["i_command_0", COMMAND.sympathy],
      ["st_option_a", "     Guilt"],
      ["i_command_a", COMMAND.guilt],
      ["b_show_stats", 1],
      ["i_sent", SENT.loadMovie],
    ]);
  }

  function limitLines(customer) {
    return [
      "\"That would put me over my limit.. I don't have time for this, bye.\"",
      `\"Damn, you see, I can only spend $${cash(customer.budget)}.  I'm leaving.\"`,
      `\"My visa only goes to $${cash(customer.budget)}... I'll try next-door.\"`,
    ];
  }

  function youngLine(customer) {
    return `\`w${customerTitle(customer)}: \`$`;
  }

  function softSell(session, player) {
    const customer = session.customer;
    const option = pick(softOptions);
    const price = cash(option.price);
    const seller = `\`w${player.name}: \`$`;
    const young = youngLine(customer);
    const pitch = pick([
      `${seller}"You know, your neighbor was in here recently.. he bought ${option.article} ${option.name} for only $${price}. Impeccable taste."\n\n`,
      `${seller}"I can tell by the fine clothes you're wearing that you appreciate the finer things. How about ${option.article} ${option.name} for $${price}?"\n\n`,
      `${seller}"I don't mean to rush you, but this isn't really something you can wait a week thinking about. We do have ${option.article} ${option.name} for $${price}..."\n\n`,
      `${seller}"You know..  I bet your ${customer.relation}'s social security death benefits would probably cover ${option.article} ${option.name} for $${price}?"\n\n`,
    ]);
    if (frandom(10) < customer.cheapness) {
      customer.moodValue += 0.4 + frandom(0.7);
      if (customer.moodValue >= 10) {
        session.customer = null;
        return walkOut(player, customer, pitch);
      }
      const alive = customer.deceasedMale ? "he" : "she";
      const refusal = pick([
        `${young}"I really don't think my ${customer.relation} needs that."`,
        `${young}"Actually, my ${customer.relation} wanted a very simple funeral."`,
        `${young}"Um, no."`,
        customer.loved
          ? `${young}"When my ${customer.relation} was alive ${alive} asked me not to buy that.  I'm sorry."`
          : `${young}"I'm not gonna pay another $${price} for an 'effing ${customer.relation} I barely knew!"`,
        `${young}"You've got to be kidding."`,
        `${young}"I'm not saying I can't afford that, but I don't think I really need it."`,
      ]);
      return continueSale(player, customer, pitch + refusal);
    }
    if (priceOf(customer) + option.price <= customer.budget) {
      customer.options.push(option);
      const mood = customer.moodValue;
      let answer;
      if (mood < 2) answer = `${young}"Thank you, I'll take it."`;
      else if (mood < 4) answer = `${young}"You don't say? ${option.name} is something I want then."`;
      else if (mood < 7) answer = `${young}"Fine, I'll take it."`;
      else if (mood < 9) answer = `${young}"I think I'm getting ripped off here but I'll take it."`;
      else answer = `${young}"WHATEVER!! Just give it to me I guess."`;
      return continueSale(player, customer, pitch + answer);
    }
    if (customer.moodValue > 9) {
      session.customer = null;
      return walkOut(player, customer, "", limitLines(customer));
    }
    customer.moodValue += 0.4 + frandom(1);
    return continueSale(player, customer, pitch + `${young}"That would put me over my limit.. how about something cheaper?"`);
  }

  function hardSell(session, player) {
    const customer = session.customer;
    if (customer.casketIndex >= 2) return sellUpgrade(session, player);
    const extra = CASKETS[customer.casketIndex + 1].price - CASKETS[customer.casketIndex].price;
    const price = cash(extra);
    const seller = `\`w${player.name}: \`$`;
    const young = youngLine(customer);
    const metal = customer.casketIndex === 1 ? "gold" : "silver";
    const pitch = customer.casketIndex === 0
      ? pick([
        `${seller}"Well, I suppose you COULD go with a wood casket.. except it really doesn't offer much protection from worms and the like.  How about a Silver Casket for only $${price} additional?"\n\n`,
        `${seller}"Wouldn't your ${customer.relation} prefer our traditional Silver Casket for only $${price} more?"\n\n`,
      ])
      : pick([
        `${seller}"Just think of the beautiful memory picture you would have of your ${customer.relation} in a Gold Casket - a bargain for only $${price} more!"\n\n`,
        `${seller}"A Gold Casket (only $${price} additional) reflects character and station in life.. its suberb styling reflects a successful living."\n\n`,
      ]);
    if (frandom(10) < customer.cheapness || customer.cheapness > 8) {
      customer.moodValue += 0.6 + frandom(1);
      if (customer.moodValue >= 10) {
        session.customer = null;
        return walkOut(player, customer, pitch);
      }
      const refusal = pick([
        `${young}"I really don't think my ${customer.relation} needs that."`,
        `${young}"Actually, my ${customer.relation} wanted a very simple funeral."`,
        `${young}"A casket is a casket, right?"`,
        customer.loved
          ? `${young}"No way, for that much it should have AC and a stereo!"`
          : `${young}"My ${customer.relation} doesn't deserve ${metal}."`,
        `${young}"I don't want to spend that much."`,
        `${young}"I've got the dough, but I would rather give to charity."`,
      ]);
      return continueSale(player, customer, pitch + refusal);
    }
    if (priceOf(customer) + extra <= customer.budget) {
      customer.casketIndex += 1;
      customer.casket = CASKETS[customer.casketIndex];
      const answer = customer.moodValue < 7
        ? pick([
          `${young}"Alright, I'll take it."`,
          `${young}"Ok, I'll do the upgrade."`,
          `${young}"Well, since you put it that way, ok."`,
        ])
        : `${young}"Fine, I'll take the damn ${metal} casket, ok?"`;
      return continueSale(player, customer, pitch + answer);
    }
    if (customer.moodValue > 9) {
      session.customer = null;
      return walkOut(player, customer, pitch, limitLines(customer));
    }
    customer.moodValue += 0.4 + frandom(1);
    return continueSale(player, customer, pitch + `${young}"That casket would put me over my limit.. how about something cheaper?"`);
  }

  function sellUpgrade(session, player) {
    const customer = session.customer;
    const next = customer.upgrade + 1;
    const young = youngLine(customer);
    const seller = `\`w${player.name}: \`$`;
    if (next >= upgrades.length) {
      return continueSale(player, customer, `\`yThis ${customerNoun(customer)} has already bought everything big you can think of.  It's a great feeling.`);
    }
    const item = upgrades[next];
    const last = customer.upgrade < 0 ? "Gold Casket" : upgrades[customer.upgrade].name;
    const price = cash(item.price);
    const pitch = pick([
      `${seller}"A simple ${last} may be enough for some.  But tell me, don't the words '${item.name}' (only $${price} additional) give you goosebumps?"\n\n`,
      `${seller}"I would feel guilty if I didn't mention we do offer a ${item.name} for a small fee of $${price}."\n\n`,
      `${seller}"${item.name}'s are a great idea for an enternal slumber. A fee of $${price} will be charged, alright?"\n\n`,
    ]);
    if (frandom(10) < customer.cheapness || customer.cheapness > 8) {
      customer.moodValue += 0.7 + frandom(1);
      if (customer.moodValue >= 10) {
        session.customer = null;
        return walkOut(player, customer, pitch);
      }
      const refusal = pick([
        `${young}"No."`,
        `${young}"Actually I want a simple funeral for my ${customer.relation}."`,
        `${young}"I would rather spend money getting drunk after this."`,
        customer.loved
          ? `${young}"God, can't you see this is hurting me talking about this now?"`
          : `${young}"In case you haven't noticed, I wasn't overly fond of my ${customer.relation}.  Forget it."`,
        `${young}"You just said $${item.price} right? I hope you're kidding."`,
        `${young}"I've got the dough, but I would rather spend it on something that matters."`,
      ]);
      return continueSale(player, customer, pitch + refusal);
    }
    if (priceOf(customer) + item.price <= customer.budget) {
      customer.upgrade += 1;
      const answer = customer.moodValue < 7
        ? pick([
          `${young}"Alright, I'll take it."`,
          `${young}"Ok."`,
          `${young}"Maybe you're right.  Ok."`,
        ])
        : `${young}"Fine, whatever, ok?"`;
      return continueSale(player, customer, pitch + answer);
    }
    if (customer.moodValue > 9) {
      session.customer = null;
      return walkOut(player, customer, pitch);
    }
    customer.moodValue += 0.4 + frandom(1);
    return continueSale(player, customer, pitch + `${young}"That would put me over my limit.. how about something cheaper?"`);
  }

  function guiltSell(session, player) {
    const customer = session.customer;
    const young = youngLine(customer);
    const seller = `\`w${player.name}: \`$`;
    if (customer.moodValue >= 10) {
      session.customer = null;
      return walkOut(player, customer, "`$You are interrupted!\n\n");
    }
    const pitch = pick([
      `${seller}"The funeral is not occasion for a display of cheapness, ${customer.male ? "sir" : "ma'am"}."\n\n`,
      `${seller}"${customer.male ? "Sir" : "Ma'am"},  let me ask you one simple thing.  Did you hate ${customer.deceasedMale ? "him" : "her"}?  Why else wouldn't you make this funeral something to be proud of?"\n\n`,
      `${seller}"Some believe an expensive funeral absolves the purchaser from any sin.  I happen to believe this, my friend."\n\n`,
      `${seller}"I would stay away from cremation.  Your loved one deserves better than 'bake and shake' - people would talk."\n\n`,
      `${seller}"Resistance to a 'proper' funeral will send the wrong message, wouldn't you agree?"\n\n`,
    ]);
    customer.moodValue += frandom(1);
    let answer;
    if (customer.loved) {
      customer.moodValue += 1 + frandom(2);
      const alive = customer.deceasedMale ? "him" : "her";
      answer = pick([
        `${young}"I LOVED ${alive}! Don't you get it? None of that stuff matters to me!"`,
        `${young}"My ${customer.relation} was ${customer.deathMethod} a ${customer.deathObject} for god-sake! How can you say something like that?"`,
        `${young}"If you want to keep my business you're going to stop saying stuff like that."`,
      ]);
    } else {
      customer.cheapness = Math.min(10, Math.max(0, customer.cheapness - (0.7 + frandom(2))));
      answer = pick([
        `${young}"You're right. I should be a little looser with my money."`,
        `${young}"Good call.  I guess it would be appropriate to spend more on my ${customer.relation}."`,
        `${young}"You've got a point there.. It makes me mad but you're right."`,
        `${young}"I agree in principle.. but I only want to spend $${cash(customer.budget)} on this."`,
      ]);
    }
    return continueSale(player, customer, pitch + answer);
  }

  function sympathySell(session, player) {
    const customer = session.customer;
    const young = youngLine(customer);
    const seller = `\`w${player.name}: \`$`;
    if (customer.moodValue >= 10) {
      session.customer = null;
      return walkOut(player, customer, "`$You are interrupted!\n\n");
    }
    const pitch = pick([
      `${seller}"${customer.male ? "Sir" : "Ma'am"}, I understand you've been through a lot, please tell me what happened."\n\n`,
      `${seller}"I know how you feel.  I know someone who lost a ${customer.relation} once."\n\n`,
      `${seller}"So you two were close?  ${customer.deceasedMale ? "He" : "She"} sounds very special."\n\n`,
      `${seller}"I'm not a licensed therapist.. but keep talking, get the pain out."\n\n`,
      `${seller}"Any time a ${customer.deathObject} is involved in an accident it's going to be traumatic."\n\n`,
    ]);
    customer.moodValue += 0.4 + frandom(1);
    let answer;
    if (!customer.loved) {
      customer.moodValue += 1 + frandom(2);
      answer = pick([
        `${young}"Sorry, I don't read ya, pard."`,
        `${young}"I don't go for all this touchy feeling crap, ok?  Don't hug me."`,
        `${young}"Um yeah.. except I actually have another ${customer.relation}, so it ain't so bad.  No more wasting my time, ok?"`,
      ]);
    } else {
      customer.cheapness = Math.min(10, Math.max(0, customer.cheapness - (0.7 + frandom(2))));
      answer = pick([
        `${young}"It's just.. I feel like ${customer.deceasedMale ? "he" : "she"} is just in another room, you know?"`,
        `${young}"I still have dreams.. of ${customer.deceasedMale ? "him" : "her"} getting ${customer.deathMethod} a ${customer.deathObject} over and over."`,
        `${young}"I mean, usually someone's ${customer.relation} is close.. but we were REALLY close if you know what I mean."`,
        `${young}"I appreciate what you're trying to do but I need to get this over with..for $${cash(customer.budget)} or less.."`,
      ]);
    }
    return continueSale(player, customer, pitch + answer);
  }

  function span(low, high) {
    return between(Math.min(low, high), Math.max(low, high));
  }

  function applyExp(player, amount) {
    let delta = amount;
    if ((player.buryPoints ?? 0) + delta < 0) delta = -(player.buryPoints ?? 0);
    player.buryPoints = Math.max(0, (player.buryPoints ?? 0) + delta);
    player.exp = Math.max(0, (player.exp ?? 0) + delta);
  }

  function applyLuck(player, amount) {
    player.luck = Math.min(100, Math.max(1, (player.luck ?? 50) + amount));
  }

  function applyTurns(player, amount) {
    player.turns = Math.max(0, (player.turns ?? 0) + amount);
  }

  function fillBlanks(text, player) {
    return String(text)
      .replaceAll("\\n", "\n")
      .replaceAll("*SIR*", player.male === false ? "ma'am" : "sir")
      .replaceAll("*NAME*", player.name)
      .replaceAll("*PARLOR*", player.funeralName);
  }

  function rewardText(changes) {
    let text = "";
    let count = 0;
    for (const [amount, singular, plural] of changes) {
      if (amount === 0) continue;
      count += 1;
      const name = Math.abs(amount) === 1 ? singular : plural;
      if (count === 1) {
        text += amount > 0
          ? `\n\n\`$You earn \`w${amount}\`$ ${name}`
          : `\n\n\`4You lose \`w${Math.abs(amount)}\`4 ${name}`;
      } else {
        text += amount > 0
          ? ` and \`w${amount}\`$ ${name}`
          : ` and \`w${Math.abs(amount)}\`4 ${name}`;
      }
    }
    if (count > 0) text += ".";
    return text;
  }

  function movieScene(player, message, picture, background, buttons = []) {
    const fields = [
      ...stats(player),
      ["st_url", art(picture || "flash/places/desk.swf")],
      ["st_main", message],
      ["i_sent", SENT.loadMovie],
    ];
    if (background) fields.push(["st_url_bg", art(background)]);
    if (buttons.length) fields.push(["b_kill_buttons", 1]);
    for (const button of buttons) {
      fields.push([`st_option_${button.key}`, button.label], [`i_command_${button.key}`, button.command]);
      if (button.sub != null) fields.push([`i_command_sub_${button.key}`, button.sub]);
    }
    return scene(fields);
  }

  function noTurns(player) {
    const message = pick([
      "You are out of turns for the day, come back tomorrow!",
      "It's closing time, you'll have to wait until tomorrow.",
      "You look at your watch - it's late.  Maybe tomorrow.",
    ]);
    return movieScene(player, `\`$${message}`, "flash/closed.swf");
  }

  function specialEvent(player) {
    const event = pick(storyEvents.filter((entry) => (player.exp ?? 0) >= entry.low && (player.exp ?? 0) <= entry.high));
    if (!event) return null;
    const points = span(event.bp[0], event.bp[1]);
    const luck = span(event.luck[0], event.luck[1]);
    const turns = span(event.turns[0], event.turns[1]);
    applyExp(player, points);
    applyLuck(player, luck);
    applyTurns(player, turns);
    const reward = rewardText([
      [points, "Bury Point", "Bury Points"],
      [luck, "luck", "luck"],
      [turns, "turn", "turns"],
    ]);
    return movieScene(player, `\`wSPECIAL EVENT\n\n\`$${event.message}${reward}`, event.pic);
  }

  function showRisk(player, risk) {
    return movieScene(player, fillBlanks(risk.startMessage, player), risk.startUrl, risk.startBg, [
      { key: "3", label: risk.accept, command: COMMAND.riskAccept },
      { key: "2", label: risk.risk, command: COMMAND.reaction },
    ]);
  }

  function missingRisk(player) {
    return scene([
      ...stats(player),
      ["g_st_error", "Huh?  Can't double with this customer."],
      ["i_sent", SENT.fail],
    ]);
  }

  function acceptRisk(session, player) {
    const risk = risks[session.risk];
    if (!session.customer || !risk) return missingRisk(player);
    const points = between(4, 10);
    applyExp(player, points);
    const message = `${fillBlanks(risk.acceptMessage, player)}\n\n${points === 1 ? "`$You earned one Bury Point, not bad.\n" : `\`$You earned \`w${points}\`$ Bury Points.\n`}`;
    session.customer = null;
    session.risk = null;
    return movieScene(player, message, risk.acceptUrl, risk.acceptBg, [
      { key: "3", label: " Continue", command: COMMAND.main },
    ]);
  }

  function takeRisk(session, player) {
    const risk = risks[session.risk];
    if (!session.customer || !risk) return missingRisk(player);
    const won = skillCheck(player.luck ?? 50, 0.9);
    const points = 50;
    applyExp(player, won ? points : -points);
    const message = won
      ? `${fillBlanks(risk.successMessage, player)}\n\n\`$You earned \`w${points}\`$ Bury Points!`
      : `${fillBlanks(risk.failMessage, player)}\n\n\`$You lose \`#${points}\`$ Bury Points.`;
    session.customer = null;
    session.risk = null;
    return movieScene(player, message, won ? risk.successUrl : risk.failUrl, won ? risk.successBg : risk.failBg, [
      { key: "3", label: " Continue", command: COMMAND.main },
    ]);
  }

  const TAG_VALUE = { wood: 1, silver: 2, gold: 4 };

  function bankScene(player, message, buttons) {
    return genericPlace(player, "flash/places/bank.swf", message, buttons);
  }

  function bankLobby(player, message) {
    const greeting = message || `You are at the city bank. This facility allows you to exchange casket sales tags.\n\n\`wTeller: \`$\"How's business, ${player.name}?\"`;
    return bankScene(player, greeting, [
      { key: "0", label: "All to wood", command: COMMAND.bankCallback, sub: 18 },
      { key: "1", label: "All to gold", command: COMMAND.bankCallback, sub: 17 },
      { key: "2", label: "Exchange", command: COMMAND.bankCallback, sub: 1 },
      { key: "3", label: "Leave", command: COMMAND.map },
    ]);
  }

  function exchangeChoices(player, message) {
    return bankScene(player, message, [
      { key: "2", label: "Gold", command: COMMAND.bankCallback, sub: 2 },
      { key: "1", label: "Silver", command: COMMAND.bankCallback, sub: 3 },
      { key: "0", label: "Wood", command: COMMAND.bankCallback, sub: 4 },
      { key: "3", label: "Back", command: COMMAND.bank },
    ]);
  }

  function exchangeTags(player, count, from, to) {
    const fromValue = TAG_VALUE[from];
    const toValue = TAG_VALUE[to];
    if (count === 0) return { ok: false, reason: "zero" };
    if (count < 0) return { ok: false, reason: "negative" };
    if (count > player[from]) return { ok: false, reason: "short" };
    if (Math.trunc((fromValue * count) / toValue) < 1) return { ok: false, reason: "small" };
    const gained = Math.trunc(count * (fromValue / toValue));
    const lost = Math.trunc((gained * toValue) / fromValue);
    player[from] -= lost;
    player[to] += gained;
    return { ok: true, lost, gained };
  }

  function bankPrompt(player, from, to, executeSub) {
    let holdFrom = Math.trunc(TAG_VALUE[to] / TAG_VALUE[from]);
    let holdTo = 1;
    if (from === "silver" && to === "gold") holdFrom = 2;
    if (holdFrom < 1) {
      holdTo = Math.trunc(TAG_VALUE[from] / TAG_VALUE[to]);
      holdFrom = 1;
    }
    return scene([
      ...stats(player),
      ["st_url", art("flash/places/bank.swf")],
      ["st_message", `\`wTeller: \`$"${holdFrom} ${from} tag(s) = ${holdTo} ${to} tag(s).  How many ${from} tags would you like to exchange?"`],
      ["st_object", "i_get_text"],
      ["st_extra_text", player[from]],
      ["i_return_command", COMMAND.bankCallback],
      ["i_return_command_sub", executeSub],
      ["i_sent", SENT.add],
    ]);
  }

  function bankCallback(session, player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 18) {
      player.wood += player.silver * TAG_VALUE.silver + player.gold * TAG_VALUE.gold;
      player.silver = 0;
      player.gold = 0;
      store.save();
      return bankLobby(player, "`wA teller converts all your tags to Wood for you.");
    }
    if (sub === 17) {
      player.wood += player.silver * TAG_VALUE.silver + player.gold * TAG_VALUE.gold;
      player.silver = 0;
      player.gold = 0;
      const traded = exchangeTags(player, player.wood, "wood", "gold");
      if (!traded.ok) {
        store.save();
        return bankLobby(player, "`wA teller converts all your tags to Gold for you.");
      }
      store.save();
      return bankLobby(player, "`wA teller converts all your tags to Gold for you.");
    }
    if (sub === 1) return exchangeChoices(player, '`wTeller: `$"Which kind tag would you like to exchange?"');
    if (sub === 4) {
      return bankScene(player, '`wTeller: `$"What would you like to convert your Wood to?"', [
        { key: "2", label: "Gold", command: COMMAND.bankCallback, sub: 11 },
        { key: "1", label: "Silver", command: COMMAND.bankCallback, sub: 12 },
        { key: "3", label: "Back", command: COMMAND.bankCallback, sub: 1 },
      ]);
    }
    if (sub === 3) {
      return bankScene(player, '`wTeller: `$"What would you like to convert your Silver to?"', [
        { key: "2", label: "Gold", command: COMMAND.bankCallback, sub: 13 },
        { key: "1", label: "Wood", command: COMMAND.bankCallback, sub: 14 },
        { key: "3", label: "Back", command: COMMAND.bankCallback, sub: 1 },
      ]);
    }
    if (sub === 2) {
      return bankScene(player, '`wTeller: `$"What would you like to convert your Gold to?"', [
        { key: "2", label: "Silver", command: COMMAND.bankCallback, sub: 16 },
        { key: "1", label: "Wood", command: COMMAND.bankCallback, sub: 15 },
        { key: "3", label: "Back", command: COMMAND.bankCallback, sub: 1 },
      ]);
    }
    const prompts = {
      11: ["wood", "gold", 5],
      12: ["wood", "silver", 6],
      13: ["silver", "gold", 7],
      14: ["silver", "wood", 8],
      15: ["gold", "wood", 9],
      16: ["gold", "silver", 10],
    };
    if (prompts[sub]) return bankPrompt(player, ...prompts[sub]);
    const pairs = {
      5: ["wood", "gold"],
      6: ["wood", "silver"],
      7: ["silver", "gold"],
      8: ["silver", "wood"],
      9: ["gold", "wood"],
      10: ["gold", "silver"],
    };
    if (pairs[sub]) {
      if (tokens.st_extra_text === "12ABORT12") return exchangeChoices(player, '`wTeller: `$"Which kind tag would you like to exchange?"');
      const count = Number.parseInt(tokens.st_extra_text ?? "", 10);
      const result = exchangeTags(player, Number.isNaN(count) ? 0 : count, ...pairs[sub]);
      if (!result.ok && result.reason === "zero") return exchangeChoices(player, '`wTeller: `$"Which kind tag would you like to exchange?"');
      if (!result.ok && result.reason === "negative") {
        return bankScene(player, `\`wTeller: \`$\"You are very amusing ${player.name}.\"`, [
          { key: "3", label: "Continue", command: COMMAND.bankCallback, sub: 1 },
        ]);
      }
      if (!result.ok && result.reason === "short") {
        return bankScene(player, '`wTeller: `$"You can\'t exchange what you don\'t have.  I\'m trying to be nice here, but please, think next time."', [
          { key: "3", label: "Continue", command: COMMAND.bankCallback, sub: 1 },
        ]);
      }
      if (!result.ok) {
        return bankScene(player, `\`wTeller: \`$\"You don't understand, do you. You can't trade just ${count} for a ${pairs[sub][1]} tag, alright?\"`, [
          { key: "3", label: "Continue", command: COMMAND.bankCallback, sub: 1 },
        ]);
      }
      store.save();
      return exchangeChoices(player, `\`wTeller: \`$\"Very good, I have converted ${result.lost} ${pairs[sub][0]} tag(s) to ${result.gained} ${pairs[sub][1]} tag(s). Any other tags you would like to convert?\"`);
    }
    return bankLobby(player);
  }

  function yangsScene(player, message, buttons, url = "flash/places/yangs.swf") {
    return genericPlace(player, url, message, buttons);
  }

  const YANGS_MENU = "`wThe Menu\n\n`w#1`$ - Lucky Dragon (adds `w10 `$luck, for here only) Cost: `w5`$ Bury Points\n`w#2`$ - Lucky Sparrow (adds `w5 `$luck, for delivery only) Cost: `w20`$ Bury Points\n`w#3`$ - Cursed Tiger (removes `w10`$ luck, for delivery only) Cost: `w20`$ Bury Points\n";

  function yangsButtons() {
    return [
      { key: "0", label: "Order #1", command: COMMAND.yangsCallback, sub: 3 },
      { key: "1", label: "Order #2", command: COMMAND.yangsCallback, sub: 1 },
      { key: "2", label: "Order #3", command: COMMAND.yangsCallback, sub: 2 },
      { key: "3", label: "Leave", command: COMMAND.map },
    ];
  }

  function yangsLobby(player) {
    const intro = "What could be luckier than a box of delectable grilled teriyaki spread over hot rice? Your mouth waters at the very thought of it.";
    if (player.kickedOut) {
      return yangsScene(
        player,
        `${intro}\n\n\`w${player.name}: \`$\"Hello? I'd like some service here!\"\n\n\`wJune: \`$\"Just leave, asshole.\"`,
        [{ key: "3", label: "Leave", command: COMMAND.map }],
      );
    }
    return yangsScene(player, `${intro}\n\nJune waits for you to order.\n\n${YANGS_MENU}`, yangsButtons());
  }

  function spendPoints(player, cost) {
    const loss = Math.min(player.buryPoints, cost);
    player.buryPoints -= loss;
    player.exp = Math.max(0, (player.exp ?? 0) - loss);
  }

  function changeLuck(player, delta) {
    player.luck = Math.min(100, Math.max(1, (player.luck ?? 50) + delta));
  }

  function cleanFortune(text) {
    return String(text ?? "").replace(/[<>`]/g, "").replace(/\s+/g, " ").trim().slice(0, 120);
  }

  function orderDragon(player) {
    let message;
    if (player.luck >= 40) {
      message = "`$Maybe later.  You especially like to eat Yang's when you're feeling down on your luck. (like less than 40 percent for instance)";
    } else if (player.buryPoints < 5) {
      message = "`$You check your wallet.. shoot, not enough Bury Points!";
    } else {
      spendPoints(player, 5);
      player.luck += 10;
      store.save();
      message = "`$You relish every bite of your General Tso's Chicken.\n\nYou gain `w10`$ luck.";
    }
    return yangsScene(player, message, [{ key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 }]);
  }

  function namePrompt(message, returnSub) {
    return scene([
      ["st_url", art("flash/places/yangs.swf")],
      ["st_message", message],
      ["st_object", "i_getname"],
      ["i_return_command", COMMAND.yangsCallback],
      ["i_return_command_sub", returnSub],
      ["i_sent", SENT.add],
    ]);
  }

  function fortunePrompt(returnSub) {
    return scene([
      ["st_url", art("flash/places/yangs.swf")],
      ["st_message", '`wJune: `$"If you would like to write a custom fortune, enter it now."'],
      ["st_extra_text", ""],
      ["st_object", "i_get_text"],
      ["i_return_command", COMMAND.yangsCallback],
      ["i_return_command_sub", returnSub],
      ["i_sent", SENT.add],
    ]);
  }

  function deliveryBlocked(player, speaker, request) {
    return yangsScene(
      player,
      `\`w${speaker}: \`$\"${request}\"\n\n\`wJune: \`$\"I'm sorry, my dad is out delivering food right now.  How about something for here?\"`,
      [
        { key: "2", label: "Get Bitchy", command: COMMAND.yangsCallback, sub: 105 },
        { key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 },
      ],
    );
  }

  function rememberName(session, player, tokens, kind) {
    if (tokens.st_extra_text === "12ABORT12" || !String(tokens.st_extra_text ?? "").trim()) return yangsScene(player, YANGS_MENU, yangsButtons());
    const found = store.findName(String(tokens.st_extra_text).trim());
    if (!found) return yangsScene(player, YANGS_MENU, yangsButtons());
    session.yangsTarget = found.id;
    session.yangsKind = kind;
    store.save();
    return fortunePrompt(kind === "sparrow" ? 104 : 102);
  }

  function deliverFood(session, player, tokens, kind) {
    if (tokens.st_extra_text === "12ABORT12") {
      session.yangsTarget = null;
      return yangsScene(player, YANGS_MENU, yangsButtons());
    }
    if ((player.yangsSent ?? 0) >= 1) {
      return yangsScene(player, '`$\"We only allow 1 delivery per day,\" Mr Yang tells you apologetically. ', [
        { key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 },
      ]);
    }
    const target = store.state.players.find((entry) => entry.id === session.yangsTarget);
    if (!target) return yangsScene(player, "`$Error delivering food, he wasn't home.", [{ key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 }]);
    if (target.id === player.id) {
      return yangsScene(
        player,
        `\`w${player.name}: \`$\"If you could just deliver that to my parlor, that would be great, m'kay?\"\n\n\`wJune: \`$\"Why don't you just get food for here?\"\n\n\`w${player.name}: \`$\"Ok, fine.\"`,
        [{ key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 }],
      );
    }
    if (player.buryPoints < 20) {
      return yangsScene(player, "`$You don't have enough Bury Points.  You realize you would just look stupid trying to order now...", [
        { key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 },
      ]);
    }
    const typed = cleanFortune(tokens.st_extra_text);
    const fortune = typed || pick(kind === "sparrow" ? kindFortunes : cursedFortunes);
    spendPoints(player, 20);
    player.yangsSent = (player.yangsSent ?? 0) + 1;
    const luck = kind === "sparrow" ? 5 : -10;
    changeLuck(target, luck);
    const cookie = kind === "sparrow"
      ? `\n\`$${player.name} has sent you some chinese food.\nYour fortune cookie reads:\n\`w\"${fortune}\"\n\`$You gain \`w${luck}\`$ luck!\n`
      : `\n\`$Some delicious smelling chinese food has been left on your doorstep.\nYour fortune cookie reads:\n\`w\"${fortune}\"\n\`$You lose \`w10\`$ luck!\n`;
    target.deliveries = target.deliveries || [];
    target.deliveries.push({ text: cookie, art: "flash/chinese_takeout.swf" });
    if (kind === "tiger" && Math.floor(Math.random() * 4) === 1) {
      store.state.news = store.state.news || [];
      const blurbs = [
        `SPOTLIGHT ON OUR TOWN\nLocal mortician ${player.name} is seen buying some Yang's for delivery.`,
        `YANG'S NOW DELIVERS\n"Yes, delivery is very popular.  You see this package from ${player.name}? I'm going deliver it all the way to ${target.funeralName}, no extra charge!" Mr Yang smiles.`,
        `YANG'S DOING WELL\n"Ah, yes.  Business is very good today! People like ${player.name} buying so much delivery," Mr Yang comments.`,
        `YANG'S MAKES A GREAT GIFT\nLooking for that special gift?  How about food? Today ${player.name} was seen buying some, seems popular.`,
      ];
      store.state.news.push(blurbs[Math.floor(Math.random() * blurbs.length)]);
      store.state.news = store.state.news.slice(-8);
    }
    session.yangsTarget = null;
    store.save();
    return yangsScene(player, "`$Mr. Yang leaves to deliver the food.", [
      { key: "3", label: "Continue", command: COMMAND.yangsCallback, sub: 100 },
    ]);
  }

  function yangsCallback(session, player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (player.kickedOut) return yangsLobby(player);
    if (sub === 100) return yangsScene(player, YANGS_MENU, yangsButtons());
    if (sub === 3) return orderDragon(player);
    if (sub === 1) {
      if ((player.yangsSent ?? 0) >= 1) return deliveryBlocked(player, "You", "I would like a lucky sparrow delivered, please.");
      return namePrompt('`wJune: `$"That\'s very nice of you.  Who\'s the lucky person?"', 103);
    }
    if (sub === 2) {
      if ((player.yangsSent ?? 0) >= 1) return deliveryBlocked(player, player.name, "I would like a cursed tiger delivered, please.");
      return namePrompt('`wJune: `$"Ah.. someone you are not too fond of, I take it.  Who would this unlucky person be?" ', 101);
    }
    if (sub === 103) return rememberName(session, player, tokens, "sparrow");
    if (sub === 101) return rememberName(session, player, tokens, "tiger");
    if (sub === 104) return deliverFood(session, player, tokens, "sparrow");
    if (sub === 102) return deliverFood(session, player, tokens, "tiger");
    if (sub === 105) {
      player.kickedOut = true;
      store.save();
      return yangsScene(
        player,
        `\`w${player.name}: \`$\"That's very interesting.  Now get the god damn teriyaki, put it in a box, and deliver it.\"\n\n\`wJune: \`$\"Please leave now.\"`,
        [{ key: "3", label: "Leave", command: COMMAND.map }],
      );
    }
    return yangsLobby(player);
  }

  function hospitalLobby(player) {
    if (player.inHospital) return hospitalRoom(player);
    return storeScene(
      player,
      "flash/places/hospital_desk.swf",
      `You are at the city hospital.  A nurse smiles at you.\n\n\`wNurse: \`$\"Hi ${player.name}.  I don't recall you having a pick-up today.\"`,
      [{ key: "3", label: "Leave", command: COMMAND.map }],
    );
  }

  function hospitalRoom(player) {
    return storeScene(
      player,
      "flash/places/hospital_room.swf",
      "You are lying on a bed, it doesn't look like your own.\n\n`$Your whole body aches.",
      [{ key: "3", label: "Leave", command: COMMAND.hospitalCallback, sub: 1 }],
    );
  }

  function hospitalCallback(player, tokens) {
    if (Number(tokens.i_command_sub) === 1) return hospitalLeave(player);
    if (player.inHospital) return hospitalRoom(player);
    return hospitalLobby(player);
  }

  function hospitalLeave(player) {
    player.inHospital = false;
    player.hp = player.hpMax || player.hp || 10;
    const who = player.male ? "prince charming" : "sleeping beauty";
    return storeScene(
      player,
      "flash/places/hospital_desk.swf",
      `You are at the city hospital.  A nurse smiles at you.\n\n\`wNurse: \`$\"I see you finally woke up, ${who}.  Hope you'll be more careful next time.\"`,
      [{ key: "3", label: "Leave", command: COMMAND.map }],
    );
  }

  function churchLobby(player) {
    return genericPlace(
      player,
      "flash/people/churchguy_h.swf",
      `\`wFather Smyth: \`y"Why hello there, ${player.name}.  Isn't it a wonderfully fantastic day today?  Praise the Lord."`,
      [{ key: "3", label: "Leave", command: COMMAND.map }],
      {
        background: "flash/background/background_church.swf",
        info: "Father Smyth greets you warmly, a little too warmly.  You wonder if he has anything on under that robe.",
      },
    );
  }

  function psychCost(player) {
    return 4 + (player.psych ?? 0) * 2;
  }

  function hpMaxOf(player) {
    return player.hpMax ?? player.hp ?? 10;
  }

  function hpCost(player) {
    return Math.trunc(hpMaxOf(player) * 1.2);
  }

  function strengthCost(player) {
    return (player.strength ?? 3) * 3;
  }

  function spiritButtons() {
    return [
      { key: "a", label: "Library", command: COMMAND.spiritCallback, sub: 5 },
      { key: "0", label: "Take PSY", command: COMMAND.spiritCallback, sub: 1 },
      { key: "1", label: "Take PE", command: COMMAND.spiritCallback, sub: 4 },
      { key: "2", label: "Take ST", command: COMMAND.spiritCallback, sub: 3 },
      { key: "3", label: "Leave", command: COMMAND.map },
    ];
  }

  function spiritLobby(player) {
    const menu = `The role of the funeral director is constantly changing, encompassing more and more responsibilities each year. (for a fee, of course)\n\nTaking classes at SU is a great way to expand your options.\n\n\`wClass schedules\n\n\`$PSY - Social Psychology (cost: \`w${psychCost(player)}\`$ gold tags)\n\`$PE - Physical Education (cost: \`w${hpCost(player)}\`$ gold tags)\n\`$ST - Strength Training (cost: \`w${strengthCost(player)}\`$ gold tags)`;
    return storeScene(player, "flash/places/psych.swf", menu, spiritButtons());
  }

  function spiritContinue(player, message) {
    return storeScene(player, "flash/places/psych.swf", message, [
      { key: "3", label: "Continue", command: COMMAND.spiritCallback, sub: 2 },
    ]);
  }

  function takePsych(player) {
    const cost = psychCost(player);
    if (cost > (player.gold ?? 0)) {
      return spiritContinue(player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
    }
    player.gold -= cost;
    player.psych = (player.psych ?? 0) + 1;
    const skills = {
      1: "Mood reading",
      2: "Financial Estimation",
      3: "Cutting resistance in half",
      4: "Cutting mood in half",
      5: "Doubling spending limit",
    };
    const extra = skills[player.psych] ? `\n\n\`$You have learned a new skill: \`w${skills[player.psych]}` : "";
    store.save();
    return spiritContinue(player, `You study hard for about an hour.  Your psychology level is raised to \`w${player.psych + 1}\`$!${extra}`);
  }

  function takePe(player) {
    const cost = hpCost(player);
    if (cost > (player.gold ?? 0)) {
      return spiritContinue(player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
    }
    player.gold -= cost;
    const gain = 4 + Math.floor(Math.random() * 2);
    const max = hpMaxOf(player);
    player.hpMax = max + gain;
    player.hp = (player.hp ?? max) + gain;
    store.save();
    return spiritContinue(player, `You run laps around the gym.  You gain \`w${gain}\`$ hitpoints!`);
  }

  function takeStrength(player) {
    const cost = strengthCost(player);
    if (cost > (player.gold ?? 0)) {
      return spiritContinue(player, "You fumble through your pockets in vain - you don't have enough gold tags to pay for the class.");
    }
    player.gold -= cost;
    player.strength = (player.strength ?? 3) + 1;
    store.save();
    return spiritContinue(player, "You lift weights for a while.  You gain `w1`$ strength!");
  }

  function spiritLibrary(player) {
    return storeScene(
      player,
      "flash/places/psych.swf",
      "You are in the library.\n\n`$Students are quietly studying here.  You see one nerdy looking kid hogging the lone library computer.\n\n`$The book of deeds is sitting on a pedestal close by.",
      [
        { key: "1", label: "View Deeds", command: COMMAND.spiritCallback, sub: 8 },
        { key: "2", label: "Old Papers", command: COMMAND.spiritCallback, sub: 6 },
        { key: "3", label: "Back", command: COMMAND.spiritCallback, sub: 2 },
      ],
    );
  }

  function spiritDeeds(player) {
    const ranked = store.state.players.filter((entry) => (entry.exp ?? 0) > 0).sort((a, b) => b.exp - a.exp);
    const top = ranked[0];
    let book = "The Book Of Deeds`$\n\nNo one in Deathville has ever done anything worthing noting.\n\nIt's sad, really.\n";
    if (top) {
      const pronoun = top.male ? "he" : "she";
      book += `\nThere is a glimmer of hope in \`w${top.name}\`$, but ${pronoun} has a way to go.`;
    }
    return storeScene(player, "flash/places/psych.swf", book, [
      { key: "3", label: "Back", command: COMMAND.spiritCallback, sub: 9 },
    ]);
  }

  function spiritPaperAsk() {
    const latest = Math.max(0, (store.state.day ?? 1) - 1);
    return scene([
      ["st_url", art("flash/places/psych.swf")],
      ["st_message", `Which old newspaper would you like to look at?  You can choose between 1 and ${latest}.`],
      ["st_object", "i_get_text"],
      ["i_return_command", COMMAND.spiritCallback],
      ["i_return_command_sub", 7],
      ["i_sent", SENT.add],
    ]);
  }

  function spiritPaper(player, tokens) {
    if (tokens.st_extra_text === "12ABORT12") return spiritLibrary(player);
    const day = Number.parseInt(tokens.st_extra_text ?? "", 10);
    const latest = (store.state.day ?? 1) - 1;
    const text = !Number.isInteger(day) || day < 1 || day > latest
      ? `You desperately hunt around for anything dealing with a day ${Number.isNaN(day) ? 0 : day} but fail.`
      : `You find a yellowing paper from day ${day}, it looks like it may have been peed on.`;
    return storeScene(player, "flash/places/psych.swf", text, [
      { key: "3", label: "Back", command: COMMAND.spiritCallback, sub: 9 },
    ]);
  }

  function spiritCallback(player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 2 || sub === 9) return sub === 9 ? spiritLibrary(player) : spiritLobby(player);
    if (sub === 1) return takePsych(player);
    if (sub === 3) return takeStrength(player);
    if (sub === 4) return takePe(player);
    if (sub === 5) return spiritLibrary(player);
    if (sub === 6) return spiritPaperAsk();
    if (sub === 7) return spiritPaper(player, tokens);
    if (sub === 8) return spiritDeeds(player);
    return spiritLobby(player);
  }

  function genericPlace(player, picture, message, buttons, options = {}) {
    const person = String(picture || "").includes("/people/");
    const foreground = options.foreground || (person ? picture : "flash/blank.swf");
    const background = options.background || (person ? "flash/blank.swf" : picture || "flash/blank.swf");
    const fields = [
      ...stats(player),
      ["st_url", art(foreground)],
      ["st_url_bg", art(background)],
      ["st_generic_info", options.info || ""],
      ["st_generic_status", ""],
      ["st_main", message],
      ["st_object", "i_generic"],
      ["b_show_tags", 1],
      ["i_sent", SENT.object],
    ];
    for (const button of buttons) {
      fields.push([`st_option_${button.key}`, button.label], [`i_command_${button.key}`, button.command]);
      if (button.sub != null) fields.push([`i_command_sub_${button.key}`, button.sub]);
    }
    return scene(fields);
  }

  function storeScene(player, url, message, buttons, background) {
    const options = background && typeof background === "object" ? background : { background };
    return genericPlace(player, url, message, buttons, options);
  }

  const STORE_ROOM = "flash/places/store.swf";

  function storeLobby(player) {
    return genericPlace(
      player,
      "flash/people/store_guy.swf",
      `\`wElijah: \`$\"What can I get for you today, ${player.name}?\"`,
      [
        { key: "0", label: "Security", command: COMMAND.storeCallback, sub: 103 },
        { key: "1", label: "Supplies", command: COMMAND.storeCallback, sub: 102 },
        { key: "2", label: "Hearses", command: COMMAND.storeCallback, sub: 100 },
        { key: "3", label: "Leave", command: COMMAND.map },
      ],
      {
        background: STORE_ROOM,
        info: "Di-Mart is not only an official DuraRest (tm) reseller but also carries many other useful supplies for the death care industry.\n\nA really creepy looking guy in a suit smiles at you.",
      },
    );
  }

  function hearseMenu(player) {
    const lines = ['`wElijah: `$"We carry nothing but the best in funeral transportation."\n'];
    const buttons = [];
    const keys = { 1: "a", 2: "0", 3: "1", 4: "2" };
    for (let index = 1; index < TRANSPORT.length; index += 1) {
      const item = TRANSPORT[index];
      lines.push(`\`w${index}\`$ - ${item.name} - Cost: \`w${item.price}\`$ gold tags.`);
      buttons.push({ key: keys[index], label: `View #${index}`, command: COMMAND.storeCallback, sub: index });
    }
    buttons.push({ key: "3", label: "Back", command: COMMAND.store });
    return storeScene(player, "flash/people/store_guy.swf", lines.join("\n"), buttons, STORE_ROOM);
  }

  function hearseLook(session, player, index) {
    const item = TRANSPORT[index];
    const owned = rideOf(player);
    const trade = Math.trunc(owned.price / 2);
    const cost = item.price - trade;
    session.storeItem = index;
    store.save();
    return storeScene(
      player,
      item.url,
      `\`wThe ${item.name}\`$ - ${item.description}\n\nEffect: \`w${item.bonus}\`$ extra turns per day.\n\nAfter trading in your ${owned.name} for \`w${trade}\`$ gold tags, the cost will be \`w${cost}\`$ gold tags.`,
      [
        { key: "2", label: "Buy it", command: COMMAND.storeCallback, sub: 101 },
        { key: "3", label: "Back", command: COMMAND.storeCallback, sub: 100 },
      ],
    );
  }

  function buyHearse(session, player) {
    const index = session.storeItem;
    if (!index || index < 1 || index >= TRANSPORT.length) return hearseMenu(player);
    const item = TRANSPORT[index];
    const owned = rideOf(player);
    const price = item.price - Math.trunc(owned.price / 2);
    if (price > (player.gold ?? 0)) {
      return storeScene(
        player,
        "flash/people/store_guy.swf",
        `\`wElijah: \`$\"I'm sorry, I really don't have time to waste.  You obviously cannot afford a luxury sedan such as the ${item.name}.\"`,
        [{ key: "3", label: "Back", command: COMMAND.storeCallback, sub: 100 }],
        STORE_ROOM,
      );
    }
    player.gold -= price;
    player.transport = index;
    player.turns += item.bonus;
    store.save();
    return storeScene(
      player,
      "flash/stuff/money_bag_2.swf",
      `You trade in your ${owned.name} for a brand new ${item.name}!\n\nYou have time for an extra \`w${item.bonus}\`$ customers today.`,
      [{ key: "3", label: "Back", command: COMMAND.storeCallback, sub: 100 }],
      { background: STORE_ROOM, foreground: "flash/stuff/money_bag_2.swf" },
    );
  }

  function suppliesMenu(player) {
    return genericPlace(
      player,
      "flash/people/store_guy.swf",
      "`wElijah: `y\"We carry a wide assortment of useful items.\"\n\n`w1`y - Lockpick - Cost: `w1`y gold tag\n",
      [
        { key: "2", label: "View #1", command: COMMAND.storeCallback, sub: 110 },
        { key: "3", label: "Back", command: COMMAND.store },
      ],
      { background: STORE_ROOM, info: "You are in the supplies section." },
    );
  }

  function lockpickPage(player) {
    const count = player.lockpicks ?? 0;
    return genericPlace(
      player,
      "flash/blank.swf",
      `\`yHave you ever accidently locked yourself out of your parlor?  A \`wLockpick\`y can 'let' you back in!  These are \`w1\`y gold tag each.  (You currently have \`w${count}\`y lockpick(s))\n\n\`wEffect: \`yWill instantly open any parlor front door.  One time use.`,
      [
        { key: "2", label: "Buy one", command: COMMAND.storeCallback, sub: 111 },
        { key: "3", label: "Back", command: COMMAND.storeCallback, sub: 102 },
      ],
      { background: STORE_ROOM, foreground: "flash/stuff/lockpick.swf" },
    );
  }

  function buyLockpick(player) {
    if ((player.gold ?? 0) < 1) {
      return storeScene(
        player,
        "flash/people/store_guy.swf",
        '`wElija: `$"You don\'t have a single gold tag at the moment?  That\'s too bad.  These are great little lockpicks."',
        [{ key: "3", label: "Continue", command: COMMAND.storeCallback, sub: 102 }],
        STORE_ROOM,
      );
    }
    player.gold -= 1;
    player.lockpicks = (player.lockpicks ?? 0) + 1;
    store.save();
    return genericPlace(
      player,
      "flash/blank.swf",
      "`yYou purchase a `wLockpick`y for `w1`y gold tag.",
      [{ key: "3", label: "Continue", command: COMMAND.storeCallback, sub: 110 }],
      { background: STORE_ROOM, foreground: "flash/stuff/money_bag_2.swf" },
    );
  }

  function securityMenu(player) {
    const padlock = player.padlock ? ` (\`walready own one at ${player.padlockStrength ?? 100} percent strength\`y)` : "";
    const smoke = player.smoke ? " (`walready own one`y)" : "";
    const safe = player.safe ? " (`walready own one`y)" : "";
    return genericPlace(
      player,
      "flash/people/store_guy.swf",
      `\`wElijah: \`y\"This town seems accident prone.  Investing in security is a great idea.\"\n\n\`w1\`y - Heavy Padlock - Cost: \`w6\`y gold tags${padlock}\n\`w2\`y - Smoke Detector - Cost: \`w8\`y gold tags${smoke}\n\`w3\`y - Safe - Cost: \`w6\`y gold tags${safe}\n`,
      [
        { key: "0", label: "View #1", command: COMMAND.storeCallback, sub: 120 },
        { key: "1", label: "View #2", command: COMMAND.storeCallback, sub: 122 },
        { key: "2", label: "View #3", command: COMMAND.storeCallback, sub: 124 },
        { key: "3", label: "Back", command: COMMAND.store },
      ],
      { background: STORE_ROOM, info: "You are in the supplies section." },
    );
  }

  function buyGear(player, field, price, ownedText, poorText, successText) {
    if (player[field]) {
      return storeScene(player, "flash/people/store_guy.swf", ownedText, [
        { key: "3", label: "Continue", command: COMMAND.storeCallback, sub: 103 },
      ], STORE_ROOM);
    }
    if ((player.gold ?? 0) < price) {
      return storeScene(player, "flash/people/store_guy.swf", poorText, [
        { key: "3", label: "Continue", command: COMMAND.storeCallback, sub: 103 },
      ], STORE_ROOM);
    }
    player.gold -= price;
    player[field] = 1;
    if (field === "padlock") player.padlockStrength = 100;
    store.save();
    return genericPlace(player, "flash/blank.swf", successText, [
      { key: "3", label: "Continue", command: COMMAND.storeCallback, sub: 103 },
    ], { background: STORE_ROOM, foreground: "flash/stuff/money_bag_2.swf" });
  }

  function storeCallback(session, player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 100) return hearseMenu(player);
    if (sub === 101) return buyHearse(session, player);
    if (sub === 102) return startFqScript(session, player, "store_supplies.c");
    if (sub === 103) return startFqScript(session, player, "store_security.c");
    if (sub >= 1 && sub <= 4) return hearseLook(session, player, sub);
    if (sub === 110) return lockpickPage(player);
    if (sub === 111) return buyLockpick(player);
    if (sub === 120) {
      return storeScene(
        player,
        "flash/blank.swf",
        "`ySecure your parlor and stop intruders with a new steel laminated `wHeavy Padlock`y.  The shackle is crome plated and hardened to maximize its strength. Only `w6`y gold tags.\n\n`wEffect: `yCasual thieves will no longer be able to break into your parlor.",
        [
          { key: "2", label: "Buy it", command: COMMAND.storeCallback, sub: 121 },
          { key: "3", label: "Back", command: COMMAND.storeCallback, sub: 103 },
        ],
        { background: STORE_ROOM, foreground: "flash/stuff/padlock.swf" },
      );
    }
    if (sub === 121) {
      return buyGear(
        player,
        "padlock",
        6,
        '`wElija: `$"Wait until the one you have breaks. Sorry, company policy."',
        '`wElija: `$"I\'m sorry, you seem to be lacking in funds.  Perhaps another time?"\n\nYou mumble something about leaving the gold tags in your other pants.',
        "`yYou purchase the `wHeavy Padlock`y for `w6`y gold tags.",
      );
    }
    if (sub === 122) {
      return storeScene(
        player,
        "flash/blank.swf",
        "`yA `wSmoke Detector`y will allow you to notice fires and call the fire department much quicker.  It retails here for `w8`y gold tags.\n\n`wEffect: `yReduce the risk of a fire getting large enough to cause damage to 20 percent.",
        [
          { key: "2", label: "Buy it", command: COMMAND.storeCallback, sub: 123 },
          { key: "3", label: "Back", command: COMMAND.storeCallback, sub: 103 },
        ],
        { background: STORE_ROOM, foreground: "flash/stuff/fire_alarm.swf" },
      );
    }
    if (sub === 123) {
      return buyGear(
        player,
        "smoke",
        8,
        '`wElija: `$"Your parlor is quite small.  You really don\'t need two of these things."\n\nYou nod in agreement.  Why waste the cash?',
        '`wElija: `$"I\'m sorry, you seem to be lacking in funds.  Perhaps another time?"\n\nYou feel like a complete dumbass.  He\'s right.',
        "`yYou purchase the `wSmoke Detector`y for `w8`y gold tags.",
      );
    }
    if (sub === 124) {
      return storeScene(
        player,
        "flash/blank.swf",
        "`yOur commercial `wSafe`y is the perfect way to product your valuables.  Features steel castors and automatic re-locking system.  Only `w6`y gold tags.\n\n`wEffect: `ySales tags cannot be stolen or damaged in a fire.",
        [
          { key: "2", label: "Buy it", command: COMMAND.storeCallback, sub: 125 },
          { key: "3", label: "Back", command: COMMAND.storeCallback, sub: 103 },
        ],
        { background: STORE_ROOM, foreground: "flash/stuff/safe.swf" },
      );
    }
    if (sub === 125) {
      return buyGear(
        player,
        "safe",
        6,
        '`wElija: `$"You already own a Safe.  You shouldn\'t need two."',
        '`wElija: `$"I\'m sorry, you seem to be lacking in funds.  Perhaps another time?"\n\nYou nod in agreement.',
        "`yYou purchase the `wSafe`y for `w6`y gold tags.",
      );
    }
    return storeLobby(player);
  }

  const VISITS_PER_DAY = 2;
  const FOG = "A dense fog covers the row upon row of funeral homes. You see disturbed, emotionally unstable people trying to choose which parlor to patronize.  You fight the urge to hand out business cards, you have more important things to attend to.";

  function he(person) {
    return person.male === false ? "she" : "he";
  }

  function He(person) {
    return person.male === false ? "She" : "He";
  }

  function his(person) {
    return person.male === false ? "her" : "his";
  }

  function him(person) {
    return person.male === false ? "her" : "him";
  }

  function playerAt(index) {
    const entry = store.state.players[index];
    if (!entry || entry.active === false) return null;
    return entry;
  }

  function playerIndex(person) {
    return store.state.players.findIndex((entry) => entry.id === person.id);
  }

  function postMail(person, text, picture) {
    person.inbox = person.inbox || [];
    person.inbox.push({ text, art: picture });
  }

  function crimeNews(text) {
    store.state.crime = store.state.crime || [];
    store.state.crime.push(text);
  }

  function endProwl(session) {
    session.prowling = false;
    session.battle = false;
    session.targetIndex = null;
  }

  function foe(session) {
    if (session.targetIndex == null) return null;
    return playerAt(session.targetIndex);
  }

  function isOnline(person, exceptSession) {
    return Object.entries(store.state.sessions).some(([id, entry]) => id !== exceptSession && entry.playerId === person.id);
  }

  function otherProwler(index, sessionId) {
    for (const [id, entry] of Object.entries(store.state.sessions)) {
      if (id === sessionId || !entry.prowling || entry.targetIndex !== index) continue;
      const who = store.player(id);
      if (who) return who;
    }
    return null;
  }

  function placeScreen(player, options) {
    const fields = [
      ...stats(player),
      ["st_url", art(options.foreground || "flash/blank.swf")],
      ["st_url_bg", art(options.background || "flash/blank.swf")],
      ["st_generic_info", options.info || ""],
      ["st_generic_status", ""],
      ["st_main", options.message || ""],
      ["st_object", "i_generic"],
      ["b_show_tags", options.battle ? 2 : 1],
      ["b_show_battle", options.battle ? 1 : 2],
      ["i_sent", SENT.object],
    ];
    for (const extra of options.extra || []) fields.push(extra);
    for (const button of options.buttons || []) {
      fields.push([`st_option_${button.key}`, button.label], [`i_command_${button.key}`, button.command]);
      if (button.sub != null) fields.push([`i_command_sub_${button.key}`, button.sub]);
    }
    return scene(fields);
  }

  function watchText(player) {
    const left = VISITS_PER_DAY - (player.visits ?? 0);
    if (left <= 0) return "You look at your watch, you should really get back to your parlor.\n";
    if (left === 1) return "You look at your watch, you have time to visit `w1`$ more parlor today.\n";
    return `You look at your watch, you have time to visit \`w${left}\`$ more parlors today.\n`;
  }

  function compButtons() {
    return [
      { key: "1", label: "Visit", command: COMMAND.compCallback, sub: 4 },
      { key: "2", label: "Look", command: COMMAND.compCallback, sub: 1 },
      { key: "3", label: "Leave", command: COMMAND.map },
    ];
  }

  function compLobby(session, player) {
    endProwl(session);
    return placeScreen(player, {
      background: "flash/places/palors.swf",
      info: FOG,
      message: watchText(player),
      buttons: compButtons(),
    });
  }

  function padRight(text, width) {
    const value = String(text ?? "");
    return value.length >= width ? value : value + " ".repeat(width - value.length);
  }

  function padLeft(text, width) {
    const value = String(text ?? "");
    return value.length >= width ? value : " ".repeat(width - value.length) + value;
  }

  function monthAndDay() {
    const now = new Date();
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    return `${months[now.getMonth()]} ${now.getDate()}`;
  }

  function rankingStatus(person) {
    const online = Object.values(store.state.sessions).find((entry) => entry.playerId === person.id);
    if (online) return online.chatting ? "in cafe" : "on now";
    return person.police ? "Protected" : "";
  }

  function rankingScreen(returnCommand, hidePolice = false) {
    const people = store.state.players
      .filter((entry) => entry.active !== false && !(hidePolice && entry.police))
      .slice()
      .sort((a, b) => (b.exp ?? 0) - (a.exp ?? 0));
    let message = "";
    let count = 0;
    for (const entry of people) {
      const sex = entry.male === false ? "<font color=\"#FC54FC\">F</font>" : "M";
      message += `${padRight(entry.name, 20)}${sex}  ${padRight(entry.funeralName, 20)}${padLeft(cash(entry.exp ?? 0), 10)} ${rankingStatus(entry)}\n`;
      count += 1;
    }
    while (count % 19 !== 0) {
      message += "\n";
      count += 1;
    }
    return scene([
      ["st_date", monthAndDay()],
      ["st_news", message],
      ["i_rank_command", returnCommand],
      ["i_sent", SENT.ranking],
    ]);
  }

  function lookupName(tokens) {
    const query = String(tokens.st_input || tokens.st_extra_text || "");
    const start = Number(tokens.i_his_id);
    const players = store.state.players;
    let found = -1;
    const needle = query.toLowerCase();
    if (needle && Number.isInteger(start) && start >= 0 && start < players.length) {
      for (let index = start; index < players.length; index += 1) {
        const entry = players[index];
        if (entry.active === false) continue;
        if (entry.name.toLowerCase().includes(needle)) {
          found = index;
          break;
        }
      }
    }
    return scene([
      ["st_object", "i_namenext"],
      ["i_his_id", found],
      ["st_message", found === -1 ? "" : players[found].name],
      ["i_sent", SENT.add],
    ]);
  }

  function compAskName() {
    return scene([
      ["st_message", "`$Who's funeral home would you like to pay a little visit to?"],
      ["st_object", "i_getname"],
      ["i_return_command", COMMAND.compCallback],
      ["i_return_command_sub", 5],
      ["i_sent", SENT.add],
    ]);
  }

  function compVisitBlocked(player) {
    return placeScreen(player, {
      background: "flash/places/palors.swf",
      info: FOG,
      message: "Is today look at everyone's parlor day?  No, it's not.  Maybe tomorrow.",
      buttons: [{ key: "3", label: "Continue", command: COMMAND.compCallback, sub: 3 }],
    });
  }

  function compInFront(session, player) {
    const target = foe(session);
    if (!target) return compLobby(session, player);
    const info = `You are standing in front of a funeral parlor.  The sign above the door proudly announces the name, "${target.funeralName}". `;
    const picture = {
      background: "flash/places/funeral_enemy.swf",
      foreground: "flash/music/music_sneak.swf",
      info,
    };
    if (target.id === player.id) {
      endProwl(session);
      return placeScreen(player, {
        ...picture,
        message: "This parlor looks strangely familiar.\n\nYou decide to go inside...",
        buttons: [{ key: "3", label: "Continue", command: COMMAND.main }],
      });
    }
    if (isOnline(target, session.id)) {
      endProwl(session);
      return placeScreen(player, {
        ...picture,
        message: `You see ${target.name} through the window talking earnestly to a young couple, perhaps later would be better...`,
        buttons: [{ key: "3", label: "Leave", command: COMMAND.comp }],
      });
    }
    if (target.police) {
      endProwl(session);
      return placeScreen(player, {
        ...picture,
        background: "flash/places/funeral_enemy_police.swf",
        message: `You park your ${rideOf(player).name} a few blocks away from ${target.funeralName}.  As you approach you see ${target.name} talking to some cops, maybe you should come back after things cool down.`,
        buttons: [{ key: "3", label: "Leave", command: COMMAND.comp }],
      });
    }
    const rival = otherProwler(session.targetIndex, session.id);
    if (rival) {
      endProwl(session);
      return placeScreen(player, {
        ...picture,
        message: `As you take your position behind a bush, you are surprised to see ${rival.name} already sitting there.\n\n\`w${rival.name}: \`$"Oh, hey ${player.name}.  Tell ya what, ${he(target)}'s mine first, ok?"\n\nYou agree to come back later.`,
        buttons: [{ key: "3", label: "Leave", command: COMMAND.comp }],
      });
    }
    player.visits = (player.visits ?? 0) + 1;
    session.prowling = true;
    store.save();
    return placeScreen(player, {
      ...picture,
      message: `The lights are off, the parlor is silent.  ${target.name} must be sleeping inside.\n\nNobody seems to be looking, you could try the door-knob if you like.\n\nYou see a \`w${rideOf(target).name}\`$ parked nearby.`,
      buttons: [
        { key: "2", label: "Try Door", command: COMMAND.compCallback, sub: 6 },
        { key: "3", label: "Leave", command: COMMAND.comp },
      ],
    });
  }

  function compGotName(session, player, tokens) {
    if ((player.visits ?? 0) >= VISITS_PER_DAY) return compLobby(session, player);
    const raw = tokens.i_his_id;
    const index = raw == null || raw === "" ? -1 : Number(raw);
    const target = playerAt(index);
    if (!target) return compLobby(session, player);
    session.targetIndex = index;
    return compInFront(session, player);
  }

  function doorMenu(player, message) {
    return placeScreen(player, {
      background: "flash/places/funeral_enemy.swf",
      foreground: "flash/music/music_sneak.swf",
      message,
      buttons: [
        { key: "2", label: "Pick Lock", command: COMMAND.compCallback, sub: 9 },
        { key: "3", label: "Leave", command: COMMAND.comp },
      ],
    });
  }

  function compTryDoor(session, player) {
    const target = foe(session);
    if (!target || !session.prowling) return compLobby(session, player);
    if (target.padlock > 0) {
      const holding = player.lockpicks > 0 ? `\n\nYou are holding \`w${cash(player.lockpicks)}\`y lockpick(s).` : "";
      const text = player.lockpicks > 0
        ? `There is a heavy padlock on the door at \`w${target.padlockStrength} percent strength\`y. What do you want to do?${holding}`
        : `There is a heavy padlock on the door at \`w${target.padlockStrength} percent strength\`y.  What do you want to do?`;
      return doorMenu(player, text);
    }
    return compInside(session, player);
  }

  function compPickLock(session, player) {
    const target = foe(session);
    if (!target || !session.prowling) return compLobby(session, player);
    if ((player.lockpicks ?? 0) < 1) {
      return doorMenu(player, "You attempt to pick the lock with your finger.  No go.  Perhaps if you had a real lockpick...");
    }
    const weakened = between(5, 10);
    target.padlockStrength = (target.padlockStrength ?? 0) - weakened;
    player.lockpicks -= 1;
    let message;
    if (target.padlockStrength < 1) {
      target.padlock = 0;
      target.padlockStrength = 0;
      message = "As you are picking the lock the whole thing breaks and falls off!  Too bad your lockpick got stuck inside it.";
      postMail(target, "`yYour padlock is `4damaged beyond repair`y by a prowler and falls off!", "flash/stuff/padlock.swf");
    } else {
      message = `\`yYou successfully pick the lock.  Your lockpick breaks as you try to get it out which causes the padlock \`w${weakened}\`y points of damage.`;
      postMail(target, `\`yYour padlock is weakened by \`4${weakened}\`y down to \`w${target.padlockStrength} percent strength\`y by a prowler.`, "flash/stuff/padlock.swf");
    }
    store.save();
    return placeScreen(player, {
      background: "flash/places/funeral_enemy.swf",
      foreground: "flash/music/music_sneak.swf",
      message,
      buttons: [{ key: "3", label: "Continue", command: COMMAND.compCallback, sub: 10 }],
    });
  }

  function compInside(session, player) {
    const target = foe(session);
    if (!target || !session.prowling) return compLobby(session, player);
    let message = `You quickly slip in and close the door.  You hear ${target.name} softly snoring in the back room.`;
    if (target.safe > 0) message += "\n\nYou see a large combination `wSafe`$ in the corner.  You won't be able to steal tags.";
    message += `\n\nJudging from the room, you would guess ${target.name} has \`w${target.hpMax}\`$ hitpoints.`;
    const buttons = [];
    if ((target.safe ?? 0) === 0) buttons.push({ key: "2", label: "Steal", command: COMMAND.compCallback, sub: 7 });
    buttons.push({ key: "3", label: "Attack", command: COMMAND.compCallback, sub: 8 });
    return placeScreen(player, {
      background: "flash/places/desk_enemy.swf",
      info: `You have broken into ${target.funeralName}.`,
      message,
      buttons,
    });
  }

  function stealTags(from, to, max) {
    const kinds = [
      ["wood", "wooden"],
      ["silver", "silver"],
      ["gold", "gold"],
    ];
    const [id, label] = kinds[Math.floor(Math.random() * kinds.length)];
    if ((from[id] ?? 0) <= 0) return { count: -1, label };
    let count = between(0, max);
    if (count > from[id]) count = from[id];
    from[id] -= count;
    to[id] = (to[id] ?? 0) + count;
    return { count, label };
  }

  function compSteal(session, player) {
    const target = foe(session);
    if (!target || !session.prowling) return compLobby(session, player);
    if ((target.safe ?? 0) > 0) return compInside(session, player);
    const stolen = stealTags(target, player, 3);
    let message;
    if (stolen.count === -1) message = "You look around but can't find anything of value.";
    else if (stolen.count === 0) message = "You begin wildly opening drawers and things looking for anything of value.";
    else if (stolen.count === 1) message = `You look around and find \`w1 ${stolen.label}\`$ tag hidden on top of the refrigerator!`;
    else message = `You look around and find \`w${stolen.count} ${stolen.label}\`$ tags in a drawer!`;
    const clothes = player.male !== false && target.male === false ? "lingerie" : "pajamas";
    message += `\n\nYou are startled by a noise behind you.  It's ${target.name} in ${his(target)} ${clothes}! You crash through a window and run like hell.`;
    let note = `\n\`wYou caught ${player.name} sneaking around inside your parlor!\`$\n`;
    if (stolen.count === 1) note += `You scare ${him(player)} off.  You notice \`w${stolen.count} ${stolen.label} tag \`$is missing.`;
    else if (stolen.count > 1) note += `You scare ${him(player)} off.  You notice \`w${stolen.count} ${stolen.label} tags \`$are missing.`;
    else note += `You scare ${him(player)} off before ${he(player)} could steal anything.`;
    postMail(target, note, "flash/people/man_prowl.swf");
    endProwl(session);
    store.save();
    return placeScreen(player, {
      background: "flash/places/desk_enemy.swf",
      message,
      buttons: [{ key: "3", label: "Leave", command: COMMAND.comp }],
    });
  }

  function swingDamage(strength) {
    const half = (strength + 1) / 2;
    return Math.floor(Math.random() * half + half);
  }

  function hitVerb(amount, mine) {
    const bands = [[1, 2, "slap", "slaps"], [3, 4, "sock", "socks"], [5, 6, "punch", "punches"], [7, 8, "kick", "kicks"], [9, 10, "throw", "throws"], [11, 12, "squeeze", "squeezes"], [13, 14, "ninja", "ninjas"]];
    const band = bands.find(([low, high]) => amount >= low && amount <= high);
    if (!band) return mine ? "slam" : "slams";
    return mine ? band[2] : band[3];
  }

  function battlePoints(winner, loser, defense) {
    const won = Math.floor((loser.exp ?? 0) * (defense ? 0.01 : 0.02));
    const lost = Math.floor((loser.exp ?? 0) * (defense ? 0.005 : 0.01));
    const apply = (who, amount) => {
      who.buryPoints = Math.max(0, (who.buryPoints ?? 0) + amount);
      who.exp = Math.max(0, (who.exp ?? 0) + amount);
    };
    apply(loser, -lost);
    apply(winner, won);
    return { won, lost };
  }

  function battleMeters(me, him) {
    return [
      ["st_stats_me", `${me.name} - ${me.hp} of ${me.hpMax} hitpoints`],
      ["st_stats_him", `${him.name} - ${him.hp} of ${him.hpMax} hitpoints`],
      ["i_hp_me", me.hp],
      ["i_hp_him", him.hp],
      ["i_hp_max_me", me.hpMax],
      ["i_hp_max_him", him.hpMax],
    ];
  }

  function battleScreen(player, target, message, buttons, artUrl, blankBackground) {
    return placeScreen(player, {
      background: blankBackground ? "flash/blank.swf" : "flash/places/funeral_enemy.swf",
      foreground: artUrl || "flash/music/music_fight.swf",
      info: "You are in battle.",
      message,
      buttons,
      battle: true,
      extra: battleMeters(player, target),
    });
  }

  function battleMenus() {
    return [
      { key: "2", label: "Attack", command: COMMAND.battleCallback, sub: 2 },
      { key: "3", label: "Run", command: COMMAND.battleCallback, sub: 1 },
    ];
  }

  function battleOver(player, target) {
    return player.hp <= 0 || target.hp <= 0;
  }

  function attackerSwing(player, target) {
    let hit = swingDamage(player.strength ?? 0);
    if (hit === 0) return `${target.name} dodges your swing.\n\n`;
    if (Math.floor(Math.random() * 10) === 0) {
      hit *= 2;
      target.hp -= hit;
      return `You \`wsoul smash\`$ ${target.name} for \`4${hit}\`$ damage!\n\n`;
    }
    target.hp -= hit;
    return `You ${hitVerb(hit, true)} ${target.name} for \`4${hit}\`$ damage.\n\n`;
  }

  function defenderSwing(player, target) {
    let hit = swingDamage(target.strength ?? 0);
    if (Math.floor(Math.random() * 20) === 0 || hit === 0) {
      return `${target.name} swings but you sidestep the blow.\n\n`;
    }
    player.hp -= hit;
    return `${target.name} ${hitVerb(hit, false)} you for \`4${hit}\`$ damage.\n\n`;
  }

  function finishBattle(session, player, target, lead) {
    if (target.hp < 1) {
      target.hp = 0;
      target.police = true;
      target.inHospital = true;
      const stolen = stealTags(target, player, 4);
      const points = battlePoints(player, target, false);
      const pocket = stolen.count < 1
        ? " Nothing was stolen."
        : stolen.count === 1
          ? ` \`w${stolen.count}\`$ ${stolen.label} tag was stolen from your pocket.`
          : ` \`w${stolen.count}\`$ ${stolen.label} tags were stolen from your pockets.`;
      postMail(
        target,
        `\n\`wYou were attacked in your sleep by ${player.name}!\`$\n\nYou are badly injured.  You vaguely remember riding in an ambulance.\n\n\`$You lose \`w${cash(points.lost)} \`$Bury Points.${pocket}`,
        "flash/stuff/battle_lose.swf",
      );
      crimeNews(`It looks like ${target.name} was taken to the hospital.`);
      const found = stolen.count < 1
        ? `You go through ${his(target)} pockets but find nothing.`
        : stolen.count === 1
          ? `You go through ${his(target)} pockets and find \`w${stolen.count} \`$${stolen.label} tag.`
          : `You go through ${his(target)} pockets and find \`w${stolen.count} \`$${stolen.label} tags.`;
      session.battle = false;
      session.prowling = false;
      store.save();
      return battleScreen(
        player,
        target,
        `${lead}${target.name} crumples to the floor. You have won the battle!\n\nYou earn \`w${cash(points.won)} \`$Bury Points. ${found}`,
        [{ key: "3", label: "Continue", command: COMMAND.battleCallback, sub: 3 }],
        "flash/stuff/money_bag_2.swf",
      );
    }
    if (player.hp < 1) {
      player.hp = 0;
      player.inHospital = true;
      const points = battlePoints(target, player, true);
      postMail(
        target,
        `\n\`wYou were attacked in your sleep by ${player.name}!\`$\n\nYou woke up and kicked ${his(player)} ass!\n\n\`$You earn \`w${cash(points.won)} \`$Bury Points. (applied to day ${store.state.day})`,
        "flash/places/hospital_ambulance.swf",
      );
      crimeNews(`It looks like ${player.name} was taken to the hospital.`);
      session.battle = false;
      session.prowling = false;
      store.save();
      return battleScreen(
        player,
        target,
        `${lead}You fall down, unable to get up.\n\nYou lose \`w${cash(points.lost)} \`$Bury Points.`,
        [{ key: "3", label: "Continue", command: COMMAND.battleCallback, sub: 4 }],
        "flash/stuff/battle_lose.swf",
        true,
      );
    }
    return null;
  }

  function openingLine(player, target) {
    const roll = Math.floor(Math.random() * 6);
    if (roll === 0) return "Stop stealing my customers!";
    if (roll === 1) return "I will BURY you!";
    if (roll === 2) return `Messing with me was a grave mistake, dear ${target.name}.`;
    if (roll === 3) return target.male === false ? "Discount this, bitch!" : "Discount this, bastard!";
    if (roll === 4) return "I was in this town first!";
    return "You're an amateur! You don't belong here!";
  }

  function compAttack(session, player) {
    const target = foe(session);
    if (!target || !session.prowling) return compLobby(session, player);
    player.hp = player.hpMax;
    target.hp = target.hpMax;
    session.battle = true;
    let message = `\`w"${openingLine(player, target)}" \`$you scream as you break into ${his(target)} room.\n\n`;
    if ((player.plots ?? 0) > (target.plots ?? 0)) {
      message += `${target.name} is completely surprised, you get first swing!\n\n`;
    } else {
      message += `${target.name}'s eyes flick open, ${he(target)} knew you were in the house!\n\n`;
      message += defenderSwing(player, target);
      const done = finishBattle(session, player, target, message);
      if (done) return done;
    }
    store.save();
    return battleScreen(player, target, message, battleMenus());
  }

  function battleAttack(session, player) {
    const target = foe(session);
    if (!target || !session.battle || battleOver(player, target)) {
      return scene([
        ["g_st_error", "Your FQ client is acting oddly.  (Be careful, this has been logged...)"],
        ["i_sent", SENT.fail],
      ]);
    }
    let message = attackerSwing(player, target);
    const won = finishBattle(session, player, target, message);
    if (won) return won;
    message += defenderSwing(player, target);
    const lost = finishBattle(session, player, target, message);
    if (lost) return lost;
    store.save();
    return battleScreen(player, target, message, battleMenus());
  }

  function battleRun(session, player) {
    const target = foe(session);
    if (!target || !session.battle || battleOver(player, target)) {
      return scene([
        ["g_st_error", "Your FQ client is acting oddly.  (Be careful, this has been logged...)"],
        ["i_sent", SENT.fail],
      ]);
    }
    postMail(target, `\n\`wYou were attacked in your sleep by ${player.name}!\`$\n\n${He(player)} got scared and ran off.\n\n`, "flash/people/man_prowl.swf");
    endProwl(session);
    store.save();
    return placeScreen(player, {
      background: "flash/places/funeral_enemy.swf",
      message: "You backflip through an open window and take off running.",
      buttons: [{ key: "3", label: "Continue", command: COMMAND.comp }],
    });
  }

  function cleanYell(text) {
    return String(text ?? "").replace(/[<>`]/g, "").replace(/\s+/g, " ").trim().slice(0, 120);
  }

  function battleYell(kind) {
    const message = kind === "win"
      ? "`wYou hear police sirens in the distance... you can yell something as you leave or just hit ENTER to continue."
      : "`wYou feel yourself being lifted into an ambulance.  Mutter something or just hit ENTER to continue.";
    return scene([
      ["st_message", message],
      ["st_extra_text", ""],
      ["st_object", "i_get_text"],
      ["i_return_command", COMMAND.battleCallback],
      ["i_return_command_sub", kind === "win" ? 5 : 6],
      ["i_sent", SENT.add],
    ]);
  }

  function battleHeard(session, player, tokens, won) {
    const target = foe(session);
    const spoken = cleanYell(tokens.st_extra_text);
    const quiet = tokens.st_extra_text === "12ABORT12" || spoken.length < 2;
    if (target) {
      if (won) {
        crimeNews(quiet
          ? `\`8${player.name} brutally assaulted ${target.name}!`
          : `\`8${player.name} brutally assaulted ${target.name}!\`6\n"${spoken}" \`8justified ${player.name}.`);
        if (!quiet) postMail(target, `\`$While recovering you can't get what ${player.name} yelled while escaping out of your head...\n\n\`w"${spoken}"\`$`, "flash/places/hospital_room.swf");
      } else {
        crimeNews(quiet
          ? `\`8${target.name} assaulted ${player.name} in self defense!`
          : `\`8${target.name} assaulted ${player.name} in self defense!\`6\n"${spoken}" \`8moaned ${player.name} from the ambulance.`);
        if (!quiet) postMail(target, `\`$As ${player.name} is leaving you hear ${him(player)} moaning from the ambulance...\n\n\`w"${spoken}"\`$`, "flash/places/hospital_ambulance.swf");
      }
    }
    store.save();
    if (won) return compLobby(session, player);
    endProwl(session);
    return hospitalRoom(player);
  }

  function battleCallback(session, player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 1) return battleRun(session, player);
    if (sub === 2) return battleAttack(session, player);
    if (sub === 3) return battleYell("win");
    if (sub === 4) return battleYell("lost");
    if (sub === 5) return battleHeard(session, player, tokens, true);
    if (sub === 6) return battleHeard(session, player, tokens, false);
    return compLobby(session, player);
  }

  function compCallback(session, player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 1) return rankingScreen(COMMAND.comp, true);
    if (sub === 3) return compLobby(session, player);
    if (sub === 4) return (player.visits ?? 0) >= VISITS_PER_DAY ? compVisitBlocked(player) : compAskName();
    if (sub === 5) return compGotName(session, player, tokens);
    if (sub === 6) return compTryDoor(session, player);
    if (sub === 7) return compSteal(session, player);
    if (sub === 8) return compAttack(session, player);
    if (sub === 9) return compPickLock(session, player);
    if (sub === 10) return compInside(session, player);
    return compLobby(session, player);
  }

  const NAME_CHANGE_COST = 1;

  function cleanPlayerName(text) {
    return [...String(text ?? "").replace(/[?|=/\\;:,{}@+`<>%$]/g, "")]
      .filter((character) => character.charCodeAt(0) < 123)
      .join("");
  }

  function rankOf(player) {
    const active = store.state.players.filter((entry) => entry.active !== false);
    const sorted = active.slice().sort((a, b) => (b.exp ?? 0) - (a.exp ?? 0) || a.id - b.id);
    const index = sorted.findIndex((entry) => entry.id === player.id);
    if (index < 0 || sorted.length === 0) return { rank: 1, percent: 100 };
    const percent = 100 - Math.trunc((index / sorted.length) * 100);
    return { rank: index + 1, percent: Math.min(100, Math.max(1, percent)) };
  }

  function equipmentNote(player) {
    const lines = [];
    if (player.smoke) lines.push("Smoke detector installed.");
    if (player.padlock) lines.push(`Your parlor entrance is padlocked. (\`w${player.padlockStrength ?? 0} percent strength\`y)`);
    if (player.safe) lines.push("You own a safe.");
    return lines.length ? `\`y${lines.join("\n")}` : "`yYour stats.";
  }

  function statsScreen(player) {
    const standing = rankOf(player);
    const rating = ratings.get(standing.percent) || "(No text for this rating.  Email Seth some ideas!)";
    return scene([
      ...stats(player),
      ["i_plots", cash(player.plots ?? 0)],
      ["i_psych", `Level ${cash((player.psych ?? 0) + 1)}`],
      ["st_rating", `#${standing.rank} - ${rating}`],
      ["i_yangs", cash(Math.max(0, 1 - (player.yangsSent ?? 0)))],
      ["i_visits", cash(Math.max(0, 2 - (player.visits ?? 0)))],
      ["i_hit_max", cash(player.hpMax ?? 10)],
      ["i_strength", cash(player.strength ?? 3)],
      ["i_lockpick", cash(player.lockpicks ?? 0)],
      ["st_ride", rideOf(player).name],
      ["st_temp", equipmentNote(player)],
      ["st_option_2", "Name"],
      ["i_command_2", COMMAND.options],
      ["st_option_3", "Back"],
      ["i_command_3", COMMAND.main],
      ["i_sent", 3],
    ]);
  }

  function changeNameScreen(player, message, buttons) {
    return placeScreen(player, {
      foreground: "flash/stuff/question.swf",
      info: "You stare at yourself in the mirror.\n\nMaybe it's time for a change.",
      message,
      buttons,
    });
  }

  function optionsScreen(player) {
    return changeNameScreen(
      player,
      "`$Announcing your new name in the newspaper will cost `w1`$ gold tag(s).  Do it?",
      [
        { key: "2", label: "Yes", command: COMMAND.optionsCallback, sub: 2 },
        { key: "3", label: "No", command: COMMAND.stats },
      ],
    );
  }

  function optionsCallback(player, tokens) {
    const sub = Number(tokens.i_command_sub);
    if (sub === 2) {
      return scene([
        ["st_message", "`wEnter your new name."],
        ["st_extra_text", ""],
        ["st_object", "i_get_text"],
        ["i_return_command", COMMAND.optionsCallback],
        ["i_return_command_sub", 1],
        ["i_sent", SENT.add],
      ]);
    }
    if (sub !== 1) return optionsScreen(player);
    const raw = tokens.st_extra_text ?? "";
    const next = cleanPlayerName(raw);
    if (raw === "12ABORT12") return optionsScreen(player);
    if ((player.gold ?? 0) < NAME_CHANGE_COST) {
      return changeNameScreen(player, "`$You don't have enough gold tags.  Go exchange your woods or something.", [
        { key: "3", label: "Continue", command: COMMAND.stats },
      ]);
    }
    if (next === player.name) {
      return changeNameScreen(player, `\`$Yeah, ${next}.. you like the sound of that... oh wait...`, [
        { key: "3", label: "Continue", command: COMMAND.options },
      ]);
    }
    if (next.length < 3) {
      return changeNameScreen(player, "`$What, are those initials?  Pick a longer name.", [
        { key: "3", label: "Continue", command: COMMAND.options },
      ]);
    }
    if (next.length > 20) {
      return changeNameScreen(player, "`$Is that a name or a lecture?  Try something shorter.", [
        { key: "3", label: "Continue", command: COMMAND.options },
      ]);
    }
    const taken = store.state.players.find((entry) => entry.id !== player.id && entry.name.toLowerCase() === next.toLowerCase());
    if (taken) {
      return changeNameScreen(player, `\`$Um, don't you think the real ${next} would kind of be pissed?  Try something original.`, [
        { key: "3", label: "Continue", command: COMMAND.options },
      ]);
    }
    const previous = player.name;
    player.gold -= NAME_CHANGE_COST;
    player.name = next;
    addNews(`\`5NAME CHANGE: ${previous} has changed ${his(player)} name to ${next}.`);
    store.save();
    return changeNameScreen(player, `\`$You will now be known as \`w${next}\`$.`, [
      { key: "3", label: "Continue", command: COMMAND.stats },
    ]);
  }

  const TWINS_COST = 6;
  const TWINS_ART = "flash/people/m_old_3.swf";
  const TWINS_BG = "flash/background/background_5.swf";

  function twinsButtons() {
    return [
      { key: "2", label: "Hire Twins", command: COMMAND.scriptCallback, sub: 1 },
      { key: "3", label: "No Thanks", command: COMMAND.main },
    ];
  }

  function twinsOffer(player, message) {
    const pitch = message || `\`wThe Twins: \`y"We like to play with fire.  We'll discreetly pay a 'visit' to any funeral parlor in town for only ${TWINS_COST} gold tags."\n\nA fire will destroy 5 percent of the parlors gravestones and burn all wood tags. If the parlor has a fire alarm, there is only a 20 percent chance of success.`;
    return movieScene(player, pitch, TWINS_ART, TWINS_BG, twinsButtons());
  }

  function twinsAskName(session) {
    session.script = { kind: "twins", step: "name" };
    return scene([
      ["st_message", "Who would you like the Twins to pay a visit to?"],
      ["st_object", "i_getname"],
      ["i_return_command", COMMAND.scriptCallback],
      ["b_kill_buttons", 1],
      ["i_sent", SENT.add],
    ]);
  }

  function twinsBroke(session, player) {
    session.script = null;
    return movieScene(player, `\`wThe Twins: \`y"You don't have enough gold tags, fool!  We shall come again..."`, TWINS_ART, TWINS_BG, [
      { key: "3", label: "Continue", command: COMMAND.main },
    ]);
  }

  function twinsStrike(session, player, target) {
    session.script = null;
    player.gold -= TWINS_COST;
    const burned = Math.trunc((target.plots ?? 0) * 0.05);
    if ((target.smoke ?? 0) > 0 && between(1, 10) > 2) {
      target.gold = (target.gold ?? 0) + 3;
      postMail(target, "\n`yA mysterious fire was started on your property.  Thanks to your smoke detector the fire was put out before any serious damage was done.  The police chief awards you `w3`y gold tags!\n", "flash/stuff/fire_alarm.swf");
      addNews(`\`7EARLY WARNING SAVES PARLOR\n\`^A small fire was quickly put out at local business ${target.funeralName} before any damage was done. "It was a close call," said a relieved ${target.name}.\n\n`);
      return movieScene(player, "`yYou watch from your window.\n\n`4The fire is put out before it can do any serious damage!\n\n`yDamn smoke detectors!", "flash/blank.swf", null, [
        { key: "3", label: "Continue", command: COMMAND.main },
      ]);
    }
    const woodLost = target.wood ?? 0;
    const safe = (target.safe ?? 0) > 0;
    if (!safe) target.wood = 0;
    target.plots = Math.max(0, (target.plots ?? 0) - burned);
    addNews(Math.random() < 0.5
      ? `\`7PARLOR DAMAGED BY FIRE, ARSON SUSPECTED\n\`^A small fire was not noticed until too late at ${target.funeralName} today.  "This isn't happening!" said ${target.name}, still in shock.\n\n`
      : `\`7PARLOR GETS BURNED\n\`^A small fire was not noticed until too late at ${target.funeralName} today.  "Faulty wiring my ass, someone is gonna pay for this!" said a distraught ${target.name}.\n\n`);
    postMail(target, safe
      ? `\n\`yA mysterious fire was started on your property.  You lost \`4${burned}\`y gravestone(s) but your \`wSafe\`y protected all your tags.\n`
      : `\n\`yA mysterious fire was started on your property.  You lost \`4${burned}\`y gravestone(s) and \`4${woodLost}\`y wood tag(s).\n`, "flash/stuff/fire_alarm.swf");
    const message = safe
      ? `\`yYou watch from your window.\n\n\`wA fire engulfs ${target.funeralName}!\n\n\`y${target.name} loses \`w${burned}\`y gravestone(s).  You see a shiny safe through a window.`
      : `\`yYou watch from your window.\n\n\`wA fire engulfs ${target.funeralName}!\n\n\`y${target.name} loses \`w${burned}\`y gravestone(s) and \`w${woodLost}\`y wood tag(s).`;
    return movieScene(player, message, "flash/places/fire.swf", null, [
      { key: "3", label: "Continue", command: COMMAND.main },
    ]);
  }

  function twinsChosen(session, player, tokens) {
    const index = Number(tokens.i_his_id);
    const retry = (message) => {
      session.script = { kind: "twins", step: "menu" };
      return twinsOffer(player, message);
    };
    const target = Number.isInteger(index) ? playerAt(index) : null;
    if (!target) return retry("`yThe twins look confused.  Better try again.");
    if (target.id === player.id) return retry("`yEven as a joke you don't think you should suggest the twins burn down your own building.  Try again.");
    if (isOnline(target, session.id)) return retry(`\`yThe twins shake their heads vigorously.  Apparently they saw ${target.name} working in the yard.`);
    if ((player.gold ?? 0) < TWINS_COST) return twinsBroke(session, player);
    return twinsStrike(session, player, target);
  }

  function scriptCallback(session, player, tokens) {
    const result = Object.hasOwn(tokens, "i_his_id") ? Number(tokens.i_his_id) : Number(tokens.i_command_sub);
    return resumeFqScript(session, player, Number.isFinite(result) ? result : 0);
  }

  function bonusTag(player) {
    const kind = ["wood", "silver", "gold"][between(0, 2)];
    player[kind] = (player[kind] ?? 0) + 1;
    const place = ["davenport", "shed", "desk", "embalming fluids", "stereo", "parlor"][between(0, 5)];
    return movieScene(player, `\`yWhile cleaning behind the ${place} you find a \`w${kind} tag\`y.`, "flash/stuff/money_bag_2.swf", null, [
      { key: "3", label: "Continue", command: COMMAND.main },
    ]);
  }

  function scriptEvent(session, player) {
    session.customer = null;
    session.risk = null;
    return startFqScript(session, player, "script_event.c");
  }

  const TAGS_PER_DAY = 1;
  const swearWords = loadSwearWords(publicRoot);

  function loadSwearWords(root) {
    const file = path.join(root, "..", "swear_words.txt");
    if (!fs.existsSync(file)) return [];
    return fs.readFileSync(file, "utf8").split(/\r?\n/).flatMap((line) => {
      const parts = line.split("|");
      if (parts[0] !== "addword" || !parts[1]) return [];
      return [[parts[1], parts[2] || ""]];
    });
  }

  function filterChat(text) {
    let out = String(text);
    for (const [bad, good] of swearWords) {
      out = out.replaceAll(new RegExp(bad.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), "gi"), good);
    }
    return out;
  }

  function pushChat(text) {
    for (const entry of Object.values(store.state.sessions)) {
      if (!entry.chatting || entry.playerId == null) continue;
      entry.chatLog = entry.chatLog || [];
      entry.chatLog.push(text);
    }
  }

  function pushChatTo(playerId, text) {
    for (const entry of Object.values(store.state.sessions)) {
      if (entry.playerId !== playerId) continue;
      entry.chatLog = entry.chatLog || [];
      entry.chatLog.push(text);
    }
  }

  function cafeRoster(sessionId) {
    const names = [];
    for (const [id, entry] of Object.entries(store.state.sessions)) {
      if (!entry.chatting || entry.playerId == null || id === sessionId) continue;
      const person = store.player(id);
      if (person) names.push(person.name);
    }
    if (!names.length) return "`$You are the only one here.";
    return `\`$Other people here:${names.map((name, index) => (index < names.length - 1 ? ` ${name},` : ` ${name}`)).join("")}`;
  }

  function openCafe(session, player) {
    session.chatting = true;
    session.location = "in cafe";
    pushChat(`\`5${player.name} saunters in.`);
    const text = `\`$The \`wPale Horse \`$has been here since before you can remember.  A great place to gossip or just sip a mochachino and watch tv.\n\nYou always wondered why they had that big glass bottle on the roof.\n\n${cafeRoster(session.id)}\nEnter /? for help.\n\n`;
    store.save();
    return scene([
      ...stats(player),
      ["st_message", text],
      ["st_object", "i_chat"],
      ["i_chat_speed", 2000],
      ["i_sent", SENT.object],
    ]);
  }

  function cafePerson(name) {
    const needle = String(name || "").toLowerCase();
    for (const [id, entry] of Object.entries(store.state.sessions)) {
      if (!entry.chatting || entry.playerId == null) continue;
      const person = store.player(id);
      if (person && person.name.toLowerCase().includes(needle)) return person;
    }
    return null;
  }

  function giveTag(player, text) {
    const parts = text.split(" ");
    const target = cafePerson(parts[1] || "");
    if (!target) {
      pushChatTo(player.id, `\`5You look around the room but don't see anyone with \`w'${parts[1] || ""}'\`5 in their name.`);
      return;
    }
    const kind = String(parts[2] || "").toLowerCase();
    const label = { wood: "Wood", silver: "Silver", gold: "Gold" }[kind];
    if (!label) {
      pushChatTo(player.id, "`5Valid tag types are wood, silver and gold.");
      return;
    }
    if ((player[kind] ?? 0) < 1) {
      pushChat(`\`5${player.name} looks in his pocket for a ${label} tag but comes up empty.`);
      return;
    }
    if (target.id === player.id) {
      pushChat(`\`5${player.name} absently tosses a ${label} tag into the air and catches it.`);
      return;
    }
    const received = target.tagsReceived ?? 0;
    if (received >= TAGS_PER_DAY) {
      const reason = received === 0
        ? "the admin has disabled tag transfer"
        : `he's already received ${received} today`;
      pushChat(`\`5${player.name} is unable to give \`w${target.name} \`5a ${label} tag. (${reason})`);
      return;
    }
    player[kind] -= 1;
    target[kind] = (target[kind] ?? 0) + 1;
    target.tagsReceived = received + 1;
    pushChat(`\`5${player.name} gives \`w${target.name} \`5a ${label} tag.`);
  }

  function showCafeInfo(player) {
    const visits = 2 - (player.visits ?? 0);
    pushChatTo(player.id, `\`5You have \`w${player.turns ?? 0}\`5 turns left today and \`w${visits}\`5 parlor visits.\nToday's BP's: \`w${cash(player.buryPoints ?? 0)}\`5, total BP's: \`w${cash(player.exp ?? 0)}\`5.\nCasket tags: \`w${player.wood ?? 0}\`5 Wood, \`w${player.silver ?? 0}\`5 Silver, \`w${player.gold ?? 0}\`5 Gold.`);
  }

  function listOnline(player) {
    const rows = ["\n`wName                 Status            Last hit", "`$-----------------------------------------------"];
    const now = Date.now();
    for (const entry of Object.values(store.state.sessions)) {
      if (entry.playerId == null) continue;
      const person = store.state.players.find((item) => item.id === entry.playerId);
      if (!person) continue;
      const seconds = Math.max(1, Math.floor((now - (entry.lastPacket || now)) / 1000));
      const span = seconds >= 86400 ? [seconds / 86400, "Day"] : seconds >= 3600 ? [seconds / 3600, "Hr"] : seconds >= 60 ? [seconds / 60, "Min"] : [seconds, "Sec"];
      const when = `${String(Math.round(span[0])).padStart(6)} ${span[1]}`;
      rows.push(`${person.name.padEnd(20)}${String(entry.location || "Parlor").padEnd(12)}  ${when}`);
    }
    for (const row of rows) pushChatTo(player.id, row);
  }

  function cafeCommand(player, text) {
    const command = (text.split(" ")[0] || "").toLowerCase();
    if (command === "?") {
      pushChatTo(player.id, "\n** `$Cafe chat help **`w\n\n`w/yell `$to send a message to everyone\n`w/me`$ to do a custom action\n`w/all `$to see who is online now\n`w/give name tag_type `$to give a tag. Example: `w/give Seth gold\n`w/info `$to show how many BP's, turns and tags you have now.\n`w/tv on (or off) `$to control the cafe tv.\n");
      return;
    }
    if (command === "who") {
      pushChatTo(player.id, cafeRoster(Object.keys(store.state.sessions).find((id) => store.state.sessions[id].playerId === player.id) || ""));
      return;
    }
    if (command === "info") return showCafeInfo(player);
    if (command === "give") return giveTag(player, text);
    if (command === "tv") {
      const setting = text.split(" ")[1] || "";
      if (!setting) {
        pushChatTo(player.id, store.state.tvOn === false ? "`5The TV is currently off, that stuff will rot your brain." : "`5The TV is currently on.");
      } else if (setting.toLowerCase() === "on") {
        store.state.tvOn = true;
        pushChatTo(player.id, "`5You turn the tv on.");
      } else {
        store.state.tvOn = false;
        pushChatTo(player.id, "`5You turn the tv off.");
      }
      return;
    }
    if (command === "me") {
      pushChat(`\`$${player.name} ${filterChat(text.slice(3))}`);
      return;
    }
    if (command === "yell") {
      const when = new Date().toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", hour12: true });
      pushChat(`\`w${when}:${player.name} yells: ${filterChat(text.slice(5))}`);
      return;
    }
    if (command === "all") return listOnline(player);
    pushChatTo(player.id, "Unknown command.  Do /? for the list.");
  }

  function processChat(player, input) {
    let rest = String(input);
    while (rest.length) {
      const bar = rest.indexOf("|");
      const line = bar === -1 ? rest : rest.slice(0, bar);
      rest = bar === -1 ? "" : rest.slice(bar + 1);
      if (!line) continue;
      if (line.startsWith("/")) cafeCommand(player, line.slice(1));
      else pushChat(`\`$${player.name}: \`w${filterChat(line)}<font size="12">`);
    }
  }

  function chatPoll(session, player, tokens) {
    const input = String(tokens.st_input || "");
    const sending = tokens.b_send_input == null || tokens.b_send_input === "" || tokens.b_send_input === "1";
    if (sending && input) {
      processChat(player, input);
      store.save();
    }
    const lines = session.chatLog || [];
    session.chatLog = [];
    return scene([
      ["st_message", lines.length ? `${lines.join("\r")}\r` : ""],
      ["i_sent", COMMAND.chatUpdate],
    ]);
  }

  function handleCommand(session, player, tokens) {
    const command = Number(tokens.i_command);
    if (!(command === COMMAND.chatUpdate && !tokens.st_input)) session.lastPacket = Date.now();
    if (command !== COMMAND.chatStart && command !== COMMAND.chatUpdate && command !== COMMAND.bar && session.chatting) {
      if (command === COMMAND.map) pushChat(`\`5${player.name} steps outside.`);
      session.chatting = false;
      store.save();
    }
    if (command === COMMAND.quit) {
      store.logoff(session.id);
      return scene([["i_sent", SENT.quit]]);
    }
    if (command === COMMAND.hospital) return hospitalLobby(player);
    if (command === COMMAND.hospitalCallback) return hospitalCallback(player, tokens);
    if (command === COMMAND.church) return startFqScript(session, player, "public/church.c");
    if (command === COMMAND.spirit) return spiritLobby(player);
    if (command === COMMAND.spiritCallback) return spiritCallback(player, tokens);
    if (command === COMMAND.yangs) return yangsLobby(player);
    if (command === COMMAND.yangsCallback) return yangsCallback(session, player, tokens);
    if (command === COMMAND.store) return storeLobby(player);
    if (command === COMMAND.storeCallback) return storeCallback(session, player, tokens);
    if (command === COMMAND.bank) return bankLobby(player);
    if (command === COMMAND.bankCallback) return bankCallback(session, player, tokens);
    if (command === COMMAND.main) return main(session, player);
    if (command === COMMAND.news) return showFront(session, store.state.day);
    if (command === COMMAND.yesterday) return showFront(session, store.state.day - 1);
    if (command === COMMAND.front) return showFront(session, session.newsDay ?? store.state.day);
    if (command === COMMAND.crime) return showCrime(session);
    if (command === COMMAND.announcement) return announce(session, player, tokens);
    if (command === COMMAND.map) {
      session.location = "Town Map";
      return scene([
        ...stats(player),
        ["st_object", "i_map"],
        ["i_sent", SENT.object],
      ]);
    }
    if (command === COMMAND.mail) return sendMail(player, tokens);
    if (command === COMMAND.reply) return scene([["i_sent", SENT.callback]]);
    if (command === COMMAND.bar || command === COMMAND.chatStart) return openCafe(session, player);
    if (command === COMMAND.chatUpdate) return chatPoll(session, player, tokens);
    if (command === COMMAND.script) {
      const name = String(tokens.st_extra_text || "");
      if (!/^[A-Za-z0-9_.-]+$/.test(name)) return main(session, player);
      return startFqScript(session, player, `public/${name}`);
    }
    if (command === COMMAND.getname) return lookupName(tokens);
    if (command === COMMAND.comp) return compLobby(session, player);
    if (command === COMMAND.compCallback) return compCallback(session, player, tokens);
    if (command === COMMAND.battleCallback) return battleCallback(session, player, tokens);
    if (command === COMMAND.ranking) return rankingScreen(COMMAND.main);
    if (command === COMMAND.stats) return startFqScript(session, player, "stats.c");
    if (command === COMMAND.options) return optionsScreen(player);
    if (command === COMMAND.optionsCallback) return optionsCallback(player, tokens);
    if (command === COMMAND.riskAccept) {
      const response = acceptRisk(session, player);
      store.save();
      return response;
    }
    if (command === COMMAND.reaction) {
      const response = takeRisk(session, player);
      store.save();
      return response;
    }
    if (command === COMMAND.scriptCallback) {
      const response = scriptCallback(session, player, tokens);
      store.save();
      return response;
    }
    if (command === COMMAND.wait) {
      scriptRuns.delete(session.id);
      if (player.turns < 1) return noTurns(player);
      if (Math.random() < 0.08) {
        const event = specialEvent(player);
        if (event) {
          store.save();
          return event;
        }
      }
      session.customer = makeCustomer(player);
      if (risks.length && Math.random() < 0.08) {
        session.risk = Math.floor(Math.random() * risks.length);
        store.save();
        return showRisk(player, risks[session.risk]);
      }
      session.risk = null;
      if (Math.random() < 0.05) {
        const event = scriptEvent(session, player);
        store.save();
        return event;
      }
      session.script = null;
      player.turns -= 1;
      store.save();
      return showCustomer(player, session.customer);
    }
    if (!session.customer && [COMMAND.soft, COMMAND.hard, COMMAND.sympathy, COMMAND.guilt, COMMAND.finish].includes(command)) {
      return scene([
        ...stats(player),
        ["g_st_error", "Can't accept, no customer exists"],
        ["i_sent", SENT.fail],
      ]);
    }
    if (command === COMMAND.finish && session.customer) {
      const response = finishSale(player, session.customer);
      session.customer = null;
      store.save();
      return response;
    }
    if (session.customer && command === COMMAND.soft) {
      const response = softSell(session, player);
      store.save();
      return response;
    }
    if (session.customer && command === COMMAND.hard) {
      const response = hardSell(session, player);
      store.save();
      return response;
    }
    if (session.customer && command === COMMAND.sympathy) {
      const response = sympathySell(session, player);
      store.save();
      return response;
    }
    if (session.customer && command === COMMAND.guilt) {
      const response = guiltSell(session, player);
      store.save();
      return response;
    }

    const places = {
      [COMMAND.graveyard]: ["flash/places/funeral-ceremony.swf", "`$The cemetery is quiet."],
      [COMMAND.cityHall]: ["flash/places/palors.swf", "`$City Hall keeps the town records."],
    };
    const known = places[command];
    if (known) return location(player, known[0], known[1]);
    return location(player, "flash/places/desk.swf", `\`$That action (${command || "none"}) is not in the browser slice yet.`);
  }

  function validateNew(tokens) {
    const name = tokens.name ?? "";
    const logon = tokens.logon ?? "";
    const funeralName = tokens.f_name ?? "";
    const password = tokens.password ?? "";
    const confirm = tokens.password_v ?? "";
    const email = tokens.email ?? "";
    const unsafe = /[<>`"\\]/;
    const checks = [
      [unsafe.test(name) || unsafe.test(logon) || unsafe.test(funeralName), "`wNames cannot include punctuation like < > or quotes.", SENT.newName],
      [name.length < 3, "`wYour name is too short.  Try a longer one.", SENT.newName],
      [name.length > 20, "`wYour name is too long.  Try a shorter one.", SENT.newName],
      [store.findName(name), `\`wSomeone is already using the name ${name}. Please try another.`, SENT.newName],
      [logon.length < 3, "`wYour logon name is too short.  Try a longer one.", SENT.newDetails],
      [logon.length > 20, "`wYour logon name is too long.  Try a shorter one.", SENT.newDetails],
      [store.findLogon(logon), `\`wSomeone is already using the logon name of ${logon}. Please try another.`, SENT.newDetails],
      [funeralName.length < 3, "`wYour funeral parlor name is too short.  Try a longer one.", SENT.newDetails],
      [funeralName.length > 20, "`wYour funeral parlor name is too long.  Try a shorter one.", SENT.newDetails],
      [password.length < 1, "`wYour password is too short.  Try a longer one.", SENT.newDetails],
      [password.length > 20, "`wYour password is too long.  Try a shorter one.", SENT.newDetails],
      [password !== confirm, "`wYour passwords don't match!  Try again, genius.", SENT.newDetails],
      [email.length < 4, "`wEmail address required.  (Only used for game purposes)", SENT.newDetails],
      [email.length > 45, "`wEmail address is too big.  Try a smaller one.", SENT.newDetails],
    ];
    return checks.find(([failed]) => failed) ?? null;
  }

  return {
    parseRequest,
    maintain,
    runScript(sessionId, relativePath) {
      const session = store.session(sessionId);
      session.id = sessionId;
      const player = store.player(sessionId);
      if (!player) return null;
      return startFqScript(session, player, relativePath);
    },
    handle(url, sessionId) {
      const request = parseRequest(url);
      if (!request) return null;
      const session = store.session(sessionId);
      session.id = sessionId;
      if (request.command === "checklogon") {
        if (session.playerId != null) store.logoff(sessionId);
        return intro();
      }
      if (request.command === "new") {
        const problem = validateNew(request.tokens);
        if (problem) {
          return scene([
            ["g_st_error", problem[1]],
            ["i_sent", problem[2]],
          ]);
        }
        const created = store.createPlayer({
          name: request.tokens.name,
          logon: request.tokens.logon,
          funeralName: request.tokens.f_name,
          password: request.tokens.password,
          email: request.tokens.email,
          male: request.tokens.b_male === "1",
        });
        store.state.news = store.state.news || [];
        store.state.news.push(`\`5A new parlor (${created.funeralName}) run by ${created.name} has been opened!\n\n`);
        store.logon(sessionId, created.id);
        store.save();
        return showFront(session, store.state.day);
      }
      if (request.command === "logon") {
        const found = store.findLogon(request.tokens.st_logon ?? "");
        if (!store.checkPassword(found, request.tokens.st_password ?? "")) {
          return scene([["i_sent", SENT.unknownUser]]);
        }
        store.logon(sessionId, found.id);
        return showFront(session, store.state.day);
      }
      if (request.command === "command") {
        const player = store.player(sessionId);
        if (!player) {
          return scene([
            ["g_st_error", "Connection with server died. (user not logged on)"],
            ["i_sent", SENT.fail],
          ]);
        }
        refreshDay(session, player);
        return handleCommand(session, player, request.tokens);
      }
      return scene([
        ["g_st_error", "Unknown request"],
        ["i_sent", SENT.fail],
      ]);
    },
  };
}
