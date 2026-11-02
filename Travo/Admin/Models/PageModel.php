<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class PageModel extends Model
{
  protected $table = 'pages';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $useSoftDeletes = false;
  protected $allowedFields = ['title', 'content', 'slug', 'meta_title', 'meta_desc', 'created_at', 'updated_at', 'status'];
  protected $useTimestamps = true;
  protected $createdField = 'created_at';
  protected $updatedField = 'updated_at';



  public function isPublished()
  {
    return $this->publishe_at !== null || strtotime($this->published_at) <= time();
  }
}
