<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class UploadModel extends Model
{
	// Model
	protected $table      = 'uploads';
	protected $primaryKey = 'id';

	protected $returnType = 'array';
	protected $useSoftDeletes = false;
	protected $allowedFields = [
		'path', 'name', 'mime_type', 'width', 'height', 'uploaded_by', 'thumbnail_path',
		'file_name', 'file_original_name', 'user_id', 'file_size', 'extension', 'type', 'upload_path', 'external_link',
	];
	protected $useTimestamps = true;
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';


	public function getAll($keyword = null)
	{
		$builder = $this->db->table($this->table);
		$builder->like('file_name', $keyword);
		$builder->orderBy('created_at', 'DESC');
		$builder->limit(10);
		$query = $builder->get();
		return $query->getResultArray();
	}

	public function getByUserId($userId)
	{
		$builder = $this->db->table($this->table);
		$builder->where('user_id', $userId);
		$query = $builder->get();
		return $query->getResultArray();
	}

	public function search($keyword)
	{
		$builder = $this->db->table($this->table);
		$builder->like('file_name', $keyword);
		$builder->limit(21);
		$builder->orderBy('created_at', 'DESC');
		$query = $builder->get();
		return $query->getResultArray();
	}

	public static function libraryImages(): array
	{
		$model = new self();
		$images = $model->where('type', 'image')->orderBy('created_at', 'DESC')->findAll(250);

		return self::formatLibraryItems($images);
	}

	public static function libraryItems(string $search = '', string $type = ''): array
	{
		$model = new self();
		$builder = $model->orderBy('created_at', 'DESC');
		if (\in_array($type, ['image', 'video', 'pdf'], true)) {
			$builder->where('type', $type);
		}

		$words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		foreach ($words as $word) {
			$builder->groupStart()
				->like('file_name', $word)
				->orLike('file_original_name', $word)
				->groupEnd();
		}

		return self::formatLibraryItems($builder->findAll(250));
	}

	private static function formatLibraryItems(array $items): array
	{
		foreach ($items as &$image) {
			$image['name'] = $image['file_original_name'] ?: ($image['file_name'] ?? $image['name'] ?? 'Media file');
			$path = $image['path'] ?? '';
			$image['url'] = preg_match('#^https?://#i', $path) ? $path : base_url($path);
			$image['width'] = (int) ($image['width'] ?? 0);
			$image['height'] = (int) ($image['height'] ?? 0);
			$image['size'] = (int) ($image['file_size'] ?? 0);
		}
		unset($image);

		return $items;
	}

	public function idOnly()
	{
		$builder = $this->db->table($this->table);
		$builder->select('id');
		$builder->orderBy('created_at', 'DESC');
		//$builder->limit(10);
		$query = $builder->get();
		return $query->getResultArray();
	}

    /**
     * Regenerate thumbnails for a specific image ID or array of IDs.
	*
     * @param int|array|null $id Optional ID(s) to regenerate. If null, regenerates all.
     * @return array Result stats ['processed' => int, 'generated' => int, 'errors' => array]
     */
    /**
     * Regenerate thumbnails for a specific image ID or array of IDs.
	*
     * @param int|array|null $id Optional ID(s) to regenerate. If null, regenerates all.
     * @param array|null $imageData Optional pre-fetched image data to avoid DB query if processing single row.
     * @return array Result stats ['processed' => int, 'generated' => int, 'errors' => array]
     */
}
