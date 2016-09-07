#!/usr/bin/env python
import time, math

def beats(tbot, user, channel, msg): 
    """Shows the internet time in Swatch beats."""
    beats = ((time.time() + 3600) % 86400) / 86.4
    beats = int(math.floor(beats))
    tbot.msg(channel, '@%03i bong' % beats)
beats.rule = "!bong"
