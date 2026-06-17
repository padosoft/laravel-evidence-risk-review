import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const DOCS = join(process.cwd(), 'docs');
const TAG = /<\/?[A-Z][A-Za-z0-9]*(\s|>|\/)/g;
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

    let inFence = false;
    readFileSync(p, 'utf8').split('\n').forEach((line, i) => {
      if (/^\s*```/.test(line)) {
        inFence = !inFence;
        return;
      }
      if (inFence) return;

      const m = stripInlineCode(line).match(TAG);
      if (m) bad.push(`${p}:${i + 1} ${m.join(' ')}`);
    });
  }
})(DOCS);

if (bad.length) {
  console.error('Raw component tags found:\n' + bad.join('\n'));
  process.exit(1);
}
console.log('OK: no raw component tags.');
