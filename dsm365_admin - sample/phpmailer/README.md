Please execute mailer-for-os.php by system() in php file

E.g.
    system('nohup php '.$this->settings['os_script_url'].' '.$this->m_settings.' '.$out.' >> '.$this->settings['log_url'].' 2>&1 &');