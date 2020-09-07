<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use mysql_xdevapi\Exception;

class User extends Model
{
    protected $table = 'user';
    protected $primaryKey = 'id';
    public $timestamps = false;
    const CREATED_AT  = 'create_date';
    /**
     * 获取用户
     * @param string $name
     * @param int $offset
     * @param int $limit
     * @return mixed
     */
    public static function selectUser($name='',$offset=0,$limit=15){
        $list =User::where([['name', 'like', '%'.$name.'%']])->offset($offset)->limit($limit)->get();
        return $list;
    }

    /**
     * 获取指定id的用户
     * @param $ids
     * @return mixed
     */
    public static function selectColumnId($ids=[]){
        if(empty($ids)){
            throw new \Exception('必要参数不可为空');
        }
        //不是数组说明是要获取单个id数据
        if (!is_array($ids)){
            $ids = [$ids];
        }
        $list =User::whereIn('id',$ids)->select('id','name')->get();
        return $list;
    }

}