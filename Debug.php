<?
/*
 * Debug.php
 */

class Debug {
	private static $Enabled = true;
	private static $Log 	= false;

	public static function Write($Message) {
		$ts = date("Y-m-d H:i:s");

		if (self::$Log && self::$Enabled)
			error_log("[$ts] $Message\n", 3, "error_log");
		else if (self::$Enabled) {
			echo "[$ts] $Message\n";
		}
	}
}
?>