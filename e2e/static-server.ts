/** Serves e2e/fixtures and the built widget (widget/dist) for the full stack tests. */
import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import { extname, join, normalize, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(fileURLToPath(new URL("..", import.meta.url)));
const port = Number(process.env.PORT ?? 4180);
const TYPES: Record<string, string> = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript",
  ".css": "text/css",
};

createServer((req, res) => {
  void (async () => {
    const path = new URL(req.url ?? "/", "http://x").pathname;
    const base = path.startsWith("/dist/")
      ? join(root, "widget")
      : join(root, "e2e/fixtures");
    const rel = path.startsWith("/dist/")
      ? path
      : path === "/"
        ? "/index.html"
        : path;
    const file = normalize(join(base, rel));
    if (!file.startsWith(root)) {
      res.writeHead(404).end();
      return;
    }
    try {
      const data = await readFile(file);
      res.writeHead(200, {
        "Content-Type": TYPES[extname(file)] ?? "application/octet-stream",
        "Cache-Control": "no-store",
      });
      res.end(data);
    } catch {
      res.writeHead(404).end();
    }
  })();
}).listen(port, () => console.log(`static server on ${port}`));
