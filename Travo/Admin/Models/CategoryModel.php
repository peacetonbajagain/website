<?php
namespace Travo\Admin\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'blog_categories';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['parent_id', 'sort_order', 'on_menu', 'title', 'slug', 'status'];
}
