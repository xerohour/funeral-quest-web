// Keeps the cafe chat scrolled to the newest line.
//
// The chat window's update() runs `st_chat.scroll = st_chat.scroll + st_chat.maxscroll`, where st_chat
// is the variable bound to the chat text field. Flash 5 resolved `.scroll` on a bound variable to its
// text field. Ruffle does not, and SWF 5 text fields have no script object at all, so the chat never
// scrolls and every poll shows the top of the buffer.
//
// buildChatView() makes a small SWF 6 movie holding a copy of that text field (same font, layout and
// binding to /:st_chat) that pins itself to the bottom every frame. patchWelcome() swaps the original
// field for an empty clip named chat_view and loads flash/chat_view.swf into it when the cafe opens.
import fs from "node:fs";
import path from "node:path";
import { pathToFileURL } from "node:url";
import zlib from "node:zlib";

const CHAT_SPRITE = 469;
const BOX_SPRITE = 465;
const CHAT_FIELD = 464;
const CHAT_VIEW_URL = "flash/chat_view.swf";

function readTags(buffer, start, end) {
  const tags = [];
  let at = start;
  while (at < end) {
    const head = buffer.readUInt16LE(at);
    const code = head >> 6;
    let length = head & 0x3f;
    let data = at + 2;
    const long = length === 0x3f;
    if (long) {
      length = buffer.readUInt32LE(at + 2);
      data = at + 6;
    }
    const tag = { code, long, data: buffer.subarray(data, data + length) };
    if (code === 39) {
      tag.spriteHead = tag.data.subarray(0, 4);
      tag.children = readTags(tag.data, 4, tag.data.length);
    }
    tags.push(tag);
    at = data + length;
    if (code === 0) break;
  }
  return tags;
}

function writeTags(tags) {
  return Buffer.concat(tags.map((tag) => {
    const data = tag.children ? Buffer.concat([tag.spriteHead, writeTags(tag.children)]) : tag.data;
    if (!tag.long && data.length < 0x3f) {
      const head = Buffer.alloc(2);
      head.writeUInt16LE((tag.code << 6) | data.length);
      return Buffer.concat([head, data]);
    }
    const head = Buffer.alloc(6);
    head.writeUInt16LE((tag.code << 6) | 0x3f);
    head.writeUInt32LE(data.length, 2);
    return Buffer.concat([head, data]);
  }));
}

function readSwf(swf) {
  const signature = swf.toString("latin1", 0, 3);
  const body = signature === "CWS" ? zlib.inflateSync(swf.subarray(8)) : swf.subarray(8);
  const headerEnd = Math.ceil((5 + (body[0] >> 3) * 4) / 8) + 4;
  return { signature, version: swf[3], body, headerEnd, tags: readTags(body, headerEnd, body.length) };
}

function writeSwf(signature, version, header, tags) {
  const body = Buffer.concat([header, writeTags(tags)]);
  const head = Buffer.alloc(8);
  head.write(signature, 0, "latin1");
  head[3] = version;
  head.writeUInt32LE(body.length + 8, 4);
  return Buffer.concat([head, signature === "CWS" ? zlib.deflateSync(body, { level: 9 }) : body]);
}

class Bits {
  constructor(buffer, at) {
    this.buffer = buffer;
    this.byte = at;
    this.bit = 0;
  }
  read(count) {
    let value = 0;
    for (let i = 0; i < count; i++) {
      value = (value << 1) | ((this.buffer[this.byte] >> (7 - this.bit)) & 1);
      if (++this.bit === 8) {
        this.bit = 0;
        this.byte++;
      }
    }
    return value;
  }
  end() {
    return this.bit ? this.byte + 1 : this.byte;
  }
}

function matrixEnd(buffer, at) {
  const bits = new Bits(buffer, at);
  if (bits.read(1)) bits.read(bits.read(5) * 2);
  if (bits.read(1)) bits.read(bits.read(5) * 2);
  bits.read(bits.read(5) * 2);
  return bits.end();
}

const cstring = (text) => Buffer.from(text + "\0", "latin1");
const tag = (code, data) => ({ code, long: false, data });

function push(...strings) {
  const payload = Buffer.concat(strings.map((text) => Buffer.concat([Buffer.from([0]), cstring(text)])));
  const head = Buffer.alloc(3);
  head[0] = 0x96;
  head.writeUInt16LE(payload.length, 1);
  return Buffer.concat([head, payload]);
}

function sprite(tags, id) {
  const found = tags.find((entry) => entry.code === 39 && entry.data.readUInt16LE(0) === id);
  if (!found) throw new Error(`sprite ${id} not found`);
  return found;
}

function definition(tags, code, id) {
  const found = tags.find((entry) => entry.code === code && entry.data.readUInt16LE(0) === id);
  if (!found) throw new Error(`tag ${code} for character ${id} not found`);
  return found;
}

function onlyPlacement(parent, character) {
  const matches = parent.children.filter((entry) => entry.code === 26 && (entry.data[0] & 0x02) && entry.data.readUInt16LE(3) === character);
  if (matches.length !== 1) throw new Error(`expected one placement of character ${character}, found ${matches.length}`);
  return matches[0];
}

function placement(depth, character, name, matrix = null) {
  const flags = 0x02 | (matrix ? 0x04 : 0) | 0x20;
  const head = Buffer.alloc(5);
  head[0] = flags;
  head.writeUInt16LE(depth, 1);
  head.writeUInt16LE(character, 3);
  return tag(26, Buffer.concat([head, matrix ?? Buffer.alloc(0), cstring(name)]));
}

function fieldParts(field) {
  const data = field.data;
  let at = Math.ceil((5 + (data[2] >> 3) * 4) / 8) + 2;
  const first = data[at];
  const second = data[at + 1];
  const flagsAt = at;
  at += 2;
  const fontAt = first & 0x01 ? at : -1;
  if (first & 0x01) at += 4;
  if (first & 0x04) at += 4;
  if (first & 0x02) at += 2;
  if (second & 0x20) at += 9;
  const variableEnd = data.indexOf(0, at);
  return { flagsAt, fontAt, variableAt: at, variable: data.toString("latin1", at, variableEnd), variableEnd };
}

export function patchWelcome(swf) {
  const movie = readSwf(swf);
  const ids = movie.tags.filter((entry) => entry.data.length >= 2 && [2, 6, 7, 10, 11, 14, 20, 21, 22, 32, 33, 34, 35, 36, 37, 39, 46, 48, 60, 75, 83, 84].includes(entry.code)).map((entry) => entry.data.readUInt16LE(0));
  const holderId = Math.max(...ids) + 1;
  if (holderId > 0xffff) throw new Error("no free character id");

  const box = sprite(movie.tags, BOX_SPRITE);
  const field = onlyPlacement(box, CHAT_FIELD);
  box.children[box.children.indexOf(field)] = placement(field.data.readUInt16LE(1), holderId, "chat_view");

  const holder = Buffer.alloc(4);
  holder.writeUInt16LE(holderId, 0);
  holder.writeUInt16LE(1, 2);
  movie.tags.splice(movie.tags.indexOf(box), 0, { code: 39, long: false, data: holder, spriteHead: holder, children: [tag(1, Buffer.alloc(0)), tag(0, Buffer.alloc(0))] });

  const chat = sprite(movie.tags, CHAT_SPRITE);
  const boxPlacement = onlyPlacement(chat, BOX_SPRITE);
  if (boxPlacement.data[0] & 0x20) throw new Error("the chat box is already named");
  let at = 5;
  if (boxPlacement.data[0] & 0x04) at = matrixEnd(boxPlacement.data, at);
  if (boxPlacement.data[0] & 0x08) throw new Error("unexpected colour transform on the chat box");
  if (boxPlacement.data[0] & 0x10) at += 2;
  boxPlacement.data = Buffer.concat([boxPlacement.data.subarray(0, at), cstring("chat_box"), boxPlacement.data.subarray(at)]);
  boxPlacement.data[0] |= 0x20;

  const load = Buffer.concat([push(CHAT_VIEW_URL, "chat_box.chat_view"), Buffer.from([0x9a, 0x01, 0x00, 0x40])]);
  const skip = Buffer.alloc(5);
  skip[0] = 0x9d;
  skip.writeUInt16LE(2, 1);
  skip.writeInt16LE(load.length, 3);
  const loader = Buffer.concat([push("chat_box.chat_view"), Buffer.from([0x1c, 0x12]), skip, load, Buffer.from([0x00])]);
  const firstScript = chat.children.findIndex((entry) => entry.code === 12);
  if (firstScript < 0) throw new Error("chat window script not found");
  chat.children.splice(firstScript + 1, 0, tag(12, loader));

  return writeSwf(movie.signature, movie.version, movie.body.subarray(0, movie.headerEnd), movie.tags);
}

export function buildChatView(swf) {
  const movie = readSwf(swf);
  const field = definition(movie.tags, 37, CHAT_FIELD);
  const parts = fieldParts(field);
  if (parts.variable !== "/:st_chat" || parts.fontAt < 0) throw new Error("chat field is not as expected");
  const font = definition(movie.tags, 48, field.data.readUInt16LE(parts.fontAt));
  const fieldData = Buffer.from(field.data.subarray(0, parts.variableEnd + 1));
  fieldData[parts.flagsAt] &= ~0x80;

  const original = onlyPlacement(sprite(movie.tags, BOX_SPRITE), CHAT_FIELD);
  const flags = original.data[0];
  if (flags & 0x08) throw new Error("unexpected colour transform on the chat field");
  const matrix = flags & 0x04 ? original.data.subarray(5, matrixEnd(original.data, 5)) : null;

  const pin = Buffer.concat([push("chat_text"), Buffer.from([0x1c]), push("scroll", "chat_text"), Buffer.from([0x1c]), push("maxscroll"), Buffer.from([0x4e, 0x4f, 0x00])]);
  const frame = [tag(12, pin), tag(1, Buffer.alloc(0))];
  const header = Buffer.from(movie.body.subarray(0, movie.headerEnd));
  header.writeUInt16LE(2, movie.headerEnd - 2);
  return writeSwf("CWS", 6, header, [
    { ...font },
    { code: 37, long: true, data: fieldData },
    placement(original.data.readUInt16LE(1), CHAT_FIELD, "chat_text", matrix),
    ...frame,
    ...frame,
    tag(0, Buffer.alloc(0)),
  ]);
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  const [from, dir] = process.argv.slice(2);
  const swf = fs.readFileSync(from);
  fs.writeFileSync(path.join(dir, "welcome.swf"), patchWelcome(swf));
  fs.writeFileSync(path.join(dir, "chat_view.swf"), buildChatView(swf));
  console.log(`wrote welcome.swf and chat_view.swf to ${dir}`);
}
