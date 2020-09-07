<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Mydump extends Model
{
    use LogsActivity;
    protected $table = 'mydump';
    protected $primaryKey = 'id';
    public $timestamps = true;
    const CREATED_AT  = 'create_time';


}