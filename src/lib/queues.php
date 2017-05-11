<?php

// Message queue
function messageQueue($discord, $logger)
{
    $x = 0;
    while ($x < 3) {
        $id = getOldestMessage();
        $id = $id['MIN(id)'];
        if (null === $id) {
            $id = 1;
        }
        $queuedMessage = getQueuedMessage($id);
        if (null !== $queuedMessage) {
            //Check if queued item is corrupt and delete it if it is
            if (null === $queuedMessage['guild'] || null === $queuedMessage['channel'] || null === $queuedMessage['message']) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Queued item is badly formed, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $guild = $discord->guilds->get('id', $queuedMessage['guild']);
            //Check if guild is bad
            if (null === $guild) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Guild provided is incorrect, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $channel = $guild->channels->get('id', (int)$queuedMessage['channel']);
            //Check if channel is bad
            if (null === $channel) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Channel provided is incorrect, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $logger->addInfo("QueueProcessing - Completing queued item #{$id}");
            $message = $channel->sendMessage($queuedMessage['message'], false, null);
            while ($message === FALSE){
                $message = $channel->sendMessage($queuedMessage['message'], false, null);
            }
            clearQueuedMessages($id);
        } else {
            $x = 99;
        }
        $x++;
    }
}