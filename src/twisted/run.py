#!/usr/bin/env python
import sys
import getopt
from logger import *
from cgi import escape
from config import Config
from importer import Importer
from twisted.internet import reactor
from twisted.web.server import Site
from twisted.web.resource import Resource

def showhelp(exitcode):
	print """TwistedBot
 -h, --help		 show this help dialogue
 -v [0-9]		   set verbosity of logging
 -c config.yaml	 set configuration file
	"""
	sys.exit(exitcode)

def main():
	try:
		opts, args = getopt.getopt(sys.argv[1:], "hc:v:", ["help", "config=", "verbosity="])
	except getopt.GetoptError, err:
		print >> sys.stderr, err
		showhelp(2)
	verbosity = False
	config = "config.yaml"
	for setting, value in opts:
		if setting=="-v":
			verbosity = int(value)
		if setting=="-c":
			config = value
		if setting in ["-h", "--help"]:
			showhelp(0)
	c = Config(config)
	settings = c.parse()
	if settings == False:
		print >> sys.stderr, "Configuration file not found, Please give a valid configuration file"
		showhelp(1)
	if verbosity != False:
		settings["verbosity"] = verbosity
	if "verbosity" not in settings:
		settings["verbosity"] = 0


	#Pick the bot engine
	if ("protocol" not in settings) or (settings["protocol"] == "irc"):
		from bot import *
		#Set up the logging
		logger = Logger(settings["verbosity"])
		#Set up the Web Server
		r = Resource()
		r.putChild('', logReader(logger))
		webFactory = Site(r)
		reactor.listenTCP(8888, webFactory)
		#Set up the IRC Bot
		BotFactory =  TwistedBotFactory(settings, config, logger, reactor)
		BotFactory.logger = logger
		reactor.connectTCP(settings["network"], 6667, TwistedBotFactory(settings, config, logger, reactor))
		reactor.suggestThreadPoolSize(10)
		tbot.protocol = settings["protocol"]
		reactor.run()
	elif (settings["protocol"] == "xmpp"):
		from jabberbot import *
		logger = Logger(2)
		importer = Importer(logger, settings["moduleblacklist"])
		me = "%s@%s" % (settings["username"], settings["network"])
		j = jid.JID(me)
		p = settings["password"]
		# Set up the jabber bot
		factory = client.XMPPClientFactory(j,p)
		tbot = TwistedJabberBot(logger)
		tbot.functions = importer.functions
		factory.addBootstrap('//event/stream/authd',tbot.authd)
		reactor.connectTCP(settings["server"], 5222, factory)
		for channel in settings["channels"]:
        		reactor.callLater(4, tbot.join, "%s/%s" % (channel, settings["nickname"]))
		tbot.protocol = settings["protocol"]
		# run
		reactor.run()

if __name__ == "__main__":
	main()
