#!/usr/bin/env python

import urllib
import re
import json
from BeautifulSoup import BeautifulStoneSoup
import sqlite3
import os

EVECENTRAL = "http://api.eve-central.com/api/marketstat?typeid=%s&minQ=1&&regionlimit=10000002"
EVESTATICDATADUMP = "~/eve.sqlite"

if os.path.isfile(os.path.expanduser(EVESTATICDATADUMP)):
	conn = sqlite3.connect(os.path.expanduser(EVESTATICDATADUMP))
else:
	conn = None

def get_type_id(name):
	c = conn.cursor()
	c.execute("select typeName, typeID from invTypes where typeName = '{0}%' collate nocase;".format(name))
	result = c.fetchone()
	if result:
		return r
	c.execute("select typeName, typeID from invTypes where typeName like '%{0}%' collate nocase;".format(name))
	results = c.fetchall()
	if len(results)==0:
		return None
	results = sorted(results, key=lambda x:len(x[0]))
	return results[0]

def itemtoprice(item):
	try:
		result = get_type_id(item)
		assert(result)
		item = result[0]
		url = EVECENTRAL % result[1]
		soup = BeautifulStoneSoup(urllib.urlopen(url))
		price = str(soup.find("sell").min)
		removetags=re.compile("<(.|\n)*?>")
		price = removetags.sub("",price)
		price = float(price)
		if price==0:
			raise ZeroDivisionError
		import locale
		locale.setlocale(locale.LC_ALL, "")
		formattedprice = locale.format('%d', price, True)
		return (item, formattedprice, price)
	except Exception as e:
		return (None, None, None)


def getprice(tbot, user, channel, msg):
	if not conn:
		tbot.msg(channel, "EVE static data dump not loaded")
		return
	msg=msg.encode("ascii").lower()
	term = msg.replace("!getprice ", "")
	try:
		item, formattedprice, price = itemtoprice(term)
		print "%s :  %s ISK" % (item, formattedprice)
		tbot.msg(channel, "%s :  %s ISK" % (item, formattedprice))
	except Exception as e:
		tbot.msg(channel, "Unable to find search item")
getprice.rule="^!getprice "

def getprices(tbot, user, channel, msg):
	if not conn:
		tbot.msg(channel, "EVE static data dump not loaded")
		return
	msg=msg.encode("ascii").lower()
	items = msg.replace("!getprices ", "")
	items = items.split(",")
	results = {}
	for item in items:
		item = item.strip()
		r = itemtoprice(item)
		print item, r
		if r[0] and r[1]:
			results[r[0]] = r[1]
	tbot.say(channel, "%s: %s" % (user, json.dumps(results)))
getprices.rule="^!getprices "

def getsetprice(tbot, user, channel, msg):
	if not conn:
		tbot.msg(channel, "EVE static data dump not loaded")
		return
	types = ["alpha", "beta", "gamma", "delta", "epsilon", "omega"]
	msg=msg.encode("ascii").lower()
	term = msg.replace("!getsetprice ", "")
	term = term.strip()
	terms = map(lambda x:term+" "+x, types)
	total = 0
	results = []
	for item in terms:
		r = itemtoprice(item)
		print item, r
		if r[0] and r[1]:
			results.append((r[0], r[1]))
			total = total + r[2]
	import locale
	locale.setlocale(locale.LC_ALL, "")
	ftotal = locale.format('%d', total, True)
	results.append(("total", ftotal))
	tbot.say(channel, "%s: %s" % (user, str(results)))
getsetprice.rule="^!getsetprice "

