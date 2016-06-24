import random
def rainbowdash(tbot, channel):
    quotes = [
            "Three months of winter coolness, and awesome holidays..",
            "I'm just glad I wasn't replaced by a bucket of turnips. ",
            "I love fun things!",
            "Are you a SPY?",
            "I could clear the sky in 10 seconds flat!"
            ]
    tbot.msg(channel,random.choice(quotes))
#rainbowdash.joined = True

def rainbowdash_ascii(tbot, user, channel, message):
    # Whoa. print all that rainbow dash you gonna have a wicked grouchy #42
    # (probably why I put it in a module that's blacklisted >_>)
    rainbowdash = open("modules/rainbowdash.ascii.txt").read().split("\n")
    for line in rainbowdash:
        tbot.msg(channel, line)
rainbowdash_ascii.rule = "!rainbowdash"
