<?php
namespace Travo\Admin\Models;

use CodeIgniter\Model;

class CommentModel extends Model
{
    protected $table = 'comments';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['post_id', 'parent_id', 'author_name', 'author_email', 'body', 'status', 'ip_address'];
}
