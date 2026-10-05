import { readdirSync, statSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import MarkdownIt from 'markdown-it';

// Parse with a real CommonMark parser instead of line regexes: fences (also inside
// blockquotes and lists), code spans of any backtick length, escapes and tags split
// across lines are all resolved exactly as the renderer resolves them. Raw HTML is
// then whatever the parser emits as an html_block or html_inline token.
const md = new MarkdownIt({ html: true });
const DOCS = join(process.cwd(), 'docs');
const BUTTON = /^\s*:::\s*button\b/;
const bad = [];

function checkInline(token, p, fallbackLine) {
  // Inline tokens inside table cells carry no map: use the enclosing block's line.
  let line = token.map ? token.map[0] + 1 : fallbackLine;
  let lineStart = true;
  for (const child of token.children) {
    if (child.type === 'softbreak' || child.type === 'hardbreak') {
      line++;
      lineStart = true;
      continue;
    }
    if (child.type === 'html_inline') bad.push(`${p}:${line} raw HTML ${child.content}`);
    if (child.type === 'text' && lineStart && BUTTON.test(child.content)) {
      bad.push(`${p}:${line} forbidden ::: button container`);
    }
    lineStart = false;
  }
}

(function walk(d) {
  for (const n of readdirSync(d)) {
    const p = join(d, n);
    if (statSync(p).isDirectory()) {
      walk(p);
      continue;
    }
    if (!n.endsWith('.md')) continue;

    let blockLine = 1;
    for (const token of md.parse(readFileSync(p, 'utf8'), {})) {
      if (token.map) blockLine = token.map[0] + 1;
      if (token.type === 'html_block') {
        bad.push(`${p}:${token.map[0] + 1} raw HTML ${token.content.trim().split('\n')[0]}`);
      } else if (token.type === 'inline') {
        checkInline(token, p, blockLine);
      }
    }
  }
})(DOCS);

if (bad.length) {
  console.error('Docs Markdown guard failed:\n' + bad.join('\n'));
  process.exit(1);
}
console.log('OK: no raw HTML or forbidden button containers.');
