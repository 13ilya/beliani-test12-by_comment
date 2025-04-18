<?php
require_once 'lib/Minifier.php';

class Deploy {
	public static $dev_ip = array('138.201.124.197', '178.63.27.78');

	public $log_file = 'deployments.log';
    public $branch;
	public $directory;
	public $remote = 'origin';
	
	private $_messages = array();
    private $_date_format = 'Y-m-d H:i:s';
	private $_is_production;
	private $_user;
	
    public $post_deploy;

    public function __construct($directory = null)
    {
		global $loggedUser;	
	  
		// Environment
		$this->_is_production = !(in_array($_SERVER['SERVER_ADDR'], self::$dev_ip) 
			|| $_SERVER['HTTP_HOST'] == 'www.beliani.net'
			|| $_SERVER['HTTP_HOST'] == 'dev.prologistics.info');

		// Branch
		if ($this->_is_production) {
		    $this->branch = 'master2';
		} else {
		    if ($_SERVER['HTTP_HOST'] == 'proloheap.prologistics.info') {
		        $this->branch = 'heap';
		    } else {
		        $this->branch = 'develop';
		    }
		}

		// Path
        $this->directory = realpath($directory).DIRECTORY_SEPARATOR;
		
		// Current user
		if (isset($loggedUser))
			$this->_user = $loggedUser;
    }

    public function execute()
    {
		try
		{
			// development
			if (!$this->_is_production)
			{
				$this->_deploy();
			}
			// production 
			elseif ($this->_user && $this->_user->get('admin'))
			{
				$this->_deploy();
			}
			else
			{
				die();
			}
			
			if (is_callable($this->post_deploy))
				call_user_func($this->post_deploy, $this->_data);

			$this->log('Deployment end');
		}
		catch (Exception $e)
		{
			$this->log($e, 'ERROR');
		}
    }
	
	private function _deploy()
	{
		$this->log('Attempting deployment...');
	
		$this->_execute('cd ' . $this->directory);
		$this->_execute('git reset --hard HEAD');
		$this->_execute('git pull '.$this->remote.' '.$this->branch);
        
        $this->minify();
		
		if ($this->_is_production)
		{
			$command = 'rm core.*';
			$this->_execute($command);
        
			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/excl /DISK/prologistics.info/public_html prologisticssh@148.251.40.98:';
			$this->_execute($command);

			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/excl /DISK/prologistics.info/public_html prologisticssh@178.63.19.201:';
			$this->_execute($command);
			
			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/.ssh/excl /DISK/prologistics.info/public_html/ widmer0815@62.75.165.227:/var/www/vhosts/euve73078.serverprofi24.de/site2/';
			$this->_execute($command);
			
			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/.ssh/excl /DISK/prologistics.info/public_html/ admin2@85.25.64.63:/var/www/vhosts/euve34483.vserver.de/httpdocs/';
			$this->_execute($command);
			
			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/.ssh/excl /DISK/prologistics.info/public_html/ admin1@85.214.114.25:/var/www/vhosts/prologistics.info/httpdocs';
			$this->_execute($command);

			$command = 'rsync -av --exclude-from=/DISK/prologistics.info/.ssh/excl /DISK/prologistics.info/public_html/ prologistics@85.214.135.134:/var/www/vhosts/prologistics.info/httpdocs';
			$this->_execute($command);
		} elseif ($this->branch === 'develop') {
			$hasBadCommit = exec('git log --since="2016-12-14" | grep "Merge branch \'heap\'"');
			if (!empty($hasBadCommit)) {
				fclose(fopen(dirname(__DIR__) . '/HEAPMERGEDINDICATOR', 'w+'));
			}
		}
	}
    
    /**
     * Run minify for css/js
     */
    public function minify() 
    {
        $this->log('Starting minify...');
    
    	$minifier = new Minifier();

		try {
			$minifier->minify();

            if ($this->_is_production) {
				$command = 'rsync -av --exclude-from=/DISK/prologistics.info/excl /DISK/prologistics.info/public_html/css prologisticssh@148.251.40.98:public_html/';
                $this->_execute($command);
				$command = 'rsync -av --exclude-from=/DISK/prologistics.info/excl /DISK/prologistics.info/public_html/js  prologisticssh@148.251.40.98:public_html/';
				$this->_execute($command);
			}
		} catch (Exception $e) {
            $this->log($e->getMessage());
		}
    }
	
	private function _execute($command)
	{
		$this->log("Executing: '$command'");
		exec($command . ' 2>&1', $output, $return);
		$this->log("Output: '" . implode(' ', $output) . "', Return: " . print_r(substr($return, 0, 500), true));
	}

	private function _getUsername()
	{
		return $this->_user ? $this->_user->get('name') : 'auto';
	}

    public function log($message, $type = 'INFO')
    {
		$row = date($this->_date_format).'  [' . $this->_getUsername() .'] ' . $type . ': '.$message;
		$this->_messages[] = $row;
		
        if ($this->log_file)
        {
            $filename = $this->log_file;

            if (!file_exists($filename))
            {
                file_put_contents($filename, '');
                chmod($filename, 0666);
            }

            file_put_contents($filename, $row.PHP_EOL, FILE_APPEND);
        }
    }
	
	public function getMessages()
	{
		return $this->_messages;
	}
}
?>