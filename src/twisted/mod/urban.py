#!/usr/bin/env python
import urllib
import re, htmlentitydefs
from BeautifulSoup import BeautifulSoup

def unescape(text):
    def fixup(m):
        text = m.group(0)
        if text[:2] == "&#":
            # character reference
            try:
                if text[:3] == "&#x":
                    return unichr(int(text[3:-1], 16))
                else:
                    return unichr(int(text[2:-1]))
            except ValueError:
                pass
        else:
            # named entity
            try:
                text = unichr(htmlentitydefs.name2codepoint[text[1:-1]])
            except KeyError:
                pass
        return text # leave as is
    return re.sub("&#?\w+;", fixup, text)

def getdefinition(url):
   f=urllib.urlopen(url)
   soup=BeautifulSoup(f)
   result=str(soup.find("div", {"class" : "definition" }))
   if (result!="None"):
      removetags=re.compile("<(.|\n)*?>")
      return removetags.sub("",result)
   return False

def urbandictionary(tbot, user, channel, msg):
   article = " ".join(msg.split()[1:])
   url=("http://www.urbandictionary.com/define.php?term="+article)
   definition=getdefinition(url)
   if not definition:
      tbot.msg(channel, "%s: I'm afraid I cannot find an entry for that" % user)
   else:
      tbot.msg(channel, u"%s: %s" % (user, unescape(definition)))
urbandictionary.rule = '(!ud)([A-Za-z0-9 ,.-?:/]*)'

#if __name__ == '__main__':
#   print __doc__.strip()
