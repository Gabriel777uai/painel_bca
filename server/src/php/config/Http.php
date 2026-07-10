<?php

namespace Config;

class Http
{
    public static function headerJson(): String
    {
        $http_type = "application/json";
        return $http_type;
    }
    public static function headerXml(): String
    {
        $http_type = "application/xml";
        return $http_type;
    }
    public static function headerText(): String
    {
        $http_type = "text/plain";
        return $http_type;
    }
    public static function headerPdf(): String
    {
        $http_type = "application/pdf";
        return $http_type;
    }
}
