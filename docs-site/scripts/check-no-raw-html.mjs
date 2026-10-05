import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const DOCS = join(process.cwd(), 'docs');
const TAG = /<\/?[A-Za-z][\w:-]*(\s[^>]*)?\/?>/g;
const BUTTON = /:::\s*button\b/;
const bad = [];

function stripInlineCode(line) {
  return line.replace(/`[^`]*`/g, '');
}

(function walk(d) {
  for (const n of readdirSync(d)) {
    const p = join(d, n);
    if (statSync(p).isDirectory()) {
      walk(p);
      continue;
    }
    if (!n.endsWith('.md')) continue;

    // Code samples may legitimately show markup: skip fenced blocks and inline code.
    let inFence = false;
    readFileSync(p, 'utf8').split('\n').forEach((line, i) => {
      if (/^\s*(```|~~~)/.test(line)) {
        inFence = !inFence;
        return;
      }
      if (inFence) return;

      const prose = stripInlineCode(line);
      const m = prose.match(TAG);
      if (m) bad.push(`${p}:${i + 1} raw HTML ${m.join(' ')}`);
      if (BUTTON.test(prose)) bad.push(`${p}:${i + 1} forbidden ::: button container`);
    });
  }
})(DOCS);

if (bad.length) {
  console.error('Docs Markdown guard failed:\n' + bad.join('\n'));
  process.exit(1);
}
console.log('OK: no raw HTML or forbidden button containers.');
