#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys, os
import pytz
from datetime import datetime
from datetime import timedelta
import json
import hashlib
import uuid
from .database_mysql import MySQLDatabaseProc as mysqlDB
from .log_proc import Logger
import re
import math
import copy # for object deep copy, not memory sharing.
from typing import Union    # 在 3.9 及以前的版本，你需要使用 typing 模組的 Union 去侷限定義 function 的 return 值為什麼類型的變數

# ========================================================================================================
#
# @2024-10-17:
# 將 raw data table (mcb_detail_record) 獨立放置在一個 raw data MySQL 機器之中。
# 因此，將過去的寫法重新分資料庫讀取，重新撰寫。
# 目前這邊是負責 main database 的部份
#
# ------------------ before 2024-10-17（以前叫mcb_v2.py） ------------------
# This class is for DSM365 of MCB
# You can use these classes to work with MCB recording data of DynaScan display device.
# Every MCB has its own account in database with unique id, and every detail of 
# recording are saved too. All functions are computing for different purpose to make
# recording data of every situation have its meaning to show on client in chart.
#
# All system timestamp must be UTC time zone for default value.
# ========================================================================================================
class MainDbProc (object):
    # DATETIME_SYS_BEGIN_TIME = "2017-05-19 00:00:00"
    UNIXTIME_SYS_BEGIN_TIME = 1495152000

    def __init__ ( self, main_db_config ):
        # define const
        self.DB_POOL_NAME = "MainDbProc_POOL_NAME"
        # setup connection pools: main server & raw server
        if mysqlDB.startCnxPool( main_db_config, self.DB_POOL_NAME, 1 ) is False:
            raise Exception( "main database connection pooling error!" )

    def __del__ ( self ):
        del self.DB_POOL_NAME

    # @2024-03-29 其實，之前掃描 raw data 的處理方式，應該可以被廢除了。因為它只剩下找尋 201(mcb lost)的功能。
    #             而找尋他的斷線，其實只需要用 realtime table 之中的 update_on 去計算即可。
    #             這樣可以極速的反應，並且省下大量的時間
    # update current display connection status via update_on for disconnection detection.
    # It only works for MCB alarm code 201(MCB Lost)
    # 但是注意！！ 201(MCB Lost) 的 end timing 則是在 PHP 之中的 McbCtrl.php 之中做判斷。原因是因為當機器再次上線後，只需要檢查它是否曾經有備判斷為離線。
    # 如果有，就去關閉這個 alarm event 即可
    def set_MCB_Disconnected ( self ):
        cnx = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        # @2024-01-19: 這邊的 new_data.end_on 可能要先斷定 「原本的」值，是否為 1970-01-01(time=0) 的狀態
        #              如果已經有被其他 PHP 的部位填入時間點了，那這次就不要更新，直接跳過，保留原值
        sql_insert_alarm_event = """INSERT INTO mcb_alarm_events ( id, mcb_id, alarm_type, start_on, end_on )
                                    VALUES( UNHEX(%s), %s, 201, FROM_UNIXTIME(%s), FROM_UNIXTIME(0) ) AS new_data
                                    ON DUPLICATE KEY UPDATE end_on = IF(
                                    mcb_alarm_events.end_on != FROM_UNIXTIME(0), mcb_alarm_events.end_on, new_data.end_on )"""
        # NOTE: 如果之後覺得速度過慢，就改用 insert into ... on duplicate ...
        # IMPORTANT: 但是請注意，這必須要建立在我們都是已知 mcb_id 都是確認已經存在於 displayer_realtime 之中。如果不能確認，則這個加速方式就是有問題，會造成錯誤
        # sql_update_realtime = """INSERT INTO displayer_realtime (id, condition_flg)
        #                          VALUES(%s, 3) AS new_data
        #                          ON DUPLICATE KEY UPDATE condition_flg = 3"""
        sql_update_realtime = """UPDATE displayer_realtime SET condition_flg = 3 WHERE id IN(%s)"""
        try:
            # 1. 目前暫時定義，超過機器本身設定的 raw data frequency 逾10分鐘沒有上線，即為斷線。
            #    PHP的 205（MCB Non-pairs）偵測是5分鐘。
            #    2024-03-04: James說要改成10分鐘
            #    2024-09-13: Nick決定開啟新的column = freq儲存現有的頻率秒速，並且在秒速以後的時間才算斷線
            # 2. 在判定它是否斷線之前，必須跳過1970-01-01(time=0)。因為它還沒有資料回傳過，不可以算是"發生"斷線，而是"持續"斷線中
            # 3. 如果這個機器處於刪除狀態，則排除計算它
            # 4. 目前這個程式只有真的 MCB Lost 發生的瞬間做偵測。而結束的判對則是寫在 PHP McbCtrl.php
            # 5. 查詢時，一定要比對event是否已經存在，並且他的時間還是1970-01-01 00:00:00，就無須再添加了。維持已經產生的起始日期。
            #    所以，只需要針對那些 e.id IS NULL 的序號做處理即可
            cursor.execute(
                """SELECT a.id, UNIX_TIMESTAMP( b.update_on )
                   FROM displayer AS a
                   INNER JOIN displayer_realtime AS b ON b.id = a.id
                   LEFT JOIN mcb_alarm_events AS e ON e.mcb_id = a.id AND e.alarm_type = 201 AND e.end_on = FROM_UNIXTIME(0)
                   WHERE a.status = 'A' AND
                         NOW() > DATE_ADD( b.update_on, INTERVAL( b.freq + 600 ) SECOND ) AND
                         ( b.condition_flg != 3 AND b.condition_flg != 0 ) AND
                         UNIX_TIMESTAMP( b.update_on ) > 0 AND
                         e.id IS NULL""" )
            displayers = cursor.fetchall()
            insert_buf = []
            update_buf = []
            # Insert new alarm event.
            for ( mcb_id, update_on ) in displayers:
                event_uuid = uuid.uuid5( uuid.uuid4(), hashlib.md5().hexdigest() )
                event_uuid = str( event_uuid ).replace( "-", "" )
                insert_buf.append( ( event_uuid, mcb_id, update_on ) )
                # 為了讓下面SQL UPDATE 之中，可以直接用逗號分隔每個item，並且加入字串之中。所以這邊先行轉成str
                update_buf.append( str(mcb_id) )

                Logger.logProcess (
                    "mcb=%s 201(MCB Lost) event created... uuid=%s, update_on=%s"
                    % ( mcb_id, event_uuid, datetime.fromtimestamp( update_on ).astimezone( pytz.utc ) )
                )
                # push into table
                if ( len( insert_buf ) >= 1000 ):
                    cursor.executemany( sql_insert_alarm_event, insert_buf )
                    # update_buf = [str(item) for item in update_buf]
                    cursor.execute( sql_update_realtime % (', '.join( update_buf ) ) )
                    del insert_buf[:]
                    del update_buf[:]
                    # In Python, you must commit the data after a sequence of INSERT, DELETE, and UPDATE statements
                    cnx.commit()
                    Logger.logProcess ( "...commit" )

            # Push into table id there is others not to full in list
            if ( len( insert_buf ) > 0 ):
                cursor.executemany( sql_insert_alarm_event, insert_buf )
                update_buf = [str(item) for item in update_buf]
                cursor.execute( sql_update_realtime % (', '.join( update_buf ) ) )
                del insert_buf[:]
                del update_buf[:]
                # In Python, you must commit the data after a sequence of INSERT, DELETE, and UPDATE statements
                cnx.commit()
                Logger.logProcess ( "...commit" )
        except Exception as e:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            fname = os.path.split( exc_tb.tb_frame.f_code.co_filename )[1]
            # print(exc_type, fname, exc_tb.tb_lineno)
            Logger.logError( "MCB alarm 201(MCB Lost) SQL error: %s; %s; %s; %s\n" % ( e, exc_type, fname, exc_tb.tb_lineno ) )
            # Rollback in case there is any error
            cnx.rollback()
        # close all connections.
        cursor.close()
        cnx.close()

    def getMcbTab ( self, page, per ):
        cnx = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        devices = None
        try:
            cursor.execute(
                """SELECT id FROM displayer ORDER BY id ASC LIMIT %s, %s""",
                ( page, per )
            )
            devices = cursor.fetchall()
        except Exception as error:
            Logger.logError( "mcb list error: %s" % error )
        cursor.close()
        cnx.close()
        return devices  # if return None, it means there is nothing!

    def getLastTimeOfKwh( self, mcb_id ) -> Union[int, None]:
        cnx = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        # look for the last generated data time.
        cursor.execute(
            """SELECT UNIX_TIMESTAMP( time_on )
               FROM displayer_kwh
               WHERE id = %s
               ORDER BY time_on DESC LIMIT 1""",
            ( mcb_id, )
        )
        row = cursor.fetchone()
        cursor.close()
        cnx.close()
        if row is not None:
            return row[0]
        return None # 必須回去 raw data table 之中找尋最初的那個資料時間點

    def getLastTimeOfRunning( self, mcb_id ):
        cnx = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        # look for the last generated data time.
        cursor.execute(
            """SELECT UNIX_TIMESTAMP( time_on )
               FROM displayer_runningtime
               WHERE id = %s
               ORDER BY time_on DESC LIMIT 1""",
            ( mcb_id, )
        )
        row = cursor.fetchone()
        cursor.close()
        cnx.close()
        if row is not None:
            return row[0] # 這裡不用轉換到下個小時的區段，切記！！跟kwh不同
        return None # 必須回去 raw data table 之中找尋最初、最末的那兩個資料時間點

    # params 如果是單一個 tuple，則會使用 execute。但是，如果是用多個 tuple 在 list 之中，則會自動使用 executemany
    def __insertSql( self, sql, params ):
        cnx = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        output = False
        try:
            if isinstance( params, tuple ):
                cursor.execute( sql, params )
            elif isinstance( params, list ):
                cursor.executemany( sql, params )
            else:
                raise("SQL insert params error: %s" % params )  # 停止所有程式
            cnx.commit()
            output = True
        except Exception as error:
            # Rollback in case there is any error
            cnx.rollback()
            Logger.logError( "INSERT syntax error in SQL: %s" % error )

        cursor.close()
        cnx.close()
        return output

    def addMcbKwh( self, data ):
        is_success = self.__insertSql(
            """INSERT INTO displayer_kwh( id, time_on, kWh, belong_to )
               VALUES( %s, FROM_UNIXTIME( %s ), %s, %s ) AS new_data
               ON DUPLICATE KEY UPDATE kWh = new_data.kWh""",
            data
        )
        if not is_success:
            Logger.logError( "save kWh error!" )
        return is_success

    def addMcbRunning( self, data ):
        is_success = self.__insertSql(
            """INSERT INTO displayer_runningtime ( id, time_on, running, belong_to )
               VALUES( %s, FROM_UNIXTIME( %s ), %s, %s ) AS new_data
               ON DUPLICATE KEY UPDATE running = new_data.running""",
            data
        )
        if not is_success:
            Logger.logError( "save running time error!" )
        return is_success
