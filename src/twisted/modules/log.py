logfile = open("/tmp/discord.db", "w")
logfile2 = open("/tmp/discord2.db", "w")
logfile3 = open("/tmp/discord3.db", "w")
logfile4 = open("/tmp/discord4.db", "w")

def log(tbot, user, channel, msg):
	logfile.write("%s" % (msg))
	logfile.flush()
	logfile2.write("%s" % (msg))
	logfile2.flush()
	logfile3.write("%s" % (msg))
	logfile3.flush()
	logfile4.write("%s" % (msg))
	logfile4.flush()
log.rule = ".*"
