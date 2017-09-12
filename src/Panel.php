<?php
/**
 * Created by xujif.
 * User: i
 * Date: 2017/5/8 0008
 * Time: 11:01
 */

namespace Xujif\PanelAdmin;


class Panel
{
    protected $config;


    public function __construct($config)
    {
        if (is_callable($config)) {
            $this->config = $config();
        } else {
            $this->config = $config;
        }
    }

    protected function arrayOnly($arr, $keys)
    {
        return array_intersect_key($arr, array_flip((array)$keys));
    }

    protected function arrayPluck($arr, $key)
    {
        $result = [];
        foreach ($arr as $item) {
            $result[] = $item[$key];
        }
        return $result;
    }


    protected function prepareMenu($menuArr)
    {
        $arr = $this->arrayOnly($menuArr, ['name', 'path','pageType', 'permission', 'opened']);
        if (isset($menuArr['children'])) {
            $arr['children'] = array_map([$this, 'prepareMenu'], $menuArr['children']);
        }
        return $arr;
    }

    protected function searchMenu($path, $menuArr)
    {
        foreach ($menuArr as $m) {
            if (isset($m['children'])) {
                $match = $this->searchMenu($path, $m['children']);
                if ($match) {
                    return $match;
                }
            } else if ($m['path'] === $path) {
                if (isset($m['pageConfig']) && is_callable($m['pageConfig'])) {
                    $m['pageConfig'] = $m['pageConfig']();
                }
                return $m;
            }
        }
        return null;
    }

    protected function getSettingDefs()
    {
        return $this->config['settings'];
    }

    protected function resolveSettings($keys)
    {
        $defs = $this->getSettingDefs();
        $result = [];
        foreach ($keys as $k) {
            $setting = $defs[$k];
            if (is_null($setting)) {
                continue;
            }else if(is_callable($setting)){
                $setting = $setting();
            }
            $setting['name'] = $k;
            $result[] = $setting;
        }
        return $result;
    }

    public function getPageDef($path)
    {
        $path = '/' . ltrim($path, '/');
        $pageDef = $this->searchMenu($path, $this->config['menus']);
        return $pageDef;
    }

    public function getModelSettings($model)
    {
        $m = $this->getModelPageConfig($model);
        $keys = $this->arrayPluck($m['config']['settings'], 'name');
        $values = $this->getSettings($keys);
        return $values;
    }

    public function setModelSettings($model, $values)
    {
        $m = $this->getModelPageConfig($model);
        $keys = $this->arrayPluck($m['config']['settings'], 'name');
        $values = $this->arrayOnly($values, $keys);
        return $this->updateSettings($values);
    }

    public function getModelConfig($modelName)
    {
        $m = $this->config['models'][$modelName];
        if (is_callable($m)) {
            return $m();
        } else {
            return $m;
        }
    }

    public function getPageConfig($path)
    {
        $def = $this->getPageDef($path);
        if (is_null($def)) {
            return null;
        }
        switch (strtolower($def['pageType'])) {
            case 'modeladmin':
                $conf = $this->getModelPageConfig($def['model']);
                return $this->arrayOnly($conf, ['config', 'component']);
            case 'widget':
                $conf = $this->getWidgetConfig($def['widget']);
                if (is_callable($conf['data'])) {
                    $conf['config']['data'] = $conf['data']();
                } else {
                    $conf['config']['data'] = $conf['data'];
                }
                return $conf;
            case 'iframe':
            case 'frame':
                return [
                    'component' => 'iframe',
                    'config' => [
                        'url' => $def['url']
                    ]
                ];

        }
        return null;
    }

    public function getMenus()
    {
        $menus = $this->config['menus'];
        return array_map([$this, 'prepareMenu'], $menus);
    }

    public function getModelPageConfig($modelName)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        $result = $this->arrayOnly($m, ['component', 'config']);
        if (isset($result['config']['settings']) && !empty($result['config']['settings'])) {
            $result['config']['settings'] = $this->resolveSettings($result['config']['settings']);
        }
        $result['config']['model'] = $modelName;
        return $result;
    }

    public function getWidgetConfig($name)
    {
        if (!isset($this->config['widgets'][$name])) {
            return null;
        }
        $m = $this->config['widgets'][$name];
        return $m;
    }

    public function getSettings($names)
    {
        $defs = $this->getSettingDefs();
        return call_user_func_array($this->config['getSettings'], [$names, $defs]);
    }

    public function updateSettings($values)
    {
        $defs = $this->getSettingDefs();
        return call_user_func_array($this->config['updateSettings'], [$values, $defs]);
    }

    public function listModel($modelName, $params)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        $result =  $m['listModel']($params);
        $arrResult = json_decode(json_encode($result),true);
        $arrResult['draw'] = $params['draw'];
        return $arrResult;
    }

    public function actionModel($modelName, $action, $pk, $params = null)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        $handles = $m['actionHandles'];
        if (!isset($handles[$action])) {
            return new \BadMethodCallException('no action: ' . $action . ' defined');
        }
        return $handles[$action]($pk, $params);
    }

    public function getModel($modelName, $pk)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        return $m['getModel']($pk);
    }

    public function updateModel($modelName, $pk, $attrs)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        return $m['updateModel']($pk, $attrs);
    }

    public function globalActionModel($modelName, $action, $params = null)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        $handles = $m['globalActionHandles'];
        if (!isset($handles[$action])) {
            return new \BadMethodCallException('no action: ' . $action . ' defined');
        }
        return $handles[$action]($params);
    }

    public function createModel($modelName, $attrs)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        return $m['createModel']($attrs);
    }

    public function batchActionModel($modelName, $action, $pkList, $params = null)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        $handles = $m['batchActionHandles'];
        if (!isset($handles[$action])) {
            return new \BadMethodCallException('no action: ' . $action . ' defined');
        }
        return $handles[$action]($pkList, $params);
    }

    public function queryModelSelect($modelName, $field, $query)
    {
        $m = $this->getModelConfig($modelName);
        if (is_null($m)) {
            return null;
        }
        return $m['queryModelSelect']($field, $query);
    }
}