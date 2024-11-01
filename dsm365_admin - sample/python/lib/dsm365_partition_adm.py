import os
from datetime import datetime, timezone
from dateutil.relativedelta import relativedelta
from .database_mysql import MySQLDatabaseProc as mysqlDB
from .log_proc import Logger
#from gn_config import gn_ConfReader as confReader


class Partition():

    def __init__(self, main_db_config):
        
        self.DB_POOL_NAME = "Partition_POOL_NAME"
        if mysqlDB.startCnxPool( main_db_config, self.DB_POOL_NAME, 1 ) is False:
            raise Exception( "main database connection pooling error!" )
        
    def __del__(self):
        del self.DB_POOL_NAME

    def add_Partition(self, i):


        cnx = mysqlDB.getConnection(self.DB_POOL_NAME)
        cursor = cnx.cursor()
        sql_add_partition = """ALTER TABLE `dynascan365_main`.`mcb_detail_record`
                               ADD PARTITION (PARTITION p_%s_%02d VALUES LESS THAN ( UNIX_TIMESTAMP ('%s') ))"""
        
        utc_time = datetime.now(timezone.utc).replace(tzinfo=None)
        Months = ["Jan.", "Feb.", "Mar.", "Apr.", "May.", "Jun.", "Jul.", "Aug.", "Sept.", "Oct.", "Nov.", "Dec."]
        #utc_time = datetime.strptime("2024-10-16 00:00:00", "%Y-%m-%d %H:%M:%S")

        if(i==0 and utc_time.day >= 15): #這裡多一個檢查是怕伺服器重新啟動時跨過了15號這樣就會少加一個partition(但應該基本上不太會發生)
            try:
                #取當月15號往後推兩個月的第一天並且時間設為 00:00:00
                partition_date = (utc_time + relativedelta(months=2)).replace(day=1, hour=0, minute=0, second=0, microsecond=0) 

                #取年的後兩碼 2024->24, 2025->25....
                year = str(partition_date.year)[-2:] 
                month = partition_date.month
                #print(sql_add_partition % (year, month, partition_date))
                cursor.execute(sql_add_partition % (year, month, partition_date))
                
                # %02d 當月份是個位數時前面加一個0
                Logger.logProcess("Add the partition p_%s_%02d for %s to %s" % (year, month, Months[int(month)-2], Months[int(month)-1]))
                cnx.commit()
                Logger.logProcess ( "...commit" )
                
            except Exception as e:
                Logger.logError ("ERROR!!!! %s" % (e))              
            finally:
                cursor.close()
                cnx.close()

        elif(utc_time.day == 15):
            try:
                #取當月15號往後推兩個月的第一天並且時間設為 00:00:00
                partition_date = (utc_time + relativedelta(months=2)).replace(day=1, hour=0, minute=0, second=0, microsecond=0) 

                #取年的後兩碼 2024->24, 2025->25....
                year = str(partition_date.year)[-2:] 
                month = partition_date.month
                #print(sql_add_partition % (year, month, partition_date))
                cursor.execute(sql_add_partition % (year, month, partition_date))
                
                # %02d 當月份是個位數時前面加一個0
                Logger.logProcess("Add the partition p_%s_%02d for %s to %s" % (year, month, Months[int(month)-2], Months[int(month)-1]))
                cnx.commit()
                Logger.logProcess ( "...commit" )
                
            except Exception as e:
                Logger.logError ("ERROR!!!! %s" % (e))              
            finally:
                cursor.close()
                cnx.close()


        else:
            Logger.logProcess("No action")
            cursor.close()
            cnx.close()

"""if __name__ == "__main__" :

    __config = os.path.dirname(os.path.dirname( os.path.abspath( __file__ ) )) + "/py_settings.conf"
    main_db_admin_settings  = confReader.db_configReader( __config, "mysql_On-Premises" )
    partition = Partition(main_db_admin_settings)
    partition.add_Partition()
    #print(__config)"""