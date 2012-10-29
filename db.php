<?php

/**
 * DB - A PDO Wrapper
 * 	Intended to be called once, set up the PDO connection, and handle common
 * @uses
 * 	$db = new db();
 * @author Nick Wright
 * 
 */

class db {
	
	public 
		$pdo;
	
	/**
	 * DB expects several constants to be set:
	 * 	DB_HOST
	 * 	DB_PORT
	 * 	DB_SCHEMA
	 * 	DB_USERNAME
	 * 	DB_PASSWORD
	 */
	public function __construct() {
		try {
			$this->pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_SCHEMA, DB_USERNAME, DB_PASSWORD);
			@$this->pdo->setAttribute(PDO_ATTR_ERRMODE, PDO_ERRMODE_EXCEPTION);
		}
		catch (PDOException $e) {
			echo $e->getMessage();
		}
	}
	
	/**
	 * Update
	 * 	Take data, parse it, then build and execute an UPDATE command
	 * @param string $table >> The name of the table
	 * @param array $set >> An assoc array of field=>values.  
	 * 		Fields will be tick'd (`field`), and values will be "prepared".
	 * @param array $where >> An assoc array to build the where statement.  
	 * 		They will be joined with "AND".
	 * @param array $rawFields >> To overwrite the "prepared values" functionality of $set, defined the "raw" fields.
	 * @param string $rawWhere >> If the where statement is more complicated than "AND", use this
	 * 
	 * @uses: One - simple name change
	 * 	$db->update('users', array('name' => 'Nick'), array('id' => 7));
	 * 
	 * 	SQL:
	 * 	UPDATE users SET `name` = :name WHERE id = :whereid;
	 * 	passing: array('name' => 'Nick', 'whereid' => 7).  
	 * 	The where prepared items get prepended with text to prevent overlap.
	 * 	This prevents cases of UPDATE users SET `name` = :name WHERE `name` = :name;
	 * 	That would cause confusion and be unclear.
	 * 	
	 * 	This will update the users table: the field `name` changes 'Nick where the id is equal to 7.
	 * @uses: Two - complicated
	 * 	$db->update('users', array(
	 * 		'name' => $name,
	 * 		'counter' => 'counter + 1'
	 * 	), array(
	 * 		'id' => 7
	 * 	), array(
	 * 		'counter'
	 * 	), 'OR id = 6');
	 * 	
	 * 	SQL:
	 *  UPDATE users SET `name` = :name, `counter` = counter + 1 WHERE id = :whereid OR id = 6;
	 *  
	 * 	This will update the users table, the field `name` will change.  The counter will be incremented by one.
	 * 	We use PDO's prepared statement functionality, so to avoid being prepared, we place "counter" in the rawFields.
	 * 	
	 * @return nothing
	 */
	public function update($table, $set, $where, $rawFields = array(), $rawWhere = null) {
		$setString = $whereString = '';
		$values = $set;
		
		foreach ($set as $k => $v) {
			$setString .= '`' . $k . '` = ';
			if (strtolower($v) == 'now()') $setString .= 'NOW()';
			elseif (strtolower($v) == 'null') $setString .= 'NULL';
			elseif (in_array($k, $rawFields)) $setString .= $v;
			else $setString .= ':' . $k;
			$setString .= ', ';
		}
		$setString = substr($setString, 0, -2);
		
		foreach ($where as $k => $v) {
			$whereString .= '`' . $k . '` = :where' . $k . ' AND ';
			$values['where' . $k] = $v;
		}
		$whereString = substr($whereString, 0, -5);
		if ($rawWhere) 
			$whereString .= ' ' . $rawWhere;
		
		$sth = $this->pdo->prepare(
			'UPDATE `' . $table . '` ' . 
			'SET ' . $setString . ' ' . 
			'WHERE ' . $whereString
		);
		$sth->execute($values);
	}
	
	/**
	 * Insert
	 * Take data, parse it, then build and execute an INSERT command
	 * @param string $table
	 * @param array $values >> An assoc array of field=>values
	 * @param array $rawFields >> @see UPDATE
	 * @param boolean $ignore >> A flag, if true, indicates "INSERT IGNORE"
	 * 
	 * @see Update >> Please read the comments concerning the Update command.  
	 * 		Many of it's core ideas about prepared statements hold true with Insert as well.
	 * 
	 * @uses:
	 * $newId = $db->insert('users', array('name' => 'Nicholas', 'createdAt' => 'now()'));
	 * // assert($newId == 8)
	 * 
	 * SQL:
	 * INSERT INTO users (name, createdAt) VALUES(:name, NOW());
	 * passing: array('name' => 'Nicholas')
	 * 
	 * @return the last inserted id
	 */
	public function insert($table, $values, $rawFields = array(), $ignore = false) {
		$fieldsString = $valuesString = '';
		
		foreach ($values as $k => $v) {
			$fieldsString .= '`' . $k . '`, ';
			
			if (strtolower($v) == 'now()') $valuesString .= 'NOW()';
			elseif (strtolower($v) == 'null') $valuesString .= 'NULL';
			elseif (in_array($k, $rawFields)) $valuesString .= $v;
			else $valuesString .= ':' . $k;
			$valuesString .= ', ';
		}
		$fieldsString = substr($fieldsString, 0, -2);
		$valuesString = substr($valuesString, 0, -2);
	    
	    $sth = $this->pdo->prepare(
	    	'INSERT ' . ($ignore ? 'IGNORE ' : '') . 'INTO `' . $table . '` ' . 
	    	'(' . $fieldsString . ') VALUES (' . $valuesString . ')'
	    );
		$sth->execute($values);
		
		return $this->pdo->lastInsertId();
	}
	
	/**
	 * Delete
	 * 
	 * @param string $table
	 * @param array $where >> An assoc array detailing the matches
	 * 
	 * @see Update >> Please read the comments concerning the Update command.  
	 * 		How it handles $where is true for Delete as well.
	 * 
	 * @uses
	 * $db->delete('users', array('id' => 7));
	 * 
	 * SQL:
	 * DELETE FROM users WHERE id = 7;
	 * 
	 * @return nothing
	 */
	public function delete($table, $where) {
		$whereString = '';
		
		foreach ($where as $k => $v)
			$whereString .= '`' . $k . '` = :' . $k . ' AND ';
			
		$whereString = substr($whereString, 0, -5);
		
		$sth = $this->pdo->prepare(
			'DELETE FROM `'. $table . '` ' . 
			'WHERE ' . $whereString
		);
		$sth->execute($where);
	}
	
	/**
	 * Query
	 * When you need to execute complicated SQL without expecting a result, 
	 * 	and the ->update, ->insert and ->delete functions are not up to the task,
	 * 	use this function.
	 * @example `INSERT ... SELECT`, `INSERT ... (), (), ()`, `DELETE ... WHERE 1 OR (2 AND 3)`
	 * 
	 * @param string $query >> the SQL you want executed.  Build it as a prepared statement (with :variables)
	 * @param array $values >> Assoc array to use for the prepared statement
	 * 
	 * @return nothing
	 */
	public function query($query, $values) {
		$sth = $this->pdo->prepare($query);
		$sth->execute($values);
	}
	
	/**
	 * Select
	 * 	Run a query and gather the results.
	 * @param string $query >> Use a prepared statement
	 * @param array $values
	 * @see Update or Insert for description on how this handles prepared values
	 * @return array >> Multi-tiered assoc array
	 */
	public function select($query, $values = array()) {
		$sth = $this->pdo->prepare($query);
		$sth->execute($values);
		$sth->setFetchMode(PDO::FETCH_ASSOC);
		return $sth->fetchAll();
	}
	
	/**
	 * Select First
	 * 	Runs the query, but only returns the first result.  
	 * 	Also attempts to append query with "LIMIT 1" for simplicity.
	 * @see Select
	 * @param string $query
	 * @param array $values
	 * @return array >> Single-tiered assoc array
	 */
	public function select_first($query, $values = array()) {
		if (!strstr($query, 'LIMIT'))
			$query .= ' LIMIT 1';
		
	    $result = $this->select($query, $values);
		return $result[0];
	}
	
	/**
	 * Fields
	 * Gather the column names that belong to the table.
	 * @param string $table
	 */
	public function fields($table) {
	   	$sth = $this->pdo->prepare('DESCRIBE `' . $table . '`');
		$sth->execute();
		$sth->setFetchMode(PDO::FETCH_ASSOC);
		return $sth->fetchAll(PDO::FETCH_COLUMN, 0);
	}
	
}