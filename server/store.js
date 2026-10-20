import fs from "node:fs";
import path from "node:path";
import { hashPassword, verifyPassword } from "./passwords.js";

const START = {
  money: 500,
  turns: 15,
  exp: 0,
  buryPoints: 0,
  luck: 50,
  wood: 5,
  silver: 2,
  gold: 0,
  plots: 0,
  transport: 0,
  lockpicks: 0,
  smoke: 0,
  padlock: 0,
  padlockStrength: 0,
  safe: 0,
  psych: 0,
  hp: 10,
  hpMax: 10,
  strength: 3,
  yangsSent: 0,
  kickedOut: false,
  visits: 0,
  police: false,
  deliveries: [],
  inbox: [],
};

export function createStore(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const state = fs.existsSync(file)
    ? JSON.parse(fs.readFileSync(file, "utf8"))
    : { day: 1, nextId: 1, players: [], sessions: {} };
  let migrated = false;
  for (const player of state.players) {
    if (player.password && !player.passwordHash) {
      player.passwordHash = hashPassword(player.password);
      delete player.password;
      migrated = true;
    }
  }
  if (migrated) fs.writeFileSync(file, JSON.stringify(state, null, 2));

  function save() {
    fs.writeFileSync(file, JSON.stringify(state, null, 2));
  }

  function session(id) {
    if (!state.sessions[id]) {
      state.sessions[id] = { playerId: null, gaveLogonMessage: false, customer: null };
      save();
    }
    return state.sessions[id];
  }

  function playerById(id) {
    return state.players.find((player) => player.id === id) ?? null;
  }

  function player(sessionId) {
    const current = session(sessionId);
    return current.playerId == null ? null : playerById(current.playerId);
  }

  function peek(sessionId) {
    const current = state.sessions[sessionId];
    if (!current || current.playerId == null) return null;
    return playerById(current.playerId);
  }

  return {
    state,
    save,
    session,
    player,
    peek,
    findLogon(logon) {
      return state.players.find((entry) => entry.logon.toLowerCase() === logon.toLowerCase()) ?? null;
    },
    findName(name) {
      return state.players.find((entry) => entry.name.toLowerCase() === name.toLowerCase()) ?? null;
    },
    createPlayer(fields) {
      const created = {
        id: state.nextId++,
        name: fields.name,
        logon: fields.logon,
        funeralName: fields.funeralName,
        email: fields.email,
        male: Boolean(fields.male),
        passwordHash: hashPassword(fields.password),
        ...START,
        dayLastLoggedOn: 0,
        active: true,
      };
      created.deliveries = [];
      created.inbox = [];
      state.players.push(created);
      save();
      return created;
    },
    logon(sessionId, playerId) {
      const current = session(sessionId);
      current.playerId = playerId;
      current.gaveLogonMessage = false;
      current.customer = null;
      save();
    },
    checkPassword(player, password) {
      if (!player) {
        verifyPassword(null, password);
        return false;
      }
      if (player.passwordHash) return verifyPassword(player.passwordHash, password);
      return false;
    },
    logoff(sessionId) {
      const current = session(sessionId);
      current.playerId = null;
      current.gaveLogonMessage = false;
      current.customer = null;
      current.prowling = false;
      current.battle = false;
      current.targetIndex = null;
      save();
    },
  };
}
