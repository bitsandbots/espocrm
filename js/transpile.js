/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2026 EspoCRM, Inc.
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

const {Transpiler} = require('espo-frontend-build-tools');
const babelCore = require('@babel/core');
const fs = require('fs');
const {globSync} = require('glob');

let file;

let fIndex = process.argv.findIndex(item => item === '-f');

if (fIndex > 0) {
    file = process.argv.at(fIndex + 1);

    if (!file) {
        throw new Error(`No file specified.`);
    }
}

const transpiler1 = new Transpiler({
    file: file,
});

const transpiler2 = new Transpiler({
    mod: 'crm',
    path: 'client/modules/crm',
    file: file,
});

const result1 = transpiler1.process();
const result2 = transpiler2.process();

// Custom module transpile: define name gets modules/quick-books/ prefix (normalises to
// quick-books: in the loader), output goes to lib/transpiled/src/ (where the loader expects it).
const QB_SRC = 'client/custom/modules/quick-books/src';
const QB_OUT = 'client/custom/modules/quick-books/lib/transpiled/src';

let qbTranspiled = 0;

const qbFiles = globSync(QB_SRC + '/**/*.{js,ts}')
    .map(f => f.replaceAll('\\', '/'))
    .filter(f => !f.endsWith('.d.ts'))
    .filter(f => !file || f === file.replaceAll('\\', '/'));

for (const srcFile of qbFiles) {
    const rel = srcFile.slice(QB_SRC.length + 1).replace(/\.(js|ts)$/, '');
    const moduleId = `modules/quick-books/${rel}`;
    const outDir = `${QB_OUT}/${rel.split('/').slice(0, -1).join('/')}`;
    const outFile = `${QB_OUT}/${rel}.js`;

    const result = babelCore.transformSync(fs.readFileSync(srcFile, 'utf-8'), {
        presets: [['@babel/preset-env', {targets: {chrome: '90', safari: '16'}}]],
        plugins: [
            ...(srcFile.endsWith('.ts') ? ['@babel/plugin-transform-typescript'] : []),
            '@babel/plugin-transform-modules-amd',
            ['@babel/plugin-proposal-decorators', {version: '2023-11'}],
        ],
        moduleId,
        sourceMaps: true,
    });

    fs.mkdirSync(outDir, {recursive: true});
    const filePart = rel.split('/').slice(-1)[0] + '.js';
    fs.writeFileSync(outFile, result.code + `\n//# sourceMappingURL=${filePart}.map ;`, 'utf-8');
    fs.writeFileSync(outFile + '.map', result.map.toString(), 'utf-8');
    qbTranspiled++;
}

const XERO_SRC = 'client/custom/modules/xero/src';
const XERO_OUT = 'client/custom/modules/xero/lib/transpiled/src';

let xeroTranspiled = 0;

const xeroFiles = globSync(XERO_SRC + '/**/*.{js,ts}')
    .map(f => f.replaceAll('\\', '/'))
    .filter(f => !f.endsWith('.d.ts'))
    .filter(f => !file || f === file.replaceAll('\\', '/'));

for (const srcFile of xeroFiles) {
    const rel = srcFile.slice(XERO_SRC.length + 1).replace(/\.(js|ts)$/, '');
    const moduleId = `modules/xero/${rel}`;
    const outDir = `${XERO_OUT}/${rel.split('/').slice(0, -1).join('/')}`;
    const outFile = `${XERO_OUT}/${rel}.js`;

    const result = babelCore.transformSync(fs.readFileSync(srcFile, 'utf-8'), {
        presets: [['@babel/preset-env', {targets: {chrome: '90', safari: '16'}}]],
        plugins: [
            ...(srcFile.endsWith('.ts') ? ['@babel/plugin-transform-typescript'] : []),
            '@babel/plugin-transform-modules-amd',
            ['@babel/plugin-proposal-decorators', {version: '2023-11'}],
        ],
        moduleId,
        sourceMaps: true,
    });

    fs.mkdirSync(outDir, {recursive: true});
    const filePart = rel.split('/').slice(-1)[0] + '.js';
    fs.writeFileSync(outFile, result.code + `\n//# sourceMappingURL=${filePart}.map ;`, 'utf-8');
    fs.writeFileSync(outFile + '.map', result.map.toString(), 'utf-8');
    xeroTranspiled++;
}

let count = result1.transpiled.length + result2.transpiled.length + qbTranspiled + xeroTranspiled;
let copiedCount = result1.copied.length + result2.copied.length;

console.log(`\n  transpiled: ${count}, copied: ${copiedCount}`)
