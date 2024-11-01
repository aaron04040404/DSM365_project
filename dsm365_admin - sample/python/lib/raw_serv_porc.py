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
class RawDbProc (object):
    # DATETIME_SYS_BEGIN_TIME = "2017-05-19 00:00:00"
    UNIXTIME_SYS_BEGIN_TIME = 1495152000

    def __init__ ( self, main_db_config ):
        # define const
        self.RAW_DB_POOL_NAME = "RawDbProc_POOL_NAME"
        # setup connection pools: main server & raw server
        if mysqlDB.startCnxPool( main_db_config, self.RAW_DB_POOL_NAME, 1 ) is False:
            raise Exception( "main database connection pooling error!" )

    def __del__ ( self ):
        del self.RAW_DB_POOL_NAME

    def __selSql( self, sql, params:tuple ):
        cnx = mysqlDB.getConnection( self.RAW_DB_POOL_NAME )
        cursor = cnx.cursor()
        output = None
        try:
            cursor.execute( sql, params )
            output = cursor.fetchall()
        except Exception as e:
            Logger.logError( "SELECT syntax error in SQL: %s" % e )
        cursor.close()
        cnx.close()
        return output

    # When is_local = True, the considering of status = 0 must be removed
    def getCmdBeginEndOn( self, mcb_id, cmd, last_on, is_local: bool = False, is_beginning: bool = True ):
        min_max = "MIN" if is_beginning is True else "MAX"
        time_type = "record_time" if is_local is True else "create_on"
        status_type = "AND status = 0" if is_local is False else ""
        rows = self.__selSql(
            f"""SELECT UNIX_TIMESTAMP( {min_max}( {time_type} ) )
               FROM mcb_detail_record
               WHERE mcb_id = %s AND cmd = %s AND {time_type} <= FROM_UNIXTIME(%s) {status_type}""",
            ( mcb_id, cmd, last_on )
        )
        time_on = None
        if rows is not None:
            time_on = rows[0][0] if rows[0][0] else None
            # If the raw data start time is less than the system birthday,
            # assign the birthday to be the beginning.
            if time_on is not None and time_on < self.UNIXTIME_SYS_BEGIN_TIME:
                time_on = self.UNIXTIME_SYS_BEGIN_TIME
        return time_on

    # When is_local = True, the considering of status = 0 must be removed
    def getCmdTimeScope( self, mcb_id, cmd, last_on, is_local: bool = False ):
        time_type = "record_time" if is_local is True else "create_on"
        status_type = "AND status = 0" if is_local is False else ""
        rows = self.__selSql(
            f"""SELECT UNIX_TIMESTAMP( MIN( {time_type} ) ),
                      UNIX_TIMESTAMP( MAX( {time_type} ) )
               FROM mcb_detail_record
               WHERE mcb_id = %s AND cmd = %s AND {time_type} <= FROM_UNIXTIME(%s) {status_type}""",
            ( mcb_id, cmd, last_on )
        )
        time_on = {
            "start_on": None,
            "end_on": None
        }
        if rows is not None:
            time_on["start_on"] = rows[0][0] if rows[0] else None
            time_on["end_on"] = rows[0][1] if rows[0] else None
            # If the raw data start time is less than the system birthday,
            # assign the birthday to be the beginning.
            if time_on["start_on"] is not None and time_on["start_on"] < self.UNIXTIME_SYS_BEGIN_TIME:
                time_on["start_on"] = self.UNIXTIME_SYS_BEGIN_TIME
        return time_on

    # When is_local = True, the considering of status = 0 must be removed
    def countCmd( self, mcb_id, cmd, start_on, end_on, is_local: bool = False ):
        time_type = "record_time" if is_local is True else "create_on"
        status_type = "AND status = 0" if is_local is False else ""
        rows = self.__selSql(
            f"""SELECT COUNT(*)
               FROM mcb_detail_record
               WHERE mcb_id = %s AND cmd = %s AND
                     ( {time_type} >= FROM_UNIXTIME(%s) AND {time_type} < FROM_UNIXTIME(%s) ) {status_type}""",
            ( mcb_id, cmd, start_on, end_on )
        )
        output = None
        if rows is not None:
            output = rows[0][0] if rows[0] else None
        return output

    # 因為是計算 kWh 故以當地機器的時間計算。即便它調整時間，或是斷線後回補資料有備這次計算給擷取到，這樣的總值會比較接近真實。
    # 如果它 local time 時間錯誤，那就沒轍了，直接忽略它吧！要有正確的統計，至少也要把機器參數設定正確，是使用者基本的常識
    def getMcbKwh( self, mcb_id, start_on, end_on ):
        rows = self.__selSql(
            """SELECT JSON_UNQUOTE( JSON_EXTRACT( args, '$.AC.P' ) ), belong_to
               FROM mcb_detail_record
               WHERE mcb_id = %s AND cmd = 160 AND ( record_time >= FROM_UNIXTIME(%s) AND record_time < FROM_UNIXTIME(%s) )
               ORDER BY record_time ASC""",
            ( mcb_id, start_on, end_on )
        )
        return rows

    def getRunningTime( self, mcb_id, start_on, end_on ):
        # NOTE: 這邊跟上面kWh會不一樣，這邊必須要有點時間重疊在開頭結尾部份。
        # 2024-10-21: 加入了 status = 0 的條件。
        rows = self.__selSql(
            """SELECT mcb_id, DATE_FORMAT( create_on, '%%Y-%%m-%%d %%H:00:00') AS hours,
                      (
                          (
                              IFNULL( MAX( CONVERT( JSON_UNQUOTE( JSON_EXTRACT( args, '$.RUNNINGTIME' ) ), UNSIGNED INTEGER ) ), 0 ) * 3600 +
                              IFNULL( MAX( CONVERT( JSON_UNQUOTE( JSON_EXTRACT( args, '$.RUNNINGTIME_MIN' ) ), UNSIGNED INTEGER ) ), 0 ) * 60
                          ) - (
                              IFNULL( MIN( CONVERT( JSON_UNQUOTE( JSON_EXTRACT( args, '$.RUNNINGTIME' ) ), UNSIGNED INTEGER ) ), 0 ) * 3600 +
                              IFNULL( MIN( CONVERT( JSON_UNQUOTE( JSON_EXTRACT( args, '$.RUNNINGTIME_MIN' ) ), UNSIGNED INTEGER ) ), 0 ) * 60
                          )
                      ) AS running, belong_to
               FROM mcb_detail_record
               WHERE mcb_id = %s AND cmd = 76 AND status = 0
               AND ( create_on BETWEEN FROM_UNIXTIME( %s ) AND FROM_UNIXTIME( %s ) )
               GROUP BY belong_to, hours""",
            ( mcb_id, start_on, end_on )
        )
        return rows