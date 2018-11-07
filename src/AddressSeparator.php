<?php

namespace Tinson\Tool;

/**
 * 中文地址省市区+姓名+电话+邮编分离
 * @author Tinson Ho
 */
class AddressSeparator
{
    use InstanceTrait;

    private static $data = [];

    const ENCODING = "utf-8";

    protected function __construct()
    {
        if (!self::$data) {
            self::$data = include __DIR__ . '/config/address.php';
        }
    }

    /**
     * 分离中文省市区+姓名+电话+邮编
     * $address = "张三,13001277920  335500  大理八大胡同42号";
     * $arr = AddressSeparator::getInstance()->handle($address);
     * @param $str
     * @return array
     */
    public function handle($str)
    {
        $str = strip_tags($str);
        $arr = preg_split("/[\s,]+/", $str, 10);
        $data = [
            'name' => null,
            'phone' => null,
            'post_code' => null
        ];
        $maxLen = 0;
        $maxKey = 0;
        foreach ($arr as $key => $val) {
            if ($this->isChinesePhone($val)) {
                $data['phone'] = $val;
                unset($arr[$key]);
                if (!empty($arr[$key - 1])) {
                    if ($this->fuzzyMatchChineseName($arr[$key - 1])) {
                        $data['name'] = $arr[$key - 1];
                        unset($arr[$key - 1]);
                    }
                } else if (!empty($arr[$key + 1])) {
                    if($this->fuzzyMatchChineseName($arr[$key + 1])){
                        $arr['name'] = $arr[$key + 1];
                        unset($arr[$key + 1]);
                    }
                }
            } else if ($this->isChinesePostCode($val)){
                $data['post_code'] = $val;
                unset($arr[$key]);
            } else {
                $len = strlen($val);
                if($len > $maxLen) {
                    $maxLen = $len;
                    $maxKey = $key;
                }
            }
        }

        if($maxLen >= 30){
            $address = $this->handleAddress($arr[$maxKey]);
        }

        if (!$address['city']) {
            $address = implode("", $arr);
            $address = $this->handleAddress($address);
            if (!$address['city'] && $maxLen < 30) {
                $address = $this->handleAddress($arr[$maxKey]);
            }
        }

        return array_merge($data, $address);
    }

    /**
     * 模糊匹配中文姓名
     * @param $str
     * @return bool
     */
    public function fuzzyMatchChineseName($str)
    {
        if ($this->isChineseName($str)) {
            $tmp = $this->handleAddress($str, true);
            return !$tmp['province'];
        }
        return false;
    }

    /**
     * 是否中文姓名
     * @param $str
     * @return bool
     */
    public function isChineseName($str)
    {
        return (bool)preg_match('/^[\x{4e00}-\x{9fa5}]{2,7}$/u', $str);
    }

    /**
     * 是否电话号码+手机号
     * @param $phone
     * @return bool
     */
    public function isChinesePhone($str)
    {
        return preg_match('/^(0[0-9]{2,3}/-)?([2-9][0-9]{6,7})+(/-[0-9]{1,4})?$/', $str) ||
            preg_match('/^1\d{10}$/', $str);
    }

    /**
     * @param $str
     * @return bool
     */
    public function isChinesePostCode($str)
    {
        return is_numeric($str) && strlen($str) == 6;
    }

    /**
     * 分离中文地址省市区
     * @param $address
     * @return array
     */
    public function handleAddress($address, $ignoreOrderSearch = false)
    {
        $start = 0;
        $arr = [null, null, null, null];
        $cache = [];

        if ($this->_sub($address, 0, 2) == "中国") {
            $start = 2;
        }

        //遍历省份逐级查找
        $this->_foreach(self::$data, $arr, $cache, $start, $address);

        if (!$ignoreOrderSearch) {
            //遍历市行政级别逐级查找，自动补全省份
            $tier = 0;
            if (!isset($arr[$tier])) {
                foreach (self::$data as $value) {
                    if (isset($value['s'])) {
                        $isDirect = $this->_isDirect($value['n'], $tier);
                        $tier = 1;
                        if ($isDirect) {
                            $tier = 2;
                        }
                        $this->_foreach($value['s'], $arr, $cache, $start, $address, $tier);
                        if (isset($arr[$tier])) {
                            $arr[$tier - 1] = $value['n'];
                            if ($isDirect) {
                                $arr[$tier - 2] = $value['n'];
                            }
                            break;
                        }
                    }
                }
            }

            //遍历县行政级别逐级查找，自动补全省市
            $tier = 0;
            if (!isset($arr[$tier])) {
                foreach (self::$data as $value) {
                    if (isset($value['s'])) {
                        foreach ($value['s'] as $val) {
                            if (isset($val['s'])) {
                                $tier = 2;
                                $this->_foreach($val['s'], $arr, $cache, $start, $address, $tier);
                                if (isset($arr[$tier])) {
                                    $arr[$tier - 1] = $val['n'];
                                    $arr[$tier - 2] = $value['n'];
                                    break 2;
                                }
                            }
                        }

                    }
                }
            }
        }


        $newArr = [
            'province' => $arr[0],
            'city' => $arr[1],
            'county' => $arr[2],
            'address' => null
        ];

        if ($start !== false) {
            $newArr['address'] = $this->_sub($address, $start, $this->_len($address) - $start);
        }

        return $newArr;
    }

    /**
     * 遍历查找
     * @param $data
     * @param $arr
     * @param $cache
     * @param $start
     * @param $address
     * @param int $tier
     */
    private function _foreach(&$data, &$arr, &$cache, &$start, $address, $tier = 0)
    {
        if ($tier >= 3) {
            return;
        }
        foreach ($data as $value) {
            $res = $this->_find($address, $start, $cache, $value);
            if ($res) {
                $start += $res[1];
                $cache = [];
                $arr[$tier] = $value['n'];
                //是否直辖市
                $isDirect = $this->_isDirect($value['n'], $tier);
                if ($isDirect) {
                    $tier += 1;
                    $arr[$tier] = $value['n'];
                }

                if (isset($value['s'])) {
                    if (!($start = $this->_step($address, $start))) {
                        return;
                    }
                    $cache = [];
                    $this->_foreach($value['s'], $arr, $cache, $start, $address, $tier + 1);

                    if ($tier + 2 < 3 && !isset($arr[$tier + 1])) {
                        foreach ($value['s'] as $val) {
                            if (isset($val['s'])) {
                                $this->_foreach($val['s'], $arr, $cache, $start, $address, $tier + 2);
                                if (isset($arr[$tier + 2])) {
                                    $arr[$tier + 1] = $val['n'];
                                    break;
                                }
                            }
                        }
                    }
                }
                break;
            }
        }
    }

    private function _find($address, $start, &$cache, $value)
    {
        $res = $this->_findByName($address, $start, $cache, $value['n']);
        if ($res) {
            return $res;
        }
        if (isset($value['ns'])) {
            return $this->_findByName($address, $start, $cache, $value['ns']);
        }
        return $res;
    }

    private function _findByName($address, $start, &$cache, $name)
    {
        $res = $this->_findByWholeName($address, $start, $cache, $name);
        if ($res) {
            return $res;
        }
        $shortName = $this->_getShortName($name);
        if ($shortName != $name && ($len = $this->_len($shortName)) >= 2) {
            return $this->_findByWholeName($address, $start, $cache, $shortName, $len);
        }
    }

    private function _findByWholeName($address, $start, &$cache, $name, $len = null)
    {
        if (!isset($len)) {
            $len = $this->_len($name);
        }

        if (!isset($cache[$len])) {
            $cache[$len] = $this->_sub($address, $start, $len);
        }

        if ($cache[$len] == $name) {
            return [$name, $len];
        }
        return false;
    }


    /**
     * 字符串按字符截取
     * @param $str
     * @param $start
     * @param $length
     * @return string
     */
    private function _sub($str, $start, $length)
    {
        return mb_substr($str, $start, $length, self::ENCODING);
    }

    /**
     * 游标前进
     * @param $address
     * @param $start
     * @return int|bool
     */
    private function _step($address, $start)
    {
        while (true) {
            $next = $this->_sub($address, $start, 1);
            if ($next === "") {
                return false;
            }
            if ($this->_isSpecialChar($next)) {
                $start++;
            } else {
                break;
            }
        }
        return $start;
    }

    /**
     * 字符长度
     * @param $str
     * @return int
     */
    private function _len($str)
    {
        return mb_strlen($str, self::ENCODING);
    }

    /**
     * 是否为特殊字符
     * @param $char
     * @return bool
     */
    private function _isSpecialChar($char)
    {
        return in_array($char, [
            ",", " ", "\n", "\t"
        ]);
    }

    /**
     * 短称
     * @param $name
     * @return string
     */
    private function _getShortName($name)
    {
        $lastChar = $this->_sub($name, -1, null);
        $offset = -1;
        switch ($lastChar) {
            case '省':
            case '市':
            case '县':
            case '镇':
                return $this->_sub($name, 0, $offset);
            case '州':
                $sub = $this->_sub($name, -2, null);
                if ($sub == '治州') {
                    $offset = -3;
                }
                return $this->_sub($name, 0, $offset);
            case '区':
                $sub = $this->_sub($name, -2, null);
                if ($sub == '地区') {
                    $offset = -2;
                } else if ($sub == '治区') {
                    $offset = -3;
                }
                return $this->_sub($name, 0, $offset);
        }
        return $name;
    }


    /**
     * 是否直辖市
     * @param $name
     * @param $tier
     * @return bool
     */
    private function _isDirect($name, $tier)
    {
        return $tier == 0 && $this->_sub($name, -1, null) == '市' ? 1 : 0;
    }

}