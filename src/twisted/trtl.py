"""
            _____________________________________________
  ___      < trtl - TwistedBot REPL for Testing Libraries >_
 //_\\\\ _    ---------------------------------------------
/_|_|_('>  /
 "   "
"""
import os
import sys
import traceback
import readline
import re
regex = re.compile("\x03(?:\d{1,2}(?:,\d{1,2})?)?", re.UNICODE)
sys.path.append("./modules/")

TO_LOAD = [filename[:-3] for dirname, dirnames, filenames in os.walk('./modules') for filename in filenames if filename[-3:] == ".py"]
MODULES = {}

from test.fake_tbot import TestedBot

TBOT = TestedBot()

for module in TO_LOAD:
    try:
        MODULES[module] = __import__(module)
        for function in dir(MODULES[module]):
            glob = MODULES[module].__dict__[function]
            if hasattr(glob, 'rule'):
                TBOT.register(glob, function)
    except:
        pass

USER = "[USER]"
CHANNEL = "[CHANNEL]"

print __doc__

while True:
    try:
        msg = raw_input("> ")
    except EOFError:
        print ""
        print "Bye!"
        sys.exit()
    except KeyboardInterrupt:
        print ""
        print "Bye!"
        sys.exit()
    if msg and msg[0] == "/":
        try:
            (command, value) = msg.split(" ", 1)
        except:
            command = msg
        if command == "/nick":
            USER = value
            print "(Nick has changed to '%s')" % USER
        elif command == "/j":
            CHANNEL = value
            print "(Channel has changed to '%s')" % CHANNEL
        elif command == "/rules":
            print "\n".join(TBOT.rules())
        else:
            print "(Not a recognised command)"
    else:
        try:
            TBOT.listen(USER, CHANNEL, msg)
            if [] != TBOT.bot_messages:
                print regex.sub('', "\n".join([x[1] for x in TBOT.bot_messages]))
                TBOT.bot_messages = []
        except:
            print traceback.format_exc()
