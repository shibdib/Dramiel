import urllib2, re, json
from BeautifulSoup import BeautifulSoup

removetags=re.compile("<(.|\n)*?>")
collapsespaces=re.compile(" +")
removecites=re.compile("\[.*\]")

def getsummary(target):
    data = urllib2.urlopen("http://en.wikipedia.org/w/api.php?action=opensearch&search=%s&format=json" % target).read()
    j = json.loads(data)
    topresult = j[1][0]
    page = urllib2.urlopen(urllib2.Request("http://en.wikipedia.org/wiki/%s" % topresult, headers={"User-Agent":"Mozilla/5.0 (X11; U; Linux i686) Gecko/20071127 Firefox/2.0.0.11"})).read()
    page = unicode(page, "utf-8")
    soup = BeautifulSoup(page, fromEncoding="utf-8")
    results = soup.findAll("div", { "class":"mw-content-ltr" })
    r = str(results[0]('p')[0])
    r = re.sub(removetags, "", r)
    r = re.sub(removecites, "", r)
    return r

def title(tbot, user, channel, msg):
    msg = msg.replace("!wik", "").strip()
    msg = msg.replace(" ", "+")
    try:
        summary=getsummary(msg)
        tbot.say(channel, summary)
    except Exception as e:
        tbot.say(channel, "%s: %s" % (user, "No results found."))
        raise
title.rule = "^!wik"
