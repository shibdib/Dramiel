import sys
sys.path.append("../modules/")
import regex
from fake_tbot import TestedBot

#import random
import unittest


class TestRegexModule(unittest.TestCase):

    def setUp(self):
        self.tbot = TestedBot()
        self.tbot.register(regex.storemessage)
        self.tbot.register(regex.substitute)
        self.tbot.register(regex.directedsubstitute)

    def test_subsitute(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "test")
        self.tbot.listen("Afal", "#42", "s/e/oa/")
        should_be_toast = ("#42", "<Afal> toast")
        self.assertEqual(self.tbot.last_message(), should_be_toast)

    def test_nothing_has_been_said(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "s/lol/bums/")
        rude_error = ("#42", "Uh Afal... you haven't said anything yet")
        self.assertEqual(self.tbot.last_message(), rude_error)

    def test_person_does_not_exist(self):
        self.tbot = TestedBot()
        self.tbot.register(regex.directedsubstitute)
        self.tbot.listen("Afal", "#42", "Fluttershy: s/yay/woo hoo/")
        disappointing_result = ("#42", "Afal: Fluttershy doesn't exist! You don't have to correct them!")
        self.assertEqual(self.tbot.last_message(), disappointing_result)

if __name__ == '__main__':
    unittest.main()
