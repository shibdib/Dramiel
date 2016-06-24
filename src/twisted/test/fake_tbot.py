"""
A fake TwistedBot!
"""
import re


class Logger():
    def log(self, *args):
        pass


class TestedBot:
    bot_messages = []
    logger = Logger()
    functions = []
    messages = {}
    __funcs = {}

    def __init__(self):
        pass

    def msg(self, channel, message):
        self.bot_messages.append((channel, message))

    def register(self, func, name=None):
        self.functions.append(func)
        if name:
            self.__funcs[name] = func.rule

    def rules(self):
        messages = ["The rules and functions are as follows:"]
        for func in self.__funcs:
            messages.append("    %s = %s" % (func, self.__funcs[func]))
        return messages

    def last_message(self):
        if len(self.bot_messages):
            return self.bot_messages[-1]

    def listen(self, usr, channel, message):
        for func in self.functions:
            if re.match(func.rule, message):
                func(self, usr, channel, message)
        return self.bot_messages
