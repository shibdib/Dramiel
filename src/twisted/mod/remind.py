import datetime

def checkreminders(tbot):
    if not hasattr(tbot, "reminders"):
        tbot.reminders = dict()
    for time in tbot.reminders.keys():
        if time<datetime.datetime.now():
            (user, channel, msg) = tbot.reminders[time]
            tbot.msg(channel, "%s: %s" % (user, msg))
            del(tbot.reminders[time])
checkreminders.mainMethod=True

def remind(tbot, user, channel, msg):
    time = int(msg.split()[1])
    time = datetime.datetime.now()+datetime.timedelta(minutes=time)
    tbot.reminders[time] = (user, channel, " ".join(msg.split()[2:]))
    tbot.msg(channel,"%s: I have set a reminder for you at %s" % (user, time.strftime("%Y-%m-%d %H:%M:%S")))
remind.rule="^!remind "

def remindtarget(tbot, user, channel, msg):
    person = msg.split()[1]
    tchannel = msg.split()[2]
    time = int(msg.split()[3])
    time = datetime.datetime.now()+datetime.timedelta(minutes=time)
    tbot.reminders[time] = (person, tchannel, " ".join(msg.split()[4:]))
    tbot.msg(channel,"%s: I have set a reminder for %s in %s at %s" % (user, person, tchannel, time.strftime("%Y-%m-%d %H:%M:%S")))
remindtarget.rule="^!remindtarget"
