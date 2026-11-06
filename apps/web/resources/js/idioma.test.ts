/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import { describe, expect, it } from 'vitest';
import { parse } from 'vue/compiler-sfc';

// Recorre las pantallas y junta los textos que ve el usuario: el de la plantilla,
// los atributos y las cadenas del código. Cada uno se revisa con dos listas de palabras.

const INGLES =
    /\b(the|your|you|to|and|of|for|with|is|are|be|this|that|an|or|not|can|will|have|has|enter|log|sign|password|email|save|saved|cancel|delete|settings|profile|appearance|account|manage|update|confirm|continue|back|dashboard|light|dark|system|enable|enabled|disable|disabled|recovery|codes?|verify|resend|authentication|passkeys?|remember|forgot|reset|name|new|add|remove|close|search|loading|success|failed|please|done|next|previous|show|hide|copy|copied|download|select|choose|required|optional|click|link|send|sent|welcome|already|create|register|security|platform|repository|documentation|toggle|navigation|sidebar|more|view|edit|user|users|secret|scan|setup|breadcrumb|added|last|used|e\.g)\b/i;
const ESPANOL =
    /[áéíóúñ¿¡]|\b(el|la|los|las|de|del|que|tu|tus|con|para|por|una|un|y|se|es|al|lo|su|sus|mi|sin|ya)\b/i;
const DE_ESPANA =
    /\b(ajustes|ordenador(es)?|m[oó]vil(es)?|ficheros?|pulsa|pulsar|pincha|coger|vosotros|añadir|añade)\b/i;

// Atributos técnicos: su valor nunca se lee en pantalla.
const TECNICOS = new Set([
    'class',
    'id',
    'type',
    'name',
    'autocomplete',
    'href',
    'to',
    'as',
    'variant',
    'size',
    'side',
    'align',
    'role',
    'tabindex',
    'for',
    'htmlFor',
    'method',
    'src',
    'inputmode',
    'key',
    'ref',
    'collapsible',
    'orientation',
    'rel',
    'target',
    'accept',
    'mode',
    'value',
    'default-value',
    'xmlns',
    'viewBox',
    'fill',
    'd',
    'stroke',
    'width',
    'height',
]);

const raiz = fileURLToPath(new URL('./', import.meta.url));
const GENERADOS = /^(routes|actions|wayfinder)\//;

interface Texto {
    donde: string;
    texto: string;
    // Las cadenas del código solo cuentan si parecen frase; las de la plantilla, siempre.
    delCodigo: boolean;
}

interface Nodo {
    type: number;
    content?: string | { content?: string };
    name?: string;
    value?: { content: string };
    exp?: { content: string };
    props?: Nodo[];
    children?: Nodo[];
    loc: { start: { line: number } };
}

function cadenas(
    codigo: string,
    archivo: string,
    primeraLinea: number,
): Texto[] {
    const fuente = ts.createSourceFile(
        archivo,
        codigo,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TS,
    );
    const salida: Texto[] = [];
    const visitar = (nodo: ts.Node): void => {
        // Las rutas de import y el nombre interno de createContext() no se ven en pantalla.
        if (
            ts.isImportDeclaration(nodo) ||
            ts.isExportDeclaration(nodo) ||
            (ts.isCallExpression(nodo) &&
                nodo.expression.getText() === 'createContext')
        ) {
            return;
        }

        let texto: string | null = null;

        if (
            ts.isStringLiteral(nodo) ||
            ts.isNoSubstitutionTemplateLiteral(nodo)
        ) {
            texto = nodo.text;
        } else if (ts.isTemplateExpression(nodo)) {
            texto = [
                nodo.head.text,
                ...nodo.templateSpans.map((s) => s.literal.text),
            ].join(' … ');
        }

        if (texto !== null) {
            const linea =
                primeraLinea +
                fuente.getLineAndCharacterOfPosition(nodo.getStart()).line;
            salida.push({
                donde: `${archivo}:${linea}`,
                texto,
                delCodigo: true,
            });
        }

        ts.forEachChild(nodo, visitar);
    };

    visitar(fuente);

    return salida;
}

function dePlantilla(nodo: Nodo, archivo: string): Texto[] {
    const salida: Texto[] = [];
    const donde = `${archivo}:${nodo.loc.start.line}`;

    if (nodo.type === 2 && typeof nodo.content === 'string') {
        salida.push({ donde, texto: nodo.content, delCodigo: false });
    }

    if (nodo.type === 5 && typeof nodo.content === 'object') {
        salida.push(
            ...cadenas(
                `(${nodo.content.content ?? ''})`,
                archivo,
                nodo.loc.start.line,
            ),
        );
    }

    for (const prop of nodo.props ?? []) {
        if (
            prop.type === 6 &&
            prop.value &&
            !TECNICOS.has(prop.name ?? '') &&
            !prop.name?.startsWith('data-')
        ) {
            salida.push({
                donde: `${archivo}:${prop.loc.start.line}`,
                texto: prop.value.content,
                delCodigo: false,
            });
        }

        if (
            prop.type === 7 &&
            prop.exp &&
            prop.name !== 'for' &&
            prop.name !== 'slot'
        ) {
            salida.push(
                ...cadenas(
                    `(${prop.exp.content})`,
                    archivo,
                    prop.loc.start.line,
                ),
            );
        }
    }

    for (const hijo of nodo.children ?? []) {
        salida.push(...dePlantilla(hijo, archivo));
    }

    return salida;
}

function textosDe(archivo: string): Texto[] {
    const fuente = readFileSync(`${raiz}${archivo}`, 'utf8');

    if (archivo.endsWith('.ts')) {
        return cadenas(fuente, archivo, 1);
    }

    const { descriptor } = parse(fuente, { filename: archivo });
    const salida = descriptor.template?.ast
        ? dePlantilla(descriptor.template.ast as unknown as Nodo, archivo)
        : [];

    for (const bloque of [descriptor.script, descriptor.scriptSetup]) {
        if (bloque) {
            salida.push(
                ...cadenas(bloque.content, archivo, bloque.loc.start.line),
            );
        }
    }

    return salida;
}

const pareceFrase = ({ texto, delCodigo }: Texto): boolean => {
    const limpio = texto.replace(/\s+/g, ' ').trim();

    if (
        !/[a-zA-Z]{2}/.test(limpio) ||
        /^(@\/|\.{1,2}\/|https?:|\/)/.test(limpio)
    ) {
        return false;
    }

    // Listas de clases de Tailwind: sin mayúsculas y casi todas con guion, dos puntos o corchetes.
    const partes = limpio.split(' ');

    if (
        !/[A-Z]/.test(limpio) &&
        partes.filter((p) => /[-:[\]/]/.test(p)).length >= partes.length / 2
    ) {
        return false;
    }

    return !delCodigo || /\s/.test(limpio) || /^[A-Z]/.test(limpio);
};

const textos = readdirSync(raiz, { recursive: true, encoding: 'utf8' })
    .filter(
        (a) =>
            /\.(vue|ts)$/.test(a) &&
            !/\.(test|d)\.ts$/.test(a) &&
            !GENERADOS.test(a),
    )
    .flatMap(textosDe)
    .filter(pareceFrase);

const conLista = (regla: (texto: string) => boolean) =>
    textos
        .filter(({ texto }) => regla(texto))
        .map(
            ({ donde, texto }) =>
                `${donde} ${JSON.stringify(texto.replace(/\s+/g, ' ').trim())}`,
        );

describe('pantallas en español de México', () => {
    it('encuentra los textos de las pantallas', () => {
        expect(textos.length).toBeGreaterThan(500);
    });

    it('no quedan textos en inglés', () => {
        expect(conLista((t) => INGLES.test(t) && !ESPANOL.test(t))).toEqual([]);
    });

    it('no usan palabras de España', () => {
        expect(conLista((t) => DE_ESPANA.test(t))).toEqual([]);
    });
});
