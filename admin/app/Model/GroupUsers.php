<?php
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use App\Model\User;
use App\Model\Group;
class GroupUsers extends Model
{
    protected $table = 'group_users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    const CREATED_AT  = 'create_date';

    /**
     * 添加用户
     * @param $groupId
     * @param $usersId
     * @return bool
     */
    public static function addUser($groupId,$users){
        if (empty($groupId)||empty($users)){
            throw new \Exception('必要参数不可为空');
        }
        $insertAll =[];
        $redisAll = [];
        if (is_array($users)){
            foreach ($users as $item){
                $insertAll[] = ['gid'=>$groupId,'uid'=>$item['id']];
                $redisAll[] =   ['id'=>$item['id'],'name'=>$item['name']];
            }
        }else{
            $insertAll = ['gid'=>$groupId,'uid'=>$users['id']];
            $redisAll =   ['id'=>$users['id'],'name'=>$users['name']];
        }

        self::insert($insertAll);
        self::redisAddUser($groupId,$redisAll);
        return true;
    }

    /**
     * 新增用户数据到redis中
     * @param $groupId
     * @param $redisAll
     */
    public static function redisAddUser($groupId,$redisAll){
        if (!is_array($redisAll) || empty($redisAll)){
            throw new \Exception('新增数据不能为空');
        }
        $groupUsers = Redis::get('group_users_'.$groupId);
        $groupUsers = \GuzzleHttp\json_decode($groupUsers,true);
        $groupUsers = array_merge($groupUsers,$redisAll);
        $groupUsers = json_encode($groupUsers);
        Redis::set('group_users_'.$groupId,$groupUsers);
    }

    /**
     * 删除群中的用户
     * @param $groupId
     * @param $usersId
     * @return bool
     * @throws \Exception
     */
    public static function delUser($groupId,$users){
        if ( empty($groupId) || empty($users) ){
            throw new \Exception('必要参数不可为空');
        }
        if (!is_array($users)){
            throw new \Exception('参数不是数组');
        }

        $usersId = array_column($users,'id');
        self::where('gid',$groupId)->whereIn('uid',$usersId)->delete($usersId);
        self::redisDelUser($groupId,$users);
        return true;
    }

    /**
     * 删除redis中的用户数据
     * @param $groupId
     * @param $redisAll
     */
    public static function redisDelUser($groupId,$redisAll){
        if (!is_array($redisAll) || empty($redisAll)){
            throw new \Exception('新增数据不能为空');
        }
        $groupUsers = Redis::get('group_users_'.$groupId);
        $groupUsers = \GuzzleHttp\json_decode($groupUsers,true);
        //用id做二维数组的键

        $groupUsers = self::arrayColumnKey($groupUsers,'id');
        $redisAll   = self::arrayColumnKey($redisAll,'id');
        //根据key做差集
        $groupUsers = array_diff_key($groupUsers,$redisAll);
        $groupUsers = json_encode($groupUsers);
        Redis::set('group_users_'.$groupId,$groupUsers);
    }


    /**
     * 获取群组中的所有用户信息
     * @param $groupId
     * @return array|string
     */
    public static function getGroupUser($groupId){
        //现获取redis中的
        $groupUsers = Redis::get('group_users_'.$groupId);

        if (empty($groupUsers)){
            //redis为空，从mysql中获取
            $group = Group::find($groupId);
            $groupUsers = [];
            //多对多关联查询
            foreach ($group->User as $role) {
                $groupUsers[] = ['name'=>$role->name,'id'=>$role->id];
            }
            //存入redis
            Redis::set('group_users_'.$groupId,\GuzzleHttp\json_encode($groupUsers));
        }else{
            //json转为数组
            $groupUsers = \GuzzleHttp\json_decode($groupUsers,true);
        }
        return $groupUsers;
    }

    /**
     * 从二维数组的一维数组中挑选一个键值做数组的健。
     * @param $arr
     * @param $key
     * @return array|false
     */
    public static function arrayColumnKey($arr,$key){
        if (!is_array($arr) ||  count($arr) == count($arr, 1)){
            throw new \Exception('不是二维数组');
        }
        $keyAll = array_column($arr,$key);
        if (count($keyAll) != count($arr) ){
            throw new \Exception('作为keys的数组和作为values的数组的元素个数不一样');
        }
        return array_combine($keyAll,$arr);
    }

}