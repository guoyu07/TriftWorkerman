<?php 
/**
 * @author 不再迟疑
 */
namespace GatewayWorker\Protocols;

class ThriftProtocol
{
    
    public static $empty = array(
        'pack_len' => 0,
        'body' => '',
        'class_name' => '',
    );
    /**
     * 包头长度
     * @var integer
     */
    const HEAD_LEN = 8;
    /**
     * 返回包长度
     * @param string $buffer
     * @return int return current package length
     */
    public static function input($buffer)
    {
        if(strlen($buffer) < self::HEAD_LEN)
        {
            return 0;
        }
        
        $data = unpack("Npack_len", $buffer);
        return $data['pack_len'];
    }
    
     /**
     * 获取整个包的buffer
     * @param array $data
     * @return string
     */
    public static function encode($data)
    {
        $class_name_len = strlen($data['class_name']);
        $package_len = self::HEAD_LEN + strlen($data['body']) + $class_name_len;
        return pack("NN", $package_len,$class_name_len).$data['class_name']. $data['body'];
    }
    
    /**
     * 从二进制数据转换为数组
     * @param string $buffer
     * @return array
     */    
    public static function decode($buffer)
    {
        $data = unpack("Npack_len/Nclass_name_len", $buffer);
        $data['class_name'] = substr($buffer, self::HEAD_LEN, $data['class_name_len']);
        $data['body'] = substr($buffer, self::HEAD_LEN+$data['class_name_len']);
        return $data;
    }
}



