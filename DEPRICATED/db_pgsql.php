
<?php
// db_pgsql.php - PostgreSQL connection for CRM

function get_pgsql_connection() {
	static $conn = null;
	if ($conn !== null) {
		return $conn;
	}
	// Update these parameters as needed for your environment
	$host = 'localhost';
	$port = '5432';
	$dbname = 'crm';
	$user = 'crm_user';
	$password = 'crm_password';
	$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
	if (!$conn) {
		die('Error: Unable to connect to PostgreSQL database.');
	}
	return $conn;
}


