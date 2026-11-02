<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class StorySubmissionModel extends Model
{
    protected $table = 'story_submissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id', 'title', 'excerpt', 'content', 'image_path', 'image_name', 'status', 'post_id',
    ];
}