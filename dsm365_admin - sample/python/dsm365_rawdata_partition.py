#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ===============================================================================================
# python dsm365_rawdata_partition.py
# To add the next month partition on 15th of each month


import sys
import os
import time
from datetime import datetime
from lib.dsm365_partition_adm import Partition
from lib.log_proc import Logger
from lib.gn_config import gn_ConfReader as confReader

def main(i):

    #main_db_admin_settings  = confReader.db_configReader( __config, "MAIN_DB_ADMIN" )
    main_db_client_settings = confReader.db_configReader( __config, "MAIN_DB_CLIENT" )
    main_db_admin_settings  = confReader.db_configReader( __config, "mysql_On-Premises" )
    #main_db_client_settings = confReader.db_configReader( __config, "mysql_On-Premises_client" )

    admin_partition = Partition(main_db_admin_settings)
    Logger.logProcess( "add partition process...[start]" )
    admin_partition.add_Partition(i)
    Logger.logProcess( "add partition process...[end]" )

    del admin_partition


if __name__ == '__main__' :

    Logger.setLogProcessFileName("add-partition")
    Logger.setLogErrorFileName("add-partition")

    __config = os.path.dirname( os.path.abspath( __file__ ) ) + "/py_settings.conf"
    if os.path.isfile( __config ):
        Logger.logProcess( "config file read: %s" % __config )
    else:
        Logger.logError( "config file error!!" )
        raise Exception( "config file error!!" )
    
    try:
        i = 0
        while True:
            main(i)
            i = 1
            time.sleep( 24 * 3600 ) #每天檢查是不是15號就好
    except KeyboardInterrupt:
        Logger.logProcess( "Processing interrupted! (Ctrl+C)" )