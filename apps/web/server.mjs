import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { existsSync } from 'node:fs';

// 1. Automatically load appropriate .env file if process.loadEnvFile is supported (Node 20.12+)
const isProd = process.env.NODE_ENV === 'production';
const envFile = isProd && existsSync('.env.production')
  ? '.env.production'
  : (existsSync('.env') ? '.env' : null);

if (envFile && typeof process.loadEnvFile === 'function') {
  try {
    process.loadEnvFile(resolve(process.cwd(), envFile));
  } catch (err) {
    // If already loaded or parsing error, continue gracefully
  }
}

// 2. Ensure default host and port if not set
if (!process.env.NITRO_PORT && process.env.PORT) {
  process.env.NITRO_PORT = process.env.PORT;
}
if (!process.env.NITRO_HOST && process.env.HOST) {
  process.env.NITRO_HOST = process.env.HOST;
}

// 3. Fix Nitro Windows ES Module hoisting issue with _importMeta_
const indexPath = resolve(process.cwd(), '.output/server/index.mjs');
if (!existsSync(indexPath)) {
  console.error('[Error] .output/server/index.mjs not found. Please run "npm run build" first.');
  process.exit(1);
}

globalThis._importMeta_ = {
  url: pathToFileURL(indexPath).href,
  env: process.env,
};

await import(pathToFileURL(indexPath).href);
