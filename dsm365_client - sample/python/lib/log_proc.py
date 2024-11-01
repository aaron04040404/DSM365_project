#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import datetime
import os
#from uaclient.actions import status

class Logger:
    PRINT_TO_TERMINAL = True
    
    LOG_FILE_PATH = os.path.dirname( os.path.abspath( __file__ ) ) + "/../log" # directory of log
    #LOG_FILE_PATH = "/var/www/dynabo/python"
    
    DATETIME_DEFAULT_FORMAT = "%Y-%m-%d %H:%M:%S"
    
    LOG_PROC_FILE_NAME  = "process"
    LOG_ERROR_FILE_NAME = "error"
    
    @staticmethod
    def writeLOG ( file_name, msg ):
        # get current time for log text content to save
        now_time = datetime.datetime.now()
        # generate text with time string
        msg = "[" + now_time.strftime( Logger.DATETIME_DEFAULT_FORMAT ) + "] " + msg
        if Logger.PRINT_TO_TERMINAL:
            print ( msg )
        
        with open( Logger.LOG_FILE_PATH + "/" + file_name + ".log." + now_time.strftime( "%Y%m%d" ), "a" ) as f:
            f.write( msg + "\n" )
            f.flush()
            f.close()
        
        # now_time = datetime.datetime.now()
        #
        # msg = "[" + now_time.strftime( Logger.DATETIME_DEFAULT_FORMAT ) + "] " + msg
        # if Logger.PRINT_TO_TERMINAL:
        #     print ( msg )
        #
        # text_file = open( Logger.LOG_FILE_PATH + "/" + file_name + ".log." + now_time.strftime( "%Y%m%d" ), "a" )
        # text_file.flush()   # prevent cache has been full to be not writable 
        # text_file.write( msg + "\n" )
        # text_file.close()
        
    @staticmethod
    def setLogProcessFileName (filename):
        Logger.LOG_PROC_FILE_NAME = filename
        
    @staticmethod
    def setLogErrorFileName (filename):
        Logger.LOG_ERROR_FILE_NAME = filename
    
    @staticmethod
    def logProcess (msg, filename = None):
        if ( filename is None or isinstance( filename, str ) is False or len( filename ) == 0 ):
            Logger.writeLOG( Logger.LOG_PROC_FILE_NAME, msg )
        else:
            Logger.writeLOG( filename, msg )
    
    @staticmethod
    def logError (msg, filename = None):
        if ( filename is None or isinstance( filename, str ) is False or len( filename ) == 0 ):
            Logger.writeLOG( Logger.LOG_ERROR_FILE_NAME, msg )
        else:
            Logger.writeLOG( filename, "<<Error>>: " + msg )
        
        
        
        
        
        