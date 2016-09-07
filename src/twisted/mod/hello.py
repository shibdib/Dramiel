def hello(tbot, user, channel, msg):
    tbot.msg(channel,"Greetings %s from %s!" % (user, channel))
hello.rule = "hello TwistedBot"
