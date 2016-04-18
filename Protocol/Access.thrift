namespace as3 org.protocol.Access
namespace php Services.Access
enum Macro {
	CMD_OK = 0;
	CMD_NOT_EXIT = 2000;
	CMD_EXIT = 2001;
	CMD_ADD = 2002;
}
struct UserInfo
{
  1: i32 uid;//uid
  2: string username;//用户名
  3: string password;//密码
  4: string token;//token
  5: string nickname;//昵称
  6: i32 birthday;//生日
  7: string address;//地址
  8: string profession;//
  9: list<i32> interest;//兴趣
  10: byte sex;//性别
  11: i32 icon;//头像
}
struct Regist
{
  1: i32 code;//code
  2: string msg;//消息
}
//登陆部分
service Access {      
    UserInfo login(1:string username,2:string password);
    Regist regist(1:string username,2:string password,3:i32 vccode);
    i32 add(1:i32 one,3:i32 two);
}
