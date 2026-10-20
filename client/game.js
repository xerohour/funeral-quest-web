import { PARLOR_ACTIONS } from "/client/actions.js";

const auth = document.querySelector("#auth");
const game = document.querySelector("#game");
const authError = document.querySelector("#auth-error");
const loginForm = document.querySelector("#login-form");
const registerForm = document.querySelector("#register-form");
const story = document.querySelector("#story");
const actions = document.querySelector("#actions");
const stats = document.querySelector("#stats");
const art = document.querySelector("#art");

let rufflePlayer = null;

function showAuth(message = "") {
  auth.hidden = false;
  game.hidden = true;
  authError.textContent = message;
}

function money(value) {
  return Number(value).toLocaleString("en-US", { style: "currency", currency: "USD" });
}

function renderStats(player) {
  const rows = [
    ["Parlor", player.funeralName],
    ["Proprietor", player.name],
    ["Cash", money(player.money)],
    ["Turns", String(player.turns)],
    ["Bury points", String(player.buryPoints)],
    ["Luck", String(player.luck)],
    ["Caskets", `${player.wood} wood, ${player.silver} silver, ${player.gold} gold`],
    ["Ride", player.transportName || "Bicycle"],
    ["HP", String(player.hp ?? 10)],
    ["Strength", String(player.strength ?? 3)],
    ["Psychology", String(player.psych ?? 0)],
  ];
  stats.replaceChildren(...rows.map(([label, value]) => {
    const row = document.createElement("div");
    row.className = "stat";
    const name = document.createElement("b");
    name.textContent = label;
    row.append(name, document.createTextNode(value));
    return row;
  }));
}

function renderArt(path) {
  art.replaceChildren();
  rufflePlayer = null;
  if (!path) return;
  if (!path.endsWith(".swf") || !window.RufflePlayer) {
    const note = document.createElement("p");
    note.textContent = path;
    art.append(note);
    return;
  }
  window.RufflePlayer.config = {
    ...(window.RufflePlayer.config || {}),
    autoplay: "on",
    unmuteOverlay: "hidden",
    splashScreen: false,
    letterbox: "on",
    contextMenu: "off",
    warnOnUnsupportedContent: false,
    allowNetworking: "internal",
  };
  const player = window.RufflePlayer.newest().createPlayer();
  player.style.width = "100%";
  player.style.height = "320px";
  rufflePlayer = player;
  art.append(player);
  player.load({
    url: `/${path.replace(/^\/+/, "")}`,
    autoplay: "on",
    unmuteOverlay: "hidden",
    splashScreen: false,
    letterbox: "on",
    allowScriptAccess: false,
    allowNetworking: "internal",
  });
}

function renderActions(screen) {
  if (screen.kind === "prompt") {
    const naming = screen.promptKind === "i_getname";
    const numeric = screen.returnCommand === 104;
    const newspaper = screen.returnCommand === 100 && screen.returnSub === 7;
    const form = document.createElement("form");
    form.className = "prompt";
    const input = document.createElement("input");
    input.name = "entry";
    input.inputMode = numeric ? "numeric" : "text";
    input.required = numeric;
    input.maxLength = numeric ? 6 : naming ? 20 : 120;
    input.setAttribute("aria-label", naming ? "Who is this for?" : numeric ? "How many tags" : newspaper ? "Which day" : "Fortune");
    const button = document.createElement("button");
    button.type = "submit";
    button.textContent = naming ? "Choose" : numeric ? "Exchange" : newspaper ? "Look" : "Send";
    form.append(input, button);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      sendAction({ command: screen.returnCommand, sub: screen.returnSub }, input.value);
    });
    const back = document.createElement("button");
    back.type = "button";
    back.textContent = "Back";
    back.addEventListener("click", () => {
      if (numeric) sendAction({ command: screen.returnCommand, sub: 1 });
      else sendAction({ command: screen.returnCommand, sub: screen.returnSub }, "12ABORT12");
    });
    actions.replaceChildren(form, back);
    return;
  }
  const list = screen.actions?.length ? screen.actions : (screen.kind === "news" ? [{ command: 4, label: "Go to the desk" }] : PARLOR_ACTIONS);
  actions.replaceChildren(...list.map((action) => {
    const button = document.createElement("button");
    button.type = "button";
    button.textContent = action.label;
    button.addEventListener("click", () => sendAction(action));
    return button;
  }));
}

function render(payload) {
  if (!payload.player) {
    showAuth(payload.error || "");
    return;
  }
  auth.hidden = true;
  game.hidden = false;
  authError.textContent = "";
  renderStats(payload.player);
  const screen = payload.screen || {};
  const lines = [screen.date, screen.topToday, screen.story].filter(Boolean);
  story.textContent = lines.join("\n\n");
  renderArt(screen.art);
  renderActions(screen);
}

async function api(path, body) {
  const response = await fetch(path, {
    method: body ? "POST" : "GET",
    headers: body ? { "Content-Type": "application/json" } : undefined,
    body: body ? JSON.stringify(body) : undefined,
    credentials: "same-origin",
  });
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || "The server refused that request.");
  return payload;
}

async function sendAction(action, text) {
  const command = typeof action === "number" ? action : action.command;
  const body = { command };
  if (action && action.sub != null) body.sub = action.sub;
  if (text != null) body.text = text;
  try {
    render(await api("/api/action", body));
  } catch (error) {
    story.textContent = error.message;
  }
}

document.querySelector("#show-login").addEventListener("click", () => {
  loginForm.hidden = false;
  registerForm.hidden = true;
  document.querySelector("#show-login").classList.add("selected");
  document.querySelector("#show-register").classList.remove("selected");
});

document.querySelector("#show-register").addEventListener("click", () => {
  loginForm.hidden = true;
  registerForm.hidden = false;
  document.querySelector("#show-register").classList.add("selected");
  document.querySelector("#show-login").classList.remove("selected");
});

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(loginForm));
  try {
    render(await api("/api/login", data));
  } catch (error) {
    showAuth(error.message);
  }
});

registerForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(registerForm));
  data.male = registerForm.elements.male.checked;
  try {
    render(await api("/api/register", data));
  } catch (error) {
    showAuth(error.message);
  }
});


api("/api/state").then(render).catch((error) => showAuth(error.message));
