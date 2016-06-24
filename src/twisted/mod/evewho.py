#!/usr/bin/env python
from __future__ import print_function

import urllib
import re
import requests
import eveapi
import arrow
from collections import defaultdict

colours = defaultdict(str)
colours.update({
	"NORMAL": u"\u000f",
	"RED": u"\u000304",
	"GREEN": u"\u000309",
	"YELLOW": u"\u000308",
	"ORANGE": u"\u00037"
})

def formatsecstatus(data):
	data = "%.2f" % data
	value = float(data)
	if value < -5:
		return (colours["RED"], data)
	if value < 0:
		return (colours["ORANGE"], data)
	if value <= 4:
		return (colours["NORMAL"], data)
	if value > 4:
		return (colours["GREEN"], data)

def getdetailshash(name):
	api = eveapi.EVEAPIConnection()
	r = api.eve.CharacterID(names=name).characters
	assert(len(r)>0)
	id = r[0].characterID
	r = api.eve.CharacterInfo(characterID=id)
	return (id, r)

def getkbstats(id):
	ZKBAPI = "https://zkillboard.com/api/stats/characterID/%s/"
	r = requests.get(ZKBAPI % id)
	data = r.json()
	kills = data["totals"]["countDestroyed"]
	lost = data["totals"]["countLost"]
	return "["+colours["GREEN"]+str(kills)+colours["NORMAL"]+", "+colours["RED"]+str(lost)+colours["NORMAL"]+"]"

def who(tbot, user, channel, msg):
	if tbot.protocol=="xmpp":
		colours.clear()
	target = msg.replace("!who ", "")
	id, r = getdetailshash(target.strip())
	if hasattr(r, "securityStatus"):
		sec = formatsecstatus(r.securityStatus)
	else:
		sec = ("", "")
	kbstats = getkbstats(id)
	created = arrow.get(r.employmentHistory[-1].startDate).humanize()
	startDate = arrow.get(r.employmentHistory[0].startDate).humanize()

	if not hasattr(r, "alliance"):
		tbot.say(channel, "%s%s %s%s%s {%s} - %s (%s)" %  (sec[0], r.characterName, "["+sec[1]+"]", colours["NORMAL"], kbstats, created, r.corporation, startDate))
	else:
		message = "%s%s %s%s%s {%s} - %s (%s) - %s" % (sec[0], r.characterName, "["+sec[1]+"]", colours["NORMAL"], kbstats, created, r.corporation, startDate, r.alliance)
		tbot.say(channel, message)
who.rule="^!who "

if __name__ == "__main__":
	a = lambda x:x
	a.say = print
	a.protocol = "xmpp"
	who(a, None, None, "!who Unreal Blight")
