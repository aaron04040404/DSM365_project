#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ===============================================================================================
# Computing all data recording of MCB Command, 4EH(78), calculate all alarm status, 
# and move MCB alarm information to right consumer user. Eventually, publish notifications to 
# right people immediately.
#
# update all modules via pip: (不要在root執行比較好)
#    pip list --outdated --format=freeze | grep -v '^\-e' | cut -d = -f 1 | xargs -n1 pip install -U
# IMPORTANT: 在 GCP 上的 VM 必須使用 pip list --outdated | cut -d ' ' -f 1 | xargs -n1 pip install -U
# ===============================================================================================
import sys
import os
import time
from datetime import datetime
from mysql.connector.constants import ClientFlag
from lib.log_proc import Logger

from lib.gn_config import gn_ConfReader as confReader
from lib.dsm365_notif_adm import Dsm365_Notification_Adm as adm_notif
from lib.dsm365_notif_tcc import Dsm365_Notification_Tcc as tcc_notif

import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from smtplib import SMTPException

DEBUG_NO_SEND = False   # turn to True for debug testing

ADMIN_NOTIFICATION_MAIL_TITLE_ARR = {
    '1':   "System Alert Notification | DynaScan365",
    '101': "Device Alert Notification | DynaScan365",
    '201': "User Alert Notification | DynaScan365",
    '301': "Update Alert Notification | DynaScan365",
    '401': "Remote Alert Notification | DynaScan365"
}

TCC_NOTIFICATION_MAIL_TITLE_ARR = {
    '1':   "System Alert Notification | Total Control Cloud",
    '101': "Device Alert Notification | Total Control Cloud",
    '201': "User Alert Notification | Total Control Cloud",
    '301': "Update Alert Notification | Total Control Cloud",
    '401': "Remote Alert Notification | Total Control Cloud"
}

def create_smtp_conn ( smtp_setting ):
    conn = None
    #Create SMTP connection object for sending the mail
    try:
        conn = smtplib.SMTP( smtp_setting['smtp_servname'], int( smtp_setting['smtp_servport'] ) ) #use gmail with port
        conn.starttls() #enable security
        conn.set_debuglevel( int( smtp_setting['smtp_debug_mode'] ) )
        conn.login( smtp_setting['user'], smtp_setting['password'] ) #login with mail_id and password
        
        Logger.logProcess( "smtp settings: server name: %s; server port: %s; user: %s; debug mode: %s" 
                           % ( smtp_setting['smtp_servname'], 
                               smtp_setting['smtp_servport'], 
                               smtp_setting['user'], 
                               smtp_setting['smtp_debug_mode'] ) )
    except SMTPException as e:
        Logger.logError( "SMTP connection fail: %s" % e )
    return conn

def mailsender ( smtp_setting, send_data ):
    if len( send_data ) == 0:
        return False
    smtp_conn = create_smtp_conn( smtp_setting )
    if smtp_conn is None:
        return False
    
    for ( mail_to, title, mail_content ) in send_data:
        # 防呆，基本是不可發生這些空值
        if ( len( mail_to ) == 0 ):
            Logger.logError( "empty email address\n" )
            continue
        elif ( len( title ) == 0 ):
            Logger.logError( "empty email title\n" )
            continue
        elif ( len( mail_content ) == 0 ):
            Logger.logError( "empty email content\n" )
            continue
        # Setup the MIME
        message            = MIMEMultipart()
        message['From']    = 'noreply@%s' % smtp_setting['domain']  # smtp_setting['user']
        message['To']      = mail_to
        message['Subject'] = title      #The subject line
        # The body and the attachments for the mail
        message.attach( MIMEText( mail_content, "html", "utf-8" ) )
        
        if DEBUG_NO_SEND is False:
            try:
                smtp_conn.sendmail( message['From'], message['To'], message.as_string() )
                del message
            except SMTPException as e:
                connect_status = 0
                try:
                    connect_status = smtp_conn.noop()[0]
                except:
                    connect_status = -1
                
                # re-connect to SMTP serv, when the connection is closed.
                if ( connect_status != 250 ):
                    smtp_conn = create_smtp_conn( smtp_setting )
                    if smtp_conn is None:
                        Logger.logError( "SMTP connection re-connected fail!" )
                        return False
                    else: 
                        # send the one you have to but stopped on this turn.
                        smtp_conn.sendmail( message['From'], message['To'], message.as_string() )
                else:
                    Logger.logError( "(X) %s unable to send email(%s): %s.\r\n" % ( mail_to, title, e ) )
                    
                del message
        else:
            Logger.logError( "(debug) %s sent (%s): don't worry! not realy sending.\r\n" % ( mail_to, title ) )
    smtp_conn.quit()
    return True

def admin_notif_mail_process ( admin_config, smtp_setting ):
    user_index = 0
    user_max_num = 25
    email_101_total = 0
    email_101_stop = False
    admin_notif_proc = adm_notif( admin_config )
    
    # ===== notification 101 category =====
    Logger.logProcess( "admin server notification 101 mailer [start]..." )
    while email_101_stop is not True:
        Logger.logProcess( "notification 101 [ %s - %s ]" % ( user_index, user_max_num ) )
        # IMPORTANT: 必須壓縮郵件，換句話說，每個人在發送的時候，要把所有內容壓在一次信件發出
        # So, in this function, it will export a compressed notification email list contents.
        data = admin_notif_proc.listNotificationEmail_101( user_index, user_max_num )
        mail_num = 0
        mails = []
        for email in data:
            if data[ email ]["count"] == 0:
                Logger.logProcess( "%s has no email content (skipped)" % email )
                continue
            
            Logger.logProcess( "generating %s email contents (%s)" % ( email, data[ email ]["count"] ) )
            
            mail_text = '<p>Hi!</p>You have a notification about:'
            for ( text, happen_on, descp, face_id, tag ) in data[ email ]["data"]:
                mail_text += """<br/>-"""
                mail_text += """<br/>%s+00:00""" % ( happen_on )
                mail_text += """<br/>%s""" % ( text )
                
                face_id = int(face_id)
                if ( face_id == 1 ):
                    mail_text += """<br/>face: front"""
                elif ( face_id == 2 ):
                    mail_text += """<br/>face: back"""
                # else: to do nothing
                
                if len( descp ) > 0:
                    mail_text += """<br/>description: %s""" % ( descp )
                if len( tag ) > 0:
                    _tag_list = tag.split(',')
                    mail_text += """<br/>tag(s):%s""" % ( ''.join( ' #' + str(x) for x in _tag_list ) )

            # if there are over 20 notifications, you have to add this sentence at the last line
            if data[ email ]["count"] > adm_notif.MAX_EMAIL_CONTENT_NUM_LIMIT:
                mail_text += """<p>There are %s warning contents unread on cloud... </p>""" % data[ email ]["count"]
                
            mail_text += """<p><a href="%s">Please click this link to watch what is happening!</a></p>""" % smtp_setting['url_ref_adm']

            mails.append( ( email, ADMIN_NOTIFICATION_MAIL_TITLE_ARR[ '101' ], mail_text ) )
            
            mail_num = len( mails )
            if mail_num >= 50:
                mailsender( smtp_setting, mails )
                email_101_total += mail_num
                del mails[:]     # remove ex-data
        if mail_num > 0:
            mailsender( smtp_setting, mails )
            email_101_total += mail_num
            del mails[:]    # remove ex-data
            
        if ( len( data ) < user_max_num ):  # there is no data on next turn
            email_101_stop = True
        else:
            user_index += user_max_num
        Logger.logProcess( "notification(101) sent %s email(s)..." % ( email_101_total ) )
        data.clear()
    Logger.logProcess( "admin server notification 101 mailer [end]... " )
    
    
    # ===== notification 1, 201, 301, 401, ... any other category =====
    
    
    del admin_notif_proc
    
def client_notif_mail_process ( admin_config, client_config, smtp_setting ):
    user_index = 0
    user_max_num = 25
    email_101_total = 0
    email_101_stop = False
    client_notif_proc = tcc_notif( admin_config, client_config )
    
    # ===== notification 101 category =====
    Logger.logProcess( "client(TCC) server notification 101 mailer [start]..." )
    while email_101_stop is not True:
        Logger.logProcess( "notification 101 [ %s - %s ]" % ( user_index, user_max_num ) )
        # IMPORTANT: 必須壓縮郵件，換句話說，每個人在發送的時候，要把所有內容壓在一次信件發出
        # So, in this function, it will export a compressed notification email list contents.
        data = client_notif_proc.listNotificationEmail_101( user_index, user_max_num )
        mail_num = 0
        mails = []
        for email in data:
            if data[ email ]["count"] == 0:
                Logger.logProcess( "%s has no email content (skipped)" % email )
                continue
            
            Logger.logProcess( "generating %s email contents (%s)" % ( email, data[ email ]["count"] ) )
            
            mail_text = '<p>Hi!</p>You have a notification about:'
            for ( text, happen_on, descp, face_id, tag ) in data[ email ]["data"]:
                mail_text += """<br/>-"""
                mail_text += """<br/>%s+00:00""" % ( happen_on )
                mail_text += """<br/>%s""" % ( text )
                
                face_id = int(face_id)
                if ( face_id == 1 ):
                    mail_text += """<br/>face: front"""
                elif ( face_id == 2 ):
                    mail_text += """<br/>face: back"""
                # else: to do nothing
                
                if len( descp ) > 0:
                    mail_text += """<br/>description: %s""" % ( descp )
                if len( tag ) > 0:
                    _tag_list = tag.split(',')
                    mail_text += """<br/>tag(s):%s""" % ( ''.join( ' #' + str(x) for x in _tag_list ) )
                
            # if there are over 20 notifications, you have to add this sentence at the last line
            if data[ email ]["count"] > adm_notif.MAX_EMAIL_CONTENT_NUM_LIMIT:
                mail_text += """<p>There are %s warning contents unread on cloud... </p>""" % data[ email ]["count"]
                
            mail_text += """<p><a href="%s">Please click this link to watch what is happening!</a></p>""" % smtp_setting['url_ref_tcc']

            mails.append( ( email, TCC_NOTIFICATION_MAIL_TITLE_ARR[ '101' ], mail_text ) )
            
            mail_num = len( mails )
            if mail_num >= 50:
                mailsender( smtp_setting, mails )
                email_101_total += mail_num
                del mails[:]     # remove ex-data
        if mail_num > 0:
            mailsender( smtp_setting, mails )
            email_101_total += mail_num
            del mails[:]    # remove ex-data
            
        if ( len( data ) < user_max_num ):  # there is no data on next turn
            email_101_stop = True
        else:
            user_index += user_max_num
        Logger.logProcess( "notification(101) sent %s email(s)..." % ( email_101_total ) )
        data.clear()
    Logger.logProcess( "client(TCC) server notification 101 mailer [end]... " )
    
    
    # ===== notification 1, 201, 301, 401, ... any other category =====
    
    
    del client_notif_proc
    
def main ( admin_config, client_config, smtp_setting ):
    # admin process
    admin_notif_mail_process( admin_config, smtp_setting )
    # client process
    client_notif_mail_process( admin_config, client_config, smtp_setting )
    
if __name__ == '__main__':
    # remodify log file name to separate
    Logger.setLogProcessFileName("notification-mail-proc")
    Logger.setLogErrorFileName("notification-mail-error")
    
    __config = os.path.dirname( os.path.abspath( __file__ ) ) + "/py_settings.conf"
    if os.path.isfile( __config ):
        Logger.logProcess( "config file read: %s" % __config )
    else:
        Logger.logError( "config file error!!" )
        raise Exception( "config file error!!" )
    # Convert database config format.
    admin_db_settings = confReader.db_configReader( __config, "DB_Alarm" )
    client_db_settings  = confReader.db_configReader( __config, "DB_Dsm365_Client" )
    gmail_smtp_settings = confReader.mail_smtp_configReader( __config, "GMAIL_SMTP" )

    try:
        while True:
            main( admin_db_settings, client_db_settings, gmail_smtp_settings )
            time.sleep( 10 * 60 )
            
            # TODO: 必須休息10分鐘, Gmail says
            
    except KeyboardInterrupt:
        Logger.logProcess( "Processing interrupted!" )
    
    