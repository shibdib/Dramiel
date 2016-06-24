#!/usr/bin/env python

import urllib
import re
import json
import sqlite3
import os

from bulbs.model import Node, Relationship
from bulbs.property import String, Integer, DateTime, Float
from bulbs.utils import current_datetime

class System(Node):
	element_type = "system"
	id = Integer()
	name = String(nullable=False)
	region = String(nullable=False)
	security = Float()

class IsConnectedTo(Relationship):
	label = "is_connected_to"


conn = sqlite3.connect(os.path.expanduser("~/eve.db"))
conn.row_factory = sqlite3.Row

# populate graph
from bulbs.neo4jserver import Graph, Config, NEO4J_URI

g = Graph(Config(NEO4J_URI, "neo4j", "key"))
g.add_proxy("system", System)
g.add_proxy("is_connected_to", IsConnectedTo)


systems = {}
for item in g.V[1::]:
	systems[item.get("id")] = item

def id_to_name(i):
	c = conn.cursor()
	c.execute("select solarSystemName from mapSolarSystems where solarSystemID=?", (i, ))
	return c.fetchone()[0]

def distance_between(f, t):
	print f, t
	c = conn.cursor()
	c.execute("select solarSystemID from mapSolarSystems where solarSystemName like ? collate nocase", (f+'%', ))
	f = systems[c.fetchone()[0]].eid
	c = conn.cursor()
	print systems[t].get("name")
	t = systems[t].eid
	print f, t
	try:
		return int(g.cypher.execute("START s=node(%d), e=node(%d)\n MATCH p=shortestPath(s-[*..250]->e)\n return length(p)" % (f, t)).one().raw)
	except StopIteration:
		import sys
		return sys.maxint

# c = conn.cursor()
# c.row_factory = sqlite3.Row
#c = conn.execute("select * from mapSolarSystems, mapRegions where mapSolarSystems.regionID = mapRegions.regionID;")
#systems = {}
#limit=6996
#count=0
#for item in c.fetchall():
#	count = count + 1
#	if count<limit:
#		continue
#	systems[item["solarSystemID"]] = g.system.create(id=item["solarSystemID"], name=item["solarSystemName"], region=item["regionName"], security=item["security"])
#
#	print systems[item["solarSystemID"]]

# limit=12248
#count=0
#c = conn.execute("select * from mapSolarSystemJumps;")
#for item in c.fetchall():
#	count=count+1
#	if count<limit:
#		continue
#	f = systems[item["fromSolarSystemID"]]
#	t = systems[item["toSolarSystemID"]]
#	relationship = g.is_connected_to.create(f, t)
#	print relationship


import requests

def thera(tbot, user, channel, msg):
	msg=msg.encode("ascii").lower()
	system = msg.replace("!thera", "").strip()
	theraholes = requests.get("http://www.eve-scout.com/api/wormholes").json()
	systems = filter(lambda x:"wormholeDestinationSolarSystemId" in x, theraholes)
	indexedtheraholes = dict(zip(map(lambda x:x["wormholeDestinationSolarSystemId"], systems), systems))
	systems = map(lambda x:x["wormholeDestinationSolarSystemId"], systems)
	results = map(lambda x:distance_between(system, x), systems)
	indexedsystems = dict(zip(map(id_to_name, systems), systems))
	systems = map(id_to_name, systems)
	results = zip(systems, results)
	sorted_results = sorted(results, key=lambda x:x[1])
	winner = sorted_results[1]
	winnersystemid = indexedsystems[winner[0]]
	winnerdata = indexedtheraholes[winnersystemid]
	print str("%s: nearest route to Thera is via the hole [%s] in %s (%s) located %d jumps away" % (user, winnerdata["signatureId"], winner[0], winnerdata["destinationSolarSystem"]["region"]["name"], winner[1]))
	tbot.msg(channel, str("%s: nearest route to Thera is via the hole [%s] in %s (%s) located %d jumps away" % (user, winnerdata["signatureId"], winner[0], winnerdata["destinationSolarSystem"]["region"]["name"], winner[1])))
thera.rule="^!thera"
