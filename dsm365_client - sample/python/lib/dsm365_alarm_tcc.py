#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys, os
import pytz
import time
import json
from datetime import datetime
from .database_mysql import MySQLDatabaseProc as mysqlDB
from .log_proc import Logger

# install bcrypt lib: $ pip install bcrypt
# for Ubuntu the following command will ensure that the required dependencies are installed:
# sudo apt-get install build-essential libffi-dev python-dev
#import bcrypt

# ========================================================================================================
# Important!
# All things in the class works for main client-side services.
# ========================================================================================================
class Dsm365_Alarm_Tcc( object ):

    def __init__ ( self, admin_db_config, client_db_config ):
        # define const
        self.MYSQL_CONN_POOL_SIZE = 1
        self.DB_WRITE_BUFF_SIZE   = 5000
        
        # I need two connections to two different databases. 
        self.ADMIN_DB_POOL_NAME  = "DSM365_TIME_ADMIN"
        self.CLIENT_DB_POOL_NAME = "DSM365_TIME_CLIENT"
        
        if mysqlDB.startCnxPool( admin_db_config, self.ADMIN_DB_POOL_NAME, self.MYSQL_CONN_POOL_SIZE ) is False:
            raise Exception( "Database connection pooling error" )
        if mysqlDB.startCnxPool( client_db_config, self.CLIENT_DB_POOL_NAME, self.MYSQL_CONN_POOL_SIZE ) is False:
            raise Exception( "Database connection pooling error" )
        
    def __del__ ( self ):
        del self.MYSQL_CONN_POOL_SIZE
        del self.DB_WRITE_BUFF_SIZE
        del self.ADMIN_DB_POOL_NAME
        del self.CLIENT_DB_POOL_NAME
        
    def syncNotificationContents ( self ):
        cnx_client = mysqlDB.getConnection( self.CLIENT_DB_POOL_NAME )
        cursor_client = cnx_client.cursor()
        lastAlarmGroups = {}
        # {
        #    '104': "1970-01-01 00:00:00", # set default start from 0. @2024-03-29 moved to PHP
        #    '201': "1970-01-01 00:00:00"  # set default start from 0.
        # }
        try:
            # only fetch alarms about time.
            # 要先檢查 是否有開啟計算該 alarm code 的機制，有再去計算
            cursor_client.execute( """SELECT id FROM mcb_alarm_type WHERE id IN ( 201 ) AND level >= 2""" )
            cursor_cache = cursor_client.fetchall()
            if ( len( cursor_cache ) == 0 ):
                Logger.logProcess( "All notifications are closed about 201(lost mcb)" )
                return
            for (id,) in cursor_cache:
                id = str( id )
                lastAlarmGroups[ id ] = 0   # set default start from 0.

            Logger.logProcess( "notifications(category=101): warning code [" + ( ','.join(str(x) for (x,) in cursor_cache) ) + "]")
            
            # take out all the last time of each notification content.
            for alarm_code in lastAlarmGroups:
                cursor_client.execute(
                    """SELECT UNIX_TIMESTAMP( happen_on )
                       FROM notification_contents 
                       WHERE category = 101 AND JSON_EXTRACT( param, '$.alarm_type') = %s
                       ORDER BY happen_on DESC 
                       LIMIT 1""" % ( alarm_code ) )
                row = cursor_client.fetchone()
                if row is not None:
                    lastAlarmGroups[ alarm_code ] = row[0]
        except Exception as e:
            # error write to log txt file.
            Logger.logError( "MCB lost notifications error: %s.\n" % e )
        
        # close all connections.
        cursor_client.close()
        cnx_client.close()
        
        # fetch notification contents via each active alarm code
        for alarm_code in lastAlarmGroups:
            Logger.logProcess(
                "generate [%s] from %s" %
                ( alarm_code, datetime.fromtimestamp( lastAlarmGroups[ alarm_code ] ).astimezone( pytz.utc ) )
            )
            # 這邊必須確保 alarm code 必須是 int ，盡量不要是 string 當參數傳進去
            self.__triggerMcbAlarmNotification( int(alarm_code), lastAlarmGroups[ alarm_code ] )
        
    # Create notification contents of MCB alarm events.
    def __triggerMcbAlarmNotification ( self, alarm_type, lastHappenedOn ):
        cnx_admin  = mysqlDB.getConnection( self.ADMIN_DB_POOL_NAME )
        cnx_client = mysqlDB.getConnection( self.CLIENT_DB_POOL_NAME )
        
        cursor_admin  = cnx_admin.cursor()
        cursor_client = cnx_client.cursor()
        
        insertManyData_content = []
        insertManyData_main    = []
        startIndex = 0
        while True:
            # you must ensure the event analyzing process is stopped before this processing starts.
            # only the level higher than 2 can be insert into notification content table.
            # P.S. level 0 is inactivate event, and 1 is muted event(but you can see it in analytics).
            cursor_admin.execute( 
                """SELECT HEX(a.id),
                          b.name,
                          IF( dm.is_kiosk = 1, IF( d.bonding = '', c.sn, d.bonding ), c.sn ) AS asset_sn,
                          c.sn AS lcm_sn,
                          c.id,
                          a.start_on,
                          UNIX_TIMESTAMP( a.end_on ) AS end_on
                   FROM mcb_alarm_events AS a 
                   INNER JOIN mcb_alarm_type AS b ON b.id = a.alarm_type 
                   INNER JOIN displayer AS c ON c.id = a.mcb_id 
                   INNER JOIN displayer_realtime AS d ON d.id = c.id
                   LEFT JOIN displayer_model AS dm ON dm.model = c.model
                   WHERE a.alarm_type = %s AND 
                         a.start_on > FROM_UNIXTIME(%s) AND 
                         b.level >= 2 
                   ORDER BY a.start_on LIMIT %s, %s"""  
                % ( alarm_type, lastHappenedOn, startIndex, self.DB_WRITE_BUFF_SIZE ) )
            rows = cursor_admin.fetchall()
            for ( id, alarmTitle, asset_sn, lcm_sn, displayer_id, startTime, endTime ) in rows:
                urlEndTimestamp = endTime # 1970-01-01-00:00:00 is for unfinished warning.
                if ( urlEndTimestamp == 0 ):
                    # urlEndTimestamp = ( startTime + timedelta( hours = 1 ) ).strftime( "%Y%m%d%H%M%S" )
                    urlEndTimestamp = int( time.mktime( datetime.now().timetuple() ) ) # because all recording time is UTC.
                    # Because it use timestamp, so DON'T use utcnow().timetuple() for this part.
                    # When the time.mktime start to convert it, you will lost hours.It makes you start from UTC time,
                    # and then to get the UTC timestamp.
                
                # URL is like http://127.0.0.1/displayer/warn/444?s=1620033013
                insertManyData_content.append( (
                    id,
                    ( alarmTitle + "-> " + asset_sn + ( "" if asset_sn == lcm_sn else ( " on LCM " + lcm_sn + " side" ) ) ), 
                    json.dumps(
                        {
                            "alarm_type": alarm_type,
                            "mcb_id": displayer_id,
                            "end_time": urlEndTimestamp,
                            "main_sn": asset_sn
                        }
                    ),
                    startTime
                ) )
                #for notification main table inserting to right people
                insertManyData_main.append( (
                    id,
                    alarm_type,
                    displayer_id,
                    displayer_id,
                    displayer_id,
                    displayer_id,
                    displayer_id,
                    displayer_id,
                    displayer_id
                ) )
                
            if ( len( insertManyData_content ) > 0 ):
                # create new notification contents
                try:
                    cursor_client.executemany( 
                        """INSERT INTO notification_contents( content_id, category, text, param, happen_on, notified ) 
                           VALUES( UNHEX(%s), 101, %s, %s, %s, 1 ) AS new_data
                           ON DUPLICATE KEY UPDATE notified = 1""" , insertManyData_content )
                    
                    Logger.logProcess( "notification(101: %s) contents saved(%s)" % ( alarm_type, cursor_client.rowcount ) )
                    
                    # Get users via right company member and right permissions.
                    # NOTE: Don't dispatch to wrong member in wrong company.
                    # IMPORTANT: 請參考display network與member的關聯性，訊息只發給有關連的人。
                    #            super-admin & admin 無須經過 display network 的規範限制
                    # NOTE: 如果該人員沒有開啟 notification 需要 email 的功能，則 is_mailed 值必須填入1。
                    #       表示無須再寄送郵件，此次直接跳過。
                    
                    # 2023-09-26 加入了 stage & network & role 的過濾之後，有符合所有條件者
                    # 才去判斷 mcb_alarm_block_list 是否需要產生
                    # get all member ID that system have to generate the notification to
                    # array = [ uuid, alarm type id, displayer id, displayer id, displayer id, displayer id, displayer id, displayer id, displayer id ]
                    cursor_client.executemany(
                        """INSERT INTO notification_main( to_who, category, content_id, is_mailed )
                           SELECT * FROM (
                               SELECT m.id, 101, UNHEX(%s), IF( ( m_e.mem_id IS NULL ), 1, 0 ) AS new_is_mailed
                               FROM member AS m
                               LEFT JOIN notification_email_list AS m_e ON m_e.category_id = 101 AND m_e.mem_id = m.id
                               LEFT JOIN mcb_alarm_block_list AS bb ON bb.mem_id = m.id AND
                                                                       bb.alarm_type_id = %s AND
                                                                       ( bb.mcb_id = %s OR bb.mcb_id = 0 )
                               WHERE bb.mem_id IS NULL AND 
                                     m.id IN (
                                         SELECT t.id
                                         FROM (
                                             (
                                                 SELECT id, type, role_id 
                                                 FROM member 
                                                 WHERE type <= 2 AND company_id = (
                                                    SELECT belong_to FROM displayer WHERE id = %s
                                                 )
                                             ) UNION (
                                                 SELECT e.id, e.type, e.role_id
                                                 FROM displayer AS a
                                                 INNER JOIN displayer_network_mcb AS b ON b.mcb_id = a.id
                                                 INNER JOIN displayer_network AS c ON c.uuid = b.net_uuid
                                                 INNER JOIN displayer_network_mem AS d ON d.net_uuid = c.uuid
                                                 INNER JOIN member AS e ON e.id = d.mem_id
                                                 WHERE a.id = %s AND 
                                                       a.status = 'A' AND
                                                       c.status >= 1 AND 
                                                       c.company_id = ( SELECT belong_to FROM displayer WHERE id = %s ) AND
                                                       e.status IN ( 1, 2 ) AND 
                                                       e.company_id = ( SELECT belong_to FROM displayer WHERE id = %s ) AND 
                                                       e.type > 2 
                                                 GROUP BY e.id, e.type, e.role_id
                                             ) 
                                         ) AS t
                                         INNER JOIN roles_properties AS r ON t.role_id = r.role_id
                                         WHERE ( r.displayer > 0 OR r.displayer_warn > 0 ) AND 
                                               ( t.type = 1 OR t.id IN (
                                                   SELECT mem_id
                                                   FROM displayer_situation_mem
                                                   WHERE company_id = ( SELECT belong_to FROM displayer WHERE id = %s ) AND
                                                         life_code = ( SELECT situation FROM displayer WHERE id = %s AND status = 'A' )
                                               ) )
                                     )
                           ) AS new_data
                           ON DUPLICATE KEY UPDATE is_mailed = new_data.new_is_mailed""", insertManyData_main )
                    
                    Logger.logProcess( "notification(101: %s) are broadcast(%s)" % ( alarm_type, cursor_client.rowcount ) )
                    
                    cnx_client.commit()
                except Exception as error:
                    exc_type, exc_obj, exc_tb = sys.exc_info()
                    fname = os.path.split( exc_tb.tb_frame.f_code.co_filename )[ 1 ]
                    print(exc_type, fname, exc_tb.tb_lineno)
                    # Rollback in case there is any error
                    cnx_client.rollback()
                    Logger.logError( "%s notification SQL error: %s" % ( alarm_type, error ) )

                # clean older in memory.
                del insertManyData_content[:]
                del insertManyData_main[:]
                
            if self.DB_WRITE_BUFF_SIZE == len( rows ):
                startIndex += self.DB_WRITE_BUFF_SIZE
            else:
                break # leave the while loop.
            
        # close all connections.
        cursor_admin.close()
        cursor_client.close()
        cnx_admin.close()
        cnx_client.close()