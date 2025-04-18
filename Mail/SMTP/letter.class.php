<?php
/**
 * A simple-featured utility for creating letter body in MIME
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
 * @version 1.5
 */
class Letter
{
	var $charset;
	var $headers;
	var $message;
	var $attachments;

	var $_body;

	/**
	 * Create letter with specified charset
	 *
	 * @param string $charset
	 * @return Letter
	 */
	function Letter($charset='UTF-8')
	{
		$this->charset=$charset;
	}

	/**
	 * Add a header to the headers list. Can set new header first
	 *
	 * @param string $str
	 * @param bool $set_first
	 * @return bool
	 */
	function add_header($str, $set_first=false)
	{
		$str=@trim($str);
		if (empty($str))
			return false;
		if (empty($this->headers))
			$this->headers=array();
		if (false!==$set_first)
			array_unshift($this->headers, $str);
		else
			$this->headers[]=$str;
		return true;
	}

	/**
	 * Set letter message body
	 *
	 * @param string $contents
	 * @param string $content_mime_type
	 * @param string $charset
	 * @return bool
	 */
	function set_message($contents, $content_mime_type='text/plain', $charset=null)
	{
		$contents          =@(string)$contents;
		$content_mime_type =@(string)$content_mime_type;
		$charset           =@(string)$charset;

		if (empty($contents))
			$contents=' ';
		if (empty($content_mime_type))
			return false;
		if (empty($charset))
			$charset=$this->charset;
//			'content_type_header' => "Content-Type: $content_mime_type; charset=\"$charset\"\r\n"
		$this->message=array(
			'content_type_header' => "Content-Type: $content_mime_type; charset=\"$charset\"\r\n"
//			'content_type_header' => "Content-Type: $content_mime_type; charset=$charset\r\n"
			,'contents'           => $contents
			);
		return true;
	}

	/**
	 * Attach a file by local path or URL
	 *
	 * To load attachment by URL, the php.ini "allow_url_fopen" should be enabled
	 *
	 * @param string $filename_or_url
	 * @param string $mime_type
	 * @param string $attached_filename
	 * @return bool
	 */
	function attach_file($filename_or_url, $mime_type='application/octet-stream', $attached_filename=null)
	{
		$contents=@file_get_contents($filename_or_url);
		if (empty($contents))
			return false;
		if (empty($attached_filename))
			$attached_filename=basename($filename_or_url);
		return $this->attach_contents($contents, $mime_type, $attached_filename);
	}

	/**
	 * Attach contents to the letter
	 *
	 * @param string $contents
	 * @param string $mime_type
	 * @param string $attached_filename
	 * @return bool
	 */
	function attach_contents($contents, $mime_type, $attached_filename, $inside=0)
	{
		$contents=@(string)$contents;
		if (empty($contents))
			return false;
		$attached_filename=@trim($attached_filename);
		if (empty($attached_filename))
			return false;
		if ($mime_type=='text/html' && $inside) {	
/*			$attach=
				"Content-Type: $mime_type; name=\"$attached_filename\"\r\n".
				"Content-Transfer-Encoding: utf8\r\n\r\n".
//				"Content-Disposition: attachment; filename =\"$attached_filename\"\r\n\r\n".
				(($contents));*/
			$attach=
				"Content-Type: text/html;charset=\"utf-8\"\r\n".
//				"Content-Transfer-Encoding: quoted-printable\r\n\".
				"\r\n".
//				"Content-Disposition: attachment; filename =\"$attached_filename\"\r\n\r\n".
				$contents;
		} else {
			$attach=
				"Content-Type: $mime_type; name=\"$attached_filename\"\r\n".
				"Content-Transfer-Encoding: base64\r\n".
				"Content-Disposition: attachment; filename =\"$attached_filename\"\r\n\r\n".
				chunk_split(base64_encode($contents));
		}
		// repeating attachments filenames will be replaced
		$this->attachments[$attached_filename]=$attach;
		return true;
	}

	function attach_html($contents)
	{
		$contents=@(string)$contents;
		if (empty($contents))
			return false;
		$attach=
			"Content-Type: text/html \r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"Content-Disposition: inline \r\n\r\n".
			chunk_split(base64_encode($contents));
		$this->attachment_html=$attach;
		// repeating attachments filenames will be replaced
//		$this->attachments[$attached_filename]=$attach;
		return true;
	}

	/**
	 * Build message body with all headers and attachments
	 *
	 * @return bool
	 */
	function build()
	{
		$this->_body='';
		if (empty($this->message))
			return false;

		// if no file attachments, create simple letter
		if (empty($this->attachments) && !strlen($this->attachment_html))
		{
			$this->add_header($this->message['content_type_header']);
			$this->_body=implode("\r\n", $this->headers)."\r\n\r\n".$this->message['contents'];
			return true;
		}

		if (strlen($this->attachment_html))
		{
			// create letter with attachments
			// boundary
			$boundary         = '=_'.md5(uniqid(microtime(), true));
			$boundary_opening = "--$boundary\r\n";
			$boundary_closing = "--$boundary--\r\n";
			// mime encoded letter headers
			$this->add_header('MIME-Version: 1.0', true);
			$this->add_header("Content-Type: multipart/alternative; boundary=\"$boundary\"");
	
			// concat all headers and attachments
			$this->_body=implode("\r\n", $this->headers)."\r\n\r\n";
			$this->_body.=$boundary_opening.$this->attachment_html."\r\n\r\n";
			$this->_body.=$boundary_closing;
//		print_r($this); die();
			return true;
		}

		// create letter with attachments
		// boundary
		$boundary         = '=_'.md5(uniqid(microtime(), true));
		$boundary_opening = "--$boundary\r\n";
		$boundary_closing = "--$boundary--\r\n";
		// mime encoded letter headers
		$this->add_header('MIME-Version: 1.0', true);
		$this->add_header("Content-Type: multipart/mixed; boundary=\"$boundary\"");

		// attach message before all attachments
		if (strlen(trim($this->message['contents']))) 
			array_unshift($this->attachments,
				$this->message['content_type_header'].
				"Content-Transfer-Encoding: base64\r\n\r\n".
				chunk_split(base64_encode($this->message['contents']))."\r\n"
				);
		// concat all headers and attachments
		$this->_body=implode("\r\n", $this->headers)."\r\n\r\n";
		foreach ($this->attachments as $attachment) {
			if (substr($attachment, 0, strlen('Content-Type: text/html')) == 'Content-Type: text/html') {
				$subboundary         = '=_'.md5(uniqid(microtime(), true));
				$subboundary_opening = "--$subboundary\r\n";
				$subboundary_closing = "--$subboundary--\r\n";
				$this->_body.=$boundary_opening;
				$this->_body.="Content-Type: multipart/alternative; boundary=\"$subboundary\"\r\n";
				$this->_body.="\r\n";
				$this->_body.=$subboundary_opening;
				$this->_body.=$attachment;
//				$this->_body.=str_replace('</html>','',$attachment).'</html>';
				$this->_body.="\r\n";
				$this->_body.="\r\n";
				$this->_body.=$subboundary_closing;
			} else {
				$this->_body.=$boundary_opening.$attachment;
			}
		}
		$this->_body.=$boundary_closing;
//		print_r($this); die();
		return true;
	}

	/**
	 * Build and fetch letter body
	 *
	 * @param bool $force_rebuild
	 * @return string
	 */
	function fetch($force_rebuild=false)
	{
		if ((empty($this->_body)) || (false!==$force_rebuild))
			$this->build();
		return (string)$this->_body;
	}
}
?>