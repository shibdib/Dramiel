from datetime import datetime

def nextponies():
    times = [
            (datetime(2011, 11, 12, 15),2,6,"The Cutie Pox"),
            (datetime(2011, 11, 19, 15),2,7,"May the Best Pet Win!"),
            (datetime(2011, 11, 26, 15),2,8,"The Mysterious mare do Well"),
            (datetime(2011, 12, 3,  15),2,9,"Sweet and Elite"),
            (datetime(2011, 12, 10, 15),2,10,"Secret of My Excess"),
            (datetime(2011, 12, 17, 15),2,11,"Hearth's Warming Eve"),
            (datetime(2012,  1, 7,  15),2,12,"Family Appreciation Day"),
            (datetime(2012,  1, 14, 15),2,13,"Baby Cakes"),
            (datetime(2012,  1, 21, 15),2,14,"The last Roundup"),
            (datetime(2012,  1, 28, 15),2,15,"The Super Speedy Cider Squeezy 6000"),
            (datetime(2012,  2, 4,  15),2,16,"Read It and Weep"),
            
            (datetime(2012, 11, 10, 15),3,1,"The Crystal Empire, Part 1"),
            (datetime(2012, 11, 10, 16),3,2,"The Crystal Empire, Part 2"),
            (datetime(2012, 11, 17, 15),3,3,"Too Many Pinkie Pies"),
            (datetime(2012, 11, 24, 15),3,4,"One Bad Apple"),
            (datetime(2012, 12, 3, 15),3,5,"Magic Duel"),
            (datetime(2012, 12, 10, 15),3,6,"Sleepless in Ponyville"),
            (datetime(2012, 12, 17, 15),3,7,"Wonderbolt Academy"),
            (datetime(2012, 12, 24, 15),3,8,"Apple Family Reunion")
            ]
    r=map(lambda x:(x[0]-datetime.now(),x[1],x[2],x[3]), times)
    r=sorted(r)
    for x in r:
        if x[0].days>=0:
            return "%s until Series %d episode %d - %s!" % (str(x[0]).split(".")[0], x[1], x[2], x[3])
    return "OutOfPoniesException: no ponies found in the future."

def ponies(tbot, user, channel, msg):
    tbot.msg(channel,nextponies())
ponies.rule = "^!ponies$"
