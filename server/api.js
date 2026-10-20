import { randomBytes } from "node:crypto";
import { publicPlayer, screenFromBody } from "./view.js";

const attempts = new Map();

function attemptKey(ip, logon) {
  return `${ip}|${String(logon ?? "").toLowerCase()}`;
}

function limited(key) {
  const row = attempts.get(key);
  return Boolean(row && row.count >= 8 && row.reset > Date.now());
}

function recordFailure(key) {
  const row = attempts.get(key);
  if (!row || row.reset <= Date.now()) {
    attempts.set(key, { count: 1, reset: Date.now() + 10 * 60 * 1000 });
    return;
  }
  row.count += 1;
}

function clearFailures(key) {
  attempts.delete(key);
}

function gameUrl(command, tokens) {
  const query = Object.entries(tokens)
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value ?? "")}`)
    .join("&");
  return `/request.xp?${command}${query ? `&${query}` : ""}`;
}

export function newSessionId() {
  return randomBytes(32).toString("hex");
}

export function createApi(store, game) {
  function payload(sessionId, body, error) {
    const player = store.player(sessionId);
    return {
      player: publicPlayer(player),
      screen: body ? screenFromBody(body) : { kind: player ? "desk" : "login", story: "", art: "", actions: [] },
      ...(error ? { error } : {}),
    };
  }

  return {
    remember(sessionId, body) {
      const session = store.session(sessionId);
      session.lastBody = body;
      store.save();
      return payload(sessionId, body);
    },
    state(sessionId) {
      const player = sessionId ? store.peek(sessionId) : null;
      if (!player) return { player: null, screen: { kind: "login", story: "", art: "", actions: [] } };
      const saved = store.session(sessionId).lastBody;
      if (saved) return payload(sessionId, saved);
      return this.remember(sessionId, game.handle(gameUrl("command", { i_command: 4 }), sessionId));
    },
    register(sessionId, fields) {
      const password = String(fields.password ?? "");
      if (password.length < 8 || password.length > 20) {
        return { status: 400, body: { player: null, error: "Use a password of 8 to 20 characters.", screen: { kind: "login" } } };
      }
      const body = game.handle(gameUrl("new", {
        name: fields.name,
        logon: fields.logon,
        f_name: fields.funeralName,
        password,
        password_v: password,
        email: fields.email,
        b_male: fields.male ? "1" : "0",
      }), sessionId);
      const screen = screenFromBody(body);
      if (screen.kind === "error") return { status: 400, body: { player: null, error: screen.story, screen } };
      return { status: 200, body: this.remember(sessionId, body) };
    },
    login(sessionId, fields, ip) {
      const key = attemptKey(ip, fields.logon);
      if (limited(key)) {
        return { status: 429, body: { player: null, error: "Too many logon attempts. Wait a few minutes.", screen: { kind: "login" } } };
      }
      const body = game.handle(gameUrl("logon", {
        st_logon: fields.logon,
        st_password: fields.password,
      }), sessionId);
      if (!store.player(sessionId)) {
        recordFailure(key);
        return { status: 401, body: { player: null, error: "Unknown logon or password.", screen: { kind: "login" } } };
      }
      clearFailures(key);
      return { status: 200, body: this.remember(sessionId, body) };
    },
    action(sessionId, fields) {
      if (!sessionId || !store.peek(sessionId)) {
        return { status: 401, body: { player: null, error: "Log on first.", screen: { kind: "login" } } };
      }
      const number = Number(fields.command);
      if (!Number.isInteger(number) || number < 1 || number > 200) {
        return { status: 400, body: payload(sessionId, null, "That action is not valid.") };
      }
      const tokens = { i_command: number };
      if (fields.sub !== undefined && fields.sub !== null && fields.sub !== "") {
        const sub = Number(fields.sub);
        if (!Number.isInteger(sub) || sub < 0 || sub > 200) {
          return { status: 400, body: payload(sessionId, null, "That action is not valid.") };
        }
        tokens.i_command_sub = sub;
      }
      if (fields.text !== undefined && fields.text !== null) {
        tokens.st_extra_text = String(fields.text).slice(0, 160);
      }
      const body = game.handle(gameUrl("command", tokens), sessionId);
      return { status: 200, body: this.remember(sessionId, body) };
    },
    logout(sessionId) {
      if (sessionId) store.logoff(sessionId);
      return { player: null, screen: { kind: "login", story: "", art: "", actions: [] } };
    },
  };
}
