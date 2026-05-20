import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as acorn from 'acorn';
import * as walk from 'acorn-walk';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SOURCE_PATH = resolve(__dirname, '../../../assets/js/swiper-init.js');

let cachedSource = null;
let cachedAst = null;

function loadAst() {
    if (cachedAst) {
        return { source: cachedSource, ast: cachedAst };
    }
    cachedSource = readFileSync(SOURCE_PATH, 'utf8');
    cachedAst = acorn.parse(cachedSource, {
        ecmaVersion: 'latest',
        sourceType: 'script',
        locations: false,
    });
    return { source: cachedSource, ast: cachedAst };
}

function findFunctionSources(names) {
    const { source, ast } = loadAst();
    const wanted = new Set(names);
    const found = new Map();

    walk.simple(ast, {
        FunctionDeclaration(node) {
            if (node.id && wanted.has(node.id.name) && !found.has(node.id.name)) {
                found.set(node.id.name, source.slice(node.start, node.end));
            }
        },
    });

    for (const name of names) {
        if (!found.has(name)) {
            throw new Error(`Helper "${name}" not found in swiper-init.js`);
        }
    }

    return names.map((name) => found.get(name));
}

/**
 * Load named helper functions from assets/js/swiper-init.js without modifying
 * the production file. Functions are extracted from the IIFE by AST parsing
 * and re-evaluated inside an isolated scope where outer dependencies ($,
 * jzsaAjax, etc.) can be injected as stubs.
 *
 * @param {string[]} names         Helper function names to extract, in dependency order.
 * @param {object}   [scope]       Optional outer-scope bindings (e.g. { $: stub, jzsaAjax: {...} }).
 * @returns {object}               Map of helper name → callable function.
 */
export function loadHelpers(names, scope = {}) {
    const sources = findFunctionSources(names);
    const scopeKeys = Object.keys(scope);
    const scopeValues = scopeKeys.map((key) => scope[key]);

    const body = [
        '"use strict";',
        ...sources,
        `return { ${names.join(', ')} };`,
    ].join('\n');

    // eslint-disable-next-line no-new-func
    const factory = new Function(...scopeKeys, body);
    return factory(...scopeValues);
}
