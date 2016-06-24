import sys
sys.path.append("../modules/")
import ping
from fake_tbot import TestedBot

import unittest


class TestPingModule(unittest.TestCase):

    def setUp(self):
        self.tbot = TestedBot()
        self.tbot.register(ping.thanks_ants)

    def test_thanks_ants(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "!thanks ants")
        self.assertEqual(self.tbot.last_message()[1], "Thanks ants... Thants")

    def test_thanks_trtl(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "!thanks trtl")
        self.assertEqual(self.tbot.last_message()[1], "Thanks trtl... Thtrtl")

    def test_thanks_sylnai(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "!thanks Sylnai")
        self.assertEqual(self.tbot.last_message()[1], "Thanks Sylnai... Thai")

    def test_thanks_phoebe(self):
        self.tbot.bot_messages = []
        self.tbot.listen("Afal", "#42", "!thanks phoebe")
        self.assertEqual(self.tbot.last_message()[1], "Thanks phoebe... Thoebe")

if __name__ == '__main__':
    unittest.main()
