<?php
class Database
{
    public static $connection;
    public static function setUpConnection()
    {
        if (!isset(Database::$connection)) {
            Database::$connection = new mysqli("194.59.164.127", "u155757515_times", "Times@2026", "u155757515_times");
        }
    }
    public static function iud($q)
    {
        Database::setUpConnection();
        Database::$connection->query($q);
    }
    public static function search($q)
    {
        Database::setUpConnection();
        $resultset = Database::$connection->query($q);
        return $resultset;
    }

    public static function escape($string)
    {
        Database::setUpConnection();
        return Database::$connection->real_escape_string($string);
    }
}
