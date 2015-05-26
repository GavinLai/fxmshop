<?php
/**
 * fxmgou 相关操作
 *
 * @author Gavin<laigw.vip@gmail.com>
 */
if (!defined('IN_ECS'))
{
  die('Hacking attempt');
}

class FXM {
  
  /**
   * 一些常量
   * @var const
   */
  const APP_PLATFORM = 'fxmgou';
  const DB_NAME   = 'fxmgou';
  const TB_PREFIX = 'tb_';
  
  /**
   * 生成密码salt
   *
   * @return string
   */
  static function gen_salt()
  {
    return substr(uniqid(rand()), -6);
  }
  
  /**
   * 生成加密密码
   * @param string $password_raw
   * @param string $salt
   * @return encoded password
   */
  static function gen_salt_password($password_raw, $salt=NULL, $len=40)
  {
    $len = in_array($len,array(32,40)) ? $len : 40;
    $encfunc = $len==40 ? 'sha1' : 'md5';
    $password_enc = preg_match("/^\w{{$len}}$/", $password_raw) ? $password_raw : $encfunc($password_raw);
    if (!isset($salt)) {
      $salt = gen_salt();
    }
    return strtoupper($encfunc($password_enc . $salt));
  }
  
  /**
   * 产生完整的表访问名称
   *
   * @param string $tbn
   * @return string
   */
  static function table($tbn)
  {
    return self::DB_NAME . '.`' . self::TB_PREFIX . $tbn . '`';
  }
  
  /**
   * 获取客户端IP
   * @return string
   */
  static function get_clientip()
  {
    static $CLI_IP = NULL;
    if (isset($CLI_IP)) {
      return $CLI_IP;
    }
  
    //~ get client ip
    if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
      $CLI_IP = getenv('HTTP_PHP_IP');
    }
    elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
      $CLI_IP = getenv('HTTP_X_FORWARDED_FOR');
    }
    elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
      $CLI_IP = getenv('REMOTE_ADDR');
    }
    elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
      $CLI_IP = $_SERVER['REMOTE_ADDR'];
    }
    preg_match("/[\d\.]{7,15}/", $CLI_IP, $ipmatches);
    $CLI_IP = $ipmatches[0] ? $ipmatches[0] : 'unknown';
  
    return $CLI_IP;
  }
  
  /**
   * 通过username获取 ECSHOP 的{users}表数据
   * @param string $username
   * @return array
   */
  static function getEcUserInfo($username) {
    global $ecs, $db;
    $sql = "SELECT *" .
           " FROM " . $ecs->table('users').
           " WHERE user_name='{$username}'";
    $row = $db->getRow($sql);
    return $row;
  }
  
  /**
   * 通过uid获取 fxmgou 的{member}表数据
   * @param integer $uid
   * @return array
   */
  static function getMemberInfo($uid) {
    global $db;
    $uid = intval($uid);
    $sql = "SELECT *" .
           " FROM " . self::table('member').
           " WHERE uid='{$uid}'";
    $row = $db->getRow($sql);
    return $row;
  }
  
  /**
   * 创建一个新用户
   *
   * @param array $data
   * @param string $from 用户来源
   * @return boolean|number
   */
  static function createUser(Array $data, $from = 'fxmgou')
  {
    if (empty($data)) return FALSE;
    
    global $db, $ecs;
    
    $now  = time();
    $salt = !empty($data['salt'])  ? $data['salt'] : self::gen_salt();
    if (isset($data['password_raw'])) {
      $data['password'] = self::gen_salt_password($data['password_raw'], $salt);
      unset($data['password_raw']);
    }
    $data = array_merge($data,['regip'=>self::get_clientip(), 'regtime'=>$now, 'posttime'=>$now, 'salt'=>$salt, 'state'=>1]);
    $uid  = 0;
    if ($db->autoExecute(self::table('member'), $data, 'INSERT')) {
      $uid = $db->insert_id();
      $db->query("UPDATE ".self::table('member')." SET `from`='{$from}' WHERE `uid`={$uid}"); //'from'字段单独更新
    }
    if($uid>0){
       
      //~ 插入ecshop数据表users
      $username= $data['username'];
      $ecdata  = [];
      $ecdata['member_platform'] = self::APP_PLATFORM;
      $ecdata['member_id']       = $uid;
      if (!empty($ecdata)) {
        $db->autoExecute($ecs->table('users'), $ecdata, 'UPDATE',"user_name='{$username}'");
      }
       
      return $uid;
    }
    return FALSE;
  }
  
  /**
   * 通过uid更新用户信息
   *
   * @param array $data
   * @param string $openid
   * @return boolean|number
   */
  static function updateUser(Array $data, $uid)
  {
    if (empty($data)) return FALSE;
    
    $uid  = intval($uid);
    
    $effcnt = 0;
    $exist_info = self::getMemberInfo($uid);
    if (!empty($exist_info)) {
      global $db;
      
      if (isset($data['salt'])) unset($data['salt']);
      
      if (isset($data['password_raw'])) {
        $data['password'] = self::gen_salt_password($data['password_raw'], $exist_info['salt']);
        unset($data['password_raw']);
      }
      
      $data = array_merge($data,['posttime'=>time()]);
    
      $db->autoExecute(self::table('member'), $data, 'UPDATE', "uid={$uid}");
      $effcnt = $db->affected_rows();
    }
    
    return $effcnt ? $effcnt : FALSE;
  }
  
  /**
   * 从ECSHOP的{users}表获取到fxmgou的uid
   * @param string|array $username
   * @return integer|array
   */
  static function getMemberIdFromEC($username) {
    global $ecs, $db;
    
    if (!is_array($username)) {
      $sql = "SELECT member_id FROM ".$ecs->table('users')." WHERE user_name='{$username}'";
      $uid = $db->getOne($sql);
      return intval($uid);
    }
    else {
      if (!empty($username)) {
        $username = addslashes_deep($username);
        $in  = "'".implode("','", $username)."'";
        $sql = "SELECT member_id FROM ".$ecs->table('users')." WHERE user_name IN({$in})";
        $col = $db->getCol($sql);
        return $col;        
      }
    }
    
    return 0;
  }
  
  /**
   * 通过uid来删除账号
   * 
   * @param integer 影响行数
   */
  static function removeUser($uid) {
    if (!is_array($uid)) {
      $uid = array($uid);
    }
    
    if (!empty($uid)) {
      global $db;
      $in  = implode(",", $uid);
      $sql = "DELETE FROM ".self::table('member')." WHERE uid IN({$in})";
      $db->query($sql);
      return $db->affected_rows();
    }
    
    return 0;
  }
  
}

/*----- END FILE: cls_fxmgou.php -----*/