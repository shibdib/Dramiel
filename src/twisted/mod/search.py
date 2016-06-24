"""
search.py

It's like a search for google or something idk.
Whoa totally new for TwistedBot far out man!
"""
import urllib
import json
import re

GOOGLE_API = "http://ajax.googleapis.com/ajax/services/search/web"

def search_google(query):
    """
    Return the json from the google API as a dictionary
    """
    args = '?v=1.0&safe=off&q=' + urllib.quote(query.encode('utf-8'))
    data = urllib.urlopen(GOOGLE_API + args).read()
    return json.loads(data)

def first_result(query):
    """
    Return the first result on google depending on the keyword
    """
    results = search_google(query)
    if results['responseData']:
        result = results['responseData']['results'][0]
        return "%(titleNoFormatting)s -- %(url)s" % result
    else:
        return "Google is crying in the corner. I think you need to leave it alone."

def count_results(query):
    """
    Return the number of results
    """
    results = search_google(query)
    if results['responseData']:
        count = results['responseData']['cursor']['resultCount']
        return count
    else:
        return "Google's abacus is broken. Go abuse another bots features (try ~fishpuns!!!)"

def google_fight(fighter1, fighter2):
    """
    Returns the result of a google fight between two search results
    """
    try:
        fighter1_res = int(count_results(fighter1).replace(",", ""))
        fighter2_res = int(count_results(fighter2).replace(",", ""))
    except:
        return "Google has had enough of the fighting! Why can't you people get along?"
    res_tuple = (fighter1, fighter1_res, fighter2, fighter2_res)
    result = "%s = %s, %s = %s" % res_tuple
    if fighter1_res > fighter2_res:
        return "%s wins! (%s)" % (fighter1, result)
    elif fighter1_res < fighter2_res:
        return "%s wins! (%s)" % (fighter2, result)
    else:
        return "It was a draw! (%s)" % (result)

def g(tbot, user, channel, msg):
    message = msg.replace("!g", "", 1).strip()
    result = first_result(message)
    tbot.msg(channel, "%s: %s" % (user, result.encode("utf-8")))
g.rule = '^!g '

def gc(tbot, user, channel, msg):
    message = msg.replace("!gc", "", 1).strip()
    result = first_result(message)
    tbot.msg(channel, "%s: %s" % (user, result.encode("utf-8")))
gc.rule = '^!gc '

def gfight(tbot, user, channel, msg):
    regex = re.compile('TwistedBot: (.*?) vs (.*)')
    fighters = regex.match(msg).groups()
    result = google_fight(*fighters)
    tbot.msg(channel, "%s: %s" % (user, result.encode("utf-8")))
gfight.rule = 'TwistedBot: (.*?) vs (.*)'

if __name__ == '__main__':
    import sys
    if len(sys.argv) > 2:
        print google_fight(sys.argv[1], sys.argv[2])
    print first_result(len(sys.argv) > 1 and sys.argv[1] or "butts")
    print count_results(len(sys.argv) > 1 and sys.argv[1] or "butts")
