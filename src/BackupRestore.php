<?php

namespace BCairns\BackupRestore;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use SilverStripe\View\ArrayData;


/**
 * @package siteconfig
 */
class BackupRestore extends LeftAndMain {

	private static $version = '4.x';

	private static $allowed_actions = array(
		'backup',
		'restore'
	);

	private static $required_permission_codes = array('ADMIN');

	private static $menu_title = 'Backup/Restore';

	private static $menu_icon = 'public/resources/vendor/bcairns/silverstripe-backuprestore/images/menuicon.png';

	private static $menu_priority = -2;

	private static $url_segment = 'backuprestore';

	private static $db_temp_dir = '../app/_db';
	private static $create_htaccess = true;


	public static function getPath(){
        return Config::inst()->get(self::class, 'db_temp_dir') . '/';
    }

	// make DB folder and add .htaccess if needed
	public static function makeFolder(){
		if( !file_exists(self::getPath()) ){
			Filesystem::makeFolder(self::getPath());
		}
		if( Config::inst()->get(self::class, 'create_htaccess') &&
		    !file_exists(self::getPath().'.htaccess') ){
			$content = <<<TEXT
<Files *>
	Order deny,allow
	Deny from all
</Files>
TEXT;
			file_put_contents(self::getPath().'.htaccess', $content);
		}
	}


	public function restore(){

		self::makeFolder();

		$gzipped = $_FILES['upload']['type'] == 'application/x-gzip';
		$sqlDest = self::getPath().'db.sql';

		$dest = $sqlDest.($gzipped ? '.gz' : '');

		if( move_uploaded_file($_FILES['upload']['tmp_name'], $dest )){

			// todo: refactor to eliminate dupe code

			if( $gzipped ){
				if( $this->_gzip_decode($dest, $sqlDest) ){
					if( $count = $this->_restore_db_from_file($sqlDest) ){
						$this->setRestoreMessage('Database restored, ' . $count . ' queries executed.');
						$this->redirectBack();
					}else{
						$this->setRestoreMessage('Failed to restore database.', 'bad');
						$this->redirectBack();
					}
				}else{
					$this->setRestoreMessage('Failed to decompress gzip.', 'bad');
					$this->redirectBack();
				}
			}else{
				if( $count = $this->_restore_db_from_file($sqlDest) ){
					$this->setRestoreMessage('Database restored, ' . $count . ' queries executed.');
					$this->redirectBack();
				}else{
					$this->setRestoreMessage('Failed to restore database.', 'bad');
					$this->redirectBack();
				}
			}
		}else{
			$this->setRestoreMessage('Failed to copy temp file.', 'bad');
			$this->redirectBack();
		}
	}


	public function backup(){

		// create DB dump file and download it

		self::makeFolder();

		$downloadName = $_SERVER['SERVER_NAME'] . '.'.date('Y-m-d.His') . '.sql';

		$path = self::getPath().'db.sql';

		if( $this->_backup_db_to_file($path) ){

			// let's try to gzip it
			$gzip_path = $path.'.gz';
			if( $this->_gzip_encode($path, $gzip_path) ){
				$path = $gzip_path;
				$downloadName .= '.gz';
			}

			header('Content-Type: application/octet-stream');
			header("Content-Transfer-Encoding: Binary");
			header("Content-Disposition: attachment; filename=\"" . $downloadName . "\"");

			header('Content-Length: '.filesize( $path ) );
			readfile( $path );
			exit;

		}

	}

	public function setRestoreMessage( $message, $status = 'good' ){
	    $session = $this->getRequest()->getSession();
        $session->set('BackupRestoreStatus', $status);
        $session->set('BackupRestoreMessage', $message);
	}

	public function RestoreMessage() {

		static $data = false;

        $session = $this->getRequest()->getSession();

		if($session->get('BackupRestoreMessage')) {
			$message = $session->get('BackupRestoreMessage');
			$status = $session->get('BackupRestoreStatus');

            $session->clear('BackupRestoreStatus');
            $session->clear('BackupRestoreMessage');

			$data = new ArrayData(array('Message' => $message, 'Status' => $status));
		}

		return $data;
	}

	public function IsLive(){
		return Director::isLive();
	}

	// restore MySQL database implementation /////////////////////////////


	/**
	 * Backup the databases to a file.
	 */
	function _restore_db_from_file($path) {
		$num = 0;

		if ($f = fopen($path, 'r') ) {
			// Read one line at a time and run the query.
			while ($line = $this->_read_sql_command_from_file($f)) {
				if ($line) {
					// Prepare and execute the statement instead of the api function to avoid substitution of '{' etc.
					$this->query($line);

					$num++;
				}
			}
			// Close the file with fclose/gzclose.
			fclose($f);
		}
		else {
		    error_log("unable to open file");
//			Debug::log("unable to open file");
			return false;
		}
		return $num;
	}

	/**
	 * Read a multiline sql command from a file.
	 *
	 * Supports the formatting created by mysqldump, but won't handle multiline comments.
	 */
	function _read_sql_command_from_file($f) {
		$out = '';
		while ($line = fgets($f)) {
			$first2 = substr($line, 0, 2);
			$first3 = substr($line, 0, 2);

			// Ignore single line comments. This function doesn't support multiline comments or inline comments.
			if ($first2 != '--' && ($first2 != '/*' || $first3 == '/*!')) {
				$out .= ' ' . trim($line);
				// If a line ends in ; or */ it is a sql command.
				if (substr($out, strlen($out) - 1, 1) == ';') {
					return trim($out);
				}
			}
		}
		return trim($out);
	}


	// backup MySQL database implementation //////////////////////////////
	// adapted from Drupal Backup/Migrate module


	// handle DB query, return rows as associative arrays
	function query( $sql, $params = array(), $config = array() ){
		$results = DB::query( $sql );
		$rows = array();
		foreach( $results as $result ){
			$rows[] = $result;
		}
		return $rows;
	}

	// handle DB query, return single value
	function queryValue( $sql, $params = array(), $config = array() ){
		$results = DB::query( $sql );
		return $results->value();
	}




	/**
	 * Backup the databases to a file.
	 *
	 *  Returns a list of sql commands, one command per line.
	 *  That makes it easier to import without loading the whole file into memory.
	 *  The files are a little harder to read, but human-readability is not a priority
	 */
	function _backup_db_to_file($path) {
		if ($f = fopen($path,'w+')) {
			$this->_write_db_to_file($f);
			fclose($f);
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	function _write_db_to_file($f){

		$excluded_tables = $this->config()->get('excluded_tables');
		if( !is_array($excluded_tables) ){
			$excluded_tables = array();
		}

		fwrite($f, $this->_get_sql_file_header());
		$alltables = $this->_get_tables();
		$allviews = $this->_get_views();

		foreach ($alltables as $table) {
			if ($table['name'] && !in_array($table['name'], $excluded_tables) ) {
				fwrite($f, $this->_get_table_structure_sql($table));
				$this->_dump_table_data_sql_to_file($f, $table);
			}
		}
		foreach ($allviews as $view) {
			if ($view['name']) {
				fwrite($f, $this->_get_view_create_sql($view));
			}
		}
		fwrite($f, $this->_get_sql_file_footer());

	}


	/**
	 * Get a list of tables in the db.
	 */
	function _get_tables() {
		$out = array();
		// get auto_increment values and names of all tables
		$tables = $this->query("show table status");
		foreach ($tables as $table) {
			$table = array_change_key_case($table);
			if (!empty($table['engine'])) {
				$out[$table['name']] = $table;
			}
		}
		return $out;
	}

	/**
	 * Get a list of views in the db.
	 */
	function _get_views() {
		$out = array();
		// get auto_increment values and names of all tables
		$tables = $this->query("show table status");
		foreach ($tables as $table) {
			$table = array_change_key_case($table);
			if (empty($table['engine'])) {
				$out[$table['name']] = $table;
			}
		}
		return $out;
	}

	/**
	 * Get the sql for the structure of the given table.
	 */
	function _get_table_structure_sql($table) {
		$out = "";
		$result = $this->query("SHOW CREATE TABLE `". $table['name'] ."`");
		foreach ($result as $create) {
			$create = array_change_key_case($create);
			$out .= "DROP TABLE IF EXISTS `". $table['name'] ."`;\n";
			// Remove newlines and convert " to ` because PDO seems to convert those for some reason.
			$out .= strtr($create['create table'], array("\n" => ' ', '"' => '`'));
			if ($table['engine']) {
				$out .= " ENGINE=". $table['engine'];
			}
			if ($table['collation']) {
				$out .= " COLLATE=". $table['collation'];
			}
			if ($table['auto_increment']) {
				$out .= " AUTO_INCREMENT=". $table['auto_increment'];
			}
			$out .= ";\n";
		}
		return $out;
	}

	/**
	 *  Get the sql to insert the data for a given table
	 */
	function _dump_table_data_sql_to_file($f, $table) {

		// todo: config values for these
		$rows_per_query = 500; // rows to read at a time from the DB
		$rows_per_line = 30;
		$bytes_per_line = 2000;

		$totalRows = $this->queryValue("SELECT count(1) FROM `". $table['name'] ."`");
		$offset = 0;
		$lines = 0;

		while( $offset < $totalRows ){

			$results = DB::query("SELECT * FROM `". $table['name'] ."` LIMIT $offset, $rows_per_query");

			$offset += $rows_per_query;
			$rows = $bytes = 0;

			// Escape backslashes, PHP code, special chars
			$search = array('\\', "'", "\x00", "\x0a", "\x0d", "\x1a");
			$replace = array('\\\\', "''", '\0', '\n', '\r', '\Z');

			foreach ($results as $row) {
				// DB Escape the values.
				$items = array();
				foreach ($row as $key => $value) {
					$items[] = is_null($value) ? "null" : "'". str_replace($search, $replace, $value) ."'";
				}

				// If there is a row to be added.
				if ($items) {
					// Start a new line if we need to.
					if ($rows == 0) {
						fwrite($f, "INSERT INTO `". $table['name'] ."` VALUES ");
						$bytes = $rows = 0;
					}
					// Otherwise add a comma to end the previous entry.
					else {
						fwrite($f, ",");
					}

					// Write the data itself.
					$sql = implode(',', $items);
					fwrite($f, '('. $sql .')');
					$bytes += strlen($sql);
					$rows++;

					// Finish the last line if we've added enough items
					if ($rows >= $rows_per_line || $bytes >= $bytes_per_line) {
						fwrite($f, ";\n");
						$lines++;
						$bytes = $rows = 0;
					}
				}
			}
			// Finish any unfinished insert statements.
			if ($rows > 0) {
				fwrite($f, ";\n");
				$lines++;
			}

		}

		return $lines;
	}


	/**
	 * Get the sql for the structure of the given view.
	 */
	function _get_view_create_sql($view) {
		$out = "";
		// Switch SQL mode to get rid of "CREATE ALGORITHM..." what requires more permissions + troubles with the DEFINER user

		$sql_mode = $this->queryValue("SELECT @@SESSION.sql_mode");

		$this->query("SET sql_mode = 'ANSI'");
		$result = $this->query("SHOW CREATE VIEW `" . $view['name'] . "`", array(), array('fetch' => PDO::FETCH_ASSOC));
		$this->query("SET SQL_mode = $sql_mode");
		foreach ($result as $create) {
			$create = array_change_key_case($create);
			$out .= "DROP VIEW IF EXISTS `". $view['name'] ."`;\n";
			$out .= "SET sql_mode = 'ANSI';\n";
			$out .= strtr($create['create view'], "\n", " ") . ";\n";
			$out .= "SET sql_mode = '$sql_mode';\n";
		}
		return $out;
	}

	/**
	 * The header for the top of the sql dump file. These commands set the connection
	 *  character encoding to help prevent encoding conversion issues.
	 */
	function _get_sql_file_header() {

		// todo: fill in all the placeholders

		$module_version = self::$version;
		$ss_version = $this->CMSVersion();
		$host = $_SERVER['HTTP_HOST'];
		$site_name = $this->SiteConfig()->Title;
		$mysql_version = $this->queryValue('SELECT @@VERSION');

		return "-- Backup/Restore (Silverstripe) MySQL Dump
-- Backup/Restore Version: $module_version
-- https://github.com/bcairns/silverstripe-backuprestore
-- SilverStripe Version: $ss_version
-- http://silverstripe.org/
--
-- Host: $host
-- Site Name: $site_name
-- Generation Time: " . date('r') . "
-- MySQL Version: $mysql_version

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=NO_AUTO_VALUE_ON_ZERO */;

SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";
SET NAMES utf8;

";
	}

	/**
	 * The footer of the sql dump file.
	 */
	function _get_sql_file_footer() {
		return "
  
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
";
	}


	// gzip implementation //////////////////////////////////////////

	/**
	 * Gzip encode a file.
	 */
	function _gzip_encode($source, $dest, $level = 9) {
		$success = FALSE;

		if (@function_exists("gzopen")) {
			if (($fp_out = gzopen($dest, 'wb'. $level)) && ($fp_in = fopen($source, 'rb'))) {
				while (!feof($fp_in)) {
					gzwrite($fp_out, fread($fp_in, 1024 * 512));
				}
				$success = TRUE;
			}
			@fclose($fp_in);
			@gzclose($fp_out);
		}
		return $success;
	}

	/**
	 * Gzip decode a file.
	 */
	function _gzip_decode($source, $dest) {
		$success = FALSE;

		if (@function_exists("gzopen")) {
			if (($fp_out = fopen($dest, 'wb')) && ($fp_in = gzopen($source, 'rb'))) {
				while (!feof($fp_in)) {
					fwrite($fp_out, gzread($fp_in, 1024 * 512));
				}
				$success = TRUE;
			}
			@gzclose($fp_in);
			@fclose($fp_out);
		}
		return $success;
	}

}
