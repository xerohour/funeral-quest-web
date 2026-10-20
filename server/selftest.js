import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { createStore } from "./store.js";
import { createGame } from "./game.js";
import { createApp } from "./index.js";

const dir = fs.mkdtempSync(path.join(os.tmpdir(), "fq-"));
const publicRoot = path.resolve("upstream", "dist", "public");
const store = createStore(path.join(dir, "players.json"));
const game = createGame(store, publicRoot);
let failed = 0;

function expect(label, body, includes) {
  const ok = includes.every((part) => body.includes(part));
  if (!ok) {
    failed += 1;
    console.error(`FAIL ${label}\n${body.slice(0, 400)}`);
  } else {
    console.log(`ok ${label}`);
  }
}

const intro = game.handle("/request.xp?checklogon", "test");
expect("intro", intro, ["i_sent=1"]);

const bad = game.handle("/request.xp?new&name=Al&logon=albert&f_name=Rest&password=a&password_v=a&email=a@b.co&b_male=1", "test");
expect("short name", bad, ["i_sent=4"]);

const created = game.handle("/request.xp?new&name=Albert&logon=albert&f_name=Restful&password=secret&password_v=secret&email=albert@example.com&b_male=1", "test");
expect("create", created, ["i_sent=5", "Restful"]);

const unknown = game.handle("/request.xp?logon&st_logon=albert&st_password=wrong", "other");
expect("bad password", unknown, ["i_sent=2"]);

const logged = game.handle("/request.xp?logon&st_logon=albert&st_password=secret", "other");
expect("logon", logged, ["i_sent=5"]);

const desk = game.handle("/request.xp?command&i_command=4", "other");
expect("morning", desk, ["morning_coffee.swf", "i_sent=10"]);

const home = game.handle("/request.xp?command&i_command=4", "other");
expect("desk", home, ["flash/places/desk.swf", "i_sent=10"]);

let customer = "";
for (let attempt = 0; attempt < 40 && !customer.includes("funeral+service+for+my"); attempt += 1) {
  customer = game.handle("/request.xp?command&i_command=2", "other");
  if (customer.includes("i_command_3=31")) game.handle("/request.xp?command&i_command=31", "other");
}
expect("customer", customer, ["flash/people/", "funeral+service+for+my", "Total%3A", "i_command_3=30", "i_sent=2"]);

const sitting = store.session("other").customer;
sitting.cheapness = 0;
sitting.moodValue = 0;
sitting.budget = 500000;
sitting.loved = false;
const soft = game.handle("/request.xp?command&i_command=25", "other");
expect("soft sell", soft, ["Thank+you", "I'll+take+it", "Total%3A", "i_command_2=25"]);
if (sitting.options.length !== 1) {
  failed += 1;
  console.error(`FAIL soft sell options ${sitting.options.length}`);
} else {
  console.log("ok soft sell added an extra");
}
sitting.cheapness = 0;
sitting.moodValue = 1;
sitting.casketIndex = 0;
sitting.casket = { id: "wood", label: "wood", price: 800, points: 1 };
sitting.upgrade = -1;
sitting.options = [];
sitting.budget = 500000;
const harder = game.handle("/request.xp?command&i_command=26", "other");
expect("hard sell", harder, ["Silver+Casket"]);
if (sitting.casketIndex !== 1) {
  failed += 1;
  console.error(`FAIL casket index ${sitting.casketIndex}`);
} else {
  console.log("ok casket upgraded");
}
sitting.loved = false;
sitting.cheapness = 5;
sitting.moodValue = 1;
const guilt = game.handle("/request.xp?command&i_command=29", "other");
expect("guilt", guilt, ["Total%3A"]);
if (!(sitting.cheapness < 5)) {
  failed += 1;
  console.error(`FAIL guilt cheapness ${sitting.cheapness}`);
} else {
  console.log("ok guilt loosened the purse");
}
sitting.loved = true;
sitting.cheapness = 5;
sitting.moodValue = 1;
const sympathy = game.handle("/request.xp?command&i_command=27", "other");
expect("sympathy", sympathy, ["Total%3A"]);
if (!(sitting.cheapness < 5)) {
  failed += 1;
  console.error(`FAIL sympathy cheapness ${sitting.cheapness}`);
} else {
  console.log("ok sympathy loosened the purse");
}

const done = game.handle("/request.xp?command&i_command=30", "other");
expect("sale finished", done, ["money_bag_2.swf", "service+is+a+success", "sales+tag", "i_sent=2"]);

const parlor = store.session("other");
const proprietor = store.player("other");
const pointsBefore = proprietor.buryPoints;
parlor.customer = { waiting: true };
parlor.risk = 0;
const ring = game.handle("/request.xp?command&i_command=31", "other");
expect("return the ring", ring, ["the+ring", "Thank+you+so+much", "Bury+Point", "i_sent=2"]);
if (proprietor.buryPoints < pointsBefore + 4 || parlor.customer) {
  failed += 1;
  console.error(`FAIL ring points=${proprietor.buryPoints} customer=${Boolean(parlor.customer)}`);
} else {
  console.log("ok ring returned");
}

const saved = JSON.parse(fs.readFileSync(path.join(dir, "players.json"), "utf8"));
const albert = saved.players.find((player) => player.logon === "albert");
expect("hashed password", JSON.stringify(albert), ['"passwordHash":"scrypt$']);
if (Object.hasOwn(albert, "password")) {
  failed += 1;
  console.error("FAIL plaintext password was stored");
}

const { server } = createApp(path.join(dir, "api.json"));
await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const base = `http://127.0.0.1:${server.address().port}`;

async function post(route, body, cookie) {
  const response = await fetch(`${base}${route}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Origin: base,
      ...(cookie ? { Cookie: cookie } : {}),
    },
    body: JSON.stringify(body),
  });
  const payload = await response.json();
  const setCookie = response.headers.get("set-cookie") || "";
  const match = setCookie.match(/fq_session=[^;]+/);
  return { status: response.status, payload, cookie: match ? match[0] : cookie, setCookie };
}

const shortPassword = await post("/api/register", {
  name: "Nora",
  logon: "nora",
  funeralName: "Quiet",
  email: "nora@example.com",
  password: "secret",
  male: false,
});
if (shortPassword.status !== 400) {
  failed += 1;
  console.error(`FAIL short api password status ${shortPassword.status}`);
} else {
  console.log("ok api rejects short password");
}

const registered = await post("/api/register", {
  name: "Nora",
  logon: "nora",
  funeralName: "Quiet",
  email: "nora@example.com",
  password: "longsecret",
  male: false,
});
const cookieOk = registered.setCookie.includes("HttpOnly") && registered.setCookie.includes("SameSite=Lax") && registered.cookie?.startsWith("fq_session=");
if (registered.status !== 200 || registered.payload.player?.logon !== "nora" || !cookieOk) {
  failed += 1;
  console.error(`FAIL api register ${registered.status} ${registered.setCookie} ${JSON.stringify(registered.payload).slice(0, 300)}`);
} else {
  console.log("ok api register");
}

const denied = await post("/api/action", { command: 2 });
if (denied.status !== 401) {
  failed += 1;
  console.error(`FAIL unauthenticated action ${denied.status}`);
} else {
  console.log("ok api requires a session");
}

const state = await fetch(`${base}/api/state`, { headers: { Cookie: registered.cookie } });
const stateBody = await state.json();
if (state.status !== 200 || stateBody.player?.funeralName !== "Quiet" || !stateBody.screen?.story) {
  failed += 1;
  console.error(`FAIL api state ${state.status} ${JSON.stringify(stateBody).slice(0, 300)}`);
} else {
  console.log("ok api state");
}

const sale = await post("/api/action", { command: 2 }, registered.cookie);
if (sale.status !== 200 || sale.payload.screen?.kind !== "customer" || !sale.payload.screen.art.includes("flash/people/")) {
  failed += 1;
  console.error(`FAIL api customer ${sale.status} ${JSON.stringify(sale.payload.screen).slice(0, 300)}`);
} else {
  console.log("ok api customer");
}

const teller = store.player("other");
teller.wood = 5;
teller.silver = 2;
teller.gold = 1;
store.save();
const bank = game.handle("/request.xp?command&i_command=39", "other");
expect("bank", bank, ["flash/places/bank.swf", "st_object=i_generic", "i_sent=10", "All+to+wood", "i_command_sub_2=1"]);
const toWood = game.handle("/request.xp?command&i_command=104&i_command_sub=18", "other");
expect("all to wood", toWood, ["i_coffin_wood=13.00", "i_coffin_silver=0.00", "i_coffin_gold=0.00"]);
const traded = game.handle("/request.xp?command&i_command=104&i_command_sub=5&st_extra_text=4", "other");
expect("wood to gold", traded, ["i_coffin_wood=9.00", "i_coffin_gold=1.00", "converted+4+wood"]);
const tooSmall = game.handle("/request.xp?command&i_command=104&i_command_sub=5&st_extra_text=1", "other");
expect("bank refuses a short trade", tooSmall, ["can't+trade+just+1"]);

teller.gold = 10;
teller.transport = 0;
teller.turns = 15;
teller.lockpicks = 0;
teller.smoke = 0;
store.save();
const shop = game.handle("/request.xp?command&i_command=40", "other");
expect("di-mart", shop, ["store.swf", "store_guy.swf", "st_generic_info=", "Elijah", "Hearses"]);
const hearses = game.handle("/request.xp?command&i_command=105&i_command_sub=100", "other");
expect("hearse list", hearses, ["Budget+Hearse", "View+#1", "gold+tags"]);
const looked = game.handle("/request.xp?command&i_command=105&i_command_sub=1", "other");
expect("hearse price", looked, ["hearse1.swf", "cost+will+be+<font+color=%22#FFFFFF%22>10"]);
const bought = game.handle("/request.xp?command&i_command=105&i_command_sub=101", "other");
expect("buy hearse", bought, ["i_coffin_gold=0.00", "i_turns=17.00", "Budget+Hearse"]);
if (teller.transport !== 1) {
  failed += 1;
  console.error(`FAIL transport is ${teller.transport}`);
} else {
  console.log("ok transport saved");
}
teller.gold = 0;
store.save();
game.handle("/request.xp?command&i_command=105&i_command_sub=4", "other");
const refused = game.handle("/request.xp?command&i_command=105&i_command_sub=101", "other");
expect("hearse too expensive", refused, ["cannot+afford"]);
teller.gold = 1;
store.save();
const supplies = game.handle("/request.xp?command&i_command=105&i_command_sub=102", "other");
expect("supplies", supplies, ["supplies+section", "Lockpick", "i_command_2=109"]);
const viewPick = game.handle("/request.xp?command&i_command=109&i_command_sub=1", "other");
expect("lockpick page", viewPick, ["lockpick.swf", "accidently"]);
const pick = game.handle("/request.xp?command&i_command=109&i_command_sub=2", "other");
expect("lockpick", pick, ["Lockpick", "i_coffin_gold=0.00"]);
if (teller.lockpicks !== 1) {
  failed += 1;
  console.error(`FAIL lockpicks ${teller.lockpicks}`);
} else {
  console.log("ok lockpick saved");
}
teller.gold = 8;
store.save();
const security = game.handle("/request.xp?command&i_command=105&i_command_sub=103", "other");
expect("security", security, ["Smoke+Detector", "accident+prone"]);
const viewAlarm = game.handle("/request.xp?command&i_command=109&i_command_sub=3", "other");
expect("smoke page", viewAlarm, ["fire_alarm.swf", "20+percent"]);
const alarm = game.handle("/request.xp?command&i_command=109&i_command_sub=2", "other");
expect("smoke detector", alarm, ["Smoke+Detector", "i_coffin_gold=0.00"]);
game.handle("/request.xp?command&i_command=105&i_command_sub=103", "other");
game.handle("/request.xp?command&i_command=109&i_command_sub=3", "other");
const secondAlarm = game.handle("/request.xp?command&i_command=109&i_command_sub=2", "other");
expect("one smoke detector", secondAlarm, ["don't+need+two"]);

const helen = store.createPlayer({
  name: "Helen",
  logon: "helen",
  funeralName: "Quietrest",
  password: "secret",
  email: "helen@example.com",
  male: false,
});
teller.buryPoints = 10;
teller.exp = 10;
teller.luck = 30;
teller.yangsSent = 0;
teller.kickedOut = false;
store.save();
const yangs = game.handle("/request.xp?command&i_command=13", "other");
expect("yangs", yangs, ["yangs.swf", "Lucky+Dragon", "Order+#1"]);
const dragon = game.handle("/request.xp?command&i_command=14&i_command_sub=3", "other");
expect("lucky dragon", dragon, ["General+Tso", "i_luck=40", "i_sp=5.00"]);
const full = game.handle("/request.xp?command&i_command=14&i_command_sub=3", "other");
expect("dragon when lucky", full, ["Maybe+later"]);
teller.luck = 10;
teller.buryPoints = 4;
store.save();
const skint = game.handle("/request.xp?command&i_command=14&i_command_sub=3", "other");
expect("dragon costs points", skint, ["not+enough+Bury+Points"]);
teller.buryPoints = 25;
teller.exp = 25;
teller.yangsSent = 0;
store.save();
const ask = game.handle("/request.xp?command&i_command=14&i_command_sub=1", "other");
expect("sparrow asks for a name", ask, ["i_getname", "lucky+person"]);
const named = game.handle("/request.xp?command&i_command=14&i_command_sub=103&st_extra_text=Helen", "other");
expect("custom fortune", named, ["i_get_text", "custom+fortune"]);
const sent = game.handle("/request.xp?command&i_command=14&i_command_sub=104&st_extra_text=Extra+sauce", "other");
expect("yangs delivered", sent, ["Mr.+Yang+leaves"]);
if (helen.luck !== 55 || teller.buryPoints !== 5 || teller.yangsSent !== 1) {
  failed += 1;
  console.error(`FAIL yangs result luck=${helen.luck} points=${teller.buryPoints} sent=${teller.yangsSent}`);
} else {
  console.log("ok yangs delivery changed luck and points");
}
store.logon("helenbox", helen.id);
const cookie = game.handle("/request.xp?command&i_command=4", "helenbox");
expect("fortune cookie", cookie, ["Extra+sauce", "gain"]);
const blocked = game.handle("/request.xp?command&i_command=14&i_command_sub=2", "other");
expect("one delivery a day", blocked, ["out+delivering", "Get+Bitchy"]);
const rude = game.handle("/request.xp?command&i_command=14&i_command_sub=105", "other");
expect("kicked out", rude, ["Please+leave"]);
const banned = game.handle("/request.xp?command&i_command=13", "other");
expect("june remembers", banned, ["Just+leave"]);

teller.gold = 0;
store.save();
const hospital = game.handle("/request.xp?command&i_command=37", "other");
expect("hospital desk", hospital, ["hospital_desk.swf", "don't+recall", "Leave"]);
teller.inHospital = true;
store.save();
const room = game.handle("/request.xp?command&i_command=37", "other");
expect("recovery room", room, ["hospital_room.swf", "whole+body+aches"]);
const woke = game.handle("/request.xp?command&i_command=101&i_command_sub=1", "other");
expect("left the hospital bed", woke, ["prince+charming"]);
if (teller.inHospital) {
  failed += 1;
  console.error("FAIL still in the hospital");
} else {
  console.log("ok hospital stay cleared");
}
const church = game.handle("/request.xp?command&i_command=34", "other");
expect("church", church, ["churchguy_h.swf", "Father+Smyth", "Praise+the+Lord"]);

const classes = game.handle("/request.xp?command&i_command=36", "other");
expect("spirit university", classes, ["psych.swf", "Social+Psychology", "Take+PSY", "Library"]);
const broke = game.handle("/request.xp?command&i_command=100&i_command_sub=1", "other");
expect("class costs gold", broke, ["fumble+through+your+pockets"]);
teller.gold = 4;
store.save();
const studied = game.handle("/request.xp?command&i_command=100&i_command_sub=1", "other");
expect("psychology class", studied, ["Mood+reading", "i_coffin_gold=0.00"]);
if (teller.psych !== 1) {
  failed += 1;
  console.error(`FAIL psych ${teller.psych}`);
} else {
  console.log("ok psychology level saved");
}
teller.gold = 9;
store.save();
const lifted = game.handle("/request.xp?command&i_command=100&i_command_sub=3", "other");
expect("strength class", lifted, ["lift+weights", "i_coffin_gold=0.00"]);
if (teller.strength !== 4) {
  failed += 1;
  console.error(`FAIL strength ${teller.strength}`);
} else {
  console.log("ok strength saved");
}
teller.gold = 12;
store.save();
const laps = game.handle("/request.xp?command&i_command=100&i_command_sub=4", "other");
expect("gym class", laps, ["run+laps", "hitpoints", "i_coffin_gold=0.00"]);
if (teller.hpMax !== 14 && teller.hpMax !== 15) {
  failed += 1;
  console.error(`FAIL hpMax ${teller.hpMax}`);
} else {
  console.log("ok hit points saved");
}
const library = game.handle("/request.xp?command&i_command=100&i_command_sub=5", "other");
expect("library", library, ["book+of+deeds", "Old+Papers"]);
const deeds = game.handle("/request.xp?command&i_command=100&i_command_sub=8", "other");
expect("deeds", deeds, ["worthing+noting", "Albert"]);
const papers = game.handle("/request.xp?command&i_command=100&i_command_sub=6", "other");
expect("old papers", papers, ["i_get_text", "1+and+0"]);
const missing = game.handle("/request.xp?command&i_command=100&i_command_sub=7&st_extra_text=5", "other");
expect("missing paper", missing, ["day+5", "but+fail"]);

const row = game.handle("/request.xp?command&i_command=38", "other");
expect("parlor row", row, ["palors.swf", "dense+fog", "more+parlors", "Visit"]);
const listed = game.handle("/request.xp?command&i_command=102&i_command_sub=1", "other");
const todayStamp = `${["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][new Date().getMonth()]}+${new Date().getDate()}`;
expect("parlor list", listed, ["i_sent=9", "Helen", "i_rank_command=38", "on+now", "%22#FC54FC%22", `st_date=${todayStamp}`]);
store.logoff("helenbox");
helen.police = true;
const fullRank = game.handle("/request.xp?command&i_command=9", "other");
expect("full ranking", fullRank, ["i_rank_command=4", "Protected", "Helen", `st_date=${todayStamp}`]);
const hidden = game.handle("/request.xp?command&i_command=102&i_command_sub=1", "other");
if (hidden.includes("Helen")) {
  failed += 1;
  console.error("FAIL police protection still listed on the parlor row");
} else {
  console.log("ok police protection hides a parlor from the row");
}
helen.police = false;
store.logon("helenbox", helen.id);
const askWho = game.handle("/request.xp?command&i_command=102&i_command_sub=4", "other");
expect("whose parlor", askWho, ["i_getname", "i_sent=14", "Who's+funeral"]);
const lookedUp = game.handle("/request.xp?command&i_command=17&st_input=Hel&i_his_id=0", "other");
expect("name lookup", lookedUp, ["i_namenext", "st_message=Helen", "i_his_id=1"]);
const helenIndex = store.state.players.findIndex((entry) => entry.id === helen.id);
const occupied = game.handle(`/request.xp?command&i_command=102&i_command_sub=5&i_his_id=${helenIndex}`, "other");
expect("owner is home", occupied, ["young+couple"]);
store.logoff("helenbox");
const dark = game.handle(`/request.xp?command&i_command=102&i_command_sub=5&i_his_id=${helenIndex}`, "other");
expect("lights off", dark, ["lights+are+off", "Try+Door", "music_sneak.swf"]);
if (teller.visits !== 1) {
  failed += 1;
  console.error(`FAIL visits ${teller.visits}`);
} else {
  console.log("ok visit counted");
}
const slipped = game.handle("/request.xp?command&i_command=102&i_command_sub=6", "other");
expect("inside the parlor", slipped, ["desk_enemy.swf", "snoring", "Steal", "Attack"]);
const grabbed = game.handle("/request.xp?command&i_command=102&i_command_sub=7", "other");
expect("stole and ran", grabbed, ["lingerie", "run+like+hell"]);
helen.padlock = 1;
helen.padlockStrength = 5;
teller.lockpicks = 1;
store.save();
const locked = game.handle(`/request.xp?command&i_command=102&i_command_sub=5&i_his_id=${helenIndex}`, "other");
expect("back for the lock", locked, ["lights+are+off"]);
const barred = game.handle("/request.xp?command&i_command=102&i_command_sub=6", "other");
expect("padlock", barred, ["heavy+padlock", "Pick+Lock"]);
const picked = game.handle("/request.xp?command&i_command=102&i_command_sub=9", "other");
expect("lockpick spent", picked, ["lockpick"]);
if (teller.lockpicks !== 0 || helen.padlock !== 0) {
  failed += 1;
  console.error(`FAIL lock pick locks=${teller.lockpicks} padlock=${helen.padlock}`);
} else {
  console.log("ok padlock broke");
}
teller.visits = 0;
helen.padlock = 0;
helen.hpMax = 1;
helen.exp = 100;
helen.buryPoints = 0;
store.save();
const asleep = game.handle(`/request.xp?command&i_command=102&i_command_sub=5&i_his_id=${helenIndex}`, "other");
expect("asleep again", asleep, ["Try+Door"]);
game.handle("/request.xp?command&i_command=102&i_command_sub=6", "other");
game.handle("/request.xp?command&i_command=102&i_command_sub=10", "other");
const fight = game.handle("/request.xp?command&i_command=102&i_command_sub=8", "other");
expect("battle starts", fight, ["in+battle", "music_fight.swf", "b_show_battle=1"]);
let beaten = fight;
for (let swing = 0; swing < 8 && !beaten.includes("won+the+battle") && !beaten.includes("unable+to+get+up"); swing += 1) {
  beaten = game.handle("/request.xp?command&i_command=103&i_command_sub=2", "other");
}
expect("battle ends", beaten, ["won+the+battle"]);
if (!helen.inHospital || !helen.police || helen.exp !== 99) {
  failed += 1;
  console.error(`FAIL battle result hospital=${helen.inHospital} police=${helen.police} exp=${helen.exp}`);
} else {
  console.log("ok battle sent her to the hospital");
}
const yelled = game.handle("/request.xp?command&i_command=103&i_command_sub=5&st_extra_text=12ABORT12", "other");
expect("left the scene", yelled, ["dense+fog"]);
const mine = game.handle(`/request.xp?command&i_command=102&i_command_sub=5&i_his_id=${store.state.players.findIndex((entry) => entry.id === teller.id)}`, "other");
expect("own parlor", mine, ["strangely+familiar"]);
teller.visits = 2;
store.save();
const tired = game.handle("/request.xp?command&i_command=102&i_command_sub=4", "other");
expect("visit limit", tired, ["everyone's+parlor"]);

const sheet = game.handle("/request.xp?command&i_command=3", "other");
expect("stats screen", sheet, ["i_sent=3", "Smoke+detector+installed.", "Level+2.00", "Budget+Hearse", "st_option_2=+++++Name", "i_command_3=4", "Coupons+are+helping"]);
const mirror = game.handle("/request.xp?command&i_command=43", "other");
expect("name change", mirror, ["question.swf", "stare+at+yourself", "gold+tag", "Yes"]);
const askName = game.handle("/request.xp?command&i_command=108&i_command_sub=2", "other");
expect("new name prompt", askName, ["i_get_text", "Enter+your+new+name", "i_sent=14"]);
const brokeName = game.handle("/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Bertie", "other");
expect("name costs gold", brokeName, ["enough+gold+tags"]);
teller.gold = 1;
store.save();
const sameName = game.handle("/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Albert", "other");
expect("same name", sameName, ["like+the+sound"]);
const renamed = game.handle("/request.xp?command&i_command=108&i_command_sub=1&st_extra_text=Bertie", "other");
expect("name changed", renamed, ["known+as", "Bertie", "i_coffin_gold=0.00"]);
if (teller.name !== "Bertie" || teller.gold !== 0) {
  failed += 1;
  console.error(`FAIL rename name=${teller.name} gold=${teller.gold}`);
} else {
  console.log("ok name saved");
}

const albertIndex = store.state.players.findIndex((entry) => entry.id === teller.id);
teller.gold = 0;
store.save();
const offer = game.runScript("other", "events/arson.c");
expect("twins offer", offer, ["m_old_3.swf", "play+with+fire", "i_command_2=109"]);
const poorTwins = game.handle("/request.xp?command&i_command=109&i_command_sub=1", "other");
expect("twins want gold", poorTwins, ["enough+gold+tags", "i_sent=33"]);
teller.gold = 6;
store.save();
game.runScript("other", "events/arson.c");
const hireTwins = game.handle("/request.xp?command&i_command=109&i_command_sub=1", "other");
expect("twins ask for a parlor", hireTwins, ["i_getname", "i_sent=14", "Twins+to+pay"]);
const ownParlor = game.handle(`/request.xp?command&i_command=109&i_his_id=${albertIndex}`, "other");
expect("twins refuse your parlor", ownParlor, ["own+building", "i_command_2=109"]);
if (teller.gold !== 6) {
  failed += 1;
  console.error(`FAIL twins charged early gold=${teller.gold}`);
} else {
  console.log("ok twins have not been paid yet");
}
store.logon("helenbox", helen.id);
game.runScript("other", "events/arson.c");
game.handle("/request.xp?command&i_command=109&i_command_sub=1", "other");
const yard = game.handle(`/request.xp?command&i_command=109&i_his_id=${helenIndex}`, "other");
expect("twins see the owner", yard, ["working+in+the+yard"]);
store.logoff("helenbox");
helen.wood = 7;
helen.plots = 40;
helen.smoke = 0;
helen.safe = 0;
game.runScript("other", "events/arson.c");
game.handle("/request.xp?command&i_command=109&i_command_sub=1", "other");
const burned = game.handle(`/request.xp?command&i_command=109&i_his_id=${helenIndex}`, "other");
expect("twins burn the parlor", burned, ["fire+engulfs", "Quietrest", "fire.swf", "i_coffin_gold=0.00"]);
if (helen.wood !== 0 || helen.plots !== 38 || teller.gold !== 0 || !helen.inbox.some((note) => note.text.includes("wood tag"))) {
  failed += 1;
  console.error(`FAIL fire result wood=${helen.wood} plots=${helen.plots} gold=${teller.gold} mail=${helen.inbox.length}`);
} else {
  console.log("ok fire took the graves, wood tags, and gold");
}

teller.buryPoints = 11;
teller.exp = 40;
teller.dayLastLoggedOn = store.state.day;
helen.buryPoints = 0;
helen.exp = 10;
store.state.date = "2000-01-01";
store.save();
game.maintain();
const sellerMail = (teller.inbox || []).some((note) => String(note.text).includes("top seller"));
const parlorMail = (teller.inbox || []).some((note) => String(note.text).includes("top parlor"));
if (!sellerMail || !parlorMail || teller.exp < 80 || store.state.date === "2000-01-01" || !String(store.state.topYesterday).includes("with 11 Bury Points")) {
  failed += 1;
  console.error(`FAIL midnight maint seller=${sellerMail} parlor=${parlorMail} exp=${teller.exp} date=${store.state.date} top=${store.state.topYesterday}`);
} else {
  console.log("ok midnight maint awards the seller before the new day");
}
let nextMorning = "";
for (let step = 0; step < 8 && !nextMorning.includes("eyes+grow+heavy"); step += 1) {
  nextMorning = game.handle("/request.xp?command&i_command=4", "other");
}
expect("new morning", nextMorning, ["eyes+grow+heavy", "morning_coffee.swf", "i_sp=0.00"]);
if (teller.buryPoints !== 0 || teller.exp < 80) {
  failed += 1;
  console.error(`FAIL new day points=${teller.buryPoints} exp=${teller.exp}`);
} else {
  console.log("ok a new day clears bury points and keeps the bonus experience");
}

const cafe = game.handle("/request.xp?command&i_command=23", "other");
expect("pale horse", cafe, ["i_chat", "Pale+Horse", "mochachino", "only+one+here", "i_chat_speed=2000", "i_sent=10"]);
const arrived = game.handle("/request.xp?command&i_command=24", "other");
expect("saunters in", arrived, [`${teller.name}+saunters+in`, "i_sent=24"]);
const said = game.handle("/request.xp?command&i_command=24&st_input=hello", "other");
expect("cafe speech", said, [`${teller.name}%3A`, "hello"]);
const swore = game.handle("/request.xp?command&i_command=24&st_input=fuck", "other");
expect("cafe filter", swore, ["fudge"]);
const cafeHelp = game.handle("/request.xp?command&i_command=24&st_input=/?", "other");
expect("cafe help", cafeHelp, ["Cafe+chat+help", "/give+Seth+gold"]);
store.logon("helenbox", helen.id);
const joined = game.handle("/request.xp?command&i_command=23", "helenbox");
expect("helen joins the cafe", joined, ["Other+people+here", teller.name]);
game.handle("/request.xp?command&i_command=24", "helenbox");
teller.gold = 2;
helen.gold = 0;
helen.tagsReceived = 0;
const gift = game.handle("/request.xp?command&i_command=24&st_input=/give+Helen+gold", "other");
expect("gives a gold tag", gift, ["gives", "Helen", "Gold+tag"]);
if (teller.gold !== 1 || helen.gold !== 1) {
  failed += 1;
  console.error(`FAIL gift gold=${teller.gold} helen=${helen.gold}`);
} else {
  console.log("ok gold tag changed hands");
}
const secondGift = game.handle("/request.xp?command&i_command=24&st_input=/give+Helen+gold", "other");
expect("one tag a day", secondGift, ["already+received"]);
if (teller.gold !== 1) {
  failed += 1;
  console.error(`FAIL second gift gold=${teller.gold}`);
} else {
  console.log("ok the second gold tag stayed put");
}
const leftCafe = game.handle("/request.xp?command&i_command=12", "other");
expect("left for the map", leftCafe, ["st_object=i_map"]);
const noticed = game.handle("/request.xp?command&i_command=24", "helenbox");
expect("steps outside", noticed, ["steps+outside"]);
const missingMail = game.handle("/request.xp?command&i_command=20&i_his_id=99&st_input=Hello", "other");
expect("mail to nobody", missingMail, ["i_message", "doesn't+exist", "i_sent=14"]);
store.logon("helenbox", helen.id);
helen.inbox = [];
game.handle("/request.xp?command&i_command=23", "helenbox");
const posted = game.handle(`/request.xp?command&i_command=20&i_his_id=${helenIndex}&st_input=Hello+there`, "other");
expect("letter sent", posted, ["i_sent=16"]);
const mailNotice = game.handle("/request.xp?command&i_command=24", "helenbox");
expect("mail while chatting", mailNotice, ["mail+waiting"]);
const letter = game.handle("/request.xp?command&i_command=4", "helenbox");
expect("letter on the desk", letter, ["received+a+letter+from", teller.name, "Hello+there", "st_quote=>Hello+there", "i_command_2=21", `i_his_id=${albertIndex}`, "i_sent=10"]);

const announced = game.handle("/request.xp?command&i_command=8&st_note=Big+sale+[example.com]", "other");
expect("announcement", announced, ["makes+an+announcement", "Big+sale", "example.com", "i_yesterday=0", "i_sent=5"]);
const crimePaper = game.handle("/request.xp?command&i_command=42", "other");
expect("crime page", crimePaper, ["no+crime+reported", "i_sent=5"]);
const prior = game.handle("/request.xp?command&i_command=7", "other");
expect("yesterday's paper", prior, ["i_yesterday=1", `st_date=Day+${store.state.day - 1}`]);

const parish = game.handle("/request.xp?command&i_command=44&st_extra_text=church.c", "other");
expect("real church", parish, ["churchguy_h.swf", "background_church.swf", "too+warmly", "Praise+the+Lord", "i_command_3=12", "st_object=i_generic"]);

const apiSaved = JSON.parse(fs.readFileSync(path.join(dir, "api.json"), "utf8"));
if (JSON.stringify(apiSaved).includes('"password":')) {
  failed += 1;
  console.error("FAIL api store contains a password field");
} else {
  console.log("ok api store has no plaintext password");
}

server.close();

if (failed) {
  console.error(`${failed} failed`);
  process.exit(1);
}
console.log("protocol self-test passed");
