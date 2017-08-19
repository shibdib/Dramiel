<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
/**
 * @property  message
 */
	class motd {
		public $config;
		public $discord;
		public $logger;
		public $message;
		private $excludeChannel;
		private $triggers;
		public function init($config, $discord, $logger) {
			$this->config = $config;
			$this->discord = $discord;
			$this->logger = $logger;
			$this->excludeChannel = $this->config['bot']['restrictedChannels'];
			$this->triggers[] = $this->config['bot']['trigger'] . 'motd';
			$this->triggers[] = $this->config['bot']['trigger'] . 'Motd';
			$this->triggers[] = $this->config['bot']['trigger'] . 'MOTD';
			$this->keyID = $config['plugins']['motd']['keyID'];
			$this->vCode = $config['plugins']['motd']['vCode'];
			$this->characterID = $config['plugins']['motd']['characterID'];
			$this->channelname = $config['plugins']['motd']['channelname'];
		}
		public function onMessage($msgData, $message) {
			$channelID = (int) $msgData['message']['channelID'];
			if (in_array($channelID, $this->excludeChannel, true)) {
				return null;
			}
			$this->message = $message;
			$user = $msgData['message']['from'];
			$message = $msgData['message']['message'];
			$data = command($message, $this->information() ['trigger'], $this->config['bot']['trigger']);
			if (isset($data['trigger'])) {
				$keyID = $this->keyID;
				$vCode = $this->vCode;
				$characterID = $this->characterID;
				$channelname = $this->channelname;

        $urlChar = "https://api.eveonline.com/char/ChatChannels.xml.aspx?keyID=$keyID&vCode=$vCode&characterID=$characterID";
				$data = file_get_contents($urlChar);
				$list = new SimpleXMLElement($data);
				
				
				foreach ($list->result->rowset->row as $row) {
					$channeln = $row["displayName"];
					
					if ($channeln == $channelname) {
					//scrub the motd, then break it into 1900 chars or less. Limit is 2000 and want to leave room for the name of the person being replied too
					$comment = $row["motd"];
          $comment = str_replace("<BR>", "\n", $comment);
          $comment = str_replace("<br>", "\n", $comment);
          $comment = str_replace("<u>", "__", $comment);
          $comment = str_replace("</u>", "__", $comment);	
          $comment = str_replace("<b>", "**", $comment);	
          $comment = str_replace("</b>", "**", $comment);	
          $comment = str_replace("<i>", "*", $comment);	
          $comment = str_replace("</i>", "*", $comment);
					$comment = str_replace("&amp;", "&", $comment);
          $comment = strip_tags($comment);
					$comment2 = str_split($comment, 1900);
					
					$this->message->reply("\n $comment2[0]");
						if (!empty($comment2[1])) {
							$this->message->reply("\n $comment2[1]");
						}
						if (!empty($comment2[2])) {
							$this->message->reply("\n $comment2[2]");
						}
						if (!empty($comment2[3])) {
							$this->message->reply("\n $comment2[3]");
						}
					}
				}
			}
		}
		public function information() {
            return array('name' => 'motd', 'trigger' => $this->triggers, 'information' => 'Displays the MOTD, useful for seeing information displayed there when not in-game.');
		}
	}
?>
