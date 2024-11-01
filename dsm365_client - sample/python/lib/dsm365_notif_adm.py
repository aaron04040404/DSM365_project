#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys, os
from datetime import datetime
import time
import json
from .database_mysql import MySQLDatabaseProc as mysqlDB
from .log_proc import Logger

# ========================================================================================================
# Important!
# All things in the class works for admin-side services.
# ========================================================================================================
class Dsm365_Notification_Adm (object):
    DATETIME_DEFAULT_FORMAT = "%Y-%m-%d %H:%M:%S"
    
    # Database connection body reference.
    def __init__ ( self, db_config ):
        # define const
        self.MYSQL_CONN_POOL_SIZE = 2
        self.DB_WRITE_BUFF_SIZE   = 5000
        self.DB_POOL_NAME         = "DSM365_WARNING_NOTIFICATION"
        
        if mysqlDB.startCnxPool( db_config, self.DB_POOL_NAME, self.MYSQL_CONN_POOL_SIZE ) is False:
            raise Exception( "Database connection pooling error" )
        
    def __del__ ( self ):
        del self.MYSQL_CONN_POOL_SIZE
        del self.DB_WRITE_BUFF_SIZE
        del self.DB_POOL_NAME
    
    MAX_EMAIL_CONTENT_NUM_LIMIT = 20
    NOTIFICATION_MAILED_CACHE_SIZE = 200
    # list displays for mailing and mark all of them are sent in this round.
    def listNotificationEmail_101( self, user_start_idx = 0, max_len = 25 ):
        output = {}
        cnx    = mysqlDB.getConnection( self.DB_POOL_NAME )
        cursor = cnx.cursor()
        # 有些alias 的使用是為了避開 python mysql connector 的 bug 而挑選的。如 tag 之中的 m_a.mcb_id。
        # python mysql connector 針對別名的識別，一個輸出格式只能用一個 SELECT #2 的別名一次。如果兩個輸出欄位都要用到同一個別名
        # 則會很容易時而可行，又時而不可行的問題發生
#         _sql_notification_sel = """SELECT a.content_id,
#                                           b.text,
#                                           b.happen_on,
#                                           a.is_read,
#                                           m_d.descp,
#                                           IF( m_dm.face_total_num > 1, m_dr.lcm_id, 0 ) AS face_id,
#                                           IFNULL( (
#                                               SELECT GROUP_CONCAT( DISTINCT( t2.tag ) )
#                                               FROM displayer_tag AS dt2
#                                               INNER JOIN tags AS t2 ON t2.uuid = dt2.tag_uuid
#                                               WHERE dt2.displayer_id = m_a.mcb_id
#                                           ), '' ) AS tag
#                                    FROM notification_main AS a
#                                    INNER JOIN notification_contents AS b ON b.content_id = a.content_id
#                                    INNER JOIN mcb_alarm_events AS m_a ON m_a.id = b.content_id
#                                    INNER JOIN displayer AS m_d ON m_d.id = m_a.mcb_id
#                                    INNER JOIN displayer_realtime AS m_dr ON m_dr.id = m_d.id
#                                    LEFT JOIN displayer_model AS m_dm ON m_dm.model = m_dr.model
#                                    WHERE a.category = 101 AND
#                                          a.to_who = %s AND
#                                          a.is_mailed = 0
#                                    ORDER BY b.happen_on DESC"""
        _sql_notification_sel = """SELECT HEX(a.content_id),
                                   	      b.text,
                                   	      b.happen_on,
                                   	      a.is_read,
                                   	      m_d.descp,
                                   	      IF( m_dm.face_total_num > 1, m_dr.lcm_id, 0 ) AS face_id,
                                   	      GROUP_CONCAT( DISTINCT( IFNULL( t2.tag, '' ) ) ) AS tag
                                   FROM notification_main AS a
                                   INNER JOIN notification_contents AS b ON b.content_id = a.content_id
                                   INNER JOIN mcb_alarm_events AS m_a ON m_a.id = b.content_id
                                   INNER JOIN displayer AS m_d ON m_d.id = m_a.mcb_id
                                   INNER JOIN displayer_realtime AS m_dr ON m_dr.id = m_d.id
                                   LEFT JOIN displayer_model AS m_dm ON m_dm.model = m_dr.model
                                   LEFT JOIN displayer_tag AS dt2 ON dt2.displayer_id = m_a.mcb_id
                                   LEFT JOIN tags AS t2 ON t2.uuid = dt2.tag_uuid
                                   WHERE a.category = 101 AND
                                         a.to_who = %s AND
                                         a.is_mailed = 0
                                   GROUP BY a.content_id,
                                   	        b.text,
                                   	        b.happen_on,
                                   	        a.is_read,
                                   	        m_d.descp,
                                   	        face_id
                                   ORDER BY b.happen_on DESC"""

#         _sql_update_mark = """UPDATE notification_main SET is_mailed = 1
#                               WHERE category = %s AND 
#                                     content_id = UNHEX(%s) AND
#                                     to_who = %s"""
        # 網路上查到，如果大量的 UPDATE 會慢很多時間。用INSERT INTO ... ON DUPLICATE KEY UPDATE ... 的方式會快很多。
        # 主因是因為再 python 編譯時候的差別。這一點不同於 C/C++ 或是 PHP 
        _sql_update_mark = """INSERT INTO notification_main ( category, content_id, to_who, is_mailed )
                              VALUES( 101, UNHEX(%s), %s, 1 ) AS new_data
                              ON DUPLICATE KEY UPDATE is_mailed = 1"""
        try:
            # IMPORTANT: When member status is 2(block), system don't send email, just turn is_mailed flag to 1.
            # NOTE: 因為 notification_email_list 是使用 INNER JOIN 。所以，只要該帳號並無啟用 email 通知的功能
            #       則搜尋出來的列表將不會有任何資料產生
            cursor.execute(
                """SELECT a.mem_id, b.email
                   FROM notification_email_list AS a
                   INNER JOIN member AS b ON b.id = a.mem_id
                   WHERE b.status = 1
                   ORDER BY a.mem_id ASC
                   LIMIT %s, %s""", ( user_start_idx, max_len ) )
            user_rows = cursor.fetchall()
            for ( user_id, email ) in user_rows:
                # send email if it is not read & member's account is active and
                # there is any result existed in list.
                cursor.execute( _sql_notification_sel % ( user_id, ) )
                rows = cursor.fetchall()
                output[ email ] = {
                    "count": 0,
                    "data": []
                }
                mark_cache = []

                Logger.logProcess( "%s is marking notifications [start] (num=%s)" % ( email, len( rows ) ) )

                for ( content_id, text, happen_on, is_read, descp, face_id, tag ) in rows:
                    # NOTE: 因為python的版本不同，所以MySQL GROUP_CONCAT 指令輸出的資料型態也可能是不同。(bytearray or str)
                    if isinstance(tag, bytes):
                        tag = tag.decode('utf-8')   # 確保tag變量是一個字串對象而不是字節對象, 將bytes轉換為utf-8字串

                    mark_cache.append( ( content_id, user_id ) )
                    if is_read == 0:
                        output[ email ]["count"] += 1
                        # NOTE: prevent export too many email content to email the user you want to send,
                        #       so if the number is out of export limit number, you have to stop to insert more contents,
                        #       but you have to keep counting data number.
                        if output[ email ]["count"] < Dsm365_Notification_Adm.MAX_EMAIL_CONTENT_NUM_LIMIT:
                            # 2022-08-03 add descp, tag output columns for email text by Nick
                            output[ email ]["data"].append( ( text, happen_on, descp, face_id, tag ) )

                    if ( len(mark_cache) >= Dsm365_Notification_Adm.NOTIFICATION_MAILED_CACHE_SIZE ):
                        #if cnx.in_transaction is not True:
                        #    cnx.start_transaction()
                        cursor.executemany( _sql_update_mark, mark_cache )
                        cnx.commit()
                        del mark_cache[:]

                if ( len(mark_cache) > 0 ):
                    #if cnx.in_transaction is not True:
                    #    cnx.start_transaction()
                    cursor.executemany( _sql_update_mark, mark_cache )
                    cnx.commit()
                    del mark_cache[:]

                Logger.logProcess( "%s is marking notifications [end]" % ( email ) )
        except Exception as e:
            exc_type, exc_obj, exc_tb = sys.exc_info()
            fname = os.path.split( exc_tb.tb_frame.f_code.co_filename )[1]
            Logger.logError(
                "admin notification(101) mail list process error: %s -> %s; %s; %s"
                % ( e, exc_type, fname, exc_tb.tb_lineno )
            )

            if cnx.in_transaction:
                cnx.rollback()
                Logger.logError( "admin notification(101) mail sent mark error!" )

        cursor.close()
        cnx.close()
        return output
