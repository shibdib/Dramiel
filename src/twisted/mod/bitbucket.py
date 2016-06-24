import urllib2
import json
from django.utils.encoding import smart_str

def getCommitSummary(url):
    m = url.split("/")
    (username, reponame, ignoreme, changeset) = m[-4:]
    try:
        apiurl = "https://api.bitbucket.org/1.0/repositories/%s/%s/changesets/%s/" % (username, reponame, changeset)
        f = urllib2.urlopen(apiurl)
        data = f.read()
    except urllib2.HTTPError:
        return "Access Denied, repository is private."
    data = json.loads(data)
    revision = data["revision"]
    person = data["author"]
    timestamp = data["timestamp"]
    branch = data["branch"]
    commitmessage = data["message"]

    return "%s: [%s] %s - %s - %s" % (revision, timestamp, branch, person, commitmessage)


def bitbucket(tbot, user, channel, msg):
    m = msg.split(" ")
    tbot.msg(channel, '\x0311'+smart_str(getCommitSummary(m[0])+chr(15)))
bitbucket.rule = "https://bitbucket.org/.*/changeset/.*"
