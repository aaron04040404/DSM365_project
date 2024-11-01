#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ===============================================================================================
# 管理 Firebase 上的 token 用戶連結時所需要使用到的金鑰。在 Vue.js 跟 PHP 取得自定義的 token 之後，透過這個自定義的
# token，Vue.js 端的用戶才可以藉由它，真正的去跟 Firebase 的伺服汽座 cert 的驗證。此時，驗證成立之後，專案下的
# Authentication 之中，就會添加一筆新的 user 紀錄。此時的 Firebase 是不會針對這些紀錄，做定期且自動化的清理。
# 所以，才會有這個 Python 自動化成是的誕生。藉由 firebase_admin 來管理這些用戶的使用期限，並且定期清除
#
# Upgrade PIP: pip install --upgrade pip (不要在root執行比較好)
# Update All Python Packages On Linux: pip3 list --outdated --format=freeze | grep -v '^\-e' | cut -d = -f 1 | xargs -n1 pip3 install -U
# IMPORTANT: 在 GCP 上的 VM 必須使用 pip list --outdated | cut -d ' ' -f 1 | xargs -n1 pip install -U
# ===============================================================================================

from datetime import datetime, timedelta
import sys
import os
import time
import pytz

import firebase_admin
from firebase_admin import credentials, auth
from lib.log_proc import Logger
from lib.gn_config import gn_ConfReader as confReader

g_exp_time = 3600 * 24   # 1 day

# delete all users' authentications when they are out of 3 hours
def delete_expired_users( exp_timestamp ):
    # 獲取所有使用者
    users = auth.list_users().iterate_all()
    total = 0
    del_num = 0
    error_queue = []
    for user in users:
        total += 1
        # 檢查上次登入時間
        last_sign_in = user.user_metadata.last_sign_in_timestamp
        if last_sign_in:
            # remove min-sec
            last_sign_in = last_sign_in / 1000
            # remove the expired user
            if last_sign_in <= exp_timestamp:
                try:
                    auth.delete_user( user.uid )
                    del_num += 1
                    Logger.logError( "delete user uid(%s) last_on: %s" % ( user.uid, datetime.fromtimestamp( last_sign_in ).astimezone( pytz.utc ) ) )
                except Exception as error:
                    error_queue.append({
                        'uid': user.uid,
                        'message': error
                    })
                    Logger.logError( "delete items error: %s" % error_queue )
    # 這邊不做批次刪除的原因是因為:
    # Firebase Admin SDK 还可一次删除多个用户。
    # 但请注意，使用 deleteUsers(uids) 等方法一次删除多个用户不会为 Cloud Functions for Firebase 触发 onDelete() 事件处理程序。
    # 这是因为批量删除不会触发针对各个用户的用户删除事件。如果您希望针对删除的每个用户触发用户删除事件，请逐个删除用户。
    Logger.logProcess( "delete items are success: total=%s, delete=%s, time scope=%s" % ( total, del_num, exp_timestamp ) )
    return True

if __name__ == '__main__':
    # let all logs are together.
    Logger.setLogProcessFileName("firebase-admin-proc")
    Logger.setLogErrorFileName("firebase-admin-proc")

    __config = os.path.dirname( os.path.abspath( __file__ ) ) + "/py_settings.conf"
    if os.path.isfile( __config ):
        Logger.logProcess( "config file read: %s" % __config )
    else:
        Logger.logError( "config file error!!" )
        raise Exception( "config file error!!" )

    # Convert database config format.
    firebase_config = confReader.configReader( __config, "Firebase" )
    if os.path.isfile( firebase_config["firebase_admin_private_key_path"] ):
        Logger.logProcess( "Firebase IAM Oauth file read success: %s" % firebase_config["firebase_admin_private_key_path"]  )

        cred = credentials.Certificate( firebase_config["firebase_admin_private_key_path"] )
        # start to working client processing
        app = None
        try:
            while True:
                # initialize Firebase Admin SDK
                app = firebase_admin.initialize_app( cred )
                Logger.logProcess( "Firebase admin process start(name=%s, object_id=%s)" % ( app.name, app.project_id ) )
                # set up the expired time. default = a day
                exp_timestamp = int( datetime.now().timestamp() ) - g_exp_time
                status = delete_expired_users( exp_timestamp )
                if status is False:
                    Logger.logProcess( "Firebase delete user keys error!" )
                # 確保應用在結束時被刪除，釋放所有資源
                firebase_admin.delete_app( app )
                app = None
                # sleep until the next day start time to clean user authentication
                tm = time
                # do get the next day starting time
                next_date = int( tm.time() / 3600 / 24 ) * 3600 * 24 + 3600 * 24
                next_time = next_date - tm.time()
                Logger.logError( "sleep %s sec. next process time: %s" % ( next_time, datetime.fromtimestamp( next_date ).astimezone( pytz.utc ) ) )
                time.sleep( next_time )
        except KeyboardInterrupt:
            # 確保應用在結束時被刪除，釋放所有資源
            if app is not None:
                firebase_admin.delete_app(app)

            Logger.logProcess( "Processing interrupted! (Ctrl+C)" )
    else:
        Logger.logError( "Firebase IAM Oauth file error!!" )