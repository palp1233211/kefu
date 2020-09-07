<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    use LogsActivity;
    protected $table = 'group';
    protected $primaryKey = 'id';
    public $timestamps = false;
    const CREATED_AT  = 'create_date';
    /**
     * 多对多关联
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function User(){
        return $this->belongsToMany('App\Model\User','group_users','gid','uid');
    }

    /**
     * 一对多关联
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany('App\Model\GroupUsers','gid','id');
    }


    public function getDescriptionForEvent(string $eventName): string
    {
        switch ($eventName) {
            case 'created':
                $description = '话题被创建';
                break;
            case 'updated':
                $description = '话题被修改';
                break;
            case 'deleted':
                $description = '话题被删除';
                break;
            default:
                $description = $eventName;
                break;
        }
        return $description;
    }

}