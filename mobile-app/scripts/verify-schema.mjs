import pg from "pg";

const client = new pg.Client({
  connectionString: process.env.PG_URL,
  ssl: { rejectUnauthorized: false },
});

await client.connect();
const { rows } = await client.query(`
  select table_name from information_schema.tables
  where table_schema = 'public' order by table_name;
`);
console.log(rows.map((r) => r.table_name).join("\n"));
await client.end();
