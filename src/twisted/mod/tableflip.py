# -*- coding: utf-8 -*-
from modules.upsidedown.upsidedown import transform as upsidedown

def tableflip(tbot, user, channel, msg):
    text = msg.replace('!tableflip', '').strip()
    flipped = upsidedown(text)
    tbot.msg(channel,u"%s: （╯°□°）╯︵ %s" % (user, flipped))
tableflip.rule = "!tableflip (.*)"
