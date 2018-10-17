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
