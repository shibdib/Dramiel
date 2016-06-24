from twisted.words.protocols import irc
from twisted.internet import protocol
from twisted.internet import task
from importer import Importer
from logger import Logger
from config import Config
import re, sys

class TwistedBot(irc.IRCClient):
    versionName = "TwistedBot"
    versionNum = "v0.2"
    sourceURL = "https://bitbucket.org/Sylnai/twistedbot/"

    def loadModules(self, clear=False):
        if clear:
            self.logger.log("WARN", "Clearing out old modules")
            self.functions.clear
            self.joinedFunctions = []
            self.userKicked = []
            self.main = []
            self.topicfunctions = []
        self.logger.log("WARN", "Loading modules")
        i = Importer(self.logger, self.moduleblacklist)
        self.functions = i.functions
        self.joinedFunctions = i.joined
        self.userKicked = i.userKicked
        self.main = i.main
        self.modefunctions = i.modefunctions
        self.topicfunctions = i.topicfunctions

    def init(self):
        self.loadModules()
        self.logger.log("INFO", "Starting main loop")
        if "main" in dir(self):
            l = task.LoopingCall(self.mainloops)
            l.start(2, now=False)

    def kickedFrom(self, channel, kicker, message):
        self.logger.log("WARN","Kicked from %s by %s with message %s" % (channel, kicker, message))

    def userJoined(self, user, channel):
        self.logger.log("INFO","%s joined %s" % (user, channel))

    def userKicked(self, kickee, channel, kicker, message):
        self.logger.log("WARN","%s got kicked from %s by %s with message %s" % (kickee, channel, kicker, message))
        if "userKickedFunctions" in dir(self):
            for f in self.userKickedFunctions:
                f(self, kickee, channel, kicker, message)

    def signedOn(self):
        self.logger.log("GOOD","Signed on as %s." % (self.nickname))
        for channel in self.channels:
            self.join(str(channel))
        
    def joined(self, channel):
        self.logger.log("GOOD","Joined %s." % (channel))
        if channel not in self.channels:
            self.channels.append(channel)
        if "joinedFunctions" in dir(self):
            for j in self.joinedFunctions:
                j(self, channel)

    def left(self, channel):
        self.logger.log("GOOD", "left %s." % (channel))
        if channel in self.channels:
            self.channels.remove(channel)

    def privmsg(self, user, channel, msg):
        user = user.split("!")[0]
        if "functions" in dir(self):
            for r in self.functions.keys():
                if r.match(msg):
                    try:
                        self.functions[r](self, user, channel, msg)
                        self.logger.log("INFO","Launched: %s" % self.functions[r])
                    except Exception as e:
                        self.logger.log("ERROR","Error when launching %s:" % self.functions[r])
                        self.msg(channel, "%s: %s" % (type(e), e))
                        raise
            self.logger.log("OKAY","%s: <%s> %s" % (channel,user,msg))

    def say(self, channel, message, length = None):
        if isinstance(message, unicode):
            message=message.encode("utf-8")
        #hand off to normal msg function
        self.msg(channel, message, length)

    def mainloops(self):
        #self.logger.log("INFO", "Doing main loop")
        for m in self.main:
            m(self)

    def modeChanged(self, user, channel, set, modes, args):
        for m in self.modefunctions:
            m(self, user, channel, set, modes, args)
            self.logger.log("INFO", "Launched %s" % m)

    def topicUpdated(self, user, channel, newTopic):
        for m in self.topicfunctions:
            m(self, user, channel, newTopic)
            self.logger.log("INFO", "Launched %s" % m)

class TwistedBotFactory(protocol.ClientFactory):
    protocol = TwistedBot

    def __init__(self, settings, config, logger, reactor):
        self.settings = settings
        self.config = Config(config) 
        self.logger = logger
        self.reactor = reactor
        self.logger.log("INFO", "Factory created")

    def buildProtocol(self, addr):
        self.logger.log("INFO", "Building an instance of %s" % self.protocol)
        p = self.protocol()
        p.factory = self
        p.logger = self.logger
        p.config = self.config
        p.reactor = self.reactor
        #Migrate settings
        for key in self.settings.keys():
            setattr(p, key, self.settings[key])
        p.config = self.config
        p.init()
        return p
    
    def startedConnecting(self, connector):
        self.logger.log("INFO", "Attempting to init new client")

    def clientConnectionLost(self, connector, reason):
        print "Lost connection (%s), reconnecting." % (reason)
        connector.connect()

    def clientConnectionFailed(self, connector, reason):
        print "Could not connect: %s" % (reason)
