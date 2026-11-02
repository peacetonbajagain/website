<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class TagModel extends Model
{
    protected $table = 'tags';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'slug'];

    public function normalizeNames(array $names): array
    {
        $normalized = [];
        foreach ($names as $name) {
            $name = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $name))), 0, 100);
            if ($name !== '') {
                $normalized[mb_strtolower($name)] = $name;
            }
        }

        return array_values($normalized);
    }

    public function findOrCreate(string $name): int
    {
        $existing = $this->where('name', $name)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $baseSlug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)), '-');
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'tag-' . substr(sha1($name), 0, 10);
        $slug = $baseSlug;
        $suffix = 1;
        while ($this->where('slug', $slug)->first() !== null) {
            $slug = $baseSlug . '-' . ++$suffix;
        }

        $this->insert(['name' => $name, 'slug' => $slug]);

        return (int) $this->getInsertID();
    }

    public function getWithCounts(int $limit = 30): array
    {
        return $this->select('tags.*, COUNT(post_tags.post_id) AS post_count')
            ->join('post_tags', 'post_tags.tag_id = tags.id', 'left')
            ->groupBy('tags.id')
            ->orderBy('post_count', 'DESC')
            ->findAll(max(1, $limit));
    }

    public function search(string $query, int $limit = 15): array
    {
        return $this->like('name', trim($query))
            ->orderBy('name', 'ASC')
            ->findAll(max(1, $limit));
    }
}