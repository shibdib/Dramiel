def diff(old, new):
    if old.count(new):
        return "Removal: " + old.replace(new, "")
    if new.count(old):
        return "Addition: " + new.replace(old, "")
    return "Damn, that's one new title."

def titlediff(tbot, user, channel, newTopic):
    if not hasattr(tbot, "topics"):
        tbot.topics=dict()
    if channel not in tbot.topics:
        tbot.topics[channel]=newTopic
    else:
        tbot.logger.log("INFO", "TopicChanged: channel:%s topic:%s" % (channel, newTopic))
        old = tbot.topics[channel]
        new = newTopic
        tbot.topics[channel]=newTopic
        tbot.say(channel, diff(old, new))
titlediff.topicUpdated = True
