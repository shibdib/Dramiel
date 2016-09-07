# -*- coding: utf-8 -*-
import re

LATIN_LIKE_SCRIPTS = ['Latin', 'Common', 'Cyrillic', 'Greek', 'Hebrew']

def readScriptRanges(scripts=None):
    """
    Read script ranges from http://unicode.org/Public/UNIDATA/Scripts.txt file.
    """
    scripts = scripts or LATIN_LIKE_SCRIPTS
    ranges = []

    f = open('Scripts.txt', 'r')
    for line in f:
        line = line.strip('\n')
        matchObj = re.match(
            '^([0123456789ABCDEF]{4}(\.\.[0123456789ABCDEF]{4})?)\s*;\s+(%s)\s+(#.*)?$'
                % '|'.join(scripts),
            line)
        if matchObj:
            entry = matchObj.group(1)
            if len(entry) > 4:
                start, stop = entry.split('..', 1)
                ranges.append((start, stop))
            else:
                ranges.append(entry)
    f.close()

    return ranges

class CharacterRangeIterator(object):
    """Iterates over a given set of codepoint ranges given in hex."""
    def __init__(self, ranges):
        self.ranges = ranges[:]
        self._curRange = self._popRange()
    def _popRange(self):
        if self.ranges:
            charRange = self.ranges[0]
            del self.ranges[0]
            if type(charRange) == type(()):
                rangeFrom, rangeTo = charRange
            else:
                rangeFrom, rangeTo = (charRange, charRange)
            return (int(rangeFrom, 16), int(rangeTo, 16))
        else:
            return []
    def __iter__(self):
        return self
    def __next__(self):
        if not self._curRange:
            raise StopIteration

        curIndex, toIndex = self._curRange
        if curIndex < toIndex:
            self._curRange = (curIndex + 1, toIndex)
        else:
            self._curRange = self._popRange()
        return chr(curIndex)
