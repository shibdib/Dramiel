
def voteyes(tbot, user, channel, msg):
    if channel not in tbot.voteyes:
        tbot.msg(channel, "%s: there is not currently a vote in %s" % (user, channel))
    else:
        if (user not in tbot.voteyes[channel]) and (user not in tbot.voteno[channel]):
            tbot.voteyes[channel].append(user)
        else:
            tbot.msg(channel, "%s: you cannot vote twice" % user)
voteyes.rule="^!yes"

def voteno(tbot, user, channel, msg):
    if channel not in tbot.voteno:
        tbot.msg(channel, "%s: there is not currently a vote in %s" % (user, channel))
    else:
        if (user not in tbot.voteno[channel]) and (user not in tbot.voteyes[channel]):
            tbot.voteno[channel].append(user)
        else:
            tbot.msg(channel, "%s: you cannot vote twice" % user)
voteno.rule="^!no"

def finishvote(tbot, channel):
    tbot.voteyes[channel] = len(tbot.voteyes[channel])
    tbot.voteno[channel] = len(tbot.voteno[channel])
    tbot.msg(channel, "Vote finished: %s for, %s against" % (tbot.voteyes[channel], tbot.voteno[channel]))
    if tbot.voteyes[channel]>tbot.voteno[channel]:
        tbot.msg(channel, "Motion carried: %s" % tbot.vote[channel])
    else:
        tbot.msg(channel, "Motion denied: %s" % tbot.vote[channel])
    del tbot.vote[channel]
    del tbot.voteno[channel]
    del tbot.voteyes[channel]

def startvote(tbot, user, channel, msg):
    if not hasattr(tbot, "voteno"):
        tbot.voteno = {}
    if not hasattr(tbot, "voteyes"):
        tbot.voteyes = {}
    if not hasattr(tbot, "vote"):
        tbot.vote = {}
    if channel not in tbot.vote:
	    subject = msg.replace("!vote", "").strip()
	    tbot.voteno[channel] = []
	    tbot.voteyes[channel] = []
	    tbot.vote[channel] = subject
	    tbot.say(channel, "Starting a vote on: %s, you have 30 seconds to get your votes in with !yes or !no." % subject)
	    tbot.reactor.callLater(30, finishvote, tbot, channel)
    else:
	    tbot.say(channel, "Please wait until the current vote finishes")
startvote.rule="^!vote"

