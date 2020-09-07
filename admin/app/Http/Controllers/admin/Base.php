<?php
/**
 * 全局的父类公共方法
 */
namespace App\Http\Controllers\admin;

//use App\Http\Controllers\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use mysql_xdevapi\Exception;
use App\Model\AdminActivityLog;


class Base extends Controller
{
    /** 对ajax请求过来的数据进行验证
     * @param $data     验证的数据
     * @param $rules    验证规则
     * @param $messages 自定义的错误信息
     * @return mixed    验证失败返回对应的错误信息，验证成功无返回值。
     */
    protected function  AjaxValidator($data,$rules, $messages){
        $validator = \Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            //将返回错误循环组装成字符串
            $arr = [];
            foreach ($validator->getMessageBag()->toArray() as $k=>$error){
                array_push($arr, $error[0]);
            }
            $str = implode(' ', $arr);
            return $this->error(2001,$str);
        }
    }

    /**
     * 返回失败的json数据
     * @param int $code 错误状态码
     * @param string $data  错误信息
     * @param string $url   跳转地址
     * @param bool $msg     错误状态描述
     * @return mixed
     */
    protected function error($code = 2002, $data = '', $url = '' ,$msg = false ){
        return \Response::json([
            'code'=>$code,
            'msg' => $msg,
            'data' => $data,
            'url' =>$url
        ]);
    }

    /**
     * 返回成功的json数据
     * @param string $data  返回的数据
     * @param int $count    layui中table数组的总条数
     * @param bool $reload   是否刷新父级页面，默认刷新
     * @param int $code     成功状态码
     * @param string $url   跳转地址
     * @param bool $msg     状态描述
     * @return mixed
     */
    protected function success($data = '', $count = 0 , $reload = true, $code = 2000, $url = '', $msg = true ){
        return \Response::json([
            'code'=>$code,
            'count'=>$count,
            'msg' => $msg,
            'data' => $data,
            'url' =>$url,
            'reload'=>$reload
        ]);
    }

    /**
     * 无限分类 得到多维数组
     * @param array $arr 需要无限分类的数组
     * @param string $id    数组id
     * @param string $pid   数组父id
     * @param string $child 保存子数组名称
     * @return array    无限分类好的数组。
     */
    protected function Tree($arr=array(),$id='id',$pid='pid',$child = "child"){
        $arr = json_decode(json_encode($arr),true);
        if (!is_array($arr) || empty($arr)){
            return array();
        }
        try{
            $key = array_column($arr,$id);
            $items = array_combine($key,$arr);
            foreach ($items as $k => $item){
                if (isset($items[$item[$pid]])){
//                    $items[$k]['level'] = $items[$item[$pid]][$child]['level']+1;
                    $items[$item[$pid]][$child][$item[$id]] = &$items[$k];
                }else{
//                    $items[$k]['level'] = 0;
                    $items[0][$child][$item[$id]] = &$items[$k];
                }
            }

        }catch (\Exception $e){
            return array();
        }
        return isset($items[0][$child]) ? $items[0][$child] : array();
    }

    /**
     * 无限分类 返回一个排好序二维数组
     * @param array $arr    需要无限分类的数组
     * @param string $id    数组id
     * @param string $pid   数组父id
     * @return array        无限分类好的数组。
     */
    protected function TreeTow($arr=array(),$id='id',$pid='pid'){
        $arr = json_decode(json_encode($arr),true);
        if (!is_array($arr) || empty($arr)){
            return array();
        }
        try{
            $items = [];
            foreach ($arr as $k=>$v){
                if ($v[$pid] != 0){
                    $key = array_column($items,$id);
                    $item = array_combine($key,$items);
                    $key = array_search($item[$v[$pid]],$items);
                    $v['level'] = $items[$key]['level'] + 1;
                    array_splice($items,$key+1,0,[$v]);
                }else{
                    $v['level'] = 0;
                    array_push($items,$v);

                }
            }
        }catch (\Exception $e){
            return array();
        }
        return $items;
    }

    /**
     * 图片上传
     * @param Request $request
     * @return mixed 返回该文件的访问地址
     */
    public function upload(Request $request){
        //验证文件是否被上传 验证文件是否有效
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            //获取上传的文件信息
            $file = $request->file('file');

            //获取文件大小
            $size = $file->getSize(); # 8016
            if ($size >= 2097152){
                return $this->error('2002','文件不得大于2mb');
            }
            //获取文件扩展名
            $extension = $file->extension(); # "png"

            if  (!in_array(strtolower($extension),['png','jpg','jpeg','gif'])){
                return $this->error('2002','文件类型不正确');
            }
            $fileName = date('Ymd',time());
            $time = date('YmdHis',time());
            $rand = rand(10000,99999);
            $name = $time . $rand .'.'. $extension;
            //文件保存，自定义文件名
            $storeAs = $file->storeAs('images/'.$fileName,$name,'public'); #参数一文件夹名，参数二文件名称，参数三选择上传配置
            return $this->success('/storage/'.$storeAs);
        }
    }

    //多个数组的笛卡尔积
    function combineDika() {
        $data = func_get_args();
        $data = current($data);
        $cnt = count($data);
        $result = array();
        $arr1 = array_shift($data);
        foreach($arr1 as $key=>$item){
            $result[] = array($item);
        }

        foreach($data as $key=>$item){
            $result = $this->combineArray($result,$item);
        }
        return $result;
    }

    //两个数组的笛卡尔积
    function combineArray($arr1,$arr2) {
        $result = array();
        foreach ($arr1 as $item1){
            foreach ($arr2 as $item2){
                $temp = $item1;
                $temp[] = $item2;
                $result[] = $temp;
            }
        }
        return $result;
    }

    /**
     * 从二维数组的一维数组中挑选一个键值做数组的健。
     * @param $arr
     * @param $key
     * @return array|false
     */
    function arrayColumnKey($arr,$key){
//        dd(count($arr) == count($arr, 1));
        if (!is_array($arr) ||  count($arr) == count($arr, 1)){
             throw new \Exception('不是二维数组123');
        }
        $keyAll = array_column($arr,$key);
        if (count($keyAll) != count($arr) ){
            throw new \Exception('作为keys的数组和作为values的数组的元素个数不一样');
        }
        return array_combine($keyAll,$arr);
    }

    function toArray($obj){
        return \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($obj),true);
    }

    /**
     * 清空redis中用户的用户拥有的群组列表数据
     */
    function delUserRedis(){
        $keys = Redis::keys('userGroup_*');
        if (!empty($keys)){
            foreach ($keys as $item){
                Redis::del($item);
            }
        }
    }

    /**
     * 向redis中用户拥有的群组列表添加新群组数据
     * @param $group [群组信息]
     * @param $users [要添加的用户列表]
     */
    function reUserGroupRedis($group,$users){
        foreach ($users as $k=>$v ){
            $userGroup = Redis::get('userGroup_'.$k);
            if (!empty($userGroup)){
                $userGroup = \GuzzleHttp\json_decode($userGroup,true);
                $userGroup[$group['id']]=$group;
                Redis::set('userGroup_'.$k,\GuzzleHttp\json_encode($userGroup));
            }
        }
    }

    /**
     * 向redis中用户拥有的群组列表删除群组数据
     * @param $group [群组信息]
     * @param $users [要添加的用户列表]
     */
    function delUserGroupRedis($group,$users){
        foreach ($users as $k=>$v ){
            $userGroup = Redis::get('userGroup_'.$k);
            if (!empty($userGroup)){
                $userGroup = \GuzzleHttp\json_decode($userGroup,true);
                unset($userGroup[$group['id']]);
                Redis::set('userGroup_'.$k,\GuzzleHttp\json_encode($userGroup));
            }
        }
    }
    /**
     * redis向用户列表添加用户信息
     * @param $identity
     * @param $data
     */
    function addUserListRedis($identity,$data){
        $userList = Redis::get('userList_'.$identity);
        if (!empty($userList)){
            $userList = \GuzzleHttp\json_decode($userList,true);
            $userList = array_merge($userList,[$data]);
            Redis::set('userList_'.$identity,\GuzzleHttp\json_encode($userList));
        }
    }

    /**
     * redis向用户列表删除用户信息
     * @param $identity
     * @param $id
     */
    function delUserListRedis($identity,$id){
        $userList = Redis::get('userList_'.$identity);
        if (!empty($userList)){
            $userList = \GuzzleHttp\json_decode($userList,true);
            foreach ($userList as $k=>$v){
                if ($v['id'] == $id){
                    unset($userList[$k]);
                    break;
                }
            }
            Redis::set('userList_'.$identity,\GuzzleHttp\json_encode($userList));
        }
    }

    /**
     * 记录管理员操作日志
     * @param $data
     * @return bool
     */
    public function activityLog($data){
        if (empty($data)){
            return false;
        }

        $activityLog = new AdminActivityLog();
        $activityLog->fillable(array_keys($data));
        $activityLog->fill($data);
        $activityLog->save();

    }

    public function dump(){

    }

}
