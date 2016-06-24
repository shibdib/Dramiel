import yaml

class Config(object):
    settings = dict()
    configfile = ""
    def __init__(self, configfile):
        self.configfile = configfile

    def parse(self):
        try:
            f = open(self.configfile, "r")
            data = "".join(f.readlines())
            f.close()
            self.settings = yaml.load(data)
            return self.settings
        except IOError:
            return False

    def addVariable(self,d):
        data = self.parse()
        if data:
            data.update(d)
            output = yaml.dump(data)
            f = open(self.configfile, "w")
            f.write(output)
            f.close()
            return True
        return False
