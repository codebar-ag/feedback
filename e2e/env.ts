import { execFileSync } from "node:child_process";
import { mkdirSync, readFileSync } from "node:fs";
import { resolve } from "node:path";

// Playwright loads this file as CommonJS (no package.json with type=module here).
export const ROOT = resolve(__dirname, "..");
export const TMP = resolve(ROOT, "e2e/.tmp");
export const API_PORT = Number(process.env.E2E_API_PORT ?? 8123);
export const WEB_PORT = Number(process.env.E2E_WEB_PORT ?? 4180);
export const API = `http://127.0.0.1:${API_PORT}`;
export const WEB = `http://localhost:${WEB_PORT}`;

mkdirSync(TMP, { recursive: true });

/** Environment of the Laravel app under test (overrides .env, Dotenv is immutable). */
export const LARAVEL_ENV: NodeJS.ProcessEnv = {
  ...process.env,
  APP_ENV: "e2e",
  APP_DEBUG: "false",
  APP_KEY: "base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=",
  APP_URL: API,
  DB_CONNECTION: "sqlite",
  DB_DATABASE: resolve(TMP, "e2e.sqlite"),
  CACHE_STORE: "array",
  LOG_CHANNEL: "stderr",
  LOG_LEVEL: "warning",
  FEEDBACK_ENDPOINT: API,
  FEEDBACK_GEO: "none",
  FEEDBACK_IP_MODE: "full",
  FEEDBACK_LIMIT_PER_IP_PER_MINUTE: "",
  FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE: "",
  FEEDBACK_MIN_ELAPSED_MS: "",
  TRUSTED_PROXIES: "",
};

export function artisan(...args: string[]): string {
  return execFileSync(
    "php",
    ["artisan", ...args, "--no-ansi", "--no-interaction"],
    { cwd: ROOT, env: LARAVEL_ENV, encoding: "utf8" },
  );
}

export function artisanJson<T = unknown>(...args: string[]): T {
  return JSON.parse(artisan(...args, "--json")) as T;
}

export interface Keys {
  open: string;
  ipRestricted: string;
}

export function keys(): Keys {
  return JSON.parse(readFileSync(resolve(TMP, "keys.json"), "utf8")) as Keys;
}
