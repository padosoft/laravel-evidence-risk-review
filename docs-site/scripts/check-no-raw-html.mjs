import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const DOCS = join(process.cwd(), 'docs');
const TAG = /<\/?[A-Za-z][\w:-]*(\s[^>]*)?\/?>/g;
const OPEN_TAG_AT_EOL = /<[A-Za-z][\w:-]*(\s[^<>]*)?$/;
const BUTTON = /:::\s*button\b/;
const FENCE = /^\s{0,3}(`{3,}|~{3,})(.*)$/;
const bad = [];

function stripInlineCode(line) {
  // A code span opens on a run of N backticks and closes on the next run of exactly N.
  return line.replace(/(?<!`)(`+)(?!`)[\s\S]*?(?<!`)\1(?!`)/g, '');
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
    // A fence closes only on the same character, at least as long, with no info string.
    let fence = null;
    readFileSync(p, 'utf8').split(/\r?\n/).forEach((line, i) => {
      const f = line.match(FENCE);
      if (fence) {
        if (f && f[1][0] === fence[0] && f[1].length >= fence.length && f[2].trim() === '') fence = null;
        return;
      }
      // A backtick fence's info string cannot contain a backtick, or it is not a fence.
      if (f && !(f[1][0] === '`' && f[2].includes('`'))) {
        fence = f[1];
        return;
      }

      const prose = stripInlineCode(line);
      const m = prose.match(TAG);
      if (m) bad.push(`${p}:${i + 1} raw HTML ${m.join(' ')}`);
      // An opening tag whose `>` sits on a later line, e.g. `<img` then `src="x">`.
      const open = prose.replace(TAG, '').match(OPEN_TAG_AT_EOL);
      if (open) bad.push(`${p}:${i + 1} raw HTML ${open[0].trim()} (tag continues on the next line)`);
      if (BUTTON.test(prose)) bad.push(`${p}:${i + 1} forbidden ::: button container`);
    });
  }
})(DOCS);

if (bad.length) {
  console.error('Docs Markdown guard failed:\n' + bad.join('\n'));
  process.exit(1);
}
console.log('OK: no raw HTML or forbidden button containers.');
