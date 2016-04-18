<?php
namespace Config;

class Db
{
    /**
     * 数据库的一个实例配置，则使用时像下面这样使用
     * $user_array = Db::instance('db1')->select('name,age')->from('users')->where('age>12')->query();
     * 等价于
     * $user_array = Db::instance('db1')->query('SELECT `name`,`age` FROM `users` WHERE `age`>12');
     * @var array
     */
    public static $db1 = array(
        'host'    => 'rdsl3k9k6dupjp92n31b.mysql.rds.aliyuncs.com',
        'port'    => 3306,
        'user'    => 'youwo',
        'password' => 'youwo197197',
        'dbname'  => 'thrift',
        'charset'    => 'utf8',
    );
}

?>