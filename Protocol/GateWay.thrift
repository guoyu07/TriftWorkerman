namespace as3 org.protocol.GateWay
namespace php Services.GateWay
struct Ping
{
  1: i32 time;//time
}
//登陆部分
service GateWay {      
    Ping ping();
    oneway void pingBack();
}
