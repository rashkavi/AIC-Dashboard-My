<?php
class Project
{
    private const BASE_NAME = "AIC Campus";
    private const BASE_URL = "https://dashboard.aiccampuslms.com";
    private const BASE_PORTAL_URL = "https://portal.aiccampuslms.com";
    private const INSTITUTE_LOGO = "common/logo.png";
    private const VERSION = "2.3.84";
    public static function getBaseName()
    {
        return self::BASE_NAME;
    }
    public static function getBaseUrl()
    {
        return self::BASE_URL;
    }
    public static function getBasePortalUrl()
    {
        return self::BASE_PORTAL_URL;
    }
    public static function getInstituteLogo()
    {
        return self::INSTITUTE_LOGO;
    }

    public static function getVersion()
    {
        return self::VERSION;
    }

    public static function getGoogleVisionKeyPath()
    {
        if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
            $path = __DIR__ . '/../secure/google-vision-key.json';
        } else {
            $path = dirname($_SERVER['DOCUMENT_ROOT'], 3) . '/aiccampuslms.com/secure/google-vision-key.json';
        }

        if (!file_exists($path)) {
            throw new Exception("Google Vision API key file not found at: " . $path);
        }

        return $path;
    }
}
