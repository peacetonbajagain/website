<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class PostTagModel extends Model
{
    protected $table = 'post_tags';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['post_id', 'tag_id'];

    public function replaceForPost(int $postId, array $tagIds): void
    {
        $this->where('post_id', $postId)->delete();
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn (int $id): bool => $id > 0)));
        if ($tagIds === []) {
            return;
        }

        $this->insertBatch(array_map(static fn (int $tagId): array => [
            'post_id' => $postId,
            'tag_id' => $tagId,
        ], $tagIds));
    }

    public function getTagsForPost(int $postId): array
    {
        return $this->select('tags.*')
            ->join('tags', 'tags.id = post_tags.tag_id')
            ->where('post_tags.post_id', $postId)
            ->orderBy('tags.name', 'ASC')
            ->findAll();
    }
}