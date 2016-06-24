#!/usr/bin/python
# -*- coding: utf-8 -*-

u"""
Simple module that "flips" latin characters in a string to create an
"upside-down" impression. Makes extensive use of compatible latin characters
encoded in Unicode.

Support for diacritics offered through combining diacritical marks. Depends on
proper rendering though.

2008-2010 Christoph Burgmer (cburgmer@ira.uka.de)

            ˙ƎᴚⱯM⊥ᖵOS ƎH⊥ NI S⅁NIꞀⱯƎᗡ ᴚƎH⊥O ᴚO ƎS∩ ƎH⊥ ᴚO ƎᴚⱯM⊥ᖵOS ƎH⊥ H⊥IM
       NOI⊥ƆƎNNOƆ NI ᴚO ᖵO ⊥∩O 'WOᴚᖵ ⅁NISIᴚⱯ 'ƎSIMᴚƎH⊥O ᴚO ⊥ᴚO⊥ '⊥ƆⱯᴚ⊥NOƆ ᖵO
        NOI⊥ƆⱯ NⱯ NI ᴚƎH⊥ƎHM '⅄⊥IꞀIᗺⱯIꞀ ᴚƎH⊥O ᴚO SƎ⅁ⱯWⱯᗡ 'WIⱯꞀƆ ⅄NⱯ ᴚOᖵ ƎꞀᗺⱯIꞀ
       Ǝᗺ SᴚƎᗡꞀOH ⊥H⅁Iᴚ⅄ԀOƆ ᴚO SᴚOH⊥∩Ɐ ƎH⊥ ꞀꞀⱯHS ⊥NƎɅƎ ON NI ˙⊥NƎWƎ⅁NIᴚᖵNINON
                         ᗡNⱯ ƎSOԀᴚ∩Ԁ ᴚⱯꞀ∩ƆI⊥ᴚⱯԀ Ɐ ᴚOᖵ SSƎN⊥Iᖵ '⅄⊥IꞀIᗺⱯ⊥NⱯHƆᴚƎW
          ᖵO SƎI⊥NⱯᴚᴚⱯM ƎH⊥ O⊥ ᗡƎ⊥IWIꞀ ⊥ON ⊥∩ᗺ ⅁NIᗡ∩ꞀƆNI 'ᗡƎIꞀԀWI ᴚO SSƎᴚԀXƎ
             'ᗡNI⋊ ⅄NⱯ ᖵO ⅄⊥NⱯᴚᴚⱯM ⊥∩OH⊥IM '„SI SⱯ„ ᗡƎᗡIɅOᴚԀ SI ƎᴚⱯM⊥ᖵOS ƎH⊥

                   ˙ǝɹɐʍʇɟoS ǝɥʇ ɟo suoᴉʇɹod ꞁɐᴉʇuɐʇsqns ɹo sǝᴉdoɔ ꞁꞁɐ uᴉ pǝpnꞁɔuᴉ
                  ǝq ꞁꞁɐɥs ǝɔᴉʇou uoᴉssᴉɯɹǝd sᴉɥʇ puɐ ǝɔᴉʇou ʇɥƃᴉɹʎdoɔ ǝʌoqɐ ǝɥ⊥

                                                        :suoᴉʇᴉpuoɔ ƃuᴉʍoꞁꞁoɟ ǝɥʇ
           oʇ ʇɔǝɾqns 'os op oʇ pǝɥsᴉuɹnɟ sᴉ ǝɹɐʍʇɟoS ǝɥʇ ɯoɥʍ oʇ suosɹǝd ʇᴉɯɹǝd
                oʇ puɐ 'ǝɹɐʍʇɟoS ǝɥʇ ɟo sǝᴉdoɔ ꞁꞁǝs ɹo/puɐ 'ǝsuǝɔᴉꞁqns 'ǝʇnqᴉɹʇsᴉp
              'ɥsᴉꞁqnd 'ǝƃɹǝɯ 'ʎɟᴉpoɯ 'ʎdoɔ 'ǝsn oʇ sʇɥƃᴉɹ ǝɥʇ uoᴉʇɐʇᴉɯᴉꞁ ʇnoɥʇᴉʍ
              ƃuᴉpnꞁɔuᴉ 'uoᴉʇɔᴉɹʇsǝɹ ʇnoɥʇᴉʍ ǝɹɐʍʇɟoS ǝɥʇ uᴉ ꞁɐǝp oʇ '(„ǝɹɐʍʇɟoS„
                 ǝɥʇ) sǝꞁᴉɟ uoᴉʇɐʇuǝɯnɔop pǝʇɐᴉɔossɐ puɐ ǝɹɐʍʇɟos sᴉɥʇ ɟo ʎdoɔ ɐ
           ƃuᴉuᴉɐʇqo uosɹǝd ʎuɐ oʇ 'ǝƃɹɐɥɔ ɟo ǝǝɹɟ 'pǝʇuɐɹƃ ʎqǝɹǝɥ sᴉ uoᴉssᴉɯɹǝԀ

                                                                   ⊥IW :ǝsuǝɔᴉꞀ
"""

__all__ = ["transform"]
__version__ = '0.3'
__author__ = 'Christoph Burgmer <cburgmer@ira.uka.de>'
__url__ = 'http://github.com/cburgmer/upsidedown'
__license__ = 'MIT'

import unicodedata
import string

# Define dual character. Make sure that mapping is bijective.
FLIP_RANGES = [
    (string.ascii_lowercase, u"ɐqɔpǝɟƃɥᴉɾʞꞁɯuodbɹsʇnʌʍxʎz"),
    # alternatives: l:ʅ
    (string.ascii_uppercase, u"ⱯᗺƆᗡƎᖵ⅁HIᒋ⋊ꞀWNOԀꝹᴚS⊥∩ɅMX⅄Z"),
    # alternatives: L:ᒣ⅂, J:ſ, F:߃Ⅎ, A:∀ᗄ, U:Ⴖ, W:Ϻ, C:ϽↃ, Q:Ό, M:Ɯꟽ
    (string.digits, u"0ІᘔƐᔭ59Ɫ86"),
    (string.punctuation, u"¡„#$%⅋,)(*+'-˙/:؛>=<¿@]\\[ᵥ‾`}|{~"),
    ]
# See also http://www.fileformat.info/convert/text/upside-down-map.htm

# See:
# http://de.wikipedia.org/wiki/Unicode-Block_Kombinierende_diakritische_Zeichen
UNICODE_COMBINING_DIACRITICS = {u'̈': u'̤', u'̊': u'̥', u'́': u'̗', u'̀': u'̖',
    u'̇': u'̣', u'̃': u'̰', u'̄': u'̱', u'̂': u'̬', u'̆': u'̯', u'̌': u'̭',
    u'̑': u'̮', u'̍': u'̩'}

TRANSLITERATIONS = {u'ß': 'ss'}

# character lookup
_CHARLOOKUP = {}
for chars, flipped in FLIP_RANGES:
    _CHARLOOKUP.update(zip(chars, flipped))

# get reverse direction
for char in _CHARLOOKUP.copy():
    # make 1:1 back transformation possible
    assert (_CHARLOOKUP[char] not in _CHARLOOKUP
            or _CHARLOOKUP[_CHARLOOKUP[char]] == char), \
            ("%s has ambiguous mapping" % _CHARLOOKUP[char])
    _CHARLOOKUP[_CHARLOOKUP[char]] = char

# lookup for diacritical marks, reverse first
_DIACRITICSLOOKUP = dict((UNICODE_COMBINING_DIACRITICS[char], char)
                         for char in UNICODE_COMBINING_DIACRITICS)
_DIACRITICSLOOKUP.update(UNICODE_COMBINING_DIACRITICS)

def transform(input_str, transliterations=None):
    u"""
    Transform the string to "upside-down" writing.

    Example:

        >>> import upsidedown
        >>> print upsidedown.transform('Hello World!')
        ¡pꞁɹoM oꞁꞁǝH

    For languages with diacritics you might want to supply a transliteration to
    work around missing (rendering of) upside-down forms:
        >>> import upsidedown
        >>> print upsidedown.transform(u'köln', transliterations={u'ö': 'oe'})
        uꞁǝoʞ
    """
    transliterations = transliterations or TRANSLITERATIONS

    for character in transliterations:
        input_str = input_str.replace(character, transliterations[character])

    output = []
    for character in reversed(input_str):
        if character in _CHARLOOKUP:
            output.append(_CHARLOOKUP[character])
        else:
            char_normalised = unicodedata.normalize("NFD", character)

            for c in char_normalised:
                if c in _CHARLOOKUP:
                    char_normalised = char_normalised.replace(c, _CHARLOOKUP[c])
                elif c in _DIACRITICSLOOKUP:
                    char_normalised = char_normalised.replace(
                                                        c,
                                                        _DIACRITICSLOOKUP[c])

            output.append(unicodedata.normalize("NFC", char_normalised))

    return ''.join(output)

def main():
    """Main method for running upsidedown.py from the command line."""
    import sys
    import locale
    _, default_encoding = locale.getdefaultlocale()

    output = []
    line = sys.stdin.readline().decode(default_encoding)

    while line:
        line = line.strip("\n")
        output.append(transform(line).encode(default_encoding))

        line = sys.stdin.readline().decode(default_encoding)
    output.reverse()
    print "\n".join(output)

if __name__ == "__main__":
    main()
