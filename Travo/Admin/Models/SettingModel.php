<?php

namespace Travo\Admin\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
  protected $table = 'settings';
  protected $primaryKey = 'id';
  protected $returnType = 'array';
  protected $allowedFields = ['setting_name', 'setting_value'];


  public function getSettings()
  {
    $builder = $this->db->table($this->table);
    $query = $builder->get();
    $settings = $query->getResult();
    foreach ($settings as $setting) {
      $pre[addslashes($setting->setting_name)] = addslashes($setting->setting_value);
    }
    return (object) $pre;
  }



  public function update_config($data)
  {
    $success = true;
    foreach ($data as $key => $value) {
      if (!$this->savesetting($key, $value)) {
        $success = false;
        break;
      }
    }
    return $success;
  }
  public function savesetting($key, $value)
  {
    $config_data = array(
      'setting_name' => $key,
      'setting_value' => $value,
    );
    $builder = $this->db->table($this->table);
    $builder->where('setting_name', $key);
    return $builder->update($config_data);
  }
  public function getBySettingName($setting_name)
  {
    $builder = $this->db->table($this->table);
    $builder->where('setting_name', $setting_name);
    $query = $builder->get();
    return $query->getRowArray();
  }
}
