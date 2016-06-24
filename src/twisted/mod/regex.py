import re
import datetime

def sub(message, regex):
    regex=re.split("(?<!\\\\)/",regex)
    if len(regex)>3:
        regex[3] = regex[3].strip()
        if not regex[3]:
            count = 1
        elif "g" in regex[3]:
            count = 0
        elif regex[3].isdigit():
            count = int(regex[3])
    else:
        count = 1
    return re.sub(regex[1], regex[2], message, count)

def substitute(tbot, user, channel, msg):
    if user in tbot.messages:
        newmessage = sub(tbot.messages[user], msg)
        if newmessage != tbot.messages[user]:
            tbot.messages[user] = newmessage
            tbot.msg(channel, "<%s> %s" % (user, newmessage))
    else:
        tbot.msg(channel, "Uh %s... you haven't said anything yet" % user)
substitute.rule="^s\/.*"

def directedsubstitute(tbot, user, channel, msg):
    (target, regex) = re.compile("^(.*?): (.*)").match(msg).groups()
    if target in tbot.messages:
        newmessage = sub(tbot.messages[target], regex)
        if newmessage != tbot.messages[target]:
            tbot.messages[target] = newmessage
            tbot.msg(channel, "%s thinks %s meant: %s" % (user, target, newmessage))
    else:
        tbot.msg(channel, "%s: %s doesn't exist! You don't have to correct them!" % (user, target))
directedsubstitute.rule="^.*?: s/.*"

def lastmsg(tbot, user, channel, msg):
    msg = msg.split()
    if len(msg)>1 and msg[1] in tbot.messages:
        tbot.msg(channel, "%s: I last saw %s say: %s" % (user, msg[1], tbot.messages[msg[1]]))
lastmsg.rule="^!lastmsg"

def seen(tbot, user, channel, msg):
    msg = msg.split()
    if len(msg)>1 and msg[1] in tbot.seen:
        tbot.msg(channel, tbot.seen[msg[1]])
seen.rule="^!seen"

def storemessage(tbot, user, channel, msg):
    if not hasattr(tbot, "seen"):
        tbot.seen = dict()
    if not hasattr(tbot, "messages"):
        tbot.messages = dict()
    if not msg.startswith("s/"):
        tbot.messages[user]=msg
    tbot.seen[user]= "I last saw %s at %s in %s. " % (user,datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"), channel)
storemessage.rule=".*"
