You can use them in the Linux background processing in Python2.7 like belows:

$ nohup python /{your project path}/mcbAlarm_v2.py > dsm365_alarm.$(date +%Y-%m-%d).log 2>&1 &

$ nohup python /{your project path}/notificationBroadcast_v1.py > dsm365_notif.$(date +%Y-%m-%d).log 2>&1 &