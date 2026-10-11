#!/usr/bin/env node
/**
 * Convert a Shopify product CSV export into a WooCommerce product CSV with the same columns as
 * woocommerce/sample-data/sample_products.csv, ready for WooCommerce → Products → Import.
 *
 *   npm run convert:shopify -- "HK Collection.csv"       # file name is looked up in reference/Shopify Export
 *   npm run convert:shopify -- path/to/in.csv -o out.csv --category "Shoes" --weight-unit kg --dimension-unit cm
 *
 * Output (default): reference/WooCommerce Import/"<input name> - WooCommerce.csv".
 *
 * Rules:
 * - Shopify metafield columns, "Option Linked To", SEO, vendor, barcodes and cost are ignored.
 * - Options: any option name with "size" → attribute "Size", "color"/"colour" → "Color" (global attributes).
 * - Colours kept simple: "light-blue-1" → "Light Blue", "gray-3" → "Grey".
 * - Sizes: "-" → "." ("10-5" → 10.5).
 * - An option with one value (e.g. the product's only colour) is a plain attribute, not a variation choice.
 *   No option with 2+ values → simple product.
 * - Prices/weights: decimal comma → dot ("79,99" → 79.99).
 * - Price → Sale price, Compare At Price → Regular price (when higher; otherwise Regular price = Price).
 * - SKUs: Shopify's when set, otherwise generated from the handle (parent) and handle + option values
 *   (variation). Variations point to their parent by SKU; re-importing with "update existing" updates them.
 * - Images: product images in Shopify's order (first = featured), Variant Image → variation image,
 *   "?v=" query removed. The WooCommerce importer downloads them.
 * - Status active + published → Published 1, otherwise draft (-1).
 * - Optional columns "brand" and "collection" (added to the export by hand):
 *   brand → "Brands" column (WooCommerce's product_brand taxonomy; the importer maps it and creates new brands);
 *   category = --category > brand > collection, e.g. --category Shoes → "Shoes > Adidas > Adidas Samba"
 *   (missing parts are skipped; the importer creates the categories).
 * - Weight/dimension column headers use the store's units (WooCommerce only maps "Weight (<store unit>)");
 *   defaults kg / cm, as set on this store.
 */
import fs from 'node:fs';
import path from 'node:path';

const REFERENCE = path.resolve(import.meta.dirname, '..', 'reference');
const EXPORT_DIR = path.join(REFERENCE, 'Shopify Export');
const IMPORT_DIR = path.join(REFERENCE, 'WooCommerce Import');

/* ---------- Arguments ---------- */

const args = process.argv.slice(2);
const opt = { out: '', category: '', weightUnit: 'kg', dimensionUnit: 'cm' };
const inputs = [];
for (let i = 0; i < args.length; i++) {
  const a = args[i];
  if (a === '-o' || a === '--out') opt.out = args[++i];
  else if (a === '--category') opt.category = args[++i];
  else if (a === '--weight-unit') opt.weightUnit = args[++i];
  else if (a === '--dimension-unit') opt.dimensionUnit = args[++i];
  else if (a === '-h' || a === '--help') {
    console.log('Usage: node tools/shopify-to-woo.mjs <shopify.csv> [-o out.csv] [--category "A > B"] [--weight-unit kg] [--dimension-unit cm]');
    process.exit(0);
  } else inputs.push(a);
}
if (inputs.length !== 1) {
  console.error('Give one Shopify CSV file. --help for options.');
  process.exit(1);
}
const input = fs.existsSync(inputs[0]) ? inputs[0] : path.join(EXPORT_DIR, inputs[0]);
if (!fs.existsSync(input)) {
  console.error(`Not found: ${inputs[0]} (also looked in ${path.relative(process.cwd(), EXPORT_DIR)})`);
  process.exit(1);
}
const output = opt.out || path.join(IMPORT_DIR, `${path.basename(input, path.extname(input))} - WooCommerce.csv`);
fs.mkdirSync(path.dirname(output), { recursive: true });

/* ---------- CSV ---------- */

function parseCsv(text) {
  text = text.replace(/^﻿/, '');
  const firstLine = text.slice(0, text.indexOf('\n'));
  const delim = (firstLine.match(/;/g) || []).length > (firstLine.match(/,/g) || []).length ? ';' : ',';
  const rows = [];
  let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i++; }
      else if (c === '"') quoted = false;
      else field += c;
    } else if (c === '"') quoted = true;
    else if (c === delim) { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(field); rows.push(row); row = []; field = '';
    } else field += c;
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  const [header, ...body] = rows.filter((r) => r.some((v) => v !== ''));
  return body.map((r) => Object.fromEntries(header.map((h, i) => [h.trim(), (r[i] ?? '').trim()])));
}

const toCsv = (rows) =>
  rows.map((r) => r.map((v) => {
    v = v === undefined || v === null ? '' : String(v);
    return /[",\n\r]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v;
  }).join(',')).join('\n') + '\n';

/* ---------- Normalizing ---------- */

const attributeName = (name) => {
  if (/size/i.test(name)) return 'Size';
  if (/colou?r/i.test(name)) return 'Color';
  return name;
};

const titleCase = (s) => s.replace(/\b\p{L}/gu, (m) => m.toUpperCase());

function normalizeValue(attribute, value) {
  if (attribute === 'Size') return value.replace(/-/g, '.');
  if (attribute === 'Color') {
    const words = value.replace(/[-_]+/g, ' ').replace(/\s+\d+$/, '').replace(/\bgray\b/gi, 'grey').trim().toLowerCase();
    return titleCase(words);
  }
  return value;
}

const slug = (s) => s.replace(/[™®©]/g, '').normalize('NFKD').replace(/[^\w\s.-]/g, '').toLowerCase().replace(/[\s._]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
const cleanUrl = (u) => (u ? u.split('?')[0] : '');
// The importer splits terms on "," (keep "\," literal).
const escapeTerm = (name) => name.replace(/,/g, '\\,');
const isTrue = (v) => /^(true|vrai|1|yes)$/i.test(v || '');

// Numbers may use a decimal comma ("79,99", "1.299,00" or "1,299.00"): WooCommerce needs "79.99".
function number(value) {
  let v = String(value || '').replace(/[^\d.,-]/g, '');
  const lastComma = v.lastIndexOf(','), lastDot = v.lastIndexOf('.');
  v = lastComma > lastDot ? v.replace(/\./g, '').replace(',', '.') : v.replace(/,/g, '');
  return v === '' ? NaN : parseFloat(v);
}
const money = (n) => String(Math.round(n * 100) / 100);

function weight(row) {
  const grams = number(row['Variant Grams']);
  if (!grams) return '';
  const factor = { kg: 1000, g: 1, lbs: 453.59237, oz: 28.349523 }[opt.weightUnit] || 1000;
  return String(Math.round((grams / factor) * 1000) / 1000);
}

function prices(row) {
  const price = number(row['Variant Price']);
  const compare = number(row['Variant Compare At Price']);
  if (Number.isNaN(price)) return { sale: '', regular: '' };
  if (compare > price) return { sale: money(price), regular: money(compare) };
  return { sale: '', regular: money(price) };
}

function stock(row) {
  const backorders = row['Variant Inventory Policy'] === 'continue' ? 1 : 0;
  const tracked = row['Variant Inventory Tracker'] === 'shopify' && row['Variant Inventory Qty'] !== undefined && row['Variant Inventory Qty'] !== '';
  if (!tracked) return { inStock: 1, qty: '', backorders };
  const qty = parseInt(row['Variant Inventory Qty'], 10) || 0;
  return { inStock: qty > 0 || backorders ? 1 : 0, qty, backorders };
}

/* ---------- WooCommerce columns (sample_products.csv) ---------- */

const W = `Weight (${opt.weightUnit})`, L = `Length (${opt.dimensionUnit})`, WD = `Width (${opt.dimensionUnit})`, H = `Height (${opt.dimensionUnit})`;
const COLUMNS = ['ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog', 'Short description', 'Description',
  'Date sale price starts', 'Date sale price ends', 'Tax status', 'Tax class', 'In stock?', 'Stock', 'Backorders allowed?',
  'Sold individually?', W, L, WD, H, 'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price', 'Categories',
  'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry days', 'Parent', 'Grouped products', 'Upsells',
  'Cross-sells', 'External URL', 'Button text', 'Position', 'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible',
  'Attribute 1 global', 'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global',
  'Meta: _wpcom_is_markdown', 'Download 1 name', 'Download 1 URL', 'Download 2 name', 'Download 2 URL'];
// Shopify allows 3 options: add a 3rd attribute block only when a product needs it.
const ATTR3 = ['Attribute 3 name', 'Attribute 3 value(s)', 'Attribute 3 visible', 'Attribute 3 global'];

/* ---------- Convert ---------- */

const rows = parseCsv(fs.readFileSync(input, 'utf8'));
const products = new Map();
for (const r of rows) {
  if (!r.Handle) continue;
  if (!products.has(r.Handle)) products.set(r.Handle, []);
  products.get(r.Handle).push(r);
}

const out = [];
const usedSkus = new Set();
const uniqueSku = (wanted) => {
  let sku = wanted, n = 2;
  while (usedSkus.has(sku)) sku = `${wanted}-${n++}`;
  usedSkus.add(sku);
  return sku;
};
let needsAttr3 = false, variable = 0, simple = 0, variationCount = 0;

for (const [handle, group] of products) {
  const first = group[0];
  if (isTrue(first['Gift Card'])) { console.warn(`Skipped gift card: ${handle}`); continue; }

  // Variant rows have an option value (or a price, for products without options); the rest only add images.
  const variantRows = group.filter((r) => r['Option1 Value'] || r['Variant Price']);

  // Options → attributes with their values in Shopify's order.
  const options = [];
  for (let i = 1; i <= 3; i++) {
    const name = first[`Option${i} Name`];
    if (!name || (name === 'Title' && variantRows.every((r) => r[`Option${i} Value`] === 'Default Title'))) continue;
    const attr = attributeName(name);
    if (options.some((o) => o.attr === attr)) { console.warn(`${handle}: 2nd "${attr}" option skipped`); continue; }
    const values = [];
    for (const r of variantRows) {
      const v = normalizeValue(attr, r[`Option${i} Value`]);
      if (v && !values.includes(v)) values.push(v);
    }
    if (!values.length) { console.warn(`${handle}: option "${name}" has no values, skipped`); continue; }
    options.push({ index: i, attr, values, varies: values.length > 1 });
  }
  if (options.length > 2) needsAttr3 = true;
  const varying = options.filter((o) => o.varies);

  const images = [];
  for (const r of [...group].sort((a, b) => (parseInt(a['Image Position'], 10) || 0) - (parseInt(b['Image Position'], 10) || 0))) {
    const url = cleanUrl(r['Image Src']);
    if (url && !images.includes(url)) images.push(url);
  }

  const published = first.Status ? (first.Status === 'active' && isTrue(first.Published) ? 1 : -1) : (isTrue(first.Published) ? 1 : -1);
  const parentSku = uniqueSku(first['Variant SKU'] && varying.length === 0 ? first['Variant SKU'] : slug(handle));
  const taxable = isTrue(first['Variant Taxable']) || first['Variant Taxable'] === '' ? 'taxable' : 'none';

  const parent = {
    Type: varying.length ? 'variable' : 'simple',
    SKU: parentSku,
    Name: first.Title,
    Published: published,
    'Is featured?': 0,
    'Visibility in catalog': 'visible',
    Description: first['Body (HTML)'],
    'Tax status': taxable,
    'In stock?': 1,
    'Backorders allowed?': 0,
    'Sold individually?': 0,
    'Allow customer reviews?': 1,
    Categories: [opt.category, first.brand, first.collection].filter(Boolean).map(escapeTerm).join(' > '),
    Brands: escapeTerm(first.brand || ''),
    Tags: first.Tags,
    Images: images.join(', '),
    Position: 0,
  };
  options.forEach((o, k) => {
    parent[`Attribute ${k + 1} name`] = o.attr;
    parent[`Attribute ${k + 1} value(s)`] = o.values.join(', ');
    parent[`Attribute ${k + 1} visible`] = 1;
    parent[`Attribute ${k + 1} global`] = 1;
  });

  if (!varying.length) {
    const r = variantRows[0] || first;
    const p = prices(r), s = stock(r);
    Object.assign(parent, { 'Sale price': p.sale, 'Regular price': p.regular, 'In stock?': s.inStock, Stock: s.qty, 'Backorders allowed?': s.backorders, [W]: weight(r) });
    out.push(parent);
    simple++;
    continue;
  }

  out.push(parent);
  variable++;
  const seen = new Set();
  let position = 0;
  for (const r of variantRows) {
    const values = varying.map((o) => normalizeValue(o.attr, r[`Option${o.index} Value`]));
    const key = values.join('|');
    if (seen.has(key)) { console.warn(`${handle}: duplicate variation ${values.join(', ')} skipped`); continue; }
    seen.add(key);

    const p = prices(r), s = stock(r);
    const variation = {
      Type: 'variation',
      SKU: uniqueSku(r['Variant SKU'] || `${parentSku}-${slug(values.join('-'))}`),
      Name: `${first.Title} - ${values.join(', ')}`,
      Published: 1,
      'Is featured?': 0,
      'Visibility in catalog': 'visible',
      'Tax status': isTrue(r['Variant Taxable']) || r['Variant Taxable'] === '' ? 'taxable' : 'none',
      'In stock?': s.inStock,
      Stock: s.qty,
      'Backorders allowed?': s.backorders,
      'Sold individually?': 0,
      [W]: weight(r),
      'Allow customer reviews?': 0,
      'Sale price': p.sale,
      'Regular price': p.regular,
      Images: cleanUrl(r['Variant Image']),
      Parent: parentSku,
      Position: position++,
    };
    // Only the varying attributes go on variation rows (that is what makes them variation attributes).
    varying.forEach((o, k) => {
      const slot = options.indexOf(o) + 1;
      variation[`Attribute ${slot} name`] = o.attr;
      variation[`Attribute ${slot} value(s)`] = values[k];
      variation[`Attribute ${slot} global`] = 1;
    });
    out.push(variation);
    variationCount++;
  }
}

let header = needsAttr3 ? [...COLUMNS.slice(0, 46), ...ATTR3, ...COLUMNS.slice(46)] : COLUMNS;
// "Brands" is WooCommerce's own column name (auto-mapped); only added when the export has brands.
if (out.some((o) => o.Brands)) header = [...header, 'Brands'];
fs.writeFileSync(output, toCsv([header, ...out.map((o) => header.map((h) => o[h] ?? ''))]));
console.log(`${products.size} products → ${variable} variable (${variationCount} variations), ${simple} simple`);
console.log(`Written: ${output}`);
