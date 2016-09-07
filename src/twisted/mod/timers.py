import arrow, requests

def get_timers():
	r = requests.get("http://timerboard.net/api/timers")
	r = r.json()
	results = []
	r = filter(lambda x:x["time"]>arrow.utcnow().timestamp, r)
	for result in r:
		results.append("%s - %s - %s [%s]" % (arrow.get(result["time"]).humanize(), result["system"], result["notes"], result["owner"]))
	return results

def timers(tbot, user, channel, msg):
	tbot.say(channel, user+": "+ " ".join(get_timers()[0:3]))
timers.rule = '!timers'

