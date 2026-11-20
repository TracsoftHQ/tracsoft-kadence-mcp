#!/usr/bin/env python3
"""Tracsoft: build the English translation of the plugin's Dutch strings.

Usage (from the repo root, needs GNU gettext: brew install gettext):

    python3 bin/build-translations.py extract
        Regenerates languages/mcp-abilities-kadence.pot from the PHP source and
        writes languages/untranslated.json: every source string that has no
        English translation yet in languages/en_US.json.

    python3 bin/build-translations.py build
        Checks languages/en_US.json (source string -> English) against the
        .pot, refuses to build when a string is missing or a printf
        placeholder differs, then writes the .po and compiles the .mo that the
        plugin loads.

languages/en_US.json is the file people edit. It is keyed by the exact Dutch
source string, so after an upstream merge only new or changed strings show up
in untranslated.json.
"""

import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, 'languages')
DOMAIN = 'mcp-abilities-kadence'
POT = os.path.join(LANG, DOMAIN + '.pot')
PO = os.path.join(LANG, DOMAIN + '-en_US.po')
MO = os.path.join(LANG, DOMAIN + '-en_US.mo')
MAP = os.path.join(LANG, 'en_US.json')
TODO = os.path.join(LANG, 'untranslated.json')

PLACEHOLDER = re.compile(r'%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]')


def php_files():
    out = []
    for base, dirs, files in os.walk(ROOT):
        rel = os.path.relpath(base, ROOT)
        if rel.split(os.sep)[0] in ('vendor', '.git', 'tests', 'dist'):
            continue
        out += [os.path.relpath(os.path.join(base, f), ROOT) for f in files if f.endswith('.php')]
    return sorted(out)


def unescape(s):
    return bytes(s, 'utf-8').decode('unicode_escape').encode('latin-1').decode('utf-8')


def escape(s):
    return (s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
             .replace('\t', '\\t').replace('\r', '\\r'))


def read_pot():
    text = open(POT, encoding='utf-8').read()
    ids = []
    for block in text.split('\n\n'):
        m = re.search(r'^msgid "(.*)"$', block, re.M)
        if m and m.group(1):
            ids.append(unescape(m.group(1)))
    return ids


def extract():
    subprocess.run(
        ['xgettext', '--language=PHP', '--from-code=UTF-8', '--keyword=__',
         '--keyword=esc_html__', '--keyword=esc_html_e', '--keyword=esc_attr__',
         '--keyword=esc_attr_e', '--add-comments=translators:', '--no-wrap',
         '--package-name=Tracsoft Kadence MCP', '-o', POT] + php_files(),
        cwd=ROOT, check=True)
    have = json.load(open(MAP, encoding='utf-8')) if os.path.exists(MAP) else {}
    todo = [s for s in read_pot() if s not in have]
    json.dump(todo, open(TODO, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'{len(todo)} strings need an English translation (see languages/untranslated.json).')


def build():
    have = json.load(open(MAP, encoding='utf-8'))
    ids = read_pot()
    problems = []
    for s in ids:
        if s not in have or not have[s].strip():
            problems.append(f'missing: {s[:80]}')
        elif sorted(PLACEHOLDER.findall(s)) != sorted(PLACEHOLDER.findall(have[s])):
            problems.append(f'placeholders differ: {s[:80]}')
    if problems:
        print('\n'.join(problems))
        sys.exit(f'{len(problems)} problem(s); nothing built.')
    lines = ['msgid ""', 'msgstr ""',
             '"Content-Type: text/plain; charset=UTF-8\\n"',
             '"Language: en_US\\n"',
             '"Project-Id-Version: Tracsoft Kadence MCP\\n"', '']
    for s in ids:
        lines += [f'msgid "{escape(s)}"', f'msgstr "{escape(have[s])}"', '']
    open(PO, 'w', encoding='utf-8').write('\n'.join(lines))
    subprocess.run(['msgfmt', '--check-format', '-o', MO, PO], check=True)
    print(f'Built {os.path.relpath(MO, ROOT)} with {len(ids)} strings.')


if __name__ == '__main__':
    {'extract': extract, 'build': build}.get(sys.argv[1] if len(sys.argv) > 1 else '', lambda: sys.exit(__doc__))()
