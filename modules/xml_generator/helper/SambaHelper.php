<?php
declare(strict_types=1);

namespace app\modules\xml_generator\helper;

use DateTime;

class SambaHelper
{
    /**
     * @param $date
     *
     * @return string
     * @throws \Exception
     */
    public static function getCorrectSambaDate($date): string
    {
        $datetime = new DateTime($date);
        return $datetime->format(DATE_RFC3339_EXTENDED);
    }

    public static function getCorrectDbDate($date): string
    {
        return date('Y-m-d H:i:s', strtotime($date));
    }

    public static function sanitizeForXml($value): string
    {
        // Decode existing HTML entities (so we dont convert existing ones like &amp; into &amp;amp...)
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($decoded, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}