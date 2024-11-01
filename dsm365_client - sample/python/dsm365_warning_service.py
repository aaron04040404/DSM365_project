#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ===============================================================================================
# Computing all data recording of MCB Command, 4EH(78), calculate all alarm status, 
# and move MCB alarm information to right consumer user. Eventually, publish notifications to 
# right people immediately.
#
#
# Upgrade PIP: pip install --upgrade pip (不要在root執行比較好)
# Update All Python Packages On Linux: pip3 list --outdated --format=freeze | grep -v '^\-e' | cut -d = -f 1 | xargs -n1 pip3 install -U
# IMPORTANT: 在 GCP 上的 VM 必須使用 pip list --outdated | cut -d ' ' -f 1 | xargs -n1 pip install -U
# ===============================================================================================
import sys
import os
import time
from datetime import datetime

from lib.log_proc import Logger
from lib.gn_config import gn_ConfReader as confReader
from lib.main_serv_proc import MainDbProc
from lib.dsm365_alarm_adm import Dsm365_Alarm_Adm as timeError_adm
from lib.dsm365_alarm_tcc import Dsm365_Alarm_Tcc as timeError_tcc

def main():
    ## get all databases you need
    #main_db_admin_settings  = confReader.db_configReader( __config, "MAIN_DB_ADMIN" )
    #main_db_client_settings = confReader.db_configReader( __config, "MAIN_DB_CLIENT" )
    #raw_db_settings         = confReader.db_configReader( __config, "RAW_DB" )
    main_db_admin_settings  = confReader.db_configReader( __config, "mysql_On-Premises" )
    main_db_client_settings = confReader.db_configReader( __config, "mysql_On-Premises_client" )

    ## main admin: mcb lost connection detection
    main_admin_db = MainDbProc( main_db_admin_settings )
    Logger.logProcess( "Mcb time warning process...[start]" )
    main_admin_db.set_MCB_Disconnected()
    Logger.logProcess( "Mcb time warning process...[end]" )

    ## main admin & client: alarm 201 notification calculation
    main_admin_notif = timeError_adm( main_db_admin_settings )
    Logger.logProcess( "Time warning notification process...[start]" )
    main_admin_notif.genNotificationContents()
    Logger.logProcess( "Time warning notification process...[end]" )

    main_client_notif = timeError_tcc( main_db_admin_settings, main_db_client_settings )
    Logger.logProcess( "Client time warning notification process...[start]" )
    main_client_notif.syncNotificationContents()
    Logger.logProcess( "Client time warning notification process...[end]" )

    # disconnect database.
    del main_admin_db
    del main_admin_notif
    del main_client_notif

if __name__ == '__main__':
    # re-modify log file name to separate
    Logger.setLogProcessFileName("alarm-service")
    Logger.setLogErrorFileName("alarm-service")

    __config = os.path.dirname( os.path.abspath( __file__ ) ) + "/py_settings.conf"
    #__config = os.path.dirname( os.path.abspath( __file__ ) ) + "/config.ini"
    if os.path.isfile( __config ):
        Logger.logProcess( "config file read: %s" % __config )
    else:
        Logger.logError( "config file error!!" )
        raise Exception( "config file error!!" )

    # start to working client processing
    try:
        while True:
            main()
            time.sleep( 5 * 60 )
    except KeyboardInterrupt:
        Logger.logProcess( "Processing interrupted! (Ctrl+C)" )