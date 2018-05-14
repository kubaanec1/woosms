<?php
namespace BulkGate\WooSms;

use BulkGate;

/**
 * @author Lukáš Piják 2018 TOPefekt s.r.o.
 * @link https://www.bulkgate.com/
 */
class Post extends BulkGate\Extensions\Strict
{
    /** @var array */
    private static $post = array();


    /**
     * @param $name
     * @param null $default
     * @param array $skip
     * @return array|null|string
     */
    public static function get($name, $default = null, $skip = array())
    {
        if(!isset(self::$post[$name]))
        {
            self::$post[$name] = self::sanitize(isset($_POST[$name]) ? $_POST[$name] : $default, $skip);
        }
        return self::$post[$name];
    }


    /**
     * @param $array_name
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    public static function getFromArray($array_name, $name, $default = null)
    {
        $array = self::get($array_name);

        if(is_array($array) && isset($array[$name]))
        {
            return $array[$name];
        }
        return $default;
    }


    /**
     * @param $data
     * @param array $skip
     * @return array|null|string
     */
    private static function sanitize($data, $skip = array())
    {
        if(is_array($data))
        {
            $output = array();
            foreach ($data as $key => $item)
            {
                $output[sanitize_text_field($key)] = is_array($item) ?
                    self::sanitize($item) : (
                        in_array($key, $skip) ?
                            wp_check_invalid_utf8($item) :
                            sanitize_text_field($item)
                    );
            }
            return $output;
        }
        elseif(is_scalar($data))
        {
            return sanitize_text_field($data);
        }
        return null;
    }
}