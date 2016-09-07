def eventwatcher(tbot, user, channel, set, modes, args):
    tbot.logger.log("INFO", "user: %s, channel: %s, set: %s, modes: %s, args: %s" % (user, channel, set, modes, args))
    if (modes=="o") and (set==True):
        for person in args:
            tbot.logger.log("INFO", "Looking up %s" % person)
            tbot.logger.log("INFO", "%s got opped in channel" % tbot.whois(person))
eventwatcher.modeChanged = True
