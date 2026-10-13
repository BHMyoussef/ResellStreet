// The Shopify design reference (an Impulse-theme store) lives only on the developer's machine:
// reference/target.json (git-ignored) = { base, pages: { home, collection, product }, variantId, ip? }.
// LIVE_IP=<ip> (or "ip" in the file) pins the store's host if local DNS fails for Chromium.
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const file = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../reference/target.json');

export function referenceTarget() {
  try {
    return JSON.parse(readFileSync(file, 'utf8'));
  } catch {
    throw new Error(`Design reference not configured: create ${path.relative(process.cwd(), file)} ({ base, pages, variantId }).`);
  }
}

export function referenceLaunchArgs(ref) {
  const ip = process.env.LIVE_IP || ref?.ip;
  return ref && ip ? { args: [`--host-resolver-rules=MAP ${new URL(ref.base).hostname} ${ip}`] } : {};
}
