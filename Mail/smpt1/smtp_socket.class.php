<?php
/**
 * Tool for connecting and executing commands to an SMTP-server
 * Copyright (C) 2006  Anton Makarenko
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @author  Anton Makarenko <php at ripfolio dot com>
 * @package Mailman
 * @version 1.3
 */
class Smtp_socket
{
	var $res;
	var $verbose;
	var $log;

	/**
	 * Create SMTP socket connector
	 *
	 * @param bool $verbose
	 * @return Smtp_socket
	 */
	function Smtp_socket($verbose=false)
	{
		$this->verbose=$verbose;
		$this->res=false;
	}

	/**
	 * Push a message into log or read all messages
	 *
	 * Call without params
	 *
	 * @param string $msg
	 * @return string
	 */
	function log($msg='')
	{
			$this->log[]=$msg;
		return '';
		if ($this->verbose)
		{
			if (count(func_get_args())===0)
				return implode("\r\n", $this->log);
			$this->log[]=$msg;
		}
		return '';
	}

	/**
	 * Open socket connection
	 *
	 * @param string $host
	 * @param int $port
	 * @param int $timeout
	 * @return resource|false
	 */
	function connect($host, $port=25, $timeout=30)
	{
		if ($this->res)
			return $this->res;
		$err_n=0; $err_str='';
		@$this->res=fsockopen($host, $port, $err_n, $err_str, $timeout);
		if ($this->res)
			$this->log(__CLASS__.'::'.__FUNCTION__.'() OK');
		else
			$this->log(__CLASS__.'::'.__FUNCTION__."() Error $err_n: $err_str");
		$this->log($this->_get_lines());
		return $this->res;
	}

	/**
	 * Close socket connection
	 *
	 * @return false
	 */
	function disconnect()
	{
		if ($this->res)
		{
			fclose($this->res);
			$this->res=false;
			$this->log(__CLASS__.'::'.__FUNCTION__.'() OK');
			return true;
		}
		$this->log(__CLASS__.'::'.__FUNCTION__.'() Failed');
		return false;
	}

	/**
	 * Read server response
	 *
	 * @return string
	 */
	function _get_lines()
	{
		$result='';
		while($str=fgets($this->res, 515))
		{
			$result.=$str;
			// done reading if the 4th character is space
			if(substr($str, 3, 1)===' ') {break;}
		}
		return $result;
	}

	/**
	 * Run a command and look for one of success codes
	 *
	 * @param string $command
	 * @param int $success_code [other success code, ...]
	 * @return string|false
	 */
	function execute($command, $success_code)
	{
		if (!$this->res)
			return false;
		$args=func_get_args();
		if (count($args)<2)
			return false;
		$command=@array_shift($args);
		$success_codes=array();
		foreach ($args as $arg)
			$success_codes[]=abs($arg);
		$this->log($command);
		fputs($this->res, $command."\r\n");
		$response=$this->_get_lines();
		// if server returns successful answer
		foreach ($success_codes as $code)
			if (false!==strpos($response, (string)$code))
			{
				$this->log($response);
				return $response;
			}
		$this->log($response);
		return false;
	}
}
?>