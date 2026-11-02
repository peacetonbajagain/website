<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table            = 'activity_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['user_id', 'user_name', 'action', 'module', 'target_id', 'details', 'ip_address', 'created_at'];
    protected $useTimestamps    = false; 
    protected $createdField     = 'created_at';
}
