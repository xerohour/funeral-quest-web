// A small runner for the Funeral Quest .c scripts.
// Portions Copyright (C) Seth A. Robinson, 2003.

const HOSTS = new Set([
  "ADDCUSTOM",
  "SETLOCATION",
  "SENDPACKETANDWAIT",
  "SENDPACKETANDWAITOVERLAY",
  "SENDPACKETANDWAITSTATS",
  "ADDBUTTON",
  "ADDBUTTONTOWNMAP",
  "ADDBUTTONHOME",
  "ADDBUTTONSTORE",
  "ADDBUTTONCHANGENAME",
  "ADDUPDATETAGS",
  "ADDUPDATESTATS",
  "ADDUPDATEBPS",
  "ADDUPDATELUCK",
  "ADDUPDATETURNS",
  "RUNSCRIPTNORETURN",
  "RANDOMRANGE",
  "RANDOM",
  "LOGMSG",
  "LOGERROR",
  "STRINGEXPAND",
  "GETTAGS",
  "MODTAGS",
  "SETTAGS",
  "ISPLAYERONNOW",
  "GETNAMEFROMID",
  "GETHOMENAMEFROMID",
  "GETCUSTOMBYID",
  "MODCUSTOMBYID",
  "SETCUSTOMBYID",
  "GETTOTALBURIALS",
  "MODTOTALBURIALS",
  "ADDTONEWS",
  "BROADCASTTV",
  "MAILTEXT",
  "MAILPICTURE",
  "MAILCONTINUEBUTTON",
]);

function up(name) {
  return String(name || "").toUpperCase();
}

function defaultFor(type) {
  if (type === "string") return "";
  return 0;
}

function formatVar(variable) {
  if (!variable) return "";
  if (variable.type === "string") return String(variable.value ?? "");
  if (variable.type === "float") return Number(variable.value).toFixed(6);
  return String(Math.trunc(Number(variable.value) || 0));
}

function expand(text, lookup) {
  let out = "";
  const source = String(text ?? "");
  for (let i = 0; i < source.length; i++) {
    const pair = source.slice(i, i + 2);
    if (pair === "\\\\") {
      out += "\\";
      i += 1;
      continue;
    }
    if (pair === "\\n") {
      out += "\n";
      i += 1;
      continue;
    }
    if (pair === '\\"') {
      out += '"';
      i += 1;
      continue;
    }
    if (source[i] === "$") {
      if (source[i + 1] === "$") {
        out += "$";
        i += 1;
        continue;
      }
      const end = source.indexOf("$", i + 1);
      if (end === -1) {
        out += "$";
        continue;
      }
      const variable = lookup(source.slice(i + 1, end));
      if (variable) out += formatVar(variable);
      i = end;
      continue;
    }
    out += source[i];
  }
  return out;
}

function stripComment(line) {
  let out = "";
  let inString = false;
  for (let i = 0; i < line.length; i++) {
    const pair = line.slice(i, i + 2);
    if (!inString && pair === "//") break;
    if (line[i] === "\\" && inString) {
      out += pair;
      i += 1;
      continue;
    }
    if (line[i] === '"') inString = !inString;
    out += line[i];
  }
  return out.trim();
}

function findOperator(line) {
  let inString = false;
  for (let i = 0; i < line.length; i++) {
    if (line[i] === "\\" && inString) {
      i += 1;
      continue;
    }
    if (line[i] === '"') {
      inString = !inString;
      continue;
    }
    if (inString) continue;
    const two = line.slice(i, i + 2);
    if (two === "+=" || two === "-=" || two === "*=" || two === "/=" || two === "%=") return { op: two, index: i };
    if (line[i] === "=" && line[i + 1] !== "=") return { op: "=", index: i };
  }
  return null;
}

function parseAtom(token) {
  const text = token.trim();
  if (text.startsWith('"')) {
    let raw = "";
    for (let i = 1; i < text.length; i++) {
      if (text[i] === "\\") {
        raw += text[i] + (text[i + 1] || "");
        i += 1;
        continue;
      }
      if (text[i] === '"') break;
      raw += text[i];
    }
    return { kind: "str", value: raw };
  }
  if (/^-?\d/.test(text)) {
    if (text.includes(".")) return { kind: "num", type: "float", value: Number(text) };
    return { kind: "num", type: "int", value: Number.parseInt(text, 10) || 0 };
  }
  const name = text.match(/^[A-Za-z_][A-Za-z0-9_]*/);
  if (!name) return { kind: "num", type: "int", value: 0 };
  return { kind: "var", name: name[0] };
}

function splitArgs(body) {
  const args = [];
  let current = "";
  let inString = false;
  for (let i = 0; i < body.length; i++) {
    const pair = body.slice(i, i + 2);
    if (inString && (pair === "\\\\" || pair === "\\n" || pair === '\\"')) {
      current += pair;
      i += 1;
      continue;
    }
    if (body[i] === '"') {
      inString = !inString;
      current += body[i];
      continue;
    }
    if (!inString && (body[i] === "," || body[i] === ")" || body[i] === ";")) {
      if (current.trim()) args.push(parseAtom(current));
      current = "";
      if (body[i] !== ",") break;
      continue;
    }
    current += body[i];
  }
  if (inString) return null;
  if (current.trim()) args.push(parseAtom(current));
  return args;
}

function parseCall(line) {
  const open = line.indexOf("(");
  if (open === -1) return null;
  const name = line.slice(0, open).trim();
  if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(name)) return null;
  const args = splitArgs(line.slice(open + 1));
  if (!args) return null;
  return { name, args };
}

function parseIf(line) {
  const open = line.indexOf("(");
  const close = line.lastIndexOf(")");
  if (open === -1 || close === -1) return null;
  const body = line.slice(open + 1, close).trim();
  const match = body.match(/^(.*?)\s*(==|!=|>=|<=|>|<)\s*(.*)$/);
  if (!match) return null;
  return { left: parseAtom(match[1]), op: match[2], right: parseAtom(match[3]) };
}

function compile(source, locals, globals, filename) {
  const functions = new Map();
  let current = null;
  let nest = -1;
  const lines = String(source).split(/\r?\n/);
  lines.forEach((raw, index) => {
    const line = stripComment(raw);
    const where = `${filename}:${index + 1}`;
    if (!line) return;
    if (line.startsWith("/")) return;
    if (!current) {
      const header = /^void\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i.exec(line);
      if (!header) return;
      current = { name: header[1], key: up(header[1]), ops: [], labels: new Map() };
      functions.set(current.key, current);
      nest = -1;
      return;
    }
    if (nest === -1) {
      if (line.startsWith("{")) {
        nest = 0;
        return;
      }
      current = null;
      return;
    }
    if (line.startsWith("}")) {
      nest -= 1;
      if (nest < 0) current = null;
      return;
    }
    if (line.startsWith("{")) {
      nest += 1;
      return;
    }
    if (/^goto\s+/i.test(line)) {
      const label = line.replace(/^goto\s+/i, "").replace(/;$/, "").trim();
      current.ops.push({ kind: "goto", nest, label });
      return;
    }
    if (/^return\b/i.test(line)) {
      current.ops.push({ kind: "return", nest });
      return;
    }
    if (/^if\b/i.test(line)) {
      const test = parseIf(line);
      if (test) current.ops.push({ kind: "if", nest, ...test });
      return;
    }
    if (line.includes(":") && !line.includes('"')) {
      const label = line.slice(0, line.indexOf(":")).trim();
      if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(label)) {
        current.labels.set(label, current.ops.length);
        current.ops.push({ kind: "label", nest, label });
        return;
      }
    }
    let global = false;
    let rest = line;
    if (/^global\s+/i.test(rest)) {
      global = true;
      rest = rest.replace(/^global\s+/i, "");
    }
    const declared = /^(int|float|string)\s+/i.exec(rest);
    if (declared) {
      rest = rest.slice(declared[0].length).trim();
      const name = rest.match(/^[A-Za-z_][A-Za-z0-9_]*/);
      if (!name) return;
      const type = declared[1].toLowerCase() === "string" ? "string" : declared[1].toLowerCase();
      const bucket = global ? globals : locals;
      if (!bucket.has(name[0])) bucket.set(name[0], { type, value: defaultFor(type) });
      const after = rest.slice(name[0].length).trim().replace(/;$/, "").trim();
      if (after.startsWith("=")) {
        const expr = compileExpr(after.slice(1).trim(), where);
        if (expr) current.ops.push({ kind: "set", nest, name: name[0], op: "=", expr, global });
      }
      return;
    }
    const operator = findOperator(line);
    if (operator) {
      const name = line.slice(0, operator.index).trim();
      const expr = compileExpr(line.slice(operator.index + operator.op.length).replace(/;$/, "").trim(), where);
      if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(name) && expr) {
        current.ops.push({ kind: "set", nest, name, op: operator.op, expr, global: false });
      }
      return;
    }
    const call = parseCall(line.replace(/;$/, "").trim());
    if (!call) {
      console.error(`Funeral Quest script skipped ${where}: ${line}`);
      return;
    }
    current.ops.push({ kind: "call", nest, name: call.name, args: call.args });
  });
  return functions;
}

function compileExpr(text, where) {
  const call = parseCall(text.replace(/;$/, "").trim());
  if (call) return { kind: "call", name: call.name, args: call.args };
  if (!text) {
    console.error(`Funeral Quest script skipped an empty expression at ${where}`);
    return null;
  }
  return parseAtom(text.replace(/;$/, "").trim());
}

function numeric(variable) {
  return Number(variable?.value) || 0;
}

function applyMath(left, op, right) {
  if (left.type === "string" || right.type === "string") {
    if (op === "+=") left.value = String(left.value ?? "") + (right.type === "string" ? right.value : formatVar(right));
    return left;
  }
  const a = numeric(left);
  const b = numeric(right);
  let next = a;
  if (op === "=") next = b;
  if (op === "+=") next = a + b;
  if (op === "-=") next = a - b;
  if (op === "*=") next = a * b;
  if (op === "/=") next = b === 0 ? 0 : a / b;
  if (op === "%=") next = b === 0 ? 0 : a % b;
  if (left.type === "int") left.value = Math.trunc(next);
  else left.value = next;
  return left;
}

function compare(left, op, right) {
  if (left?.type === "string" || right?.type === "string") {
    const a = String(left?.value ?? "");
    const b = String(right?.value ?? "");
    if (op === "==") return a === b;
    if (op === "!=") return a !== b;
    if (op === "<") return a.length < b.length;
    if (op === ">") return a.length > b.length;
    if (op === "<=") return a.length <= b.length;
    if (op === ">=") return a.length >= b.length;
    return false;
  }
  const a = numeric(left);
  const b = numeric(right);
  if (op === "==") return a === b;
  if (op === "!=") return a !== b;
  if (op === "<") return a < b;
  if (op === ">") return a > b;
  if (op === "<=") return a <= b;
  if (op === ">=") return a >= b;
  return false;
}

export function createScriptRuntime(env) {
  const globals = env.globals;
  let locals = new Map();
  let functions = new Map();
  let wait = null;
  let packet = null;
  let replaced = null;

  function lookup(name) {
    return locals.get(name) || globals.get(name) || null;
  }

  function ensure(name) {
    return locals.get(name) || globals.get(name);
  }

  function readAtom(atom, expanding) {
    if (!atom) return { type: "int", value: 0 };
    if (atom.kind === "num") return { type: atom.type, value: atom.value };
    if (atom.kind === "str") {
      const value = expanding ? expand(atom.value, lookup) : atom.value;
      return { type: "string", value };
    }
    if (atom.kind === "var") return lookup(atom.name) || { type: "int", value: 0 };
    if (atom.kind === "call") return callValue(atom.name, atom.args);
    return { type: "int", value: 0 };
  }

  function callValue(name, args) {
    const key = up(name);
    if (functions.has(key)) {
      const status = callLocal(functions.get(key), args);
      return status === "wait" ? { type: "wait" } : { type: "int", value: 0 };
    }
    const expanded = args.map((atom) => {
      const value = readAtom(atom, false);
      if (value.type === "string") return { type: "string", value: expand(value.value, lookup) };
      return value;
    });
    const result = env.call(key, expanded, api);
    if (result?.wait) {
      packet = result.packet;
      return { type: "wait" };
    }
    return result || { type: "int", value: 0 };
  }

  function callLocal(fn, args) {
    fn.savedParent = fn.savedParent || null;
    return run(fn, 0);
  }

  function run(fn, startIp) {
    let nestLock = -1;
    let i = startIp;
    while (i < fn.ops.length) {
      const op = fn.ops[i];
      if (nestLock !== -1) {
        if (nestLock < op.nest) {
          i += 1;
          continue;
        }
        nestLock = -1;
      }
      if (op.kind === "label") {
        i += 1;
        continue;
      }
      if (op.kind === "goto") {
        const target = fn.labels.get(op.label);
        nestLock = -1;
        if (target == null) {
          i += 1;
          continue;
        }
        i = target;
        continue;
      }
      if (op.kind === "return") break;
      if (op.kind === "if") {
        if (!compare(readAtom(op.left, false), op.op, readAtom(op.right, false))) nestLock = op.nest;
        i += 1;
        continue;
      }
      if (op.kind === "set") {
        const expr = op.expr.kind === "call" ? callValue(op.expr.name, op.expr.args) : readAtom(op.expr, false);
        if (expr?.type === "wait") {
          if (!wait) wait = { key: fn.key, ip: i + 1 };
          return "wait";
        }
        const dest = ensure(op.name);
        if (dest) {
          if (op.op === "=") {
            if (dest.type === "int") dest.value = Math.trunc(numeric(expr));
            else if (dest.type === "float") dest.value = numeric(expr);
            else dest.value = expr.type === "string" ? String(expr.value ?? "") : formatVar(expr);
          } else {
            applyMath(dest, op.op, expr.type === dest.type ? expr : { type: dest.type === "string" ? "string" : "float", value: expr.value });
          }
        }
        i += 1;
        continue;
      }
      if (op.kind === "call") {
        const key = up(op.name);
        if (functions.has(key)) {
          const callee = functions.get(key);
          callee.savedParent = { key: fn.key, ip: i + 1 };
          const status = run(callee, 0);
          if (status === "wait") return "wait";
          i += 1;
          continue;
        }
        const result = callValue(op.name, op.args);
        if (result?.type === "wait") {
          if (!wait) wait = { key: fn.key, ip: i + 1 };
          return "wait";
        }
        i += 1;
        continue;
      }
      i += 1;
    }
    if (startIp !== 0 && fn.savedParent) {
      const parent = fn.savedParent;
      fn.savedParent = null;
      const parentFn = functions.get(parent.key);
      if (parentFn) return run(parentFn, parent.ip);
    }
    return "ok";
  }

  function packetOf() {
    if (replaced) return replaced.packetOf();
    return packet;
  }

  const api = {
    lookup,
    spawn(relativePath) {
      replaced = env.spawn(relativePath);
      return replaced;
    },
    packetOf,
  };

  return {
    waiting: () => Boolean(wait) || Boolean(replaced?.waiting()),
    packetOf,
    start(source, filename, preset) {
      locals = new Map();
      for (const [name, variable] of preset) locals.set(name, { ...variable });
      functions = compile(source, locals, globals, filename);
      wait = null;
      packet = null;
      replaced = null;
      const create = functions.get("ONCREATE");
      if (create) run(create, 0);
      const main = functions.get("MAIN");
      if (main && !wait) run(main, 0);
      return packetOf();
    },
    resume(result) {
      if (replaced) return replaced.resume(result);
      if (!wait) return packetOf();
      const next = wait;
      wait = null;
      packet = null;
      const variable = locals.get("i_my_result");
      if (variable) variable.value = Math.trunc(Number(result) || 0);
      const fn = functions.get(next.key);
      if (fn) run(fn, next.ip);
      return packetOf();
    },
  };
}

export function loadGlobals(source) {
  const globals = new Map([
    ["true", { type: "int", value: 1 }],
    ["false", { type: "int", value: 0 }],
  ]);
  const locals = new Map();
  const functions = compile(source, locals, globals, "init.c");
  const runtime = {
    globals,
    locals,
    functions,
  };
  const main = functions.get("MAIN");
  if (!main) return globals;
  const env = {
    globals,
    call(name, args) {
      if (name === "LOGMSG" || name === "LOGERROR") return { type: "int", value: 0 };
      return { type: "int", value: 0 };
    },
    spawn() {
      return null;
    },
  };
  const runner = createScriptRuntime(env);
  runner.start(source, "init.c", []);
  return globals;
}

export { HOSTS, expand };
