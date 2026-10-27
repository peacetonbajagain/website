<?php
namespace Travo\Admin\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
    protected $table = 'pages';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['title', 'slug', 'content', 'meta_title', 'meta_desc', 'status'];
}
