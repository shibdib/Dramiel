#!/usr/bin/env python
import random
import re
import time
import datetime
import sqlite3
import os
import itertools
import requests
import eveapi
from bs4 import BeautifulSoup

conn = sqlite3.connect(os.path.expanduser("~/eve.sqlite"))
HOME = "PR-8CA"

def calc_jumps(system, start):
	url = "http://evemaps.dotlan.net/route/%s:%s" % (start, system)
	url = url.replace(" ", "_")
	print url
	r = requests.get(url)
	s = BeautifulSoup(r.text)
	try:
		links = s.find_all("a")
		systems = map(lambda y:y.text, filter(lambda x:x["href"].startswith("/system/"), links))
		systems = systems[0:systems.index(system)+1]
		print systems
		return systems
	except Exception as e:
		print e
		return None

def calc_mids(system, start, ship="Panther"):
	url = "http://evemaps.dotlan.net/jump/%s,544/%s:%s" % (ship, start, system)
	url = url.replace(" ", "_")
	print url
	r = requests.get(url)
	s = BeautifulSoup(r.text)
	try:
		links = s.find_all("a")
		systems = map(lambda y:y.text, filter(lambda x:x["href"].startswith("/system/"), links))
		systems = systems[0:systems.index(system)+1]
		return systems
	except Exception as e:
		print e
		return None

def complete(system):
	c = conn.cursor()
	c.execute("select mapSolarSystems.solarSystemName, mapRegions.regionName from mapSolarSystems, mapRegions where mapSolarSystems.solarSystemName like ? and mapSolarSystems.regionID = mapRegions.regionID", (system+"%", ))
	try:
		r = list(itertools.chain(*c.fetchall()))
		r = zip(r[::2], r[1::2])
		f = filter(lambda x:x[0].lower() == system.lower(), r)
		if f:
			return f
		if len(r)>5:
			return r[:5]
		return r
	except Exception as e:
		return []

def new_complete(system):
	c = conn.cursor()
	c.execute("select mapSolarSystems.solarSystemID, mapSolarSystems.solarSystemName, mapRegions.regionName from mapSolarSystems, mapRegions where mapSolarSystems.solarSystemName like ? and mapSolarSystems.regionID = mapRegions.regionID", (system+"%", ))
	try:
		r = list(itertools.chain(*c.fetchall()))
		print r
		r = zip(r[::3], r[1::3], r[2::3])
		print r
		f = filter(lambda x:x[1].lower() == system.lower(), r)
		print f
		if f:
			return f
		if len(r)>5:
			return r[:5]
		return r
	except Exception as e:
		print e
		return []


def rangefunc(tbot, user, channel, msg):
	system = msg.replace("!range ", "").strip()
	args = system.split(",")
	c = complete(args[0].strip())
	if len(args)>1:
		start = complete(args[1].strip())
	else:
		start = complete(HOME)
	if len(c)==0:
		tbot.msg(channel, "System not found")
	elif len(c)>1:
		tbot.msg(channel, "Please be more specific: " + " ".join(map(lambda x:"%s (%s)" % (x[0], x[1]), c)))
	elif len(start)==0:
		tbot.msg(channel, "System not found")
	elif len(start)>1:
		tbot.msg(channel, "Please be more specific: " + " ".join(map(lambda x:"%s (%s)" % (x[0], x[1]), start)))
	else:
		jumps = calc_jumps(c[0][0], start[0][0])
		blops_mids = calc_mids(c[0][0], start[0][0])
		carrier_mids = calc_mids(c[0][0], start[0][0], ship="Nidhoggur")
		if not jumps:
			tbot.msg(channel, "Invalid route")
		elif not blops_mids:
			tbot.msg(channel, "Distance from %s (%s) to %s (%s): %d gate jumps" % (start[0][0], start[0][1], c[0][0], c[0][1], len(jumps)-1))
		else:
			tbot.msg(channel, "Distance from %s (%s) to %s (%s): %d gate jumps, %d blops jumps or %d carrier jumps" % (start[0][0], start[0][1], c[0][0], c[0][1], len(jumps)-1, len(blops_mids)-1, len(carrier_mids)-1))
rangefunc.rule="^!range .*"

def zipit(resultset):
	results = []
	for row in resultset._rows:
		results.append(dict(zip(resultset._cols, row)))
	return results


def whereis(tbot, user, channel, msg):
	system = msg.replace("!where ", "").strip()
	system = new_complete(system)
	print system
	api = eveapi.EVEAPIConnection()

	if len(system)==0:
		tbot.msg(channel, "System not found")
	elif len(system)>1:
		tbot.msg(channel, "Please be more specific: " + " ".join(map(lambda x:"%s (%s)" % (x[1], x[2]), system)))
	data = zipit(api.Map.Kills().solarSystems)
	result = filter(lambda x:x["solarSystemID"] == system[0][0], data)
	print result
	if len(system)==1:
		if len(result):
			del result[0]["solarSystemID"]
			tbot.msg(channel, "%s (%s) - Last Hour: %d pods, %d players, %d npcs" % (system[0][1], system[0][2], result[0]["podKills"], result[0]["shipKills"], result[0]["factionKills"]))
		else:
			tbot.msg(channel, "%s (%s)" % (system[0][1], system[0][2]))


whereis.rule="^!where .*"
