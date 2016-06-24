import sqlite3, re
import datetime, time
fiveminutes=datetime.timedelta(minutes=5)

def getkarma(name):
  conn = sqlite3.connect('karma.db')
  c = conn.cursor()
  c.execute("select * from karma where name=?",(name,))
  r = c.fetchone()
  conn.close()
  if r:
    return r[1]
  return int(0)
  
def setkarma(name,value):
  conn = sqlite3.connect('karma.db')
  c = conn.cursor()
  c.execute("select * from karma where name=?",(name,))
  r = c.fetchone()
  if r:
    now=datetime.datetime.now()
    then=datetime.datetime.fromtimestamp(float(r[2]))
    if ((now-then)>fiveminutes):
      value=r[1]+value
      c.execute("update karma set value=?, timestamp=? where name=?", (value,time.mktime(datetime.datetime.now().timetuple()),name))
    else:
      conn.close()
      return r[2]
  else:
    c.execute("insert into karma values(?, ?, ?)", (name,value,time.mktime(datetime.datetime.now().timetuple())))
  conn.commit()
  conn.close()
  return 0
    
def notify(tbot, recipient, text):
  tbot.notice(recipient, text)

def plusplus(tbot, user, channel, msg):
  msg = re.match(plusplus.rule, msg)
  name = msg.groups()[0]
  status=setkarma(name,1) 
  if (status==0):
    notify(tbot, user, "%s is now at %d karma." % (name,getkarma(name)))
  else:
    notify(tbot, user, "Please wait until 5 minutes after %s." % datetime.datetime.fromtimestamp(float(status)))
plusplus.rule = '(\w+)\+\+ ?.*?'

def minusminus(tbot, user, channel, msg):
  msg = re.match(minusminus.rule, msg)
  name = msg.groups()[0]
  status=setkarma(name,-1) 
  if (status==0):
    notify(tbot, user, "%s is now at %d karma." % (name,getkarma(name)))
  else:
    notify(tbot, user, "Please wait until 5 minutes after %s." % datetime.datetime.fromtimestamp(float(status)))
minusminus.rule = '(\w+)\-\- ?.*?'

def askkarma(tbot, user, channel, msg):
  name=str(msg).replace("!karma","").strip()
  tbot.say(channel, "%s is at %d karma." % (name,getkarma(name)))
askkarma.rule=r'!karma .*'

def karmastats(tbot, user, channel, msg):
  conn=sqlite3.connect("karma.db")
  c=conn.cursor()
  results=c.execute("select name,value from karma order by value").fetchall()[-5:]
  results = " ".join(map(lambda x: "[%s : %s]" % (x[0],x[1]), results))
  bottomresults=c.execute("select name,value from karma order by -value").fetchall()[-5:]
  bottomresults = " ".join(map(lambda x: "[%s : %s]" % (x[0],x[1]), bottomresults))
  conn.close()
  tbot.say(channel, results)
  tbot.say(channel, bottomresults)
karmastats.rule=r'!karmastats'
