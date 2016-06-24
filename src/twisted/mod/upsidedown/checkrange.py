#!/usr/bin/python
# -*- coding: utf-8 -*-

"""
Find all characters in Latin like scripts that are flagged REVERSED or TURNED.

Example::
    $ python3 findchar.py Q
    'ℚ' ℚ DOUBLE-STRUCK CAPITAL Q
    '℺' ℺ ROTATED CAPITAL Q
    '⒬' ⒬ PARENTHESIZED LATIN SMALL LETTER Q
    'Ⓠ' Ⓠ CIRCLED LATIN CAPITAL LETTER Q
    'ⓠ' ⓠ CIRCLED LATIN SMALL LETTER Q
    ...

Needs Python3.

You need the Scripts.txt file from Unicode::
    $ wget http://unicode.org/Public/UNIDATA/Scripts.txt
"""
import unicodedata
from util import readScriptRanges, CharacterRangeIterator

ranges = readScriptRanges()
for c in CharacterRangeIterator(ranges):
    try:
        name = unicodedata.name(c)
    except ValueError:
        continue
    #if not 'CAPITAL' in name:
        #continue
    if 'REVERSED' in name or 'TURNED' in name:
        print(repr(c), c, name)
