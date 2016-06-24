import urllib2, re
from BeautifulSoup import BeautifulSoup

removetags=re.compile("<(.|\n)*?>")
collapsespaces=re.compile(" +")

def gettitle(url):
    response = urllib2.urlopen(url)
    data = response.read()
    soup = BeautifulSoup(data)
    result = unicode(soup.find("title"))
    result = removetags.sub("",result)
    result = result.replace("\n", "")
    result = collapsespaces.sub(" ", result)
    result = result.strip()
    return result

def title(tbot, user, channel, msg):
    msg = msg.split(" ")
    if len(msg)==1:
        if channel in tbot.storedlinks:
            tbot.say(channel, "%s: %s" % (user, gettitle(tbot.storedlinks[channel]).encode("utf-8")))
        else:
            tbot.say(channel, "%s: I do not have a stored link for %s." % (user, channel))
    else:
        for m in msg:
            if re.compile("^https?://.*").match(m):
                tbot.say(channel, "%s: %s" % (user, gettitle(m).encode("utf-8")))
title.rule = "^!title"

def storelink(tbot, user, channel, msg):
    if not hasattr(tbot, "storedlinks"):
        tbot.storedlinks = dict()
    msg = msg.split(" ")
    for m in msg:
        if re.compile("^https?://.*").match(m):
            tbot.storedlinks[channel] = m
storelink.rule = ".*https?://.*"
