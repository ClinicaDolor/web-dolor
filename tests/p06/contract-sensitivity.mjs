import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const css = fs.readFileSync('public/assets/css/app.css', 'utf8');
const startMarker = '/* P06-COMPLETE-01';
const endMarker = '/* END P06-COMPLETE-01';
const start = css.indexOf(startMarker);
const end = css.indexOf(endMarker, start);
if (start < 0 || end <= start) throw new Error('No se pudo preparar el fixture aislado de P06.');

const invalidCss = css.slice(0, end)
  + '\nbody.app-shell-body.patient-surgery-page .p06-contract-sensitivity { color: red !important; }\n'
  + css.slice(end);
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'p06-contract-'));
const fixturePath = path.join(directory, 'app.css');
fs.writeFileSync(fixturePath, invalidCss, 'utf8');

try {
  const result = spawnSync(process.execPath, [
    'tests/p06/contract-check.mjs',
    `--css-path=${fixturePath}`,
  ], { encoding: 'utf8' });
  const output = `${result.stdout ?? ''}${result.stderr ?? ''}`;
  if (result.status === 0 || !output.includes('El CSS P06 introduce !important innecesario.')) {
    throw new Error('El contrato P06 no detectó la violación sintética de !important.');
  }
} finally {
  fs.rmSync(directory, { recursive: true, force: true });
}

console.log('P06 contract sensitivity: PASS');
