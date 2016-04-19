<?php
namespace Config;

class Redis
{
    
    /**
     * Redis的一个实例配置
     */
    public static $redis1 = array(
        'host'    => '***',
        'port'    => 6379,
        'user'    => '***',
        'password'=> '***',
        'select'  => 2,
    );
}

?>
