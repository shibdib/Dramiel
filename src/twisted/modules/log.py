logfile = open("/tmp/discord.db", "w")

def log(tbot, user, channel, msg):
	logfile.write("%s" % (msg))
	logfile.flush()
log.rule = ".*"
