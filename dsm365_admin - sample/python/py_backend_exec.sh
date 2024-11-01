#!bin/bash

echo "dsm365 admin/client side warning processing is starting...(v4)"
nohup /usr/bin/python3 /var/www/dsm365_admin/python/dsm365_warning_service.py > /dev/null 2>&1 &

echo "dsm365 admin side display insight unit calculation is starting..."
nohup /usr/bin/python3 /var/www/dsm365_admin/python/dsm365_insight_units.py > /dev/null 2>&1 &

echo "dsm365 admin notification emailing is starting..."
nohup /usr/bin/python3 /var/www/dsm365_admin/python/dsm365_emailer.py > /dev/null 2>&1 &

echo "firebase authentication clean-up starting..."
nohup /usr/bin/python3 /var/www/dsm365_admin/python/dsm365_firebase_admin.py > /dev/null 2>&1 &
