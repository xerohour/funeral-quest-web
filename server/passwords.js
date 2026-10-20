import { randomBytes, scryptSync, timingSafeEqual } from "node:crypto";

const N = 16384;
const r = 8;
const p = 1;
const keyLen = 32;

const dummy = scryptSync("funeral-quest-dummy", randomBytes(16), keyLen, { N, r, p });

export function hashPassword(password) {
  const salt = randomBytes(16);
  const hash = scryptSync(String(password), salt, keyLen, { N, r, p });
  return `scrypt$${N}$${r}$${p}$${salt.toString("hex")}$${hash.toString("hex")}`;
}

export function verifyPassword(stored, password) {
  const parts = String(stored ?? "").split("$");
  if (parts.length !== 6 || parts[0] !== "scrypt") {
    scryptSync(String(password), dummy, keyLen, { N, r, p });
    return false;
  }
  const [, n, rounds, parallel, saltHex, hashHex] = parts;
  const actual = scryptSync(String(password), Buffer.from(saltHex, "hex"), keyLen, {
    N: Number(n),
    r: Number(rounds),
    p: Number(parallel),
  });
  const expected = Buffer.from(hashHex, "hex");
  if (actual.length !== expected.length) return false;
  return timingSafeEqual(actual, expected);
}
