import { readFileSync } from "fs";
import pg from "pg";
import { fileURLToPath } from "url";
import path from "path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sql = readFileSync(path.join(__dirname, "..", "supabase", "schema.sql"), "utf8");

const client = new pg.Client({
  connectionString: process.env.PG_URL,
  ssl: { rejectUnauthorized: false },
});

try {
  await client.connect();
  await client.query(sql);
  console.log("SCHEMA_OK");
} catch (e) {
  console.error("SCHEMA_ERROR:", e.message);
  process.exitCode = 1;
} finally {
  await client.end();
}
