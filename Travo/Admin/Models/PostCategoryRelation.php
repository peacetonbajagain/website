<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class PostCategoryRelation extends Model
{
    protected $table = 'post_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['post_id', 'category_id'];
}