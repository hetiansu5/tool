<?php

namespace Tinson\Tool;

/**
 * 验证类
 */
class Validate
{

    /**
     * 是否邮箱
     * @param string $email
     * @return bool
     */
    public static function isEmail($email)
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * 是否纬度
     * @param mixed $num
     * @return bool
     */
    public static function isLatitude($num)
    {
        return is_numeric($num) && $num >= -90 && $num <= 90;
    }

    /**
     * 是否经度
     * @param mixed $num
     * @return bool
     */
    public static function isLongitude($num)
    {
        return is_numeric($num) && $num >= -180 && $num <= 180;
    }

    /**
     * 判断是否符合身份证号码要求（一定要18位，15位的老身份已经废除）
     * @param string $id
     * @return bool
     */
    public static function isIdentityNumber($id)
    {
        $id = strtoupper($id);
        $reg = "/^(\d{6})(\d{4})(\d{2})(\d{2})(\d{3})([0-9]|X)$/";
        $arrSplit = array();
        $result = preg_match($reg, $id, $arrSplit);
        if (!$result) {
            return false;
        }

        $birthDate = $arrSplit[2] . '-' . $arrSplit[3] . '-' . $arrSplit[4];
        $birthTime = strtotime($birthDate);
        if (!$birthTime) { //检查生日日期是否正确
            return false;
        } else if ($birthTime > time()) { //超过当前日期的也不符合
            return false;
        }

        //检验18位身份证的校验码是否正确。
        //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
        $coefficient = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $remainderMap = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sign = 0;
        for ($i = 0; $i < 17; $i++) {
            $b = (int)$id{$i};
            $w = $coefficient[$i];
            $sign += $b * $w;
        }
        $n = $sign % 11;
        $valNum = $remainderMap[$n];
        if ($valNum != substr($id, 17, 1)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 是否中国大陆的手机号
     * @param string $phone
     * @return string
     */
    public static function isTelPhone($phone)
    {
        return preg_match("/1[3458]{1}\d{9}$/", $phone);
    }

    /**
     * 是否是IPv4
     * fuzzyMatch用于匹配 127.0.0.* 带*的格式
     * @param string $ip
     * @param bool $fuzzyMatch
     * @return bool
     */
    public static function isIPv4($ip, $fuzzyMatch = false)
    {
        if ($fuzzyMatch) {
            $ipMap = explode('.', $ip);
            if (!$ipMap || count($ipMap) != 4) {
                return false;
            }
            foreach ($ipMap as $num) {
                if ($num == '*') {
                    continue;
                }

                if (!is_numeric($num) || $num < 0 || $num > 255 || strpos($num, " ") !== false) {
                    return false;
                }
            }
            return true;
        } else {
            return (bool)filter_var($ip, FILTER_VALIDATE_IP);
        }
    }

    /**
     * 是否md5
     * @param string $md5
     * @return bool
     */
    public static function isMd5($md5 = '')
    {
        return strlen($md5) == 32 && ctype_xdigit($md5);
    }

    /**
     * 是否纯数字类型
     * @param string $val
     * @return bool
     */
    public static function isIntValue($val = '')
    {
        if (is_int($val)) {
            return true;
        }
        if (is_string($val)) {
            return preg_match("/^\d+$/", $val) ? true : false;
        }
        return false;
    }

}