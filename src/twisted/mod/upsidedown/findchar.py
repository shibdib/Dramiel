#!/usr/bin/python
# -*- coding: utf-8 -*-

"""
Find a character forms for a given character in a range of Unicode scripts.

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
import re
import sys
from util import readScriptRanges, CharacterRangeIterator

char = sys.argv[1]

ranges = readScriptRanges()
for c in CharacterRangeIterator(ranges):
    try:
        name = unicodedata.name(c)
    except ValueError:
        continue
    if re.match('(%(c)s$|%(c)s |.* %(c)s$|.* %(c)s )' % {'c': char}, name):
        print(repr(c), c, name)
