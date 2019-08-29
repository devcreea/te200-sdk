<?php
/**
 * Created by PhpStorm.
 * User: cree
 * Date: 2019/5/16
 * Time: 16:17
 */

namespace Te200;

use GuzzleHttp\Client;

class TeBase
{

    // 基础代理
    public $client = null;

    // 最终保存数据存储
    protected $datas = null;

    // 账号
    private $username = null;

    // 这个密码请查看curl请求加密后的
    private $password = null;

    private $ip = null;

    private $url = null;


    /**
     * 构造
     * TeBase constructor.
     */
    public function __construct()
    {
        $this->client = new Client(['cookies' => true]);

    }

    public function setLogin($ip, $username, $password)
    {
        $this->ip = $ip;
        // 拼接的固定路径
        $this->url = 'http://' . $ip .'/cgi/WebCGI?';
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    /**
     * 获取基础url
     * @param $str
     * @return string
     */
    public function baseUrl($str = null)
    {
        return $this->url . $str;
    }

    /**
     * 获取cookieJar 这个傻逼跳转是利用current的
     *
     * @param array $array
     * @return \GuzzleHttp\Cookie\CookieJar
     * @throws \Exception
     */
    protected function baseCookie($array = [])
    {
        $datas  = [
            'td_cookie' => '436183129',
            'language' => 'zh_CN',
            'OsVer' => '17.18.0.7',
            'Series' => '',
            'Product' => 'TE200',
            'current' => 'pbx',
            'loginname' => $this->getUsername(),
            'defaultpwd' => 'bbb',
            'password' => $this->getPassword()
        ];
        $datas = array_merge($datas, $array);
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        return $cookieJar->fromArray($datas, $this->getIp());

    }

    /**
     * 返回账号
     *
     * @return string
     * @throws \Exception
     */
    public function getUsername()
    {
        if (empty($this->username)) throw new \Exception('请检查你的账号', 422);
        return $this->username;
    }

    /**
     * 返回密码
     *
     * @return string
     * @throws \Exception
     */
    public function getPassword()
    {
        if (empty($this->password)) throw new \Exception('请检查你的密码', 422);
        return $this->password;
    }
    /**
     * 获取IP
     *
     * @return string
     * @throws \Exception
     */
    public function getIp()
    {
        if (empty($this->ip)) throw new \Exception('请检查IP', 422);
        return $this->ip;
    }


    public function get()
    {
        return $this->datas;
    }

}