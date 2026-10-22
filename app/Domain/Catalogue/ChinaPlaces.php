<?php

namespace App\Domain\Catalogue;

/** English names for the Chinese provinces and cities that appear in the university catalogue. */
class ChinaPlaces
{
    public const PROVINCES = [
        '北京' => 'Beijing', '天津' => 'Tianjin', '上海' => 'Shanghai', '重庆' => 'Chongqing', '河北' => 'Hebei', '山西' => 'Shanxi',
        '辽宁' => 'Liaoning', '吉林' => 'Jilin', '黑龙江' => 'Heilongjiang', '江苏' => 'Jiangsu', '浙江' => 'Zhejiang', '安徽' => 'Anhui',
        '福建' => 'Fujian', '江西' => 'Jiangxi', '山东' => 'Shandong', '河南' => 'Henan', '湖北' => 'Hubei', '湖南' => 'Hunan',
        '广东' => 'Guangdong', '海南' => 'Hainan', '四川' => 'Sichuan', '贵州' => 'Guizhou', '云南' => 'Yunnan', '陕西' => 'Shaanxi',
        '甘肃' => 'Gansu', '青海' => 'Qinghai', '内蒙古' => 'Inner Mongolia', '广西' => 'Guangxi', '西藏' => 'Tibet (Xizang)',
        '宁夏' => 'Ningxia', '新疆' => 'Xinjiang',
    ];

    /** city => [English name, province (Chinese)] */
    public const CITIES = [
        '上海' => ['Shanghai', '上海'], '北京' => ['Beijing', '北京'], '天津' => ['Tianjin', '天津'], '重庆' => ['Chongqing', '重庆'],
        '乌鲁木齐' => ['Urumqi', '新疆'], '石河子' => ['Shihezi', '新疆'], '佳木斯' => ['Jiamusi', '黑龙江'], '哈尔滨' => ['Harbin', '黑龙江'],
        '牡丹江' => ['Mudanjiang', '黑龙江'], '黑河' => ['Heihe', '黑龙江'], '齐齐哈尔' => ['Qiqihar', '黑龙江'], '保定' => ['Baoding', '河北'],
        '石家庄' => ['Shijiazhuang', '河北'], '秦皇岛' => ['Qinhuangdao', '河北'], '兰州' => ['Lanzhou', '甘肃'], '南京' => ['Nanjing', '江苏'],
        '南通' => ['Nantong', '江苏'], '徐州' => ['Xuzhou', '江苏'], '扬州' => ['Yangzhou', '江苏'], '无锡' => ['Wuxi', '江苏'],
        '苏州' => ['Suzhou', '江苏'], '镇江' => ['Zhenjiang', '江苏'], '南宁' => ['Nanning', '广西'], '桂林' => ['Guilin', '广西'],
        '南昌' => ['Nanchang', '江西'], '景德镇' => ['Jingdezhen', '江西'], '赣州' => ['Ganzhou', '江西'], '厦门' => ['Xiamen', '福建'],
        '福州' => ['Fuzhou', '福建'], '泉州' => ['Quanzhou', '福建'], '武夷山' => ['Wuyishan', '福建'], '合肥' => ['Hefei', '安徽'],
        '芜湖' => ['Wuhu', '安徽'], '黄山' => ['Huangshan', '安徽'], '吉林' => ['Jilin City', '吉林'], '四平' => ['Siping', '吉林'],
        '长春' => ['Changchun', '吉林'], '延吉' => ['Yanji', '吉林'], '呼和浩特' => ['Hohhot', '内蒙古'], '通辽' => ['Tongliao', '内蒙古'],
        '咸阳' => ['Xianyang', '陕西'], '杨凌' => ['Yangling', '陕西'], '西安' => ['Xi\'an', '陕西'], '大理' => ['Dali', '云南'],
        '昆明' => ['Kunming', '云南'], '大连' => ['Dalian', '辽宁'], '抚顺' => ['Fushun', '辽宁'], '沈阳' => ['Shenyang', '辽宁'],
        '锦州' => ['Jinzhou', '辽宁'], '阜新' => ['Fuxin', '辽宁'], '葫芦岛' => ['Huludao', '辽宁'], '鞍山' => ['Anshan', '辽宁'],
        '太原' => ['Taiyuan', '山西'], '宁波' => ['Ningbo', '浙江'], '杭州' => ['Hangzhou', '浙江'], '温州' => ['Wenzhou', '浙江'],
        '舟山' => ['Zhoushan', '浙江'], '金华' => ['Jinhua', '浙江'], '宜昌' => ['Yichang', '湖北'], '武汉' => ['Wuhan', '湖北'],
        '荆州' => ['Jingzhou', '湖北'], '广州' => ['Guangzhou', '广东'], '深圳' => ['Shenzhen', '广东'], '珠海' => ['Zhuhai', '广东'],
        '汕头' => ['Shantou', '广东'], '开封' => ['Kaifeng', '河南'], '郑州' => ['Zhengzhou', '河南'], '成都' => ['Chengdu', '四川'],
        '拉萨' => ['Lhasa', '西藏'], '济南' => ['Jinan', '山东'], '淄博' => ['Zibo', '山东'], '烟台' => ['Yantai', '山东'],
        '青岛' => ['Qingdao', '山东'], '海口' => ['Haikou', '海南'], '湘潭' => ['Xiangtan', '湖南'], '长沙' => ['Changsha', '湖南'],
        '西宁' => ['Xining', '青海'], '贵阳' => ['Guiyang', '贵州'], '银川' => ['Yinchuan', '宁夏'],
    ];

    public static function province(?string $zh): ?string
    {
        $zh = trim((string) $zh);

        return $zh === '' ? null : (self::PROVINCES[$zh] ?? null);
    }

    /** "广州、珠海、深圳", "阜新；辽宁, 葫芦岛", "南宁市" -> English list, plus the Chinese province of the first known city. */
    public static function cities(?string $zh): array
    {
        $parts = preg_split('/[、；;,，\/]+/u', (string) $zh) ?: [];
        $en = [];
        $province = null;
        foreach ($parts as $part) {
            $part = trim(preg_replace('/市$/u', '', trim($part)));
            if ($part === '' || isset(self::PROVINCES[$part])) {
                continue; // stray province name inside a city list
            }
            if (isset(self::CITIES[$part])) {
                $en[] = self::CITIES[$part][0];
                $province ??= self::CITIES[$part][1];
            }
        }

        return ['city' => $en ? implode(', ', $en) : null, 'province_zh' => $province];
    }
}
