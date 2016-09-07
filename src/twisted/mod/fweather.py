#!/usr/bin/env python
import urllib
from BeautifulSoup import BeautifulSoup

import re, htmlentitydefs

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

def fucking_weather(place):
   url = "http://www.thefuckingweather.com/?where=%s&unit=c" % place
   f=urllib.urlopen(url)
   soup=BeautifulSoup(f)
   result=str(soup.find("p", {"class" : "large" }))
   remark=str(soup.find("p", {"class" : "remark"}))
   if (remark!="None"):
       result=result+" "+remark
   if (result!="None"):
      removetags=re.compile("<(.|\n)*?>")
      return unescape(removetags.sub("",result)).replace('\n', ' ')
   return ""

def fweather(tbot, user, channel, msg):
   message=msg.replace("!fweather","").strip()
   if len(message):
      tbot.logger.log("WARN", message)
      text=fucking_weather(message)
   else:
      tbot.logger.log("WARN", "falling back to Aberystwyth")
      text=fucking_weather("Aberystwyth")
   if (len(text.strip())==0):
       text = "WHERE THE FUCK IS THAT?"
   message=user+": "+text
   tbot.msg(channel, message.encode("utf-8"))
fweather.rule = '^!fweather'


if __name__ == '__main__': 
   import sys
   print fucking_weather(len(sys.argv) > 1 and sys.argv[1] or "Aberystwyth");
