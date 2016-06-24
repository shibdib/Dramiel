from datetime import datetime

def nextadventure():
    times = [
           (datetime(2012, 11, 12, 15),5,1,"2nd part of the Season 4 finale")
            ]
    r=map(lambda x:(x[0]-datetime.now(),x[1],x[2],x[3]), times)
    r=sorted(r)
    for x in r:
        if x[0].days>=0:
            return "%s until Series %d episode %d - %s!" % (str(x[0]).split(".")[0], x[1], x[2], x[3])
    return "OutOfAdventureException: no adventures are to be had in the future."

def adventure(tbot, user, channel, msg):
    tbot.msg(channel, nextadventure())
adventure.rule = "^!adventure(time)?$"
