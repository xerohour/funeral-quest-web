import fs from "node:fs";
import path from "node:path";
import { buildChatView, patchWelcome } from "./patch-welcome.mjs";

const root = process.cwd();
const www = path.join(root, "www");

function copyFile(from, to) {
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
}

function copyDir(from, to) {
  fs.mkdirSync(to, { recursive: true });
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    const source = path.join(from, entry.name);
    const target = path.join(to, entry.name);
    if (entry.isDirectory()) copyDir(source, target);
    else copyFile(source, target);
  }
}

copyDir(path.join(root, "upstream", "dist", "public", "flash"), path.join(www, "flash"));
const welcome = fs.readFileSync(path.join(root, "upstream", "dist", "public", "flash", "welcome.swf"));
fs.writeFileSync(path.join(www, "flash", "welcome.swf"), patchWelcome(welcome));
fs.writeFileSync(path.join(www, "flash", "chat_view.swf"), buildChatView(welcome));
copyFile(path.join(root, "upstream", "dist", "public", "favicon.ico"), path.join(www, "favicon.ico"));

const pages = path.join(root, "upstream", "dist", "public", "pages");
const centered =
  '<style type="text/css">html { overflow-y: scroll; } body { max-width: 666px; margin: 8px auto; } ' +
  'tr:has(> td[colspan="4"] :is(p, ul, ol)) + tr > td { height: 36px; }</style>\n';
const textRow = /<tr>\s*<td[^>]*><\/td>\s*<td[^>]*><\/td>\s*<td colspan="3"[^>]*>/g;
const widenTextRow = (row) =>
  row.replace(/ height="\d+"/g, "").replace(/<td[^>]*><\/td>(\s*)<td colspan="3"/, '$1<td colspan="4"');
const navBar = fs
  .readFileSync(path.join(pages, "intro.htm"), "latin1")
  .match(/<table width="100%"[^>]*class="frame_left">[\s\S]*?<\/table>/)[0];
const navBarStyle =
  '<style type="text/css">.frame_left { background-image: url(frame_bg.gif); background-repeat: repeat-x } ' +
  ".frame_left + p { margin-top: 0; } .frame_left ~ * { margin-left: 3px; }</style>\n";
for (const name of fs.readdirSync(pages)) {
  if (name === "server.htm" || name === "rankings.htm" || name === "start.html") continue;
  const from = path.join(pages, name);
  const to = path.join(www, "pages", name);
  if (!name.endsWith(".htm")) {
    copyFile(from, to);
    continue;
  }
  const html = fs.readFileSync(from, "latin1");
  fs.mkdirSync(path.dirname(to), { recursive: true });
  let aligned = html.replace(/<\/head>/i, centered + "$&").replace(textRow, widenTextRow);
  if (name === "rank_start.htm") {
    aligned = aligned.replace(/<\/head>/i, navBarStyle + "$&").replace(/<body[^>]*>/i, "$&\n" + navBar);
  }
  fs.writeFileSync(to, aligned, "latin1");
}

const ruffle = path.join(root, "node_modules", "@ruffle-rs", "ruffle");
for (const name of fs.readdirSync(ruffle)) {
  if (name.endsWith(".js") || name.endsWith(".wasm")) {
    copyFile(path.join(ruffle, name), path.join(www, "ruffle", name));
  }
}

// Keep the original and emulator notices with every prepared upload.
copyFile(path.join(root, "upstream", "license.txt"), path.join(www, "licenses", "FuneralQuest-Proton-SDK.txt"));
copyFile(path.join(root, "upstream", "README.md"), path.join(www, "licenses", "FuneralQuest-README.md"));
for (const name of ["LICENSE_MIT", "LICENSE_APACHE"]) {
  copyFile(path.join(ruffle, name), path.join(www, "licenses", "Ruffle-" + name + ".txt"));
}
copyFile(path.join(root, "LICENSE"), path.join(www, "licenses", "PHP-port-MIT.txt"));
const notices = fs.readFileSync(path.join(root, "THIRD_PARTY_NOTICES.md"), "utf8")
  .replaceAll("licenses/Proton-SDK.txt", "FuneralQuest-Proton-SDK.txt")
  .replaceAll("](LICENSE)", "](PHP-port-MIT.txt)");
fs.writeFileSync(path.join(www, "licenses", "THIRD_PARTY_NOTICES.md"), notices);

const dist = path.join(root, "upstream", "dist");
const packed = path.join(www, "private", "dist");
for (const name of fs.readdirSync(dist)) {
  if (name.endsWith(".txt")) copyFile(path.join(dist, name), path.join(packed, name));
}
copyDir(path.join(dist, "script"), path.join(packed, "script"));

console.log("Packed the Flash client, Ruffle, and game data into www/");
