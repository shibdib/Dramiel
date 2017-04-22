import os
dir = os.path.dirname(__file__)
filename = os.path.join(dir, '..','..','..','jabberPings.db')
logfile = open(filename, "w")

def log(tbot, user, channel, msg):
	logfile.write("%s" % (msg))
	logfile.flush()
log.rule = ".*"
