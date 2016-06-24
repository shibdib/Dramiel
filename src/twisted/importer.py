#!/usr/bin/env python
import os, sys, imp
from logger import Logger
from types import FunctionType
import re
stripinternals = lambda x:x[0:2]!="__"

class Importer(object):
    loadedModules = []
    blacklist = []
    logger = None
    def __init__(self, logger, blacklist):
        self.logger = logger
        self.blacklist = blacklist
        self.functions = dict()
        self.userKicked = []
        self.joined = []
        self.main = []
        self.modefunctions = []
        self.topicfunctions = []
        for oldmodule in self.loadedModules:
            self.loadedModules.remove(oldmodule)
            del sys.modules[oldmodule.__name__]
        if self.loadedModules:
            del self.loadedModules
        self.loadedModules = []
        for file in os.listdir("modules/"):
            if file.endswith(".py"):
                self._import(file)
        self.logger.log("WARN", str(self.loadedModules))

    def _import(self,name):
        if name.split(".")[0] in self.blacklist:
            self.logger.log("INFO", "Skipping blacklisted module %s" % name)
            return
        self.logger.log("INFO", "Loading modules from %s" % name)
        mod = imp.load_source(name.split(".")[0], "modules/"+name)
        self.loadedModules.append(mod)
        d = dir(mod)
        d = filter(stripinternals, d)
        for item in d:
            member = getattr(mod, item)
            if not isinstance(member, FunctionType):
                next
            list = dir(member)
            list =  filter(stripinternals, list)
            if "rule" in list:
                rule = getattr(member, "rule")
                self.logger.log("GOOD", "privmsg: /%s/ -> %s" % (rule, member))
                rule = re.compile(rule)
                self.functions[rule] = member
            if "joined" in list:
                self.logger.log("GOOD", "joined: %s" % member)
                self.joined.append(member)
            if "userKicked" in list:
                self.logger.log("GOOD", "userKicked: %s" % member)
                self.userKicked.append(member)
            if "mainMethod" in list:
                self.logger.log("GOOD", "main: %s" % member)
                self.main.append(member)
            if "modeChanged" in list:
                self.logger.log("GOOD", "modeChanged: %s" % member)
                self.modefunctions.append(member)
            if "topicUpdated" in list:
                self.logger.log("GOOD", "topicUpdated: %s" % member)
                self.topicfunctions.append(member)
