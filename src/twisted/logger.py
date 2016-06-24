from datetime import datetime
import pygments.console
from cgi import escape
import re
from twisted.web.resource import Resource

class Logger(object):
    verbosity = 3
    verbositylevels = {
            0: ["GOOD", "ERROR"],
            1: ["GOOD", "ERROR", "WARN"],
            2: ["GOOD", "ERROR", "WARN", "INFO"],
            3: ["GOOD", "ERROR", "WARN", "INFO", "OKAY"],
            }
    loglevels = {
            "GOOD": "green",
            "INFO": "blue",
            "OKAY": "white",
            "WARN": "red",
            "ERROR": "darkred"
            }

    webBuffer = []

    def __init__(self, verbosity):
        self.log("INFO","Verbosity set to %s, logging at: %s" % (verbosity, self.verbositylevels[verbosity]))
        self.verbosity = verbosity

    def csscolorize(self, level, string):
        url=re.compile("(https?|ftp)(://)([\w_/\.&=-]*)")
        result = escape(string)
        if url.search(result):
            result = " ".join(map(lambda x:url.match(x) and "<a href='"+x+"' >"+x+"</a>" or x, result.split()))
        return '<p class="%s">%s</p>' % (level, result)

    def log(self, loglevel, message):
        if loglevel in self.verbositylevels[self.verbosity]:
            m = "%s %s" % (datetime.now().strftime("%H:%M:%S"), message)
            print pygments.console.colorize(self.loglevels[loglevel], m)
            self.webBuffer.append(self.csscolorize(loglevel, m))

class logReader(Resource):
    page = """<html>
<head>
    <title>TwistedBot WebLogger</title>
<meta http-equiv="Refresh" content="2" \>
<style type="text/css"><!--
body {
    background-color: rgb(0,0,0);
    overflow:hidden;
    font-family: courier,fixed,swiss,sans-serif;
    font-size: 16px;
    color: #33d011;
}
p {
padding: 0;
margin-top: 0;
margin-right: 0;
margin-bottom: 0;
margin-left: 0;
}
p.GOOD {color: rgb(0,187,0) }
p.INFO {color: rgb(0,0,187) }
p.OKAY {color: rgb(187,187,187) }
p.WARN {color: rgb(187,0,0) }
p.ERROR {color: rgb(255,85,85) }
--></style>
</head>
<body>
%s
</body>
</html>
    """
    def __init__(self, logger):
        self.logger = logger

    def render(self, request):
        return self.page % "\n".join(self.logger.webBuffer[-30:])
