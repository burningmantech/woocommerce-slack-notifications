# Woocommerce Slack Notifications for Burning Man E-Commerce

Installing
You will need to have administrator rights in Slack in order to be able to fully install this plugin.
1) Install & Activate plugin in Wordpress Plugins on whatever Wordpress installation you are using
2) Visit https://api.slack.com/ and log into the burningman account
3) Click 'Start Building'
4) After you have named and created your App, click the 'Basic Information' tab and 'Add Features and Functionality' slider
5) Make sure Incoming Webhooks is turned on 
6) Click Permissions, Go to Scopes and add the following: chat:write:bot, chat:write:user, incoming-webhook
7) Install the App, select the channel to output to
8) Copy the OAuth Access Token
9) Go to Woocommerce > Settings > Slack Notifications, enter oAuth Access Token and set plugin parameters. Check enable plugin and save.

## What It Does
This plugin's primary purpose is to check of the Payment Gateways we are using are still online, and to record their states as they are switched from 'testmode' to 'production'. It will send notifications to the Slack channel whenever Burning Man's 'Click and Pledge' Gatway and 'PayEezy' Gatway do the following:
- Production -> Testmode
- Testmode -> Production
- Plugin Deactivated
- Plugin Activated
- Payment Method Enabled
- Payment Method Disabled

Because the PayEezy Gateway uses AJAX to insert WooCommerce Errors, I have included a Javascript which will record any PayEezy related errors to Google Analytics. I am intending to add functionality to allow an admin to set a specific Cart error that can trigger a Slack Notification. This would allow some general cart errors related to improper payment gateway settings to be sent to slack and improve respnse time.
