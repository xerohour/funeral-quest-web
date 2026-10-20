import http from "node:http";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import { createStore } from "./store.js";
import { createGame } from "./game.js";
import { createApi, newSessionId } from "./api.js";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const publicRoot = path.join(root, "upstream", "dist", "public");
const clientRoot = path.join(root, "client");
const ruffleRoot = path.join(root, "node_modules", "@ruffle-rs", "ruffle");

const types = {
  ".html": "text/html; charset=utf-8",
  ".htm": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".wasm": "application/wasm",
  ".swf": "application/x-shockwave-flash",
  ".gif": "image/gif",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".png": "image/png",
  ".txt": "text/plain; charset=utf-8",
  ".ico": "image/x-icon",
};

function contentType(file) {
  return types[path.extname(file).toLowerCase()] || "application/octet-stream";
}

function contained(rootDir, candidate) {
  const file = path.resolve(rootDir, candidate);
  const relative = path.relative(rootDir, file);
  if (relative.startsWith("..") || path.isAbsolute(relative)) return null;
  return file;
}

function readCookie(request, name) {
  const match = (request.headers.cookie || "").match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : "";
}

function sessionCookie(id, request) {
  const secure = request.headers["x-forwarded-proto"] === "https";
  return [`fq_session=${encodeURIComponent(id)}`, "HttpOnly", "SameSite=Lax", "Path=/", "Max-Age=1209600", secure ? "Secure" : ""].filter(Boolean).join("; ");
}

function originAllowed(request) {
  const origin = request.headers.origin;
  if (!origin) return true;
  try {
    return new URL(origin).host === request.headers.host;
  } catch {
    return false;
  }
}

function redact(url) {
  return url.replace(/((?:password|_password)(?:_v)?=)[^&]*/gi, "$1***");
}

async function readJson(request) {
  const chunks = [];
  let size = 0;
  for await (const chunk of request) {
    size += chunk.length;
    if (size > 8000) throw new Error("Request is too large.");
    chunks.push(chunk);
  }
  if (!chunks.length) return {};
  return JSON.parse(Buffer.concat(chunks).toString("utf8"));
}

export function createApp(storeFile = path.join(root, "data", "players.json")) {
  const store = createStore(storeFile);
  const game = createGame(store, publicRoot);
  const api = createApi(store, game);

  const server = http.createServer(async (request, response) => {
    let url = request.url || "/";
    if (url.includes("/flash/flash/")) url = url.replace("/flash/flash/", "/flash/");
    const pathname = url.split("?")[0];
    response.setHeader("X-Content-Type-Options", "nosniff");
    response.setHeader("Referrer-Policy", "no-referrer");
    const classic = pathname === "/" || pathname === "/index.html" || pathname === "/classic" || pathname === "/home.html";
    response.setHeader("Content-Security-Policy", classic
      ? "default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'"
      : "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; img-src 'self' data:; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

    if (pathname.startsWith("/api/")) {
      if (request.method !== "GET" && !originAllowed(request)) {
        response.writeHead(403, { "Content-Type": "application/json" });
        response.end(JSON.stringify({ error: "Cross-site request blocked." }));
        return;
      }
      let sessionId = readCookie(request, "fq_session");
      const issueSession = () => {
        if (sessionId) return sessionId;
        sessionId = newSessionId();
        response.setHeader("Set-Cookie", sessionCookie(sessionId, request));
        return sessionId;
      };
      try {
        if (request.method === "GET" && pathname === "/api/state") {
          const body = api.state(sessionId);
          response.writeHead(200, { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" });
          response.end(JSON.stringify(body));
          return;
        }
        if (request.method !== "POST") {
          response.writeHead(405, { "Content-Type": "application/json" });
          response.end(JSON.stringify({ error: "Use POST for that." }));
          return;
        }
        const fields = await readJson(request);
        let result;
        if (pathname === "/api/register") result = api.register(issueSession(), fields);
        else if (pathname === "/api/login") result = api.login(issueSession(), fields, request.socket.remoteAddress || "");
        else if (pathname === "/api/action") result = api.action(sessionId, fields);
        else if (pathname === "/api/logout") {
          const body = api.logout(sessionId);
          response.setHeader("Set-Cookie", "fq_session=; HttpOnly; SameSite=Lax; Path=/; Max-Age=0");
          response.writeHead(200, { "Content-Type": "application/json; charset=utf-8" });
          response.end(JSON.stringify(body));
          return;
        } else {
          response.writeHead(404, { "Content-Type": "application/json" });
          response.end(JSON.stringify({ error: "Unknown API." }));
          return;
        }
        response.writeHead(result.status, { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" });
        response.end(JSON.stringify(result.body));
      } catch {
        response.writeHead(400, { "Content-Type": "application/json" });
        response.end(JSON.stringify({ error: "The request could not be read." }));
      }
      return;
    }

    if (url.includes("request.xp?")) {
      let id = readCookie(request, "fq");
      if (!id) {
        id = `flash-${newSessionId()}`;
        response.setHeader("Set-Cookie", `fq=${encodeURIComponent(id)}; HttpOnly; SameSite=Lax; Path=/`);
      }
      const body = game.handle(url, id);
      console.log(`${request.method} ${redact(url).split("&").slice(0, 2).join("&")}`);
      response.writeHead(200, { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" });
      response.end(body);
      return;
    }

    if (pathname === "/" || pathname === "/index.html") {
      const file = path.join(clientRoot, "index.html");
      response.writeHead(200, { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-cache" });
      fs.createReadStream(file).pipe(response);
      return;
    }
    if (pathname === "/classic" || pathname === "/home.html") {
      const file = path.join(clientRoot, "index.html");
      response.writeHead(200, { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-cache" });
      fs.createReadStream(file).pipe(response);
      return;
    }
    if (pathname.startsWith("/client/")) {
      const file = contained(clientRoot, pathname.slice("/client/".length));
      if (!file || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        response.writeHead(404);
        response.end("Not found");
        return;
      }
      response.writeHead(200, { "Content-Type": contentType(file), "Cache-Control": "no-cache" });
      fs.createReadStream(file).pipe(response);
      return;
    }
    if (pathname.startsWith("/ruffle/")) {
      const file = contained(ruffleRoot, pathname.slice("/ruffle/".length));
      if (!file || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
        response.writeHead(404);
        response.end("Missing Ruffle file");
        return;
      }
      response.writeHead(200, { "Content-Type": contentType(file) });
      fs.createReadStream(file).pipe(response);
      return;
    }
    const file = contained(publicRoot, decodeURIComponent(pathname).replace(/^\/+/, ""));
    if (!file || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
      response.writeHead(404, { "Content-Type": "text/plain" });
      response.end("Not found");
      return;
    }
    response.writeHead(200, { "Content-Type": contentType(file) });
    fs.createReadStream(file).pipe(response);
  });

  return { server, store, game, api };
}

const launchedDirectly = process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href;
if (launchedDirectly) {
  const port = Number(process.env.PORT || 8080);
  const { server, game } = createApp();
  const maintTimer = setInterval(() => game.maintain(), 6000);
  if (typeof maintTimer.unref === "function") maintTimer.unref();
  server.listen(port, () => {
    console.log(`Funeral Quest is at http://localhost:${port}/`);
  });
}
