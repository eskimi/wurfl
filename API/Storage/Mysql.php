<?php
/**
 * Copyright (c) 2014 ScientiaMobile, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * Refer to the COPYING.txt file distributed with this package.
 *
 * @category   WURFL
 * @package	WURFL_Storage
 * @copyright  ScientiaMobile, Inc.
 * @license	GNU Affero General Public License
 * @author	 Fantayeneh Asres Gizaw
 * @version	$id$
 */
/**
 * WURFL Storage
 * @package	WURFL_Storage
 */
class WURFL_Storage_Mysql extends WURFL_Storage_Base {

	private $defaultParams = array(
		"host" => "localhost",
		"port" => 3306,
		"db" => "wurfl_persistence_db",	
		"user" => "",
		"pass" => "",
		"socket" => null,
		"table" => "wurfl_object_cache",
		"keycolumn" => "key",
		"valuecolumn" => "value"
	);

	private $link;
	private $host;
	private $db;
	private $user;
	private $pass;
	private $port;
	private $socket;
	private $table;
	private $keycolumn;
	private $valuecolumn;

	public function __construct($params) {
		$currentParams = is_array($params) ? array_merge($this->defaultParams,$params) : $this->defaultParams;
		foreach($currentParams as $key => $value) {
			$this->$key = $value;
		}
		$this->initialize();
	}

	private function initialize() {
		$this->_ensureModuleExistance();

		/* Initializes link to MySql */
		$this->link = mysqli_connect($this->host,$this->user,$this->pass, null,$this->port, $this->socket);
		if (!$this->link || mysqli_error($this->link)) {
			throw new WURFL_Storage_Exception("Couldn't link to $this->host (".mysqli_error($this->link).")");
		}

		/* Initializes link to database */
		$success=mysqli_select_db($this->link,$this->db);
		if (!$success) {
			throw new WURFL_Storage_Exception("Couldn't change to database $this->db (".mysqli_error($this->link).")");
		}

        /* Is Table there? */
		$test = mysqli_query($this->link,"SHOW TABLES FROM `$this->db` LIKE '$this->table'");
		if (!is_object($test)) {
			throw new WURFL_Storage_Exception("Couldn't show tables from database $this->db (".mysqli_error($this->link).")");
		}

		// create table if it's not there.
		if (mysqli_num_rows($test)==0) {
			$sql="CREATE TABLE `$this->db`.`$this->table` (
					  `$this->keycolumn` varchar(255) collate latin1_general_ci NOT NULL,
					  `$this->valuecolumn` mediumblob NOT NULL,
					  `ts` timestamp NOT NULL default CURRENT_TIMESTAMP,
					  PRIMARY KEY  (`$this->keycolumn`)
					) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci";
			$success=mysqli_query($this->link,$sql);
			if (!$success) {
				throw new WURFL_Storage_Exception("Table $this->table missing in $this->db (".mysqli_error($this->link).")");
			}
		}

		if (is_object($test)) mysqli_free_result($test);
	}
	
	public function save($objectId, $object, $expiration=null) {
		$object=mysqli_real_escape_string($this->link,serialize($object));
		$objectId=$this->encode("",$objectId);
		$objectId=mysqli_real_escape_string($this->link,$objectId);
		$sql = "delete from `$this->db`.`$this->table` where `$this->keycolumn`='$objectId'";
		$success=mysqli_query($this->link,$sql);
		if (!$success) {
			throw new WURFL_Storage_Exception("MySql error ".mysqli_error($this->link)."deleting $objectId in $this->db");
		}

		$sql="insert into `$this->db`.`$this->table` (`$this->keycolumn`,`$this->valuecolumn`) VALUES ('$objectId','$object')";
		$success=mysqli_query($this->link,$sql);
		if (!$success) {
			throw new WURFL_Storage_Exception("MySQL error ".mysqli_error($this->link)."setting $objectId in $this->db");
		}
		return $success;
	}

	public function load($objectId) {
		$return = null;
		$objectId = $this->encode("", $objectId);
		$objectId = mysqli_real_escape_string($this->link,$objectId);

		$sql = "select `$this->valuecolumn` from `$this->db`.`$this->table` where `$this->keycolumn`='$objectId'";
		$result = mysqli_query($this->link,$sql);
		if (!is_object($result)) {
			throw new WURFL_Storage_Exception("MySql error ".mysqli_error($this->link)."in $this->db");
		}

		$row = mysqli_fetch_assoc($result);
		if (is_array($row)) {
			$return = @unserialize($row['value']);
			if ($return === false) {
				$return = null;
			}
		}
		
		if (is_object($result)) mysqli_free_result($result);
		
		return $return;
	}

	public function clear() {
		$sql = "truncate table `$this->db`.`$this->table`";
		$success=mysqli_query($this->link,$sql);
		if (mysqli_error($this->link)) {
			throw new WURFL_Storage_Exception("MySql error ".mysqli_error($this->link)." clearing $this->db.$this->table");
		}
		return $success;
	}



	/**
	 * Ensures the existance of the the PHP Extension mysqli
	 * @throws WURFL_Storage_Exception required extension is unavailable
	 */
	private function _ensureModuleExistance() {
		if(!extension_loaded("mysqli")) {
			throw new WURFL_Storage_Exception("The PHP extension mysqli must be installed and loaded in order to use the mysql.");
		}
	}

}