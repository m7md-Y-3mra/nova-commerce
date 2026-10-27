#!/usr/bin/env python3
"""Fill a WordPress .pot with translations and write a .po file.

Usage: bin/po-fill.py <in.pot> <out.po> <strings.py> <THEME|PLUGIN>

<strings.py> defines dicts mapping msgid (or (singular, plural) tuples)
to Arabic msgstr (or a list of 6 plural forms). Untranslated entries keep
an empty msgstr so WordPress falls back to English. Compile with msgfmt -c.
"""
import re
import runpy
import sys

AR_PLURALS = ("nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : "
              "n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);")


def unquote(lines):
    return "".join(re.match(r'^(?:msgid_plural|msgid|msgctxt)?\s*"(.*)"$', l).group(1) for l in lines)


def quote(s):
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"') + '"'


def main():
    pot, out, strings_file, table = sys.argv[1:5]
    strings = runpy.run_path(strings_file)[table]
    blocks = open(pot, encoding="utf-8").read().strip().split("\n\n")
    result = []
    for block in blocks:
        lines = block.split("\n")
        keep, msgid, plural, cur = [], [], [], None
        for line in lines:
            if line.startswith("msgid_plural"):
                cur = plural
            elif line.startswith("msgid"):
                cur = msgid
            elif line.startswith("msgstr"):
                cur = None
                continue
            elif line.startswith('"') and cur is not None:
                pass
            else:
                keep.append(line)
                continue
            cur.append(line)
        mid = unquote(msgid).encode().decode("unicode_escape").encode("latin-1").decode("utf-8")
        mpl = unquote(plural).encode().decode("unicode_escape").encode("latin-1").decode("utf-8") if plural else None
        body = keep + msgid + plural
        if mid == "":  # header
            hdr = [l for l in keep if l.startswith("#")]
            result.append("\n".join(hdr + ['msgid ""', 'msgstr ""',
                '"Project-Id-Version: NOVA Store 1.0.0\\n"',
                '"MIME-Version: 1.0\\n"',
                '"Language-Team: Arabic\\n"',
                '"Content-Type: text/plain; charset=UTF-8\\n"',
                '"Content-Transfer-Encoding: 8bit\\n"',
                '"Language: ar\\n"',
                quote("Plural-Forms: " + AR_PLURALS + "\\n").replace("\\\\n", "\\n"),
                '"X-Domain: ' + ("nova-store" if table == "THEME" else "nova-store-core") + '\\n"']))
            continue
        if mpl is not None:
            forms = strings.get((mid, mpl), [""] * 6)
            body += ["msgstr[%d] %s" % (i, quote(f)) for i, f in enumerate(forms)]
        else:
            body.append("msgstr " + quote(strings.get(mid, "")))
        result.append("\n".join(body))
    open(out, "w", encoding="utf-8").write("\n\n".join(result) + "\n")


if __name__ == "__main__":
    main()
