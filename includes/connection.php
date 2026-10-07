<?php
class Database
{
    public static $connection;
    public static function setUpConnection()
    {
        if (!isset(Database::$connection)) {
            Database::$connection = new mysqli("127.0.0.1", "root", "RanaDe@10326510", "u155757515_uni_aic_live2");
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
