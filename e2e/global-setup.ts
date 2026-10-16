import { rmSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";
import { LARAVEL_ENV, TMP, WEB, artisan, artisanJson } from "./env";

/** Fresh SQLite database and two projects (one with IP restriction). */
export default function globalSetup(): void {
  const db = String(LARAVEL_ENV.DB_DATABASE);
  rmSync(db, { force: true });
  writeFileSync(db, "");
  artisan("migrate", "--force");
  const open = artisanJson<{ public_key: string }>(
    "feedback:project:create",
    "E2E Website",
    `--origin=${WEB}`,
    "--color=#0A7C66",
    "--by=e2e",
  );
  const restricted = artisanJson<{ public_key: string }>(
    "feedback:project:create",
    "E2E Intranet",
    `--origin=${WEB}`,
    "--ip=10.0.0.0/8",
  );
  writeFileSync(
    resolve(TMP, "keys.json"),
    JSON.stringify({
      open: open.public_key,
      ipRestricted: restricted.public_key,
    }),
  );
}
