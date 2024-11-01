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
import re
from datetime import datetime

from lib.log_proc import Logger
from lib.gn_config import gn_ConfReader as confReader
from lib.main_serv_proc import MainDbProc
from lib.raw_serv_porc import RawDbProc

## global variables
# all database instance
g_main_admin_db = None
g_raw_db        = None

def running_unit( mcb_id, now_utctime ):
    ## to use global variables
    global g_main_admin_db
    global g_raw_db

    if g_main_admin_db is None or g_raw_db is None:
        Logger.logProcess ( "database instance is None. please get all database instances first!" )
        return False

    if mcb_id <= 0:
        Logger.logProcess ( f"mcb id error in running time calculation: id = {mcb_id}" )
        return False

    # 雖說這不太可能發生，但是還是預防一下system取樣時間的時候，系統時間是錯誤的可能性
    if now_utctime < MainDbProc.UNIXTIME_SYS_BEGIN_TIME:
        now_utctime = MainDbProc.UNIXTIME_SYS_BEGIN_TIME

    ## for power consumption(kWh):
    # look for the last generated data time in main server kwh table.
    start_on = g_main_admin_db.getLastTimeOfRunning( mcb_id )
    end_on = 0
    if start_on is None: # if there is no beginning, you have to find it out in raw data.
        time_scope_dict = g_raw_db.getCmdTimeScope( mcb_id, 160, now_utctime )
        if ( time_scope_dict["start_on"] is None or time_scope_dict["end_on"] is None ):
            Logger.logProcess( f"mcb {mcb_id} running time has no raw data for the beginning..." )
            return True

        start_on = time_scope_dict["start_on"]
        end_on = time_scope_dict["end_on"]
        # If the raw data start time is less than the system birthday, assign the birthday to be the beginning.
        if ( start_on < MainDbProc.UNIXTIME_SYS_BEGIN_TIME ):
            start_on = MainDbProc.UNIXTIME_SYS_BEGIN_TIME
    else:
        # 這裡不用轉換到下個小時的區段，切記！！跟kwh不同
        # find the end time near by now(UTC)
        end_on = g_raw_db.getCmdBeginEndOn( mcb_id, 76, now_utctime )
        if ( end_on is None ):
            Logger.logProcess( f"mcb {mcb_id} running time has no raw data for the end..." )
            return True

    # convert to be base on hour
    start_on = int( start_on / 3600 ) * 3600
    end_on   = int( end_on / 3600 ) * 3600
    # no data, stop the method if the end time is smaller/equal than start time.
    if ( end_on <= start_on ):
        Logger.logProcess( f"mcb {mcb_id} running time last hour is not completed!" )
        return True

    Logger.logProcess( f"mcb {mcb_id} running time calculating... {start_on} ~ {end_on} " )

    # 請注意這個 getRunningTime 輸出的 結果欄位是否與下面 addMcbRunning 資料庫寫入的欄位是一樣的 ( id, time_on, running, belong_to )
    # @2024-10-22:
    # 目前採取一次全部的時間之內的資料取出來做處理，不進行分時段取資料的方式。如果以後有什麼問題，請從這邊開始修改
    running_list = g_raw_db.getRunningTime( mcb_id, start_on, end_on )
    _is_done = True
    if running_list is not None and len( running_list ) > 0:
        _is_done = g_main_admin_db.addMcbRunning( running_list )
        if not _is_done:
            Logger.logProcess( f"mcb {mcb_id} insert running time fail!" )
    # else: 這代表它沒有東西可以使用。自然就是直接放行，回應True即可
    return _is_done

## calculate kWh of each MCB unit
def mcb_kwh_unit( mcb_id, now_utctime ):
    ## to use global variables
    global g_main_admin_db
    global g_raw_db

    if g_main_admin_db is None or g_raw_db is None:
        Logger.logProcess ( "database instance is None. please get all database instances first!" )
        return False

    if mcb_id <= 0:
        Logger.logProcess ( f"mcb id error in kWh calculation: id = {mcb_id}" )
        return False

    # 雖說這不太可能發生，但是還是預防一下system取樣時間的時候，系統時間是錯誤的可能性
    if now_utctime < MainDbProc.UNIXTIME_SYS_BEGIN_TIME:
        now_utctime = MainDbProc.UNIXTIME_SYS_BEGIN_TIME

    ## for power consumption(kWh):
    # look for the last generated data time in main server kwh table.
    start_on = g_main_admin_db.getLastTimeOfKwh( mcb_id )
    if start_on is None: # if there is no beginning, you have to find it out in raw data.
        start_on = g_raw_db.getCmdBeginEndOn( mcb_id, 160, now_utctime, True )   # take recording timestamp(local system time)
        if ( start_on is None ):
            Logger.logProcess ( f"mcb {mcb_id} kWh has no raw data!" )
            return True
        # If the raw data start time is less than the system birthday, assign the birthday to be the beginning time.
        if ( start_on < MainDbProc.UNIXTIME_SYS_BEGIN_TIME ):
            start_on = MainDbProc.UNIXTIME_SYS_BEGIN_TIME
    else:
        start_on = start_on + 3600    # 取 kWh 中 time_on 的下個小時時段為開始，切記！！

    # convert to base on hour
    start_on = int( start_on / 3600 ) * 3600

    # if the end time is smaller/equal than start time, stop this process.
    if ( now_utctime <= start_on ):
        Logger.logProcess ( f"mcb {mcb_id} kWh last hour is not completed!" )
        return True

    # to check how many data's you have to calculate.
    total_raw_num = g_raw_db.countCmd( mcb_id, 160, start_on, now_utctime, True )
    if ( total_raw_num == 0 ):
        Logger.logProcess ( f"mcb {mcb_id} kWh has no new raw data anymore!" )
        return True

    Logger.logProcess( f"mcb {mcb_id} kWh has {total_raw_num} raw data to calculate: {start_on} ~ {now_utctime}" )
    # calculate new data to insert into displayer_kwh
    sql_insertmany_vals = []
    end_on = start_on + 3600      # change to the next hour point 每次處理一個小時的量體
    _is_done = True
    while True: # 每次處理一個小時的量體
        # stop the while-loop!
        # when the end time is bigger than(/equal to) stop time,
        # it is out of the range.
        if ( total_raw_num == 0 or now_utctime <= end_on ):
            break   # stop whole loop process

        kwh_rows = g_raw_db.getMcbKwh( mcb_id, start_on, end_on )
        # skip it if there is nothing in raw data table.
        if ( kwh_rows is None or len( kwh_rows ) == 0 ):
            # update to the next hour range
            start_on = end_on
            end_on += 3600
            continue    # skip this round and go next round

        # calculate and put values into temp for ac power kWh of each MCB
        ac_pw_temp = {} # give it an empty dict
        for ( ac_pw, belong_to ) in kwh_rows:
            total_raw_num -= 1

            # if the client ID is never existed, insert a new {} into it
            if ( belong_to not in ac_pw_temp ):
                ac_pw_temp[ belong_to ] = {
                    "sum": 0,
                    "num": 0
                }

            if ( ac_pw is None ): # when ac_pw is a None, it means the device is without the value providing.
                # ac_pw_temp[ belong_to ][ 'sum' ] += 0
                ac_pw_temp[ belong_to ][ 'num' ] += 1
            elif ( isinstance( ac_pw, int ) ):
                if ( ac_pw >= 0 ):  # only work with valid values(> 0).
                    ac_pw_temp[ belong_to ][ 'sum' ] += ac_pw
                    ac_pw_temp[ belong_to ][ 'num' ] += 1
                # if it is an invalid value, skip it.
            elif ( isinstance( ac_pw, str ) and len( ac_pw ) > 0 ):
                # older 版本有分 w/peak，新版本就只有單純的 w, 更舊的版本資料就忽視，不做計算了
                try:
                    _ac_pw = ac_pw.split( "/" )
                    if ( len( _ac_pw ) >= 1 ):
                        # remove all thing but number in string.
                        # if ac value cannot to be int from string like int(''), it will go to exception
                        _ac_pw = int( re.sub( '\D', '', _ac_pw[ 0 ] ).strip() )
                    else:
                        _ac_pw = 0

                    if ( _ac_pw >= 0 ):
                        ac_pw_temp[ belong_to ][ 'sum' ] += _ac_pw
                        ac_pw_temp[ belong_to ][ 'num' ] += 1
                    # if it is an invalid value, skip it.
                except ValueError:
                    # if it is an invalid value, skip it.
                    Logger.logProcess( f"<skip> mcb {mcb_id} AC(pre-version) invalid: {ac_pw}; timestamp: {start_on}" )
            else:
                # if it is an invalid value, skip it.
                Logger.logProcess( f"<skip> mcb {mcb_id} AC invalid: {ac_pw}; timestamp: {start_on}" )

        for belong_to in ac_pw_temp:
            _kWh = 0
            if ( ac_pw_temp[ belong_to ][ 'sum' ] != 0 and ac_pw_temp[ belong_to ][ 'num' ] > 0 ):
                _kWh = ac_pw_temp[ belong_to ][ 'sum' ] / ac_pw_temp[ belong_to ][ 'num' ] / 1000
            # push values into sql insert value array
            sql_insertmany_vals.append( ( mcb_id, start_on, _kWh, belong_to ) )
        # push into memory for database saving.
        if( len( sql_insertmany_vals ) > 0 ):
            _is_done = g_main_admin_db.addMcbKwh( sql_insertmany_vals )
            if not _is_done:
                Logger.logProcess( f"mcb {mcb_id} insert kWh fail!" )
                break   # stop the while loop. 為什麼要這樣做，是為了以後修正錯誤後，就可以繼續從最後錯誤的時間點開始。
                        # 如果忽略這個錯誤，而繼續loop的執行，以後要補檔幾乎是不可能的。因此，寧願不要繼續執行！
            # clean pre-batch data
            sql_insertmany_vals.clear()

        # go to next hour
        start_on = end_on
        end_on += 3600
    return _is_done

def main():
    ## to use global variables
    global g_main_admin_db
    global g_raw_db

    ## get all databases you need
    #main_db_admin_settings  = confReader.db_configReader( __config, "MAIN_DB_ADMIN" )
    #raw_db_settings         = confReader.db_configReader( __config, "RAW_DB" )
    main_db_admin_settings  = confReader.db_configReader( __config, "mysql_On-Premises" )
    raw_db_settings         = confReader.db_configReader( __config, "mysql_On-Premises" )
    ## initialize db
    g_main_admin_db = MainDbProc( main_db_admin_settings )
    g_raw_db = RawDbProc( raw_db_settings )

    ## mcb power consumption & running time
    Logger.logProcess( "Display insight values calculating...[start]" )
    now_utctime = datetime.utcnow().timestamp()
    page = 0
    per = 10000
    while True:
        devices = None
        devices = g_main_admin_db.getMcbTab( page, per )
        if devices is not None:
            for ( mcb_id, ) in devices:
                if mcb_kwh_unit( mcb_id, now_utctime ) is False: # calculate each mcb kwh unit
                    Logger.logProcess( f"display({mcb_id}) kWh calculating fail!" )
                if running_unit( mcb_id, now_utctime ) is False: # calculate each mcb running time unit
                    Logger.logProcess( f"display({mcb_id}) running time calculating fail!" )
            # break while loop when it is the last page content in display table.
            if ( len( devices ) < per ):
                break   # stop while loop
            else: # to next page
                page += per
        else:
            Logger.logProcess( "No mcb in table [page = %s, per = %s]" % ( page, per ) )
            break   # stop while loop
    Logger.logProcess( "Display insight values calculating...[end]" )

if __name__ == '__main__':
    # re-modify log file name to separate
    Logger.setLogProcessFileName("insight-unit")
    Logger.setLogErrorFileName("insight-unit")

    __config = os.path.dirname( os.path.abspath( __file__ ) ) + "/py_settings.conf"
    if os.path.isfile( __config ):
        Logger.logProcess( "config file read: %s" % __config )
    else:
        Logger.logError( "config file error!!" )
        raise Exception( "config file error!!" )

    # start to working client processing
    try:
        while True:
            main()
            # 休息到下個整點的時間，再開始計算
            tm = time
            time.sleep( int( tm.time() / 3600 ) * 3600 + 3600 - tm.time() )
    except KeyboardInterrupt:
        Logger.logProcess( "Processing interrupted! (Ctrl+C)" )