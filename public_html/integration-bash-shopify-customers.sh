#!/bin/bash

SECONDS=0

# echo "JOBS start " | tee /home/adhome/servers/shopify.sambaai.local/logs/integration-log-customers.txt

actionsall() {
	/usr/bin/php /home/adhome/servers/shopify.sambaai.local/yii xml-generator/generate-customers | tee -a /home/adhome/servers/shopify.sambaai.local/logs/integration-log-customers.txt
}

actions() {
	/usr/bin/php /home/adhome/servers/shopify.sambaai.local/yii xml-generator/generate-customers | tee -a /home/adhome/servers/shopify.sambaai.local/logs/integration-log-customers.txt
	if (($SECONDS > 500)); then
	    break
	fi
}

actionsall

while (($SECONDS <= 500)); do
   actions # Loop execution
done

# echo "It takes $SECONDS seconds to complete this task..."  | tee -a /home/adhome/servers/shopify.sambaai.local/logs/integration-log-customers.txt
